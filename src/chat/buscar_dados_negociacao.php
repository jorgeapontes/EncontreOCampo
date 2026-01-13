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
    
    // Buscar informações da MENSAGEM específica
    $sql_mensagem = "SELECT data_envio, remetente_id FROM chat_mensagens WHERE id = :mensagem_id";
    $stmt_msg = $conn->prepare($sql_mensagem);
    $stmt_msg->bindParam(':mensagem_id', $mensagem_id, PDO::PARAM_INT);
    $stmt_msg->execute();
    $mensagem = $stmt_msg->fetch(PDO::FETCH_ASSOC);
    
    if (!$mensagem) {
        echo json_encode(['success' => false, 'error' => 'Mensagem não encontrada']);
        exit();
    }
    
    // Determinar quem enviou esta mensagem
    $remetente_id = $mensagem['remetente_id'];
    $remetente_eh_comprador = ($remetente_id == $conversa['comprador_id']);
    $remetente_eh_vendedor = ($remetente_id == $conversa['vendedor_id']);
    
    // Buscar nome do remetente
    $sql_remetente = "SELECT nome FROM usuarios WHERE id = :remetente_id";
    $stmt_rem = $conn->prepare($sql_remetente);
$stmt_rem->bindParam(':remetente_id', $remetente_id, PDO::PARAM_INT);
    $stmt_rem->execute();
    $remetente_info = $stmt_rem->fetch(PDO::FETCH_ASSOC);
    $remetente_nome = $remetente_info['nome'] ?? '';
    
    // Buscar nome do produto
    $sql_produto = "SELECT nome FROM produtos WHERE id = :produto_id";
    $stmt_prod = $conn->prepare($sql_produto);
    $stmt_prod->bindParam(':produto_id', $conversa['produto_id'], PDO::PARAM_INT);
    $stmt_prod->execute();
    $produto_info = $stmt_prod->fetch(PDO::FETCH_ASSOC);
    $produto_nome = $produto_info['nome'] ?? '';
    
    // Aqui está a LÓGICA CRÍTICA:
    // Precisamos encontrar qual proposta está associada a esta mensagem
    
    // Opção 1: Procurar pela última proposta do remetente na data aproximada da mensagem
    $dados_proposta = null;
    $eh_mais_recente = false;
    
    if ($remetente_eh_comprador) {
        // BUSCAR DA TABELA PROPOSTAS_COMPRADOR
        // Primeiro, buscar TODAS as propostas do comprador para este produto
        $sql_propostas_comprador = "SELECT 
            pc.ID as proposta_id,
            pc.preco_proposto,
            pc.quantidade_proposta,
            pc.valor_frete,
            pc.forma_pagamento,
            pc.opcao_frete,
            pc.data_proposta,
            pc.status,
            'comprador' as tipo_proposta
            FROM propostas_comprador pc
            WHERE pc.comprador_id = :comprador_id 
            AND pc.produto_id = :produto_id
            ORDER BY pc.data_proposta DESC";
        
        $stmt_pc = $conn->prepare($sql_propostas_comprador);
        $stmt_pc->bindParam(':comprador_id', $conversa['comprador_id'], PDO::PARAM_INT);
        $stmt_pc->bindParam(':produto_id', $conversa['produto_id'], PDO::PARAM_INT);
        $stmt_pc->execute();
        $propostas_comprador = $stmt_pc->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($propostas_comprador)) {
            // Tentar associar pela data mais próxima da mensagem
            $data_mensagem = strtotime($mensagem['data_envio']);
            $melhor_match = null;
            $menor_diferenca = PHP_INT_MAX;
            
            foreach ($propostas_comprador as $index => $proposta) {
                $data_proposta = strtotime($proposta['data_proposta']);
                $diferenca = abs($data_mensagem - $data_proposta);
                
                if ($diferenca < $menor_diferenca) {
                    $menor_diferenca = $diferenca;
                    $melhor_match = $proposta;
                    $melhor_match['indice'] = $index;
                }
            }
            
            if ($melhor_match) {
                $dados_proposta = $melhor_match;
                // Verificar se é a mais recente (primeira do array)
                $eh_mais_recente = ($melhor_match['indice'] === 0);
            }
        }
        
    } elseif ($remetente_eh_vendedor) {
        // BUSCAR DA TABELA PROPOSTAS_VENDEDOR
        $sql_propostas_vendedor = "SELECT 
            pv.ID as proposta_id,
            pv.preco_proposto,
            pv.quantidade_proposta,
            pv.valor_frete,
            pv.forma_pagamento,
            pv.opcao_frete,
            pv.data_proposta,
            pv.status,
            'vendedor' as tipo_proposta
            FROM propostas_vendedor pv
            WHERE pv.vendedor_id = :vendedor_id 
            AND pv.produto_id = :produto_id
            ORDER BY pv.data_proposta DESC";
        
        $stmt_pv = $conn->prepare($sql_propostas_vendedor);
        $stmt_pv->bindParam(':vendedor_id', $conversa['vendedor_id'], PDO::PARAM_INT);
        $stmt_pv->bindParam(':produto_id', $conversa['produto_id'], PDO::PARAM_INT);
        $stmt_pv->execute();
        $propostas_vendedor = $stmt_pv->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($propostas_vendedor)) {
            // Tentar associar pela data mais próxima da mensagem
            $data_mensagem = strtotime($mensagem['data_envio']);
            $melhor_match = null;
            $menor_diferenca = PHP_INT_MAX;
            
            foreach ($propostas_vendedor as $index => $proposta) {
                $data_proposta = strtotime($proposta['data_proposta']);
                $diferenca = abs($data_mensagem - $data_proposta);
                
                if ($diferenca < $menor_diferenca) {
                    $menor_diferenca = $diferenca;
                    $melhor_match = $proposta;
                    $melhor_match['indice'] = $index;
                }
            }
            
            if ($melhor_match) {
                $dados_proposta = $melhor_match;
                // Verificar se é a mais recente (primeira do array)
                $eh_mais_recente = ($melhor_match['indice'] === 0);
            }
        }
    }
    
    if (!$dados_proposta) {
        // Se não encontrou proposta específica, criar dados básicos
        echo json_encode([
            'success' => true,
            'dados_negociacao' => [
                'proposta_id' => 0,
                'produto_nome' => $produto_nome,
                'quantidade' => 0,
                'preco_unitario' => 0,
                'valor_frete' => 0,
                'total' => 0,
                'forma_pagamento' => 'à vista',
                'opcao_frete' => 'vendedor',
                'enviado_por' => $remetente_nome,
                'status' => 'pendente',
                'remetente_eh_comprador' => $remetente_eh_comprador,
                'remetente_eh_vendedor' => $remetente_eh_vendedor,
                'eh_mais_recente' => false,
                'tipo_proposta' => $remetente_eh_comprador ? 'comprador' : 'vendedor'
            ]
        ]);
        exit();
    }
    
    // Calcular total
    $total = ($dados_proposta['quantidade_proposta'] * $dados_proposta['preco_proposto']) + $dados_proposta['valor_frete'];
    
    // Preparar dados para o card
    $dados_negociacao = [
        'proposta_id' => $dados_proposta['proposta_id'],
        'produto_nome' => $produto_nome,
        'quantidade' => $dados_proposta['quantidade_proposta'],
        'preco_unitario' => $dados_proposta['preco_proposto'],
        'valor_frete' => $dados_proposta['valor_frete'],
        'total' => $total,
        'forma_pagamento' => $dados_proposta['forma_pagamento'],
        'opcao_frete' => $dados_proposta['opcao_frete'],
        'enviado_por' => $remetente_nome,
        'status' => $dados_proposta['status'],
        'remetente_eh_comprador' => $remetente_eh_comprador,
        'remetente_eh_vendedor' => $remetente_eh_vendedor,
        'eh_mais_recente' => $eh_mais_recente,
        'tipo_proposta' => $dados_proposta['tipo_proposta']
    ];
    
    // Buscar também o ID da negociação se existir
    if ($remetente_eh_comprador) {
        $sql_negociacao = "SELECT ID FROM propostas_negociacao WHERE proposta_comprador_id = :proposta_id";
    } else {
        $sql_negociacao = "SELECT ID FROM propostas_negociacao WHERE proposta_vendedor_id = :proposta_id";
    }
    
    $stmt_neg = $conn->prepare($sql_negociacao);
    $stmt_neg->bindParam(':proposta_id', $dados_proposta['proposta_id'], PDO::PARAM_INT);
    $stmt_neg->execute();
    $negociacao = $stmt_neg->fetch(PDO::FETCH_ASSOC);
    
    if ($negociacao) {
        $dados_negociacao['negociacao_id'] = $negociacao['ID'];
    } else {
        $dados_negociacao['negociacao_id'] = 0;
    }
    
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