<?php
// src/comprador/negociacoes.php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../permissions.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar id do comprador
$database = new Database();
$db = $database->getConnection();
$sql = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
$stmt = $db->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$comprador = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$comprador) {
    die('Comprador não encontrado.');
}
$comprador_id = $comprador['id'];

// Buscar entregas finalizadas para o comprador
$sql = "SELECT e.id, e.endereco_origem, e.endereco_destino, e.valor_frete, e.data_entrega, e.foto_comprovante, p.nome as produto_nome, t.nome_comercial as transportador_nome
    FROM entregas e
    INNER JOIN produtos p ON e.produto_id = p.id
    INNER JOIN transportadores t ON e.transportador_id = t.id
    WHERE e.comprador_id = :comprador_id AND e.status = 'entregue' AND e.status_detalhado = 'finalizada'
    ORDER BY e.data_entrega DESC";
$stmt = $db->prepare($sql);
$stmt->bindParam(':comprador_id', $comprador_id);
$stmt->execute();
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Compras Realizadas</title>
    <link rel="stylesheet" href="../css/comprador/dashboard.css">
</head>
<body>
    <h1>Compras Realizadas (Entregas Finalizadas)</h1>
    <?php if (count($compras) === 0): ?>
        <p>Nenhuma compra finalizada ainda.</p>
    <?php else: ?>
        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Produto</th>
                    <th>Transportador</th>
                    <th>Origem</th>
                    <th>Destino</th>
                    <th>Valor Frete</th>
                    <th>Data Entrega</th>
                    <th>Comprovante</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($compras as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td><?php echo htmlspecialchars($c['produto_nome']); ?></td>
                        <td><?php echo htmlspecialchars($c['transportador_nome']); ?></td>
                        <td><?php echo htmlspecialchars(substr($c['endereco_origem'], 0, 20)) . '...'; ?></td>
                        <td><?php echo htmlspecialchars(substr($c['endereco_destino'], 0, 20)) . '...'; ?></td>
                        <td>R$ <?php echo number_format($c['valor_frete'], 2, ',', '.'); ?></td>
                        <td><?php echo ($c['data_entrega'] ? date('d/m/Y', strtotime($c['data_entrega'])) : '-'); ?></td>
                        <?php if ($c['foto_comprovante']): ?>
                            <td><a href="../../uploads/entregas/<?php echo htmlspecialchars($c['foto_comprovante']); ?>" target="_blank">Ver Foto</a></td>
                        <?php else: ?>
                            <td>-</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p><a href="dashboard.php">Voltar ao Painel</a></p>
</body>
</html>
