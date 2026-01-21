<?php
// src/chat/excluir_proposta.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit();
}

$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!isset($dados['proposta_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID da proposta não informado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$proposta_id = (int)$dados['proposta_id'];

$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar se o usuário é o comprador da proposta
    $sql_verificar = "SELECT ID FROM propostas 
                     WHERE ID = :proposta_id 
                     AND comprador_id = :usuario_id
                     AND status = 'negociacao'";
    
    $stmt = $conn->prepare($sql_verificar);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        throw new Exception('Proposta não encontrada ou você não tem permissão para excluí-la');
    }
    
    // Excluir a proposta
    $sql_excluir = "DELETE FROM propostas WHERE ID = :proposta_id";
    $stmt_excluir = $conn->prepare($sql_excluir);
    $stmt_excluir->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    
    if (!$stmt_excluir->execute()) {
        throw new Exception('Erro ao excluir proposta');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Proposta excluída com sucesso'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>