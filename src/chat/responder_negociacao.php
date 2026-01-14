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
    
    // PRIMEIRO: Buscar a mensagem para obter conversa_id e dados_json
    $sql_mensagem = "SELECT conversa_id, dados_json, remetente_id FROM chat_mensagens WHERE id = :mensagem_id";
    $stmt_msg = $conn->prepare($sql_mensagem);
    $stmt_msg->bindParam(':mensagem_id', $mensagem_id, PDO::PARAM_INT);
    $stmt_msg->execute();
    $mensagem_original = $stmt_msg->fetch(PDO::FETCH_ASSOC);
    
    if (!$mensagem_original) {
        throw new Exception('Mensagem não encontrada');
    }
    
    // SEGUNDO: Verificar se o usuário faz parte da conversa
    $sql_conversa = "SELECT comprador_id, vendedor_id FROM chat_conversas WHERE id = :conversa_id";
    $stmt_conv = $conn->prepare($sql_conversa);
    $stmt_conv->bindParam(':conversa_id', $mensagem_original['conversa_id'], PDO::PARAM_INT);
    $stmt_conv->execute();
    $conversa = $stmt_conv->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversa) {
        throw new Exception('Conversa não encontrada');
    }
    
    // Verificar se o usuário faz parte da conversa
    $eh_participante = ($usuario_id == $conversa['comprador_id'] || $usuario_id == $conversa['vendedor_id']);
    
    if (!$eh_participante) {
        throw new Exception('Você não faz parte desta conversa');
    }
    
    // Verificar se o usuário está tentando responder à sua própria proposta
    if ($mensagem_original['remetente_id'] == $usuario_id) {
        throw new Exception('Você não pode responder à sua própria proposta');
    }
    
    // TERCEIRO: Buscar a negociação usando o ID fornecido
    $sql_negociacao = "SELECT * FROM propostas_negociacao WHERE ID = :negociacao_id";
    $stmt_neg = $conn->prepare($sql_negociacao);
    $stmt_neg->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt_neg->execute();
    $negociacao = $stmt_neg->fetch(PDO::FETCH_ASSOC);
    
    if (!$negociacao) {
        throw new Exception('Negociação não encontrada');
    }
    
    // Verificar se a negociação está associada à mensagem correta
    // (verificando se o dados_json contém o negociacao_id correto)
    $dados_json = json_decode($mensagem_original['dados_json'], true);
    if (!$dados_json || !isset($dados_json['negociacao_id']) || $dados_json['negociacao_id'] != $negociacao_id) {
        throw new Exception('Negociação não associada a esta mensagem');
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
    
    // Atualizar status das propostas individuais se existirem
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
    
    // Atualizar dados JSON da mensagem com o novo status
    if ($dados_json) {
        $dados_json['status'] = $acao;
        
        $sql_update_msg = "UPDATE chat_mensagens 
                          SET dados_json = :dados_json 
                          WHERE id = :mensagem_id";
        
        $stmt_upd_msg = $conn->prepare($sql_update_msg);
        $stmt_upd_msg->bindParam(':dados_json', json_encode($dados_json));
        $stmt_upd_msg->bindParam(':mensagem_id', $mensagem_id, PDO::PARAM_INT);
        $stmt_upd_msg->execute();
        
        // Enviar mensagem de resposta
        $status_texto = $acao === 'aceita' ? 'aceito' : 'recusado';
        $mensagem_resposta = "O acordo de compra foi {$status_texto} ";
        
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