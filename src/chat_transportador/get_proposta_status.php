<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID não fornecido']);
    exit();
}

$proposta_id = (int)$_GET['id'];

$database = new Database();
$conn = $database->getConnection();

try {
    // Buscar status da proposta
    $sql = "SELECT status FROM propostas_transportadores WHERE id = :id LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo json_encode(['status' => $row['status']]);
    } else {
        echo json_encode(['status' => 'nao_encontrada']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>