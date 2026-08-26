<?php
/**
 * Webhook Stripe para EncontreOCampo
 * Processa eventos de assinatura, pagamentos e atualizações
 * 
 * @package EncontreOCampo
 * @author Seu Nome
 * @version 2.0.0
 */

// =============================================
// CARREGAR DEPENDÊNCIAS E CONFIGURAÇÕES
// =============================================

// Carregar variáveis de ambiente do .env na raiz
require_once __DIR__ . '/../../vendor/autoload.php'; // Ajuste o caminho se necessário

use Dotenv\Dotenv;

// Carregar .env da raiz do projeto
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Carregar configurações do Stripe e conexão com banco
require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';

// Inicializar Stripe
\Config\StripeConfig::init();

// =============================================
// FUNÇÕES HELPER
// =============================================

/**
 * Função para logging estruturado
 * 
 * @param string $message Mensagem de log
 * @param string $type Tipo do log (INFO, ERRO, AVISO, SUCESSO, DEBUG)
 * @return void
 */
function writeLog($message, $type = 'INFO') {
    // Criar diretório de logs se não existir
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/stripe_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // Escrever no arquivo de log diário
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    
    // Manter log completo para compatibilidade (opcional)
    file_put_contents(__DIR__ . '/log_webhook.txt', $formattedMessage, FILE_APPEND);
    
    // Em ambiente de desenvolvimento, também pode logar no error_log
    if ($_ENV['APP_ENV'] ?? 'production' === 'development') {
        error_log("[WEBHOOK] $message");
    }
}

/**
 * Envia notificações por email (placeholder - implementar depois)
 * 
 * @param string $to Email do destinatário
 * @param string $subject Assunto do email
 * @param string $message Corpo do email
 * @return void
 */
function sendNotification($to, $subject, $message) {
    // TODO: Implementar sistema de email real
    // Por enquanto só registra no log
    writeLog("Notificação para $to: $subject - $message", 'NOTIFICACAO');
    
    // Exemplo futuro com PHPMailer:
    // $mail = new PHPMailer(true);
    // ... configurações de email
    // $mail->send();
}

/**
 * Valida se a requisição veio dos IPs da Stripe (camada extra de segurança)
 * 
 * @return bool
 */
function isFromStripe() {
    // Lista oficial de IPs da Stripe (atualize periodicamente)
    $stripe_ips = [
        '54.187.174.169',
        '54.187.205.235',
        '54.187.216.72',
        '54.241.31.99',
        '54.241.31.102',
        '54.241.34.107'
    ];
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $is_production = ($_ENV['APP_ENV'] ?? 'production') === 'production';
    
    // Em produção, bloquear IPs não autorizados
    if ($is_production && !in_array($client_ip, $stripe_ips)) {
        writeLog("Tentativa de acesso de IP não autorizado: $client_ip", 'SEGURANCA');
        return false;
    }
    
    return true;
}

// =============================================
// VALIDAÇÕES INICIAIS DE SEGURANÇA
// =============================================

// Verificar se é uma requisição POST (webhooks são sempre POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeLog("Método não permitido: " . $_SERVER['REQUEST_METHOD'], 'ERRO');
    http_response_code(405); // Method Not Allowed
    exit();
}

// Verificar IP de origem (opcional - descomente se quiser ativar)
// if (!isFromStripe()) {
//     http_response_code(403);
//     exit();
// }

// =============================================
// CONFIGURAÇÃO DA CHAVE DO WEBHOOK
// =============================================

// Determinar ambiente
$is_production = ($_ENV['APP_ENV'] ?? 'production') === 'production';

// Pegar chave secreta do .env (NUNCA hardcoded!)
$endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;

// Fallback seguro para ambiente de teste (NÃO USAR EM PRODUÇÃO!)
if (!$endpoint_secret && !$is_production) {
    $endpoint_secret = 'whsec_test_replace_me';
    writeLog("⚠️ AVISO: Usando chave de teste hardcoded. Configure .env para produção!", 'AVISO');
}

// Verificar se a chave foi encontrada
if (!$endpoint_secret) {
    writeLog("❌ ERRO CRÍTICO: STRIPE_WEBHOOK_SECRET não definido no .env", 'ERRO');
    http_response_code(500);
    exit();
}

writeLog("🔧 Webhook inicializado - Ambiente: " . ($is_production ? 'PRODUÇÃO' : 'TESTE'), 'INFO');

// =============================================
// PROCESSAR WEBHOOK
// =============================================

