<?php
// src/vendedor/deletar_conta.php
// Exclusão da conta do vendedor: cancela a assinatura no Stripe, tira os
// anúncios do ar, anonimiza os dados pessoais e apaga os documentos
// (ver src/functions/conta_functions.php).
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

    // Sem cancelar no Stripe a cobrança continuaria depois da exclusão,
    // então qualquer erro aqui (exceto "já cancelada") interrompe a exclusão.
    try {
        \Config\StripeConfig::init();
        \Stripe\Subscription::retrieve($vendedor['stripe_subscription_id'])->cancel();
        error_log("Assinatura Stripe {$vendedor['stripe_subscription_id']} cancelada (exclusão da conta do vendedor {$vendedor['id']})");
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        error_log('Aviso ao cancelar assinatura Stripe (provavelmente já cancelada): ' . $e->getMessage());
    }
});
