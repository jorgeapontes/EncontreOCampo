<?php
// src/transportador/toggle_favorito.php
session_start();
require_once __DIR__ . '/../conexao.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    echo json_encode(['success' => false, 'erro' => 'Acesso negado']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$proposta_id = isset($input['proposta_id']) ? (int)$input['proposta_id'] : 0;
if ($proposta_id <= 0) {
    echo json_encode(['success' => false, 'erro' => 'ID inválido']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// garantir que a tabela exista (migração simples)
try {
    $sql_create = "CREATE TABLE IF NOT EXISTS transportador_favoritos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transportador_id INT NOT NULL,
        proposta_id INT NOT NULL,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ux_transportador_proposta (transportador_id, proposta_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sql_create);
} catch (PDOException $e) {
    // se falhar, continuar e dar erro depois
}

try {
    // buscar transportador id
    $sql_t = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id LIMIT 1";
    $stmt_t = $db->prepare($sql_t);
    $stmt_t->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_t->execute();
    $t = $stmt_t->fetch(PDO::FETCH_ASSOC);
    if (!$t) { echo json_encode(['success'=>false,'erro'=>'Transportador não encontrado']); exit(); }
    $transportador_id = (int)$t['id'];

    // verificar se já existe
    $sql_check = "SELECT id FROM transportador_favoritos WHERE transportador_id = :transportador_id AND proposta_id = :proposta_id LIMIT 1";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $exists = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        // remover
        $sql_del = "DELETE FROM transportador_favoritos WHERE id = :id";
        $stmt_del = $db->prepare($sql_del);
        $stmt_del->bindParam(':id', $exists['id'], PDO::PARAM_INT);
        $stmt_del->execute();
        echo json_encode(['success' => true, 'favorited' => false]);
        exit();
    } else {
        // inserir
        $sql_ins = "INSERT INTO transportador_favoritos (transportador_id, proposta_id) VALUES (:transportador_id, :proposta_id)";
        $stmt_ins = $db->prepare($sql_ins);
        $stmt_ins->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
        $stmt_ins->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
        $stmt_ins->execute();
        echo json_encode(['success' => true, 'favorited' => true]);
        exit();
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'erro' => 'Erro no servidor: ' . $e->getMessage()]);
    exit();
}

?>