<?php
// src/chat/responder_proposta.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

// Ler dados JSON
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados || !isset($dados['acao']) || !isset($dados['proposta_id'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];
$acao = $dados['acao'];
$proposta_id = (int)$dados['proposta_id'];

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();
    
    // Buscar informações da proposta
    $sql_proposta = "SELECT * FROM propostas WHERE ID = :proposta_id";
    $stmt = $conn->prepare($sql_proposta);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        throw new Exception('Proposta não encontrada');
    }
    
    // Verificar se a proposta já foi finalizada (exceto se for cancelamento pelo comprador)
    if ($proposta['status'] !== 'negociacao') {
        // Permitir cancelamento apenas se a proposta já estiver cancelada e o comprador quiser cancelar novamente?
        // Ou não permitir alterações em propostas já finalizadas
        if (!($acao === 'cancelar' && $proposta['status'] === 'cancelada' && $usuario_tipo === 'comprador')) {
            throw new Exception('Esta proposta já foi finalizada');
        }
    }
    
    // Validar permissões
    $nova_status = '';
    $mensagem_acao = '';
    
    if ($acao === 'cancelar' && $usuario_tipo === 'comprador') {
        if ($proposta['comprador_id'] != $usuario_id) {
            throw new Exception('Apenas o comprador desta proposta pode cancelá-la');
        }
        $nova_status = 'cancelada';
        $mensagem_acao = 'cancelada';
        
    } elseif (($acao === 'aceitar' || $acao === 'recusar') && $usuario_tipo === 'vendedor') {
        if ($proposta['vendedor_id'] != $usuario_id) {
            throw new Exception('Apenas o vendedor desta proposta pode aceitar/recusar');
        }
        $nova_status = ($acao === 'aceitar') ? 'aceita' : 'recusada';
        $mensagem_acao = ($acao === 'aceitar') ? 'aceita' : 'recusada';
        
    } else {
        throw new Exception('Ação não permitida para seu tipo de usuário');
    }
    
    // Atualizar status da proposta
    $sql_atualizar = "UPDATE propostas SET 
                     status = :status,
                     data_atualizacao = NOW()
                     WHERE ID = :proposta_id";
    
    $stmt_atualizar = $conn->prepare($sql_atualizar);
    $stmt_atualizar->bindParam(':status', $nova_status);
    $stmt_atualizar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    
    if (!$stmt_atualizar->execute()) {
        throw new Exception('Erro ao atualizar proposta');
    }
    
    // Se foi aceita, verificar estoque
    if ($nova_status === 'aceita') {
        $sql_estoque = "SELECT estoque FROM produtos WHERE id = :produto_id";
        $stmt_estoque = $conn->prepare($sql_estoque);
        $stmt_estoque->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
        $stmt_estoque->execute();
        $produto = $stmt_estoque->fetch(PDO::FETCH_ASSOC);
        
        if ($produto['estoque'] < $proposta['quantidade_proposta']) {
            throw new Exception('Estoque insuficiente');
        }
        
        // Atualizar estoque
        $sql_update_estoque = "UPDATE produtos SET 
                              estoque = estoque - :quantidade
                              WHERE id = :produto_id";
        
        $stmt_update = $conn->prepare($sql_update_estoque);
        $stmt_update->bindParam(':quantidade', $proposta['quantidade_proposta'], PDO::PARAM_INT);
        $stmt_update->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
        $stmt_update->execute();
    }
    
    // Se foi cancelada, restaurar o estoque (se estava aceita anteriormente)
    if ($nova_status === 'cancelada' && $proposta['status'] === 'aceita') {
        $sql_restaurar_estoque = "UPDATE produtos SET 
                                 estoque = estoque + :quantidade
                                 WHERE id = :produto_id";
        
        $stmt_restaurar = $conn->prepare($sql_restaurar_estoque);
        $stmt_restaurar->bindParam(':quantidade', $proposta['quantidade_proposta'], PDO::PARAM_INT);
        $stmt_restaurar->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
        $stmt_restaurar->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Proposta {$mensagem_acao} com sucesso",
        'novo_status' => $nova_status
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>