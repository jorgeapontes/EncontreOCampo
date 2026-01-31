<?php
// src/chat_transportador/responder_proposta.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Log para depuração
error_log("=== INICIANDO responder_proposta.php ===");
$input = file_get_contents('php://input');
error_log("Input recebido: " . $input);

if (!isset($_SESSION['usuario_id'])) {
    error_log("Usuário não logado");
    echo json_encode(['success' => false, 'error' => 'Acesso restrito']);
    exit();
}

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erro no JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit();
}

$acao = isset($data['acao']) ? $data['acao'] : '';
$id = isset($data['id']) ? (int)$data['id'] : 0;

error_log("Ação: $acao, ID: $id");

if ($id <= 0 || !in_array($acao, ['aceitar', 'recusar'])) {
    error_log("Dados inválidos: ID=$id, Ação=$acao");
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();
    
    // 1. Verificar se a proposta existe e obter dados
    $sql = "SELECT pt.*, p.comprador_id, p.vendedor_id, p.produto_id 
            FROM propostas_transportadores pt
            INNER JOIN propostas p ON pt.proposta_id = p.ID
            WHERE pt.id = :id AND pt.status = 'pendente'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        error_log("Proposta não encontrada ou já respondida. ID: $id");
        throw new Exception('Proposta não encontrada ou já respondida');
    }
    
    error_log("Proposta encontrada: " . print_r($proposta, true));
    
    // 2. Verificar se o usuário atual é o comprador
    if ($_SESSION['usuario_id'] != $proposta['comprador_id']) {
        error_log("Usuário não é o comprador. Sessão: {$_SESSION['usuario_id']}, Comprador: {$proposta['comprador_id']}");
        throw new Exception('Somente o comprador pode responder a esta proposta');
    }
    
    $novo_status = ($acao == 'aceitar') ? 'aceita' : 'recusada';
    error_log("Novo status: $novo_status");
    
    // 3. Atualizar status da proposta do transportador
    $sql_update = "UPDATE propostas_transportadores 
                   SET status = :status, data_resposta = NOW() 
                   WHERE id = :id";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bindParam(':status', $novo_status);
    $stmt_update->bindParam(':id', $id, PDO::PARAM_INT);
    
    if (!$stmt_update->execute()) {
        throw new Exception('Erro ao atualizar proposta');
    }
    
    // 4. Se aceita, vincular transportador à proposta principal
    if ($acao == 'aceitar') {
        // Obter transportador_id da proposta
        $sql_transp = "SELECT transportador_id FROM propostas_transportadores WHERE id = :id";
        $stmt_transp = $conn->prepare($sql_transp);
        $stmt_transp->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt_transp->execute();
        $transp = $stmt_transp->fetch(PDO::FETCH_ASSOC);
        
        if ($transp) {
            // Atualizar proposta principal com transportador
            $sql_upd_proposta = "UPDATE propostas 
                                 SET transportador_id = :transportador_id, 
                                     status_entrega = 'aceita',
                                     valor_frete_final = (SELECT valor_frete FROM propostas_transportadores WHERE id = :pt_id)
                                 WHERE ID = :proposta_id";
            $stmt_upd = $conn->prepare($sql_upd_proposta);
            $stmt_upd->bindParam(':transportador_id', $transp['transportador_id'], PDO::PARAM_INT);
            $stmt_upd->bindParam(':pt_id', $id, PDO::PARAM_INT);
            $stmt_upd->bindParam(':proposta_id', $proposta['proposta_id'], PDO::PARAM_INT);
            $stmt_upd->execute();
            
            // Criar registro de entrega
            // Buscar endereço do comprador
            $sql_comprador = "SELECT cep, rua, numero, complemento, estado, cidade 
                              FROM compradores 
                              WHERE usuario_id = :comprador_id";
            $stmt_comprador = $conn->prepare($sql_comprador);
            $stmt_comprador->bindParam(':comprador_id', $proposta['comprador_id'], PDO::PARAM_INT);
            $stmt_comprador->execute();
            $end_comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);
            
            if ($end_comprador) {
                $endereco_destino = $end_comprador['rua'] . ', ' . $end_comprador['numero'] . 
                                   ' - ' . $end_comprador['cidade'] . '/' . $end_comprador['estado'] . 
                                   ' - CEP: ' . $end_comprador['cep'];
                
                // Buscar endereço do vendedor
                $sql_vendedor = "SELECT v.id, v.cep, v.rua, v.numero, v.complemento, v.estado, v.cidade 
                    FROM vendedores v
                    WHERE v.usuario_id = :vendedor_id";
                    $stmt_vendedor = $conn->prepare($sql_vendedor);
                    $stmt_vendedor->bindParam(':vendedor_id', $proposta['vendedor_id'], PDO::PARAM_INT);
                    $stmt_vendedor->execute();
                    $end_vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

                    $endereco_origem = '';
                    $vendedor_id_para_entrega = null;
                    if ($end_vendedor) {
                        $endereco_origem = $end_vendedor['rua'] . ', ' . $end_vendedor['numero'] . 
                                        ' - ' . $end_vendedor['cidade'] . '/' . $end_vendedor['estado'] . 
                                        ' - CEP: ' . $end_vendedor['cep'];
                        $vendedor_id_para_entrega = $end_vendedor['id']; // Usa o ID correto da tabela vendedores
                    }

                    // Depois, na inserção da entrega:
                    $sql_entrega = "INSERT INTO entregas 
                                (produto_id, transportador_id, endereco_origem, endereco_destino, 
                                    status, valor_frete, vendedor_id, comprador_id, data_solicitacao, 
                                    data_aceitacao, status_detalhado) 
                                VALUES (:produto_id, :transportador_id, :endereco_origem, :endereco_destino, 
                                        'pendente', (SELECT valor_frete FROM propostas_transportadores WHERE id = :pt_id), 
                                        :vendedor_id, :comprador_id, NOW(), NOW(), 'aguardando_entrega')";
                    $stmt_entrega = $conn->prepare($sql_entrega);
                    $stmt_entrega->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
                    $stmt_entrega->bindParam(':transportador_id', $transp['transportador_id'], PDO::PARAM_INT);
                    $stmt_entrega->bindParam(':endereco_origem', $endereco_origem);
                    $stmt_entrega->bindParam(':endereco_destino', $endereco_destino);
                    $stmt_entrega->bindParam(':pt_id', $id, PDO::PARAM_INT);
                    $stmt_entrega->bindParam(':vendedor_id', $vendedor_id_para_entrega, PDO::PARAM_INT);
                    $stmt_entrega->bindParam(':comprador_id', $proposta['comprador_id'], PDO::PARAM_INT);
                    $stmt_entrega->execute();
                
                error_log("Entrega criada com sucesso");
            }
        }
    }
    
    // 5. Enviar mensagem automática no chat
    // Buscar conversa existente
    $sql_conversa = "SELECT id FROM chat_conversas 
                     WHERE produto_id = :produto_id 
                     AND comprador_id = :comprador_id 
                     AND transportador_id = (SELECT usuario_id FROM transportadores WHERE id = (SELECT transportador_id FROM propostas_transportadores WHERE id = :pt_id))";
    $stmt_conversa = $conn->prepare($sql_conversa);
    $stmt_conversa->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
    $stmt_conversa->bindParam(':comprador_id', $proposta['comprador_id'], PDO::PARAM_INT);
    $stmt_conversa->bindParam(':pt_id', $id, PDO::PARAM_INT);
    $stmt_conversa->execute();
    $conversa = $stmt_conversa->fetch(PDO::FETCH_ASSOC);
    
    if ($conversa) {
        $mensagem = '';
        if ($acao == 'aceitar') {
            $mensagem = '✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.';
        } else {
            $mensagem = '❌ Proposta recusada.';
        }
        
        // Inserir mensagem automática
        $sql_msg = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) 
                   VALUES (:conversa_id, :remetente_id, :mensagem, 'aceite')";
        $stmt_msg = $conn->prepare($sql_msg);
        $stmt_msg->bindParam(':conversa_id', $conversa['id'], PDO::PARAM_INT);
        $stmt_msg->bindParam(':remetente_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
        $stmt_msg->bindParam(':mensagem', $mensagem);
        $stmt_msg->execute();
        
        // Atualizar última mensagem na conversa
        $sql_upd_conv = "UPDATE chat_conversas 
                         SET ultima_mensagem = :mensagem, ultima_mensagem_data = NOW() 
                         WHERE id = :conversa_id";
        $stmt_upd_conv = $conn->prepare($sql_upd_conv);
        $stmt_upd_conv->bindParam(':mensagem', $mensagem);
        $stmt_upd_conv->bindParam(':conversa_id', $conversa['id'], PDO::PARAM_INT);
        $stmt_upd_conv->execute();
        
        error_log("Mensagem de $acao inserida no chat");
    }
    
    $conn->commit();
    error_log("Transação concluída com sucesso");
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}