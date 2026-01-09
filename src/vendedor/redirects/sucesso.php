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

        if ($session->payment_status === 'paid') {
            $vendedor_id = $session->metadata->vendedor_id;
            $plano_id = $session->metadata->plano_id;
            
            // --- NOVA LÓGICA: Captura o ID do Cliente Stripe ---
            $stripe_customer_id = $session->customer; 

            $database = new Database();
            $conn = $database->getConnection();

            $agora = date('Y-m-d H:i:s');
            $data_vencimento = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Buscar nome do plano para exibição
            $stmtPlano = $conn->prepare("SELECT nome FROM planos WHERE id = ?");
            $stmtPlano->execute([$plano_id]);
            $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);
            if ($plano) {
                $nome_plano = $plano['nome'];
            }

            // --- ATUALIZAÇÃO DO BANCO: Incluindo o stripe_customer_id ---
            $sql = "UPDATE vendedores SET 
                    plano_id = ?, 
                    status_assinatura = 'ativo', 
                    Data_inicio_assinatura = ?, 
                    data_vencimento_assinatura = ?,
                    stripe_customer_id = ? 
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$plano_id, $agora, $data_vencimento, $stripe_customer_id, $vendedor_id]);

            $sucesso = true;
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
    <style>
        :root {
            --primary-green: #28a745;
            --dark-green: #1e7e34;
            --bg-light: #f8faf9;
            --text-main: #2d3436;
            --white: #ffffff;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .success-card {
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 450px;
            width: 90%;
        }
        .icon-container {
            font-size: 60px;
            margin-bottom: 20px;
        }
        .icon-success { color: var(--primary-green); }
        .icon-error { color: #d63031; }
        
        h1 { font-size: 24px; color: var(--text-main); margin-bottom: 10px; }
        p { color: #636e72; line-height: 1.6; margin-bottom: 25px; }

        .info-box {
            background: #f1f3f5;
            padding: 20px;
            border-radius: 12px;
            text-align: left;
            margin-bottom: 30px;
        }
        .info-item { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .info-item:last-child { margin-bottom: 0; }
        .label { font-weight: 600; color: #636e72; }
        .value { color: var(--text-main); font-weight: 700; }

        .btn-green {
            display: block;
            background: var(--primary-green);
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            transition: 0.3s;
        }
        .btn-green:hover { background: var(--dark-green); transform: translateY(-2px); }
    </style>
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
                    <span class="value" style="color: var(--primary-green);">Ativo</span>
                </div>
            </div>

            <a href="../perfil.php" class="btn-green">Ir para o meu Painel</a>

        <?php else: ?>
            <div class="icon-container icon-error">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <h1>Ops! Algo deu errado</h1>
            <p>Não conseguimos confirmar a ativação do seu plano automaticamente.</p>
            
            <div class="info-box" style="background: #fff5f5; border: 1px solid #fed7d7;">
                <p style="font-size: 13px; color: #c53030; margin: 0;">
                    <?php echo $mensagem_status ?: "Houve um problema na comunicação com o Stripe. Se o valor foi cobrado, entre em contato com o suporte."; ?>
                </p>
            </div>

            <a href="../escolher_plano.php" class="btn-green" style="background: #636e72;">Tentar Novamente</a>
        <?php endif; ?>
    </div>

</body>
</html>