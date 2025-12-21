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
    
    // Verificar se o usuário tem permissão para negociar neste produto/conversa
    // E obter o ID correto do comprador na tabela compradores
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
    
    // Determinar quem é o comprador e quem é o vendedor
    $comprador_usuario_id = $verificacao['comprador_id'];
    $vendedor_usuario_id = $verificacao['vendedor_id'];
    $comprador_tabela_id = $verificacao['comprador_tabela_id'];
    $vendedor_tabela_id = $verificacao['vendedor_tabela_id'];
    $vendedor_sistema_id = $verificacao['vendedor_sistema_id'];
    
    if (!$comprador_tabela_id && $usuario_id == $comprador_usuario_id) {
        throw new Exception('Comprador não encontrado na base de dados');
    }
    
    if (!$vendedor_tabela_id && $usuario_id == $vendedor_usuario_id) {
        throw new Exception('Vendedor não encontrado na base de dados');
    }
    
    // Inserir proposta na tabela apropriada
    if ($usuario_id == $comprador_usuario_id) {
        // Proposta do comprador
        $sql_proposta = "INSERT INTO propostas_comprador 
            (comprador_id, preco_proposto, quantidade_proposta, condicoes_compra, status) 
            VALUES (:comprador_id, :preco, :quantidade, :condicoes, 'enviada')";
        
        $stmt_proposta = $conn->prepare($sql_proposta);
        $condicoes = json_encode([
            'forma_pagamento' => $dados['forma_pagamento'],
            'opcao_frete' => $dados['opcao_frete'],
            'valor_frete' => $dados['valor_frete'] ?? 0,
            'total' => $dados['total'] ?? 0,
            'produto_id' => $dados['produto_id'],
            'conversa_id' => $dados['conversa_id']
        ]);
        
        $stmt_proposta->bindParam(':comprador_id', $comprador_tabela_id, PDO::PARAM_INT);
        $stmt_proposta->bindParam(':preco', $dados['valor_unitario']);
        $stmt_proposta->bindParam(':quantidade', $dados['quantidade'], PDO::PARAM_INT);
        $stmt_proposta->bindParam(':condicoes', $condicoes);
        $stmt_proposta->execute();
        
        $proposta_id = $conn->lastInsertId();
        
        // Criar registro na tabela de negociação
        $sql_negociacao = "INSERT INTO propostas_negociacao 
            (produto_id, proposta_comprador_id, status) 
            VALUES (:produto_id, :proposta_id, 'negociacao')";
        
        $stmt_negociacao = $conn->prepare($sql_negociacao);
        $stmt_negociacao->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
        $stmt_negociacao->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
        $stmt_negociacao->execute();
        
        $negociacao_id = $conn->lastInsertId();
        
    } else {
        // Contraproposta do vendedor (se implementado no futuro)
        throw new Exception('Funcionalidade de contraproposta do vendedor ainda não implementada');
    }
    
    // Enviar notificação para o outro usuário
    $outro_usuario_id = ($usuario_id == $comprador_usuario_id) ? $vendedor_usuario_id : $comprador_usuario_id;
    
    // Buscar nome do produto para a notificação
    $sql_produto_info = "SELECT nome FROM produtos WHERE id = :produto_id";
    $stmt_prod = $conn->prepare($sql_produto_info);
    $stmt_prod->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
    $stmt_prod->execute();
    $produto_info = $stmt_prod->fetch(PDO::FETCH_ASSOC);
    $produto_nome = $produto_info['nome'] ?? 'Produto';
    
    $sql_notificacao = "INSERT INTO notificacoes 
        (usuario_id, mensagem, tipo, url) 
        VALUES (:usuario_id, :mensagem, 'info', :url)";
    
    $stmt_not = $conn->prepare($sql_notificacao);
    
    if ($usuario_id == $comprador_usuario_id) {
        $mensagem = "💰 Nova proposta para '{$produto_nome}' - Quantidade: {$dados['quantidade']}";
    } else {
        $mensagem = "🔄 Contraproposta recebida para '{$produto_nome}'";
    }
    
    $url = "../../src/chat/chat.php?produto_id=" . $dados['produto_id'] . "&conversa_id=" . $dados['conversa_id'];
    
    $stmt_not->bindParam(':usuario_id', $outro_usuario_id, PDO::PARAM_INT);
    $stmt_not->bindParam(':mensagem', $mensagem);
    $stmt_not->bindParam(':url', $url);
    $stmt_not->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Proposta enviada com sucesso',
        'proposta_id' => $proposta_id ?? null,
        'negociacao_id' => $negociacao_id ?? null
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>