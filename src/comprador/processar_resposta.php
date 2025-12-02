
<?php
// src/comprador/processar_resposta.php

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

if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['action'])) {
    redirecionar('erro', "Parâmetros inválidos.");
}

$negociacao_id = (int)$_GET['id'];
$acao = $_GET['action'];
$usuario_id = $_SESSION['usuario_id'];

// Ações válidas
$acoes_validas = ['aceitar', 'recusar'];
if (!in_array($acao, $acoes_validas)) {
    redirecionar('erro', "Ação inválida.");
}

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
        redirecionar('erro', "Negociação não encontrada ou você não tem permissão.");
    }

    // Verificar se a proposta pode ser respondida
    // 1. Status na negociação deve ser 'negociacao'
    // 2. Status na proposta do comprador deve ser 'pendente'
    if ($negociacao['negociacao_status'] !== 'negociacao' || $negociacao['comprador_status'] !== 'pendente') {
        redirecionar('erro', "Esta contraproposta não pode mais ser respondida.");
    }

    // Determinar novo status com base na ação
    $novo_status_negociacao = ($acao === 'aceitar') ? 'aceita' : 'recusada';
    $novo_status_comprador = ($acao === 'aceitar') ? 'aceita' : 'recusada';

    // Atualizar os status
    $conn->beginTransaction();
    
    try {
        // Atualizar status da proposta do comprador
        $sql_update_comprador = "UPDATE propostas_comprador 
                                SET status = :novo_status 
                                WHERE id = :proposta_id";
        
        $stmt_comprador_update = $conn->prepare($sql_update_comprador);
        $stmt_comprador_update->bindParam(':novo_status', $novo_status_comprador);
        $stmt_comprador_update->bindParam(':proposta_id', $negociacao['proposta_comprador_id']);
        $stmt_comprador_update->execute();
        
        // Atualizar status da negociação
        $sql_update_negociacao = "UPDATE propostas_negociacao 
                                 SET status = :novo_status,
                                     data_atualizacao = NOW()
                                 WHERE id = :negociacao_id";
        
        $stmt_negociacao_update = $conn->prepare($sql_update_negociacao);
        $stmt_negociacao_update->bindParam(':novo_status', $novo_status_negociacao);
        $stmt_negociacao_update->bindParam(':negociacao_id', $negociacao_id);
        $stmt_negociacao_update->execute();
        
        $conn->commit();
        
        $mensagem = ($acao === 'aceitar') 
            ? "Contraproposta aceita com sucesso!" 
            : "Contraproposta recusada com sucesso!";
            
        redirecionar('sucesso', $mensagem);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro ao processar resposta: " . $e->getMessage());
    redirecionar('erro', "Erro interno do sistema. Tente novamente.");
}
?>