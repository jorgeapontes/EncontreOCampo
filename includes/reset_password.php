<?php
session_start();
require_once '..src/conexao.php'; // Arquivo de conexão com o banco

$token = $_GET['token'] ?? '';
$erro = '';
$valido = false;
$email = '';

if ($token) {
    $sql = "SELECT id, email, reset_token_expira FROM usuarios WHERE reset_token = ? AND reset_token_expira > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $usuario = $result->fetch_assoc();
        $valido = true;
        $email = $usuario['email'];
    } else {
        $erro = "Link inválido ou expirado!";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha - Encontre o Campo</title>
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .password-strength {
            height: 5px;
            background-color: #eee;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
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
        
        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
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
        
        .error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .success {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            border: 1px solid var(--primary-color);
        }
        
        .requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .requirements ul {
            padding-left: 20px;
            margin-top: 5px;
        }
        
        .valid {
            color: var(--primary-dark);
        }
        
        .invalid {
            color: #c62828;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Encontre o Campo</h1>
            <p>Nova Senha</p>
        </div>
        
        <?php if ($erro): ?>
            <div class="message error">
                <?php echo $erro; ?>
                <p style="margin-top: 10px;"><a href="forgot_password.php">Solicitar novo link</a></p>
            </div>
        <?php elseif (!$valido): ?>
            <div class="message error">
                Link inválido!
            </div>
        <?php else: ?>
        
        <div class="instructions">
            <p>Digite sua nova senha para a conta: <strong><?php echo htmlspecialchars($email); ?></strong></p>
        </div>
        
        <form id="resetForm" action="process_reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="nova_senha">Nova Senha</label>
                <input type="password" id="nova_senha" name="nova_senha" required 
                       oninput="checkPasswordStrength()">
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="requirements">
                    <p>A senha deve conter:</p>
                    <ul>
                        <li id="length" class="invalid">Pelo menos 8 caracteres</li>
                        <li id="uppercase" class="invalid">Uma letra maiúscula</li>
                        <li id="lowercase" class="invalid">Uma letra minúscula</li>
                        <li id="number" class="invalid">Um número</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirmar_senha">Confirmar Senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required 
                       oninput="checkPasswordMatch()">
                <div id="matchMessage" style="font-size: 12px; margin-top: 5px;"></div>
            </div>
            
            <button type="submit" class="btn" id="submitBtn" disabled>Redefinir Senha</button>
        </form>
        
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">← Voltar para o Login</a>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('nova_senha').value;
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            
            // Verificar requisitos
            if (password.length >= 8) {
                document.getElementById('length').className = 'valid';
                strength += 25;
            } else {
                document.getElementById('length').className = 'invalid';
            }
            
            if (/[A-Z]/.test(password)) {
                document.getElementById('uppercase').className = 'valid';
                strength += 25;
            } else {
                document.getElementById('uppercase').className = 'invalid';
            }
            
            if (/[a-z]/.test(password)) {
                document.getElementById('lowercase').className = 'valid';
                strength += 25;
            } else {
                document.getElementById('lowercase').className = 'invalid';
            }
            
            if (/[0-9]/.test(password)) {
                document.getElementById('number').className = 'valid';
                strength += 25;
            } else {
                document.getElementById('number').className = 'invalid';
            }
            
            // Atualizar barra de força
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#f44336';
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#ff9800';
            } else {
                strengthBar.style.backgroundColor = '#4CAF50';
            }
            
            checkFormValidity();
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('nova_senha').value;
            const confirm = document.getElementById('confirmar_senha').value;
            const message = document.getElementById('matchMessage');
            
            if (password === confirm) {
                message.textContent = "Senhas coincidem!";
                message.style.color = "#4CAF50";
            } else {
                message.textContent = "As senhas não coincidem!";
                message.style.color = "#f44336";
            }
            
            checkFormValidity();
        }
        
        function checkFormValidity() {
            const password = document.getElementById('nova_senha').value;
            const confirm = document.getElementById('confirmar_senha').value;
            const submitBtn = document.getElementById('submitBtn');
            
            // Verificar força mínima (pelo menos 3 requisitos atendidos)
            const requirements = document.querySelectorAll('.valid');
            
            if (password === confirm && requirements.length >= 3 && password.length >= 8) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }
    </script>
</body>
</html>