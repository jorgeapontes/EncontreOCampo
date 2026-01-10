<?php
// src/vendedor/gerar_portal_stripe.php
require_once 'auth.php';
require_once __DIR__ . '/../../config/StripeConfig.php';

\Config\StripeConfig::init();

// Buscamos o customer_id do vendedor logado
$customer_id = $vendedor['stripe_customer_id'] ?? null;

if (!$customer_id) {
    // Se não tiver o ID salvo, redireciona para escolher um plano pela primeira vez
    header("Location: escolher_plano.php");
    exit();
}

try {
    // Cria o link seguro para o Portal do Cliente do Stripe
    $session = \Stripe\BillingPortal\Session::create([
      'customer' => $customer_id,
      'return_url' => 'http://localhost/EncontreOCampo/src/vendedor/gerenciar_assinatura.php',
    ]);

    // Redireciona o usuário para o Stripe
    header("Location: " . $session->url);
    exit();
} catch (Exception $e) {
    die("Erro ao acessar portal financeiro: " . $e->getMessage());
}