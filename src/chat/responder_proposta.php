<?php
// src/chat/responder_proposta.php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../conexao.php';
require_once 'enviar_mensagem_automatica.php';

header('Content-Type: application/json; charset=utf-8');

ob_start();


try {

    // Verificar se está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

// Ler dados JSON
$json = file_get_contents('php://input');

if (empty($json)) {
        throw new Exception('Nenhum dado recebido');
    }

$dados = json_decode($json, true);

if (!$dados || json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }

if (!$dados || !isset($dados['acao']) || !isset($dados['proposta_id'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];
$acao = $dados['acao'];
$proposta_id = (int)$dados['proposta_id'];

$database = new Database();
$conn = $database->getConnection();


    $conn->beginTransaction();
    
    // Buscar informações da proposta
    $sql_proposta = "SELECT * FROM propostas WHERE ID = :proposta_id";
    $stmt = $conn->prepare($sql_proposta);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        throw new Exception('Proposta não encontrada');
    }
    
    // Validar permissões e definir nova status
    $nova_status = '';
    $mensagem_acao = '';
    
    if ($acao === 'cancelar' && $usuario_tipo === 'comprador') {
        if ($proposta['comprador_id'] != $usuario_id) {
            throw new Exception('Apenas o comprador desta proposta pode cancelá-la');
        }
        $nova_status = 'cancelada';
        $mensagem_acao = 'cancelada';
        
    } elseif ($acao === 'aceitar_para_assinatura' && $usuario_tipo === 'vendedor') {
        if ($proposta['vendedor_id'] != $usuario_id) {
            throw new Exception('Apenas o vendedor desta proposta pode aceitar para assinatura');
        }
        if ($proposta['status'] !== 'negociacao') {
            throw new Exception('Esta proposta não está mais em negociação');
        }
        $nova_status = 'assinando';
        $mensagem_acao = 'aceita e enviada para assinatura';
        
    } elseif (($acao === 'aceitar' || $acao === 'recusar') && $usuario_tipo === 'vendedor') {
        // Manter compatibilidade com versão anterior
        if ($proposta['vendedor_id'] != $usuario_id) {
            throw new Exception('Apenas o vendedor desta proposta pode aceitar/recusar');
        }
        if ($proposta['status'] !== 'negociacao') {
            throw new Exception('Esta proposta não está mais em negociação');
        }
        $nova_status = ($acao === 'aceitar') ? 'aceita' : 'recusada';
        $mensagem_acao = ($acao === 'aceitar') ? 'aceita' : 'recusada';
        
    } else {
        throw new Exception('Ação não permitida para seu tipo de usuário');
    }
    
    // Atualizar status da proposta
    $sql_atualizar = "UPDATE propostas SET 
                     status = :status,
                     data_atualizacao = NOW()
                     WHERE ID = :proposta_id";
    
    $stmt_atualizar = $conn->prepare($sql_atualizar);
    $stmt_atualizar->bindParam(':status', $nova_status);
    $stmt_atualizar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    
    if (!$stmt_atualizar->execute()) {
        throw new Exception('Erro ao atualizar proposta');
    }
    
    // Se foi aceita (sem assinatura), verificar estoque
    if ($nova_status === 'aceita') {
        $sql_estoque = "SELECT estoque FROM produtos WHERE id = :produto_id";
        $stmt_estoque = $conn->prepare($sql_estoque);
        $stmt_estoque->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
        $stmt_estoque->execute();
        $produto = $stmt_estoque->fetch(PDO::FETCH_ASSOC);
        
        if ($produto['estoque'] < $proposta['quantidade_proposta']) {
            throw new Exception('Estoque insuficiente');
        }
        
        // Atualizar estoque
        $sql_update_estoque = "UPDATE produtos SET 
                              estoque = estoque - :quantidade
                              WHERE id = :produto_id";
        
        $stmt_update = $conn->prepare($sql_update_estoque);
        $stmt_update->bindParam(':quantidade', $proposta['quantidade_proposta'], PDO::PARAM_INT);
        $stmt_update->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
        $stmt_update->execute();
    }
    
    // Se foi cancelada, restaurar o estoque (se estava aceita anteriormente)
    if ($nova_status === 'cancelada' && $proposta['status'] === 'aceita') {
        $sql_restaurar_estoque = "UPDATE produtos SET 
                                 estoque = estoque + :quantidade
                                 WHERE id = :produto_id";
        
        $stmt_restaurar = $conn->prepare($sql_restaurar_estoque);
        $stmt_restaurar->bindParam(':quantidade', $proposta['quantidade_proposta'], PDO::PARAM_INT);
        $stmt_restaurar->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
        $stmt_restaurar->execute();
    }
    
    $sql_update_timestamp = "UPDATE propostas SET data_atualizacao = NOW() WHERE ID = :proposta_id";
    $stmt_timestamp = $conn->prepare($sql_update_timestamp);
    $stmt_timestamp->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_timestamp->execute();

    // Buscar dados atualizados
    $sql_novos_dados = "SELECT data_atualizacao FROM propostas WHERE ID = :proposta_id";
    $stmt_novos = $conn->prepare($sql_novos_dados);
    $stmt_novos->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_novos->execute();
    $dados_atualizados = $stmt_novos->fetch(PDO::FETCH_ASSOC);

    // Buscar mais informações da proposta para a mensagem
    $sql_proposta_detalhes = "SELECT p.*, prod.nome as produto_nome,
                            u_comp.nome as comprador_nome,
                            u_vend.nome as vendedor_nome
                            FROM propostas p
                            JOIN produtos prod ON p.produto_id = prod.id
                            JOIN usuarios u_comp ON p.comprador_id = u_comp.id
                            JOIN usuarios u_vend ON p.vendedor_id = u_vend.id
                            WHERE p.ID = :proposta_id";

    $stmt_detalhes = $conn->prepare($sql_proposta_detalhes);
    $stmt_detalhes->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_detalhes->execute();
    $proposta_detalhes = $stmt_detalhes->fetch(PDO::FETCH_ASSOC);

    // Determinar unidade de medida
    $modo = 'por_unidade'; // Você precisa buscar do produto
    $unidade_medida = 'unidade';

    if ($acao === 'cancelar') {
        $detalhes_mensagem = [
            'usuario_nome' => $_SESSION['usuario_nome'] ?? 'Usuário'
        ];
        
        enviarNotificacaoAcao(
            $proposta_detalhes['produto_id'],
            $proposta_detalhes['comprador_id'],
            $proposta_detalhes['vendedor_id'],
            'proposta_cancelada',
            $detalhes_mensagem,
            $usuario_id
        );
        
    } elseif ($acao === 'aceitar_para_assinatura') {
        enviarNotificacaoAcao(
            $proposta_detalhes['produto_id'],
            $proposta_detalhes['comprador_id'],
            $proposta_detalhes['vendedor_id'],
            'proposta_aceita_assinatura',
            [],
            $usuario_id
        );
        
    } elseif ($acao === 'recusar') {
        enviarNotificacaoAcao(
            $proposta_detalhes['produto_id'],
            $proposta_detalhes['comprador_id'],
            $proposta_detalhes['vendedor_id'],
            'proposta_recusada',
            [],
            $usuario_id
        );
    }

    $conn->commit();
    
    ob_clean();

    echo json_encode([
        'success' => true,
        'message' => "Proposta {$mensagem_acao} com sucesso",
        'novo_status' => $nova_status
    ]);
    
} catch (Exception $e) {
    // Reverter se houver transação
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Limpar buffer
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();
?>