// EncontreOCampo/src/vendedor/redirects/falha.php
<?php
require_once __DIR__ . '/../../config/Database.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Recusado - EncontreOCampo</title>
    <link rel="stylesheet" href="css/falha.css">
</head>
<body>
    <div class="error-container">
        <div class="error-icon">✗</div>
        <h1>Pagamento Não Aprovado</h1>
        <p>Houve um problema com o processamento do seu pagamento.</p>
        <p>Possíveis causas:</p>
        <ul class="lista-causas">
            <li>Saldo insuficiente no cartão</li>
            <li>Cartão expirado ou bloqueado</li>
            <li>Dados do cartão incorretos</li>
            <li>Problemas temporários na operadora</li>
        </ul>
        
        <div class="botoes-wrapper">
            <a href="../escolher_plano" class="btn-tentar">Tentar Novamente</a>
            <a href="../perfil" class="btn-voltar">Voltar para o Perfil</a>
        </div>
    </div>
</body>
</html>