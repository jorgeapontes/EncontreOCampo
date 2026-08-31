// EncontreOCampo/src/vendedor/redirects/pendente.php
<?php
require_once '../../conexao.php'
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Pendente - EncontreOCampo</title>
    <link rel="stylesheet" href="css/pendente.css">
</head>
<body>
    <div class="pending-container">
        <div class="pending-icon">⏳</div>
        <h1>Pagamento em Processamento</h1>
        <p>Seu pagamento está sendo processado pelo Mercado Pago.</p>
        <p>Este processo pode levar alguns minutos.</p>
        <p>Você receberá uma notificação por email quando o pagamento for confirmado.</p>
        
        <div class="botoes-wrapper">
            <a href="../perfil" class="btn-voltar">Voltar para o Perfil</a>
        </div>
    </div>
</body>
</html>