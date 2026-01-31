<?php
// get_user_details.php
session_start();

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

require_once __DIR__ . '/../conexao.php';

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID do usuário não fornecido']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$user_id = $_GET['id'];

try {
    // Buscar dados do usuário
    $sql = "SELECT * FROM usuarios WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo json_encode(['error' => 'Usuário não encontrado']);
        exit();
    }
    
    // Buscar dados específicos do tipo de usuário (se houver tabela separada)
    $detalhes = [];
    $tipo = $usuario['tipo'];
    
    if ($tipo === 'comprador') {
        $sql_detalhes = "SELECT * FROM compradores WHERE usuario_id = :id";
    } elseif ($tipo === 'vendedor') {
        $sql_detalhes = "SELECT * FROM vendedores WHERE usuario_id = :id";
    } elseif ($tipo === 'transportador') {
        $sql_detalhes = "SELECT * FROM transportadores WHERE usuario_id = :id";
    } else {
        $sql_detalhes = null;
    }
    
    if ($sql_detalhes) {
        $stmt_detalhes = $conn->prepare($sql_detalhes);
        $stmt_detalhes->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt_detalhes->execute();
        $detalhes = $stmt_detalhes->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    // Preparar resposta
    $response = [
        'success' => true,
        'usuario' => [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'tipo' => $usuario['tipo'],
            'status' => $usuario['status'],
            'data_criacao' => $usuario['data_criacao'],
            'foto_rosto' => $usuario['foto_rosto'] ?? null,
            'foto_documento_frente' => $usuario['foto_documento_frente'] ?? null,
            'foto_documento_verso' => $usuario['foto_documento_verso'] ?? null
        ],
        'detalhes' => $detalhes
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao buscar dados: ' . $e->getMessage()]);
}