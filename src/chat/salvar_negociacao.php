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

if (!$dados) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

// Validar dados obrigatórios
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
    
    // Verificar permissões e obter informações
    $sql_verificar = "SELECT 
        c.id as conversa_id,
        c.comprador_id,
        c.vendedor_id,
        p.estoque,
        p.vendedor_id as vendedor_sistema_id,
        comp.id as comprador_tabela_id,
        vend.id as vendedor_tabela_id
        FROM chat_conversas c
        JOIN produtos p ON c.produto_id = p.id
        LEFT JOIN compradores comp ON comp.usuario_id = c.comprador_id
        LEFT JOIN vendedores vend ON vend.usuario_id = c.vendedor_id
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
    
    // Verificar estoque
    if ($dados['quantidade'] > $verificacao['estoque']) {
        throw new Exception('Quantidade solicitada excede o estoque disponível');
    }
    
    // Determinar IDs corretos - AGORA USANDO compradores(id) e vendedores(id)
    $comprador_usuario_id  = $verificacao['comprador_id']; // compradores(id)
    $vendedor_usuario_id = $verificacao['vendedor_id'];   // vendedores(id)
    
    // Verificar se o usuário é comprador ou vendedor
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
    
    // Inserir proposta na tabela apropriada
    if ($eh_comprador) {
        // ========== PROPOSTA DO COMPRADOR ==========
        // Verificar se já existe proposta do comprador para este produto
        $sql_check_existente = "SELECT ID FROM propostas_comprador 
            WHERE comprador_id = :comprador_id 
            AND produto_id = :produto_id";
        
        $stmt_check = $conn->prepare($sql_check_existente);
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
                status = 'enviada',
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
                        NOW(), 'enviada', :forma_pagamento, :opcao_frete, :valor_frete)";
            
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
        
        // Criar ou atualizar registro na tabela de negociação
        $sql_check_negociacao = "SELECT ID FROM propostas_negociacao 
            WHERE proposta_comprador_id = :proposta_id 
            AND produto_id = :produto_id";
        
        $stmt_check_neg = $conn->prepare($sql_check_negociacao);
        $stmt_check_neg->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
        $stmt_check_neg->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        $stmt_check_neg->execute();
        $negociacao_existente = $stmt_check_neg->fetch(PDO::FETCH_ASSOC);
        
        if ($negociacao_existente) {
            $sql_negociacao = "UPDATE propostas_negociacao SET
                data_atualizacao = NOW(),
                valor_total = :valor_total,
                quantidade_final = :quantidade,
                status = 'negociacao',
                forma_pagamento = :forma_pagamento,
                opcao_frete = :opcao_frete
                WHERE ID = :negociacao_id";
            
            $stmt_negociacao = $conn->prepare($sql_negociacao);
            $stmt_negociacao->bindParam(':negociacao_id', $negociacao_existente['ID'], PDO::PARAM_INT);
        } else {
            $sql_negociacao = "INSERT INTO propostas_negociacao 
                (proposta_comprador_id, produto_id, data_inicio, data_atualizacao, 
                 valor_total, quantidade_final, status, forma_pagamento, opcao_frete) 
                VALUES (:proposta_comprador_id, :produto_id, NOW(), NOW(), 
                        :valor_total, :quantidade, 'negociacao', :forma_pagamento, :opcao_frete)";
            
            $stmt_negociacao = $conn->prepare($sql_negociacao);
            $stmt_negociacao->bindParam(':proposta_comprador_id', $proposta_id, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        }
        
        $stmt_negociacao->bindParam(':valor_total', $total);
        $stmt_negociacao->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
        $stmt_negociacao->bindParam(':forma_pagamento', $dados['forma_pagamento']);
        $stmt_negociacao->bindParam(':opcao_frete', $dados['opcao_frete']);
        $stmt_negociacao->execute();
        
        $negociacao_id = $negociacao_existente ? $negociacao_existente['ID'] : $conn->lastInsertId();
        
    } elseif ($eh_vendedor) {
        // ========== PROPOSTA DO VENDEDOR ==========
        // Verificar se já existe proposta do vendedor para este produto
        $sql_check_existente = "SELECT ID FROM propostas_vendedor 
            WHERE vendedor_id = :vendedor_id 
            AND produto_id = :produto_id";
        
        $stmt_check = $conn->prepare($sql_check_existente);
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
                status = 'enviada',
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
                        NOW(), 'enviada', :forma_pagamento, :opcao_frete, :valor_frete)";
            
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
        
        // Buscar proposta do comprador para vincular (se existir)
        $sql_busca_proposta_comprador = "SELECT pc.ID 
            FROM propostas_comprador pc
            WHERE pc.comprador_id = :comprador_id
            AND pc.produto_id = :produto_id
            AND pc.status = 'enviada'
            ORDER BY pc.data_proposta DESC LIMIT 1";
        
        $stmt_busca = $conn->prepare($sql_busca_proposta_comprador);
        $stmt_busca->bindParam(':comprador_id', $comprador_usuario_id, PDO::PARAM_INT);
        $stmt_busca->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        $stmt_busca->execute();
        $proposta_comprador_existente = $stmt_busca->fetch(PDO::FETCH_ASSOC);
        
        // Verificar se já existe negociação para este produto entre estes usuários
        $sql_check_negociacao = "SELECT ID FROM propostas_negociacao 
            WHERE produto_id = :produto_id
            AND (
                (proposta_comprador_id IS NOT NULL AND proposta_comprador_id = :proposta_comprador_id)
                OR 
                (proposta_vendedor_id IS NOT NULL AND proposta_vendedor_id = :proposta_vendedor_id)
                OR
                (
                    proposta_comprador_id IN (SELECT ID FROM propostas_comprador WHERE comprador_id = :comprador_id AND produto_id = :produto_id2)
                    AND proposta_vendedor_id IN (SELECT ID FROM propostas_vendedor WHERE vendedor_id = :vendedor_id2 AND produto_id = :produto_id3)
                )
            )";
        
        $stmt_check_neg = $conn->prepare($sql_check_negociacao);
        $stmt_check_neg->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        $stmt_check_neg->bindValue(':proposta_comprador_id', $proposta_comprador_existente ? $proposta_comprador_existente['ID'] : null, PDO::PARAM_INT);
        $stmt_check_neg->bindParam(':proposta_vendedor_id', $proposta_id, PDO::PARAM_INT);
        $stmt_check_neg->bindParam(':comprador_id', $comprador_usuario_id, PDO::PARAM_INT);
        $stmt_check_neg->bindParam(':vendedor_id2', $vendedor_usuario_id, PDO::PARAM_INT);
        $stmt_check_neg->bindParam(':produto_id2', $dados['produto_id'], PDO::PARAM_INT);
        $stmt_check_neg->bindParam(':produto_id3', $dados['produto_id'], PDO::PARAM_INT);
        $stmt_check_neg->execute();
        $negociacao_existente = $stmt_check_neg->fetch(PDO::FETCH_ASSOC);
        
        if ($negociacao_existente) {
            // Atualizar negociação existente
            $sql_negociacao = "UPDATE propostas_negociacao SET
                proposta_vendedor_id = :proposta_vendedor_id,
                data_atualizacao = NOW(),
                valor_total = :valor_total,
                quantidade_final = :quantidade,
                status = 'negociacao',
                forma_pagamento = :forma_pagamento,
                opcao_frete = :opcao_frete";
            
            // Se existir proposta do comprador, atualizar o campo também
            if ($proposta_comprador_existente) {
                $sql_negociacao .= ", proposta_comprador_id = :proposta_comprador_id";
            }
            
            $sql_negociacao .= " WHERE ID = :negociacao_id";
            
            $stmt_negociacao = $conn->prepare($sql_negociacao);
            $stmt_negociacao->bindParam(':negociacao_id', $negociacao_existente['ID'], PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':proposta_vendedor_id', $proposta_id, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':valor_total', $total);
            $stmt_negociacao->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
            $stmt_negociacao->bindParam(':forma_pagamento', $dados['forma_pagamento']);
            $stmt_negociacao->bindParam(':opcao_frete', $dados['opcao_frete']);
            
            if ($proposta_comprador_existente) {
                $stmt_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_existente['ID'], PDO::PARAM_INT);
            }
            
        } else {
            // Criar nova negociação
            $sql_negociacao = "INSERT INTO propostas_negociacao 
                (proposta_vendedor_id, produto_id, data_inicio, data_atualizacao, 
                valor_total, quantidade_final, status, forma_pagamento, opcao_frete";
            
            $sql_valores = " VALUES (:proposta_vendedor_id, :produto_id, NOW(), NOW(), 
                        :valor_total, :quantidade, 'negociacao', :forma_pagamento, :opcao_frete";
            
            $params = [
                ':proposta_vendedor_id' => $proposta_id,
                ':produto_id' => $dados['produto_id'],
                ':valor_total' => $total,
                ':quantidade' => $quantidade,
                ':forma_pagamento' => $dados['forma_pagamento'],
                ':opcao_frete' => $dados['opcao_frete']
            ];
            
            // Se existir proposta do comprador, vincular
            if ($proposta_comprador_existente) {
                $sql_negociacao .= ", proposta_comprador_id";
                $sql_valores .= ", :proposta_comprador_id";
                $params[':proposta_comprador_id'] = $proposta_comprador_existente['ID'];
            }
            
            $sql_negociacao .= ")" . $sql_valores . ")";
            
            $stmt_negociacao = $conn->prepare($sql_negociacao);
            
            foreach ($params as $key => $value) {
                $stmt_negociacao->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        
        $stmt_negociacao->execute();
        $negociacao_id = $negociacao_existente ? $negociacao_existente['ID'] : $conn->lastInsertId();
    }
    
    // Enviar notificação para o outro usuário
    $outro_usuario_id = $eh_comprador ? $verificacao['vendedor_id'] : $verificacao['comprador_id'];
    
    // Buscar nome do produto
    $sql_produto_info = "SELECT nome FROM produtos WHERE id = :produto_id";
    $stmt_prod = $conn->prepare($sql_produto_info);
    $stmt_prod->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
    $stmt_prod->execute();
    $produto_info = $stmt_prod->fetch(PDO::FETCH_ASSOC);
    $produto_nome = $produto_info['nome'] ?? 'Produto';
    
    // Inserir notificação
    $sql_notificacao = "INSERT INTO notificacoes 
        (usuario_id, mensagem, tipo, url) 
        VALUES (:usuario_id, :mensagem, 'info', :url)";
    
    $stmt_not = $conn->prepare($sql_notificacao);
    
    $mensagem = $eh_comprador 
        ? "Nova proposta para '{$produto_nome}' - Quantidade: {$dados['quantidade']}" 
        : "Proposta recebida para '{$produto_nome}'";
    
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
        'tipo' => $eh_comprador ? 'comprador' : 'vendedor'
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