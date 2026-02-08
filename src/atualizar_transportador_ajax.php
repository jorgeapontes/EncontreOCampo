<?php
// src/atualizar_transportador_ajax.php
session_start();
require_once __DIR__ . '/conexao.php';

// Verificar autenticação
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';
$aba = $_GET['aba'] ?? 'ativos';
$mostrar_arquivados = ($aba === 'arquivados');

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
    'ultimas_mensagens' => [],
    'timestamp' => time(),
    'total_conversas' => 0,
    'total_nao_lidas' => 0
];

try {
    // BUSCAR NOVAS CONVERSAS CRIADAS DESDE A ÚLTIMA VERIFICAÇÃO
    $sql_novas_conversas = "SELECT 
                    cc.id AS conversa_id,
                    cc.produto_id,
                    cc.ultima_mensagem,
                    cc.ultima_mensagem_data,
                    p.nome AS produto_nome,
                    p.imagem_url AS produto_imagem,
                    p.preco AS produto_preco,
                    cc.transportador_id,
                    (SELECT u.nome FROM usuarios u WHERE u.id = cc.transportador_id) AS transportador_nome,
                    cc.favorito_comprador AS arquivado,
                    (SELECT COUNT(*) 
                     FROM chat_mensagens cm 
                     WHERE cm.conversa_id = cc.id 
                     AND cm.remetente_id != :usuario_id 
                     AND cm.lida = 0) AS mensagens_nao_lidas
                FROM chat_conversas cc
                INNER JOIN produtos p ON cc.produto_id = p.id
                WHERE cc.comprador_id = :usuario_id
                AND cc.transportador_id IS NOT NULL
                AND cc.status = 'ativo'
                AND cc.comprador_excluiu = 0
                AND UNIX_TIMESTAMP(cc.data_criacao) > :ultima_verificacao";
    
    if ($mostrar_arquivados) {
        $sql_novas_conversas .= " AND cc.favorito_comprador = 1";
    } else {
        $sql_novas_conversas .= " AND cc.favorito_comprador = 0";
    }

    $sql_novas_conversas .= " ORDER BY cc.ultima_mensagem_data DESC";
    
    $stmt_novas_conv = $conn->prepare($sql_novas_conversas);
    $stmt_novas_conv->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_novas_conv->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_novas_conv->execute();
    $novas_conversas = $stmt_novas_conv->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar conversas removidas (excluídas pelo usuário)
    $sql_removidas = "SELECT cc.id as conversa_id
                    FROM chat_conversas cc
                    WHERE cc.comprador_id = :usuario_id
                    AND cc.transportador_id IS NOT NULL
                    AND cc.comprador_excluiu = 1
                    AND UNIX_TIMESTAMP(COALESCE(cc.data_delecao, cc.ultima_mensagem_data)) > :ultima_verificacao";
    
    $stmt_removidas = $conn->prepare($sql_removidas);
    $stmt_removidas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_removidas->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_removidas->execute();
    $conversas_removidas = $stmt_removidas->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($novas_conversas) > 0 || count($conversas_removidas) > 0) {
        $response['atualizado'] = true;
        $response['novas_conversas'] = $novas_conversas;
        $response['conversas_removidas'] = $conversas_removidas;
    }
    
    // VERIFICAR MENSAGENS NOVAS PARA COMPRADOR COM TRANSPORTADOR
    $sql_novas_mensagens = "SELECT 
                        cm.conversa_id,
                        COUNT(cm.id) as total_novas,
                        MAX(cm.data_envio) as ultima_data
                      FROM chat_mensagens cm
                      INNER JOIN chat_conversas cc ON cm.conversa_id = cc.id
                      WHERE cc.comprador_id = :usuario_id
                      AND cc.transportador_id IS NOT NULL
                      AND cm.remetente_id != :usuario_id
                      AND cm.lida = 0
                      AND UNIX_TIMESTAMP(cm.data_envio) > :ultima_verificacao
                      AND cc.comprador_excluiu = 0
                      AND cc.status = 'ativo'
                      GROUP BY cm.conversa_id";
    
    $stmt_novas = $conn->prepare($sql_novas_mensagens);
    $stmt_novas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_novas->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_novas->execute();
    
    $novas_mensagens = $stmt_novas->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($novas_mensagens) > 0) {
        $response['atualizado'] = true;
        $response['novas_mensagens'] = $novas_mensagens;
    }
    
    // CONTAR TOTAL DE MENSAGENS NÃO LIDAS
    $sql_contadores = "SELECT 
                        cc.id as conversa_id,
                        COUNT(cm.id) as nao_lidas
                       FROM chat_conversas cc
                       LEFT JOIN chat_mensagens cm ON cc.id = cm.conversa_id 
                          AND cm.remetente_id != :usuario_id 
                          AND cm.lida = 0
                       WHERE cc.comprador_id = :usuario_id
                       AND cc.transportador_id IS NOT NULL
                       AND cc.status = 'ativo'
                       AND cc.comprador_excluiu = 0";
    
    if ($mostrar_arquivados) {
        $sql_contadores .= " AND cc.favorito_comprador = 1";
    } else {
        $sql_contadores .= " AND cc.favorito_comprador = 0";
    }
    
    $sql_contadores .= " GROUP BY cc.id";
    
    $stmt_contadores = $conn->prepare($sql_contadores);
    $stmt_contadores->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_contadores->execute();
    
    $contadores = $stmt_contadores->fetchAll(PDO::FETCH_ASSOC);
    $response['contadores'] = $contadores;
    
    // CALCULAR TOTAL
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
                       WHERE cc.comprador_id = :usuario_id
                       AND cc.transportador_id IS NOT NULL
                       AND UNIX_TIMESTAMP(cc.ultima_mensagem_data) > :ultima_verificacao
                       AND cc.comprador_excluiu = 0
                       AND cc.status = 'ativo'";
    
    $stmt_ultimas = $conn->prepare($sql_ultimas_msg);
    $stmt_ultimas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_ultimas->bindParam(':ultima_verificacao', $ultima_verificacao, PDO::PARAM_INT);
    $stmt_ultimas->execute();
    
    $response['ultimas_mensagens'] = $stmt_ultimas->fetchAll(PDO::FETCH_ASSOC);
    
    // CONTAR TOTAL DE CONVERSAS
    $sql_total = "SELECT COUNT(*) as total
                  FROM chat_conversas cc
                  WHERE cc.comprador_id = :usuario_id
                  AND cc.transportador_id IS NOT NULL
                  AND cc.status = 'ativo'
                  AND cc.comprador_excluiu = 0";
    
    if ($mostrar_arquivados) {
        $sql_total .= " AND cc.favorito_comprador = 1";
    } else {
        $sql_total .= " AND cc.favorito_comprador = 0";
    }
    
    $stmt_total = $conn->prepare($sql_total);
    $stmt_total->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_total->execute();
    $response['total_conversas'] = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (PDOException $e) {
    $response['error'] = 'Erro ao buscar atualizações: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>