<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/send_notification.php'; // NOVO: Adicionado para notificações

use Config\StripeConfig;
StripeConfig::init();

$id_plano = $_GET['id'] ?? null;
$usuario_id = $_SESSION['usuario_id'] ?? null;

if ($id_plano && $usuario_id) {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Busca o ID REAL e o STATUS do vendedor vinculado a este usuário
    // CORREÇÃO: Adicionado 'status_assinatura' e 'plano_id' na busca para evitar o erro de Undefined Index
    $stmtVendedor = $db->prepare("SELECT id, status_assinatura, plano_id FROM vendedores WHERE usuario_id = :u_id");
    $stmtVendedor->execute([':u_id' => $usuario_id]);
    $vendedor = $stmtVendedor->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor) {
        die("Erro: Perfil de vendedor não encontrado para este usuário.");
    }

    // Verifica se já possui assinatura ativa antes de processar nova compra
    if (isset($vendedor['status_assinatura']) && $vendedor['status_assinatura'] === 'ativo' && $vendedor['plano_id'] > 1) {
        header("Location: escolher_plano.php?erro=assinatura_ativa");
        exit;
    }

    $vendedor_id_real = $vendedor['id'];

    // 2. Busca o preço da Stripe para o plano
    $stmtPlano = $db->prepare("SELECT nome, stripe_price_id FROM planos WHERE id = :id");
    $stmtPlano->execute([':id' => $id_plano]);
    $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);

    if ($plano && !empty($plano['stripe_price_id'])) {
        try {
            // Buscar informações do usuário para notificação
            $stmtUsuario = $db->prepare("SELECT nome, email FROM usuarios WHERE id = :usuario_id");
            $stmtUsuario->execute([':usuario_id' => $usuario_id]);
            $usuario_info = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
            
            $usuario_nome = $usuario_info['nome'] ?? 'Usuário';
            $usuario_email = $usuario_info['email'] ?? null;

            // Detectar a URL base dinamicamente baseado no servidor
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $base_url = $protocol . '://' . $host;
            
            // URLs de redirecionamento dinâmicas
            $success_url = $base_url . '/src/vendedor/redirects/sucesso.php?session_id={CHECKOUT_SESSION_ID}';
            $cancel_url = $base_url . '/src/vendedor/escolher_plano.php';
            
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'], 
                'line_items' => [[
                    'price' => $plano['stripe_price_id'],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'customer_email' => $_SESSION['usuario_email'] ?? null,
                'metadata' => [
                    'vendedor_id' => $vendedor_id_real, 
                    'plano_id' => $id_plano,
                    'usuario_nome' => $usuario_nome
                ]
            ]);

            // NOTIFICAÇÃO POR EMAIL - NOVO (antes do redirecionamento)
            if ($usuario_email) {
                $subject = "Processamento de Assinatura Iniciado - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($usuario_nome) . ",\n\n";
                $message .= "Você iniciou o processo de assinatura do plano '" . htmlspecialchars($plano['nome']) . "'.\n\n";
                $message .= "Detalhes:\n";
                $message .= "- Plano: " . htmlspecialchars($plano['nome']) . "\n";
                $message .= "- Data/Hora: " . date('d/m/Y H:i') . "\n";
                $message .= "- Status: Processamento iniciado\n\n";
                $message .= "Você será redirecionado para a página de pagamento do Stripe.\n";
                $message .= "Após a confirmação do pagamento, seu plano será ativado automaticamente.\n\n";
                $message .= "Caso tenha algum problema, entre em contato com nosso suporte.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao('rafaeltonetti.cardoso@gmail.com', $usuario_nome, $subject, $message);
            }

            header("Location: " . $session->url);
            exit;
        } catch (Exception $e) {
            // NOTIFICAÇÃO DE ERRO - NOVO
            if ($usuario_email) {
                $subject = "Erro no Processamento da Assinatura - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($usuario_nome) . ",\n\n";
                $message .= "Ocorreu um erro ao processar sua solicitação de assinatura.\n\n";
                $message .= "Detalhes do erro:\n";
                $message .= "- Plano: " . htmlspecialchars($plano['nome']) . "\n";
                $message .= "- Data/Hora: " . date('d/m/Y H:i') . "\n";
                $message .= "- Erro: " . $e->getMessage() . "\n\n";
                $message .= "Por favor, tente novamente ou entre em contato com nosso suporte.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao('rafaeltonetti.cardoso@gmail.com', $usuario_nome, $subject, $message);
            }
            
            die("Erro ao criar sessão de pagamento: " . $e->getMessage());
        }
    } else {
        die("Erro: Plano inválido ou sem configuração de preço no Stripe.");
    }
} else {
    header("Location: escolher_plano.php");
    exit;
}
?>