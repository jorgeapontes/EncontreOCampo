<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/chat_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatErro(405, 'Método inválido');
}

verificarLoginChat();
chatExigirCsrf();

$usuario_id = (int)$_SESSION['usuario_id'];
chatLiberarSessao();

if (!isset($_POST['conversa_id']) || !isset($_FILES['imagem'])) {
    chatErro(400, 'Dados incompletos.');
}

$conversa_id = (int)$_POST['conversa_id'];

$database = new Database();
$conn = $database->getConnection();
$sql_check = "SELECT id FROM chat_conversas WHERE id = :id AND (comprador_id = :uid OR vendedor_id = :uid)";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bindParam(':id', $conversa_id, PDO::PARAM_INT);
$stmt_check->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
$stmt_check->execute();
if (!$stmt_check->fetch()) {
    chatErro(403, 'Conversa inválida.');
}

$newFilename = chatSalvarImagem($_FILES['imagem'], 'img_');
$destination = __DIR__ . '/../../uploads/chat/' . $newFilename;

// Caminho relativo à raiz do site (public_html), sem o prefixo de pasta
// local de desenvolvimento que não existe em produção.
$webPath = '/uploads/chat/' . $newFilename;

try {
    $sql = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo)
            VALUES (:conversa_id, :remetente_id, :caminho_imagem, 'imagem')";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
    $stmt->bindParam(':caminho_imagem', $webPath);
    $stmt->execute();
    $mensagem_id = (int)$conn->lastInsertId();

    $sql_update = "UPDATE chat_conversas
                  SET ultima_mensagem = '[Imagem]',
                      ultima_mensagem_data = NOW(),
                      comprador_lido = IF(comprador_id = :usuario_id, 1, 0),
                      vendedor_lido = IF(vendedor_id = :usuario_id, 1, 0)
                  WHERE id = :id";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bindParam(':id', $conversa_id, PDO::PARAM_INT);
    $stmt_update->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_update->execute();

    echo json_encode([
        'success' => true,
        'path' => $webPath,
        'mensagem_id' => $mensagem_id,
        'data_envio' => chatDataEnvio($conn, $mensagem_id)
    ]);
} catch (PDOException $e) {
    // Se der erro no BD, tenta apagar a imagem enviada
    @unlink($destination);
    error_log("Erro ao salvar imagem no banco: " . $e->getMessage());
    chatErro(500, 'Erro ao salvar no banco.');
}
?>
