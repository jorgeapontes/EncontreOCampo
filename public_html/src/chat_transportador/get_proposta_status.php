<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../chat/chat_config.php';

header('Content-Type: application/json');

verificarLoginChat();

if (!isset($_GET['id'])) {
    chatErro(400, 'ID não fornecido');
}

$proposta_id = (int)$_GET['id'];
$usuario_id = (int)$_SESSION['usuario_id'];
chatLiberarSessao();

$database = new Database();
$conn = $database->getConnection();

try {
    // Buscar status da proposta, só para o comprador dela ou o transportador que a enviou
    $sql = "SELECT pt.status FROM propostas_transportadores pt
            JOIN propostas p ON pt.proposta_id = p.ID
            LEFT JOIN transportadores t ON pt.transportador_id = t.id
            WHERE pt.id = :id AND (p.comprador_id = :uid OR t.usuario_id = :uid)
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
    $stmt->bindParam(':uid', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode(['success' => true, 'status' => $row['status']]);
    } else {
        echo json_encode(['success' => true, 'status' => 'nao_encontrada']);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar status da proposta (id={$proposta_id}): " . $e->getMessage());
    chatErro(500, 'Não foi possível verificar o status da proposta.');
}
?>
