<?php
// src/transportador/enviar_proposta_frete.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposta_id = intval($_POST['proposta_id'] ?? 0);
    $valor_frete = floatval($_POST['valor_frete'] ?? 0);
    if ($proposta_id <= 0 || $valor_frete <= 0) {
        header('Location: disponiveis.php?erro=Dados inválidos.');
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();

    // Buscar o id do transportador
    $sql_transportador = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id";
    $stmt = $db->prepare($sql_transportador);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $transportador = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transportador) {
        header('Location: disponiveis.php?erro=Transportador não encontrado.');
        exit();
    }
    $transportador_id = $transportador['id'];

    // Verifica se já existe proposta pendente para esse acordo
    $sql_check = "SELECT id FROM propostas_frete_transportador WHERE proposta_id = :proposta_id AND transportador_id = :transportador_id AND status = 'pendente'";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_check->execute();
    if ($stmt_check->fetch()) {
        header('Location: disponiveis.php?erro=Você já enviou uma proposta pendente para este acordo.');
        exit();
    }

    // Inserir proposta de frete
    $sql_insert = "INSERT INTO propostas_frete_transportador (proposta_id, transportador_id, valor_frete, status) VALUES (:proposta_id, :transportador_id, :valor_frete, 'pendente')";
    $stmt_insert = $db->prepare($sql_insert);
    $stmt_insert->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':valor_frete', $valor_frete);
    $stmt_insert->execute();

    header('Location: disponiveis.php?sucesso=Proposta enviada com sucesso!');
    exit();
}

header('Location: disponiveis.php');
exit();
