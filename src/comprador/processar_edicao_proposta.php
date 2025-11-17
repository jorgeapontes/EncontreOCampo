<?php
// src/comprador/processar_edicao_proposta.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

$database = new Database();
$conn = $database->getConnection();

// Função para redirecionar
function redirecionar($proposta_id, $tipo, $mensagem) {
    if ($tipo === 'sucesso') {
        header("Location: minhas_propostas.php?sucesso=" . urlencode($mensagem));
    } else {
        header("Location: editar_proposta.php?id={$proposta_id}&erro=" . urlencode($mensagem));
    }
    exit();
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    redirecionar(null, 'erro', "Acesso negado. Faça login como Comprador.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar(null, 'erro', "Método inválido.");
}

$usuario_id = $_SESSION['usuario_id'];
$proposta_id = filter_input(INPUT_POST, 'proposta_id', FILTER_VALIDATE_INT);

if (!$proposta_id) {
    redirecionar(null, 'erro', "ID da proposta inválido.");
}

// 2. OBTER DADOS DO FORMULÁRIO
$novo_preco = filter_input(INPUT_POST, 'preco_proposto', FILTER_VALIDATE_FLOAT);
$nova_quantidade = filter_input(INPUT_POST, 'quantidade_proposta', FILTER_VALIDATE_INT);
$novas_condicoes = filter_input(INPUT_POST, 'condicoes', FILTER_SANITIZE_STRING);

// Validações
if (!$novo_preco || !$nova_quantidade || $novo_preco <= 0 || $nova_quantidade <= 0) {
    redirecionar($proposta_id, 'erro', "Preço e quantidade devem ser válidos.");
}

// 3. VERIFICAR PROPRIEDADE E ATUALIZAR
try {
    // Buscar ID do comprador
    $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if (!$comprador) {
        redirecionar($proposta_id, 'erro', "Perfil de comprador não encontrado.");
    }

    $comprador_id = $comprador['id'];

    // Verificar propriedade e status da proposta
    $sql_verificar = "SELECT pn.status, p.estoque 
                      FROM propostas_negociacao pn
                      JOIN produtos p ON pn.produto_id = p.id
                      WHERE pn.id = :proposta_id AND pn.comprador_id = :comprador_id";
    
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_verificar->execute();
    $proposta = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

    if (!$proposta) {
        redirecionar($proposta_id, 'erro', "Proposta não encontrada ou você não tem permissão para editá-la.");
    }

    // Verificar se pode ser editada
    if (!in_array($proposta['status'], ['pendente', 'negociacao'])) {
        redirecionar($proposta_id, 'erro', "Esta proposta não pode mais ser editada.");
    }

    // Verificar estoque
    if ($nova_quantidade > $proposta['estoque']) {
        redirecionar($proposta_id, 'erro', "Quantidade solicitada maior que estoque disponível.");
    }

    // 4. ATUALIZAR PROPOSTA
    $sql_atualizar = "UPDATE propostas_negociacao 
                      SET preco_proposto = :preco, 
                          quantidade_proposta = :quantidade, 
                          condicoes_comprador = :condicoes,
                          data_proposta = NOW()
                      WHERE id = :proposta_id AND comprador_id = :comprador_id";
    
    $stmt_atualizar = $conn->prepare($sql_atualizar);
    $stmt_atualizar->bindParam(':preco', $novo_preco);
    $stmt_atualizar->bindParam(':quantidade', $nova_quantidade, PDO::PARAM_INT);
    
    // Tratar condições vazias
    if (empty($novas_condicoes)) {
        $stmt_atualizar->bindValue(':condicoes', null, PDO::PARAM_NULL);
    } else {
        $stmt_atualizar->bindParam(':condicoes', $novas_condicoes, PDO::PARAM_STR);
    }
    
    $stmt_atualizar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_atualizar->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    
    if ($stmt_atualizar->execute()) {
        redirecionar($proposta_id, 'sucesso', "Proposta atualizada com sucesso!");
    } else {
        redirecionar($proposta_id, 'erro', "Erro ao atualizar proposta. Tente novamente.");
    }

} catch (PDOException $e) {
    error_log("Erro ao atualizar proposta: " . $e->getMessage());
    redirecionar($proposta_id, 'erro', "Erro interno do sistema. Tente novamente.");
}
?>