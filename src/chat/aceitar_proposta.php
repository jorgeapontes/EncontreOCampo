<?php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php?erro=Acesso restrito.');
    exit();
}
$usuario_id = $_SESSION['usuario_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposta_id = intval($_POST['proposta_id'] ?? 0);
    if ($proposta_id <= 0) {
        header('Location: negociacao_chat.php?erro=Proposta inválida.');
        exit();
    }
    $database = new Database();
    $db = $database->getConnection();
    // Buscar proposta e chat
    $sql = "SELECT np.*, nc.comprador_id, nc.transportador_id FROM negociacao_propostas np JOIN negociacao_chats nc ON np.chat_id = nc.id WHERE np.id = :proposta_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposta) {
        header('Location: negociacao_chat.php?erro=Proposta não encontrada.');
        exit();
    }
    // Só o destinatário pode aceitar
    $destinatario_id = ($proposta['remetente_id'] == $proposta['comprador_id']) ? $proposta['transportador_id'] : $proposta['comprador_id'];
    if ($usuario_id != $destinatario_id) {
        header('Location: negociacao_chat.php?chat_id=' . $proposta['chat_id'] . '&erro=Sem permissão.');
        exit();
    }
    // Atualizar status
    $sql = "UPDATE negociacao_propostas SET status = 'aceita' WHERE id = :proposta_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    // Recusar outras propostas pendentes desse chat
    $sql = "UPDATE negociacao_propostas SET status = 'recusada' WHERE chat_id = :chat_id AND id != :proposta_id AND status = 'pendente'";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':chat_id', $proposta['chat_id'], PDO::PARAM_INT);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    // Mensagem de sistema
    $sql = "INSERT INTO negociacao_mensagens (chat_id, remetente_id, mensagem, tipo, proposta_id) VALUES (:chat_id, :remetente_id, 'Proposta aceita.', 'sistema', :proposta_id)";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':chat_id', $proposta['chat_id'], PDO::PARAM_INT);
    $stmt->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    header('Location: negociacao_chat.php?chat_id=' . $proposta['chat_id']);
    exit();
}
header('Location: ../login.php');
exit();
