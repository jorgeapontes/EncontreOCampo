<?php
// src/vendedor/stripe/sucesso.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclusão dos arquivos necessários
require_once __DIR__ . '/../../../vendor/autoload.php'; 
require_once __DIR__ . '/../../../config/StripeConfig.php';
require_once __DIR__ . '/../../conexao.php';

use Config\StripeConfig;
StripeConfig::init();

$session_id = $_GET['session_id'] ?? null;
$sucesso = false;
$mensagem_status = "";
$nome_plano = "Assinado";

if ($session_id) {
    try {
        // Recupera a sessão do Stripe
        $session = \Stripe\Checkout\Session::retrieve($session_id);

        // Dentro do sucesso.php, onde ocorre o pagamento confirmado
        // Verifica se o pagamento foi bem-sucedido (pode ser 'paid' ou 'unpaid' dependendo do tipo de pagamento)
        if ($session->payment_status === 'paid' || $session->status === 'complete') {
            $vendedor_id = $session->metadata->vendedor_id;
            $plano_id = $session->metadata->plano_id;
            
            // CAPTURANDO OS DOIS IDS DO STRIPE
            $stripe_customer_id = $session->customer; 
            $stripe_subscription_id = $session->subscription; // <-- ESTA LINHA É ESSENCIAL

            $database = new Database();
            $conn = $database->getConnection();

            $agora = date('Y-m-d H:i:s');
            $data_vencimento = date('Y-m-d H:i:s', strtotime('+30 days'));

            // ATUALIZAÇÃO COMPLETA DO BANCO
            // Se o webhook já processou, isso vai atualizar com os mesmos dados (safe)
            // Se o webhook ainda não processou, isso garante que os dados são salvos
            $sql = "UPDATE vendedores SET 
                    plano_id = ?, 
                    status_assinatura = 'ativo', 
                    Data_inicio_assinatura = ?, 
                    data_vencimento_assinatura = ?,
                    stripe_customer_id = ?,
                    stripe_subscription_id = ? 
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $resultado = $stmt->execute([
                $plano_id, 
                $agora, 
                $data_vencimento, 
                $stripe_customer_id, 
                $stripe_subscription_id, // Gravando o ID da assinatura aqui
                $vendedor_id
            ]);

            // Verificar se a atualização foi bem-sucedida
            if ($resultado && $stmt->rowCount() > 0) {
                $sucesso = true;
            } else {
                $mensagem_status = "Pagamento confirmado, mas houve um erro ao ativar o plano. Por favor, contate o suporte.";
            }
        } else if ($session->status === 'complete' && $session->payment_status === 'unpaid') {
            // Pagamento pode estar pendente (débito em conta, etc)
            $mensagem_status = "Pagamento em processamento. Você será notificado em breve.";
        } else {
            $mensagem_status = "O pagamento ainda não foi confirmado.";
        }
    } catch (Exception $e) {
        $mensagem_status = "Erro ao processar ativação: " . $e->getMessage();
    }
} else {
    $mensagem_status = "Sessão de pagamento inválida.";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Concluído | Encontre o Campo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/sucesso.css">
</head>
<body>

    <div class="success-card">
        <?php if ($sucesso): ?>
            <div class="icon-container icon-success">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h1>Assinatura Ativada!</h1>
            <p>Parabéns! Você já pode desfrutar do seu plano.</p>
            
            <div class="info-box">
                <div class="info-item">
                    <span class="label">Plano:</span>
                    <span class="value"><?php echo htmlspecialchars($nome_plano); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Próximo Vencimento:</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime('+30 days')); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Status:</span>
                    <span class="value value-ativo">Ativo</span>
                </div>
            </div>

            <a href="../perfil" class="btn-green">Ir para o meu Painel</a>

        <?php else: ?>
            <div class="icon-container icon-error">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <h1>Ops! Algo deu errado</h1>
            <p>Não conseguimos confirmar a ativação do seu plano automaticamente.</p>
            
            <div class="info-box info-box-erro">
                <p class="mensagem-erro-stripe">
                    <?php echo $mensagem_status ?: "Houve um problema na comunicação com o Stripe. Se o valor foi cobrado, entre em contato com o suporte."; ?>
                </p>
            </div>

            <a href="../escolher_plano" class="btn-green btn-green-cinza">Tentar Novamente</a>
        <?php endif; ?>
    </div>

</body>
</html>