<?php
// src/chat/buscar_assinaturas.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

$proposta_id = isset($_GET['proposta_id']) ? (int)$_GET['proposta_id'] : 0;

if ($proposta_id <= 0) {
    echo json_encode(['error' => 'ID da proposta inválido']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

try {
    // Buscar informações da proposta
    $sql_proposta = "SELECT p.*, 
                    u_comp.nome as comprador_nome,
                    u_vend.nome as vendedor_nome,
                    u_comp.id as comprador_id,
                    u_vend.id as vendedor_id
                    FROM propostas p
                    JOIN usuarios u_comp ON p.comprador_id = u_comp.id
                    JOIN usuarios u_vend ON p.vendedor_id = u_vend.id
                    WHERE p.ID = :proposta_id 
                    AND (p.comprador_id = :usuario_id OR p.vendedor_id = :usuario_id)";
    
    $stmt_proposta = $conn->prepare($sql_proposta);
    $stmt_proposta->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_proposta->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_proposta->execute();
    $proposta = $stmt_proposta->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        throw new Exception('Proposta não encontrada');
    }
    
    // Determinar quem é o outro usuário
    $outro_usuario_id = ($usuario_id == $proposta['comprador_id']) ? 
                       $proposta['vendedor_id'] : $proposta['comprador_id'];
    $outro_usuario_nome = ($usuario_id == $proposta['comprador_id']) ? 
                         $proposta['vendedor_nome'] : $proposta['comprador_nome'];
    
    // Buscar assinaturas da proposta
    $sql_assinaturas = "SELECT pa.*, u.nome, u.tipo 
                       FROM propostas_assinaturas pa
                       JOIN usuarios u ON pa.usuario_id = u.id
                       WHERE pa.proposta_id = :proposta_id
                       ORDER BY pa.data_assinatura ASC";
    
    $stmt_assinaturas = $conn->prepare($sql_assinaturas);
    $stmt_assinaturas->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_assinaturas->execute();
    $assinaturas = $stmt_assinaturas->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'assinaturas' => $assinaturas,
        'outro_usuario_nome' => $outro_usuario_nome,
        'outro_usuario_id' => $outro_usuario_id,
        'proposta_status' => $proposta['status']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>