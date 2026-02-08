<?php
// src/vendedor/atualizar_chats_ajax.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar autenticação
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];
$aba = $_GET['aba'] ?? 'ativos';
$mostrar_arquivados = ($aba === 'arquivados');
$vendedor_id = $_SESSION['vendedor_id'] ?? null; // Adicione esta linha se disponível

$database = new Database();
$conn = $database->getConnection();

// TIMESTAMP da última verificação
$ultima_verificacao = $_GET['ultima_verificacao'] ?? 0;

$response = [
    'atualizado' => false,
    'novas_mensagens' => [],
    'contadores' => [],
    'novas_conversas' => [],
    'conversas_removidas' => [],
    'timestamp' => time(),
    'total_conversas' => 0,
    'total_arquivadas' => 0
];

try {
    // VERIFICAR MENSAGENS NOVAS DESDE A ÚLTIMA VERIFICAÇÃO
    $sql_novas = "SELECT 
                    cm.conversa_id,
                    COUNT(cm.id) as total_novas,
                    MAX(cm.data_envio) as ultima_data
                  FROM chat_mensagens cm
                  INNER JOIN chat_conversas cc ON cm.conversa_id = cc.id
                  WHERE (cc.comprador_id = :usuario_id OR cc.vendedor_id = :usuario_id)
                  AND cm.remetente_id != :usuario_id
                  AND cm.lida = 0
                  AND UNIX_TIMESTAMP(cm.data_envio) > :ultima_verificacao
                  GROUP BY cm.conversa_id";
    
    $stmt_novas = $conn->prepare($sql_novas);
    $stmt_novas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_novas->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_novas->execute();
    
    $novas_mensagens = $stmt_novas->fetchAll(PDO::FETCH_ASSOC);
    
    // BUSCAR NOVAS CONVERSAS CRIADAS DESDE A ÚLTIMA VERIFICAÇÃO
    $sql_novas_conversas = "SELECT 
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
                AND cc.transportador_id IS NULL
                AND UNIX_TIMESTAMP(cc.data_criacao) > :ultima_verificacao";
    
    if ($mostrar_arquivados) {
        $sql_novas_conversas .= " AND cc.favorito_vendedor = 1";
    } else {
        $sql_novas_conversas .= " AND cc.favorito_vendedor = 0";
    }

    // ORDENAR POR DATA DA ÚLTIMA MENSAGEM (MAIS RECENTE PRIMEIRO)
    $sql_novas_conversas .= " ORDER BY cc.ultima_mensagem_data DESC";
    
    $stmt_novas_conv = $conn->prepare($sql_novas_conversas);
    $stmt_novas_conv->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_novas_conv->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_novas_conv->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_novas_conv->execute();
    $novas_conversas_vendedor = $stmt_novas_conv->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar novas conversas como comprador
    $sql_novas_conversas_comprador = "SELECT 
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
                AND cc.transportador_id IS NULL
                AND UNIX_TIMESTAMP(cc.data_criacao) > :ultima_verificacao";
    
    if ($mostrar_arquivados) {
        $sql_novas_conversas_comprador .= " AND cc.favorito_comprador = 1";
    } else {
        $sql_novas_conversas_comprador .= " AND cc.favorito_comprador = 0";
    }
    
    // ORDENAR POR DATA DA ÚLTIMA MENSAGEM (MAIS RECENTE PRIMEIRO)
    $sql_novas_conversas_comprador .= " ORDER BY cc.ultima_mensagem_data DESC";

    $stmt_novas_conv_c = $conn->prepare($sql_novas_conversas_comprador);
    $stmt_novas_conv_c->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_novas_conv_c->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_novas_conv_c->execute();
    $novas_conversas_comprador = $stmt_novas_conv_c->fetchAll(PDO::FETCH_ASSOC);
    
    $novas_conversas = array_merge($novas_conversas_vendedor, $novas_conversas_comprador);
    
    // Verificar conversas removidas (excluídas pelo outro usuário ou arquivadas/movidas)
    $sql_removidas = "SELECT cc.id as conversa_id
                    FROM chat_conversas cc
                    LEFT JOIN produtos p ON cc.produto_id = p.id
                    WHERE ((p.vendedor_id = :vendedor_id AND cc.vendedor_excluiu = 1) 
                        OR (cc.comprador_id = :usuario_id AND cc.comprador_excluiu = 1))
                    AND UNIX_TIMESTAMP(GREATEST(COALESCE(cc.data_delecao, cc.ultima_mensagem_data), cc.data_criacao)) > :ultima_verificacao";
    
    $stmt_removidas = $conn->prepare($sql_removidas);
    $stmt_removidas->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_removidas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_removidas->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_removidas->execute();
    $conversas_removidas = $stmt_removidas->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($novas_mensagens) > 0 || count($novas_conversas) > 0 || count($conversas_removidas) > 0) {
        $response['atualizado'] = true;
        $response['novas_mensagens'] = $novas_mensagens;
        $response['novas_conversas'] = $novas_conversas;
        $response['conversas_removidas'] = $conversas_removidas;
    }
    
    // CONTAR TOTAL DE MENSAGENS NÃO LIDAS
    $sql_total_nao_lidas = "SELECT 
                            cc.id as conversa_id,
                            COUNT(cm.id) as nao_lidas
                           FROM chat_conversas cc
                           LEFT JOIN chat_mensagens cm ON cc.id = cm.conversa_id 
                              AND cm.remetente_id != :usuario_id 
                              AND cm.lida = 0
                           WHERE (cc.comprador_id = :usuario_id OR cc.vendedor_id = :usuario_id)
                           AND cc.status = 'ativo'";
    
    if ($mostrar_arquivados) {
        if ($usuario_tipo === 'comprador') {
            $sql_total_nao_lidas .= " AND cc.favorito_comprador = 1";
        } else {
            $sql_total_nao_lidas .= " AND cc.favorito_vendedor = 1";
        }
    } else {
        if ($usuario_tipo === 'comprador') {
            $sql_total_nao_lidas .= " AND cc.favorito_comprador = 0";
        } else {
            $sql_total_nao_lidas .= " AND cc.favorito_vendedor = 0";
        }
    }
    
    // Filtrar excluídos
    if ($usuario_tipo === 'comprador') {
        $sql_total_nao_lidas .= " AND cc.comprador_excluiu = 0";
    } elseif ($usuario_tipo === 'vendedor') {
        $sql_total_nao_lidas .= " AND cc.vendedor_excluiu = 0";
    }
    
    $sql_total_nao_lidas .= " GROUP BY cc.id";
    
    $stmt_total = $conn->prepare($sql_total_nao_lidas);
    $stmt_total->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_total->execute();
    
    $contadores = $stmt_total->fetchAll(PDO::FETCH_ASSOC);
    $response['contadores'] = $contadores;
    
    // CONTAGEM TOTAL PARA STATS BAR
    $total_nao_lidas = 0;
    foreach ($contadores as $cont) {
        $total_nao_lidas += (int)$cont['nao_lidas'];
    }
    $response['total_nao_lidas'] = $total_nao_lidas;
    
    // ATUALIZAR ÚLTIMAS MENSAGENS
    $sql_ultimas_msg = "SELECT 
                        cc.id as conversa_id,
                        cc.ultima_mensagem,
                        cc.ultima_mensagem_data
                       FROM chat_conversas cc
                       WHERE (cc.comprador_id = :usuario_id OR cc.vendedor_id = :usuario_id)
                       AND UNIX_TIMESTAMP(cc.ultima_mensagem_data) > :ultima_verificacao";
    
    $stmt_ultimas = $conn->prepare($sql_ultimas_msg);
    $stmt_ultimas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_ultimas->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_ultimas->execute();
    
    $response['ultimas_mensagens'] = $stmt_ultimas->fetchAll(PDO::FETCH_ASSOC);
    
    // CONTAR TOTAL DE CONVERSAS ATIVAS E ARQUIVADAS
    // Total ativas
    $sql_total_ativas = "SELECT COUNT(*) as total
                        FROM chat_conversas cc
                        LEFT JOIN produtos p ON cc.produto_id = p.id
                        WHERE ((p.vendedor_id = :vendedor_id AND cc.vendedor_excluiu = 0) 
                            OR (cc.comprador_id = :usuario_id AND cc.comprador_excluiu = 0))
                        AND cc.status = 'ativo'
                        AND cc.transportador_id IS NULL";
    
    if (!$mostrar_arquivados) {
        $sql_total_ativas .= " AND (cc.favorito_vendedor = 0 OR cc.favorito_comprador = 0)";
    } else {
        $sql_total_ativas .= " AND (cc.favorito_vendedor = 1 OR cc.favorito_comprador = 1)";
    }
    
    $stmt_total_ativas = $conn->prepare($sql_total_ativas);
    $stmt_total_ativas->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_total_ativas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_total_ativas->execute();
    $response['total_conversas'] = $stmt_total_ativas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Total arquivadas
    $sql_total_arquivadas = "SELECT COUNT(*) as total
                            FROM chat_conversas cc
                            LEFT JOIN produtos p ON cc.produto_id = p.id
                            WHERE ((p.vendedor_id = :vendedor_id AND cc.vendedor_excluiu = 0) 
                                OR (cc.comprador_id = :usuario_id AND cc.comprador_excluiu = 0))
                            AND cc.status = 'ativo'
                            AND cc.transportador_id IS NULL
                            AND (cc.favorito_vendedor = 1 OR cc.favorito_comprador = 1)";
    
    $stmt_total_arquivadas = $conn->prepare($sql_total_arquivadas);
    $stmt_total_arquivadas->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_total_arquivadas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_total_arquivadas->execute();
    $response['total_arquivadas'] = $stmt_total_arquivadas->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (PDOException $e) {
    $response['error'] = 'Erro ao buscar atualizações: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>