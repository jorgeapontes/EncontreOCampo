<?php
session_start();
require_once '../src/conexao.php'; // Arquivo de conexão com o banco

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    
    // Validar token
    $sql = "SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expira > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$token]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) !== 1) {
        $_SESSION['message'] = "Token inválido ou expirado!";
        $_SESSION['message_type'] = 'error';
        header('Location: forgot_password.php');
        exit;
    }
    
    $usuario = $result[0];
    
    // Validar força da senha (opcional, mas recomendado)
    if (strlen($nova_senha) < 8) {
        $_SESSION['message'] = "A senha deve ter pelo menos 8 caracteres!";
        $_SESSION['message_type'] = 'error';
        header("Location: ../src/reset_senha.php?token=$token");
        exit;
    }
    
    // Hash da nova senha
    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
    
    // Atualizar senha e limpar token
    $sql_update = "UPDATE usuarios SET senha = ?, reset_token = NULL, reset_token_expira = NULL WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->execute([$senha_hash, $usuario['id']]);
    
    if ($stmt_update->execute()) {
        $_SESSION['message'] = "Senha alterada com sucesso! Você já pode fazer login.";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Erro ao alterar senha. Tente novamente.";
        $_SESSION['message_type'] = 'error';
    }
    $conn = null;
    
    header('Location: ../src/login.php'); // Sua página de login
    exit;
}
?>