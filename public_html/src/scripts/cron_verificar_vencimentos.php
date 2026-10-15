<?php
// cron_verificar_vencimentos.php
// Rede de segurança do webhook do Stripe para assinaturas vencidas.
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador.
//
// Cron (Hostinger) - 1x por dia, 00:00:
// 0 0 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/src/scripts/cron_verificar_vencimentos.php
//
// REGRA ÚNICA DE EXPIRAÇÃO: quem decide se a assinatura acabou é o Stripe.
// - O webhook (src/vendedor/webhook_stripe.php) aplica as mudanças na hora:
//   invoice.paid renova, invoice.payment_failed marca 'atrasado' e
//   customer.subscription.deleted rebaixa para o Free com 'expirado'.
// - Este cron só age quando o vencimento passou há mais de DIAS_CARENCIA dias
//   sem renovação (sinal de que algum webhook se perdeu). Ele pergunta ao Stripe
//   qual é o status real da assinatura e aplica a mesma regra do webhook.
//   Assim o vendedor nunca é rebaixado enquanto o Stripe ainda tenta cobrar,
//   e quem deixou de pagar não fica com o plano pago para sempre.

require_once __DIR__ . '/cron_utils.php';
cronIniciar('cron_vencimentos');

const DIAS_CARENCIA = 2;
const ID_PLANO_FREE = 1;

cronLog('========================================');
cronLog('INICIANDO VERIFICAÇÃO DE VENCIMENTOS');
cronLog('========================================');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../config/StripeConfig.php';

