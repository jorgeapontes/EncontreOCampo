<?php
// src/vendedor/processar_decisao.php - VERSÃO FINAL CORRIGIDA

session_start();
require_once __DIR__ . '/../conexao.php';
require_once 'funcoes_notificacoes.php';

$database = new Database();
$conn = $database->getConnection();

// Função para redirecionar com mensagem de erro/sucesso
function redirecionar($id, $tipo, $mensagem) {
    $url = ($id) 
        ? "detalhes_proposta.php?id={$id}&{$tipo}=" . urlencode($mensagem) 
        : "propostas.php?{$tipo}=" . urlencode($mensagem);
    header("Location: {$url}");
    exit(); 
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    redirecionar(null, 'erro', "Acesso negado. Faça login como Vendedor.");
}

$usuario_id = $_SESSION['usuario_id']; // ID do vendedor logado

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
    // 2.1. Busca o ID do vendedor na tabela 'vendedores'
    $sql_vendedor = "SELECT v.id FROM vendedores v JOIN usuarios u ON v.usuario_id = u.id WHERE u.id = :usuario_id";
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor || !isset($vendedor['id']) || !is_numeric($vendedor['id'])) {
         redirecionar(null, 'erro', "Seu perfil de vendedor não foi encontrado ou está inválido.");
    }

    $vendedor_id_fk = $vendedor['id']; // ID validado
    
    // 2.2. Verifica se a proposta pertence a um produto deste vendedor
    $sql_propriedade = "SELECT pn.id 
                        FROM propostas_negociacao pn
                        JOIN produtos pr ON pn.produto_id = pr.id
                        WHERE pn.id = :proposta_id AND pr.vendedor_id = :vendedor_id";
    $stmt_propriedade = $conn->prepare($sql_propriedade);
    $stmt_propriedade->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_propriedade->bindParam(':vendedor_id', $vendedor_id_fk, PDO::PARAM_INT); 
    $stmt_propriedade->execute();

    if ($stmt_propriedade->rowCount() === 0) {
        redirecionar(null, 'erro', "Proposta não encontrada ou você não tem permissão para esta ação.");
    }
} catch (PDOException $e) { 
    error_log("Erro de DB em processar_decisao (Segurança): " . $e->getMessage()); 
    redirecionar($proposta_id, 'erro', "Erro de segurança ao verificar propriedade. Tente novamente."); 
}


