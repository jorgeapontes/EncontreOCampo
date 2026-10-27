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

// =====================================================================
// CONFERÊNCIA COM O STRIPE (seção Pagamentos do master)
// Compara o que o site guarda de cada vendedor com o que o Stripe diz, usando a
// mesma regra do webhook e do cron_verificar_vencimentos (o Stripe é a fonte da
// verdade): active/trialing → ativo com o plano do preço e o vencimento do
// período; past_due/incomplete → atrasado (mantém o plano); paused → pausado;
// canceled/unpaid/incomplete_expired/sem assinatura → Free + expirado.
// =====================================================================
const STRIPE_ID_PLANO_FREE = 1;

/** Fim do período atual da assinatura (o campo mudou de lugar nas versões da API). */
function stripeFimDoPeriodo($assinatura): ?string
{
    $fim = $assinatura->current_period_end ?? ($assinatura->items->data[0]->current_period_end ?? null);
    return $fim ? date('Y-m-d H:i:s', $fim) : null;
}

/**
 * Compara um vendedor com a assinatura dele no Stripe ($assinatura null = não existe no Stripe).
 * $planosPorPreco: [stripe_price_id => ['id' => .., 'nome' => ..]].
 * Retorna o estado esperado e a lista de divergências (vazia = tudo certo).
 */
function compararComStripe(array $vendedor, $assinatura, array $planosPorPreco): array
{
    $statusStripe = $assinatura->status ?? 'sem assinatura';
    $esperado = ['status' => 'expirado', 'plano_id' => STRIPE_ID_PLANO_FREE, 'vencimento' => null];
    $avisos = [];
    if (in_array($statusStripe, ['active', 'trialing'], true)) {
        $precoId = $assinatura->items->data[0]->price->id ?? null;
        $plano = $planosPorPreco[$precoId] ?? null;
        $esperado = ['status' => 'ativo', 'plano_id' => $plano['id'] ?? (int) $vendedor['plano_id'], 'vencimento' => stripeFimDoPeriodo($assinatura)];
        if (!$plano) {
            $avisos[] = "o preço {$precoId} do Stripe não corresponde a nenhum plano do site";
        }
        if (!empty($assinatura->cancel_at_period_end)) {
            $avisos[] = 'cancelamento agendado para o fim do período';
        }
    } elseif (in_array($statusStripe, ['past_due', 'incomplete'], true)) {
        $esperado = ['status' => 'atrasado', 'plano_id' => (int) $vendedor['plano_id'], 'vencimento' => $vendedor['data_vencimento_assinatura']];
    } elseif ($statusStripe === 'paused') {
        $esperado = ['status' => 'pausado', 'plano_id' => (int) $vendedor['plano_id'], 'vencimento' => $vendedor['data_vencimento_assinatura']];
    }

    $divergencias = [];
    // No plano grátis, "inativo" ou vazio equivale a "expirado"
    $semPlanoPago = $esperado['status'] === 'expirado' && (int) $vendedor['plano_id'] === STRIPE_ID_PLANO_FREE
        && in_array((string) $vendedor['status_assinatura'], ['expirado', 'inativo', ''], true);
    if (!$semPlanoPago && $vendedor['status_assinatura'] !== $esperado['status']) {
        $divergencias[] = "situação no site \"{$vendedor['status_assinatura']}\", deveria ser \"{$esperado['status']}\"";
    }
    if ((int) $vendedor['plano_id'] !== (int) $esperado['plano_id']) {
        $divergencias[] = (int) $esperado['plano_id'] === STRIPE_ID_PLANO_FREE
            ? 'continua com plano pago no site, mas o Stripe não cobra mais'
            : 'plano no site diferente do que o Stripe cobra';
    }
    if ($esperado['status'] === 'ativo' && $esperado['vencimento']
        && abs(strtotime((string) $vendedor['data_vencimento_assinatura']) - strtotime($esperado['vencimento'])) > 86400) {
        $divergencias[] = 'vencimento no site ' . ($vendedor['data_vencimento_assinatura'] ? date('d/m/Y', strtotime($vendedor['data_vencimento_assinatura'])) : 'vazio')
            . ', no Stripe ' . date('d/m/Y', strtotime($esperado['vencimento']));
    }
    if ($assinatura && !empty($vendedor['stripe_customer_id']) && ($assinatura->customer ?? null) !== $vendedor['stripe_customer_id']) {
        $avisos[] = 'a assinatura pertence a outro cliente do Stripe';
    }
    return ['status_stripe' => $statusStripe, 'esperado' => $esperado, 'divergencias' => $divergencias, 'avisos' => $avisos];
}

