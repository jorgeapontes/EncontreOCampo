<?php
session_start();
require_once '..src/conexao.php'; // Arquivo de conexão com o banco

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    
    // Validar token
    $sql = "SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expira > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        $_SESSION['message'] = "Token inválido ou expirado!";
        $_SESSION['message_type'] = 'error';
        header('Location: forgot_password.php');
        exit;
    }
    
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    // Validar força da senha (opcional, mas recomendado)
    if (strlen($nova_senha) < 8) {
        $_SESSION['message'] = "A senha deve ter pelo menos 8 caracteres!";
        $_SESSION['message_type'] = 'error';
        header("Location: reset_password.php?token=$token");
        exit;
    }
    
    // Hash da nova senha
    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
    
    // Atualizar senha e limpar token
    $sql_update = "UPDATE usuarios SET senha = ?, reset_token = NULL, reset_token_expira = NULL WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("si", $senha_hash, $usuario['id']);
    
    if ($stmt_update->execute()) {
        $_SESSION['message'] = "Senha alterada com sucesso! Você já pode fazer login.";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Erro ao alterar senha. Tente novamente.";
        $_SESSION['message_type'] = 'error';
    }
    
    $stmt_update->close();
    $conn->close();
    
    header('Location: login.php'); // Sua página de login
    exit;
}
?>