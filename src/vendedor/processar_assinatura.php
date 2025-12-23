<?php
require_once __DIR__ . '/../../config/StripeConfig.php';
require_once __DIR__ . '/../conexao.php';

use Config\StripeConfig;
StripeConfig::init();

$id_plano = $_GET['id'] ?? null;

if ($id_plano) {
    $database = new Database();
    $db = $database->getConnection();

    // Agora buscamos o stripe_price_id que você cadastrou
    $stmt = $db->prepare("SELECT nome, stripe_price_id FROM planos WHERE id = :id");
    $stmt->execute([':id' => $id_plano]);
    $plano = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($plano && !empty($plano['stripe_price_id'])) {
        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'], 
                'line_items' => [[
                    'price' => $plano['stripe_price_id'], // Usamos o ID fixo da Stripe aqui
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => 'http://localhost/EncontreOCampo/src/vendedor/sucesso.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'http://localhost/EncontreOCampo/src/vendedor/escolher_plano.php',
                'customer_email' => $_SESSION['usuario_email'],
                'metadata' => [
                    'vendedor_id' => $_SESSION['usuario_id'],
                    'plano_id' => $id_plano
                ]
            ]);

            header("Location: " . $session->url);
            exit;
        } catch (Exception $e) {
            die("Erro Stripe: " . $e->getMessage());
        }
    } else {
        die("Este plano ainda não foi configurado com um ID da Stripe.");
    }
}