<?php
// src/vendedor/gerar_portal_stripe.php
require_once 'auth.php'; // Para pegar o $db e o $vendedor
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/StripeConfig.php';

use Config\StripeConfig;
StripeConfig::init();

// Precisamos do ID do cliente na Stripe. 
// Se você não salvou o 'customer_id' no banco, a Stripe permite buscar pelo e-mail.
try {
    // 1. Buscamos o e-mail do usuário logado
    $email_usuario = $_SESSION['usuario_email'];

    // 2. Procuramos o cliente na Stripe pelo e-mail
    $customers = \Stripe\Customer::all(['email' => $email_usuario, 'limit' => 1]);
    
    if (empty($customers->data)) {
        die("Erro: Você ainda não possui uma assinatura processada pela Stripe.");
    }

    $customer_id = $customers->data[0]->id;

    // 3. Criamos uma sessão para o Portal do Cliente
    $session = \Stripe\BillingPortal\Session::create([
        'customer' => $customer_id,
        'return_url' => 'http://localhost/EncontreOCampo/src/vendedor/gerenciar_assinatura.php',
    ]);

    // 4. Redirecionamos o usuário para o portal seguro da Stripe
    header("Location: " . $session->url);
    exit;

} catch (Exception $e) {
    die("Erro ao acessar portal: " . $e->getMessage());
}