<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Alterado: apenas comprador pode responder
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    echo json_encode(['success' => false, 'erro' => 'Acesso restrito - Apenas compradores podem responder propostas']);
    exit();
}

$json = file_get_contents('php://input');
$dados = json_decode($json, true);
if (!$dados || !isset($dados['acao']) || !isset($dados['id'])) {
    echo json_encode(['success' => false, 'erro' => 'Dados inválidos']);
    exit();
}

$acao = $dados['acao'];
$pt_id = (int)$dados['id'];
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();

    // Buscar proposta_transportador
    $sql = "SELECT pt.*, p.ID as proposta_id, p.produto_id, p.comprador_id, p.vendedor_id, t.id as transportador_id_sistema, t.usuario_id as transportador_usuario_id
            FROM propostas_transportadores pt
            JOIN propostas p ON pt.proposta_id = p.ID
            LEFT JOIN transportadores t ON pt.transportador_id = t.id
            WHERE pt.id = :id LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $pt_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('Proposta não encontrada');

    // Verificar se o comprador logado é o destinatário
    if ((int)$row['comprador_id'] !== $usuario_id) {
        throw new Exception('Apenas o comprador desta proposta pode respondê-la');
    }

    // VERIFICAÇÃO CRÍTICA: Verificar se a proposta já foi respondida
    if (!empty($row['status']) && $row['status'] !== 'pendente') {
        throw new Exception('Esta proposta já foi ' . $row['status']);
    }

    if ($acao === 'aceitar') {
        // Resolver id do transportador na tabela `transportadores` (prioritário) para checagens
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

        // Atualizar status da proposta transportador
        $sql_up = "UPDATE propostas_transportadores SET status = 'aceita', data_resposta = NOW() WHERE id = :id";
        $stmt_up = $conn->prepare($sql_up);
        $stmt_up->bindParam(':id', $pt_id, PDO::PARAM_INT);
        $stmt_up->execute();

        // Atualizar a proposta principal
        $sql_pm = "UPDATE propostas SET data_atualizacao = NOW() WHERE ID = :pid";
        $stmt_pm = $conn->prepare($sql_pm);
        $stmt_pm->bindParam(':pid', $row['proposta_id'], PDO::PARAM_INT);
        $stmt_pm->execute();

        // Preparar endereços vendedor/comprador
        $end_origem = '';
        $end_destino = '';
        // vendedor
        if (!empty($row['vendedor_id'])) {
            $sql_v = "SELECT rua, numero, complemento, cidade, estado, cep, nome_comercial FROM vendedores WHERE id = :vid LIMIT 1";
            $st_v = $conn->prepare($sql_v);
            $st_v->bindParam(':vid', $row['vendedor_id'], PDO::PARAM_INT);
            $st_v->execute();
            $v = $st_v->fetch(PDO::FETCH_ASSOC);
            if ($v) {
                $end_origem = trim(($v['rua'] ?? '') . ', ' . ($v['numero'] ?? '') . ' - ' . ($v['cidade'] ?? '') . '/' . ($v['estado'] ?? '') . ' - CEP: ' . ($v['cep'] ?? ''));
            }
        }
        // comprador
        if (!empty($row['comprador_id'])) {
            $sql_c = "SELECT rua, numero, complemento, cidade, estado, cep FROM compradores WHERE usuario_id = :cid LIMIT 1";
            $st_c = $conn->prepare($sql_c);
            $st_c->bindParam(':cid', $row['comprador_id'], PDO::PARAM_INT);
            $st_c->execute();
            $c = $st_c->fetch(PDO::FETCH_ASSOC);
            if ($c) {
                $end_destino = trim(($c['rua'] ?? '') . ', ' . ($c['numero'] ?? '') . ' - ' . ($c['cidade'] ?? '') . '/' . ($c['estado'] ?? '') . ' - CEP: ' . ($c['cep'] ?? ''));
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
                // Tentar como usuario_id (legado)
                $st_vchk2 = $conn->prepare("SELECT id FROM vendedores WHERE usuario_id = :uid LIMIT 1");
                $st_vchk2->bindParam(':uid', $row['vendedor_id'], PDO::PARAM_INT);
                $st_vchk2->execute();
                $vchk2 = $st_vchk2->fetch(PDO::FETCH_ASSOC);
                if ($vchk2) $vendedor_para_entrega = $vchk2['id'];
            }
        }

        // Criar entrega
        $sql_ent = "INSERT INTO entregas (produto_id, transportador_id, endereco_origem, endereco_destino, status, data_solicitacao, valor_frete, vendedor_id, comprador_id, data_aceitacao, observacoes, status_detalhado) VALUES (:produto_id, :transportador_id, :end_origem, :end_destino, 'pendente', NOW(), :valor_frete, :vendedor_id, :comprador_id, NOW(), :observacoes, 'aguardando_entrega')";
        $st_ent = $conn->prepare($sql_ent);
        $st_ent->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
        // Usar o id da tabela transportadores para a entrega
        if ($transportador_sistema_id !== null) {
            $st_ent->bindValue(':transportador_id', $transportador_sistema_id, PDO::PARAM_INT);
        } else {
            // fallback
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
        $st_ent->execute();

        // Inserir mensagem no chat notificando aceitação - MENSAGEM ALTERADA
        $convRow = null;
        if ($transportador_sistema_id !== null) {
            $sql_conv = "SELECT id FROM chat_conversas WHERE produto_id = :produto_id AND comprador_id = :comprador_id AND transportador_id = :tid LIMIT 1";
            $st_conv = $conn->prepare($sql_conv);
            $st_conv->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
            $st_conv->bindParam(':comprador_id', $row['comprador_id'], PDO::PARAM_INT);
            $st_conv->bindParam(':tid', $transportador_sistema_id, PDO::PARAM_INT);
            $st_conv->execute();
            $convRow = $st_conv->fetch(PDO::FETCH_ASSOC);
        }
        if (!$convRow) {
            // fallback
            $sql_conv2 = "SELECT id FROM chat_conversas WHERE produto_id = :produto_id AND comprador_id = :comprador_id AND transportador_id IS NOT NULL LIMIT 1";
            $st_conv2 = $conn->prepare($sql_conv2);
            $st_conv2->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
            $st_conv2->bindParam(':comprador_id', $row['comprador_id'], PDO::PARAM_INT);
            $st_conv2->execute();
            $convRow = $st_conv2->fetch(PDO::FETCH_ASSOC);
        }
        if ($convRow) {
            $conversa_id = (int)$convRow['id'];
            // MENSAGEM ALTERADA: Agora indica que foi aceita pelo comprador
            $msg = "✅ Proposta aceita pelo comprador. O transportador será notificado para proceder com a coleta e entrega.";
            $sql_msg = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) VALUES (:conversa_id, :remetente_id, :mensagem, 'aceite')";
            $st_msg = $conn->prepare($sql_msg);
            $st_msg->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
            $st_msg->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
            $st_msg->bindParam(':mensagem', $msg);
            $st_msg->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true]);
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
        $sql_conv = "SELECT id FROM chat_conversas WHERE produto_id = :produto_id AND comprador_id = :comprador_id AND transportador_id = :tid LIMIT 1";
        $st_conv = $conn->prepare($sql_conv);
        $st_conv->bindParam(':produto_id', $row['produto_id'], PDO::PARAM_INT);
        $st_conv->bindParam(':comprador_id', $row['comprador_id'], PDO::PARAM_INT);
        $transportador_sistema_id = $row['transportador_id_sistema'] ?? null;
        $st_conv->bindParam(':tid', $transportador_sistema_id, PDO::PARAM_INT);
        $st_conv->execute();
        $convRow = $st_conv->fetch(PDO::FETCH_ASSOC);
        
        if ($convRow) {
            $conversa_id = (int)$convRow['id'];
            // MENSAGEM ALTERADA: Agora indica que foi recusada pelo comprador
            $msg = "❌ Proposta recusada pelo comprador.";
            $sql_msg = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) VALUES (:conversa_id, :remetente_id, :mensagem, 'texto')";
            $st_msg = $conn->prepare($sql_msg);
            $st_msg->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
            $st_msg->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
            $st_msg->bindParam(':mensagem', $msg);
            $st_msg->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
        exit();
    }

    throw new Exception('Ação inválida');

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'erro' => $e->getMessage()]);
    exit();
}

?>