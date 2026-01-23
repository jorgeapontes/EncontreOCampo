<?php
// src/procurando_transportador.php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissions.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar id do comprador
$database = new Database();
$db = $database->getConnection();
$sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
$stmt = $db->prepare($sql_comprador);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$comprador = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$comprador) {
    die('Comprador não encontrado.');
}
$comprador_id = $comprador['id'];

// Buscar propostas de frete recebidas para acordos do comprador
$sql = "SELECT pf.id as proposta_frete_id, pf.valor_frete, pf.status, pf.data_envio, t.nome_comercial as transportador_nome, p.id as proposta_id, pr.nome AS produto_nome, p.quantidade_proposta as quantidade, p.valor_total
    FROM propostas_frete_transportador pf
    INNER JOIN propostas p ON pf.proposta_id = p.id
    INNER JOIN produtos pr ON p.produto_id = pr.id
    INNER JOIN transportadores t ON pf.transportador_id = t.id
    WHERE p.comprador_id = :comprador_id AND pf.status IN ('pendente','contraproposta')
    ORDER BY pf.data_envio DESC";
$stmt = $db->prepare($sql);
$stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
$stmt->execute();
$propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Propostas de Frete Recebidas</title>
    <link rel="stylesheet" href="css/comprador/dashboard.css">
</head>
<body>
    <h1>Propostas de Frete Recebidas</h1>
    <?php if (count($propostas) === 0): ?>
        <p>Nenhuma proposta de frete recebida no momento.</p>
    <?php else: ?>
        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Transportador</th>
                    <th>Valor do Frete</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($propostas as $p): ?>
                    <tr>
                        <td>#<?php echo $p['proposta_id']; ?> - <?php echo htmlspecialchars($p['produto_nome']); ?> (<?php echo $p['quantidade']; ?>)</td>
                        <td><?php echo htmlspecialchars($p['transportador_nome']); ?></td>
                        <td>R$ <?php echo number_format($p['valor_frete'], 2, ',', '.'); ?></td>
                        <td><?php echo ucfirst($p['status']); ?></td>
                        <td>
                            <form action="responder_proposta_frete.php" method="POST" style="display:inline;">
                                <input type="hidden" name="proposta_frete_id" value="<?php echo $p['proposta_frete_id']; ?>">
                                <button type="submit" name="acao" value="aceitar">Aceitar</button>
                                <button type="submit" name="acao" value="recusar">Recusar</button>
                            </form>
                            <form action="responder_proposta_frete.php" method="POST" style="display:inline;">
                                <input type="hidden" name="proposta_frete_id" value="<?php echo $p['proposta_frete_id']; ?>">
                                <input type="number" step="0.01" min="0" name="novo_valor" placeholder="Contra-proposta" required>
                                <button type="submit" name="acao" value="contraproposta">Enviar Contra-proposta</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
