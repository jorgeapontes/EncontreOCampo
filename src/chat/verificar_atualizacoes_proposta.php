<?php
// src/chat/verificar_atualizacoes_proposta.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || !isset($_GET['produto_id'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$produto_id = (int)$_GET['produto_id'];
$ultima_data = isset($_GET['ultima_data']) ? $_GET['ultima_data'] : null;

$database = new Database();
$conn = $database->getConnection();

// Buscar a data da última atualização para este produto e usuário
$sql = "SELECT MAX(data_atualizacao) as ultima_atualizacao 
        FROM propostas 
        WHERE produto_id = :produto_id 
        AND (comprador_id = :usuario_id OR vendedor_id = :usuario_id)";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();

$resultado = $stmt->fetch(PDO::FETCH_ASSOC);
$ultima_atualizacao_servidor = $resultado['ultima_atualizacao'];

$response = [
    'atualizacao' => false,
    'nova_data_atualizacao' => $ultima_atualizacao_servidor
];

// Se não temos data local OU se a data do servidor é mais recente
if (!$ultima_data || 
    ($ultima_atualizacao_servidor && 
     strtotime($ultima_atualizacao_servidor) > strtotime($ultima_data))) {
    $response['atualizacao'] = true;
}

echo json_encode($response);
?>