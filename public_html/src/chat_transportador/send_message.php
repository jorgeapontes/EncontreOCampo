<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../chat/chat_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatErro(405, 'Método inválido');
}

verificarLoginChat();
chatExigirCsrf();

$conversa_id = isset($_POST['conversa_id']) ? (int)$_POST['conversa_id'] : 0;
$mensagem = isset($_POST['mensagem']) ? trim($_POST['mensagem']) : '';
$tipo = 'texto';
$usuario_id = (int)$_SESSION['usuario_id'];

chatLiberarSessao();

if ($conversa_id <= 0) {
    chatErro(400, 'Conversa inválida');
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
        chatErro(403, 'Acesso negado');
    }

    // Tratar upload de imagem, se existir (tipo validado pelo conteúdo do
    // arquivo, não pelo MIME que o navegador informa)
    if (!empty($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
        $filename = chatSalvarImagem($_FILES['imagem'], 'chat_');

        // Caminho público relativo usado pelo cliente
        $mensagem = '../../uploads/chat/' . $filename;
        $tipo = 'imagem';
    }

    // Se não for imagem, garantir que haja texto
    if ($tipo === 'texto' && $mensagem === '') {
        chatErro(400, 'Mensagem vazia');
    }
    if ($tipo === 'texto' && mb_strlen($mensagem) > CHAT_TAMANHO_MAX_MENSAGEM) {
        chatErro(400, 'Mensagem muito longa (máx. ' . CHAT_TAMANHO_MAX_MENSAGEM . ' caracteres).');
    }

    // Inserir mensagem
    $sql_insert = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) VALUES (:conversa_id, :remetente_id, :mensagem, :tipo)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':mensagem', $mensagem);
    $stmt_insert->bindParam(':tipo', $tipo);
    $stmt_insert->execute();
    $mensagem_id = (int)$conn->lastInsertId();

    // Atualizar última mensagem na conversa
    $sql_update = "UPDATE chat_conversas SET ultima_mensagem = :mensagem, ultima_mensagem_data = NOW() WHERE id = :conversa_id";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bindParam(':mensagem', $mensagem);
    $stmt_update->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_update->execute();

    echo json_encode([
        'success' => true,
        'mensagem_id' => $mensagem_id,
        'path' => $tipo === 'imagem' ? $mensagem : null,
        'data_envio' => chatDataEnvio($conn, $mensagem_id)
    ]);
    exit();

} catch (PDOException $e) {
    error_log('Erro send_message transportador: ' . $e->getMessage());
    chatErro(500, 'Erro ao enviar mensagem');
}

?>
