<?php
// src/carregar_conversas_transportador_ajax.php
session_start();
require_once __DIR__ . '/conexao.php';

// Verificar autenticação
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$aba = $_GET['aba'] ?? 'ativos';
$mostrar_arquivados = ($aba === 'arquivados');

$database = new Database();
$conn = $database->getConnection();

$response = [
    'conversas' => [],
    'timestamp' => time(),
    'total_conversas' => 0,
    'total_nao_lidas' => 0
];

try {
    $sql = "SELECT 
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
            AND cc.comprador_excluiu = 0";
    
    if ($mostrar_arquivados) {
        $sql .= " AND cc.favorito_comprador = 1";
    } else {
        $sql .= " AND cc.favorito_comprador = 0";
    }
    
    $sql .= " ORDER BY cc.ultima_mensagem_data DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['conversas'] = $conversas;
    $response['total_conversas'] = count($conversas);
    
    // Contar mensagens não lidas
    $total_nao_lidas = 0;
    foreach ($conversas as $conv) {
        $total_nao_lidas += (int)$conv['mensagens_nao_lidas'];
    }
    $response['total_nao_lidas'] = $total_nao_lidas;
    
} catch (PDOException $e) {
    $response['error'] = 'Erro ao carregar conversas: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>