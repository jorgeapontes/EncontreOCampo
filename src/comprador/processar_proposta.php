<?php
// src/comprador/processar_proposta.php

session_start();
require_once __DIR__ . '/../conexao.php'; // Caminho para a conexão (conexao.php)

$database = new Database();
$conn = $database->getConnection();

// Função para redirecionar com mensagem de erro (voltando para o formulário)
function redirecionarComErro($mensagem, $anuncio_id = null) {
    $location = 'proposta_nova.php';
    if ($anuncio_id) {
        $location .= "?anuncio_id=" . $anuncio_id . "&";
    } else {
        $location .= "?";
    }
    header("Location: {$location}erro=" . urlencode($mensagem));
    exit();
}

// 1. VERIFICAÇÃO DE ACESSO E MÉTODO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso negado. Faça login como Comprador."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../anuncios.php");
    exit();
}

// 2. OBTENÇÃO E VALIDAÇÃO DE DADOS
$produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
$preco_proposto = filter_input(INPUT_POST, 'preco_proposto', FILTER_VALIDATE_FLOAT);
$quantidade_proposta = filter_input(INPUT_POST, 'quantidade_proposta', FILTER_VALIDATE_FLOAT);
$condicoes = filter_input(INPUT_POST, 'condicoes', FILTER_SANITIZE_STRING);

$usuario_id = $_SESSION['usuario_id']; // ID da tabela 'usuarios'

if (!$produto_id || !$preco_proposto || !$quantidade_proposta || $preco_proposto <= 0 || $quantidade_proposta <= 0) {
    redirecionarComErro("Dados da proposta incompletos ou inválidos.", $produto_id);
}

// 3. OBTENDO O ID DO COMPRADOR (ID da tabela 'compradores')
$comprador_id = null;
try {
    $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $resultado_comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if ($resultado_comprador) {
        $comprador_id = $resultado_comprador['id'];
    } else {
        redirecionarComErro("Não foi possível identificar seu ID de comprador. Contate o suporte.", $produto_id);
    }
} catch (PDOException $e) {
    redirecionarComErro("Erro ao buscar ID do comprador.", $produto_id);
}


// 4. INSERÇÃO DA PROPOSTA
try {
    // Status inicial: 'pendente'
    $sql_insercao = "INSERT INTO propostas_negociacao 
                     (produto_id, comprador_id, preco_proposto, quantidade_proposta, condicoes_comprador, status, data_proposta)
                     VALUES 
                     (:produto_id, :comprador_id, :preco_proposto, :quantidade_proposta, :condicoes_comprador, 'pendente', NOW())";
                     
    $stmt_insercao = $conn->prepare($sql_insercao);
    
    $stmt_insercao->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_insercao->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_insercao->bindParam(':preco_proposto', $preco_proposto);
    $stmt_insercao->bindParam(':quantidade_proposta', $quantidade_proposta);
    $stmt_insercao->bindParam(':condicoes_comprador', $condicoes);
    
    if ($stmt_insercao->execute()) {
        // SUCESSO: Redirecionar para a página de listagem de propostas (próxima a ser criada)
        header("Location: minhas_propostas.php?sucesso=" . urlencode("Proposta enviada com sucesso!"));
        exit();
    } else {
        redirecionarComErro("Erro ao salvar a proposta no banco de dados. Tente novamente.", $produto_id);
    }

} catch (PDOException $e) {
    redirecionarComErro("Erro de SQL ao inserir proposta.", $produto_id);
}

?>