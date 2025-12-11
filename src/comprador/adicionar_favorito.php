<?php
// src/comprador/adicionar_favorito.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar se o usuário está logado como comprador
if (!isset($_SESSION['usuario_id']) ) {
    $_SESSION['mensagem'] = "Faça login como comprador para adicionar aos favoritos.";
    $_SESSION['tipo_mensagem'] = "erro";
    header("Location: ../login.php");
    exit();
}

// Verificar se foi enviado um ID de produto
if (!isset($_GET['produto_id']) || empty($_GET['produto_id'])) {
    $_SESSION['mensagem'] = "ID do produto não informado.";
    $_SESSION['tipo_mensagem'] = "erro";
    header("Location: ../anuncios.php");
    exit();
}

$produto_id = intval($_GET['produto_id']);
$usuario_id = $_SESSION['usuario_id'];
$redirect_url = $_GET['redirect'] ?? 'favoritos.php';

$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar se o produto existe e está ativo
    $sql_verificar_produto = "SELECT id FROM produtos WHERE id = :produto_id AND status = 'ativo'";
    $stmt_verificar_produto = $conn->prepare($sql_verificar_produto);
    $stmt_verificar_produto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_verificar_produto->execute();
    
    if ($stmt_verificar_produto->rowCount() === 0) {
        $_SESSION['mensagem'] = "Produto não encontrado ou não está disponível.";
        $_SESSION['tipo_mensagem'] = "erro";
        header("Location: ../anuncios.php");
        exit();
    }
    
    // Verificar se já está favoritado
    $sql_verificar = "SELECT id FROM favoritos WHERE usuario_id = :usuario_id AND produto_id = :produto_id";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_verificar->execute();
    
    if ($stmt_verificar->rowCount() > 0) {
        $_SESSION['mensagem'] = "Este produto já está nos seus favoritos.";
        $_SESSION['tipo_mensagem'] = "info";
        header("Location: " . $redirect_url);
        exit();
    }
    
    // Adicionar aos favoritos
    $sql_adicionar = "INSERT INTO favoritos (usuario_id, produto_id) VALUES (:usuario_id, :produto_id)";
    $stmt_adicionar = $conn->prepare($sql_adicionar);
    $stmt_adicionar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_adicionar->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    
    if ($stmt_adicionar->execute()) {
        $_SESSION['mensagem'] = "Produto adicionado aos favoritos com sucesso!";
        $_SESSION['tipo_mensagem'] = "sucesso";
    } else {
        $_SESSION['mensagem'] = "Erro ao adicionar aos favoritos.";
        $_SESSION['tipo_mensagem'] = "erro";
    }
    
} catch (PDOException $e) {
    $_SESSION['mensagem'] = "Erro no servidor: " . $e->getMessage();
    $_SESSION['tipo_mensagem'] = "erro";
}

// Redirecionar de volta para a página de origem
header("Location: " . $redirect_url);
exit();
?>