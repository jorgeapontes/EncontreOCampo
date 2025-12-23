// EncontreOCampo/src/vendedor/redirects/pendente.php
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Pendente - EncontreOCampo</title>
    <style>
        .pending-container {
            text-align: center;
            padding: 50px;
            max-width: 600px;
            margin: 0 auto;
        }
        .pending-icon {
            font-size: 80px;
            color: #ff9800;
            margin-bottom: 20px;
        }
        .btn-voltar {
            background-color: #009ee3;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="pending-container">
        <div class="pending-icon">⏳</div>
        <h1>Pagamento em Processamento</h1>
        <p>Seu pagamento está sendo processado pelo Mercado Pago.</p>
        <p>Este processo pode levar alguns minutos.</p>
        <p>Você receberá uma notificação por email quando o pagamento for confirmado.</p>
        
        <div style="margin-top: 30px;">
            <a href="../perfil.php" class="btn-voltar">Voltar para o Perfil</a>
        </div>
    </div>
</body>
</html>