/** Vendedores que passaram pelo Stripe ou estão num plano pago (os únicos que dá para conferir). */
function vendedoresParaConferir(PDO $pdo, ?int $vendedorId = null): array
{
    $sql = "SELECT v.id, v.usuario_id, u.nome, v.plano_id, v.status_assinatura, v.data_vencimento_assinatura,
                   v.stripe_customer_id, v.stripe_subscription_id
            FROM vendedores v JOIN usuarios u ON u.id = v.usuario_id LEFT JOIN planos p ON p.id = v.plano_id
            WHERE (p.preco_mensal > 0 OR v.stripe_subscription_id IS NOT NULL)" . ($vendedorId ? ' AND v.id = ?' : '') . "
            ORDER BY u.nome LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vendedorId ? [$vendedorId] : []);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Busca a assinatura no Stripe: o objeto, null se não existe lá, ou lança erro de comunicação. */
function buscarAssinaturaStripe(?string $assinaturaId)
{
    if (!$assinaturaId) {
        return null;
    }
    try {
        return \Stripe\Subscription::retrieve($assinaturaId);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        return null; // apagada no Stripe (ou de outro ambiente)
    }
}

/** Confere todos os vendedores. Retorna uma linha por vendedor (com 'erro' se o Stripe não respondeu). */
function conferirAssinaturasComStripe(PDO $pdo): array
{
    require_once __DIR__ . '/../../config/StripeConfig.php';
    \Config\StripeConfig::init();
    $planosPorPreco = [];
    foreach ($pdo->query("SELECT id, nome, stripe_price_id FROM planos WHERE stripe_price_id IS NOT NULL AND stripe_price_id <> ''") as $p) {
        $planosPorPreco[$p['stripe_price_id']] = $p;
    }
    $resultado = [];
    foreach (vendedoresParaConferir($pdo) as $v) {
        $linha = ['vendedor' => $v];
        try {
            $linha += compararComStripe($v, buscarAssinaturaStripe($v['stripe_subscription_id']), $planosPorPreco);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            throw $e; // chave errada: não adianta seguir para os outros vendedores
        } catch (Throwable $e) {
            $linha['erro'] = 'O Stripe não respondeu: ' . $e->getMessage();
        }
        $resultado[] = $linha;
    }
    return $resultado;
}

/**
 * Aplica no site o que o Stripe diz para um vendedor (botão "Corrigir").
 * Avisa o vendedor no painel quando ele perde ou fica com o plano em atraso, como o cron faz.
 * Retorna o texto do que foi feito.
 */
function corrigirVendedorPeloStripe(PDO $pdo, int $vendedorId): string
{
    require_once __DIR__ . '/../../config/StripeConfig.php';
    \Config\StripeConfig::init();
    $v = vendedoresParaConferir($pdo, $vendedorId)[0] ?? null;
    if (!$v) {
        // Já está no plano grátis sem assinatura: não há o que conferir
        return 'Nada a corrigir: o vendedor já está no plano gratuito, sem assinatura no Stripe.';
    }
    $planosPorPreco = [];
    foreach ($pdo->query("SELECT id, nome, stripe_price_id FROM planos WHERE stripe_price_id IS NOT NULL AND stripe_price_id <> ''") as $p) {
        $planosPorPreco[$p['stripe_price_id']] = $p;
    }
    $c = compararComStripe($v, buscarAssinaturaStripe($v['stripe_subscription_id']), $planosPorPreco);
    if (!$c['divergencias']) {
        return 'Nada a corrigir: o site já está igual ao Stripe.';
    }
    $e = $c['esperado'];
    $pdo->prepare("UPDATE vendedores SET status_assinatura = ?, plano_id = ?, data_vencimento_assinatura = ? WHERE id = ?")
        ->execute([$e['status'], $e['plano_id'], $e['vencimento'], $vendedorId]);

    $avisoVendedor = [
        'expirado' => 'Sua assinatura foi encerrada por falta de pagamento e sua conta voltou para o Plano Gratuito.',
        'atrasado' => 'Não conseguimos cobrar a sua mensalidade. Atualize a forma de pagamento para não perder os benefícios do seu plano.',
    ][$e['status']] ?? null;
    if ($avisoVendedor && $v['status_assinatura'] !== $e['status']) {
        $pdo->prepare("INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) VALUES (?, ?, 'alerta', 'vendedor/gerenciar_assinatura')")
            ->execute([$v['usuario_id'], $avisoVendedor]);
    }
    return "{$v['nome']}: " . implode('; ', $c['divergencias']) . ". Corrigido para \"{$e['status']}\" (Stripe: {$c['status_stripe']}).";
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
