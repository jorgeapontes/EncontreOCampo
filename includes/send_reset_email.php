<?php
session_start();
require_once '../src/conexao.php'; // Arquivo de conexão com o banco

$database = new Database();
$conn = $database->getConnection();

function gerarToken($tamanho = 32) {
    return bin2hex(random_bytes($tamanho));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Email inválido!";
        $_SESSION['message_type'] = 'error';
        header('Location: forgot_password.php');
        exit;
    }
    
    // Verificar se email existe
    $sql = "SELECT id, nome FROM usuarios WHERE email = ? AND status = 'ativo'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$email]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) === 1) {
        $usuario = $result[0];
        $token = gerarToken();
        $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token válido por 1 hora
        
        // Salvar token no banco
        $sql_update = "UPDATE usuarios SET reset_token = ?, reset_token_expira = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([$token, $expiracao, $usuario['id']]);
        
        if ($stmt_update->execute()) {
            // Configurar email (vamos criar este arquivo depois)
            require_once 'email_config.php';
            
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/EncontreOCampo/includes/reset_password.php?token=$token";
            
            // Enviar email
            if (enviarEmailRecuperacao($email, $usuario['nome'], $reset_link)) {
                $_SESSION['message'] = "Email de recuperação enviado! Verifique sua caixa de entrada.";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Erro ao enviar email. Tente novamente.";
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = "Erro ao gerar token. Tente novamente.";
            $_SESSION['message_type'] = 'error';
        }
    } else {
        // Por segurança, não informar se o email existe ou não
        $_SESSION['message'] = "Se o email estiver cadastrado, você receberá um link de recuperação.";
        $_SESSION['message_type'] = 'success';
    }
    $conn = null;
    header('Location: forgot_password.php');
    exit;
}
?>