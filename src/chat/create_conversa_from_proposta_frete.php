<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    echo json_encode(['success' => false, 'erro' => 'Acesso negado']);
    exit();
}

$pf_id = isset($_POST['proposta_frete_id']) ? (int)$_POST['proposta_frete_id'] : 0;
if ($pf_id <= 0) {
    echo json_encode(['success' => false, 'erro' => 'Proposta de frete inválida']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Buscar proposta de frete, proposta e transportador.usuario_id
    $sql = "SELECT pf.id as pf_id, pf.transportador_id as transportador_sistema_id, p.ID as proposta_id, p.produto_id, p.comprador_id, p.vendedor_id, t.usuario_id as transportador_usuario_id, v.usuario_id as vendedor_usuario_id
            FROM propostas_frete_transportador pf
            JOIN propostas p ON pf.proposta_id = p.ID
            LEFT JOIN transportadores t ON pf.transportador_id = t.id
            LEFT JOIN vendedores v ON p.vendedor_id = v.id
            WHERE pf.id = :pf_id LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':pf_id', $pf_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'erro' => 'Proposta não encontrada']);
        exit();
    }

    // Verificar que o comprador é o dono da proposta
    $usuario_id = $_SESSION['usuario_id'];
    if ((int)$row['comprador_id'] !== (int)$usuario_id) {
        echo json_encode(['success' => false, 'erro' => 'Acesso negado']);
        exit();
    }

    $produto_id = (int)$row['produto_id'];
    $comprador_id = (int)$row['comprador_id'];
    $transportador_usuario_id = (int)($row['transportador_usuario_id'] ?? 0);
    $vendedor_usuario_id = (int)($row['vendedor_usuario_id'] ?? 0);

    if ($transportador_usuario_id <= 0) {
        echo json_encode(['success' => false, 'erro' => 'Transportador sem usuário associado']);
        exit();
    }

    // Verificar se já existe conversa
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
    $sql_ins = "INSERT INTO chat_conversas (produto_id, comprador_id, vendedor_id, transportador_id, ultima_mensagem_data) VALUES (:produto_id, :comprador_id, :vendedor_id, :transportador_id, NOW())";
    $stmt_ins = $conn->prepare($sql_ins);
    $stmt_ins->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':vendedor_id', $vendedor_usuario_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':transportador_id', $transportador_usuario_id, PDO::PARAM_INT);
    $stmt_ins->execute();

    $new_id = (int)$conn->lastInsertId();
    echo json_encode(['success' => true, 'conversa_id' => $new_id]);
    exit();

} catch (PDOException $e) {
    error_log('Erro create_conversa_from_proposta_frete: ' . $e->getMessage());
    echo json_encode(['success' => false, 'erro' => 'Erro ao criar conversa']);
    exit();
}

?>
