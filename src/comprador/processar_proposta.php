<?php
// src/comprador/processar_proposta.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso inválido ou restrito."));
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// 2. VALIDAÇÃO E COLETA DE DADOS
$produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
$comprador_usuario_id = filter_input(INPUT_POST, 'comprador_usuario_id', FILTER_VALIDATE_INT);
$preco_proposto = filter_input(INPUT_POST, 'preco_proposto', FILTER_VALIDATE_FLOAT);
$quantidade_proposta = filter_input(INPUT_POST, 'quantidade_proposta', FILTER_VALIDATE_INT);
$condicoes = filter_input(INPUT_POST, 'condicoes', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

// Verifica se os IDs e valores obrigatórios são válidos
if (!$produto_id || !$comprador_usuario_id || $preco_proposto === false || $quantidade_proposta === false || $preco_proposto <= 0 || $quantidade_proposta <= 0) {
    header("Location: dashboard.php?erro=" . urlencode("Dados da proposta inválidos ou incompletos."));
    exit();
}

try {
    // 3. OBTENÇÃO DO ID DO COMPRADOR NA TABELA 'compradores'
    // A chave estrangeira na tabela propostas_negociacao é para compradores.id
    $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $comprador_usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if (!$comprador) {
        throw new Exception("ID do Comprador não encontrado na tabela de compradores.");
    }
    $comprador_fk_id = $comprador['id'];

    // 4. VERIFICAR ESTOQUE DISPONÍVEL (Prevenção de fraude / Overbooking)
    $sql_estoque = "SELECT estoque FROM produtos WHERE id = :produto_id AND status = 'ativo'";
    $stmt_estoque = $conn->prepare($sql_estoque);
    $stmt_estoque->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_estoque->execute();
    $produto = $stmt_estoque->fetch(PDO::FETCH_ASSOC);

    if (!$produto || $quantidade_proposta > $produto['estoque']) {
        header("Location: ../anuncios.php?erro=" . urlencode("Quantidade desejada excede o estoque disponível."));
        exit();
    }


    // 5. INSERIR A PROPOSTA NA TABELA 'propostas_negociacao'
    // Assume-se que a coluna 'condicoes_comprador' foi adicionada.
    $sql_insert = "INSERT INTO propostas_negociacao (produto_id, comprador_id, preco_proposto, quantidade_proposta, condicoes_comprador, status) 
                   VALUES (:produto_id, :comprador_id, :preco_proposto, :quantidade_proposta, :condicoes_comprador, 'pendente')";
    
    $stmt_insert = $conn->prepare($sql_insert);
    
    $stmt_insert->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':comprador_id', $comprador_fk_id, PDO::PARAM_INT); // Usando o FK de compradores
    $stmt_insert->bindParam(':preco_proposto', $preco_proposto);
    $stmt_insert->bindParam(':quantidade_proposta', $quantidade_proposta, PDO::PARAM_INT);
    $stmt_insert->bindParam(':condicoes_comprador', $condicoes);
    
    $stmt_insert->execute();

    // 6. SUCESSO E REDIRECIONAMENTO
    header("Location: dashboard.php?msg=" . urlencode("Proposta enviada com sucesso! Aguarde a resposta do vendedor."));
    exit();

} catch (Exception $e) {
    // Tratamento de erros
    header("Location: dashboard.php?erro=" . urlencode("Erro ao processar a proposta: " . $e->getMessage()));
    exit();
} catch (PDOException $e) {
     // Tratamento de erros de banco de dados
    header("Location: dashboard.php?erro=" . urlencode("Erro de Banco de Dados: Tente novamente."));
    exit();
}
?>