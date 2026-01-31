<?php
// src/chat_transportador/get_proposta_status.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'pendente']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'pendente']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    $sql = "SELECT status FROM propostas_transportadores WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode(['status' => $result['status']]);
    } else {
        echo json_encode(['status' => 'pendente']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'pendente']);
}