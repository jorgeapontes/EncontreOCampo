<?php
// src/verificar_notificacoes.php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['total_nao_lidas' => 0]);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

$sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
$stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
$stmt_nao_lidas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_nao_lidas->execute();
$total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];

echo json_encode(['total_nao_lidas' => $total_nao_lidas]);
?>