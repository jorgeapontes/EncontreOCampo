<?php
// src/comprador/remover_favorito.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar se o usuário está logado como comprador
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

// Verificar se foi enviado um ID de favorito
if (!isset($_GET['favorito_id']) || empty($_GET['favorito_id'])) {
    $_SESSION['mensagem'] = "ID do favorito não informado.";
    $_SESSION['tipo_mensagem'] = "erro";
    header("Location: favoritos.php");
    exit();
}

$favorito_id = intval($_GET['favorito_id']);
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar se o favorito pertence ao usuário atual
    $sql_verificar = "SELECT id FROM favoritos WHERE id = :favorito_id AND usuario_id = :usuario_id";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':favorito_id', $favorito_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_verificar->execute();
    
    if ($stmt_verificar->rowCount() === 0) {
        $_SESSION['mensagem'] = "Favorito não encontrado ou você não tem permissão para removê-lo.";
        $_SESSION['tipo_mensagem'] = "erro";
        header("Location: favoritos.php");
        exit();
    }
    
    // Remover o favorito
    $sql_remover = "DELETE FROM favoritos WHERE id = :favorito_id";
    $stmt_remover = $conn->prepare($sql_remover);
    $stmt_remover->bindParam(':favorito_id', $favorito_id, PDO::PARAM_INT);
    
    if ($stmt_remover->execute()) {
        $_SESSION['mensagem'] = "Produto removido dos favoritos com sucesso!";
        $_SESSION['tipo_mensagem'] = "sucesso";
    } else {
        $_SESSION['mensagem'] = "Erro ao remover dos favoritos.";
        $_SESSION['tipo_mensagem'] = "erro";
    }
    
} catch (PDOException $e) {
    $_SESSION['mensagem'] = "Erro no servidor: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = "erro";
}

// Redirecionar de volta para a página de favoritos
header("Location: favoritos.php");
exit();
?>