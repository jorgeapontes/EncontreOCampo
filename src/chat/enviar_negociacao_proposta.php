<?php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposta_id = intval($_POST['proposta_id'] ?? 0);
    $valor_frete = floatval($_POST['valor_frete'] ?? 0);
    if ($proposta_id <= 0 || $valor_frete <= 0) {
        header('Location: ../transportador/disponiveis.php?erro=Dados inválidos.');
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();

    // Buscar dados do transportador
    $sql_transportador = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id";
    $stmt = $db->prepare($sql_transportador);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $transportador = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transportador) {
        header('Location: ../transportador/disponiveis.php?erro=Transportador não encontrado.');
        exit();
    }
    $transportador_id = $transportador['id'];

    // Buscar dados da proposta original para obter entrega e comprador
    $sql_proposta = "SELECT p.id as proposta_id, e.id as entrega_id, e.comprador_id FROM propostas p INNER JOIN entregas e ON p.entrega_id = e.id WHERE p.id = :proposta_id";
    $stmt = $db->prepare($sql_proposta);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposta) {
        header('Location: ../transportador/disponiveis.php?erro=Proposta não encontrada.');
        exit();
    }
    $entrega_id = $proposta['entrega_id'];
    $comprador_id = $proposta['comprador_id'];

    // Verificar se já existe chat de negociação para esta entrega e transportador
    $sql_chat = "SELECT id FROM negociacao_chats WHERE entrega_id = :entrega_id AND comprador_id = :comprador_id AND transportador_id = :transportador_id";
    $stmt = $db->prepare($sql_chat);
    $stmt->bindParam(':entrega_id', $entrega_id, PDO::PARAM_INT);
    $stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt->execute();
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($chat) {
        $chat_id = $chat['id'];
    } else {
        // Criar novo chat
        $sql_create_chat = "INSERT INTO negociacao_chats (entrega_id, comprador_id, transportador_id, status) VALUES (:entrega_id, :comprador_id, :transportador_id, 'ativo')";
        $stmt_create = $db->prepare($sql_create_chat);
        $stmt_create->bindParam(':entrega_id', $entrega_id, PDO::PARAM_INT);
        $stmt_create->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
        $stmt_create->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
        $stmt_create->execute();
        $chat_id = $db->lastInsertId();
    }

    // Inserir proposta na negociação
    $sql_proposta_neg = "INSERT INTO negociacao_propostas (chat_id, valor, autor_id, tipo, status) VALUES (:chat_id, :valor, :autor_id, 'proposta', 'pendente')";
    $stmt = $db->prepare($sql_proposta_neg);
    $stmt->bindParam(':chat_id', $chat_id, PDO::PARAM_INT);
    $stmt->bindParam(':valor', $valor_frete);
    $stmt->bindParam(':autor_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();

    // Mensagem de sistema no chat
    $msg = "Proposta de frete enviada: R$ " . number_format($valor_frete, 2, ',', '.');
    $sql_msg = "INSERT INTO negociacao_mensagens (chat_id, remetente_id, mensagem, tipo) VALUES (:chat_id, :remetente_id, :mensagem, 'proposta')";
    $stmt = $db->prepare($sql_msg);
    $stmt->bindParam(':chat_id', $chat_id, PDO::PARAM_INT);
    $stmt->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':mensagem', $msg);
    $stmt->execute();

    // TODO: Notificar comprador

    header('Location: ../chat/negociacao_chat.php?chat_id=' . $chat_id);
    exit();
}
header('Location: ../transportador/disponiveis.php');
exit();
