<?php
require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/send_notification.php'; // NOVO: Adicionado para notificações

\Config\StripeConfig::init();

// Detectar o ambiente (produção ou teste) baseado no servidor
$is_production = !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) && $_SERVER['HTTP_HOST'] !== '';

// Chave do webhook hardcoded conforme ambiente
if ($is_production) {
    // Substitua pela sua chave de webhook de produção (live)
    $endpoint_secret = 'whsec_Dr9wtGQ6ktoQYNFbQBkGVNqjOnfSfpeT';
} else {
    // Substitua pela sua chave de webhook de teste (test)
    $endpoint_secret = 'whsec_test_replace_me';
}

if (!$endpoint_secret) {
    // Fallback: Se não houver variável de ambiente, lança erro
    file_put_contents('log_webhook.txt', "[" . date('Y-m-d H:i:s') . "] ERRO: STRIPE_WEBHOOK_SECRET não definido no .env\n", FILE_APPEND);
    http_response_code(500);
    exit();
}

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

try {
    // Validação da assinatura do Stripe
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    
    $db = (new Database())->getConnection();
    $log_msg = "[" . date('Y-m-d H:i:s') . "] Evento recebido: " . $event->type . " | Ambiente: " . ($is_production ? 'PRODUÇÃO' : 'TESTE') . "\n";
    file_put_contents('log_webhook.txt', $log_msg, FILE_APPEND);

    switch ($event->type) {
        case 'invoice.payment_failed':
            $invoice = $event->data->object;
            $cus_id = $invoice->customer;
            
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'atrasado' WHERE stripe_customer_id = ?");
            $stmt->execute([$cus_id]);
            
            // NOTIFICAÇÃO POR EMAIL - CORRIGIDO
            $sql_email = "SELECT u.email, u.nome FROM usuarios u 
                         JOIN vendedores v ON u.id = v.usuario_id 
                         WHERE v.stripe_customer_id = ?";
            $stmt_email = $db->prepare($sql_email);
            $stmt_email->execute([$cus_id]);
            $vendedor_info = $stmt_email->fetch(PDO::FETCH_ASSOC);
            
            if ($vendedor_info && !empty($vendedor_info['email'])) {
                $subject = "Pagamento da Assinatura Não Processado - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($vendedor_info['nome']) . ",\n\n";
                $message .= "O pagamento da sua assinatura não foi processado com sucesso.\n\n";
                $message .= "Detalhes:\n";
                $message .= "- Status: Atrasado\n";
                $message .= "- Data: " . date('d/m/Y H:i') . "\n";
                $message .= "- ID da Cobrança: " . $invoice->id . "\n\n";
                $message .= "Por favor, verifique os dados do seu método de pagamento.\n";
                $message .= "Se o problema persistir, entre em contato com nosso suporte.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($vendedor_info['email'], $vendedor_info['nome'], $subject, $message);
            }
            
            file_put_contents('log_webhook.txt', "STATUS: Atrasado | Cliente: $cus_id | Linhas: " . $stmt->rowCount() . "\n", FILE_APPEND);
            break;

        case 'invoice.paid':
            $invoice = $event->data->object;
            $cus_id = $invoice->customer;
            
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo' WHERE stripe_customer_id = ?");
            $stmt->execute([$cus_id]);
            
            // NOTIFICAÇÃO POR EMAIL - CORRIGIDO
            $sql_email = "SELECT u.email, u.nome, p.nome as plano_nome FROM usuarios u 
                         JOIN vendedores v ON u.id = v.usuario_id 
                         JOIN planos p ON v.plano_id = p.id
                         WHERE v.stripe_customer_id = ?";
            $stmt_email = $db->prepare($sql_email);
            $stmt_email->execute([$cus_id]);
            $vendedor_info = $stmt_email->fetch(PDO::FETCH_ASSOC);
            
            if ($vendedor_info && !empty($vendedor_info['email'])) {
                $subject = "Pagamento Confirmado - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($vendedor_info['nome']) . ",\n\n";
                $message .= "O pagamento da sua assinatura foi confirmado com sucesso!\n\n";
                $message .= "Detalhes:\n";
                $message .= "- Plano: " . htmlspecialchars($vendedor_info['plano_nome']) . "\n";
                $message .= "- Status: Ativo\n";
                $message .= "- Data: " . date('d/m/Y H:i') . "\n";
                $message .= "- Valor: R$ " . number_format($invoice->amount_paid / 100, 2, ',', '.') . "\n";
                $message .= "- ID da Cobrança: " . $invoice->id . "\n\n";
                $message .= "Sua assinatura está ativa e todos os recursos estão disponíveis.\n";
                $message .= "Agradecemos por sua confiança!\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($vendedor_info['email'], $vendedor_info['nome'], $subject, $message);
            }
            
            file_put_contents('log_webhook.txt', "STATUS: Ativo (renovação) | Cliente: $cus_id | Linhas: " . $stmt->rowCount() . "\n", FILE_APPEND);
            break;

        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            $sub_id = $subscription->id;
            
            $stmt = $db->prepare("UPDATE vendedores SET plano_id = 1, status_assinatura = 'expirado' WHERE stripe_subscription_id = ?");
            $stmt->execute([$sub_id]);
            
            // NOTIFICAÇÃO POR EMAIL - CORRIGIDO
            $sql_email = "SELECT u.email, u.nome FROM usuarios u 
                         JOIN vendedores v ON u.id = v.usuario_id 
                         WHERE v.stripe_subscription_id = ?";
            $stmt_email = $db->prepare($sql_email);
            $stmt_email->execute([$sub_id]);
            $vendedor_info = $stmt_email->fetch(PDO::FETCH_ASSOC);
            
            if ($vendedor_info && !empty($vendedor_info['email'])) {
                $subject = "Assinatura Cancelada - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($vendedor_info['nome']) . ",\n\n";
                $message .= "Sua assinatura foi cancelada.\n\n";
                $message .= "Detalhes:\n";
                $message .= "- Status: Expirado/Cancelado\n";
                $message .= "- Data: " . date('d/m/Y H:i') . "\n";
                $message .= "- ID da Assinatura: " . $sub_id . "\n\n";
                $message .= "Seu plano foi rebaixado para o plano gratuito.\n";
                $message .= "Você pode assinar um novo plano a qualquer momento.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($vendedor_info['email'], $vendedor_info['nome'], $subject, $message);
            }
            
            file_put_contents('log_webhook.txt', "STATUS: Expirado (assinatura deletada) | Subscription: $sub_id | Linhas: " . $stmt->rowCount() . "\n", FILE_APPEND);
            break;

        // TRATAMENTO PARA UPGRADE/DOWNGRADE DE PLANOS
        case 'customer.subscription.updated':
            $subscription = $event->data->object;
            $cus_id = $subscription->customer;
            
            file_put_contents('log_webhook.txt', "[" . date('Y-m-d H:i:s') . "] Processing subscription.updated para cliente: $cus_id\n", FILE_APPEND);
            
            try {
                // Recupera a assinatura completa para obter o novo preço
                $stripe_sub = \Stripe\Subscription::retrieve($subscription->id);
                $novo_price_id = $stripe_sub->items->data[0]->price->id;
                
                file_put_contents('log_webhook.txt', "Novo price_id: $novo_price_id\n", FILE_APPEND);
                
                // Busca o plano correspondente ao novo price_id no banco
                $stmt_plano = $db->prepare("SELECT id, nome FROM planos WHERE stripe_price_id = ?");
                $stmt_plano->execute([$novo_price_id]);
                $novo_plano = $stmt_plano->fetch(PDO::FETCH_ASSOC);
                
                if ($novo_plano) {
                    // Atualiza o plano do vendedor
                    $stmt_update = $db->prepare("UPDATE vendedores SET plano_id = ?, status_assinatura = 'ativo' WHERE stripe_customer_id = ?");
                    $resultado = $stmt_update->execute([$novo_plano['id'], $cus_id]);
                    
                    // NOTIFICAÇÃO POR EMAIL - CORRIGIDO
                    $sql_email = "SELECT u.email, u.nome, p_antigo.nome as plano_antigo FROM usuarios u 
                                 JOIN vendedores v ON u.id = v.usuario_id 
                                 JOIN planos p_antigo ON v.plano_id = p_antigo.id
                                 WHERE v.stripe_customer_id = ?";
                    $stmt_email = $db->prepare($sql_email);
                    $stmt_email->execute([$cus_id]);
                    $vendedor_info = $stmt_email->fetch(PDO::FETCH_ASSOC);
                    
                    if ($vendedor_info && !empty($vendedor_info['email'])) {
                        $subject = "Plano Atualizado - Encontre o Campo";
                        $message = "Olá " . htmlspecialchars($vendedor_info['nome']) . ",\n\n";
                        $message .= "Seu plano foi atualizado com sucesso!\n\n";
                        $message .= "Detalhes da atualização:\n";
                        $message .= "- Plano Anterior: " . htmlspecialchars($vendedor_info['plano_antigo']) . "\n";
                        $message .= "- Novo Plano: " . htmlspecialchars($novo_plano['nome']) . "\n";
                        $message .= "- Status: Ativo\n";
                        $message .= "- Data: " . date('d/m/Y H:i') . "\n\n";
                        $message .= "Todos os recursos do novo plano já estão disponíveis para você.\n";
                        $message .= "Agradecemos por sua confiança!\n\n";
                        $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                        
                        enviarEmailNotificacao($vendedor_info['email'], $vendedor_info['nome'], $subject, $message);
                    }
                    
                    $log_update = "[" . date('Y-m-d H:i:s') . "] ✓ Plano atualizado: Cliente=$cus_id, Novo Plano ID=" . $novo_plano['id'] . " (" . $novo_plano['nome'] . "), Linhas atualizadas=" . $stmt_update->rowCount() . "\n";
                    file_put_contents('log_webhook.txt', $log_update, FILE_APPEND);
                } else {
                    file_put_contents('log_webhook.txt', "✗ ERRO: Plano não encontrado para price_id: $novo_price_id\n", FILE_APPEND);
                }
            } catch (\Exception $e) {
                $error_msg = "[" . date('Y-m-d H:i:s') . "] ✗ Erro ao processar subscription.updated: " . $e->getMessage() . "\n";
                file_put_contents('log_webhook.txt', $error_msg, FILE_APPEND);
            }
            break;

        case 'checkout.session.completed':
            $session = $event->data->object;
            $v_id = $session->metadata->vendedor_id;
            $p_id = $session->metadata->plano_id;
            $sub_id = $session->subscription;
            $cus_id = $session->customer;

            $stmt = $db->prepare("UPDATE vendedores SET plano_id = ?, stripe_customer_id = ?, stripe_subscription_id = ?, status_assinatura = 'ativo' WHERE id = ?");
            $stmt->execute([$p_id, $cus_id, $sub_id, $v_id]);
            
            // NOTIFICAÇÃO POR EMAIL - CORRIGIDO
            $sql_email = "SELECT u.email, u.nome, p.nome as plano_nome FROM usuarios u 
                         JOIN vendedores v ON u.id = v.usuario_id 
                         JOIN planos p ON p.id = ?
                         WHERE v.id = ?";
            $stmt_email = $db->prepare($sql_email);
            $stmt_email->execute([$p_id, $v_id]);
            $vendedor_info = $stmt_email->fetch(PDO::FETCH_ASSOC);
            
            if ($vendedor_info && !empty($vendedor_info['email'])) {
                $subject = "Assinatura Ativada com Sucesso! - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($vendedor_info['nome']) . ",\n\n";
                $message .= "Parabéns! Sua assinatura foi ativada com sucesso.\n\n";
                $message .= "Detalhes da sua assinatura:\n";
                $message .= "- Plano: " . htmlspecialchars($vendedor_info['plano_nome']) . "\n";
                $message .= "- Status: Ativo\n";
                $message .= "- Data de Ativação: " . date('d/m/Y H:i') . "\n";
                $message .= "- ID da Assinatura: " . $sub_id . "\n\n";
                $message .= "Agora você tem acesso a todos os recursos do seu plano.\n";
                $message .= "Bem-vindo(a) ao Encontre o Campo Premium!\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($vendedor_info['email'], $vendedor_info['nome'],  $subject, $message);
            }
            
            file_put_contents('log_webhook.txt', "STATUS: Ativo (checkout completo) | Vendedor: $v_id, Plano: $p_id, Linhas: " . $stmt->rowCount() . "\n", FILE_APPEND);
            break;

        default:
            file_put_contents('log_webhook.txt', "[" . date('Y-m-d H:i:s') . "] Evento não processado: " . $event->type . "\n", FILE_APPEND);
    }

    http_response_code(200);

} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Assinatura inválida
    $error_log = "[" . date('Y-m-d H:i:s') . "] ✗ ERRO DE ASSINATURA: " . $e->getMessage() . " | Endpoint Secret pode estar incorreto!\n";
    file_put_contents('log_webhook.txt', $error_log, FILE_APPEND);
    http_response_code(400);
    exit();
} catch (\Exception $e) {
    // Outros erros
    $error_log = "[" . date('Y-m-d H:i:s') . "] ✗ ERRO NO WEBHOOK: " . $e->getMessage() . " em " . $e->getFile() . " na linha " . $e->getLine() . "\n";
    file_put_contents('log_webhook.txt', $error_log, FILE_APPEND);
    
    // Retorna 500 para o Stripe tentar reenviar o evento mais tarde
    http_response_code(500); 
    exit();
}
?>