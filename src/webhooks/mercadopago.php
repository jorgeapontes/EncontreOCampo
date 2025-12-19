<?php
// src/webhooks/mercadopago.php

// 1. LOG DE ENTRADA (Para você ver no arquivo webhook_debug.log)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

file_put_contents('webhook_debug.log', 
    "[" . date('Y-m-d H:i:s') . "] Webhook chamado. Tipo: " . ($data['type'] ?? 'desconhecido') . "\n" .
    "Payload: " . $input . "\n\n", 
    FILE_APPEND);

// 2. DEPENDÊNCIAS (Ajustadas para o seu projeto)
require_once __DIR__ . '/../conexao.php'; // Usa o arquivo que você já tem no projeto
require_once __DIR__ . '/../config/MercadoPagoConfig.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$data) {
    http_response_code(400);
    exit;
}

// 3. LOGICA DE PROCESSAMENTO
try {
    // Usamos o $conn que vem do seu arquivo conexao.php
    $db = $conn; 

    // TIPO: PAGAMENTO (Venda de produto ou cobrança de assinatura aprovada)
    if (isset($data['type']) && $data['type'] == 'payment') {
        $payment_id = $data['data']['id'];
        
        // Busca os detalhes no Mercado Pago
        $payment = MercadoPagoAPI::getPayment($payment_id);
        
        if ($payment) {
            $ref = $payment->external_reference ?? '';
            $status = $payment->status; // 'approved', 'pending', etc.
            
            // Tenta identificar se é uma assinatura pelo external_reference
            // Formato esperado: vendedor_ID_plano_ID_TIMESTAMP
            if (preg_match('/vendedor_(\d+)_plano_(\d+)/', $ref, $matches)) {
                $vendedor_id = $matches[1];
                $plano_id = $matches[2];

                if ($status == 'approved') {
                    // Atualiza o vendedor para o novo plano
                    $stmt = $db->prepare("UPDATE vendedores SET plano_id = ? WHERE id = ?");
                    $stmt->execute([$plano_id, $vendedor_id]);
                    
                    file_put_contents('webhook_debug.log', "Plano atualizado para vendedor $vendedor_id\n", FILE_APPEND);
                }
            }
        }
    } 
    
    // TIPO: ASSINATURA (O que apareceu no seu log: subscription_preapproval)
    elseif (isset($data['type']) && ($data['type'] == 'subscription_preapproval' || $data['type'] == 'subscription')) {
        $sub_id = $data['data']['id'] ?? $data['id'];
        
        // Aqui você buscaria os detalhes da assinatura
        // Por enquanto, vamos apenas logar que recebemos
        file_put_contents('webhook_debug.log', "Notificação de Assinatura recebida: $sub_id\n", FILE_APPEND);
        
        // Lógica: Se for 'updated' e status 'authorized', você ativa no banco.
    }

    // SEMPRE responda 200 para o Mercado Pago não reenviar a mesma coisa
    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    file_put_contents('webhook_debug.log', "ERRO: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
}