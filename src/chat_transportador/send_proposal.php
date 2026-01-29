<?php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Permitir envio de proposta quando o usuário estiver logado.
// A verificação de permissão mais específica (ser o `comprador_id` da conversa)
// é feita após buscar a conversa abaixo.
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Acesso restrito']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit();
}

$conversa_id = isset($_POST['conversa_id']) ? (int)$_POST['conversa_id'] : 0;
$valor = isset($_POST['valor']) ? floatval(str_replace(',', '.', $_POST['valor'])) : 0.0;
$data_entrega = isset($_POST['data_entrega']) ? trim($_POST['data_entrega']) : null; // YYYY-MM-DD

if ($conversa_id <= 0 || $valor <= 0 || !$data_entrega) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();

    // Buscar dados da conversa
    $sql = "SELECT produto_id, comprador_id, vendedor_id, transportador_id FROM chat_conversas WHERE id = :cid LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':cid', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv || $conv['comprador_id'] != $_SESSION['usuario_id']) {
        throw new Exception('Conversa inválida ou sem permissão');
    }

    $produto_id = (int)$conv['produto_id'];
    $comprador_id = (int)$conv['comprador_id'];
    $vendedor_id = (int)$conv['vendedor_id'];
    $transportador_usuario_id = (int)$conv['transportador_id'];

    if (!$transportador_usuario_id) {
        throw new Exception('Transportador não definido nesta conversa');
    }

    // Buscar id do transportador na tabela transportadores
    $sql_t = "SELECT id FROM transportadores WHERE usuario_id = :uid LIMIT 1";
    $stmt_t = $conn->prepare($sql_t);
    $stmt_t->bindParam(':uid', $transportador_usuario_id, PDO::PARAM_INT);
    $stmt_t->execute();
    $trow = $stmt_t->fetch(PDO::FETCH_ASSOC);
    if (!$trow) throw new Exception('Transportador não encontrado');
    $transportador_id = (int)$trow['id'];

    // Buscar preço do produto para preencher preco_proposto (não crítico)
    $sql_p = "SELECT preco, estoque FROM produtos WHERE id = :pid LIMIT 1";
    $stmt_p = $conn->prepare($sql_p);
    $stmt_p->bindParam(':pid', $produto_id, PDO::PARAM_INT);
    $stmt_p->execute();
    $prod = $stmt_p->fetch(PDO::FETCH_ASSOC);
    $preco_produto = $prod ? (float)$prod['preco'] : 0.00;

    // Inserir proposta master (tabela propostas)
    $sql_ins = "INSERT INTO propostas (comprador_id, vendedor_id, produto_id, preco_proposto, quantidade_proposta, forma_pagamento, opcao_frete, valor_frete, valor_total, status, data_inicio, data_atualizacao, data_entrega_estimada, transportador_id) VALUES (:comprador_id, :vendedor_id, :produto_id, :preco_proposto, 1, 'à vista', 'entregador', :valor_frete, :valor_total, 'negociacao', NOW(), NOW(), :data_entrega, :transportador_id)";
    $stmt_ins = $conn->prepare($sql_ins);
    $valor_total = $valor; // para entregas consideraremos só o frete aqui
    $stmt_ins->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_ins->bindParam(':preco_proposto', $preco_produto);
    $stmt_ins->bindParam(':valor_frete', $valor);
    $stmt_ins->bindParam(':valor_total', $valor_total);
    $stmt_ins->bindParam(':data_entrega', $data_entrega);
    $stmt_ins->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    if (!$stmt_ins->execute()) throw new Exception('Erro ao criar proposta');

    $proposta_id = (int)$conn->lastInsertId();

    // Inserir na tabela propostas_transportadores
    // calcular prazo (dias) a partir de data_entrega
    $prazo = 0;
    $ts = strtotime($data_entrega);
    if ($ts !== false) {
        $diff = $ts - time();
        $prazo = max(0, (int)ceil($diff / 86400));
    }

    $sql_pt = "INSERT INTO propostas_transportadores (proposta_id, transportador_id, valor_frete, prazo_entrega, observacoes, status, data_criacao) VALUES (:proposta_id, :transportador_id, :valor_frete, :prazo_entrega, '', 'pendente', NOW())";
    $stmt_pt = $conn->prepare($sql_pt);
    $stmt_pt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_pt->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_pt->bindParam(':valor_frete', $valor);
    $stmt_pt->bindParam(':prazo_entrega', $prazo, PDO::PARAM_INT);
    if (!$stmt_pt->execute()) throw new Exception('Erro ao criar proposta para transportador');

    $propostas_transportador_id = (int)$conn->lastInsertId();

    // Inserir mensagem no chat informando a proposta (tipo 'proposta')
    $mensagem_texto = "*PROPOSTA DE ENTREGA*\nValor: R$ " . number_format($valor, 2, ',', '.') . "\nPrazo: " . $data_entrega . "\nID: " . $propostas_transportador_id;
    $dados_json = json_encode(['proposta_id' => $proposta_id, 'propostas_transportador_id' => $propostas_transportador_id, 'valor' => $valor, 'prazo' => $data_entrega]);

    $sql_msg = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo, dados_json) VALUES (:conversa_id, :remetente_id, :mensagem, 'proposta', :dados_json)";
    $stmt_msg = $conn->prepare($sql_msg);
    $stmt_msg->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_msg->bindParam(':remetente_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_msg->bindParam(':mensagem', $mensagem_texto);
    $stmt_msg->bindParam(':dados_json', $dados_json);
    if (!$stmt_msg->execute()) throw new Exception('Erro ao enviar mensagem de proposta');

    // Atualizar ultima mensagem na conversa
    $sql_up = "UPDATE chat_conversas SET ultima_mensagem = :mensagem, ultima_mensagem_data = NOW() WHERE id = :cid";
    $stmt_up = $conn->prepare($sql_up);
    $stmt_up->bindParam(':mensagem', $mensagem_texto);
    $stmt_up->bindParam(':cid', $conversa_id, PDO::PARAM_INT);
    $stmt_up->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'proposta_id' => $proposta_id, 'propostas_transportador_id' => $propostas_transportador_id]);
    exit();

} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

?>
