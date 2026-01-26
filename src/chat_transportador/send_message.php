<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'erro' => 'Método inválido']);
    exit();
}

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'erro' => 'Usuário não autenticado']);
    exit();
}

$conversa_id = isset($_POST['conversa_id']) ? (int)$_POST['conversa_id'] : 0;
$mensagem = isset($_POST['mensagem']) ? trim($_POST['mensagem']) : '';
$usuario_id = $_SESSION['usuario_id'];

if ($conversa_id <= 0 || empty($mensagem)) {
    echo json_encode(['success' => false, 'erro' => 'Dados inválidos']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar se o transportador pertence a esta conversa
    $sql_verifica = "SELECT comprador_id, transportador_id FROM chat_conversas WHERE id = :conversa_id";
    $stmt_verifica = $conn->prepare($sql_verifica);
    $stmt_verifica->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_verifica->execute();
    $conversa = $stmt_verifica->fetch(PDO::FETCH_ASSOC);

    if (!$conversa || ($conversa['comprador_id'] != $usuario_id && $conversa['transportador_id'] != $usuario_id)) {
        echo json_encode(['success' => false, 'erro' => 'Acesso negado']);
        exit();
    }

    // Inserir mensagem
    $sql_insert = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem) VALUES (:conversa_id, :remetente_id, :mensagem)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':mensagem', $mensagem);
    $stmt_insert->execute();

    // Atualizar última mensagem na conversa
    $sql_update = "UPDATE chat_conversas SET ultima_mensagem = :mensagem, ultima_mensagem_data = NOW() WHERE id = :conversa_id";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bindParam(':mensagem', $mensagem);
    $stmt_update->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_update->execute();

    echo json_encode(['success' => true, 'mensagem_id' => $conn->lastInsertId()]);
    exit();

} catch (PDOException $e) {
    error_log('Erro send_message transportador: ' . $e->getMessage());
    echo json_encode(['success' => false, 'erro' => 'Erro ao enviar mensagem']);
    exit();
}

?>
