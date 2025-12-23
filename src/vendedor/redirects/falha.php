// EncontreOCampo/src/vendedor/redirects/falha.php
<?php
session_start();
require_once __DIR__ . '/../../config/Database.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Recusado - EncontreOCampo</title>
    <style>
        .error-container {
            text-align: center;
            padding: 50px;
            max-width: 600px;
            margin: 0 auto;
        }
        .error-icon {
            font-size: 80px;
            color: #f44336;
            margin-bottom: 20px;
        }
        .btn-tentar {
            background-color: #009ee3;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 30px;
            margin-right: 10px;
        }
        .btn-voltar {
            background-color: #666;
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
    <div class="error-container">
        <div class="error-icon">✗</div>
        <h1>Pagamento Não Aprovado</h1>
        <p>Houve um problema com o processamento do seu pagamento.</p>
        <p>Possíveis causas:</p>
        <ul style="text-align: left; display: inline-block;">
            <li>Saldo insuficiente no cartão</li>
            <li>Cartão expirado ou bloqueado</li>
            <li>Dados do cartão incorretos</li>
            <li>Problemas temporários na operadora</li>
        </ul>
        
        <div style="margin-top: 30px;">
            <a href="../escolher_plano.php" class="btn-tentar">Tentar Novamente</a>
            <a href="../perfil.php" class="btn-voltar">Voltar para o Perfil</a>
        </div>
    </div>
</body>
</html>