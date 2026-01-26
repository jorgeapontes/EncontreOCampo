<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'erro' => 'Usuário não autenticado']);
    exit();
}

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
$ultimo_id = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;
$usuario_id = $_SESSION['usuario_id'];

if ($conversa_id <= 0) {
    echo json_encode(['success' => false, 'erro' => 'Conversa inválida']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar que o transportador pertence a conversa
    $sql_verifica = "SELECT comprador_id, transportador_id FROM chat_conversas WHERE id = :conversa_id";
    $stmt_verifica = $conn->prepare($sql_verifica);
    $stmt_verifica->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_verifica->execute();
    $conversa = $stmt_verifica->fetch(PDO::FETCH_ASSOC);

    if (!$conversa || ($conversa['comprador_id'] != $usuario_id && $conversa['transportador_id'] != $usuario_id)) {
        echo json_encode(['success' => false, 'erro' => 'Acesso negado']);
        exit();
    }

    if ($ultimo_id > 0) {
        $sql = "SELECT id, remetente_id, mensagem, tipo, DATE_FORMAT(data_envio, '%d/%m %H:%i') as data_formatada, lida FROM chat_mensagens WHERE conversa_id = :conversa_id AND id > :ultimo_id ORDER BY id ASC";
    } else {
        $sql = "SELECT id, remetente_id, mensagem, tipo, DATE_FORMAT(data_envio, '%d/%m %H:%i') as data_formatada, lida FROM chat_mensagens WHERE conversa_id = :conversa_id ORDER BY id ASC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    if ($ultimo_id > 0) $stmt->bindParam(':ultimo_id', $ultimo_id, PDO::PARAM_INT);
    $stmt->execute();
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($mensagens) > 0) {
        // marcar como lidas
        $sql_lidas = "UPDATE chat_mensagens SET lida = 1 WHERE conversa_id = :conversa_id AND remetente_id != :usuario_id AND lida = 0";
        $stmt_l = $conn->prepare($sql_lidas);
        $stmt_l->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt_l->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_l->execute();
    }

    echo json_encode(['success' => true, 'mensagens' => $mensagens]);
    exit();

} catch (PDOException $e) {
    error_log('Erro get_messages transportador: ' . $e->getMessage());
    echo json_encode(['success' => false, 'erro' => 'Erro ao buscar mensagens']);
}

?>
