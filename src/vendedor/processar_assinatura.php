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

    // 1. Busca o ID REAL do vendedor vinculado a este usuário
    $stmtVendedor = $db->prepare("SELECT id FROM vendedores WHERE usuario_id = :u_id");
    $stmtVendedor->execute([':u_id' => $usuario_id]);
    $vendedor = $stmtVendedor->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor) {
        die("Erro: Perfil de vendedor não encontrado para este usuário.");
    }

    $vendedor_id_real = $vendedor['id'];

    // 2. Busca o preço da Stripe para o plano
    $stmtPlano = $db->prepare("SELECT nome, stripe_price_id FROM planos WHERE id = :id");
    $stmtPlano->execute([':id' => $id_plano]);
    $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);

    if ($plano && !empty($plano['stripe_price_id'])) {
        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'], 
                'line_items' => [[
                    'price' => $plano['stripe_price_id'],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => 'http://localhost/EncontreOCampo/src/vendedor/redirects/sucesso.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'http://localhost/EncontreOCampo/src/vendedor/escolher_plano.php',
                'customer_email' => $_SESSION['usuario_email'] ?? null,
                'metadata' => [
                    'vendedor_id' => $vendedor_id_real, // Enviando o ID da tabela vendedores
                    'plano_id' => $id_plano
                ]
            ]);

            header("Location: " . $session->url);
            exit;
        } catch (Exception $e) {
            die("Erro Stripe: " . $e->getMessage());
        }
    } else {
        die("Erro: ID da Stripe não configurado para este plano.");
    }
} else {
    die("Erro: Sessão inválida. Por favor, faça login novamente.");
}