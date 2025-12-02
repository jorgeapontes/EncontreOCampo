<?php
// src/comprador/processar_edicao_proposta.php - ATUALIZADO

session_start();
require_once __DIR__ . '/../conexao.php'; 

$database = new Database();
$conn = $database->getConnection();

function redirecionar($negociacao_id, $tipo, $mensagem) {
    if ($tipo === 'sucesso') {
        header("Location: minhas_propostas.php?sucesso=" . urlencode($mensagem));
    } else {
        header("Location: editar_proposta.php?id={$negociacao_id}&erro=" . urlencode($mensagem));
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
$negociacao_id = filter_input(INPUT_POST, 'proposta_id', FILTER_VALIDATE_INT);

if (!$negociacao_id) {
    redirecionar(null, 'erro', "ID da negociação inválido.");
}

// 2. OBTER DADOS DO FORMULÁRIO
$novo_preco = filter_input(INPUT_POST, 'preco_proposto', FILTER_VALIDATE_FLOAT);
$nova_quantidade = filter_input(INPUT_POST, 'quantidade_proposta', FILTER_VALIDATE_INT);
$novas_condicoes = filter_input(INPUT_POST, 'condicoes', FILTER_SANITIZE_STRING);

// Validações
if (!$novo_preco || !$nova_quantidade || $novo_preco <= 0 || $nova_quantidade <= 0) {
    redirecionar($negociacao_id, 'erro', "Preço e quantidade devem ser válidos.");
}

try {
    // Buscar ID do comprador
    $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if (!$comprador) {
        redirecionar($negociacao_id, 'erro', "Perfil de comprador não encontrado.");
    }

    $comprador_id = $comprador['id'];

    // Verificar propriedade e obter ID da proposta do comprador
    $sql_verificar = "SELECT pn.proposta_comprador_id, pn.status, p.estoque 
                      FROM propostas_negociacao pn
                      JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
                      JOIN produtos p ON pn.produto_id = p.id
                      WHERE pn.id = :negociacao_id AND pc.comprador_id = :comprador_id";
    
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_verificar->execute();
    $dados = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        redirecionar($negociacao_id, 'erro', "Negociação não encontrada ou você não tem permissão para editá-la.");
    }

    // Verificar se pode ser editada
    if ($dados['status'] !== 'negociacao') {
        redirecionar($negociacao_id, 'erro', "Esta proposta não pode mais ser editada.");
    }

    // Verificar estoque
    if ($nova_quantidade > $dados['estoque']) {
        redirecionar($negociacao_id, 'erro', "Quantidade solicitada maior que estoque disponível.");
    }

    // 4. ATUALIZAR PROPOSTA DO COMPRADOR
    $sql_atualizar = "UPDATE propostas_comprador 
                      SET preco_proposto = :preco, 
                          quantidade_proposta = :quantidade, 
                          condicoes_compra = :condicoes,
                          data_proposta = NOW()
                      WHERE id = :proposta_comprador_id";
    
    $stmt_atualizar = $conn->prepare($sql_atualizar);
    $stmt_atualizar->bindParam(':preco', $novo_preco);
    $stmt_atualizar->bindParam(':quantidade', $nova_quantidade, PDO::PARAM_INT);
    
    // Tratar condições vazias
    if (empty($novas_condicoes)) {
        $stmt_atualizar->bindValue(':condicoes', null, PDO::PARAM_NULL);
    } else {
        $stmt_atualizar->bindParam(':condicoes', $novas_condicoes, PDO::PARAM_STR);
    }
    
    $stmt_atualizar->bindParam(':proposta_comprador_id', $dados['proposta_comprador_id'], PDO::PARAM_INT);
    
    if ($stmt_atualizar->execute()) {
        // Atualizar data da negociação
        $sql_atualizar_negociacao = "UPDATE propostas_negociacao 
                                     SET data_atualizacao = NOW()
                                     WHERE id = :negociacao_id";
        $stmt_negociacao = $conn->prepare($sql_atualizar_negociacao);
        $stmt_negociacao->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
        $stmt_negociacao->execute();
        
        redirecionar($negociacao_id, 'sucesso', "Proposta atualizada com sucesso!");
    } else {
        redirecionar($negociacao_id, 'erro', "Erro ao atualizar proposta. Tente novamente.");
    }

} catch (PDOException $e) {
    error_log("Erro ao atualizar proposta: " . $e->getMessage());
    redirecionar($negociacao_id, 'erro', "Erro interno do sistema. Tente novamente.");
}
?>