<?php
// src/chat/buscar_dados_proposta.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit();
}

$proposta_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

// Buscar dados da proposta
$sql = "SELECT * FROM propostas 
        WHERE ID = :id 
        AND (comprador_id = :usuario_id OR vendedor_id = :usuario_id)";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();

$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposta) {
    echo json_encode(['success' => false, 'error' => 'Proposta não encontrada']);
    exit();
}

echo json_encode([
    'success' => true,
    'dados' => $proposta
]);
?>