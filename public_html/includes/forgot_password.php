<?php
require_once '../src/conexao.php'; // Arquivo de conexão com o banco

$database = new Database();
$conn = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <title>Recuperar Senha - Encontre o Campo</title>
    <link rel="stylesheet" href="css/forgot_password.css">
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Encontre o Campo</h1>
            <p>Recuperação de Senha</p>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo $_SESSION['message_type']; ?>">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="instructions">
            <p>Digite seu email cadastrado. Enviaremos um link para redefinir sua senha.</p>
        </div>
        
        <form action="send_reset_email" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="seu@email.com">
            </div>
            
            <button type="submit" class="btn">Enviar Link de Recuperação</button>
        </form>
        
        <div class="back-link">
            <a href="../src/login">← Voltar para o Login</a>
        </div>
    </div>
</body>
</html>