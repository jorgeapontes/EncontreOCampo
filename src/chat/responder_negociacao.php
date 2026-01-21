<?php
// src/chat/responder_proposta.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

// Ler dados JSON
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados || !isset($dados['proposta_id']) || !isset($dados['status'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();
    
    // Verificar se o usuário tem permissão para responder esta proposta
    $sql_verificar = "SELECT * FROM propostas 
                     WHERE ID = :proposta_id 
                     AND (comprador_id = :usuario_id OR vendedor_id = :usuario_id)";
    
    $stmt = $conn->prepare($sql_verificar);
    $stmt->bindParam(':proposta_id', $dados['proposta_id'], PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        throw new Exception('Proposta não encontrada ou sem permissão');
    }
    
    // Atualizar status da proposta
    $sql_atualizar = "UPDATE propostas SET 
                     status = :status,
                     data_atualizacao = NOW()
                     WHERE ID = :proposta_id";
    
    $stmt_atualizar = $conn->prepare($sql_atualizar);
    $stmt_atualizar->bindParam(':status', $dados['status']);
    $stmt_atualizar->bindParam(':proposta_id', $dados['proposta_id'], PDO::PARAM_INT);
    
    if (!$stmt_atualizar->execute()) {
        throw new Exception('Erro ao atualizar proposta');
    }
    
    // Enviar notificação para o outro usuário
    $outro_usuario_id = ($_SESSION['usuario_id'] == $proposta['comprador_id']) 
        ? $proposta['vendedor_id'] 
        : $proposta['comprador_id'];
    
    $status_texto = [
        'aceita' => 'aceita',
        'recusada' => 'recusada',
        'cancelada' => 'cancelada'
    ];
    
    $status_msg = isset($status_texto[$dados['status']]) ? $status_texto[$dados['status']] : 'atualizada';
    
    // Buscar nome do produto
    $sql_produto = "SELECT nome FROM produtos WHERE id = :produto_id";
    $stmt_prod = $conn->prepare($sql_produto);
    $stmt_prod->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
    $stmt_prod->execute();
    $produto_nome = $stmt_prod->fetchColumn() ?: 'Produto';
    
    $sql_notificacao = "INSERT INTO notificacoes 
        (usuario_id, mensagem, tipo, url) 
        VALUES (:usuario_id, :mensagem, 'info', :url)";
    
    $stmt_not = $conn->prepare($sql_notificacao);
    
    $mensagem = "Sua proposta para '{$produto_nome}' foi {$status_msg}";
    $url = isset($dados['conversa_id']) 
        ? "../../src/chat/chat.php?produto_id=" . $proposta['produto_id'] . "&conversa_id=" . $dados['conversa_id']
        : NULL;
    
    $stmt_not->bindParam(':usuario_id', $outro_usuario_id, PDO::PARAM_INT);
    $stmt_not->bindParam(':mensagem', $mensagem);
    $stmt_not->bindParam(':url', $url);
    $stmt_not->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Proposta ' . $status_msg . ' com sucesso'
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>