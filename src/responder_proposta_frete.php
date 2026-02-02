<?php
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/funcoes_notificacoes.php';
require_once __DIR__ . '/../includes/send_notification.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: login.php?erro=" . urlencode("Acesso restrito."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: procurando_transportador.php?erro=" . urlencode("Requisição inválida."));
    exit();
}

$proposta_frete_id = filter_input(INPUT_POST, 'proposta_frete_id', FILTER_VALIDATE_INT);
$acao = filter_input(INPUT_POST, 'acao', FILTER_SANITIZE_STRING);
$novo_valor = filter_input(INPUT_POST, 'novo_valor', FILTER_VALIDATE_FLOAT);

if (!$proposta_frete_id || !$acao) {
    header("Location: procurando_transportador.php?erro=" . urlencode("Dados inválidos."));
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    $sql = "SELECT pf.*, p.comprador_id, p.produto_id, pr.nome as produto_nome, 
                   t.nome_comercial as transportador_nome, t.usuario_id as transportador_usuario_id,
                   u.email as transportador_email, u.nome as transportador_nome_usuario,
                   uc.nome as comprador_nome, uc.email as comprador_email
            FROM propostas_transportadores pf
            INNER JOIN propostas p ON pf.proposta_id = p.ID
            INNER JOIN produtos pr ON p.produto_id = pr.id
            INNER JOIN transportadores t ON pf.transportador_id = t.id
            INNER JOIN usuarios u ON t.usuario_id = u.id
            INNER JOIN usuarios uc ON p.comprador_id = uc.id
            WHERE pf.id = :proposta_frete_id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        $db->rollBack();
        header("Location: procurando_transportador.php?erro=" . urlencode("Proposta não encontrada."));
        exit();
    }
    
    if ($proposta['comprador_id'] != $_SESSION['usuario_id']) {
        $db->rollBack();
        header("Location: procurando_transportador.php?erro=" . urlencode("Você não tem permissão para esta ação."));
        exit();
    }
    
    // Função para enviar notificação por email
    function enviarNotificacaoEmailDireto($proposta, $acao, $novo_valor = null) {
        $transportador_nome = $proposta['transportador_nome_usuario'] ?: $proposta['transportador_nome'];
        $comprador_nome = $proposta['comprador_nome'];
        
        $assuntos = [
            'aceitar' => 'Proposta de Frete Aceita',
            'recusar' => 'Proposta de Frete Recusada',
            'contraproposta' => 'Contraproposta Recebida'
        ];
        
        $mensagens = [
            'aceitar' => 
                "Olá " . $transportador_nome . "!\n\n" .
                "Sua proposta de frete foi ACEITA pelo comprador " . $comprador_nome . "!\n\n" .
                "Detalhes da entrega:\n" .
                "Produto: " . $proposta['produto_nome'] . "\n" .
                "Valor do frete: R$ " . number_format($proposta['valor_frete'], 2, ',', '.') . "\n" .
                "Prazo de entrega: " . $proposta['prazo_entrega'] . " dia(s)\n\n" .
                "Entre em contato com o vendedor para combinar a coleta e inicie o transporte!",
            
            'recusar' =>
                "Olá " . $transportador_nome . ",\n\n" .
                "Infelizmente, sua proposta de frete foi RECUSADA pelo comprador " . $comprador_nome . ".\n\n" .
                "Produto: " . $proposta['produto_nome'] . "\n" .
                "Valor proposto: R$ " . number_format($proposta['valor_frete'], 2, ',', '.') . "\n\n" .
                "Não desanime! Continue oferecendo seus serviços na plataforma.",
            
            'contraproposta' =>
                "Olá " . $transportador_nome . ",\n\n" .
                "Você recebeu uma CONTRA PROPOSTA do comprador " . $comprador_nome . "!\n\n" .
                "Produto: " . $proposta['produto_nome'] . "\n" .
                "Seu valor: R$ " . number_format($proposta['valor_frete'], 2, ',', '.') . "\n" .
                "Contraproposta: R$ " . number_format($novo_valor, 2, ',', '.') . "\n\n" .
                "Acesse a plataforma para responder a esta contraproposta!"
        ];
        
        // Usar o email real do transportador em vez do email fixo
        return enviarEmailNotificacao(
            $proposta['transportador_email'], // Email correto do transportador
            $transportador_nome,
            $assuntos[$acao] . ' - ' . $proposta['produto_nome'],
            $mensagens[$acao]
        );
    }
    
    switch ($acao) {
        case 'aceitar':
            $sql_update = "UPDATE propostas_transportadores 
                           SET status = 'aceita', data_resposta = NOW() 
                           WHERE id = :proposta_frete_id";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
            $stmt_update->execute();
            
            $sql_update_proposta = "UPDATE propostas 
                                    SET valor_frete = :valor_frete, 
                                        valor_total = valor_total + :valor_frete 
                                    WHERE ID = :proposta_id";
            $stmt_update_proposta = $db->prepare($sql_update_proposta);
            $stmt_update_proposta->bindParam(':valor_frete', $proposta['valor_frete']);
            $stmt_update_proposta->bindParam(':proposta_id', $proposta['proposta_id'], PDO::PARAM_INT);
            $stmt_update_proposta->execute();
            
            $sql_recusar_outras = "UPDATE propostas_transportadores 
                                   SET status = 'recusada', data_resposta = NOW() 
                                   WHERE proposta_id = :proposta_id 
                                   AND id != :proposta_frete_id 
                                   AND status = 'pendente'";
            $stmt_recusar_outras = $db->prepare($sql_recusar_outras);
            $stmt_recusar_outras->bindParam(':proposta_id', $proposta['proposta_id'], PDO::PARAM_INT);
            $stmt_recusar_outras->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
            $stmt_recusar_outras->execute();
            
            // Notificar transportador usando a nova função
            notificarRespostaPropostaFrete(
                $proposta['transportador_usuario_id'],
                $proposta['produto_nome'],
                'aceitar'
            );
            
            enviarNotificacaoEmailDireto($proposta, 'aceitar');
            break;
            
        case 'recusar':
            $sql_update = "UPDATE propostas_transportadores 
                           SET status = 'recusada', data_resposta = NOW() 
                           WHERE id = :proposta_frete_id";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
            $stmt_update->execute();
            
            // Notificar transportador usando a nova função
            notificarRespostaPropostaFrete(
                $proposta['transportador_usuario_id'],
                $proposta['produto_nome'],
                'recusar'
            );
            
            // Enviar email
            enviarNotificacaoEmailDireto($proposta, 'recusar');
            break;
            
        case 'contraproposta':
            if ($novo_valor === false || $novo_valor < 0) {
                $db->rollBack();
                header("Location: procurando_transportador.php?erro=" . urlencode("Valor inválido para contraproposta."));
                exit();
            }
            
            $sql_update = "UPDATE propostas_transportadores 
                           SET status = 'contraproposta', 
                               valor_frete = :novo_valor, 
                               data_resposta = NOW(),
                               observacoes = CONCAT(COALESCE(observacoes, ''), '\nContraproposta do comprador: R$ ', :novo_valor)
                           WHERE id = :proposta_frete_id";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->bindParam(':novo_valor', $novo_valor);
            $stmt_update->bindParam(':proposta_frete_id', $proposta_frete_id, PDO::PARAM_INT);
            $stmt_update->execute();
            
            // Notificar transportador usando a nova função
            notificarRespostaPropostaFrete(
                $proposta['transportador_usuario_id'],
                $proposta['produto_nome'],
                'contraproposta',
                $novo_valor
            );
            
            enviarNotificacaoEmailDireto($proposta, 'contraproposta', $novo_valor);
            break;
            
        default:
            $db->rollBack();
            header("Location: procurando_transportador.php?erro=" . urlencode("Ação inválida."));
            exit();
    }
    
    $db->commit();
    
    $mensagens_sucesso = [
        'aceitar' => 'Proposta aceita com sucesso!',
        'recusar' => 'Proposta recusada.',
        'contraproposta' => 'Contraproposta enviada!'
    ];
    
    header("Location: procurando_transportador.php?sucesso=" . urlencode($mensagens_sucesso[$acao]));
    exit();
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Erro ao responder proposta de frete: " . $e->getMessage());
    header("Location: procurando_transportador.php?erro=" . urlencode("Erro ao processar. Tente novamente."));
    exit();
}
?>