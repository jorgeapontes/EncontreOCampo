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
$nao_exibir = isset($_POST['nao_exibir']) ? (int)$_POST['nao_exibir'] : 0;

if ($tipo_aviso !== 'regioes_entrega') {
    echo json_encode(['success' => false, 'message' => 'Tipo de aviso inválido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Valor a ser salvo (0 = não exibir, 1 = exibir)
    $valor_aviso = $nao_exibir ? 0 : 1;
    
    // Verificar se já existe registro
    $sql_check = "SELECT id FROM usuario_avisos_preferencias WHERE usuario_id = :usuario_id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($existe) {
        // Atualizar registro existente
        $sql_update = "UPDATE usuario_avisos_preferencias 
                       SET aviso_regioes_entrega = :valor, 
                           data_atualizacao = NOW() 
                       WHERE usuario_id = :usuario_id";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->bindParam(':valor', $valor_aviso, PDO::PARAM_INT);
        $stmt_update->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_update->execute();
        
        $affected = $stmt_update->rowCount();
    } else {
        // Inserir novo registro
        $sql_insert = "INSERT INTO usuario_avisos_preferencias 
                       (usuario_id, aviso_regioes_entrega, data_criacao) 
                       VALUES (:usuario_id, :valor, NOW())";
        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':valor', $valor_aviso, PDO::PARAM_INT);
        $stmt_insert->execute();
        
        $affected = $stmt_insert->rowCount();
    }
    
    if ($affected > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Preferência salva com sucesso',
            'nao_exibir' => $nao_exibir
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhuma alteração realizada']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao salvar preferência de aviso: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar preferência: ' . $e->getMessage()]);
}
?>