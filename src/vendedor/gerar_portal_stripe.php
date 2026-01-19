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
    // Detectar a URL base dinamicamente baseado no servidor
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . '://' . $host;
    
    // URL de retorno dinâmica
    $return_url = $base_url . '/src/vendedor/gerenciar_assinatura.php';
    
    // Cria o link seguro para o Portal do Cliente do Stripe
    $session = \Stripe\BillingPortal\Session::create([
      'customer' => $customer_id,
      'return_url' => $return_url,
    ]);

    // Redireciona o usuário para o Stripe
    header("Location: " . $session->url);
    exit();
} catch (Exception $e) {
    die("Erro ao acessar portal financeiro: " . $e->getMessage());
}