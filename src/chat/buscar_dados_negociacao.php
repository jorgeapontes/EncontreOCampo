<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
$mensagem_id = isset($_GET['mensagem_id']) ? (int)$_GET['mensagem_id'] : 0;
$usuario_id = $_SESSION['usuario_id'];

if ($conversa_id <= 0 || $mensagem_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar se o usuário pertence a esta conversa
    $sql_verifica = "SELECT comprador_id, vendedor_id, produto_id FROM chat_conversas WHERE id = :conversa_id";
    $stmt_verifica = $conn->prepare($sql_verifica);
    $stmt_verifica->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_verifica->execute();
    $conversa = $stmt_verifica->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversa || ($conversa['comprador_id'] != $usuario_id && $conversa['vendedor_id'] != $usuario_id)) {
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit();
    }
    
    // Buscar TODAS as negociações para este produto, ordenadas pela mais recente
    $sql_negociacoes = "SELECT 
        pn.ID as negociacao_id,
        pn.status,
        pn.valor_total,
        pn.quantidade_final,
        pn.forma_pagamento,
        pn.opcao_frete,
        pn.proposta_comprador_id,
        pn.proposta_vendedor_id,
        pn.data_inicio,
        pn.data_atualizacao,
        p.id as produto_id,
        p.nome as produto_nome,
        pc.preco_proposto as preco_comprador,
        pc.quantidade_proposta as quantidade_comprador,
        pc.valor_frete as frete_comprador,
        pc.data_proposta as data_proposta_comprador,
        pv.preco_proposto as preco_vendedor,
        pv.quantidade_proposta as quantidade_vendedor,
        pv.valor_frete as frete_vendedor,
        pv.data_proposta as data_proposta_vendedor,
        uc.nome as comprador_nome,
        uv.nome as vendedor_nome,
        u.nome as usuario_nome
        FROM propostas_negociacao pn
        JOIN produtos p ON pn.produto_id = p.id
        JOIN chat_conversas cc ON cc.produto_id = p.id AND cc.id = :conversa_id
        JOIN usuarios uc ON uc.id = cc.comprador_id
        JOIN usuarios uv ON uv.id = cc.vendedor_id
        JOIN usuarios u ON u.id = :usuario_id
        LEFT JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.ID
        LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.ID
        WHERE pn.produto_id = :produto_id
        ORDER BY pn.data_atualizacao DESC";
    
    $stmt = $conn->prepare($sql_negociacoes);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->bindParam(':produto_id', $conversa['produto_id'], PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $negociacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($negociacoes)) {
        echo json_encode(['success' => false, 'error' => 'Negociação não encontrada']);
        exit();
    }
    
    // Determinar quem é o usuário atual
    $eh_comprador = ($usuario_id == $conversa['comprador_id']);
    $eh_vendedor = ($usuario_id == $conversa['vendedor_id']);
    
    // Determinar qual negociação corresponde a esta mensagem
    // Vamos pegar a mais recente (primeira do array) para esta mensagem
    $negociacao = $negociacoes[0];
    
    // Buscar dados específicos da proposta
    $preco_unitario = 0;
    $quantidade = 0;
    $valor_frete = 0;
    
    if ($negociacao['proposta_comprador_id']) {
        $preco_unitario = $negociacao['preco_comprador'] ?: 0;
        $quantidade = $negociacao['quantidade_comprador'] ?: 0;
        $valor_frete = $negociacao['frete_comprador'] ?: 0;
        $enviado_por = $negociacao['comprador_nome'];
    }
    
    if ($negociacao['proposta_vendedor_id']) {
        $preco_unitario = $negociacao['preco_vendedor'] ?: 0;
        $quantidade = $negociacao['quantidade_vendedor'] ?: 0;
        $valor_frete = $negociacao['frete_vendedor'] ?: 0;
        $enviado_por = $negociacao['vendedor_nome'];
    }
    
    // Se ambas existem, usar a mais recente
    if ($negociacao['proposta_comprador_id'] && $negociacao['proposta_vendedor_id']) {
        // Comparar datas das propostas
        $data_comprador = strtotime($negociacao['data_proposta_comprador'] ?: '1970-01-01');
        $data_vendedor = strtotime($negociacao['data_proposta_vendedor'] ?: '1970-01-01');
        
        if ($data_comprador > $data_vendedor) {
            // Proposta do comprador é mais recente
            $preco_unitario = $negociacao['preco_comprador'] ?: 0;
            $quantidade = $negociacao['quantidade_comprador'] ?: 0;
            $valor_frete = $negociacao['frete_comprador'] ?: 0;
            $enviado_por = $negociacao['comprador_nome'];
        } else {
            // Proposta do vendedor é mais recente
            $preco_unitario = $negociacao['preco_vendedor'] ?: 0;
            $quantidade = $negociacao['quantidade_vendedor'] ?: 0;
            $valor_frete = $negociacao['frete_vendedor'] ?: 0;
            $enviado_por = $negociacao['vendedor_nome'];
        }
    }
    
    // Calcular total correto (sem duplicar frete)
    $total_calculado = ($quantidade * $preco_unitario) + $valor_frete;
    
    // Preparar dados para o card
    $dados_negociacao = [
        'negociacao_id' => $negociacao['negociacao_id'],
        'produto_id' => $negociacao['produto_id'],
        'produto_nome' => $negociacao['produto_nome'],
        'quantidade' => $quantidade ?: $negociacao['quantidade_final'],
        'preco_unitario' => $preco_unitario,
        'valor_frete' => $valor_frete,
        'total' => $total_calculado,
        'forma_pagamento' => $negociacao['forma_pagamento'],
        'opcao_frete' => $negociacao['opcao_frete'],
        'enviado_por' => $enviado_por,
        'status' => $negociacao['status'],
        'tipo_remetente' => $eh_comprador ? 'comprador' : 'vendedor',
        'tem_proposta_comprador' => !empty($negociacao['proposta_comprador_id']),
        'tem_proposta_vendedor' => !empty($negociacao['proposta_vendedor_id'])
    ];
    
    echo json_encode([
        'success' => true,
        'dados_negociacao' => $dados_negociacao
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados da negociação: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>