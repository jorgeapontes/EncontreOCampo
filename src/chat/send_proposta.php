<?php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php?erro=Acesso restrito.');
    exit();
}
$usuario_id = $_SESSION['usuario_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chat_id = intval($_POST['chat_id'] ?? 0);
    $valor = floatval(str_replace(',', '.', $_POST['valor'] ?? '0'));
    $prazo = trim($_POST['prazo'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    if ($chat_id <= 0 || $valor <= 0 || $prazo === '') {
        header('Location: negociacao_chat.php?chat_id=' . $chat_id . '&erro=Dados inválidos.');
        exit();
    }
    $database = new Database();
    $db = $database->getConnection();
    // Verificar permissão
    $sql = "SELECT * FROM negociacao_chats WHERE id = :chat_id AND (comprador_id = :uid OR transportador_id = :uid)";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':chat_id', $chat_id, PDO::PARAM_INT);
    $stmt->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->fetch()) {
        header('Location: ../login.php?erro=Acesso negado.');
        exit();
    }
    // Inserir proposta
    $sql = "INSERT INTO negociacao_propostas (chat_id, remetente_id, valor, prazo, observacoes, status) VALUES (:chat_id, :remetente_id, :valor, :prazo, :observacoes, 'pendente')";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':chat_id', $chat_id, PDO::PARAM_INT);
    $stmt->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':valor', $valor);
    $stmt->bindParam(':prazo', $prazo);
    $stmt->bindParam(':observacoes', $observacoes);
    $stmt->execute();
    $proposta_id = $db->lastInsertId();
    // Mensagem de sistema no chat
    $sql = "INSERT INTO negociacao_mensagens (chat_id, remetente_id, mensagem, tipo, proposta_id) VALUES (:chat_id, :remetente_id, 'Enviou uma nova proposta.', 'proposta', :proposta_id)";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':chat_id', $chat_id, PDO::PARAM_INT);
    $stmt->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    header('Location: negociacao_chat.php?chat_id=' . $chat_id);
    exit();
}
header('Location: ../login.php');
exit();
