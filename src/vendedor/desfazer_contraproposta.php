<?php
// src/vendedor/desfazer_contraproposta.php - VERSÃO DEFINITIVA

session_start();
require_once __DIR__ . '/../conexao.php';

function redirecionar($id, $tipo, $mensagem) {
    header("Location: detalhes_proposta.php?id=" . $id . "&" . $tipo . "=" . urlencode($mensagem));
    exit();
}

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: propostas.php?erro=" . urlencode("ID da proposta inválido."));
    exit();
}

$proposta_comprador_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

try {
    // 1. Buscar todos os dados necessários ANTES de qualquer operação
    $sql_dados = "SELECT 
                    pv.id AS proposta_vendedor_id,
                    pn.id AS negociacao_id,
                    pn.produto_id,
                    pn.proposta_comprador_id,
                    pc.status AS status_comprador,
                    p.vendedor_id
                  FROM propostas_vendedor pv
                  JOIN propostas_negociacao pn ON pv.proposta_comprador_id = pn.proposta_comprador_id
                  JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
                  JOIN produtos p ON pn.produto_id = p.id
                  WHERE pv.proposta_comprador_id = :proposta_comprador_id 
                  AND p.vendedor_id = (SELECT id FROM vendedores WHERE usuario_id = :usuario_id)
                  LIMIT 1";
    
    $stmt_dados = $conn->prepare($sql_dados);
    $stmt_dados->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
    $stmt_dados->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_dados->execute();
    $dados = $stmt_dados->fetch(PDO::FETCH_ASSOC);
    
    if (!$dados) {
        redirecionar($proposta_comprador_id, 'erro', "Contraproposta não encontrada ou acesso negado.");
    }
    
    // Extrair dados
    $proposta_vendedor_id = $dados['proposta_vendedor_id'];
    $negociacao_id = $dados['negociacao_id'];
    $produto_id = $dados['produto_id'];
    
    // Iniciar transação
    $conn->beginTransaction();
    
    try {
        // ESTRATÉGIA: Primeiro desconectar, depois excluir
        
        // A) SETAR proposta_vendedor_id como NULL na negociação
        $sql_desvincular = "UPDATE propostas_negociacao 
                           SET proposta_vendedor_id = NULL,
                               status = 'negociacao',
                               data_atualizacao = NOW()
                           WHERE id = :negociacao_id";
        
        $stmt_desvincular = $conn->prepare($sql_desvincular);
        $stmt_desvincular->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
        
        if (!$stmt_desvincular->execute()) {
            throw new Exception("Falha ao desvincular negociação");
        }
        
        // B) Atualizar proposta do comprador
        $sql_atualizar_comprador = "UPDATE propostas_comprador 
                                   SET status = 'enviada'
                                   WHERE id = :proposta_comprador_id";
        
        $stmt_atualizar = $conn->prepare($sql_atualizar_comprador);
        $stmt_atualizar->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
        
        if (!$stmt_atualizar->execute()) {
            throw new Exception("Falha ao atualizar proposta do comprador");
        }
        
        // C) SOMENTE AGORA excluir a proposta do vendedor
        $sql_excluir_vendedor = "DELETE FROM propostas_vendedor 
                                WHERE id = :proposta_vendedor_id";
        
        $stmt_excluir = $conn->prepare($sql_excluir_vendedor);
        $stmt_excluir->bindParam(':proposta_vendedor_id', $proposta_vendedor_id, PDO::PARAM_INT);
        
        if (!$stmt_excluir->execute()) {
            throw new Exception("Falha ao excluir proposta do vendedor");
        }
        
        // Verificar se a negociação ainda existe (por causa do CASCADE)
        $sql_verificar = "SELECT COUNT(*) as total FROM propostas_negociacao WHERE id = :negociacao_id";
        $stmt_verificar = $conn->prepare($sql_verificar);
        $stmt_verificar->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
        $stmt_verificar->execute();
        $resultado = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
        
        // Se a negociação foi excluída pelo CASCADE, recriá-la
        if ($resultado['total'] == 0) {
            $sql_recriar = "INSERT INTO propostas_negociacao 
                           (produto_id, proposta_comprador_id, status, data_criacao)
                           VALUES (:produto_id, :proposta_comprador_id, 'negociacao', NOW())";
            
            $stmt_recriar = $conn->prepare($sql_recriar);
            $stmt_recriar->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_recriar->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
            
            if (!$stmt_recriar->execute()) {
                throw new Exception("Falha ao recriar negociação");
            }
        }
        
        $conn->commit();
        
        redirecionar($proposta_comprador_id, 'sucesso', "Contraproposta desfeita com sucesso! A proposta voltou ao estado inicial.");
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro ao desfazer contraproposta: " . $e->getMessage());
    redirecionar($proposta_comprador_id, 'erro', "Erro interno do sistema. Tente novamente.");
} catch (Exception $e) {
    error_log("Exception ao desfazer contraproposta: " . $e->getMessage());
    redirecionar($proposta_comprador_id, 'erro', $e->getMessage());
}
?>