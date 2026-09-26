<?php
// cron_verificar_vencimentos.php
// Marca como 'expirado' as assinaturas vencidas há mais de DIAS_CARENCIA dias
// e avisa o vendedor por notificação no painel.
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador.
//
// Cron (Hostinger) - 1x por dia, 00:00:
// 0 0 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/src/scripts/cron_verificar_vencimentos.php
//
// Por que o plano_id NÃO volta para o Free aqui:
// o webhook invoice.paid (src/vendedor/webhook_stripe.php) reativa o status e
// renova o vencimento, mas não restaura o plano_id. Se este cron rebaixasse o
// plano e o Stripe conseguisse cobrar depois (retentativa), o vendedor ficaria
// pagando e preso no plano Free. Quando o Stripe desiste de cobrar, o evento
// customer.subscription.deleted já rebaixa o plano.

require_once __DIR__ . '/cron_utils.php';
cronIniciar('cron_vencimentos');

const DIAS_CARENCIA = 2;

cronLog('========================================');
cronLog('INICIANDO VERIFICAÇÃO DE VENCIMENTOS');
cronLog('========================================');

require_once __DIR__ . '/../conexao.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
    cronLog('✅ Conexão com banco estabelecida.');

    // =============================================
    // 1. VENDEDORES COM ASSINATURA VENCIDA HÁ MAIS DE DIAS_CARENCIA DIAS
    // =============================================
    $stmt = $db->prepare(
        "SELECT id, usuario_id, nome_comercial, status_assinatura, data_vencimento_assinatura
         FROM vendedores
         WHERE status_assinatura IN ('ativo', 'atrasado')
           AND data_vencimento_assinatura IS NOT NULL
           AND data_vencimento_assinatura < (NOW() - INTERVAL " . DIAS_CARENCIA . " DAY)"
    );
    $stmt->execute();
    $vencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($vencidos);
    cronLog("📊 $total vendedor(es) com assinatura vencida há mais de " . DIAS_CARENCIA . ' dias.');

    // =============================================
    // 2. EXPIRAR E NOTIFICAR CADA VENDEDOR
    // =============================================
    // O WHERE do UPDATE repete a condição do SELECT: se o webhook do Stripe
    // renovar a assinatura entre o SELECT e o UPDATE, o vendedor não é expirado.
    $stmtExpirar = $db->prepare(
        "UPDATE vendedores
         SET status_assinatura = 'expirado'
         WHERE id = ?
           AND status_assinatura IN ('ativo', 'atrasado')
           AND data_vencimento_assinatura < (NOW() - INTERVAL " . DIAS_CARENCIA . " DAY)"
    );
    $stmtNotificar = $db->prepare(
        "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) VALUES (?, ?, 'alerta', ?)"
    );

    $processados = 0;
    $ignorados = 0;
    $erros = 0;

    foreach ($vencidos as $vendedor) {
        $vendedorId = (int)$vendedor['id'];
        $nome = $vendedor['nome_comercial'] ?: 'ID ' . $vendedorId;
        $rotulo = "$nome (ID: $vendedorId, status: {$vendedor['status_assinatura']}, vencido em: {$vendedor['data_vencimento_assinatura']})";

        try {
            // Expiração e aviso gravados juntos: ou os dois, ou nenhum.
            $db->beginTransaction();

            $stmtExpirar->execute([$vendedorId]);
            if ($stmtExpirar->rowCount() === 0) {
                $db->rollBack();
                $ignorados++;
                cronLog("   ⏭️ $rotulo — assinatura alterada durante a execução, ignorado.");
                continue;
            }

            $stmtNotificar->execute([
                $vendedor['usuario_id'],
                'Sua assinatura expirou por falta de pagamento. Regularize para continuar com os benefícios do seu plano.',
                '/src/vendedor/gerenciar_assinatura',
            ]);

            $db->commit();
            $processados++;
            cronLog("   ✅ $rotulo — expirado e notificado.");

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $erros++;
            cronLog("   ❌ $rotulo — ERRO: " . $e->getMessage());
            error_log("CRON VENCIMENTOS: erro no vendedor ID $vendedorId: " . $e->getMessage());
        }
    }

    // =============================================
    // 3. RESUMO
    // =============================================
    cronLog('========================================');
    cronLog("RESUMO: $total encontrado(s) | $processados expirado(s) | $ignorados ignorado(s) | $erros erro(s)");
    cronLog('FINALIZADO ' . ($erros > 0 ? 'COM ERROS' : 'COM SUCESSO'));
    cronLog('========================================');

    exit($erros > 0 ? 1 : 0);

} catch (Throwable $e) {
    cronLog('❌ ERRO CRÍTICO: ' . $e->getMessage());
    cronLog('   Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine());
    cronLog('FINALIZADO COM ERRO CRÍTICO');
    cronLog('========================================');
    error_log('CRON VENCIMENTOS - ERRO CRÍTICO: ' . $e->getMessage());
    exit(1);
}
