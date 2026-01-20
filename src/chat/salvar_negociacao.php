<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

// VERIFICAR SE É COMPRADOR
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    echo json_encode(['success' => false, 'error' => 'Apenas compradores podem enviar propostas']);
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
    
    // 1. Verificar se o usuário tem permissão para negociar neste produto/conversa
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
        AND c.comprador_id = :usuario_id"; // Só permite se for o comprador da conversa
    
    $stmt = $conn->prepare($sql_verificar);
    $stmt->bindParam(':conversa_id', $dados['conversa_id'], PDO::PARAM_INT);
    $stmt->bindParam(':produto_id', $dados['produto_id'], PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $verificacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verificacao) {
        throw new Exception('Apenas o comprador da conversa pode enviar propostas');
    }
    
    // 2. Verificar estoque
    if ($dados['quantidade'] > $verificacao['estoque']) {
        throw new Exception('Quantidade solicitada excede o estoque disponível');
    }
    
    // 3. Preparar valores para inserção na tabela propostas
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
    
    // 4. Inserir na tabela propostas (CORRIGIDO)
    $sql_proposta = "INSERT INTO propostas 
        (comprador_id, vendedor_id, produto_id, preco_proposto, quantidade_proposta, 
         forma_pagamento, opcao_frete, valor_frete, valor_total, status) 
        VALUES (:comprador_id, :vendedor_id, :produto_id, :preco_proposto, :quantidade_proposta,
                :forma_pagamento, :opcao_frete, :valor_frete, :valor_total, 'negociacao')";
    
    $stmt_proposta = $conn->prepare($sql_proposta);
    $stmt_proposta->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_proposta->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_proposta->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_proposta->bindParam(':preco_proposto', $preco_proposto);
    $stmt_proposta->bindParam(':quantidade_proposta', $quantidade_proposta, PDO::PARAM_INT);
    $stmt_proposta->bindParam(':forma_pagamento', $forma_pagamento);
    $stmt_proposta->bindParam(':opcao_frete', $opcao_frete);
    $stmt_proposta->bindParam(':valor_frete', $valor_frete);
    $stmt_proposta->bindParam(':valor_total', $valor_total);
    
    if (!$stmt_proposta->execute()) {
        throw new Exception('Erro ao salvar proposta no banco de dados');
    }
    
    $proposta_id = $conn->lastInsertId();
    
    // 5. Atualizar também na tabela propostas_comprador (manter compatibilidade)
    // Primeiro, precisamos obter o comprador_tabela_id (ID na tabela compradores)
    $sql_comprador_tabela = "SELECT id FROM compradores WHERE usuario_id = :comprador_id";
    $stmt_comp = $conn->prepare($sql_comprador_tabela);
    $stmt_comp->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_comp->execute();
    $comprador_info = $stmt_comp->fetch(PDO::FETCH_ASSOC);
    
    if ($comprador_info) {
        $comprador_tabela_id = $comprador_info['id'];
        
        $sql_proposta_comprador = "INSERT INTO propostas_comprador 
            (comprador_id, produto_id, preco_proposto, quantidade_proposta, 
             data_proposta, status, forma_pagamento, opcao_frete, valor_frete) 
            VALUES (:comprador_id, :produto_id, :preco_proposto, :quantidade_proposta,
                    NOW(), 'enviada', :forma_pagamento, :opcao_frete, :valor_frete)";
        
        $stmt_proposta_comp = $conn->prepare($sql_proposta_comprador);
        $stmt_proposta_comp->bindParam(':comprador_id', $comprador_tabela_id, PDO::PARAM_INT);
        $stmt_proposta_comp->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_proposta_comp->bindParam(':preco_proposto', $preco_proposto);
        $stmt_proposta_comp->bindParam(':quantidade_proposta', $quantidade_proposta, PDO::PARAM_INT);
        $stmt_proposta_comp->bindParam(':forma_pagamento', $forma_pagamento);
        $stmt_proposta_comp->bindParam(':opcao_frete', $opcao_frete);
        $stmt_proposta_comp->bindParam(':valor_frete', $valor_frete);
        $stmt_proposta_comp->execute();
        
        $proposta_comprador_id = $conn->lastInsertId();
        
        // Criar registro na tabela de negociação para compatibilidade
        $sql_negociacao = "INSERT INTO propostas_negociacao 
            (proposta_comprador_id, produto_id, data_inicio, data_atualizacao, 
             valor_total, quantidade_final, status, forma_pagamento, opcao_frete) 
            VALUES (:proposta_comprador_id, :produto_id, NOW(), NOW(),
                    :valor_total, :quantidade_final, 'negociacao', :forma_pagamento, :opcao_frete)";
        
        $stmt_negociacao = $conn->prepare($sql_negociacao);
        $stmt_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
        $stmt_negociacao->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_negociacao->bindParam(':valor_total', $valor_total);
        $stmt_negociacao->bindParam(':quantidade_final', $quantidade_proposta, PDO::PARAM_INT);
        $stmt_negociacao->bindParam(':forma_pagamento', $forma_pagamento);
        $stmt_negociacao->bindParam(':opcao_frete', $opcao_frete);
        $stmt_negociacao->execute();
        
        $negociacao_id = $conn->lastInsertId();
    }
    
    // 6. Enviar notificação para o vendedor
    // Buscar nome do produto para a notificação
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
    
    $mensagem = "Nova proposta para '{$produto_nome}' - Quantidade: {$quantidade_proposta} unidades";
    
    $url = "../../src/chat/chat.php?produto_id=" . $produto_id . "&conversa_id=" . $dados['conversa_id'];
    
    $stmt_not->bindParam(':usuario_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_not->bindParam(':mensagem', $mensagem);
    $stmt_not->bindParam(':url', $url);
    $stmt_not->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Proposta enviada com sucesso',
        'proposta_id' => $proposta_id,
        'proposta_comprador_id' => $proposta_comprador_id ?? null,
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