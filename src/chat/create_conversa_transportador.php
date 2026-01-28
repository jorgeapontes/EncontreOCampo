<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar autenticação e tipo
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    echo json_encode(['success' => false, 'erro' => 'Acesso negado']);
    exit();
}

$proposta_id = isset($_POST['proposta_id']) ? (int)$_POST['proposta_id'] : 0;
if ($proposta_id <= 0) {
    echo json_encode(['success' => false, 'erro' => 'Proposta inválida']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
        // Buscar dados da proposta e produto + vendedor (id do vendedor)
        $sql = "SELECT p.produto_id, p.comprador_id, p.vendedor_id
            FROM propostas p
            WHERE p.ID = :proposta_id LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'erro' => 'Proposta não encontrada']);
        exit();
    }

    $produto_id = (int)$row['produto_id'];
    $comprador_id = (int)$row['comprador_id'];
    $vendedor_id = isset($row['vendedor_id']) ? (int)$row['vendedor_id'] : 0;
    $transportador_usuario_id = (int)$_SESSION['usuario_id'];

    // Verificar se já existe conversa com transportador
    $sql_check = "SELECT id FROM chat_conversas WHERE produto_id = :produto_id AND comprador_id = :comprador_id AND transportador_id = :transportador_id";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':transportador_id', $transportador_usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() > 0) {
        $conv = $stmt_check->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'conversa_id' => (int)$conv['id']]);
        exit();
    }

    // Criar nova conversa
    $sql_insert = "INSERT INTO chat_conversas (produto_id, comprador_id, vendedor_id, transportador_id, ultima_mensagem_data) VALUES (:produto_id, :comprador_id, :vendedor_id, :transportador_id, NOW())";
    $stmt_ins = $conn->prepare($sql_insert);
    $stmt_ins->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':transportador_id', $transportador_usuario_id, PDO::PARAM_INT);
    $stmt_ins->execute();

    $new_id = (int)$conn->lastInsertId();
    echo json_encode(['success' => true, 'conversa_id' => $new_id]);
    exit();

} catch (PDOException $e) {
    error_log('Erro create_conversa_transportador: ' . $e->getMessage());
    echo json_encode(['success' => false, 'erro' => 'Erro ao criar conversa']);
    exit();
}

?>
