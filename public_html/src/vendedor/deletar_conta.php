<?php
// src/vendedor/deletar_conta.php
// Exclusão da conta do vendedor: tira os anúncios do ar, anonimiza os dados
// pessoais e apaga os documentos (ver src/functions/conta_functions.php).
//
// Assinatura no Stripe: enquanto ela estiver ativa e RENOVANDO, a exclusão é
// bloqueada (o perfil nem mostra o botão de confirmar). Depois que o vendedor
// cancela a renovação em "Gerenciar assinatura", a exclusão é liberada e a
// assinatura é encerrada na hora, sem nova cobrança.
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../functions/conta_functions.php';
require_once __DIR__ . '/../../config/StripeConfig.php';

excluirContaDaSessao('vendedor', function (PDO $db, int $usuarioId) {
    $stmt = $db->prepare("SELECT id, stripe_subscription_id FROM vendedores WHERE usuario_id = ?");
    $stmt->execute([$usuarioId]);
    $vendedor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor || empty($vendedor['stripe_subscription_id'])) {
        return;
    }

    [$bloqueia, $assinatura] = assinaturaImpedeExclusao($vendedor['stripe_subscription_id']);
    if ($bloqueia) {
        throw new ExclusaoContaBloqueada(MSG_PLANO_ATIVO_EXCLUSAO);
    }

    // Renovação já cancelada: encerra agora, sem cobrança
    if ($assinatura && in_array($assinatura->status, ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'], true)) {
        $assinatura->cancel();
        error_log("Assinatura Stripe {$vendedor['stripe_subscription_id']} encerrada na exclusão da conta do vendedor {$vendedor['id']}");
    }
});
