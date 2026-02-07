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

$database = new Database();
$conn = $database->getConnection();

// TIMESTAMP da última verificação
$ultima_verificacao = $_GET['ultima_verificacao'] ?? 0;

$response = [
    'atualizado' => false,
    'novas_mensagens' => [],
    'contadores' => [],
    'timestamp' => time()
];

try {
    // VERIFICAR MENSAGENS NOVAS PARA COMPRADOR COM TRANSPORTADOR
    if (in_array($usuario_tipo, ['comprador', 'vendedor'])) {
        $sql_novas = "SELECT 
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
        
        $stmt_novas = $conn->prepare($sql_novas);
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
        if ($response['atualizado']) {
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
        }
    }
    
} catch (PDOException $e) {
    $response['error'] = 'Erro ao buscar atualizações: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>