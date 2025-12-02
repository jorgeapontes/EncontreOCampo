<?php
// src/comprador/excluir_proposta.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

$database = new Database();
$conn = $database->getConnection();

function redirecionar($tipo, $mensagem) {
    header("Location: minhas_propostas.php?{$tipo}=" . urlencode($mensagem));
    exit();
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    redirecionar('erro', "Acesso negado. Faça login como Comprador.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirecionar('erro', "ID da negociação inválido.");
}

$negociacao_id = (int)$_GET['id'];
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

    // Verificar propriedade da negociação
    $sql_verificar = "SELECT pn.id, pn.status AS negociacao_status, 
                             pn.proposta_comprador_id, pc.status AS comprador_status
                      FROM propostas_negociacao pn
                      JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
                      WHERE pn.id = :negociacao_id AND pc.comprador_id = :comprador_id";
    
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_verificar->execute();
    $negociacao = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

    if (!$negociacao) {
        redirecionar('erro', "Negociação não encontrada ou você não tem permissão para excluí-la.");
    }

    // Verificar se a proposta pode ser excluída
    // 1. Status na negociação deve ser 'negociacao'
    // 2. Status na proposta do comprador deve ser 'enviada'
    if ($negociacao['negociacao_status'] !== 'negociacao' || $negociacao['comprador_status'] !== 'enviada') {
        $status_msg = "Negociação: {$negociacao['negociacao_status']}, Proposta: {$negociacao['comprador_status']}";
        redirecionar('erro', "Esta proposta não pode mais ser excluída. " . $status_msg);
    }

    $proposta_comprador_id = $negociacao['proposta_comprador_id'];

    // 3. EXCLUIR PROPOSTA E NEGOCIAÇÃO RELACIONADA
    $conn->beginTransaction();
    
    try {
        // Se houver propostas do vendedor relacionadas, excluí-las primeiro
        $sql_excluir_vendedor = "DELETE FROM propostas_vendedor 
                                WHERE proposta_comprador_id = :proposta_comprador_id";
        $stmt_vendedor = $conn->prepare($sql_excluir_vendedor);
        $stmt_vendedor->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
        $stmt_vendedor->execute();
        
        // Excluir a negociação
        $sql_excluir_negociacao = "DELETE FROM propostas_negociacao 
                                  WHERE id = :negociacao_id";
        $stmt_negociacao = $conn->prepare($sql_excluir_negociacao);
        $stmt_negociacao->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
        $stmt_negociacao->execute();
        
        // Finalmente, excluir a proposta do comprador
        $sql_excluir_comprador = "DELETE FROM propostas_comprador 
                                 WHERE id = :proposta_comprador_id AND comprador_id = :comprador_id";
        
        $stmt_excluir = $conn->prepare($sql_excluir_comprador);
        $stmt_excluir->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
        $stmt_excluir->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
        
        if ($stmt_excluir->execute()) {
            $conn->commit();
            redirecionar('sucesso', "Proposta excluída com sucesso!");
        } else {
            $conn->rollBack();
            redirecionar('erro', "Erro ao excluir proposta. Tente novamente.");
        }

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro ao excluir proposta: " . $e->getMessage());
    redirecionar('erro', "Erro interno do sistema. Tente novamente.");
}
?>