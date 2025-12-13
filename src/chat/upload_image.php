<?php
// src/chat/upload_image.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Configurações
$uploadDir = __DIR__ . '/../../uploads/chat/'; // Caminho absoluto para salvar
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

header('Content-Type: application/json');

// Validações básicas
if (!isset($_SESSION['usuario_id']) || !isset($_POST['conversa_id']) || !isset($_FILES['imagem'])) {
    echo json_encode(['success' => false, 'error' => 'Dados incompletos.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$conversa_id = (int)$_POST['conversa_id'];
$file = $_FILES['imagem'];

// Validar conversa (segurança básica para garantir que o usuário pertence à conversa)
$database = new Database();
$conn = $database->getConnection();
$sql_check = "SELECT id FROM chat_conversas WHERE id = :id AND (comprador_id = :uid OR vendedor_id = :uid)";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bindParam(':id', $conversa_id, PDO::PARAM_INT);
$stmt_check->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
$stmt_check->execute();
if (!$stmt_check->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Conversa inválida.']);
    exit;
}

// Validar arquivo
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo.']);
    exit;
}
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Arquivo muito grande (Máx 5MB).']);
    exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido.']);
    exit;
}

// Verificar/Criar diretório
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Gerar nome único e mover
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$newFilename = uniqid('img_') . '_' . time() . '.' . $extension;
$destination = $uploadDir . $newFilename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // CORRIGIDO: Caminho relativo à raiz do projeto
    // Se seu arquivo index.php está na raiz, use este caminho:
   $webPath = '/EncontreOCampo/uploads/chat/' . $newFilename;
    
    // Se precisar de caminho absoluto da web, descomente e ajuste:
    // $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    // $host = $_SERVER['HTTP_HOST'];
    // $webPath = $protocol . "://" . $host . "/uploads/chat/" . $newFilename;

    try {
        // Inserir no banco como tipo 'imagem'
        $sql = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) 
                VALUES (:conversa_id, :remetente_id, :caminho_imagem, 'imagem')";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt->bindParam(':remetente_id', $usuario_id, PDO::PARAM_INT);
        $stmt->bindParam(':caminho_imagem', $webPath);
        $stmt->execute();

        // Atualizar última mensagem da conversa
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

        echo json_encode(['success' => true, 'path' => $webPath]);
    } catch (PDOException $e) {
        // Se der erro no BD, tenta apagar a imagem enviada
        @unlink($destination);
        error_log("Erro ao salvar imagem no banco: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erro ao salvar no banco.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Falha ao mover arquivo.']);
}

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // DEBUG - REMOVER DEPOIS
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    
    error_log("=== DEBUG UPLOAD ===");
    error_log("Document Root: " . $documentRoot);
    error_log("Upload Dir: " . $uploadDir);
    error_log("Destination: " . $destination);
    error_log("File exists: " . (file_exists($destination) ? 'SIM' : 'NÃO'));
    error_log("URL completa: " . $protocol . "://" . $host . "../uploads/chat/" . $newFilename);
    // FIM DEBUG
    
    $webPath = '../uploads/chat/' . $newFilename;}
?>