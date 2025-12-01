<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Encontre o Campo</title>
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --dark-color: #2E7D32;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: var(--dark-color);
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            border: 1px solid var(--primary-color);
        }
        
        .error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .instructions {
            background-color: #f0f8ff;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #555;
        }
    </style>
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
        
        <form action="send_reset_email.php" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="seu@email.com">
            </div>
            
            <button type="submit" class="btn">Enviar Link de Recuperação</button>
        </form>
        
        <div class="back-link">
            <a href="login.php">← Voltar para o Login</a>
        </div>
    </div>
</body>
</html>