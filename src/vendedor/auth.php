<?php
// src/vendedor/auth.php

require_once __DIR__ . '/../conexao.php'; // inicia a sessão certa (EOC_SESSID)

// Verifica se o usuário está logado E se o tipo é 'vendedor'
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {

    // Mensagem de erro para a index.php — precisa ser gravada ANTES de destruir a sessão,
    // senão ela nunca chega até o redirecionamento
    session_unset();
    $_SESSION['erro_login'] = "Acesso negado. Faça login como Vendedor.";

    // Destrói a sessão e redireciona para a página inicial
    session_destroy();

    // Redireciona para a raiz
    header("Location: ../../index.php");
    exit();
}

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