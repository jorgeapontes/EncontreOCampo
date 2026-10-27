<?php
// src/functions/pagamentos_functions.php
// Histórico dos eventos do Stripe (tabela pagamentos_stripe), mostrado na seção
// Pagamentos do master. Gravado pelo webhook (src/vendedor/webhook_stripe.php).
//
// Regras:
//   - Nunca grava dados de cartão nem o payload: só tipo, ids, valor e vendedor.
//   - O mesmo evento reenviado pelo Stripe atualiza a linha (evento_id único).
//   - Se a gravação falhar, o webhook segue normalmente: o erro vai só para o log.

require_once __DIR__ . '/../conexao.php';

/** Nomes dos eventos na tela (os não listados aparecem com o nome do Stripe). */
const STRIPE_EVENTOS = [
    'invoice.paid'                         => 'Pagamento recebido',
    'invoice.payment_succeeded'            => 'Pagamento confirmado',
    'invoice.payment_failed'               => 'Pagamento falhou',
    'checkout.session.completed'           => 'Nova assinatura',
    'customer.subscription.updated'        => 'Assinatura atualizada',
    'customer.subscription.deleted'        => 'Assinatura cancelada',
    'customer.subscription.paused'         => 'Assinatura pausada',
    'customer.subscription.resumed'        => 'Assinatura retomada',
    'customer.subscription.trial_will_end' => 'Fim do teste próximo',
    'assinatura_invalida'                  => 'Chamada recusada',
];

/** Rótulo e classe do selo de cada status. */
const STRIPE_STATUS = [
    'ok'       => ['Processado', 'ok'],
    'falha'    => ['Pagamento recusado', 'problema'],
    'erro'     => ['Erro no site', 'problema'],
    'recusado' => ['Não veio do Stripe', 'atencao'],
];

function pagamentosConexao(): ?PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = (new Database())->getConnection();
    }
    return $pdo;
}

/**
 * Registra um evento do webhook.
 *   $status: ok | falha | erro | recusado (ver migration 010)
 *   $event:  o \Stripe\Event (null quando a assinatura é inválida)
 */
function registrarEventoStripe($event, string $status, string $detalhe = ''): void
{
    try {
        $pdo = pagamentosConexao();
        if (!$pdo) {
            throw new RuntimeException('sem conexão com o banco');
        }

        $obj = $event->data->object ?? null;
        $tipo = $event->type ?? 'assinatura_invalida';
        $cliente = $obj->customer ?? null;
        $assinatura = null;
        $valor = null;
        $moeda = null;
        $vendedorId = isset($obj->metadata->vendedor_id) ? (int) $obj->metadata->vendedor_id : null;

        if ($obj && ($obj->object ?? '') === 'invoice') {
            // Recebido = amount_paid; falha = o que se tentou cobrar (amount_due)
            $centavos = $tipo === 'invoice.payment_failed' ? ($obj->amount_due ?? null) : ($obj->amount_paid ?? null);
            $valor = $centavos !== null ? $centavos / 100 : null;
            $moeda = isset($obj->currency) ? strtoupper($obj->currency) : null;
            $assinatura = $obj->subscription ?? ($obj->parent->subscription_details->subscription ?? null);
        } elseif ($obj && ($obj->object ?? '') === 'checkout.session') {
            $valor = isset($obj->amount_total) ? $obj->amount_total / 100 : null;
            $moeda = isset($obj->currency) ? strtoupper($obj->currency) : null;
            $assinatura = $obj->subscription ?? null;
        } elseif ($obj && ($obj->object ?? '') === 'subscription') {
            $assinatura = $obj->id ?? null;
        }

        if (!$vendedorId && ($assinatura || $cliente)) {
            $stmt = $pdo->prepare("SELECT id FROM vendedores WHERE stripe_subscription_id = ? OR stripe_customer_id = ?
                                   ORDER BY stripe_subscription_id = ? DESC LIMIT 1");
            $stmt->execute([$assinatura, $cliente, $assinatura]);
            $vendedorId = ($id = $stmt->fetchColumn()) ? (int) $id : null;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO pagamentos_stripe (data_hora, evento_id, tipo, status, vendedor_id, cliente_stripe, assinatura_stripe, valor, moeda, detalhe)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), detalhe = VALUES(detalhe),
                                     vendedor_id = COALESCE(VALUES(vendedor_id), vendedor_id)"
        );
        $stmt->execute([
            date('Y-m-d H:i:s'),
            $event->id ?? null,
            mb_substr($tipo, 0, 60),
            $status,
            $vendedorId,
            $cliente,
            $assinatura,
            $valor,
            $moeda,
            $detalhe !== '' ? mb_substr($detalhe, 0, 255) : null,
        ]);
    } catch (Throwable $e) {
        error_log('Pagamentos Stripe: falha ao registrar evento ' . ($event->id ?? '(sem id)') . ': ' . $e->getMessage());
    }
}

/** Quantas chamadas recusadas (sem assinatura válida) houve na última hora — para limitar o registro. */
function stripeRecusadosUltimaHora(): int
{
    try {
        $stmt = pagamentosConexao()->prepare("SELECT COUNT(*) FROM pagamentos_stripe WHERE status = 'recusado' AND data_hora >= ?");
        $stmt->execute([date('Y-m-d H:i:s', strtotime('-1 hour'))]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
