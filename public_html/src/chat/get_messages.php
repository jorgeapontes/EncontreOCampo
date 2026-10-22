<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/chat_config.php';

header('Content-Type: application/json');

verificarLoginChat();

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
$ultimo_id = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;
$antes_id = isset($_GET['antes_id']) ? (int)$_GET['antes_id'] : 0;
// Com a aba em segundo plano o cliente continua buscando, mas não marca
// como lida: o usuário ainda não viu a mensagem.
$marcar_lidas = !isset($_GET['marcar_lidas']) || $_GET['marcar_lidas'] === '1';
$com_proposta = isset($_GET['com_proposta']) && $_GET['com_proposta'] === '1';
$usuario_id = (int)$_SESSION['usuario_id'];

chatLiberarSessao();

if ($conversa_id <= 0) {
    chatErro(400, 'Conversa inválida');
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Verificar se o usuário pertence a esta conversa
    $sql_verifica = "SELECT produto_id, comprador_id, vendedor_id FROM chat_conversas WHERE id = :conversa_id";
    $stmt_verifica = $conn->prepare($sql_verifica);
    $stmt_verifica->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt_verifica->execute();
    $conversa = $stmt_verifica->fetch(PDO::FETCH_ASSOC);

    if (!$conversa || ($conversa['comprador_id'] != $usuario_id && $conversa['vendedor_id'] != $usuario_id)) {
        chatErro(403, 'Acesso negado');
    }

    // O vendedor não vê mensagens de transportadores nesta conversa
    $filtro = ($conversa['vendedor_id'] == $usuario_id)
        ? "AND (u.tipo IS NULL OR u.tipo <> 'transportador')"
        : '';

    [$mensagens, $tem_mais] = chatBuscarMensagens($conn, $conversa_id, $ultimo_id, $antes_id, '', $filtro);

    // Páginas antigas não precisam marcar leitura nem checar proposta
    if ($antes_id > 0) {
        echo json_encode(['success' => true, 'mensagens' => $mensagens, 'tem_mais' => $tem_mais]);
        exit();
    }

    if ($marcar_lidas) {
        marcarMensagensComoLidas($conn, $conversa_id, $usuario_id);
    }

    $resposta = [
        'success' => true,
        'mensagens' => $mensagens,
        'ultimo_lido_id' => chatUltimoLidoId($conn, $conversa_id, $usuario_id),
    ];
    if ($ultimo_id <= 0) {
        $resposta['tem_mais'] = $tem_mais;
    }

    // Data da última alteração na proposta desta conversa: o cliente
    // recarrega o card quando ela muda, sem precisar de um segundo polling.
    if ($com_proposta) {
        $stmt_p = $conn->prepare("SELECT MAX(data_atualizacao) FROM propostas
                                  WHERE produto_id = :produto_id AND comprador_id = :comprador_id AND vendedor_id = :vendedor_id");
        $stmt_p->bindParam(':produto_id', $conversa['produto_id'], PDO::PARAM_INT);
        $stmt_p->bindParam(':comprador_id', $conversa['comprador_id'], PDO::PARAM_INT);
        $stmt_p->bindParam(':vendedor_id', $conversa['vendedor_id'], PDO::PARAM_INT);
        $stmt_p->execute();
        $resposta['proposta_atualizada_em'] = $stmt_p->fetchColumn() ?: '';
    }

    echo json_encode($resposta);

} catch (PDOException $e) {
    error_log("Erro ao buscar mensagens: " . $e->getMessage());
    chatErro(500, 'Erro ao buscar mensagens');
}
?>
