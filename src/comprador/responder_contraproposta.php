<?php
// src/comprador/responder_contraproposta.php

session_start();
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['acao'])) {
    header("Location: minhas_propostas.php?erro=" . urlencode("Parâmetros inválidos."));
    exit();
}

$proposta_id = $_GET['id'];
$acao = $_GET['acao'];
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar se a proposta pertence ao comprador - CORRIGIDO: removida restrição de status
    $sql_verificar = "SELECT pn.id 
                     FROM propostas_negociacao pn
                     JOIN compradores c ON pn.comprador_id = c.id
                     WHERE pn.id = :proposta_id AND c.usuario_id = :usuario_id";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_verificar->execute();

    if ($stmt_verificar->rowCount() == 0) {
        header("Location: minhas_propostas.php?erro=" . urlencode("Proposta não encontrada ou você não tem permissão para responder."));
        exit();
    }

    // Atualizar o status com base na ação
    if ($acao === 'aceitar') {
        $novo_status = 'aceita';
        $mensagem_sucesso = "Contraproposta aceita com sucesso!";
    } elseif ($acao === 'recusar') {
        $novo_status = 'recusada';
        $mensagem_sucesso = "Contraproposta recusada com sucesso!";
    } else {
        header("Location: minhas_propostas.php?erro=" . urlencode("Ação inválida."));
        exit();
    }

    $sql_atualizar = "UPDATE propostas_negociacao 
                     SET status = :status, data_atualizacao = NOW() 
                     WHERE id = :proposta_id";
    
    $stmt_atualizar = $conn->prepare($sql_atualizar);
    $stmt_atualizar->bindParam(':status', $novo_status, PDO::PARAM_STR);
    $stmt_atualizar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_atualizar->execute();

    header("Location: minhas_propostas.php?sucesso=" . urlencode($mensagem_sucesso));
    exit();

} catch (PDOException $e) {
    header("Location: minhas_propostas.php?erro=" . urlencode("Erro ao processar resposta: " . $e->getMessage()));
    exit();
}
?>