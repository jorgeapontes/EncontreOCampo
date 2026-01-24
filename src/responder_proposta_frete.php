<?php
// src/responder_proposta_frete.php
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/conexao.php';

// Verificar se é comprador
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: login.php?erro=" . urlencode("Acesso restrito."));
    exit();
}

// Verificar se recebeu os dados do formulário
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: procurando_transportador.php?erro=" . urlencode("Requisição inválida."));
    exit();
}

$proposta_frete_id = filter_input(INPUT_POST, 'proposta_frete_id', FILTER_VALIDATE_INT);
$acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_STRING);

if (!$proposta_frete_id || !$acao) {
    header("Location: procurando_transportador.php?erro=" . urlencode("Dados inválidos."));
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // Buscar dados da proposta de frete
    $sql = "SELECT pf.*, p.comprador_id, p.produto_id, pr.nome as produto_nome, t.nome_comercial as transportador_nome
            FROM propostas_frete_transportador pf
            INNER JOIN propostas p ON pf.proposta_id = p.ID
            INNER JOIN produtos pr ON p.produto_id = pr.id
            INNER JOIN transportadores t ON pf.transportador_id = t.id
            WHERE pf.id = :proposta_frete_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta_frete = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta_frete) {
        $db->rollBack();
        header("Location: procurando_transportador.php?erro=" . urlencode("Proposta não encontrada."));
        exit();
    }
    
    // Verificar se é o comprador correto
    if ($proposta_frete['comprador_id'] != $_SESSION['usuario_id']) {
        $db->rollBack();
        header("Location: procurando_transportador.php?erro=" . urlencode("Você não tem permissão para esta ação."));
        exit();
    }
    
    // Processar a ação
    if ($acao === 'aceitar') {
        // Atualizar status da proposta de frete para aceita
        $sql_update = "UPDATE propostas_frete_transportador 
                       SET status = 'aceita', data_resposta = NOW() 
                       WHERE id = :proposta_frete_id";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
        $stmt_update->execute();
        
        // Atualizar valor do frete na proposta principal
        $sql_update_proposta = "UPDATE propostas 
                                SET valor_frete = :valor_frete, 
                                    valor_total = valor_total + :valor_frete 
                                WHERE ID = :proposta_id";
        $stmt_update_proposta = $db->prepare($sql_update_proposta);
        $stmt_update_proposta->bindParam(':valor_frete', $proposta_frete['valor_frete']);
        $stmt_update_proposta->bindParam(':proposta_id', $proposta_frete['proposta_id'], PDO::PARAM_INT);
        $stmt_update_proposta->execute();
        
        // Recusar todas as outras propostas para este pedido
        $sql_recusar_outras = "UPDATE propostas_frete_transportador 
                               SET status = 'recusada', data_resposta = NOW() 
                               WHERE proposta_id = :proposta_id 
                               AND id != :proposta_frete_id 
                               AND status = 'pendente'";
        $stmt_recusar_outras = $db->prepare($sql_recusar_outras);
        $stmt_recusar_outras->bindParam(':proposta_id', $proposta_frete['proposta_id'], PDO::PARAM_INT);
        $stmt_recusar_outras->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
        $stmt_recusar_outras->execute();
        
        // Notificar o transportador
        $sql_notif = "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) 
                      SELECT t.usuario_id, :mensagem, 'sucesso', :url
                      FROM transportadores t
                      WHERE t.id = :transportador_id";
        $stmt_notif = $db->prepare($sql_notif);
        $mensagem = "Sua proposta de frete foi aceita para '" . $proposta_frete['produto_nome'] . "'!";
        $url = 'transportador/entregas.php';
        $stmt_notif->bindParam(':mensagem', $mensagem);
        $stmt_notif->bindParam(':url', $url);
        $stmt_notif->bindParam(':transportador_id', $proposta_frete['transportador_id'], PDO::PARAM_INT);
        $stmt_notif->execute();
        
        $db->commit();
        header("Location: procurando_transportador.php?sucesso=" . urlencode("Proposta aceita com sucesso!"));
        exit();
        
    } elseif ($acao === 'recusar') {
        // Atualizar status da proposta de frete para recusada
        $sql_update = "UPDATE propostas_frete_transportador 
                       SET status = 'recusada', data_resposta = NOW() 
                       WHERE id = :proposta_frete_id";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
        $stmt_update->execute();
        
        // Notificar o transportador
        $sql_notif = "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) 
                      SELECT t.usuario_id, :mensagem, 'info', :url
                      FROM transportadores t
                      WHERE t.id = :transportador_id";
        $stmt_notif = $db->prepare($sql_notif);
        $mensagem = "Sua proposta de frete foi recusada para '" . $proposta_frete['produto_nome'] . "'.";
        $url = 'transportador/dashboard.php';
        $stmt_notif->bindParam(':mensagem', $mensagem);
        $stmt_notif->bindParam(':url', $url);
        $stmt_notif->bindParam(':transportador_id', $proposta_frete['transportador_id'], PDO::PARAM_INT);
        $stmt_notif->execute();
        
        $db->commit();
        header("Location: procurando_transportador.php?sucesso=" . urlencode("Proposta recusada."));
        exit();
        
    } elseif ($acao === 'contraproposta') {
        $novo_valor = filter_input(INPUT_POST, 'novo_valor', FILTER_VALIDATE_FLOAT);
        
        if ($novo_valor === false || $novo_valor < 0) {
            $db->rollBack();
            header("Location: procurando_transportador.php?erro=" . urlencode("Valor inválido para contraproposta."));
            exit();
        }
        
        // Atualizar status e valor da proposta de frete
        $sql_update = "UPDATE propostas_frete_transportador 
                       SET status = 'contraproposta', 
                           valor_frete = :novo_valor, 
                           data_resposta = NOW(),
                           observacoes = CONCAT(COALESCE(observacoes, ''), '\nContraproposta do comprador: R$ ', :novo_valor)
                       WHERE id = :proposta_frete_id";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->bindParam(':novo_valor', $novo_valor);
        $stmt_update->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
        $stmt_update->execute();
        
        // Notificar o transportador
        $sql_notif = "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) 
                      SELECT t.usuario_id, :mensagem, 'info', :url
                      FROM transportadores t
                      WHERE t.id = :transportador_id";
        $stmt_notif = $db->prepare($sql_notif);
        $mensagem = "Você recebeu uma contraproposta de R$ " . number_format($novo_valor, 2, ',', '.') . " para '" . $proposta_frete['produto_nome'] . "'";
        $url = 'transportador/entregas.php';
        $stmt_notif->bindParam(':mensagem', $mensagem);
        $stmt_notif->bindParam(':url', $url);
        $stmt_notif->bindParam(':transportador_id', $proposta_frete['transportador_id'], PDO::PARAM_INT);
        $stmt_notif->execute();
        
        $db->commit();
        header("Location: procurando_transportador.php?sucesso=" . urlencode("Contraproposta enviada!"));
        exit();
        
    } else {
        $db->rollBack();
        header("Location: procurando_transportador.php?erro=" . urlencode("Ação inválida."));
        exit();
    }
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Erro ao responder proposta de frete: " . $e->getMessage());
    header("Location: procurando_transportador.php?erro=" . urlencode("Erro ao processar. Tente novamente."));
    exit();
}