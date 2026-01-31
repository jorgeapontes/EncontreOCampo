<?php
session_start();
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    echo json_encode(['success' => false, 'erro' => 'Acesso negado.']);
    exit;
}

$proposta_id = filter_input(INPUT_POST, 'proposta_id', FILTER_VALIDATE_INT);
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$data_entrega = filter_input(INPUT_POST, 'data_entrega', FILTER_SANITIZE_STRING);

if (!$proposta_id || !$valor || !$data_entrega) {
    echo json_encode(['success' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();

    // Buscar dados da proposta de compra
    $sql = "SELECT p.*, 
                   c.nome as comprador_nome,
                   v.nome as vendedor_nome,
                   pr.nome as produto_nome
            FROM propostas p
            LEFT JOIN usuarios c ON p.comprador_id = c.id
            LEFT JOIN usuarios v ON p.vendedor_id = v.id
            LEFT JOIN produtos pr ON p.produto_id = pr.id
            WHERE p.ID = :proposta_id 
            AND p.opcao_frete = 'entregador' 
            AND p.status = 'aceita' 
            AND p.transportador_id IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta_compra = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposta_compra) {
        throw new Exception('Proposta de compra não encontrada ou já possui transportador.');
    }

    // Buscar transportador_id
    $sql_transp = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id";
    $stmt_transp = $conn->prepare($sql_transp);
    $stmt_transp->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_transp->execute();
    $transportador = $stmt_transp->fetch(PDO::FETCH_ASSOC);

    if (!$transportador) {
        throw new Exception('Transportador não encontrado.');
    }

    $transportador_id = $transportador['id'];

    // Inserir proposta do transportador
    $sql_insert = "INSERT INTO propostas_transportadores 
                   (proposta_id, transportador_id, valor_frete, prazo_entrega, status, data_criacao) 
                   VALUES (:proposta_id, :transportador_id, :valor_frete, :prazo_entrega, 'pendente', NOW())";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':valor_frete', $valor);
    $stmt_insert->bindParam(':prazo_entrega', $data_entrega);
    $stmt_insert->execute();

    $proposta_transportador_id = $conn->lastInsertId();

    // Buscar conversa existente
    $sql_conversa = "SELECT id FROM chat_conversas 
                     WHERE produto_id = :produto_id 
                     AND comprador_id = :comprador_id 
                     AND transportador_id = :transportador_id";
    $stmt_conversa = $conn->prepare($sql_conversa);
    $stmt_conversa->bindParam(':produto_id', $proposta_compra['produto_id'], PDO::PARAM_INT);
    $stmt_conversa->bindParam(':comprador_id', $proposta_compra['comprador_id'], PDO::PARAM_INT);
    $stmt_conversa->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_conversa->execute();
    $conversa = $stmt_conversa->fetch(PDO::FETCH_ASSOC);

    if (!$conversa) {
        // Criar nova conversa
        $sql_insert_conversa = "INSERT INTO chat_conversas 
                                (produto_id, comprador_id, vendedor_id, transportador_id, data_criacao) 
                                VALUES (:produto_id, :comprador_id, :vendedor_id, :transportador_id, NOW())";
        $stmt_insert_conversa = $conn->prepare($sql_insert_conversa);
        $stmt_insert_conversa->bindParam(':produto_id', $proposta_compra['produto_id'], PDO::PARAM_INT);
        $stmt_insert_conversa->bindParam(':comprador_id', $proposta_compra['comprador_id'], PDO::PARAM_INT);
        $stmt_insert_conversa->bindParam(':vendedor_id', $proposta_compra['vendedor_id'], PDO::PARAM_INT);
        $stmt_insert_conversa->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
        $stmt_insert_conversa->execute();
        $conversa_id = $conn->lastInsertId();
    } else {
        $conversa_id = $conversa['id'];
    }

    // Inserir mensagem no chat
    $mensagem = "*PROPOSTA DE ENTREGA*\nValor: R$ " . number_format($valor, 2, ',', '') . "\nPrazo: " . date('d/m/Y', strtotime($data_entrega)) . "\nID: " . $proposta_transportador_id;
    $dados_json = json_encode([
        'proposta_id' => $proposta_id,
        'propostas_transportador_id' => $proposta_transportador_id,
        'valor' => $valor,
        'prazo' => $data_entrega
    ]);

    $sql_msg = "INSERT INTO chat_mensagens 
                (conversa_id, remetente_id, mensagem, tipo, dados_json, data_envio) 
                VALUES (:conversa_id, :remetente_id, :mensagem, 'proposta', :dados_json, NOW())";
    $stmt_msg = $conn->prepare($sql_msg);
    $stmt_msg->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_msg->bindParam(':remetente_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_msg->bindParam(':mensagem', $mensagem);
    $stmt_msg->bindParam(':dados_json', $dados_json);
    $stmt_msg->execute();

    // Atualizar última mensagem da conversa
    $sql_update_conv = "UPDATE chat_conversas 
                        SET ultima_mensagem = :ultima_mensagem, 
                            ultima_mensagem_data = NOW() 
                        WHERE id = :conversa_id";
    $stmt_update_conv = $conn->prepare($sql_update_conv);
    $stmt_update_conv->bindParam(':ultima_mensagem', $mensagem);
    $stmt_update_conv->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_update_conv->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'conversa_id' => $conversa_id]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'erro' => $e->getMessage()]);
}