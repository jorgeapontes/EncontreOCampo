<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

// Verificar se é requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit();
}

// Ler dados JSON
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados || !isset($dados['negociacao_id']) || !isset($dados['acao']) || !isset($dados['mensagem_id'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$negociacao_id = $dados['negociacao_id'];
$acao = $dados['acao']; // 'aceita' ou 'recusada'
$mensagem_id = $dados['mensagem_id'];

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();
    
    // Buscar informações completas da negociação
    $sql_negociacao = "SELECT 
        pn.*,
        pc.comprador_id,
        pv.vendedor_id
        FROM propostas_negociacao pn
        LEFT JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.ID
        LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.ID
        WHERE pn.ID = :negociacao_id";
    
    $stmt = $conn->prepare($sql_negociacao);
    $stmt->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt->execute();
    $negociacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$negociacao) {
        throw new Exception('Negociação não encontrada');
    }
    
    // Verificar quem está respondendo
    $comprador_id = $negociacao['comprador_id'];
    $vendedor_id = $negociacao['vendedor_id'];
    
    $eh_comprador = ($usuario_id == $comprador_id);
    $eh_vendedor = ($usuario_id == $vendedor_id);
    
    // Verificar se é o usuário correto (o que NÃO enviou a última proposta)
    // Para simplificar, vamos permitir que qualquer um dos dois responda à proposta do outro
    if (!$eh_comprador && !$eh_vendedor) {
        throw new Exception('Você não faz parte desta negociação');
    }
    
    // Verificar se já foi respondida
    if ($negociacao['status'] !== 'pendente') {
        throw new Exception('Esta negociação já foi respondida');
    }
    
    // Atualizar status da negociação
    $sql_update_negociacao = "UPDATE propostas_negociacao 
                             SET status = :status, 
                                 data_atualizacao = NOW() 
                             WHERE ID = :negociacao_id";
    
    $stmt_update = $conn->prepare($sql_update_negociacao);
    $stmt_update->bindParam(':status', $acao);
    $stmt_update->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt_update->execute();
    
    // Atualizar status das propostas individuais
    if ($negociacao['proposta_comprador_id']) {
        $sql_update_proposta = "UPDATE propostas_comprador 
                               SET status = :status 
                               WHERE ID = :proposta_id";
        $stmt_prop = $conn->prepare($sql_update_proposta);
        $stmt_prop->bindParam(':status', $acao);
        $stmt_prop->bindParam(':proposta_id', $negociacao['proposta_comprador_id'], PDO::PARAM_INT);
        $stmt_prop->execute();
    }
    
    if ($negociacao['proposta_vendedor_id']) {
        $sql_update_proposta = "UPDATE propostas_vendedor 
                               SET status = :status 
                               WHERE ID = :proposta_id";
        $stmt_prop = $conn->prepare($sql_update_proposta);
        $stmt_prop->bindParam(':status', $acao);
        $stmt_prop->bindParam(':proposta_id', $negociacao['proposta_vendedor_id'], PDO::PARAM_INT);
        $stmt_prop->execute();
    }
    
    // Buscar mensagem original para atualizar
    $sql_mensagem = "SELECT conversa_id, dados_json FROM chat_mensagens WHERE id = :mensagem_id";
    $stmt_msg = $conn->prepare($sql_mensagem);
    $stmt_msg->bindParam(':mensagem_id', $mensagem_id, PDO::PARAM_INT);
    $stmt_msg->execute();
    $mensagem_original = $stmt_msg->fetch(PDO::FETCH_ASSOC);
    
    if ($mensagem_original && $mensagem_original['dados_json']) {
        // Atualizar dados JSON da mensagem com o novo status
        $dados_json = json_decode($mensagem_original['dados_json'], true);
        $dados_json['status'] = $acao;
        
        $sql_update_msg = "UPDATE chat_mensagens 
                          SET dados_json = :dados_json 
                          WHERE id = :mensagem_id";
        
        $stmt_upd_msg = $conn->prepare($sql_update_msg);
        $stmt_upd_msg->bindParam(':dados_json', json_encode($dados_json));
        $stmt_upd_msg->bindParam(':mensagem_id', $mensagem_id, PDO::PARAM_INT);
        $stmt_upd_msg->execute();
        
        // Enviar mensagem de resposta
        $status_texto = $acao === 'aceita' ? 'aceitou' : 'recusou';
        $mensagem_resposta = "✅ Você {$status_texto} a proposta de compra";
        
        $sql_resposta = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) 
                        VALUES (:conversa_id, :remetente_id, :mensagem, 'texto')";
        
        $stmt_resp = $conn->prepare($sql_resposta);
        $stmt_resp->bindParam(':conversa_id', $mensagem_original['conversa_id'], PDO::PARAM_INT);
        $stmt_resp->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
        $stmt_resp->bindParam(':mensagem', $mensagem_resposta);
        $stmt_resp->execute();
        
        // Atualizar conversa
        $sql_update_conv = "UPDATE chat_conversas 
                           SET ultima_mensagem = :mensagem,
                               ultima_mensagem_data = NOW(),
                               comprador_lido = IF(comprador_id = :usuario_id, 1, 0),
                               vendedor_lido = IF(vendedor_id = :usuario_id, 1, 0)
                           WHERE id = :conversa_id";
        
        $stmt_upd_conv = $conn->prepare($sql_update_conv);
        $stmt_upd_conv->bindParam(':mensagem', $mensagem_resposta);
        $stmt_upd_conv->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_upd_conv->bindParam(':conversa_id', $mensagem_original['conversa_id'], PDO::PARAM_INT);
        $stmt_upd_conv->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Resposta enviada com sucesso',
        'status' => $acao
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Erro ao responder negociação: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>