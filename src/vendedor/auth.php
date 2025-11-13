<?php
// src/vendedor/auth.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado E se o tipo é 'vendedor'
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    // Destrói a sessão e redireciona para a página inicial
    session_unset();
    session_destroy();
    
    // Mensagem de erro para a index.php (opcional)
    $_SESSION['erro_login'] = "Acesso negado. Faça login como Vendedor.";
    
    // Redireciona para a raiz
    header("Location: ../../index.php");
    exit();
}

// Inclui a conexão com o banco de dados e funções de sanitização
require_once '../conexao.php';

$database = new Database();
$db = $database->getConnection();

// ID do usuário logado
$usuario_id = $_SESSION['usuario_id'];

// Carregar dados específicos do VENDEDOR (usando JOIN com a tabela vendedores)
$query = "SELECT u.nome, u.email, v.* FROM usuarios u
          INNER JOIN vendedores v ON u.id = v.usuario_id
          WHERE u.id = :usuario_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':usuario_id', $usuario_id);
$stmt->execute();
$dados_vendedor = $stmt->fetch(PDO::FETCH_ASSOC);

// Define o nome de exibição na sessão
$_SESSION['vendedor_nome'] = $dados_vendedor['nome_comercial'] ?? $dados_vendedor['nome']; 

// Variável global para usar no dashboard
$vendedor = $dados_vendedor;
?>