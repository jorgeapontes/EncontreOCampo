<?php
// src/comprador/responder_contraproposta.php - REDIRECIONADOR

session_start();

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['acao'])) {
    header("Location: minhas_propostas.php?erro=" . urlencode("Parâmetros inválidos."));
    exit();
}

$negociacao_id = $_GET['id'];
$acao = $_GET['acao'];

// Redireciona para o novo processador
header("Location: processar_resposta.php?id={$negociacao_id}&action={$acao}");
exit();
?>