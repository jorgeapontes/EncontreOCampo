<?php
// src/vendedor/processar_aviso.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$tipo_aviso = $_POST['tipo_aviso'] ?? '';

if ($tipo_aviso !== 'regioes_entrega') {
    echo json_encode(['success' => false, 'message' => 'Tipo de aviso inválido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar se já existe registro
    $sql_check = "SELECT id FROM usuario_avisos_preferencias WHERE usuario_id = :usuario_id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($existe) {
        // Atualizar registro existente
        $sql_update = "UPDATE usuario_avisos_preferencias 
                       SET aviso_regioes_entrega = 0, 
                           data_atualizacao = NOW() 
                       WHERE usuario_id = :usuario_id";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_update->execute();
    } else {
        // Inserir novo registro
        $sql_insert = "INSERT INTO usuario_avisos_preferencias 
                       (usuario_id, aviso_regioes_entrega, data_criacao) 
                       VALUES (:usuario_id, 0, NOW())";
        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_insert->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Preferência salva com sucesso']);
    
} catch (PDOException $e) {
    error_log("Erro ao salvar preferência de aviso: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar preferência']);
}
?>