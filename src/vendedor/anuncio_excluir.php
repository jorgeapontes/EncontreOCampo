<?php
// src/vendedor/anuncio_excluir.php
session_start();
require_once '../conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

if (!isset($_POST['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
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

// Versão simplificada - não verifica propostas
// Excluir anúncio
$sql = "DELETE FROM produtos WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);

if ($stmt->execute()) {
    // Excluir imagens relacionadas se necessário
    // Aqui você pode adicionar lógica para excluir arquivos físicos
    echo json_encode(['success' => true, 'message' => 'Anúncio excluído com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir anúncio']);
}
?>