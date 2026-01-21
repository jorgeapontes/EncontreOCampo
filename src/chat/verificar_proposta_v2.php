<?php
// src/chat/verificar_proposta_v2.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
$ultima_data = isset($_GET['ultima_data']) ? $_GET['ultima_data'] : null;

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
        ORDER BY data_atualizacao DESC LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
$stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
$stmt->execute();

$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

$response = [
    'atualizacao' => false,
    'tipo_atualizacao' => null,
    'proposta' => null
];

if ($proposta) {
    $proposta_data_atualizacao = strtotime($proposta['data_atualizacao']);
    $ultima_data_timestamp = $ultima_data ? strtotime($ultima_data) : 0;
    
    // Se a data de atualização da proposta for mais recente que a última conhecida
    if ($proposta_data_atualizacao > $ultima_data_timestamp) {
        $response['atualizacao'] = true;
        $response['proposta'] = $proposta;
        
        // Determinar o tipo de atualização
        if ($ultima_data_timestamp == 0) {
            $response['tipo_atualizacao'] = 'primeira_carga';
        } else {
            // Verificar se é uma nova proposta ou apenas atualização de status
            $sql_anterior = "SELECT status FROM propostas WHERE produto_id = :produto_id 
                            AND (comprador_id = :usuario_id OR vendedor_id = :usuario_id)
                            AND data_atualizacao < :data_atualizacao
                            ORDER BY data_atualizacao DESC LIMIT 1";
            
            $stmt_ant = $conn->prepare($sql_anterior);
            $stmt_ant->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_ant->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
            $stmt_ant->bindParam(':data_atualizacao', $proposta['data_atualizacao']);
            $stmt_ant->execute();
            
            $proposta_anterior = $stmt_ant->fetch(PDO::FETCH_ASSOC);
            
            if ($proposta_anterior) {
                if ($proposta_anterior['status'] !== $proposta['status']) {
                    $response['tipo_atualizacao'] = 'status_atualizado';
                } else {
                    $response['tipo_atualizacao'] = 'dados_atualizados';
                }
            } else {
                $response['tipo_atualizacao'] = 'nova_proposta';
            }
        }
    }
}

echo json_encode($response);
?>