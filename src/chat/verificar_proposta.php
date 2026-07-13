<?php
// src/chat/verificar_proposta.php
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
$proposta_atual_id = isset($_GET['proposta_atual_id']) ? (int)$_GET['proposta_atual_id'] : 0;

if ($conversa_id <= 0 || $produto_id <= 0) {
    echo json_encode(['error' => 'Parâmetros inválidos']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Buscar a última proposta desta conversa
$sql = "SELECT * FROM propostas 
        WHERE produto_id = :produto_id 
        AND (comprador_id = :usuario_id OR vendedor_id = :usuario_id)
        ORDER BY data_inicio DESC LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
$stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
$stmt->execute();

$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

$response = [
    'nova_proposta' => false,
    'atualizacao_status' => false,
    'proposta' => null
];

if ($proposta) {
    // Verificar se é uma nova proposta ou se o status mudou
    if ($proposta['ID'] > $proposta_atual_id) {
        $response['nova_proposta'] = true;
        $response['proposta'] = $proposta;
    } else if ($proposta_atual_id > 0) {
        // Verificar se o status mudou
        $sql_status = "SELECT status FROM propostas WHERE ID = :proposta_id";
        $stmt_status = $conn->prepare($sql_status);
        $stmt_status->bindParam(':proposta_id', $proposta_atual_id, PDO::PARAM_INT);
        $stmt_status->execute();
        $status_atual = $stmt_status->fetchColumn();
        
        if ($status_atual !== $proposta['status']) {
            $response['atualizacao_status'] = true;
            $response['novo_status'] = formatarStatus($proposta['status']);
            $response['novo_status_class'] = $proposta['status'];
        }
    }
    
    // Incluir sempre a proposta atual para atualização
    if (!$response['nova_proposta']) {
        $response['proposta'] = $proposta;
    }
}

echo json_encode($response);

function formatarStatus($status) {
    $status_texto = [
        'aceita' => '✅ Aceita',
        'negociacao' => '🔄 Em Negociação',
        'recusada' => '❌ Recusada'
    ];
    
    return $status_texto[$status] ?? $status;
}