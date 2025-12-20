<?php
// src/webhooks/mercadopago.php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../config/MercadoPagoConfig.php';

// Captura os dados enviados pelo Mercado Pago (JSON ou URL)
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

// O Mercado Pago envia o tipo de notificação em campos diferentes conforme a versão
$id = $_GET['id'] ?? $dados['data']['id'] ?? $dados['id'] ?? null;
$topic = $_GET['type'] ?? $_GET['topic'] ?? $dados['type'] ?? $dados['topic'] ?? null;

// Log para debug local
file_put_contents('log_webhook.txt', "--- Notificação Recorrência: " . date('Y-m-d H:i:s') . " ---" . PHP_EOL, FILE_APPEND);
file_put_contents('log_webhook.txt', "ID: $id | Tópico: $topic" . PHP_EOL, FILE_APPEND);

if ($id && ($topic === 'preapproval' || $topic === 'subscription')) {
    try {
        MercadoPagoAPI::init();
        $client = new \MercadoPago\Client\Preapproval\PreapprovalClient();
        
        // Buscamos os detalhes da assinatura no Mercado Pago
        $subscription = $client->get($id);

        file_put_contents('log_webhook.txt', "Status da Assinatura: " . $subscription->status . PHP_EOL, FILE_APPEND);

        // Se o status for "authorized", o pagamento recorrente foi ativado com sucesso
        if ($subscription->status === 'authorized') {
            $database = new Database();
            $conn = $database->getConnection();

            $ext_ref = $subscription->external_reference; // Ex: vendedor_1_plano_3
            $parts = explode('_', $ext_ref);
            
            if (count($parts) >= 4) {
                $vendedor_id = (int)$parts[1];
                $plano_id = (int)$parts[3];

                // Atualizamos o vendedor para o novo plano e marcamos como ativo
                $sql = "UPDATE vendedores SET 
                            plano_id = ?, 
                            status_assinatura = 'ativo', 
                            data_assinatura = NOW() 
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $result = $stmt->execute([$plano_id, $vendedor_id]);

                if ($result) {
                    file_put_contents('log_webhook.txt', "SUCESSO: Vendedor $vendedor_id ativado no Plano $plano_id" . PHP_EOL, FILE_APPEND);
                }
            }
        }
        
        // Respondemos 200 sempre para o MP saber que recebemos
        http_response_code(200);

    } catch (Exception $e) {
        file_put_contents('log_webhook.txt', "ERRO WEBHOOK: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        http_response_code(200); 
    }
} else {
    // Se for apenas uma notificação de teste ou pagamento pontual, apenas confirmamos o recebimento
    http_response_code(200);
}