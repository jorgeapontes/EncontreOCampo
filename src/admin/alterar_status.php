<?php
// src/admin/alterar_status.php
session_start();

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

require_once __DIR__ . '/../conexao.php';

$database = new Database();
$conn = $database->getConnection();

$usuario_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$novo_status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);

if ($usuario_id === false || $usuario_id === null || !in_array($novo_status, ['ativo', 'inativo'])) {
    header('Location: todos_usuarios.php?msg=' . urlencode('Erro: Parâmetros inválidos.'));
    exit;
}

try {
    $sql = "UPDATE usuarios SET status = :status WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $novo_status);
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();

    // Registrar ação do admin
    $admin_id = $_SESSION['usuario_id'] ?? 1;
    $acao_desc = "Alterou status do usuário (ID: $usuario_id) para $novo_status";
    $sql_acao = "INSERT INTO admin_acoes (admin_id, acao, tabela_afetada, registro_id) 
                 VALUES (:admin_id, :acao, 'usuarios', :registro_id)";
    $stmt_acao = $conn->prepare($sql_acao);
    $stmt_acao->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt_acao->bindParam(':acao', $acao_desc);
    $stmt_acao->bindParam(':registro_id', $usuario_id, PDO::PARAM_INT);
    $stmt_acao->execute();

    header('Location: todos_usuarios.php?msg=' . urlencode('Status alterado com sucesso.'));
} catch (Exception $e) {
    header('Location: todos_usuarios.php?msg=' . urlencode('Erro ao alterar status: ' . $e->getMessage()));
}