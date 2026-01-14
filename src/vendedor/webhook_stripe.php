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

// Log inicial para saber que o evento chegou
file_put_contents('log_webhook.txt', "\n--- NOVO EVENTO: " . $event->type . " --- " . date('H:i:s') . "\n", FILE_APPEND);

switch ($event->type) {
    
    // CASO 1: CANCELAMENTO
    case 'customer.subscription.deleted':
        $subscription = $event->data->object;
        $sub_id = $subscription->id;
        $cus_id = $subscription->customer;
        
        $stmt = $db->prepare("UPDATE vendedores SET plano_id = 1, status_assinatura = 'expirado', stripe_subscription_id = NULL WHERE stripe_subscription_id = ? OR stripe_customer_id = ?");
        $stmt->execute([$sub_id, $cus_id]);
        
        file_put_contents('log_webhook.txt', "Cancelamento OK. Sub: $sub_id | Linhas: " . $stmt->rowCount() . "\n", FILE_APPEND);
        break;

    // CASO 2: MUDANÇA DE PLANO (UPGRADE/DOWNGRADE)
    case 'customer.subscription.updated':
        $subscription = $event->data->object;
        $sub_id = $subscription->id;
        
        // Pega o ID do preço que está vindo do Stripe
        $new_price_id = $subscription->items->data[0]->price->id;
        file_put_contents('log_webhook.txt', "Evento UPDATED: Procurando plano para o Price ID: $new_price_id\n", FILE_APPEND);

        // 1. Descobrir qual plano no SEU banco usa esse Price ID do Stripe
        $stmtPlano = $db->prepare("SELECT id FROM planos WHERE stripe_price_id = ?");
        $stmtPlano->execute([$new_price_id]);
        $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);

        if ($plano) {
            $novo_plano_id = $plano['id'];
            
            // 2. Atualizar o vendedor
            $stmtUpdate = $db->prepare("UPDATE vendedores SET plano_id = ? WHERE stripe_subscription_id = ?");
            $stmtUpdate->execute([$novo_plano_id, $sub_id]);
            
            $count = $stmtUpdate->rowCount();
            file_put_contents('log_webhook.txt', "Sucesso! Plano atualizado para $novo_plano_id (Sub: $sub_id) | Linhas afetadas: $count\n", FILE_APPEND);
        } else {
            file_put_contents('log_webhook.txt', "ERRO: Nenhum plano encontrado no banco com o stripe_price_id: $new_price_id\n", FILE_APPEND);
        }
        break;

    // CASO 3: PRIMEIRA COMPRA
    case 'checkout.session.completed':
        $session = $event->data->object;
        $v_id = $session->metadata->vendedor_id;
        $p_id = $session->metadata->plano_id;
        $sub_id = $session->subscription;
        $cus_id = $session->customer;

        $stmt = $db->prepare("UPDATE vendedores SET plano_id = ?, stripe_customer_id = ?, stripe_subscription_id = ?, status_assinatura = 'ativo' WHERE id = ?");
        $stmt->execute([$p_id, $cus_id, $sub_id, $v_id]);
        
        file_put_contents('log_webhook.txt', "Compra concluída! Vendedor: $v_id | Plano: $p_id\n", FILE_APPEND);
        break;
}

http_response_code(200);