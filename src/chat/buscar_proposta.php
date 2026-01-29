<?php
// buscar_proposta.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

$proposta_id = $_GET['id'] ?? 0;
if ($proposta_id <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

$sql = "SELECT * FROM propostas WHERE ID = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
$stmt->execute();
$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposta) {
    echo json_encode(['error' => 'Proposta não encontrada']);
    exit();
}

// Formatar valores para exibição
$proposta['preco_proposto'] = number_format($proposta['preco_proposto'], 2, '.', '');
$proposta['valor_frete'] = number_format($proposta['valor_frete'], 2, '.', '');
$proposta['valor_total'] = number_format($proposta['valor_total'], 2, '.', '');

echo json_encode($proposta);
?>