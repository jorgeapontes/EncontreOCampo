<?php
require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';

\Config\StripeConfig::init();

// Use a secret que aparece no seu terminal para o CLI
$endpoint_secret = 'whsec_41933476e94e79ffc132a4c87783cca46b6d6375479641f8114216ae85f2c4ae'; 

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(\Exception $e) {
    http_response_code(400); 
    exit();
}

$db = (new Database())->getConnection();
file_put_contents('log_webhook.txt', "Evento recebido: " . $event->type . "\n", FILE_APPEND);

switch ($event->type) {
    // 1. O PAGAMENTO FALHOU (Cartão recusado/sem saldo)
    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        $cus_id = $invoice->customer;
        
        $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'atrasado' WHERE stripe_customer_id = ?");
        $stmt->execute([$cus_id]);
        
        file_put_contents('log_webhook.txt', "STATUS ATUALIZADO: Atrasado para cliente $cus_id\n", FILE_APPEND);
        break;

    // 2. O PAGAMENTO FOI FEITO (Renovação mensal OK)
    case 'invoice.paid':
        $invoice = $event->data->object;
        $cus_id = $invoice->customer;
        
        $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo' WHERE stripe_customer_id = ?");
        $stmt->execute([$cus_id]);
        break;

    // 3. ASSINATURA CANCELADA OU EXPIRADA
    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        $sub_id = $subscription->id;
        
        $stmt = $db->prepare("UPDATE vendedores SET plano_id = 1, status_assinatura = 'expirado' WHERE stripe_subscription_id = ?");
        $stmt->execute([$sub_id]);
        break;

    // 4. NOVA COMPRA (Checkout)
    case 'checkout.session.completed':
        $session = $event->data->object;
        $v_id = $session->metadata->vendedor_id;
        $p_id = $session->metadata->plano_id;
        $sub_id = $session->subscription;
        $cus_id = $session->customer;

        $stmt = $db->prepare("UPDATE vendedores SET plano_id = ?, stripe_customer_id = ?, stripe_subscription_id = ?, status_assinatura = 'ativo' WHERE id = ?");
        $stmt->execute([$p_id, $cus_id, $sub_id, $v_id]);
        break;
}

http_response_code(200);