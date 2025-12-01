<?php
// src/vendedor/anuncio_alterar_status.php
session_start();
require_once '../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

if (!isset($_POST['id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dados insuficientes']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Verificar se o anúncio pertence ao vendedor logado
$sql_verifica = "SELECT p.*, v.usuario_id 
                 FROM produtos p 
                 JOIN vendedores v ON p.vendedor_id = v.id 
                 WHERE p.id = :id AND v.usuario_id = :usuario_id";
$stmt_verifica = $conn->prepare($sql_verifica);
$stmt_verifica->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
$stmt_verifica->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
$stmt_verifica->execute();
$anuncio = $stmt_verifica->fetch(PDO::FETCH_ASSOC);

if (!$anuncio) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Anúncio não encontrado ou acesso negado']);
    exit();
}

// Atualizar status
$sql = "UPDATE produtos SET status = :status WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':status', $_POST['status'], PDO::PARAM_STR);
$stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status']);
}
?>