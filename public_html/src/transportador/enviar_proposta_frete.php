<?php
// src/transportador/enviar_proposta_frete.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/send_notification.php';

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
    $sql_transportador = "SELECT t.id, t.nome_comercial, u.email as transportador_email 
                          FROM transportadores t 
                          INNER JOIN usuarios u ON t.usuario_id = u.id 
                          WHERE t.usuario_id = :usuario_id";
    $stmt_transportador = $db->prepare($sql_transportador);
    $stmt_transportador->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_transportador->execute();
    $transportador = $stmt_transportador->fetch(PDO::FETCH_ASSOC);
    
    if (!$transportador) {
        header("Location: dashboard.php?erro=" . urlencode("Transportador não encontrado."));
        exit();
    }
    
    $transportador_id = $transportador['id'];
    $transportador_nome = $transportador['nome_comercial'];
    
    // Verificar se a proposta existe e precisa de transportador
    $sql_verifica = "SELECT p.*, 
                            pr.nome as produto_nome, 
                            uc.email as comprador_email, 
                            uc.nome as comprador_nome,
                            uv.email as vendedor_email,
                            uv.nome as vendedor_nome
                     FROM propostas p 
                     INNER JOIN produtos pr ON p.produto_id = pr.id
                     INNER JOIN usuarios uc ON p.comprador_id = uc.id
                     INNER JOIN vendedores v ON pr.vendedor_id = v.id
                     INNER JOIN usuarios uv ON v.usuario_id = uv.id
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
    $sql_check = "SELECT id FROM propostas_transportadores 
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
    $sql_insert = "INSERT INTO propostas_transportadores 
                   (proposta_id, transportador_id, valor_frete, status, data_criacao) 
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
    
    // NOTIFICAÇÃO POR EMAIL para o COMPRADOR
    if (!empty($proposta['comprador_email'])) {
        $assunto = "🚚 Nova Proposta de Frete Recebida";
        $conteudo = "O transportador <strong>{$transportador_nome}</strong> enviou uma proposta de frete para seu pedido do produto '{$proposta['produto_nome']}' no valor de <strong>R$ " . number_format($valor_frete, 2, ',', '.') . "</strong>. Acesse a plataforma para avaliar a proposta.";
        
        enviarEmailNotificacao(
            $proposta['comprador_email'],
            $proposta['comprador_nome'],
            $assunto,
            $conteudo
        );
    }
    
    // NOTIFICAÇÃO POR EMAIL para o VENDEDOR
    if (!empty($proposta['vendedor_email'])) {
        $assunto_vendedor = "📦 Nova Proposta de Frete para seu Produto";
        $conteudo_vendedor = "O transportador <strong>{$transportador_nome}</strong> enviou uma proposta de frete para o produto '{$proposta['produto_nome']}' que você vendeu, no valor de <strong>R$ " . number_format($valor_frete, 2, ',', '.') . "</strong>. O comprador será notificado para avaliar a proposta.";
        
        enviarEmailNotificacao(
            $proposta['vendedor_email'],
            $proposta['vendedor_nome'],
            $assunto_vendedor,
            $conteudo_vendedor
        );
    }
    
    header("Location: dashboard.php?sucesso=1");
    exit();
    
} catch (PDOException $e) {
    error_log("Erro ao enviar proposta de frete: " . $e->getMessage());
    header("Location: dashboard.php?erro=" . urlencode("Erro ao enviar proposta. Tente novamente."));
    exit();
}