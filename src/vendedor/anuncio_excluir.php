<?php
// src/vendedor/anuncio_excluir.php

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
if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do anúncio não fornecido.']);
    exit();
}

$anuncio_id = intval($_POST['id']);

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verificar se o vendedor tem permissão para excluir este anúncio
    $sql_verificar = "SELECT p.id, p.imagem_url FROM produtos p 
                     INNER JOIN vendedores v ON p.vendedor_id = v.id 
                     WHERE p.id = :anuncio_id AND v.usuario_id = :usuario_id";
    
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_verificar->execute();
    
    if ($stmt_verificar->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Anúncio não encontrado ou você não tem permissão para excluí-lo.']);
        exit();
    }
    
    $anuncio = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
    
    // Excluir a imagem do servidor (se existir e não for placeholder)
    if (!empty($anuncio['imagem_url']) && strpos($anuncio['imagem_url'], 'placeholder.png') === false) {
        $caminho_imagem = realpath(dirname(__FILE__) . '/../' . $anuncio['imagem_url']);
        if ($caminho_imagem && file_exists($caminho_imagem)) {
            @unlink($caminho_imagem);
        }
    }
    
    // Excluir propostas relacionadas (se existirem)
    try {
        $sql_excluir_propostas = "DELETE FROM propostas WHERE produto_id = :anuncio_id";
        $stmt_excluir_propostas = $conn->prepare($sql_excluir_propostas);
        $stmt_excluir_propostas->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
        $stmt_excluir_propostas->execute();
    } catch (Exception $e) {
        // Continuar mesmo se não conseguir excluir propostas
        error_log("Aviso: Não foi possível excluir propostas do anúncio " . $anuncio_id . ": " . $e->getMessage());
    }
    
    // Excluir o anúncio
    $sql_excluir = "DELETE FROM produtos WHERE id = :id";
    $stmt_excluir = $conn->prepare($sql_excluir);
    $stmt_excluir->bindParam(':id', $anuncio_id, PDO::PARAM_INT);
    
    if ($stmt_excluir->execute()) {
        // Registrar ação no log (opcional)
        $sql_log = "INSERT INTO log_alteracoes (usuario_id, acao, detalhes, data) 
                   VALUES (:usuario_id, 'EXCLUIR_ANUNCIO', 
                   CONCAT('Anúncio excluído - ID: ', :anuncio_id), NOW())";
        
        try {
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
            $stmt_log->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
            $stmt_log->execute();
        } catch (Exception $e) {
            // Não falhar se o log não funcionar
            error_log("Erro ao registrar log: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Anúncio excluído com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir o anúncio.']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao excluir anúncio: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}