<?php
// src/comprador/processar_resposta.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

$database = new Database();
$conn = $database->getConnection();

// Função para redirecionar com mensagem de erro/sucesso
function redirecionar($id, $tipo, $mensagem) {
    $url = ($id) 
        ? "detalhes_proposta.php?id={$id}&{$tipo}=" . urlencode($mensagem) 
        : "propostas_comprador.php?{$tipo}=" . urlencode($mensagem);
    header("Location: {$url}");
    exit(); 
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    redirecionar(null, 'erro', "Acesso negado. Faça login como Comprador.");
}

$usuario_id = $_SESSION['usuario_id']; // ID do comprador logado

// OBTÉM action e proposta_id de GET ou POST
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
if (!$action) {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
}

$proposta_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$proposta_id) {
    $proposta_id = filter_input(INPUT_POST, 'proposta_id', FILTER_VALIDATE_INT);
}

if (!$proposta_id) {
    redirecionar(null, 'erro', "ID da proposta inválido ou faltando.");
}

// 2. VERIFICAÇÃO DE PROPRIEDADE (Segurança)
try { 
    // 2.1. Busca o ID do comprador na tabela 'compradores'
    $sql_comprador = "SELECT c.id FROM compradores c JOIN usuarios u ON c.usuario_id = u.id WHERE u.id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if (!$comprador || !isset($comprador['id']) || !is_numeric($comprador['id'])) {
         redirecionar(null, 'erro', "Seu perfil de comprador não foi encontrado ou está inválido.");
    }

    $comprador_id_fk = $comprador['id']; // ID validado
    
    // 2.2. Verifica se a proposta pertence a ESTE comprador
    $sql_propriedade = "SELECT id FROM propostas_negociacao WHERE id = :proposta_id AND comprador_id = :comprador_id";
    $stmt_propriedade = $conn->prepare($sql_propriedade);
    $stmt_propriedade->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_propriedade->bindParam(':comprador_id', $comprador_id_fk, PDO::PARAM_INT); 
    $stmt_propriedade->execute();

    if ($stmt_propriedade->rowCount() === 0) {
        redirecionar(null, 'erro', "Proposta não encontrada ou você não tem permissão para esta ação.");
    }
} catch (PDOException $e) { 
    error_log("Erro de DB em processar_resposta (Segurança): " . $e->getMessage()); 
    redirecionar($proposta_id, 'erro', "Erro de segurança ao verificar propriedade. Tente novamente."); 
}


// 3. EXECUÇÃO DA AÇÃO
try { 
    switch ($action) {
        case 'aceitar':
            $sql = "UPDATE propostas_negociacao SET status = 'aceita', data_resposta = NOW() WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
            $stmt->execute();
            
            redirecionar($proposta_id, 'sucesso', "A Contraproposta do Vendedor foi **ACEITA**! Negociação concluída.");
            break;

        case 'recusar':
            $sql = "UPDATE propostas_negociacao SET status = 'recusada', data_resposta = NOW() WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
            $stmt->execute();
            
            redirecionar($proposta_id, 'sucesso', "A Contraproposta do Vendedor foi **RECUSADA**. Negociação encerrada.");
            break;
            
        case 'contraproposta':
            $novo_preco = filter_input(INPUT_POST, 'novo_preco', FILTER_VALIDATE_FLOAT);
            $nova_quantidade = filter_input(INPUT_POST, 'nova_quantidade', FILTER_VALIDATE_FLOAT);
            $novas_condicoes = filter_input(INPUT_POST, 'novas_condicoes', FILTER_SANITIZE_STRING); // Condições do Comprador
            
            if (!$novo_preco || !$nova_quantidade || $novo_preco <= 0 || $nova_quantidade <= 0) {
                redirecionar($proposta_id, 'erro', "Preço e Quantidade na contraproposta devem ser válidos.");
            }
            
            // O COMPRADOR ATUALIZA condicoes_comprador e limpa observacoes_vendedor
            $sql = "UPDATE propostas_negociacao SET 
                        status = 'negociacao',
                        preco_proposto = :novo_preco, 
                        quantidade_proposta = :nova_quantidade, 
                        condicoes_comprador = :novas_condicoes,
                        observacoes_vendedor = NULL,
                        data_resposta = NOW() 
                    WHERE id = :id";
                    
            $stmt = $conn->prepare($sql);
            
            $stmt->bindValue(':novo_preco', $novo_preco, PDO::PARAM_STR); 
            $stmt->bindValue(':nova_quantidade', $nova_quantidade, PDO::PARAM_STR); 
            
            // Trata string vazia como NULL para o campo TEXT opcional (condicoes_comprador)
            if (empty($novas_condicoes)) {
                $stmt->bindValue(':novas_condicoes', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':novas_condicoes', $novas_condicoes, PDO::PARAM_STR);
            }
            
            $stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
            $stmt->execute();
            
            redirecionar($proposta_id, 'sucesso', "Sua **Contraproposta** foi enviada! Aguarde a resposta do vendedor.");
            break;

        default:
            redirecionar($proposta_id, 'erro', "Ação inválida.");
            break;
    }
} catch (PDOException $e) { 
    error_log("Erro de DB em processar_resposta (Ação): " . $e->getMessage());
    redirecionar($proposta_id, 'erro', "Erro interno do servidor ao processar a ação. Tente novamente.");
}

?>