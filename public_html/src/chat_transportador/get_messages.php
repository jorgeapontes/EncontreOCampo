<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../chat/chat_config.php';

header('Content-Type: application/json');

verificarLoginChat();

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
$ultimo_id = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;
$antes_id = isset($_GET['antes_id']) ? (int)$_GET['antes_id'] : 0;
$marcar_lidas = !isset($_GET['marcar_lidas']) || $_GET['marcar_lidas'] === '1';
// Ids de propostas_transportadores ainda pendentes na tela: o servidor
// devolve o status atual delas para o card atualizar sem recarregar o chat
$ids_status = [];
if (!empty($_GET['status_propostas'])) {
    $ids_status = array_slice(array_filter(array_map('intval', explode(',', $_GET['status_propostas']))), 0, 50);
}
$usuario_id = (int)$_SESSION['usuario_id'];

chatLiberarSessao();

if ($conversa_id <= 0) {
    chatErro(400, 'Conversa inválida');
}

$database = new Database();
$conn = $database->getConnection();

// Status das propostas de frete citadas nas mensagens, numa única query
// (antes era um fetch por card). Só propostas do comprador desta conversa.
function statusPropostasTransportador($conn, array $ids, $comprador_id) {
    $ids = array_values(array_unique(array_filter($ids)));
    if (!$ids) return [];

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT pt.id, pt.status FROM propostas_transportadores pt
            JOIN propostas p ON pt.proposta_id = p.ID
            WHERE pt.id IN ($marcadores) AND p.comprador_id = ?";
    $stmt = $conn->prepare($sql);
    foreach ($ids as $i => $id) {
        $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
    }
    $stmt->bindValue(count($ids) + 1, $comprador_id, PDO::PARAM_INT);
    $stmt->execute();

    $status = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status[(int)$row['id']] = $row['status'] ?: 'pendente';
    }
    return $status;
}

try {
    // Verificar que o transportador pertence a conversa
    $sql_verifica = "SELECT comprador_id, transportador_id FROM chat_conversas WHERE id = :conversa_id";
    $stmt_verifica = $conn->prepare($sql_verifica);
    $stmt_verifica->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_verifica->execute();
    $conversa = $stmt_verifica->fetch(PDO::FETCH_ASSOC);

    if (!$conversa || ($conversa['comprador_id'] != $usuario_id && $conversa['transportador_id'] != $usuario_id)) {
        chatErro(403, 'Acesso negado');
    }

    [$mensagens, $tem_mais] = chatBuscarMensagens($conn, $conversa_id, $ultimo_id, $antes_id);

    // Anexar o status atual a cada mensagem de proposta
    $ids_mensagens = [];
    foreach ($mensagens as $i => $msg) {
        if ($msg['tipo'] !== 'proposta' || empty($msg['dados_json'])) continue;
        $dados = json_decode($msg['dados_json'], true);
        if (!empty($dados['propostas_transportador_id'])) {
            $ids_mensagens[$i] = (int)$dados['propostas_transportador_id'];
        }
    }
    $status = statusPropostasTransportador($conn, array_merge(array_values($ids_mensagens), $ids_status), (int)$conversa['comprador_id']);
    foreach ($ids_mensagens as $i => $pt_id) {
        $mensagens[$i]['proposta_status'] = $status[$pt_id] ?? 'pendente';
    }

    if ($antes_id > 0) {
        echo json_encode(['success' => true, 'mensagens' => $mensagens, 'tem_mais' => $tem_mais]);
        exit();
    }

    if ($marcar_lidas) {
        marcarMensagensComoLidas($conn, $conversa_id, $usuario_id);
    }

    $resposta = [
        'success' => true,
        'mensagens' => $mensagens,
        'ultimo_lido_id' => chatUltimoLidoId($conn, $conversa_id, $usuario_id),
        'status_propostas' => (object)array_intersect_key($status, array_flip($ids_status)),
    ];
    if ($ultimo_id <= 0) {
        $resposta['tem_mais'] = $tem_mais;
    }

    echo json_encode($resposta);
    exit();

} catch (PDOException $e) {
    error_log('Erro get_messages transportador: ' . $e->getMessage());
    chatErro(500, 'Erro ao buscar mensagens');
}

?>
