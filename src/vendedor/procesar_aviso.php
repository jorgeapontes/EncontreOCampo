<?php
// src/vendedor/processar_aviso.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit();
}

// Obter o tipo de aviso
$tipo_aviso = $_POST['tipo_aviso'] ?? '';

if (empty($tipo_aviso)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de aviso não especificado']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar se já existe um registro de preferências para este usuário
    $sql_check = "SELECT id FROM usuario_avisos_preferencias WHERE usuario_id = :usuario_id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($existe) {
        // Atualizar registro existente
        $campo = '';
        switch ($tipo_aviso) {
            case 'regioes_entrega':
                $campo = 'aviso_regioes_entrega';
                break;
            // Adicione outros tipos de avisos aqui no futuro
            default:
                echo json_encode(['success' => false, 'message' => 'Tipo de aviso inválido']);
                exit();
        }
        
        $sql_update = "UPDATE usuario_avisos_preferencias SET $campo = 0 WHERE usuario_id = :usuario_id";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_update->execute();
    } else {
        // Criar novo registro
        $sql_insert = "INSERT INTO usuario_avisos_preferencias (usuario_id, aviso_regioes_entrega) VALUES (:usuario_id, 0)";
        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_insert->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Preferência salva com sucesso']);
    
} catch (PDOException $e) {
    error_log("Erro ao salvar preferência de aviso: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar preferência']);
}