/** Fim do período atual da assinatura (o campo mudou de lugar nas versões da API). */
function fimDoPeriodo($assinatura): ?string {
    $fim = $assinatura->current_period_end ?? ($assinatura->items->data[0]->current_period_end ?? null);
    return $fim ? date('Y-m-d H:i:s', $fim) : null;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
    cronLog('✅ Conexão com banco estabelecida.');

    \Config\StripeConfig::init();
    cronLog('✅ Stripe configurado.');

    // =============================================
    // 1. ASSINATURAS PAGAS VENCIDAS HÁ MAIS DE DIAS_CARENCIA DIAS
    // =============================================
    $stmt = $db->prepare(
        "SELECT id, usuario_id, nome_comercial, status_assinatura, data_vencimento_assinatura, stripe_subscription_id
         FROM vendedores
         WHERE plano_id > " . ID_PLANO_FREE . "
           AND status_assinatura IN ('ativo', 'atrasado')
           AND data_vencimento_assinatura IS NOT NULL
           AND data_vencimento_assinatura < (NOW() - INTERVAL " . DIAS_CARENCIA . " DAY)"
    );
    $stmt->execute();
    $vencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($vencidos);
    cronLog("📊 $total vendedor(es) com assinatura vencida há mais de " . DIAS_CARENCIA . ' dias.');

    // =============================================
    // 2. CONSULTAR O STRIPE E APLICAR O STATUS REAL
    // =============================================
    // Os UPDATEs repetem a condição do SELECT: se o webhook alterar o vendedor
    // durante a execução, o cron não sobrescreve.
    $condicao = "id = ? AND status_assinatura IN ('ativo', 'atrasado')
                 AND data_vencimento_assinatura < (NOW() - INTERVAL " . DIAS_CARENCIA . " DAY)";

    $stmtRenovar = $db->prepare(
        "UPDATE vendedores SET status_assinatura = 'ativo', data_vencimento_assinatura = ? WHERE $condicao"
    );
    $stmtAtrasar = $db->prepare(
        "UPDATE vendedores SET status_assinatura = 'atrasado' WHERE $condicao AND status_assinatura = 'ativo'"
    );
    $stmtEncerrar = $db->prepare(
        "UPDATE vendedores SET plano_id = " . ID_PLANO_FREE . ", status_assinatura = 'expirado', data_vencimento_assinatura = NULL WHERE $condicao"
    );
    $stmtNotificar = $db->prepare(
        "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) VALUES (?, ?, 'alerta', ?)"
    );

    $contagem = ['renovado' => 0, 'atrasado' => 0, 'encerrado' => 0, 'sem_mudanca' => 0, 'erro' => 0];

    foreach ($vencidos as $vendedor) {
        $vendedorId = (int)$vendedor['id'];
        $nome = $vendedor['nome_comercial'] ?: 'ID ' . $vendedorId;
        $rotulo = "$nome (ID: $vendedorId, vencido em: {$vendedor['data_vencimento_assinatura']})";

        try {
            // Status no Stripe. Sem assinatura no Stripe não há como estar pago.
            $statusStripe = 'canceled';
            $assinatura = null;
            if (!empty($vendedor['stripe_subscription_id'])) {
                try {
                    $assinatura = \Stripe\Subscription::retrieve($vendedor['stripe_subscription_id']);
                    $statusStripe = $assinatura->status;
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    // Assinatura inexistente no Stripe (apagada/conta de teste)
                    $statusStripe = 'canceled';
                }
            }

            $db->beginTransaction();

            if (in_array($statusStripe, ['active', 'trialing'], true) && ($novoFim = fimDoPeriodo($assinatura))) {
                // Pago no Stripe, mas o webhook invoice.paid não chegou
                $stmtRenovar->execute([$novoFim, $vendedorId]);
                $acao = $stmtRenovar->rowCount() ? 'renovado' : 'sem_mudanca';
                $mensagem = null;
            } elseif (in_array($statusStripe, ['past_due', 'incomplete'], true)) {
                // Stripe ainda está tentando cobrar: avisa, mas mantém o plano
                $stmtAtrasar->execute([$vendedorId]);
                $acao = $stmtAtrasar->rowCount() ? 'atrasado' : 'sem_mudanca';
                $mensagem = 'Não conseguimos cobrar a sua mensalidade. Atualize a forma de pagamento para não perder os benefícios do seu plano.';
            } else {
                // canceled, unpaid, incomplete_expired, paused ou sem assinatura
                $stmtEncerrar->execute([$vendedorId]);
                $acao = $stmtEncerrar->rowCount() ? 'encerrado' : 'sem_mudanca';
                $mensagem = 'Sua assinatura foi encerrada por falta de pagamento e sua conta voltou para o Plano Gratuito.';
            }

            if ($acao !== 'sem_mudanca' && $mensagem) {
                $stmtNotificar->execute([$vendedor['usuario_id'], $mensagem, '/src/vendedor/gerenciar_assinatura']);
            }

            $db->commit();
            $contagem[$acao]++;
            cronLog("   ✅ $rotulo — Stripe: $statusStripe → $acao.");

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $contagem['erro']++;
            cronLog("   ❌ $rotulo — ERRO: " . $e->getMessage());
            error_log("CRON VENCIMENTOS: erro no vendedor ID $vendedorId: " . $e->getMessage());
        }
    }

    // =============================================
    // 3. RESUMO
    // =============================================
    cronLog('========================================');
    cronLog("RESUMO: $total encontrado(s) | {$contagem['renovado']} renovado(s) | {$contagem['atrasado']} atrasado(s) | "
        . "{$contagem['encerrado']} encerrado(s) | {$contagem['sem_mudanca']} sem mudança | {$contagem['erro']} erro(s)");
    cronLog('FINALIZADO ' . ($contagem['erro'] > 0 ? 'COM ERROS' : 'COM SUCESSO'));
    cronLog('========================================');

    exit($contagem['erro'] > 0 ? 1 : 0);

} catch (Throwable $e) {
    cronLog('❌ ERRO CRÍTICO: ' . $e->getMessage());
    cronLog('   Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine());
    cronLog('FINALIZADO COM ERRO CRÍTICO');
    cronLog('========================================');
    error_log('CRON VENCIMENTOS - ERRO CRÍTICO: ' . $e->getMessage());
    exit(1);
}