// 3. EXECUÇÃO DA AÇÃO
try { 
    switch ($action) {
        case 'aceitar':
        // Iniciar transação para garantir consistência
        $conn->beginTransaction();
        
        try {
            // 1. Primeiro, buscar informações da proposta e produto
            $sql_info = "SELECT pn.produto_id, pn.quantidade_proposta, pr.estoque 
                        FROM propostas_negociacao pn
                        JOIN produtos pr ON pn.produto_id = pr.id
                        WHERE pn.id = :id";
            $stmt_info = $conn->prepare($sql_info);
            $stmt_info->bindParam(':id', $proposta_id, PDO::PARAM_INT);
            $stmt_info->execute();
            $info_proposta = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            if (!$info_proposta) {
                throw new Exception("Proposta não encontrada.");
            }
            
            $produto_id = $info_proposta['produto_id'];
            $quantidade_vendida = $info_proposta['quantidade_proposta'];
            $estoque_atual = $info_proposta['estoque'];
            
            // 2. Verificar se há estoque suficiente
            if ($estoque_atual < $quantidade_vendida) {
                throw new Exception("Estoque insuficiente. Disponível: {$estoque_atual}, Solicitado: {$quantidade_vendida}");
            }
            
            // 3. Atualizar o estoque do produto
            $novo_estoque = $estoque_atual - $quantidade_vendida;
            $sql_estoque = "UPDATE produtos SET estoque = :novo_estoque WHERE id = :produto_id";
            $stmt_estoque = $conn->prepare($sql_estoque);
            $stmt_estoque->bindParam(':novo_estoque', $novo_estoque, PDO::PARAM_INT);
            $stmt_estoque->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_estoque->execute();
            
            // 4. Atualizar o status da proposta
            $sql_proposta = "UPDATE propostas_negociacao 
                            SET status = 'aceita', data_resposta = NOW() 
                            WHERE id = :id";
            $stmt_proposta = $conn->prepare($sql_proposta);
            $stmt_proposta->bindParam(':id', $proposta_id, PDO::PARAM_INT);
            $stmt_proposta->execute();
            
            // 5. Se o estoque chegou a zero, desativar automaticamente o anúncio
            if ($novo_estoque <= 0) {
                $sql_desativar = "UPDATE produtos SET status = 'inativo' WHERE id = :produto_id";
                $stmt_desativar = $conn->prepare($sql_desativar);
                $stmt_desativar->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                $stmt_desativar->execute();
            }
            
            // Confirmar todas as operações
            $conn->commit();
            
            redirecionar($proposta_id, 'sucesso', "Proposta **ACEITA** com sucesso! Estoque atualizado. Quantidade vendida: {$quantidade_vendida}, Novo estoque: {$novo_estoque}");
            
            // Notificar o comprador
            $sql_comprador = "SELECT c.usuario_id, p.nome as produto_nome 
                            FROM propostas_negociacao pn
                            JOIN compradores c ON pn.comprador_id = c.id
                            JOIN produtos p ON pn.produto_id = p.id
                            WHERE pn.id = :proposta_id";
            $stmt_comprador = $conn->prepare($sql_comprador);
            $stmt_comprador->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
            $stmt_comprador->execute();
            $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

            if ($comprador) {
                notificarRespostaProposta($comprador['usuario_id'], $comprador['produto_nome'], $action, $proposta_id);
            }

        } catch (Exception $e) {
            // Reverter todas as operações em caso de erro
            $conn->rollBack();
            redirecionar($proposta_id, 'erro', "Erro ao aceitar proposta: " . $e->getMessage());
        }
        break;

        case 'recusar':
            // data_resposta agora existe!
            $sql = "UPDATE propostas_negociacao SET status = 'recusada', data_resposta = NOW() WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
            $stmt->execute();
            
            redirecionar($proposta_id, 'sucesso', "Proposta **RECUSADA**. O comprador foi notificado.");

            // Notificar o comprador
            $sql_comprador = "SELECT c.usuario_id, p.nome as produto_nome 
                            FROM propostas_negociacao pn
                            JOIN compradores c ON pn.comprador_id = c.id
                            JOIN produtos p ON pn.produto_id = p.id
                            WHERE pn.id = :proposta_id";
            $stmt_comprador = $conn->prepare($sql_comprador);
            $stmt_comprador->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
            $stmt_comprador->execute();
            $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

            if ($comprador) {
                notificarRespostaProposta($comprador['usuario_id'], $comprador['produto_nome'], $action, $proposta_id);
            }
            break;
            
        case 'contraproposta':
            $novo_preco = filter_input(INPUT_POST, 'novo_preco', FILTER_VALIDATE_FLOAT);
            $nova_quantidade = filter_input(INPUT_POST, 'nova_quantidade', FILTER_VALIDATE_FLOAT);
            $novas_condicoes = filter_input(INPUT_POST, 'novas_condicoes', FILTER_SANITIZE_STRING);
            
            if (!$novo_preco || !$nova_quantidade || $novo_preco <= 0 || $nova_quantidade <= 0) {
                redirecionar($proposta_id, 'erro', "Preço e Quantidade na contraproposta devem ser válidos.");
            }
            
            // SQL com observacoes_vendedor e data_resposta corretos
            $sql = "UPDATE propostas_negociacao SET 
                        status = 'negociacao',
                        preco_proposto = :novo_preco, 
                        quantidade_proposta = :nova_quantidade, 
                        observacoes_vendedor = :novas_condicoes,
                        data_resposta = NOW() 
                    WHERE id = :id";
                    
            $stmt = $conn->prepare($sql);
            
            $stmt->bindValue(':novo_preco', $novo_preco, PDO::PARAM_STR); 
            $stmt->bindValue(':nova_quantidade', $nova_quantidade, PDO::PARAM_STR); 
            
            // Trata string vazia como NULL
            if (empty($novas_condicoes)) {
                $stmt->bindValue(':novas_condicoes', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':novas_condicoes', $novas_condicoes, PDO::PARAM_STR);
            }
            
            $stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
            $stmt->execute();
            
            redirecionar($proposta_id, 'sucesso', "**Contraproposta** enviada com sucesso! Aguarde a resposta do comprador.");

            // Notificar o comprador
            $sql_comprador = "SELECT c.usuario_id, p.nome as produto_nome 
                            FROM propostas_negociacao pn
                            JOIN compradores c ON pn.comprador_id = c.id
                            JOIN produtos p ON pn.produto_id = p.id
                            WHERE pn.id = :proposta_id";
            $stmt_comprador = $conn->prepare($sql_comprador);
            $stmt_comprador->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
            $stmt_comprador->execute();
            $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

            if ($comprador) {
                notificarRespostaProposta($comprador['usuario_id'], $comprador['produto_nome'], $action, $proposta_id);
            }
            break;

        default:
            redirecionar($proposta_id, 'erro', "Ação inválida.");
            break;
    }
} catch (PDOException $e) { 
    // Revertendo para a mensagem de erro genérica por segurança
    error_log("Erro de DB em processar_decisao (Ação): " . $e->getMessage());
    redirecionar($proposta_id, 'erro', "Erro interno do servidor ao processar a ação. Tente novamente.");
}

?>