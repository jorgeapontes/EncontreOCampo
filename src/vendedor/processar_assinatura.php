<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';

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
                    'plano_id' => $id_plano
                ]
            ]);

            header("Location: " . $session->url);
            exit;
        } catch (Exception $e) {
            die("Erro ao criar sessão de pagamento: " . $e->getMessage());
        }
    } else {
        die("Erro: Plano inválido ou sem configuração de preço no Stripe.");
    }
} else {
    header("Location: escolher_plano.php");
    exit;
}