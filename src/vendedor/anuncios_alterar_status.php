<?php
// src/vendedor/anuncio_alterar_status.php

session_start();
require_once '../conexao.php';

// Configurar cabeçalhos para JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar se o usuário está logado como vendedor
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

// Validar dados recebidos
if (!isset($_POST['id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit();
}

$anuncio_id = intval($_POST['id']);
$novo_status = $_POST['status'];

// Validar status
$status_permitidos = ['ativo', 'inativo', 'pendente'];
if (!in_array($novo_status, $status_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Status inválido.']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verificar se o vendedor tem permissão para alterar este anúncio
    $sql_verificar = "SELECT p.id FROM produtos p 
                     INNER JOIN vendedores v ON p.vendedor_id = v.id 
                     WHERE p.id = :anuncio_id AND v.usuario_id = :usuario_id";
    
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_verificar->execute();
    
    if ($stmt_verificar->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Anúncio não encontrado ou você não tem permissão para alterá-lo.']);
        exit();
    }
    
    // Atualizar o status do anúncio
    $sql_atualizar = "UPDATE produtos SET status = :status WHERE id = :id";
    $stmt_atualizar = $conn->prepare($sql_atualizar);
    $stmt_atualizar->bindParam(':status', $novo_status, PDO::PARAM_STR);
    $stmt_atualizar->bindParam(':id', $anuncio_id, PDO::PARAM_INT);
    
    if ($stmt_atualizar->execute()) {
        // Registrar ação no log (opcional)
        $sql_log = "INSERT INTO log_alteracoes (usuario_id, acao, detalhes, data) 
                   VALUES (:usuario_id, 'ALTERAR_STATUS_ANUNCIO', 
                   CONCAT('Anúncio ID: ', :anuncio_id, ', Novo status: ', :status), NOW())";
        
        try {
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
            $stmt_log->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
            $stmt_log->bindParam(':status', $novo_status, PDO::PARAM_STR);
            $stmt_log->execute();
        } catch (Exception $e) {
            // Não falhar se o log não funcionar
            error_log("Erro ao registrar log: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Status alterado com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o status.']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao alterar status do anúncio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}