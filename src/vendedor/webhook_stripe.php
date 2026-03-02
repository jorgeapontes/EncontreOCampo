<?php
require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';

\Config\StripeConfig::init();

// Função helper para logs (ITEM 2 - Melhorar sistema de logs)
function writeLog($message, $type = 'INFO') {
    // Criar diretório de logs se não existir
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/stripe_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    
    // Manter apenas o log completo também para compatibilidade
    file_put_contents('log_webhook.txt', $formattedMessage, FILE_APPEND);
}

// Função para enviar notificação por email (pode ser expandida depois)
function sendNotification($to, $subject, $message) {
    // Implementar envio de email aqui
    // Por enquanto só loga
    writeLog("Notificação para $to: $subject - $message", 'NOTIFICACAO');
}

// Detectar o ambiente (produção ou teste) baseado no servidor
$is_production = !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) && $_SERVER['HTTP_HOST'] !== '';

// Chave do webhook hardcoded conforme ambiente
if ($is_production) {
    $endpoint_secret = '';
} else {
    $endpoint_secret = 'whsec_test_replace_me';
}

if (!$endpoint_secret) {
    writeLog("STRIPE_WEBHOOK_SECRET não definido", 'ERRO');
    http_response_code(500);
    exit();
}

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    
    $db = (new Database())->getConnection();
    
    // Registrar evento recebido
    writeLog("Evento recebido: " . $event->type . " | Ambiente: " . ($is_production ? 'PRODUÇÃO' : 'TESTE'), 'EVENTO');

    switch ($event->type) {
        case 'invoice.payment_failed':
            $invoice = $event->data->object;
            $cus_id = $invoice->customer;
            $customer_email = $invoice->customer_email ?? 'email não disponível';
            
            // Atualizar status
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'atrasado' WHERE stripe_customer_id = ?");
            $stmt->execute([$cus_id]);
            
            writeLog("STATUS: Atrasado | Cliente: $cus_id | Email: $customer_email | Linhas: " . $stmt->rowCount(), 'PAYMENT_FAILED');
            
            // Notificar cliente e admin (ITEM 4 - notificações)
            sendNotification($customer_email, 'Pagamento falhou', 'Seu pagamento falhou. Por favor, atualize seu método de pagamento.');
            sendNotification('admin@seudominio.com', 'Pagamento falhou - Cliente', "Cliente: $customer_email ($cus_id)");
            break;

        case 'invoice.payment_succeeded': // ITEM 1 - Novo evento
            writeLog("Processando invoice.payment_succeeded", 'DEBUG');
            // Mesmo tratamento do invoice.paid, mas com pequenas diferenças
            // Fall through para usar o mesmo código do invoice.paid
        case 'invoice.paid':
            $invoice = $event->data->object;
            $cus_id = $invoice->customer;
            $customer_email = $invoice->customer_email ?? 'email não disponível';
            
            // Buscar o subscription_id do local correto no payload
            $subscription_id = null;
            
            if (isset($invoice->subscription) && !empty($invoice->subscription)) {
                $subscription_id = $invoice->subscription;
                writeLog("Subscription encontrada em invoice->subscription: $subscription_id", 'DEBUG');
            } 
            elseif (isset($invoice->parent->subscription_details->subscription)) {
                $subscription_id = $invoice->parent->subscription_details->subscription;
                writeLog("Subscription encontrada em parent.subscription_details.subscription: $subscription_id", 'DEBUG');
            }
            
            if (!$subscription_id) {
                writeLog("Não foi possível encontrar subscription_id no payload", 'ERRO');
                http_response_code(200);
                exit();
            }
            
            writeLog("invoice.paid - Cliente: $cus_id, Subscription: $subscription_id", 'INFO');
            
            try {
                // Pegar a data do PRÓXIMO vencimento diretamente da invoice
                $next_billing_date = null;
                
                if (isset($invoice->lines->data[0]->period->end)) {
                    $next_period_end = $invoice->lines->data[0]->period->end;
                    $next_billing_date = date('Y-m-d H:i:s', $next_period_end);
                    writeLog("Próximo vencimento encontrado na invoice: $next_billing_date", 'INFO');
                } 
                
                if (!$next_billing_date) {
                    $subscription = \Stripe\Subscription::retrieve($subscription_id);
                    
                    if (isset($subscription->current_period_end)) {
                        $next_billing_date = date('Y-m-d H:i:s', $subscription->current_period_end);
                        writeLog("AVISO: Usando current_period_end da subscription: $next_billing_date", 'AVISO');
                    } elseif (isset($subscription->items->data[0]->current_period_end)) {
                        $next_billing_date = date('Y-m-d H:i:s', $subscription->items->data[0]->current_period_end);
                        writeLog("Usando current_period_end do subscription item: $next_billing_date", 'INFO');
                    } else {
                        throw new \Exception("Não foi possível encontrar a data de vencimento");
                    }
                }
                
                // Atualizar status e data de vencimento
                $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo', data_vencimento_assinatura = ? WHERE stripe_customer_id = ?");
                $result = $stmt->execute([$next_billing_date, $cus_id]);
                $rowCount = $stmt->rowCount();
                
                writeLog("Pagamento processado: Cliente=$cus_id, Próximo vencimento=$next_billing_date, Linhas afetadas=$rowCount", 'SUCESSO');
                
            } catch (\Exception $e) {
                writeLog("ERRO ao processar data de vencimento: " . $e->getMessage(), 'ERRO');
                
                // Fallback: atualiza só o status
                $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo' WHERE stripe_customer_id = ?");
                $stmt->execute([$cus_id]);
            }
            break;

        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            $sub_id = $subscription->id;
            
            $stmt = $db->prepare("UPDATE vendedores SET plano_id = 1, status_assinatura = 'expirado', data_vencimento_assinatura = NULL WHERE stripe_subscription_id = ?");
            $stmt->execute([$sub_id]);
            
            writeLog("STATUS: Expirado (assinatura deletada) | Subscription: $sub_id | Linhas: " . $stmt->rowCount(), 'SUBSCRIPTION_DELETED');
            break;

        case 'customer.subscription.paused': // ITEM 1 - Novo evento
            $subscription = $event->data->object;
            $sub_id = $subscription->id;
            $cus_id = $subscription->customer;
            
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'pausado' WHERE stripe_subscription_id = ?");
            $stmt->execute([$sub_id]);
            
            writeLog("STATUS: Pausado | Cliente: $cus_id | Subscription: $sub_id | Linhas: " . $stmt->rowCount(), 'SUBSCRIPTION_PAUSED');
            
            // Notificar cliente
            sendNotification($subscription->customer_email ?? 'cliente@email.com', 'Assinatura pausada', 'Sua assinatura foi pausada com sucesso.');
            break;

        case 'customer.subscription.resumed': // ITEM 1 - Novo evento
            $subscription = $event->data->object;
            $sub_id = $subscription->id;
            $cus_id = $subscription->customer;
            
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo' WHERE stripe_subscription_id = ?");
            $stmt->execute([$sub_id]);
            
            writeLog("STATUS: Ativo (retomada) | Cliente: $cus_id | Subscription: $sub_id | Linhas: " . $stmt->rowCount(), 'SUBSCRIPTION_RESUMED');
            
            // Notificar cliente
            sendNotification($subscription->customer_email ?? 'cliente@email.com', 'Assinatura retomada', 'Sua assinatura foi retomada com sucesso.');
            break;

        case 'customer.subscription.updated':
            $subscription = $event->data->object;
            $cus_id = $subscription->customer;
            
            writeLog("Processing subscription.updated para cliente: $cus_id", 'DEBUG');
            
            try {
                $stripe_sub = \Stripe\Subscription::retrieve($subscription->id);
                $novo_price_id = $stripe_sub->items->data[0]->price->id;
                
                if (isset($stripe_sub->current_period_end)) {
                    $next_billing_date = date('Y-m-d H:i:s', $stripe_sub->current_period_end);
                } elseif (isset($stripe_sub->items->data[0]->current_period_end)) {
                    $next_billing_date = date('Y-m-d H:i:s', $stripe_sub->items->data[0]->current_period_end);
                } else {
                    $next_billing_date = date('Y-m-d H:i:s', strtotime('+1 month'));
                    writeLog("Usando data estimada +1 mês: $next_billing_date", 'AVISO');
                }
                
                writeLog("Novo price_id: $novo_price_id | Próximo vencimento: $next_billing_date", 'DEBUG');
                
                $stmt_plano = $db->prepare("SELECT id, nome FROM planos WHERE stripe_price_id = ?");
                $stmt_plano->execute([$novo_price_id]);
                $novo_plano = $stmt_plano->fetch(PDO::FETCH_ASSOC);
                
                if ($novo_plano) {
                    $stmt_update = $db->prepare("UPDATE vendedores SET plano_id = ?, status_assinatura = 'ativo', data_vencimento_assinatura = ? WHERE stripe_customer_id = ?");
                    $resultado = $stmt_update->execute([$novo_plano['id'], $next_billing_date, $cus_id]);
                    
                    writeLog("✓ Plano atualizado: Cliente=$cus_id, Novo Plano=" . $novo_plano['nome'] . ", Próximo vencimento=$next_billing_date", 'SUCESSO');
                } else {
                    writeLog("ERRO: Plano não encontrado para price_id: $novo_price_id", 'ERRO');
                }
            } catch (\Exception $e) {
                writeLog("Erro ao processar subscription.updated: " . $e->getMessage(), 'ERRO');
            }
            break;

        case 'checkout.session.completed':
            $session = $event->data->object;
            $v_id = $session->metadata->vendedor_id;
            $p_id = $session->metadata->plano_id;
            $sub_id = $session->subscription;
            $cus_id = $session->customer;
            $customer_email = $session->customer_details->email ?? 'email não disponível';

            try {
                $subscription = \Stripe\Subscription::retrieve($sub_id);
                
                if (isset($subscription->current_period_end)) {
                    $next_billing_date = date('Y-m-d H:i:s', $subscription->current_period_end);
                } elseif (isset($subscription->items->data[0]->current_period_end)) {
                    $next_billing_date = date('Y-m-d H:i:s', $subscription->items->data[0]->current_period_end);
                } else {
                    $next_billing_date = date('Y-m-d H:i:s', strtotime('+1 month'));
                    writeLog("Usando data estimada +1 mês para checkout: $next_billing_date", 'AVISO');
                }
                
                $stmt = $db->prepare("UPDATE vendedores SET plano_id = ?, stripe_customer_id = ?, stripe_subscription_id = ?, status_assinatura = 'ativo', data_vencimento_assinatura = ? WHERE id = ?");
                $stmt->execute([$p_id, $cus_id, $sub_id, $next_billing_date, $v_id]);
                
                writeLog("Nova assinatura: Vendedor=$v_id, Plano=$p_id, Cliente=$cus_id, Email=$customer_email, Próximo vencimento=$next_billing_date", 'SUCESSO');
                
                // Enviar email de boas-vindas
                sendNotification($customer_email, 'Bem-vindo!', 'Sua assinatura foi ativada com sucesso.');
                
            } catch (\Exception $e) {
                $stmt = $db->prepare("UPDATE vendedores SET plano_id = ?, stripe_customer_id = ?, stripe_subscription_id = ?, status_assinatura = 'ativo' WHERE id = ?");
                $stmt->execute([$p_id, $cus_id, $sub_id, $v_id]);
                
                writeLog("Checkout com fallback: Vendedor=$v_id, Erro: " . $e->getMessage(), 'AVISO');
            }
            break;

        case 'customer.subscription.trial_will_end': // ITEM 1 - Novo evento
            $subscription = $event->data->object;
            $cus_id = $subscription->customer;
            $trial_end = date('Y-m-d H:i:s', $subscription->trial_end);
            
            writeLog("AVISO: Trial terminará em $trial_end para cliente $cus_id", 'AVISO');
            // Notificar cliente sobre fim do trial
            sendNotification($subscription->customer_email ?? 'cliente@email.com', 'Trial terminando', "Seu período de teste termina em $trial_end");
            break;

        default:
            writeLog("Evento não processado: " . $event->type, 'IGNORADO');
    }

    http_response_code(200);
    writeLog("Webhook processado com sucesso", 'FIM');

} catch (\Stripe\Exception\SignatureVerificationException $e) {
    writeLog("ERRO DE ASSINATURA: " . $e->getMessage(), 'ERRO');
    http_response_code(400);
    exit();
} catch (\Exception $e) {
    writeLog("ERRO NO WEBHOOK: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine(), 'ERRO');
    http_response_code(500); 
    exit();
}