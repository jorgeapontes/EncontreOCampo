<?php
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
        $session = \Stripe\Checkout\Session::retrieve($session_id);

        if ($session->payment_status === 'paid') {
            $vendedor_id = $session->metadata->vendedor_id;
            $plano_id = $session->metadata->plano_id;

            $database = new Database();
            $conn = $database->getConnection();

            $agora = date('Y-m-d H:i:s');
            $data_vencimento = date('Y-m-d H:i:s', strtotime('+30 days'));

            $stmtPlano = $conn->prepare("SELECT nome FROM planos WHERE id = ?");
            $stmtPlano->execute([$plano_id]);
            $plano = $stmtPlano->fetch(PDO::FETCH_ASSOC);
            if ($plano) { $nome_plano = $plano['nome']; }

            $sql = "UPDATE vendedores SET 
                    plano_id = :p_id, 
                    status_assinatura = :status,
                    data_vencimento_assinatura = :d_venc,
                    Data_assinatura = :d_assin,
                    Data_inicio_assinatura = :d_inicio
                    WHERE id = :v_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':p_id'     => $plano_id,
                ':status'   => 'ativo',
                ':d_venc'   => $data_vencimento,
                ':d_assin'  => $agora,
                ':d_inicio' => $agora,
                ':v_id'     => $vendedor_id
            ]);

            $sucesso = true;
        }
    } catch (Exception $e) {
        $mensagem_status = "Erro: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Confirmado | Encontre o Campo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-green: #28a745; /* Verde Sucesso */
            --dark-green: #1e7e34;
            --soft-green: #eafaf1;
            --text-main: #2d3436;
            --text-light: #636e72;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f8faf9; /* Fundo quase branco com toque verde */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Card Principal */
        .card {
            background: var(--white);
            max-width: 480px;
            width: 100%;
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        /* Ícone de Sucesso Animado */
        .icon-container {
            width: 80px;
            height: 80px;
            background-color: var(--soft-green);
            color: var(--primary-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 35px;
        }

        h1 {
            color: var(--text-main);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        p {
            color: var(--text-light);
            font-size: 15px;
            line-height: 1.6;
        }

        /* Caixa de Informações */
        .info-box {
            background-color: #fcfdfc;
            border: 1px dashed #cbd5e0;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .info-item:last-child { margin-bottom: 0; }

        .label { color: var(--text-light); font-size: 14px; }
        .value { color: var(--text-main); font-weight: 600; font-size: 14px; }

        /* Botão Verde */
        .btn-green {
            display: block;
            width: 100%;
            padding: 15px;
            background-color: var(--primary-green);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-green:hover {
            background-color: var(--dark-green);
            transform: translateY(-1px);
        }

        .btn-text {
            display: inline-block;
            margin-top: 15px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-text:hover { color: var(--text-main); }

        /* Estilo para Erro */
        .icon-error { color: #d63031; background-color: #fff5f5; }
        .btn-error { background-color: #636e72; }
    </style>
</head>
<body>

    <div class="card">
        <?php if ($sucesso): ?>
            <div class="icon-container">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h1>Pagamento Confirmado</h1>
            <p>Sua assinatura foi processada com sucesso. Agora seu perfil está com todos os recursos liberados.</p>

            <div class="info-box">
                <div class="info-item">
                    <span class="label">Plano:</span>
                    <span class="value"><?php echo htmlspecialchars($nome_plano); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Vencimento:</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime('+30 days')); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Status:</span>
                    <span class="value" style="color: var(--primary-green);">Ativo</span>
                </div>
            </div>

            <a href="../perfil.php" class="btn-green">Acessar meu Painel</a>

        <?php else: ?>
            <div class="icon-container icon-error">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <h1>Não foi possível ativar</h1>
            <p>Houve um problema ao processar seu pagamento junto ao banco.</p>
            
            <div class="info-box" style="border-style: solid; border-color: #fed7d7; background: #fff5f5;">
                <p style="font-size: 13px; color: #c53030;">
                    <?php echo $mensagem_status ?: "Ocorreu um erro inesperado. Verifique os dados do cartão."; ?>
                </p>
            </div>

            <a href="../escolher_plano.php" class="btn-green btn-error">Tentar outro cartão</a>
        <?php endif; ?>
    </div>

</body>
</html>