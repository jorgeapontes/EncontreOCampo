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
$camposObrigatorios = ['produto_id', 'conversa_id', 'quantidade', 'valor_unitario', 'forma_pagamento', 'opcao_frete'];
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
    
    // 1. Verificar se o usuário NÃO é o vendedor do produto
    $sql_verificar_vendedor = "SELECT v.usuario_id as vendedor_usuario_id 
                              FROM produtos p
                              JOIN vendedores v ON p.vendedor_id = v.id
                              WHERE p.id = :produto_id";
                              
    $stmt_vend = $conn->prepare($sql_verificar_vendedor);
    $stmt_vend->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
    $stmt_vend->execute();
    $vendedor_info = $stmt_vend->fetch(PDO::FETCH_ASSOC);

    if ($vendedor_info && $vendedor_info['vendedor_usuario_id'] == $usuario_id) {
        throw new Exception('Você é o vendedor deste produto e não pode enviar propostas para ele');
    }
    
    // 2. Verificar se o usuário é o comprador da conversa
    $sql_verificar = "SELECT 
        c.id as conversa_id,
        c.comprador_id,
        c.vendedor_id,
        p.estoque,
        p.vendedor_id as vendedor_sistema_id
        FROM chat_conversas c
        JOIN produtos p ON c.produto_id = p.id
        WHERE c.id = :conversa_id 
        AND p.id = :produto_id
        AND c.comprador_id = :usuario_id";
    
    $stmt = $conn->prepare($sql_verificar);
    $stmt->bindParam(':conversa_id', $dados['conversa_id'], PDO::PARAM_INT);
    $stmt->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $verificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verificacao) {
        throw new Exception('Apenas o comprador da conversa pode enviar propostas');
    }
    
    // 3. Verificar estoque
    if ($dados['quantidade'] > $verificacao['estoque']) {
        throw new Exception('Quantidade solicitada excede o estoque disponível');
    }
    
    // 4. Preparar valores para inserção/atualização na tabela propostas
    $comprador_id = $verificacao['comprador_id'];
    $vendedor_id = $verificacao['vendedor_id'];
    $produto_id = $dados['produto_id'];
    
    // Converter valores para o formato correto
    $preco_proposto = (float) $dados['valor_unitario'];
    $quantidade_proposta = (int) $dados['quantidade'];
    $valor_frete = isset($dados['valor_frete']) ? (float) $dados['valor_frete'] : 0.00;
    
    // Calcular valor total
    $valor_total = ($preco_proposto * $quantidade_proposta) + $valor_frete;
    
    // Mapear forma de pagamento do formulário para o enum da tabela
    $forma_pagamento_mapa = [
        'pagamento_ato' => 'à vista',
        'pagamento_entrega' => 'entrega'
    ];
    
    $forma_pagamento = isset($forma_pagamento_mapa[$dados['forma_pagamento']]) 
        ? $forma_pagamento_mapa[$dados['forma_pagamento']] 
        : 'à vista';
    
    // Mapear opção de frete do formulário para o enum da tabela
    $opcao_frete_mapa = [
        'frete_vendedor' => 'vendedor',
        'retirada_comprador' => 'comprador',
        'buscar_transportador' => 'entregador'
    ];
    
    $opcao_frete = isset($opcao_frete_mapa[$dados['opcao_frete']]) 
        ? $opcao_frete_mapa[$dados['opcao_frete']] 
        : 'vendedor';
    
    // 5. Verificar se já existe uma proposta em negociação para este produto/usuários
    $sql_existe = "SELECT ID FROM propostas 
                  WHERE produto_id = :produto_id 
                  AND comprador_id = :comprador_id 
                  AND vendedor_id = :vendedor_id
                  AND status = 'negociacao'";
    
    $stmt_existe = $conn->prepare($sql_existe);
    $stmt_existe->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_existe->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_existe->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_existe->execute();
    $proposta_existente = $stmt_existe->fetch(PDO::FETCH_ASSOC);
    
    if ($proposta_existente) {
        // ATUALIZAR proposta existente
        $sql_atualizar = "UPDATE propostas SET 
            preco_proposto = :preco_proposto,
            quantidade_proposta = :quantidade_proposta,
            forma_pagamento = :forma_pagamento,
            opcao_frete = :opcao_frete,
            valor_frete = :valor_frete,
            valor_total = :valor_total,
            status = 'negociacao',
            data_atualizacao = NOW()
            WHERE ID = :proposta_id";
        
        $stmt_atualizar = $conn->prepare($sql_atualizar);
        $stmt_atualizar->bindParam(':proposta_id', $proposta_existente['ID'], PDO::PARAM_INT);
        $stmt_atualizar->bindParam(':preco_proposto', $preco_proposto);
        $stmt_atualizar->bindParam(':quantidade_proposta', $quantidade_proposta, PDO::PARAM_INT);
        $stmt_atualizar->bindParam(':forma_pagamento', $forma_pagamento);
        $stmt_atualizar->bindParam(':opcao_frete', $opcao_frete);
        $stmt_atualizar->bindParam(':valor_frete', $valor_frete);
        $stmt_atualizar->bindParam(':valor_total', $valor_total);
        
        if (!$stmt_atualizar->execute()) {
            throw new Exception('Erro ao atualizar proposta no banco de dados');
        }
        
        $proposta_id = $proposta_existente['ID'];
        $acao = 'atualizada';
    } else {
        // INSERIR nova proposta
        $sql_inserir = "INSERT INTO propostas 
            (comprador_id, vendedor_id, produto_id, preco_proposto, quantidade_proposta, 
             forma_pagamento, opcao_frete, valor_frete, valor_total, status, data_inicio, data_atualizacao) 
            VALUES (:comprador_id, :vendedor_id, :produto_id, :preco_proposto, :quantidade_proposta,
                    :forma_pagamento, :opcao_frete, :valor_frete, :valor_total, 'negociacao', NOW(), NOW())";
        
        $stmt_inserir = $conn->prepare($sql_inserir);
        $stmt_inserir->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
        $stmt_inserir->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
        $stmt_inserir->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_inserir->bindParam(':preco_proposto', $preco_proposto);
        $stmt_inserir->bindParam(':quantidade_proposta', $quantidade_proposta, PDO::PARAM_INT);
        $stmt_inserir->bindParam(':forma_pagamento', $forma_pagamento);
        $stmt_inserir->bindParam(':opcao_frete', $opcao_frete);
        $stmt_inserir->bindParam(':valor_frete', $valor_frete);
        $stmt_inserir->bindParam(':valor_total', $valor_total);
        
        if (!$stmt_inserir->execute()) {
            throw new Exception('Erro ao salvar proposta no banco de dados');
        }
        
        $proposta_id = $conn->lastInsertId();
        $acao = 'enviada';
    }
    
    // 6. Enviar notificação para o vendedor
    $sql_produto_info = "SELECT nome FROM produtos WHERE id = :produto_id";
    $stmt_prod = $conn->prepare($sql_produto_info);
    $stmt_prod->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_prod->execute();
    $produto_info = $stmt_prod->fetch(PDO::FETCH_ASSOC);
    $produto_nome = $produto_info['nome'] ?? 'Produto';
    
    $sql_notificacao = "INSERT INTO notificacoes 
        (usuario_id, mensagem, tipo, url) 
        VALUES (:usuario_id, :mensagem, 'info', :url)";
    
    $stmt_not = $conn->prepare($sql_notificacao);
    
    $mensagem = $acao === 'enviada' 
        ? "Nova proposta para '{$produto_nome}' - Quantidade: {$quantidade_proposta} unidades" 
        : "Proposta atualizada para '{$produto_nome}' - Quantidade: {$quantidade_proposta} unidades";
    
    $url = "../../src/chat/chat.php?produto_id=" . $produto_id . "&conversa_id=" . $dados['conversa_id'];
    
    $stmt_not->bindParam(':usuario_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_not->bindParam(':mensagem', $mensagem);
    $stmt_not->bindParam(':url', $url);
    $stmt_not->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Proposta ' . $acao . ' com sucesso',
        'proposta_id' => $proposta_id,
        'acao' => $acao
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>