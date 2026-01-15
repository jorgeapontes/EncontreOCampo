<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit();
}

$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

$camposObrigatorios = ['produto_id', 'conversa_id', 'quantidade', 'preco_proposto', 'forma_pagamento', 'opcao_frete', 'usuario_tipo'];
foreach ($camposObrigatorios as $campo) {
    if (!isset($dados[$campo]) || empty($dados[$campo])) {
        echo json_encode(['success' => false, 'error' => "Campo obrigatório faltando: {$campo}"]);
        exit();
    }
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();
    
    $sql_verificar = "SELECT 
        c.id as conversa_id,
        c.comprador_id,
        c.vendedor_id,
        p.estoque,
        p.nome as produto_nome,
        uc.nome as comprador_nome,
        uv.nome as vendedor_nome,
        u.nome as usuario_nome
        FROM chat_conversas c
        JOIN produtos p ON c.produto_id = p.id
        JOIN usuarios u ON u.id = :usuario_id
        JOIN usuarios uc ON uc.id = c.comprador_id
        JOIN usuarios uv ON uv.id = c.vendedor_id
        WHERE c.id = :conversa_id 
        AND p.id = :produto_id
        AND (c.comprador_id = :usuario_id OR c.vendedor_id = :usuario_id)";
    
    $stmt = $conn->prepare($sql_verificar);
    $stmt->bindParam(':conversa_id', $dados['conversa_id'], PDO::PARAM_INT);
    $stmt->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $verificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verificacao) {
        throw new Exception('Você não tem permissão para negociar nesta conversa/produto');
    }
    
    if ($dados['quantidade'] > $verificacao['estoque']) {
        throw new Exception('Quantidade solicitada excede o estoque disponível');
    }
    
    $comprador_usuario_id = $verificacao['comprador_id'];
    $vendedor_usuario_id = $verificacao['vendedor_id'];
    
    $eh_comprador = ($usuario_id == $comprador_usuario_id);
    $eh_vendedor = ($usuario_id == $vendedor_usuario_id);
    
    if (!$eh_comprador && !$eh_vendedor) {
        throw new Exception('Usuário não é parte desta conversa');
    }
    
    // Preparar valores
    $valor_frete = isset($dados['valor_frete']) ? floatval($dados['valor_frete']) : 0;
    $preco_proposto = floatval($dados['preco_proposto']);
    $quantidade = intval($dados['quantidade']);
    $total = ($quantidade * $preco_proposto) + $valor_frete;
    
    // Determinar nome de quem enviou
    $enviado_por = $eh_comprador ? $verificacao['comprador_nome'] : $verificacao['vendedor_nome'];
    if (empty($enviado_por)) {
        $enviado_por = $verificacao['usuario_nome'];
    }
    
    // ========== LÓGICA DE VINCULAÇÃO DE PROPOSTAS ==========
    
    // Verificar se já existe uma negociação para este produto entre estes usuários
    $sql_busca_negociacao = "SELECT 
        pn.ID as negociacao_id,
        pn.proposta_comprador_id,
        pn.proposta_vendedor_id,
        pn.status,
        pc.ID as proposta_comprador_existente,
        pv.ID as proposta_vendedor_existente
        FROM propostas_negociacao pn
        LEFT JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.ID 
            AND pc.comprador_id = :comprador_id 
            AND pc.produto_id = :produto_id
        LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.ID 
            AND pv.vendedor_id = :vendedor_id 
            AND pv.produto_id = :produto_id2
        WHERE pn.produto_id = :produto_id3
        AND (
            (pn.proposta_comprador_id IS NOT NULL AND pc.ID IS NOT NULL)
            OR (pn.proposta_vendedor_id IS NOT NULL AND pv.ID IS NOT NULL)
            OR (pn.proposta_comprador_id IS NULL AND pn.proposta_vendedor_id IS NULL)
        )
        ORDER BY pn.data_atualizacao DESC
        LIMIT 1";
    
    $stmt_busca = $conn->prepare($sql_busca_negociacao);
    $stmt_busca->bindParam(':comprador_id', $comprador_usuario_id, PDO::PARAM_INT);
    $stmt_busca->bindParam(':vendedor_id', $vendedor_usuario_id, PDO::PARAM_INT);
    $stmt_busca->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
    $stmt_busca->bindParam(':produto_id2', $dados['produto_id'], PDO::PARAM_INT);
    $stmt_busca->bindParam(':produto_id3', $dados['produto_id'], PDO::PARAM_INT);
    $stmt_busca->execute();
    $negociacao_existente = $stmt_busca->fetch(PDO::FETCH_ASSOC);
    
    $negociacao_id = null;
    $proposta_id = null;
    
    if ($eh_comprador) {
        // ========== PROPOSTA DO COMPRADOR ==========
        
        // Verificar se já existe proposta do comprador
        $sql_check_proposta = "SELECT ID FROM propostas_comprador 
            WHERE comprador_id = :comprador_id 
            AND produto_id = :produto_id";
        
        $stmt_check = $conn->prepare($sql_check_proposta);
        $stmt_check->bindParam(':comprador_id', $comprador_usuario_id, PDO::PARAM_INT);
        $stmt_check->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        $stmt_check->execute();
        $proposta_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($proposta_existente) {
            // Atualizar proposta existente
            $sql_proposta = "UPDATE propostas_comprador SET
                preco_proposto = :preco,
                quantidade_proposta = :quantidade,
                data_proposta = NOW(),
                status = 'pendente',
                forma_pagamento = :forma_pagamento,
                opcao_frete = :opcao_frete,
                valor_frete = :valor_frete
                WHERE ID = :proposta_id";
            
            $stmt_proposta = $conn->prepare($sql_proposta);
            $stmt_proposta->bindParam(':proposta_id', $proposta_existente['ID'], PDO::PARAM_INT);
        } else {
            // Inserir nova proposta
            $sql_proposta = "INSERT INTO propostas_comprador 
                (comprador_id, produto_id, preco_proposto, quantidade_proposta, 
                data_proposta, status, forma_pagamento, opcao_frete, valor_frete) 
                VALUES (:comprador_id, :produto_id, :preco, :quantidade, 
                        NOW(), 'pendente', :forma_pagamento, :opcao_frete, :valor_frete)";
            
            $stmt_proposta = $conn->prepare($sql_proposta);
            $stmt_proposta->bindParam(':comprador_id', $comprador_usuario_id, PDO::PARAM_INT);
            $stmt_proposta->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        }
        
        $stmt_proposta->bindParam(':preco', $preco_proposto);
        $stmt_proposta->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
        $stmt_proposta->bindParam(':forma_pagamento', $dados['forma_pagamento']);
        $stmt_proposta->bindParam(':opcao_frete', $dados['opcao_frete']);
        $stmt_proposta->bindParam(':valor_frete', $valor_frete);
        $stmt_proposta->execute();
        
        $proposta_id = $proposta_existente ? $proposta_existente['ID'] : $conn->lastInsertId();
        
        // Gerenciar a negociação
        if ($negociacao_existente) {
            // Atualizar negociação existente
            $sql_negociacao = "UPDATE propostas_negociacao SET
                proposta_comprador_id = :proposta_comprador_id,
                data_atualizacao = NOW(),
                valor_total = :valor_total,
                quantidade_final = :quantidade,
                status = 'pendente',
                forma_pagamento = :forma_pagamento,
                opcao_frete = :opcao_frete
                WHERE ID = :negociacao_id";
            
            $stmt_negociacao = $conn->prepare($sql_negociacao);
            $stmt_negociacao->bindParam(':negociacao_id', $negociacao_existente['negociacao_id'], PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':proposta_comprador_id', $proposta_id, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':valor_total', $total);
            $stmt_negociacao->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':forma_pagamento', $dados['forma_pagamento']);
            $stmt_negociacao->bindParam(':opcao_frete', $dados['opcao_frete']);
            $stmt_negociacao->execute();
            
            $negociacao_id = $negociacao_existente['negociacao_id'];
        } else {
            // Criar nova negociação
            $sql_negociacao = "INSERT INTO propostas_negociacao 
                (proposta_comprador_id, produto_id, data_inicio, data_atualizacao, 
                valor_total, quantidade_final, status, forma_pagamento, opcao_frete) 
                VALUES (:proposta_comprador_id, :produto_id, NOW(), NOW(), 
                        :valor_total, :quantidade, 'pendente', :forma_pagamento, :opcao_frete)";
            
            $stmt_negociacao = $conn->prepare($sql_negociacao);
            $stmt_negociacao->bindParam(':proposta_comprador_id', $proposta_id, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':valor_total', $total);
            $stmt_negociacao->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':forma_pagamento', $dados['forma_pagamento']);
            $stmt_negociacao->bindParam(':opcao_frete', $dados['opcao_frete']);
            $stmt_negociacao->execute();
            
            $negociacao_id = $conn->lastInsertId();
        }
        
    } elseif ($eh_vendedor) {
        // ========== PROPOSTA DO VENDEDOR ==========
        
        // Verificar se já existe proposta do vendedor
        $sql_check_proposta = "SELECT ID FROM propostas_vendedor 
            WHERE vendedor_id = :vendedor_id 
            AND produto_id = :produto_id";
        
        $stmt_check = $conn->prepare($sql_check_proposta);
        $stmt_check->bindParam(':vendedor_id', $vendedor_usuario_id, PDO::PARAM_INT);
        $stmt_check->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        $stmt_check->execute();
        $proposta_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($proposta_existente) {
            // Atualizar proposta existente
            $sql_proposta = "UPDATE propostas_vendedor SET
                preco_proposto = :preco,
                quantidade_proposta = :quantidade,
                data_proposta = NOW(),
                status = 'pendente',
                forma_pagamento = :forma_pagamento,
                opcao_frete = :opcao_frete,
                valor_frete = :valor_frete
                WHERE ID = :proposta_id";
            
            $stmt_proposta = $conn->prepare($sql_proposta);
            $stmt_proposta->bindParam(':proposta_id', $proposta_existente['ID'], PDO::PARAM_INT);
        } else {
            // Inserir nova proposta
            $sql_proposta = "INSERT INTO propostas_vendedor 
                (vendedor_id, produto_id, preco_proposto, quantidade_proposta, 
                data_proposta, status, forma_pagamento, opcao_frete, valor_frete) 
                VALUES (:vendedor_id, :produto_id, :preco, :quantidade, 
                        NOW(), 'pendente', :forma_pagamento, :opcao_frete, :valor_frete)";
            
            $stmt_proposta = $conn->prepare($sql_proposta);
            $stmt_proposta->bindParam(':vendedor_id', $vendedor_usuario_id, PDO::PARAM_INT);
            $stmt_proposta->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        }
        
        $stmt_proposta->bindParam(':preco', $preco_proposto);
        $stmt_proposta->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
        $stmt_proposta->bindParam(':forma_pagamento', $dados['forma_pagamento']);
        $stmt_proposta->bindParam(':opcao_frete', $dados['opcao_frete']);
        $stmt_proposta->bindParam(':valor_frete', $valor_frete);
        $stmt_proposta->execute();
        
        $proposta_id = $proposta_existente ? $proposta_existente['ID'] : $conn->lastInsertId();
        
        // Gerenciar a negociação
        if ($negociacao_existente) {
            // Atualizar negociação existente
            $sql_negociacao = "UPDATE propostas_negociacao SET
                proposta_vendedor_id = :proposta_vendedor_id,
                data_atualizacao = NOW(),
                valor_total = :valor_total,
                quantidade_final = :quantidade,
                status = 'pendente',
                forma_pagamento = :forma_pagamento,
                opcao_frete = :opcao_frete
                WHERE ID = :negociacao_id";
            
            $stmt_negociacao = $conn->prepare($sql_negociacao);
            $stmt_negociacao->bindParam(':negociacao_id', $negociacao_existente['negociacao_id'], PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':proposta_vendedor_id', $proposta_id, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':valor_total', $total);
            $stmt_negociacao->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':forma_pagamento', $dados['forma_pagamento']);
            $stmt_negociacao->bindParam(':opcao_frete', $dados['opcao_frete']);
            $stmt_negociacao->execute();
            
            $negociacao_id = $negociacao_existente['negociacao_id'];
        } else {
            // Criar nova negociação
            $sql_negociacao = "INSERT INTO propostas_negociacao 
                (proposta_vendedor_id, produto_id, data_inicio, data_atualizacao, 
                valor_total, quantidade_final, status, forma_pagamento, opcao_frete) 
                VALUES (:proposta_vendedor_id, :produto_id, NOW(), NOW(), 
                        :valor_total, :quantidade, 'pendente', :forma_pagamento, :opcao_frete)";
            
            $stmt_negociacao = $conn->prepare($sql_negociacao);
            $stmt_negociacao->bindParam(':proposta_vendedor_id', $proposta_id, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':valor_total', $total);
            $stmt_negociacao->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':forma_pagamento', $dados['forma_pagamento']);
            $stmt_negociacao->bindParam(':opcao_frete', $dados['opcao_frete']);
            $stmt_negociacao->execute();
            
            $negociacao_id = $conn->lastInsertId();
        }
    }
    
    // ========== PREPARAR DADOS PARA O CARD ==========
    
    // Buscar informações completas da negociação
    $sql_info_negociacao = "SELECT 
        pn.*,
        pc.preco_proposto as preco_comprador,
        pv.preco_proposto as preco_vendedor,
        COALESCE(pc.preco_proposto, pv.preco_proposto) as preco_final,
        COALESCE(pc.quantidade_proposta, pv.quantidade_proposta) as quantidade_final,
        COALESCE(pc.forma_pagamento, pv.forma_pagamento) as forma_pagamento_final,
        COALESCE(pc.opcao_frete, pv.opcao_frete) as opcao_frete_final,
        COALESCE(pc.valor_frete, pv.valor_frete) as valor_frete_final
        FROM propostas_negociacao pn
        LEFT JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.ID
        LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.ID
        WHERE pn.ID = :negociacao_id";
    
    $stmt_info = $conn->prepare($sql_info_negociacao);
    $stmt_info->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt_info->execute();
    $info_negociacao = $stmt_info->fetch(PDO::FETCH_ASSOC);
    
    // Usar os dados finais da negociação (que podem ser de qualquer uma das propostas)
    $preco_final = $info_negociacao['preco_final'] ?? $preco_proposto;
    $quantidade_final = $info_negociacao['quantidade_final'] ?? $quantidade;
    $forma_pagamento_final = $info_negociacao['forma_pagamento_final'] ?? $dados['forma_pagamento'];
    $opcao_frete_final = $info_negociacao['opcao_frete_final'] ?? $dados['opcao_frete'];
    $valor_frete_final = $info_negociacao['valor_frete_final'] ?? $valor_frete;
    $total_final = ($quantidade_final * $preco_final) + $valor_frete_final;
    
    // Preparar dados para o card
    $dados_card = [
        'negociacao_id' => $negociacao_id,
        'proposta_id' => $proposta_id,
        'produto_id' => $dados['produto_id'],
        'produto_nome' => $verificacao['produto_nome'],
        'quantidade' => $quantidade_final,
        'preco_proposto' => $preco_final,
        'valor_frete' => $valor_frete_final,
        'total' => $total_final,
        'forma_pagamento' => $forma_pagamento_final,
        'opcao_frete' => $opcao_frete_final,
        'enviado_por' => $enviado_por,
        'status' => 'pendente',
        'tipo_remetente' => $eh_comprador ? 'comprador' : 'vendedor',
        'tem_proposta_comprador' => !empty($info_negociacao['proposta_comprador_id']),
        'tem_proposta_vendedor' => !empty($info_negociacao['proposta_vendedor_id'])
    ];
    
    // Mensagem que aparecerá no chat
    // Gerar mensagem descritiva para o chat
    $preco_formatado = number_format($preco_final, 2, ',', '.');
    $total_formatado = number_format($total_final, 2, ',', '.');
    $valor_frete_formatado = number_format($valor_frete_final, 2, ',', '.');

    // Formatar forma de pagamento
    $forma_pagamento_texto = $forma_pagamento_final === 'à vista' ? 'À Vista' : 'Na Entrega';

    // Formatar opção de frete
    $opcao_frete_texto = 'Frete por conta do ';
    switch ($opcao_frete_final) {
        case 'vendedor':
            $opcao_frete_texto .= 'vendedor';
            if ($valor_frete_final > 0) {
                $opcao_frete_texto .= " (R$ {$valor_frete_formatado})";
            } else {
                $opcao_frete_texto .= " (gratuito)";
            }
            break;
        case 'comprador':
            $opcao_frete_texto = 'Retirada pelo comprador';
            break;
        case 'entregador':
            $opcao_frete_texto = 'Buscar transportador pela plataforma';
            break;
        default:
            $opcao_frete_texto .= 'vendedor';
    }

    $mensagem_chat = "** Acordo de Compra Enviado **\n\n";
    $mensagem_chat .= "Produto: {$verificacao['produto_nome']}\n";
    $mensagem_chat .= "Quantidade: {$quantidade_final} unidades\n";
    $mensagem_chat .= "Valor Unitário: R$ {$preco_formatado}\n";
    $mensagem_chat .= "Forma de Pagamento: {$forma_pagamento_texto}\n";
    $mensagem_chat .= "Frete/Entrega: {$opcao_frete_texto}\n";
    $mensagem_chat .= "Valor Total: R$ {$total_formatado}\n";
    $mensagem_chat .= "Status: Pendente de resposta\n";

    $dados_json = null;
    
    // Inserir mensagem no chat
    $sql_insert_msg = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo, dados_json) 
                      VALUES (:conversa_id, :remetente_id, :mensagem, 'negociacao', :dados_json)";
    
    $stmt_insert_msg = $conn->prepare($sql_insert_msg);
    $stmt_insert_msg->bindParam(':conversa_id', $dados['conversa_id'], PDO::PARAM_INT);
    $stmt_insert_msg->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
    $stmt_insert_msg->bindParam(':mensagem', $mensagem_chat);
    $stmt_insert_msg->bindParam(':dados_json', $dados_json);
    $stmt_insert_msg->execute();
    
    $mensagem_id = $conn->lastInsertId();
    
    // Adicionar mensagem_id aos dados do card
    $dados_card['mensagem_id'] = $mensagem_id;
    $dados_json_updated = json_encode($dados_card);
    
    // Atualizar com o message_id
    $sql_update_msg = "UPDATE chat_mensagens SET dados_json = :dados_json WHERE id = :mensagem_id";
    $stmt_update_msg = $conn->prepare($sql_update_msg);
    $stmt_update_msg->bindParam(':dados_json', $dados_json_updated);
    $stmt_update_msg->bindParam(':mensagem_id', $mensagem_id, PDO::PARAM_INT);
    $stmt_update_msg->execute();
    
    // Atualizar conversa
    $sql_update_conv = "UPDATE chat_conversas 
                       SET ultima_mensagem = '[Acordo de Compra]',
                           ultima_mensagem_data = NOW(),
                           comprador_lido = IF(comprador_id = :usuario_id, 1, 0),
                           vendedor_lido = IF(vendedor_id = :usuario_id, 1, 0)
                       WHERE id = :conversa_id";
    
    $stmt_update = $conn->prepare($sql_update_conv);
    $stmt_update->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_update->bindParam(':conversa_id', $dados['conversa_id'], PDO::PARAM_INT);
    $stmt_update->execute();
    
    // Enviar notificação para o outro usuário
    $outro_usuario_id = $eh_comprador ? $verificacao['vendedor_id'] : $verificacao['comprador_id'];
    
    $sql_notificacao = "INSERT INTO notificacoes 
        (usuario_id, mensagem, tipo, url) 
        VALUES (:usuario_id, :mensagem, 'info', :url)";
    
    $stmt_not = $conn->prepare($sql_notificacao);
    
    $mensagem = "Nova proposta recebida para '{$verificacao['produto_nome']}'";
    $url = "../../src/chat/chat.php?produto_id=" . $dados['produto_id'] . "&conversa_id=" . $dados['conversa_id'];
    
    $stmt_not->bindParam(':usuario_id', $outro_usuario_id, PDO::PARAM_INT);
    $stmt_not->bindParam(':mensagem', $mensagem);
    $stmt_not->bindParam(':url', $url);
    $stmt_not->execute();
    
    $conn->commit();
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Proposta enviada com sucesso',
        'proposta_id' => $proposta_id,
        'negociacao_id' => $negociacao_id,
        'mensagem_id' => $mensagem_id,
        'tipo' => $eh_comprador ? 'comprador' : 'vendedor',
        'dados_card' => $dados_card
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Erro ao salvar negociação: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>