<?php
// src/vendedor/webhook_stripe.php
require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';

\Config\StripeConfig::init();
$endpoint_secret = 'whsec_Ek5V2MZ2KZpWOd6018hGhheA07RnnM8H'; 

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(Exception $e) {
    http_response_code(400); exit();
}

$db = (new Database())->getConnection();

switch ($event->type) {
    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        $sub_id = $subscription->id;
        $cus_id = $subscription->customer;
        
        // Tentamos pelo ID da assinatura OU pelo ID do cliente para não ter erro
        $stmt = $db->prepare("UPDATE vendedores SET 
                                plano_id = 1, 
                                status_assinatura = 'expirado', 
                                stripe_subscription_id = NULL 
                              WHERE stripe_subscription_id = ? OR stripe_customer_id = ?");
        $stmt->execute([$sub_id, $cus_id]);
        
        file_put_contents('log_webhook.txt', "Cancelamento: Sub $sub_id | Cus $cus_id | Linhas: " . $stmt->rowCount() . "\n", FILE_APPEND);
        break;

    case 'checkout.session.completed':
        $session = $event->data->object;
        $v_id = $session->metadata->vendedor_id;
        $p_id = $session->metadata->plano_id;
        $sub_id = $session->subscription;
        $cus_id = $session->customer;

        $stmt = $db->prepare("UPDATE vendedores SET 
                                plano_id = ?, 
                                stripe_customer_id = ?, 
                                stripe_subscription_id = ?, 
                                status_assinatura = 'ativo' 
                              WHERE id = ?");
        $stmt->execute([$p_id, $cus_id, $sub_id, $v_id]);
        break;
}

http_response_code(200);