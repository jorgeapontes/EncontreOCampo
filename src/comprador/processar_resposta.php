<?php
// src/comprador/processar_resposta.php

session_start();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/send_notification.php';

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
                             pn.produto_id,
                             pc.status AS comprador_status,
                             pv.status AS vendedor_status,
                             p.nome AS produto_nome,
                             p.vendedor_id
                      FROM propostas_negociacao pn
                      JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
                      LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.id
                      JOIN produtos p ON pn.produto_id = p.id
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

    // Buscar informações para notificação
    // Buscar informações do vendedor
    $sqlVendedorInfo = "SELECT u.nome, u.email FROM usuarios u 
                       JOIN vendedores v ON u.id = v.usuario_id 
                       WHERE v.id = :vendedor_id";
    $stmtVendedorInfo = $conn->prepare($sqlVendedorInfo);
    $stmtVendedorInfo->bindParam(':vendedor_id', $negociacao['vendedor_id'], PDO::PARAM_INT);
    $stmtVendedorInfo->execute();
    $vendedorInfo = $stmtVendedorInfo->fetch(PDO::FETCH_ASSOC);
    
    // Buscar informações do comprador
    $sqlCompradorInfo = "SELECT u.nome, u.email FROM usuarios u 
                        JOIN compradores c ON u.id = c.usuario_id 
                        WHERE c.id = :comprador_id";
    $stmtCompradorInfo = $conn->prepare($sqlCompradorInfo);
    $stmtCompradorInfo->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmtCompradorInfo->execute();
    $compradorInfo = $stmtCompradorInfo->fetch(PDO::FETCH_ASSOC);

    // Verificar se a proposta pode ser respondida
    if ($negociacao['negociacao_status'] !== 'negociacao' || $negociacao['comprador_status'] !== 'pendente') {
        redirecionar('erro', "Esta contraproposta não pode mais ser respondida.");
    }

    // Atualizar os status
    $conn->beginTransaction();
    
    try {
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
                $stmt_negociacao_update->bindParam(':preco_final', $proposta_vendedor['preco_proposto']);
                $stmt_negociacao_update->bindParam(':quantidade_final', $proposta_vendedor['quantidade_proposta']);
            } else {
                $preco_final = $negociacao['preco_final'] ?? 0;
                $quantidade_final = $negociacao['quantidade_final'] ?? 0;
                $stmt_negociacao_update->bindParam(':preco_final', $preco_final);
                $stmt_negociacao_update->bindParam(':quantidade_final', $quantidade_final);
            }
            
            $stmt_negociacao_update->execute();
            
            // Enviar notificação de aceitação para o vendedor
            if ($vendedorInfo && isset($vendedorInfo['email']) && $compradorInfo && isset($compradorInfo['email'])) {
                enviarEmailNotificacao(
                    $vendedorInfo['email'],
                    $vendedorInfo['nome'],
                    'Contraproposta Aceita - ' . htmlspecialchars($negociacao['produto_nome']),
                    'O comprador ' . $compradorInfo['nome'] . ' aceitou sua contraproposta para o produto ' . 
                    htmlspecialchars($negociacao['produto_nome']) . '. A negociação foi finalizada com sucesso!'
                );
            }
        } else {
            // Se recusou, apenas atualizar status
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                     SET status = 'recusada',
                                         data_atualizacao = NOW()
                                     WHERE id = :negociacao_id";
            
            $stmt_negociacao_update = $conn->prepare($sql_update_negociacao);
            $stmt_negociacao_update->bindParam(':negociacao_id', $negociacao_id);
            $stmt_negociacao_update->execute();
            
            // Enviar notificação de recusa para o vendedor
            if ($vendedorInfo && isset($vendedorInfo['email']) && $compradorInfo && isset($compradorInfo['email'])) {
                enviarEmailNotificacao(
                    $vendedorInfo['email'],
                    $vendedorInfo['nome'],
                    'Contraproposta Recusada - ' . htmlspecialchars($negociacao['produto_nome']),
                    'O comprador ' . $compradorInfo['nome'] . ' recusou sua contraproposta para o produto ' . 
                    htmlspecialchars($negociacao['produto_nome']) . '. Você pode fazer uma nova contraproposta se desejar.'
                );
            }
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