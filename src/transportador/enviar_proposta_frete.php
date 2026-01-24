<?php
// src/transportador/enviar_proposta_frete.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

// Verificar se é transportador
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito."));
    exit();
}

// Verificar se recebeu os dados do formulário
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php?erro=" . urlencode("Requisição inválida."));
    exit();
}

$proposta_id = filter_input(INPUT_POST, 'proposta_id', FILTER_VALIDATE_INT);
$valor_frete = filter_input(INPUT_POST, 'valor_frete', FILTER_VALIDATE_FLOAT);

if (!$proposta_id || $valor_frete === false || $valor_frete < 0) {
    header("Location: dashboard.php?erro=" . urlencode("Dados inválidos."));
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Buscar transportador_id
    $sql_transportador = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id";
    $stmt_transportador = $db->prepare($sql_transportador);
    $stmt_transportador->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_transportador->execute();
    $transportador = $stmt_transportador->fetch(PDO::FETCH_ASSOC);
    
    if (!$transportador) {
        header("Location: dashboard.php?erro=" . urlencode("Transportador não encontrado."));
        exit();
    }
    
    $transportador_id = $transportador['id'];
    
    // Verificar se a proposta existe e precisa de transportador
    $sql_verifica = "SELECT p.*, pr.nome as produto_nome 
                     FROM propostas p 
                     INNER JOIN produtos pr ON p.produto_id = pr.id
                     WHERE p.ID = :proposta_id 
                     AND p.opcao_frete = 'entregador' 
                     AND p.status = 'aceita'";
    $stmt_verifica = $db->prepare($sql_verifica);
    $stmt_verifica->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_verifica->execute();
    $proposta = $stmt_verifica->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        header("Location: dashboard.php?erro=" . urlencode("Proposta não encontrada ou não disponível."));
        exit();
    }
    
    // Verificar se já enviou proposta para este pedido
    $sql_check = "SELECT id FROM propostas_frete_transportador 
                  WHERE proposta_id = :proposta_id 
                  AND transportador_id = :transportador_id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() > 0) {
        header("Location: dashboard.php?erro=" . urlencode("Você já enviou uma proposta para este pedido."));
        exit();
    }
    
    // Inserir proposta de frete
    $sql_insert = "INSERT INTO propostas_frete_transportador 
                   (proposta_id, transportador_id, valor_frete, status, data_envio) 
                   VALUES (:proposta_id, :transportador_id, :valor_frete, 'pendente', NOW())";
    $stmt_insert = $db->prepare($sql_insert);
    $stmt_insert->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':valor_frete', $valor_frete);
    $stmt_insert->execute();
    
    // Criar notificação para o comprador
    $sql_notif = "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) 
                  VALUES (:comprador_id, :mensagem, 'info', :url)";
    $stmt_notif = $db->prepare($sql_notif);
    $mensagem = "Nova proposta de frete recebida para '" . $proposta['produto_nome'] . "' - R$ " . number_format($valor_frete, 2, ',', '.');
    $url = 'procurando_transportador.php';
    $stmt_notif->bindParam(':comprador_id', $proposta['comprador_id'], PDO::PARAM_INT);
    $stmt_notif->bindParam(':mensagem', $mensagem);
    $stmt_notif->bindParam(':url', $url);
    $stmt_notif->execute();
    
    header("Location: dashboard.php?sucesso=1");
    exit();
    
} catch (PDOException $e) {
    error_log("Erro ao enviar proposta de frete: " . $e->getMessage());
    header("Location: dashboard.php?erro=" . urlencode("Erro ao enviar proposta. Tente novamente."));
    exit();
}