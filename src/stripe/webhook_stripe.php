<?php
// src/vendedor/stripe/webhook_stripe.php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/StripeConfig.php';
require_once __DIR__ . '/../../conexao.php'; // Ajuste conforme o seu caminho de conexão

\Config\StripeConfig::init();

// --- CONFIGURAÇÃO IMPORTANTE ---
// Esta chave você obtém no Dashboard do Stripe ou via Stripe CLI ao testar localmente
$endpoint_secret = 'whsec_XXXXXXXXX'; 

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    // Verifica se a assinatura da Stripe é autêntica
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(\UnexpectedValueException $e) {
    http_response_code(400); // Payload inválido
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400); // Assinatura inválida
    exit();
}

// Conexão com o Banco de Dados
$database = new Database();
$db = $database->getConnection();

// Lógica baseada no tipo de evento enviado pela Stripe
switch ($event->type) {

    // 1. PAGAMENTO DE RENOVAÇÃO BEM-SUCEDIDO
    case 'invoice.paid':
        $invoice = $event->data->object;
        $customer_id = $invoice->customer;
        
        // Calcula a nova data de vencimento (geralmente +30 dias a partir de agora)
        $nova_data_vencimento = date('Y-m-d H:i:s', $invoice->lines->data[0]->period->end);

        $sql = "UPDATE vendedores SET 
                status_assinatura = 'ativo', 
                data_vencimento_assinatura = :vencimento 
                WHERE stripe_customer_id = :cust_id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':vencimento' => $nova_data_vencimento,
            ':cust_id'    => $customer_id
        ]);
        break;

    // 2. FALHA NO PAGAMENTO (Cartão recusado)
    case 'invoice.payment_failed':
        $invoice = $event->data->object;
        $customer_id = $invoice->customer;
        
        // Marcamos o vendedor como inativo para o sistema travar as edições
        $sql = "UPDATE vendedores SET status_assinatura = 'inativo' WHERE stripe_customer_id = :cust_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':cust_id' => $customer_id]);
        break;

    // 3. ASSINATURA CANCELADA OU EXPIRADA TOTALMENTE
    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        $customer_id = $subscription->customer;
        
        // O plano acabou ou foi cancelado: voltamos o vendedor para o Plano Free (ID 1)
        $sql = "UPDATE vendedores SET 
                plano_id = 1, 
                status_assinatura = 'ativo', 
                data_vencimento_assinatura = NULL 
                WHERE stripe_customer_id = :cust_id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':cust_id' => $customer_id]);
        break;

    default:
        // Outros eventos que não queremos tratar agora
        error_log('Evento Stripe recebido mas não processado: ' . $event->type);
}

// Responde 200 para a Stripe saber que recebemos a mensagem com sucesso
http_response_code(200);