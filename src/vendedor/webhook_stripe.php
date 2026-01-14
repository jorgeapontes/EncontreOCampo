<?php
require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';

\Config\StripeConfig::init();

// Lembra de conferir se a chave do CLI no terminal ainda é esta ao reiniciar o 'stripe listen'
$endpoint_secret = 'whsec_41933476e94e79ffc132a4c87783cca46b6d6375479641f8114216ae85f2c4ae'; 

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

try {
    // Validação da assinatura do Stripe
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    
    $db = (new Database())->getConnection();
    file_put_contents('log_webhook.txt', "[" . date('Y-m-d H:i:s') . "] Evento recebido: " . $event->type . "\n", FILE_APPEND);

    switch ($event->type) {
        case 'invoice.payment_failed':
            $invoice = $event->data->object;
            $cus_id = $invoice->customer;
            
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'atrasado' WHERE stripe_customer_id = ?");
            $stmt->execute([$cus_id]);
            
            file_put_contents('log_webhook.txt', "STATUS: Atrasado | Cliente: $cus_id | Linhas: " . $stmt->rowCount() . "\n", FILE_APPEND);
            break;

        case 'invoice.paid':
            $invoice = $event->data->object;
            $cus_id = $invoice->customer;
            
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo' WHERE stripe_customer_id = ?");
            $stmt->execute([$cus_id]);
            break;

        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            $sub_id = $subscription->id;
            
            $stmt = $db->prepare("UPDATE vendedores SET plano_id = 1, status_assinatura = 'expirado' WHERE stripe_subscription_id = ?");
            $stmt->execute([$sub_id]);
            break;

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

} catch (\Exception $e) {
    // LOG DE ERRO AVANÇADO: Se o banco falhar ou houver erro de código, você saberá o porquê
    $error_log = "[" . date('Y-m-d H:i:s') . "] ERRO NO WEBHOOK: " . $e->getMessage() . " em " . $e->getFile() . " na linha " . $e->getLine() . "\n";
    file_put_contents('log_webhook.txt', $error_log, FILE_APPEND);
    
    // Retornamos 500 para o Stripe tentar reenviar esse evento mais tarde
    http_response_code(500); 
    exit();
}