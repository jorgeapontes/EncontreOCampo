<?php
require_once '../src/conexao.php'; // Arquivo de conexão com o banco

$database = new Database();
$conn = $database->getConnection();

$token = $_GET['token'] ?? '';
$erro = '';
$valido = false;
$email = '';

if ($token) {
    $sql = "SELECT id, email, reset_token_expira FROM usuarios WHERE reset_token = ? AND reset_token_expira > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$token]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) === 1) {
        $usuario = $result[0];
        $valido = true;
        $email = $usuario['email'];
    } else {
        $erro = "Link inválido ou expirado!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Senha - Encontre o Campo</title>
    <link rel="stylesheet" href="css/reset_password.css">
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
                <p class="solicitar-novo-link"><a href="forgot_password">Solicitar novo link</a></p>
            </div>
        <?php elseif (!$valido): ?>
            <div class="message error">
                Link inválido!
            </div>
        <?php else: ?>
        
        <div class="instructions">
            <p>Digite sua nova senha para a conta: <strong><?php echo htmlspecialchars($email); ?></strong></p>
        </div>
        
        <form id="resetForm" action="process_reset_password" method="POST">
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
                <div id="matchMessage" class="match-message"></div>
            </div>
            
            <button type="submit" class="btn" id="submitBtn" disabled>Redefinir Senha</button>
        </form>
        
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login">← Voltar para o Login</a>
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