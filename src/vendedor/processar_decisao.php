<?php
// src/vendedor/processar_decisao.php

session_start();
require_once __DIR__ . '/../conexao.php';

function redirecionar($id, $tipo, $mensagem) {
    header("Location: detalhes_proposta.php?id=" . $id . "&" . $tipo . "=" . urlencode($mensagem));
    exit();
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: propostas.php?erro=" . urlencode("ID da proposta inválido."));
    exit();
}

if (!isset($_GET['action'])) {
    header("Location: propostas.php?erro=" . urlencode("Ação não especificada."));
    exit();
}

$proposta_comprador_id = (int)$_GET['id'];
$acao = $_GET['action'];
$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

// Ações válidas
$acoes_validas = ['aceitar', 'recusar', 'contraproposta'];
if (!in_array($acao, $acoes_validas)) {
    redirecionar($proposta_comprador_id, 'erro', "Ação inválida.");
}

try {
    // Primeiro, obtém o ID do vendedor
    $sql_vendedor = "SELECT id FROM vendedores WHERE usuario_id = :usuario_id";
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor) {
        redirecionar($proposta_comprador_id, 'erro', "Vendedor não encontrado.");
    }

    $vendedor_id = $vendedor['id'];

    // Buscar dados da proposta atual
    $sql_proposta = "SELECT 
                        pc.*,
                        pn.id AS negociacao_id,
                        pn.produto_id,  -- ADICIONADO: produto_id da negociação
                        pn.status AS negociacao_status,
                        p.nome AS produto_nome,
                        p.vendedor_id AS produto_vendedor_id
                    FROM propostas_comprador pc
                    JOIN propostas_negociacao pn ON pc.id = pn.proposta_comprador_id
                    JOIN produtos p ON pn.produto_id = p.id  -- ADICIONADO: JOIN com produtos
                    WHERE pc.id = :proposta_comprador_id AND p.vendedor_id = :vendedor_id";
    
    $stmt_proposta = $conn->prepare($sql_proposta);
    $stmt_proposta->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
    $stmt_proposta->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_proposta->execute();
    $proposta = $stmt_proposta->fetch(PDO::FETCH_ASSOC);

    if (!$proposta) {
        redirecionar($proposta_comprador_id, 'erro', "Proposta não encontrada ou acesso negado.");
    }

    // Verificar se a proposta pode ser processada
    if ($proposta['negociacao_status'] !== 'negociacao') {
        redirecionar($proposta_comprador_id, 'erro', "Esta proposta não pode mais ser processada.");
    }

    // Iniciar transação
    $conn->beginTransaction();
    
    try {
        if ($acao === 'aceitar') {
            // Verificar se há contraproposta do vendedor ativa
            $sql_check_vendedor = "SELECT pv.id, pv.status 
                                FROM propostas_vendedor pv
                                JOIN propostas_negociacao pn ON pv.id = pn.proposta_vendedor_id
                                WHERE pn.proposta_comprador_id = :proposta_comprador_id 
                                AND pv.status IN ('enviada', 'pendente')";
            $stmt_check_vendedor = $conn->prepare($sql_check_vendedor);
            $stmt_check_vendedor->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_check_vendedor->execute();
            $vendedor_proposal = $stmt_check_vendedor->fetch(PDO::FETCH_ASSOC);
            
            // Determinar status baseado na existência de contraproposta do vendedor
            if ($vendedor_proposal) {
                // Existe contraproposta do vendedor: situação 3
                $novo_status_comprador = 'aceita';
                $novo_status_vendedor = 'finalizada';
            } else {
                // Não existe contraproposta: situação 1
                $novo_status_comprador = 'aceita';
                $novo_status_vendedor = null; // Não aplicável
            }
            
            // 1. Atualizar status da proposta do comprador
            $sql_update_comprador = "UPDATE propostas_comprador 
                                    SET status = :status 
                                    WHERE id = :proposta_comprador_id";
            
            $stmt_update_comprador = $conn->prepare($sql_update_comprador);
            $stmt_update_comprador->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_comprador->bindParam(':status', $novo_status_comprador);
            $stmt_update_comprador->execute();
            
            // 2. Se existir proposta do vendedor, atualizar status
            if ($vendedor_proposal && $novo_status_vendedor) {
                $sql_update_vendedor = "UPDATE propostas_vendedor 
                                    SET status = :status
                                    WHERE id = :proposta_vendedor_id";
                
                $stmt_update_vendedor = $conn->prepare($sql_update_vendedor);
                $stmt_update_vendedor->bindParam(':proposta_vendedor_id', $vendedor_proposal['id']);
                $stmt_update_vendedor->bindParam(':status', $novo_status_vendedor);
                $stmt_update_vendedor->execute();
            }
            
            // 3. Atualizar negociação
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                    SET status = 'aceita',
                                        preco_final = :preco_final,
                                        quantidade_final = :quantidade_final,
                                        data_atualizacao = NOW()
                                    WHERE proposta_comprador_id = :proposta_comprador_id";
            
            $stmt_update_negociacao = $conn->prepare($sql_update_negociacao);
            $stmt_update_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_negociacao->bindParam(':preco_final', $proposta['preco_proposto']);
            $stmt_update_negociacao->bindParam(':quantidade_final', $proposta['quantidade_proposta']);
            $stmt_update_negociacao->execute();
            
            $conn->commit();
            
            $msg = $vendedor_proposal ? 
                "Contraproposta do comprador aceita com sucesso! Negociação finalizada." :
                "Proposta do comprador aceita com sucesso! Negociação concluída.";
                
            redirecionar($proposta_comprador_id, 'sucesso', $msg);
            
        } elseif ($acao === 'recusar') {
            // 1. Atualizar status da proposta do comprador
            $sql_update_comprador = "UPDATE propostas_comprador 
                                    SET status = 'recusada' 
                                    WHERE id = :proposta_comprador_id";
            
            $stmt_update_comprador = $conn->prepare($sql_update_comprador);
            $stmt_update_comprador->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_comprador->execute();
            
            // 2. Atualizar status da negociação
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                     SET status = 'recusada',
                                         data_atualizacao = NOW()
                                     WHERE proposta_comprador_id = :proposta_comprador_id";
            
            $stmt_update_negociacao = $conn->prepare($sql_update_negociacao);
            $stmt_update_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_negociacao->execute();
            
            $conn->commit();
            redirecionar($proposta_comprador_id, 'sucesso', "Proposta recusada. A negociação foi encerrada.");
            
        } elseif ($acao === 'contraproposta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Processar contraproposta
            $novo_preco = $_POST['novo_preco'];
            $nova_quantidade = $_POST['nova_quantidade'];
            $novas_condicoes = $_POST['novas_condicoes'] ?? '';
            
            // 1. Criar nova proposta do vendedor
            $sql_insert_vendedor = "INSERT INTO propostas_vendedor 
                                   (proposta_comprador_id, vendedor_id, preco_proposto, 
                                    quantidade_proposta, condicoes_venda, status) 
                                   VALUES (:proposta_comprador_id, :vendedor_id, :preco, 
                                           :quantidade, :condicoes, 'enviada')";
            
            $stmt_insert_vendedor = $conn->prepare($sql_insert_vendedor);
            $stmt_insert_vendedor->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_insert_vendedor->bindParam(':vendedor_id', $vendedor_id);
            $stmt_insert_vendedor->bindParam(':preco', $novo_preco);
            $stmt_insert_vendedor->bindParam(':quantidade', $nova_quantidade);
            $stmt_insert_vendedor->bindParam(':condicoes', $novas_condicoes);
            $stmt_insert_vendedor->execute();
            
            $nova_proposta_vendedor_id = $conn->lastInsertId();
            
            // 2. Atualizar status da proposta do comprador para 'pendente'
            $sql_update_comprador = "UPDATE propostas_comprador 
                                    SET status = 'pendente' 
                                    WHERE id = :proposta_comprador_id";
            
            $stmt_update_comprador = $conn->prepare($sql_update_comprador);
            $stmt_update_comprador->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_comprador->execute();
            
            // 3. Atualizar a negociação para vincular com a nova proposta do vendedor
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                     SET proposta_vendedor_id = :proposta_vendedor_id,
                                         status = 'negociacao',
                                         data_atualizacao = NOW()
                                     WHERE proposta_comprador_id = :proposta_comprador_id";
            
            $stmt_update_negociacao = $conn->prepare($sql_update_negociacao);
            $stmt_update_negociacao->bindParam(':proposta_vendedor_id', $nova_proposta_vendedor_id);
            $stmt_update_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_negociacao->execute();
            
            $conn->commit();
            redirecionar($proposta_comprador_id, 'sucesso', "Contraproposta enviada com sucesso! Aguarde a resposta do comprador.");
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro ao processar decisão: " . $e->getMessage());
    redirecionar($proposta_comprador_id, 'erro', "Erro interno do sistema. Tente novamente.");
}
?>