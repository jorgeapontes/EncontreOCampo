<?php
// src/vendedor/carregar_conversas_ajax.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar autenticação
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$aba = $_GET['aba'] ?? 'ativos';
$mostrar_arquivados = ($aba === 'arquivados');
$vendedor_id = $_SESSION['vendedor_id'] ?? null;

$database = new Database();
$conn = $database->getConnection();

$response = [
    'conversas' => [],
    'timestamp' => time(),
    'total_conversas' => 0,
    'total_arquivadas' => 0,
    'total_nao_lidas' => 0
];

try {
    // BUSCAR CHATS COMO VENDEDOR
    $sql_vendedor = "SELECT 
                cc.id AS conversa_id,
                cc.produto_id,
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                u.id AS outro_usuario_id,
                u.nome AS outro_usuario_nome,
                'vendedor' AS tipo_chat,
                cc.favorito_vendedor AS arquivado,
                (SELECT COUNT(*) 
                 FROM chat_mensagens cm 
                 WHERE cm.conversa_id = cc.id 
                 AND cm.remetente_id != :usuario_id 
                 AND cm.lida = 0) AS mensagens_nao_lidas
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN usuarios u ON cc.comprador_id = u.id
            WHERE p.vendedor_id = :vendedor_id
            AND cc.status = 'ativo'
            AND cc.vendedor_excluiu = 0
            AND cc.transportador_id IS NULL";
    
    if ($mostrar_arquivados) {
        $sql_vendedor .= " AND cc.favorito_vendedor = 1";
    } else {
        $sql_vendedor .= " AND cc.favorito_vendedor = 0";
    }

    // ORDENAR POR DATA DA ÚLTIMA MENSAGEM (MAIS RECENTE PRIMEIRO)
    $sql_vendedor .= " ORDER BY cc.ultima_mensagem_data DESC";
    
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $conversas_vendedor = $stmt_vendedor->fetchAll(PDO::FETCH_ASSOC);
    
    // BUSCAR CHATS COMO COMPRADOR
    $sql_comprador = "SELECT 
                cc.id AS conversa_id,
                cc.produto_id,
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                uv.id AS outro_usuario_id,
                COALESCE(v.nome_comercial, uv.nome) AS outro_usuario_nome,
                'comprador' AS tipo_chat,
                cc.favorito_comprador AS arquivado,
                (SELECT COUNT(*) 
                 FROM chat_mensagens cm 
                 WHERE cm.conversa_id = cc.id 
                 AND cm.remetente_id != :usuario_id 
                 AND cm.lida = 0) AS mensagens_nao_lidas
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN vendedores v ON p.vendedor_id = v.id
            INNER JOIN usuarios uv ON v.usuario_id = uv.id
            WHERE cc.comprador_id = :usuario_id
            AND cc.status = 'ativo'
            AND cc.comprador_excluiu = 0
            AND cc.transportador_id IS NULL";
    
    if ($mostrar_arquivados) {
        $sql_comprador .= " AND cc.favorito_comprador = 1";
    } else {
        $sql_comprador .= " AND cc.favorito_comprador = 0";
    }

    // ORDENAR POR DATA DA ÚLTIMA MENSAGEM (MAIS RECENTE PRIMEIRO)
    $sql_comprador .= " ORDER BY cc.ultima_mensagem_data DESC";
    
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $conversas_comprador = $stmt_comprador->fetchAll(PDO::FETCH_ASSOC);
    
    // COMBINAR E MANTER A ORDEM (agora já vem ordenado do banco)
    $conversas = array_merge($conversas_vendedor, $conversas_comprador);
    // Não precisa mais do usort aqui, pois as consultas já retornam ordenadas
    // Mas mantemos o usort para garantir ordenação correta após o merge
    usort($conversas, function($a, $b) {
        return strtotime($b['ultima_mensagem_data']) - strtotime($a['ultima_mensagem_data']);
    });
        
    $response['conversas'] = $conversas;
    
    // Contar totais
    $response['total_conversas'] = count($conversas);
    
    // Contar mensagens não lidas
    $total_nao_lidas = 0;
    foreach ($conversas as $conv) {
        $total_nao_lidas += (int)$conv['mensagens_nao_lidas'];
    }
    $response['total_nao_lidas'] = $total_nao_lidas;
    
    // Contar arquivadas
    $sql_arquivadas = "SELECT COUNT(*) as total
                      FROM chat_conversas cc
                      LEFT JOIN produtos p ON cc.produto_id = p.id
                      WHERE ((p.vendedor_id = :vendedor_id AND cc.vendedor_excluiu = 0) 
                          OR (cc.comprador_id = :usuario_id AND cc.comprador_excluiu = 0))
                      AND cc.status = 'ativo'
                      AND cc.transportador_id IS NULL
                      AND (cc.favorito_vendedor = 1 OR cc.favorito_comprador = 1)";
    
    $stmt_arquivadas = $conn->prepare($sql_arquivadas);
    $stmt_arquivadas->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_arquivadas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_arquivadas->execute();
    $response['total_arquivadas'] = $stmt_arquivadas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (PDOException $e) {
    $response['error'] = 'Erro ao carregar conversas: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>