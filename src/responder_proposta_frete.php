<?php
// src/responder_proposta_frete.php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissions.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposta_frete_id = intval($_POST['proposta_frete_id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    $novo_valor = isset($_POST['novo_valor']) ? floatval($_POST['novo_valor']) : null;

    $database = new Database();
    $db = $database->getConnection();

    // Buscar proposta de frete e garantir que pertence a um acordo do comprador logado
    $sql = "SELECT pf.*, p.comprador_id FROM propostas_frete_transportador pf INNER JOIN propostas p ON pf.proposta_id = p.id WHERE pf.id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $proposta_frete_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposta || $proposta['comprador_id'] != $usuario_id) {
        header('Location: procurando_transportador.php?erro=Proposta não encontrada.');
        exit();
    }

    if ($acao === 'aceitar') {
        // Aceitar proposta: atualizar status para aceita, recusar outras pendentes desse acordo
        $db->beginTransaction();
        $sql_aceita = "UPDATE propostas_frete_transportador SET status = 'aceita', data_resposta = NOW() WHERE id = :id";
        $stmt_aceita = $db->prepare($sql_aceita);
        $stmt_aceita->bindParam(':id', $proposta_frete_id, PDO::PARAM_INT);
        $stmt_aceita->execute();
        $sql_recusa = "UPDATE propostas_frete_transportador SET status = 'recusada', data_resposta = NOW() WHERE proposta_id = :proposta_id AND id != :id AND status IN ('pendente','contraproposta')";
        $stmt_recusa = $db->prepare($sql_recusa);
        $stmt_recusa->bindParam(':proposta_id', $proposta['proposta_id'], PDO::PARAM_INT);
        $stmt_recusa->bindParam(':id', $proposta_frete_id, PDO::PARAM_INT);
        $stmt_recusa->execute();
        $db->commit();
        // Criar registro em entregas
        require_once __DIR__ . '/criar_entrega_apos_aceite.php';
        criarEntregaSeAceita($proposta_frete_id);
        header('Location: procurando_transportador.php?sucesso=Proposta aceita!');
        exit();
    } elseif ($acao === 'recusar') {
        $sql = "UPDATE propostas_frete_transportador SET status = 'recusada', data_resposta = NOW() WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $proposta_frete_id, PDO::PARAM_INT);
        $stmt->execute();
        header('Location: procurando_transportador.php?sucesso=Proposta recusada.');
        exit();
    } elseif ($acao === 'contraproposta' && $novo_valor && $novo_valor > 0) {
        $sql = "UPDATE propostas_frete_transportador SET valor_frete = :novo_valor, status = 'contraproposta', data_resposta = NOW() WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':novo_valor', $novo_valor);
        $stmt->bindParam(':id', $proposta_frete_id, PDO::PARAM_INT);
        $stmt->execute();
        header('Location: procurando_transportador.php?sucesso=Contra-proposta enviada.');
        exit();
    }
}
header('Location: procurando_transportador.php?erro=Ação inválida.');
exit();
