// EncontreOCampo/src/webhooks/mercadopago.php
<?php
require_once __DIR__ . '/../../config/MercadoPagoConfig.php';
require_once __DIR__ . '/../conexao.php';

// Configurar logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/mercadopago_webhook.log');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Obter dados
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Registrar webhook recebido
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    INSERT INTO webhook_logs (event_type, resource_id, payload, created_at) 
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([
    $data['type'] ?? 'unknown',
    $data['data']['id'] ?? null,
    $input
]);

// Processar notificação
if (isset($data['type']) && isset($data['data']['id'])) {
    $type = $data['type'];
    $resource_id = $data['data']['id'];
    
    try {
        if ($type === 'payment') {
            processarPagamento($resource_id, $conn);
        } elseif ($type === 'subscription') {
            processarAssinatura($resource_id, $conn);
        }
        
        // Marcar como processado
        $stmt = $conn->prepare("UPDATE webhook_logs SET processed = 1 WHERE id = ?");
        $stmt->execute([$conn->lastInsertId()]);
        
    } catch (Exception $e) {
        // Registrar erro
        $stmt = $conn->prepare("UPDATE webhook_logs SET error_message = ? WHERE id = ?");
        $stmt->execute([$e->getMessage(), $conn->lastInsertId()]);
        error_log("Erro no webhook: " . $e->getMessage());
    }
}

http_response_code(200);
echo 'OK';

function processarPagamento($payment_id, $conn) {
    $paymentClient = MercadoPagoConfig::getPaymentClient();
    $payment = $paymentClient->get($payment_id);
    
    $status = $payment->status;
    $external_reference = $payment->external_reference;
    
    // Parse external_reference: vendedor_X_plano_Y_timestamp
    if (preg_match('/vendedor_(\d+)_plano_(\d+)_(\d+)/', $external_reference, $matches)) {
        $vendedor_id = $matches[1];
        $plano_id = $matches[2];
        
        // Buscar assinatura pendente
        $stmt = $conn->prepare("
            SELECT a.* FROM vendedor_assinaturas a
            WHERE a.vendedor_id = ? AND a.plano_id = ? AND a.status = 'pending'
            ORDER BY a.created_at DESC LIMIT 1
        ");
        $stmt->execute([$vendedor_id, $plano_id]);
        $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assinatura) {
            // Atualizar assinatura
            $stmt = $conn->prepare("
                UPDATE vendedor_assinaturas 
                SET status = ?, payment_id = ?, data_inicio = CURDATE(),
                    data_vencimento = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                WHERE id = ?
            ");
            $stmt->execute([$status, $payment_id, $assinatura['id']]);
            
            // Se pagamento aprovado, ativar plano
            if ($status === 'approved') {
                ativarPlanoVendedor($vendedor_id, $plano_id, $conn);
                enviarEmailConfirmacao($vendedor_id, $plano_id, $conn);
            }
        }
    }
}

function processarAssinatura($subscription_id, $conn) {
    $subscriptionClient = MercadoPagoConfig::getSubscriptionClient();
    $subscription = $subscriptionClient->get($subscription_id);
    
    $status = $subscription->status;
    $external_reference = $subscription->external_reference;
    
    if (preg_match('/vendedor_(\d+)_plano_(\d+)_(\d+)/', $external_reference, $matches)) {
        $vendedor_id = $matches[1];
        $plano_id = $matches[2];
        
        // Atualizar assinatura
        $stmt = $conn->prepare("
            UPDATE vendedor_assinaturas 
            SET status = ?, subscription_id = ?, updated_at = NOW()
            WHERE vendedor_id = ? AND plano_id = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$status, $subscription_id, $vendedor_id, $plano_id]);
        
        // Se assinatura ativa, renovar vencimento
        if ($status === 'authorized') {
            $stmt = $conn->prepare("
                UPDATE vendedor_assinaturas 
                SET data_vencimento = DATE_ADD(data_vencimento, INTERVAL 1 MONTH)
                WHERE vendedor_id = ? AND plano_id = ?
            ");
            $stmt->execute([$vendedor_id, $plano_id]);
        }
    }
}

function ativarPlanoVendedor($vendedor_id, $plano_id, $conn) {
    // Buscar plano
    $stmt = $conn->prepare("SELECT * FROM planos WHERE id = ?");
    $stmt->execute([$plano_id]);
    $plano = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($plano) {
        // Atualizar vendedor
        $stmt = $conn->prepare("
            UPDATE vendedores 
            SET plano_id = ?, status_assinatura = 'ativa',
                data_inicio_assinatura = NOW(),
                data_vencimento_assinatura = DATE_ADD(NOW(), INTERVAL 1 MONTH)
            WHERE id = ?
        ");
        $stmt->execute([$plano_id, $vendedor_id]);
        
        // Criar registro de controle de anúncios se não existir
        $stmt = $conn->prepare("
            INSERT INTO vendedor_anuncios_controle 
            (vendedor_id, total_anuncios, anuncios_gratis_utilizados, anuncios_pagos_utilizados, anuncios_ativos)
            VALUES (?, 0, 0, 0, 0)
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $stmt->execute([$vendedor_id]);
    }
}

function enviarEmailConfirmacao($vendedor_id, $plano_id, $conn) {
    // Buscar dados do vendedor
    $stmt = $conn->prepare("
        SELECT u.email, u.nome, v.nome_comercial, p.nome as plano_nome, p.preco_mensal 
        FROM usuarios u
        JOIN vendedores v ON u.id = v.usuario_id
        JOIN planos p ON p.id = ?
        WHERE v.id = ?
    ");
    $stmt->execute([$plano_id, $vendedor_id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dados) {
        $to = $dados['email'];
        $subject = "Confirmação de Assinatura - EncontreOCampo";
        $message = "
            Olá " . $dados['nome_comercial'] . ",
            
            Sua assinatura do plano " . $dados['plano_nome'] . " foi ativada com sucesso!
            
            Valor: R$ " . number_format($dados['preco_mensal'], 2, ',', '.') . " /mês
            Data de ativação: " . date('d/m/Y') . "
            
            Agora você pode criar até " . getLimiteAnuncios($plano_id, $conn) . " anúncios.
            
            Atenciosamente,
            Equipe EncontreOCampo
        ";
        
        $headers = "From: noreply@encontreocampo.com.br\r\n";
        $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
        
        // Em produção, usar função de email real
        // mail($to, $subject, $message, $headers);
        
        // Para teste, registrar no log
        error_log("Email enviado para: $to - Assunto: $subject");
    }
}

function getLimiteAnuncios($plano_id, $conn) {
    $stmt = $conn->prepare("SELECT limite_total_anuncios FROM planos WHERE id = ?");
    $stmt->execute([$plano_id]);
    $plano = $stmt->fetch(PDO::FETCH_ASSOC);
    return $plano['limite_total_anuncios'] ?? 0;
}
?>