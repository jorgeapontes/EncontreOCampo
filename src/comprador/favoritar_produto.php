<?php
// src/comprador/favoritar_produto.php (CORRIGIDO)
session_start();
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$produto_id = $_POST['produto_id'] ?? null;
$acao = $_POST['acao'] ?? null;

if (!$produto_id || !$acao) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    if ($acao === 'adicionar') {
        $sql = "INSERT INTO favoritos (usuario_id, produto_id) VALUES (:usuario_id, :produto_id)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Produto adicionado aos favoritos']);
    } elseif ($acao === 'remover') {
        $sql = "DELETE FROM favoritos WHERE usuario_id = :usuario_id AND produto_id = :produto_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Produto removido dos favoritos']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao processar: ' . $e->getMessage()]);
}