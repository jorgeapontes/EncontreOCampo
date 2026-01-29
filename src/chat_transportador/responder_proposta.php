<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    echo json_encode(['success' => false, 'erro' => 'Acesso restrito']);
    exit();
}

$json = file_get_contents('php://input');
$dados = json_decode($json, true);
if (!$dados || !isset($dados['acao']) || !isset($dados['id'])) {
    echo json_encode(['success' => false, 'erro' => 'Dados inválidos']);
    exit();
}

$acao = $dados['acao'];
$pt_id = (int)$dados['id']; // ID da proposta_transportador
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();

    // Buscar proposta_transportador E a proposta original
    $sql = "SELECT pt.*, p.ID as proposta_id, p.produto_id, p.comprador_id, p.vendedor_id, 
                   t.id as transportador_id_sistema, t.usuario_id as transportador_usuario_id,
                   p.frete_resolvido, p.status as proposta_status, p.valor_frete as valor_frete_proposta,
                   p.opcao_frete
            FROM propostas_transportadores pt
            JOIN propostas p ON pt.proposta_id = p.ID
            LEFT JOIN transportadores t ON pt.transportador_id = t.id
            WHERE pt.id = :id LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $pt_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('Proposta não encontrada');

    // Verificar se o transportador logado é o destinatário
    if ((int)$row['transportador_usuario_id'] !== $usuario_id) {
        throw new Exception('Ação não permitida');
    }

    // VERIFICAÇÃO CRÍTICA: Verificar se a proposta já foi respondida
    if (!empty($row['status']) && $row['status'] !== 'pendente') {
        throw new Exception('Esta proposta já foi ' . $row['status']);
    }

    if ($acao === 'aceitar') {
        // DEBUG: Log para verificar dados
        error_log("DEBUG: Proposta transportador ID: " . $pt_id);
        error_log("DEBUG: Proposta ID associada: " . $row['proposta_id']);
        error_log("DEBUG: Opção frete: " . $row['opcao_frete']);
        error_log("DEBUG: Transportador sistema ID: " . ($row['transportador_id_sistema'] ?? 'null'));

        // Resolver id do transportador na tabela `transportadores`
        $transportador_sistema_id = null;
        if (!empty($row['transportador_id_sistema'])) {
            $transportador_sistema_id = (int)$row['transportador_id_sistema'];
        } elseif (!empty($row['transportador_id'])) {
            $st_lookup = $conn->prepare("SELECT id FROM transportadores WHERE usuario_id = :uid LIMIT 1");
            $st_lookup->bindParam(':uid', $row['transportador_id'], PDO::PARAM_INT);
            $st_lookup->execute();
            $lk = $st_lookup->fetch(PDO::FETCH_ASSOC);
            if ($lk) $transportador_sistema_id = (int)$lk['id'];
        }
        if ($transportador_sistema_id === null) {
            $st_lookup2 = $conn->prepare("SELECT id FROM transportadores WHERE usuario_id = :uid LIMIT 1");
            $st_lookup2->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
            $st_lookup2->execute();
            $lk2 = $st_lookup2->fetch(PDO::FETCH_ASSOC);
            if ($lk2) $transportador_sistema_id = (int)$lk2['id'];
        }

        // Atualizar status da proposta transportador
        $sql_up = "UPDATE propostas_transportadores SET status = 'aceita', data_resposta = NOW() WHERE id = :id";
        $stmt_up = $conn->prepare($sql_up);
        $stmt_up->bindParam(':id', $pt_id, PDO::PARAM_INT);
        $stmt_up->execute();

        // BUSCAR A PROPOSTA ORIGINAL COMPRADOR-VENDEDOR
        // A proposta que tem opcao_frete = 'entregador' e status = 'aceita'
        $sql_original = "SELECT ID FROM propostas 
                        WHERE produto_id = :produto_id 
                        AND comprador_id = :comprador_id 
                        AND vendedor_id = :vendedor_id 
                        AND opcao_frete = 'entregador' 
                        AND status = 'aceita'
                        ORDER BY ID ASC LIMIT 1";
        
        $stmt_original = $conn->prepare($sql_original);
        $stmt_original->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
        $stmt_original->bindParam(':comprador_id', $row['comprador_id'], PDO::PARAM_INT);
        $stmt_original->bindParam(':vendedor_id', $row['vendedor_id'], PDO::PARAM_INT);
        $stmt_original->execute();
        $original_row = $stmt_original->fetch(PDO::FETCH_ASSOC);
        
        if (!$original_row) {
            throw new Exception('Não foi possível encontrar a proposta original entre comprador e vendedor');
        }
        
        $proposta_original_id = (int)$original_row['ID'];
        error_log("DEBUG: Proposta original ID encontrada: " . $proposta_original_id);

        // Buscar o ID correto do transportador na tabela transportadores
        $sql_get_transp_id = "SELECT t.id FROM transportadores t WHERE t.usuario_id = :usuario_id";
        $stmt_get_transp_id = $conn->prepare($sql_get_transp_id);
        $stmt_get_transp_id->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_get_transp_id->execute();
        $transp_row = $stmt_get_transp_id->fetch(PDO::FETCH_ASSOC);

        if ($transp_row) {
            $transportador_id_final = $transp_row['id'];
            
            // ATUALIZAR A PROPOSTA ORIGINAL COMPRADOR-VENDEDOR
            $sql_update_proposta = "UPDATE propostas SET 
                transportador_id = :transportador_id, 
                frete_resolvido = 1,
                data_atualizacao = NOW(),
                valor_frete_final = :valor_frete
                WHERE ID = :proposta_id";
            
            $stmt_update_proposta = $conn->prepare($sql_update_proposta);
            $stmt_update_proposta->bindParam(':transportador_id', $transportador_id_final, PDO::PARAM_INT);
            $stmt_update_proposta->bindParam(':valor_frete', $row['valor_frete']);
            $stmt_update_proposta->bindParam(':proposta_id', $proposta_original_id, PDO::PARAM_INT);
            $stmt_update_proposta->execute();
            
            // Verificar se atualizou
            if ($stmt_update_proposta->rowCount() == 0) {
                error_log("ERRO: Não atualizou proposta original ID: " . $proposta_original_id);
                throw new Exception('Não foi possível atualizar a proposta original');
            } else {
                error_log("SUCESSO: Atualizou proposta original ID: " . $proposta_original_id . 
                         " com transportador_id: " . $transportador_id_final . 
                         " e frete_resolvido = 1");
            }
        } else {
            throw new Exception('Transportador não encontrado no sistema');
        }

        // Preparar endereços vendedor/comprador DA PROPOSTA ORIGINAL
        $end_origem = '';
        $end_destino = '';
        
        // vendedor - buscar da proposta original
        if (!empty($row['vendedor_id'])) {
            $sql_v = "SELECT rua, numero, complemento, cidade, estado, cep, nome_comercial 
                     FROM vendedores WHERE id = :vid LIMIT 1";
            $st_v = $conn->prepare($sql_v);
            $st_v->bindParam(':vid', $row['vendedor_id'], PDO::PARAM_INT);
            $st_v->execute();
            $v = $st_v->fetch(PDO::FETCH_ASSOC);
            if ($v) {
                $end_origem = trim(($v['rua'] ?? '') . ', ' . ($v['numero'] ?? '') . 
                                 ' - ' . ($v['cidade'] ?? '') . '/' . ($v['estado'] ?? '') . 
                                 ' - CEP: ' . ($v['cep'] ?? ''));
            }
        }
        
        // comprador - buscar da proposta original
        if (!empty($row['comprador_id'])) {
            // Primeiro tentar pelo usuario_id
            $sql_c = "SELECT rua, numero, complemento, cidade, estado, cep 
                     FROM compradores WHERE usuario_id = :cid LIMIT 1";
            $st_c = $conn->prepare($sql_c);
            $st_c->bindParam(':cid', $row['comprador_id'], PDO::PARAM_INT);
            $st_c->execute();
            $c = $st_c->fetch(PDO::FETCH_ASSOC);
            if (!$c) {
                // Se não encontrar, tentar pelo id direto
                $sql_c2 = "SELECT rua, numero, complemento, cidade, estado, cep 
                          FROM compradores WHERE id = :cid LIMIT 1";
                $st_c2 = $conn->prepare($sql_c2);
                $st_c2->bindParam(':cid', $row['comprador_id'], PDO::PARAM_INT);
                $st_c2->execute();
                $c = $st_c2->fetch(PDO::FETCH_ASSOC);
            }
            if ($c) {
                $end_destino = trim(($c['rua'] ?? '') . ', ' . ($c['numero'] ?? '') . 
                                   ' - ' . ($c['cidade'] ?? '') . '/' . ($c['estado'] ?? '') . 
                                   ' - CEP: ' . ($c['cep'] ?? ''));
            }
        }

        // Resolver vendedor_id para inserir em entregas
        $vendedor_para_entrega = null;
        if (!empty($row['vendedor_id'])) {
            // Tentar como id da tabela vendedores
            $st_vchk = $conn->prepare("SELECT id FROM vendedores WHERE id = :vid LIMIT 1");
            $st_vchk->bindParam(':vid', $row['vendedor_id'], PDO::PARAM_INT);
            $st_vchk->execute();
            $vchk = $st_vchk->fetch(PDO::FETCH_ASSOC);
            if ($vchk) {
                $vendedor_para_entrega = $vchk['id'];
            } else {
                // Tentar como usuario_id
                $st_vchk2 = $conn->prepare("SELECT id FROM vendedores WHERE usuario_id = :uid LIMIT 1");
                $st_vchk2->bindParam(':uid', $row['vendedor_id'], PDO::PARAM_INT);
                $st_vchk2->execute();
                $vchk2 = $st_vchk2->fetch(PDO::FETCH_ASSOC);
                if ($vchk2) $vendedor_para_entrega = $vchk2['id'];
            }
        }

        // Criar entrega baseada na PROPOSTA ORIGINAL
        $sql_ent = "INSERT INTO entregas (produto_id, transportador_id, endereco_origem, 
                    endereco_destino, status, data_solicitacao, valor_frete, vendedor_id, 
                    comprador_id, data_aceitacao, observacoes, status_detalhado) 
                    VALUES (:produto_id, :transportador_id, :end_origem, :end_destino, 
                    'pendente', NOW(), :valor_frete, :vendedor_id, :comprador_id, NOW(), 
                    :observacoes, 'aguardando_entrega')";
        
        $st_ent = $conn->prepare($sql_ent);
        $st_ent->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
        
        // Usar o id da tabela transportadores para a entrega
        if ($transportador_sistema_id !== null) {
            $st_ent->bindValue(':transportador_id', $transportador_sistema_id, PDO::PARAM_INT);
        } else {
            $st_ent->bindValue(':transportador_id', $row['transportador_id'] ?? null, PDO::PARAM_INT);
        }
        
        $st_ent->bindParam(':end_origem', $end_origem);
        $st_ent->bindParam(':end_destino', $end_destino);
        $st_ent->bindParam(':valor_frete', $row['valor_frete']);
        
        if ($vendedor_para_entrega !== null) {
            $st_ent->bindValue(':vendedor_id', $vendedor_para_entrega, PDO::PARAM_INT);
        } else {
            $st_ent->bindValue(':vendedor_id', null, PDO::PARAM_NULL);
        }
        
        $st_ent->bindParam(':comprador_id', $row['comprador_id'], PDO::PARAM_INT);
        $st_ent->bindParam(':observacoes', $row['observacoes']);
        
        if (!$st_ent->execute()) {
            $error = $st_ent->errorInfo();
            throw new Exception("Erro ao criar entrega: " . ($error[2] ?? 'Erro desconhecido'));
        }

        // Inserir mensagem no chat notificando aceitação
        $convRow = null;
        if ($transportador_sistema_id !== null) {
            $sql_conv = "SELECT id FROM chat_conversas 
                        WHERE produto_id = :produto_id 
                        AND comprador_id = :comprador_id 
                        AND transportador_id = :tid LIMIT 1";
            $st_conv = $conn->prepare($sql_conv);
            $st_conv->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
            $st_conv->bindParam(':comprador_id', $row['comprador_id'], PDO::PARAM_INT);
            $st_conv->bindParam(':tid', $transportador_sistema_id, PDO::PARAM_INT);
            $st_conv->execute();
            $convRow = $st_conv->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$convRow) {
            // fallback: qualquer conversa existente para esse produto/comprador com transportador
            $sql_conv2 = "SELECT id FROM chat_conversas 
                         WHERE produto_id = :produto_id 
                         AND comprador_id = :comprador_id 
                         AND transportador_id IS NOT NULL LIMIT 1";
            $st_conv2 = $conn->prepare($sql_conv2);
            $st_conv2->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
            $st_conv2->bindParam(':comprador_id', $row['comprador_id'], PDO::PARAM_INT);
            $st_conv2->execute();
            $convRow = $st_conv2->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($convRow) {
            $conversa_id = (int)$convRow['id'];
            $msg = "✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.";
            $sql_msg = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) 
                       VALUES (:conversa_id, :remetente_id, :mensagem, 'aceite')";
            $st_msg = $conn->prepare($sql_msg);
            $st_msg->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
            $st_msg->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
            $st_msg->bindParam(':mensagem', $msg);
            $st_msg->execute();
        }

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Proposta aceita com sucesso',
            'proposta_original_id' => $proposta_original_id,
            'frete_resolvido' => 1
        ]);
        exit();

    } elseif ($acao === 'recusar') {
        // Verificar se já foi respondida
        if (!empty($row['status']) && $row['status'] !== 'pendente') {
            throw new Exception('Esta proposta já foi ' . $row['status']);
        }
        
        $sql_up = "UPDATE propostas_transportadores SET status = 'recusada', data_resposta = NOW() WHERE id = :id";
        $stmt_up = $conn->prepare($sql_up);
        $stmt_up->bindParam(':id', $pt_id, PDO::PARAM_INT);
        $stmt_up->execute();
        
        // Opcional: enviar mensagem no chat informando a recusa
        $sql_conv = "SELECT id FROM chat_conversas 
                    WHERE produto_id = :produto_id 
                    AND comprador_id = :comprador_id 
                    AND transportador_id = :tid LIMIT 1";
        $st_conv = $conn->prepare($sql_conv);
        $st_conv->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
        $st_conv->bindParam(':comprador_id', $row['comprador_id'], PDO::PARAM_INT);
        $transportador_sistema_id = $row['transportador_id_sistema'] ?? null;
        $st_conv->bindParam(':tid', $transportador_sistema_id, PDO::PARAM_INT);
        $st_conv->execute();
        $convRow = $st_conv->fetch(PDO::FETCH_ASSOC);
        
        if ($convRow) {
            $conversa_id = (int)$convRow['id'];
            $msg = "❌ Proposta recusada pelo transportador.";
            $sql_msg = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) 
                       VALUES (:conversa_id, :remetente_id, :mensagem, 'texto')";
            $st_msg = $conn->prepare($sql_msg);
            $st_msg->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
            $st_msg->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
            $st_msg->bindParam(':mensagem', $msg);
            $st_msg->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Proposta recusada']);
        exit();
    }

    throw new Exception('Ação inválida');

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    error_log("ERRO responder_proposta: " . $e->getMessage());
    echo json_encode(['success' => false, 'erro' => $e->getMessage()]);
    exit();
}
?>