// Obter payload e assinatura
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$event = null;

// Log do payload recebido (em produção, talvez não queira logar payload completo)
writeLog("📦 Payload recebido - Tamanho: " . strlen($payload) . " bytes", 'DEBUG');

try {
    // Verificar assinatura do webhook
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    
    // Conectar ao banco de dados
    $db = (new Database())->getConnection();
    
    // Registrar evento recebido
    writeLog("✅ Evento recebido: " . $event->type . " | ID: " . $event->id, 'EVENTO');

    // =========================================
    // PROCESSAR DIFERENTES TIPOS DE EVENTO
    // =========================================
    
    switch ($event->type) {
        
        // ===== EVENTOS DE PAGAMENTO =====
        
        case 'invoice.payment_failed':
            $invoice = $event->data->object;
            $cus_id = $invoice->customer;
            $customer_email = $invoice->customer_email ?? 'email não disponível';
            
            // Atualizar status da assinatura para atrasado
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'atrasado' WHERE stripe_customer_id = ?");
            $stmt->execute([$cus_id]);
            
            writeLog("💰 PAGAMENTO FALHOU | Cliente: $cus_id | Email: $customer_email | Linhas: " . $stmt->rowCount(), 'PAYMENT_FAILED');
            
            // Notificar cliente e admin
            sendNotification($customer_email, '⚠️ Pagamento falhou', 'Seu pagamento falhou. Por favor, atualize seu método de pagamento para evitar a suspensão da sua conta.');
            sendNotification($_ENV['ADMIN_EMAIL'] ?? 'admin@encontreocampo.com.br', 'Pagamento falhou - Cliente', "Cliente: $customer_email ($cus_id)");
            break;
            
        case 'invoice.payment_succeeded':
            writeLog("📄 Processando invoice.payment_succeeded", 'DEBUG');
            // Fall through para usar mesmo código do invoice.paid
        case 'invoice.paid':
            $invoice = $event->data->object;
            $cus_id = $invoice->customer;
            $customer_email = $invoice->customer_email ?? 'email não disponível';
            
            // Buscar subscription_id no payload
            $subscription_id = null;
            
            if (isset($invoice->subscription) && !empty($invoice->subscription)) {
                $subscription_id = $invoice->subscription;
                writeLog("📋 Subscription encontrada em invoice->subscription: $subscription_id", 'DEBUG');
            } elseif (isset($invoice->parent->subscription_details->subscription)) {
                $subscription_id = $invoice->parent->subscription_details->subscription;
                writeLog("📋 Subscription encontrada em parent.subscription_details.subscription: $subscription_id", 'DEBUG');
            }
            
            if (!$subscription_id) {
                writeLog("❌ Não foi possível encontrar subscription_id no payload", 'ERRO');
                http_response_code(200); // Stripe espera 200 mesmo se não processarmos
                exit();
            }
            
            writeLog("💰 invoice.paid - Cliente: $cus_id, Subscription: $subscription_id", 'INFO');
            
            try {
                // Obter data do próximo vencimento
                $next_billing_date = null;
                
                // Tentar pegar da invoice primeiro
                if (isset($invoice->lines->data[0]->period->end)) {
                    $next_period_end = $invoice->lines->data[0]->period->end;
                    $next_billing_date = date('Y-m-d H:i:s', $next_period_end);
                    writeLog("📅 Próximo vencimento encontrado na invoice: $next_billing_date", 'INFO');
                } 
                
                // Se não achou, buscar da subscription
                if (!$next_billing_date) {
                    $subscription = \Stripe\Subscription::retrieve($subscription_id);
                    
                    if (isset($subscription->current_period_end)) {
                        $next_billing_date = date('Y-m-d H:i:s', $subscription->current_period_end);
                        writeLog("📅 Usando current_period_end da subscription: $next_billing_date", 'AVISO');
                    } elseif (isset($subscription->items->data[0]->current_period_end)) {
                        $next_billing_date = date('Y-m-d H:i:s', $subscription->items->data[0]->current_period_end);
                        writeLog("📅 Usando current_period_end do subscription item: $next_billing_date", 'INFO');
                    } else {
                        throw new \Exception("Não foi possível encontrar a data de vencimento");
                    }
                }
                
                // Atualizar banco de dados
                $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo', data_vencimento_assinatura = ? WHERE stripe_customer_id = ?");
                $result = $stmt->execute([$next_billing_date, $cus_id]);
                $rowCount = $stmt->rowCount();
                
                writeLog("✅ Pagamento processado com sucesso | Cliente=$cus_id | Próximo vencimento=$next_billing_date | Linhas afetadas=$rowCount", 'SUCESSO');
                
            } catch (\Exception $e) {
                writeLog("❌ ERRO ao processar data de vencimento: " . $e->getMessage(), 'ERRO');
                
                // Fallback: atualiza só o status
                $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo' WHERE stripe_customer_id = ?");
                $stmt->execute([$cus_id]);
                writeLog("⚠️ Fallback executado - apenas status atualizado para ativo", 'AVISO');
            }
            break;
            
        // ===== EVENTOS DE CHECKOUT =====
        
        case 'checkout.session.completed':
            $session = $event->data->object;
            $v_id = $session->metadata->vendedor_id ?? null;
            $p_id = $session->metadata->plano_id ?? null;
            $sub_id = $session->subscription ?? null;
            $cus_id = $session->customer ?? null;
            $customer_email = $session->customer_details->email ?? 'email não disponível';
            
            // Validar dados obrigatórios
            if (!$v_id || !$p_id || !$sub_id || !$cus_id) {
                writeLog("❌ Dados incompletos no checkout.session.completed", 'ERRO');
                http_response_code(200);
                exit();
            }

            try {
                $subscription = \Stripe\Subscription::retrieve($sub_id);
                
                // Obter data do próximo vencimento
                if (isset($subscription->current_period_end)) {
                    $next_billing_date = date('Y-m-d H:i:s', $subscription->current_period_end);
                } elseif (isset($subscription->items->data[0]->current_period_end)) {
                    $next_billing_date = date('Y-m-d H:i:s', $subscription->items->data[0]->current_period_end);
                } else {
                    $next_billing_date = date('Y-m-d H:i:s', strtotime('+1 month'));
                    writeLog("📅 Usando data estimada +1 mês para checkout: $next_billing_date", 'AVISO');
                }
                
                // Atualizar vendedor
                $stmt = $db->prepare("UPDATE vendedores SET plano_id = ?, stripe_customer_id = ?, stripe_subscription_id = ?, status_assinatura = 'ativo', data_vencimento_assinatura = ? WHERE id = ?");
                $stmt->execute([$p_id, $cus_id, $sub_id, $next_billing_date, $v_id]);
                
                writeLog("🆕 NOVA ASSINATURA | Vendedor=$v_id | Plano=$p_id | Cliente=$cus_id | Email=$customer_email | Próximo vencimento=$next_billing_date", 'SUCESSO');
                
                // Enviar email de boas-vindas
                sendNotification($customer_email, '🎉 Bem-vindo ao EncontreOCampo!', 'Sua assinatura foi ativada com sucesso. Aproveite todos os recursos do seu plano!');
                
            } catch (\Exception $e) {
                // Fallback sem data de vencimento
                $stmt = $db->prepare("UPDATE vendedores SET plano_id = ?, stripe_customer_id = ?, stripe_subscription_id = ?, status_assinatura = 'ativo' WHERE id = ?");
                $stmt->execute([$p_id, $cus_id, $sub_id, $v_id]);
                
                writeLog("⚠️ Checkout com fallback | Vendedor=$v_id | Erro: " . $e->getMessage(), 'AVISO');
            }
            break;
            
        // ===== EVENTOS DE ASSINATURA =====
        
        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            $sub_id = $subscription->id;
            $cus_id = $subscription->customer ?? 'não informado';
            
            $stmt = $db->prepare("UPDATE vendedores SET plano_id = 1, status_assinatura = 'expirado', data_vencimento_assinatura = NULL WHERE stripe_subscription_id = ?");
            $stmt->execute([$sub_id]);
            
            writeLog("🗑️ ASSINATURA DELETADA | Cliente: $cus_id | Subscription: $sub_id | Linhas: " . $stmt->rowCount(), 'SUBSCRIPTION_DELETED');
            
            // Notificar cliente
            sendNotification($subscription->customer_email ?? 'cliente@email.com', 'Assinatura cancelada', 'Sua assinatura foi cancelada conforme solicitado.');
            break;
            
        case 'customer.subscription.paused':
            $subscription = $event->data->object;
            $sub_id = $subscription->id;
            $cus_id = $subscription->customer;
            
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'pausado' WHERE stripe_subscription_id = ?");
            $stmt->execute([$sub_id]);
            
            writeLog("⏸️ ASSINATURA PAUSADA | Cliente: $cus_id | Subscription: $sub_id | Linhas: " . $stmt->rowCount(), 'SUBSCRIPTION_PAUSED');
            
            sendNotification($subscription->customer_email ?? 'cliente@email.com', 'Assinatura pausada', 'Sua assinatura foi pausada com sucesso.');
            break;
            
        case 'customer.subscription.resumed':
            $subscription = $event->data->object;
            $sub_id = $subscription->id;
            $cus_id = $subscription->customer;
            
            $stmt = $db->prepare("UPDATE vendedores SET status_assinatura = 'ativo' WHERE stripe_subscription_id = ?");
            $stmt->execute([$sub_id]);
            
            writeLog("▶️ ASSINATURA RETOMADA | Cliente: $cus_id | Subscription: $sub_id | Linhas: " . $stmt->rowCount(), 'SUBSCRIPTION_RESUMED');
            
            sendNotification($subscription->customer_email ?? 'cliente@email.com', 'Assinatura retomada', 'Sua assinatura foi retomada com sucesso.');
            break;
            
        case 'customer.subscription.updated':
            $subscription = $event->data->object;
            $cus_id = $subscription->customer;
            
            writeLog("🔄 Processando subscription.updated para cliente: $cus_id", 'DEBUG');
            
            try {
                $stripe_sub = \Stripe\Subscription::retrieve($subscription->id);
                $novo_price_id = $stripe_sub->items->data[0]->price->id;
                
                // Obter próxima data de vencimento
                if (isset($stripe_sub->current_period_end)) {
                    $next_billing_date = date('Y-m-d H:i:s', $stripe_sub->current_period_end);
                } elseif (isset($stripe_sub->items->data[0]->current_period_end)) {
                    $next_billing_date = date('Y-m-d H:i:s', $stripe_sub->items->data[0]->current_period_end);
                } else {
                    $next_billing_date = date('Y-m-d H:i:s', strtotime('+1 month'));
                    writeLog("📅 Usando data estimada +1 mês: $next_billing_date", 'AVISO');
                }
                
                writeLog("📊 Novo price_id: $novo_price_id | Próximo vencimento: $next_billing_date", 'DEBUG');
                
                // Buscar plano correspondente
                $stmt_plano = $db->prepare("SELECT id, nome FROM planos WHERE stripe_price_id = ?");
                $stmt_plano->execute([$novo_price_id]);
                $novo_plano = $stmt_plano->fetch(PDO::FETCH_ASSOC);
                
                if ($novo_plano) {
                    $stmt_update = $db->prepare("UPDATE vendedores SET plano_id = ?, status_assinatura = 'ativo', data_vencimento_assinatura = ? WHERE stripe_customer_id = ?");
                    $resultado = $stmt_update->execute([$novo_plano['id'], $next_billing_date, $cus_id]);
                    
                    writeLog("✅ Plano atualizado: Cliente=$cus_id | Novo Plano=" . $novo_plano['nome'] . " | Próximo vencimento=$next_billing_date", 'SUCESSO');
                } else {
                    writeLog("❌ ERRO: Plano não encontrado para price_id: $novo_price_id", 'ERRO');
                }
            } catch (\Exception $e) {
                writeLog("❌ Erro ao processar subscription.updated: " . $e->getMessage(), 'ERRO');
            }
            break;
            
        case 'customer.subscription.trial_will_end':
            $subscription = $event->data->object;
            $cus_id = $subscription->customer;
            $trial_end = date('Y-m-d H:i:s', $subscription->trial_end);
            
            writeLog("⏰ AVISO: Trial terminará em $trial_end para cliente $cus_id", 'AVISO');
            sendNotification($subscription->customer_email ?? 'cliente@email.com', 'Período de teste terminando', "Seu período de teste gratuito termina em $trial_end. Não se esqueça de configurar seu pagamento!");
            break;
            
        // ===== EVENTOS NÃO PROCESSADOS =====
        
        default:
            writeLog("ℹ️ Evento não processado (ignorado): " . $event->type, 'IGNORADO');
    }

    // Responder com sucesso para a Stripe
    http_response_code(200);
    writeLog("✅ Webhook processado com sucesso", 'FIM');

} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Erro de assinatura - requisição não é realmente da Stripe
    writeLog("🔒 ERRO DE ASSINATURA: " . $e->getMessage(), 'ERRO');
    http_response_code(400);
    exit();
    
} catch (\Exception $e) {
    // Outros erros
    writeLog("❌ ERRO NO WEBHOOK: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine(), 'ERRO');
    http_response_code(500);
    exit();
}