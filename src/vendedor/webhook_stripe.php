<?php
// src/vendedor/webhook_stripe.php
require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';

\Config\StripeConfig::init();
$endpoint_secret = 'seu_webhook_secret_aqui'; // Você pega isso no Dashboard do Stripe

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(\UnexpectedValueException $e) {
    http_response_code(400); exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); exit();
}

// Quando o checkout é concluído com sucesso
if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;

    $database = new Database();
    $db = $database->getConnection();

    // Pegamos os dados que enviamos no Metadata anteriormente
    $vendedor_id = $session->metadata->vendedor_id;
    $plano_id = $session->metadata->plano_id;
    $customer_id = $session->customer; // cus_...
    $subscription_id = $session->subscription; // sub_...

    // Atualizamos o vendedor com os IDs do Stripe e o novo plano
    $query = "UPDATE vendedores SET 
                plano_id = :plano_id, 
                stripe_customer_id = :cus_id, 
                stripe_subscription_id = :sub_id,
                status_assinatura = 'ativo' 
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':plano_id' => $plano_id,
        ':cus_id' => $customer_id,
        ':sub_id' => $subscription_id,
        ':id' => $vendedor_id
    ]);
}

http_response_code(200);