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

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
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

    // Verificar propriedade da negociação com mais informações
    $sql_verificar = "SELECT pn.id, pn.status AS negociacao_status, 
                             pn.proposta_comprador_id, pn.proposta_vendedor_id,
                             pn.preco_final, pn.quantidade_final,
                             pc.status AS comprador_status,
                             pv.status AS vendedor_status
                      FROM propostas_negociacao pn
                      JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
                      LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.id
                      WHERE pn.id = :negociacao_id AND pc.comprador_id = :comprador_id";
    
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_verificar->execute();
    $negociacao = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

    if (!$negociacao) {
        redirecionar('erro', "Negociação não encontrada ou você não tem permissão.");
    }

    // Buscar a proposta do vendedor associada
    $sql_proposta_vendedor = "SELECT pv.* 
                             FROM propostas_vendedor pv
                             JOIN propostas_negociacao pn ON pv.id = pn.proposta_vendedor_id
                             WHERE pn.id = :negociacao_id";
    $stmt_proposta_vendedor = $conn->prepare($sql_proposta_vendedor);
    $stmt_proposta_vendedor->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt_proposta_vendedor->execute();
    $proposta_vendedor = $stmt_proposta_vendedor->fetch(PDO::FETCH_ASSOC);

    // Verificar se a proposta pode ser respondida
    if ($negociacao['negociacao_status'] !== 'negociacao' || $negociacao['comprador_status'] !== 'pendente') {
        redirecionar('erro', "Esta contraproposta não pode mais ser respondida.");
    }

    // Determinar novo status com base na ação
    $novo_status_negociacao = ($acao === 'aceitar') ? 'aceita' : 'recusada';
    $novo_status_comprador = ($acao === 'aceitar') ? 'aceita' : 'recusada';
    $novo_status_vendedor = ($acao === 'aceitar') ? 'aceita' : 'recusada';

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
        
        if ($acao === 'aceitar') {
            // 1. Atualizar status da proposta do comprador para 'finalizada'
            $sql_update_comprador = "UPDATE propostas_comprador 
                                    SET status = 'finalizada' 
                                    WHERE id = :proposta_id";
            
            $stmt_comprador_update = $conn->prepare($sql_update_comprador);
            $stmt_comprador_update->bindParam(':proposta_id', $negociacao['proposta_comprador_id']);
            $stmt_comprador_update->execute();
            
            // 2. Atualizar status da proposta do vendedor para 'aceita'
            if ($proposta_vendedor) {
                $sql_update_vendedor = "UPDATE propostas_vendedor 
                                    SET status = 'aceita'
                                    WHERE id = :proposta_vendedor_id";
                
                $stmt_vendedor_update = $conn->prepare($sql_update_vendedor);
                $stmt_vendedor_update->bindParam(':proposta_vendedor_id', $proposta_vendedor['id']);
                $stmt_vendedor_update->execute();
            }
            
            // 3. Atualizar status da negociação com preço e quantidade final da proposta do vendedor
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                    SET status = 'aceita',
                                        preco_final = :preco_final,
                                        quantidade_final = :quantidade_final,
                                        data_atualizacao = NOW()
                                    WHERE id = :negociacao_id";
            
            $stmt_negociacao_update = $conn->prepare($sql_update_negociacao);
            $stmt_negociacao_update->bindParam(':negociacao_id', $negociacao_id);
            
            if ($proposta_vendedor) {
                // Usa os valores da proposta do vendedor
                $stmt_negociacao_update->bindParam(':preco_final', $proposta_vendedor['preco_proposto']);
                $stmt_negociacao_update->bindParam(':quantidade_final', $proposta_vendedor['quantidade_proposta']);
            } else {
                // Fallback - usa os valores da proposta do comprador
                $preco_final = $negociacao['preco_final'] ?? 0;
                $quantidade_final = $negociacao['quantidade_final'] ?? 0;
                $stmt_negociacao_update->bindParam(':preco_final', $preco_final);
                $stmt_negociacao_update->bindParam(':quantidade_final', $quantidade_final);
            }
            
            $stmt_negociacao_update->execute();
        } else {
            // Se recusou, apenas atualizar status
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                     SET status = 'recusada',
                                         data_atualizacao = NOW()
                                     WHERE id = :negociacao_id";
            
            $stmt_negociacao_update = $conn->prepare($sql_update_negociacao);
            $stmt_negociacao_update->bindParam(':negociacao_id', $negociacao_id);
            $stmt_negociacao_update->execute();
        }
        
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