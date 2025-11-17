<?php
// src/comprador/excluir_proposta.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

$database = new Database();
$conn = $database->getConnection();

// Função para redirecionar
function redirecionar($tipo, $mensagem) {
    header("Location: minhas_propostas.php?{$tipo}=" . urlencode($mensagem));
    exit();
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    redirecionar('erro', "Acesso negado. Faça login como Comprador.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirecionar('erro', "ID da proposta inválido.");
}

$proposta_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

// 2. VERIFICAR PROPRIEDADE E EXCLUIR
try {
    // Buscar ID do comprador
    $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if (!$comprador) {
        redirecionar('erro', "Perfil de comprador não encontrado.");
    }

    $comprador_id = $comprador['id'];

    // Verificar propriedade da proposta
    $sql_verificar = "SELECT id, status FROM propostas_negociacao 
                      WHERE id = :proposta_id AND comprador_id = :comprador_id";
    
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_verificar->execute();
    $proposta = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

    if (!$proposta) {
        redirecionar('erro', "Proposta não encontrada ou você não tem permissão para excluí-la.");
    }

    // Verificar se a proposta pode ser excluída (apenas pendente ou em negociação)
    if (!in_array($proposta['status'], ['pendente', 'negociacao'])) {
        redirecionar('erro', "Esta proposta não pode mais ser excluída.");
    }

    // 3. EXCLUIR PROPOSTA
    $sql_excluir = "DELETE FROM propostas_negociacao 
                    WHERE id = :proposta_id AND comprador_id = :comprador_id";
    
    $stmt_excluir = $conn->prepare($sql_excluir);
    $stmt_excluir->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_excluir->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    
    if ($stmt_excluir->execute()) {
        redirecionar('sucesso', "Proposta excluída com sucesso!");
    } else {
        redirecionar('erro', "Erro ao excluir proposta. Tente novamente.");
    }

} catch (PDOException $e) {
    error_log("Erro ao excluir proposta: " . $e->getMessage());
    redirecionar('erro', "Erro interno do sistema. Tente novamente.");
}
?>