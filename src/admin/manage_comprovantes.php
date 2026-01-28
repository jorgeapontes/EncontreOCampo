<?php
session_start();

require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
        $sql = "SELECT e.id, e.endereco_origem, e.endereco_destino, e.valor_frete, e.data_entrega, e.foto_comprovante, e.assinatura_comprovante,
                p.nome as produto_nome,
                COALESCE(c.nome_comercial, u.nome) as comprador_nome,
                v.nome_comercial as vendedor_nome, v.cep as vendedor_cep, v.rua as vendedor_rua, v.numero as vendedor_numero, v.cidade as vendedor_cidade, v.estado as vendedor_estado
            FROM entregas e
            LEFT JOIN produtos p ON e.produto_id = p.id
            LEFT JOIN compradores c ON c.usuario_id = e.comprador_id
            LEFT JOIN usuarios u ON u.id = e.comprador_id
            LEFT JOIN vendedores v ON v.id = COALESCE(e.vendedor_id, p.vendedor_id)
            WHERE (e.foto_comprovante IS NOT NULL OR e.assinatura_comprovante IS NOT NULL)
            ORDER BY e.data_entrega DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $comprovantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar comprovantes: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovantes de Entrega - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #ddd; text-align: left; }
        img.thumb { max-width: 120px; max-height: 90px; object-fit: cover; border-radius: 6px; }
        .preview-link { display:inline-block; }
    </style>
</head>
<body>
<!-- NAVBAR padrão admin -->
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <img src="../../img/logo-nova.png" class="logo" alt="Logo">
            <div>
                <h1>ENCONTRE</h1>
                <h2>O CAMPO</h2>
            </div>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="todos_usuarios.php" class="nav-link">Todos os Usuários</a>
            <a href="chats_admin.php" class="nav-link">Chats</a>
            <a href="manage_comprovantes.php" class="nav-link active">Comprovantes</a>
            <a href="../../index.php" class="nav-link">Home</a>
            <a href="../logout.php" class="nav-link logout">Sair</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="header-section">
        <h1>Comprovantes de Entrega</h1>
        <p>Visualização das fotos enviadas pelos entregadores</p>
    </div>

    <main>
        <?php if (empty($comprovantes)): ?>
            <p>Nenhum comprovante encontrado.</p>
        <?php else: ?>
            <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Produto</th>
                        <th>Comprador</th>
                        <th>Vendedor</th>
                        <th>Data Entrega</th>
                        <th>Endereço Origem</th>
                        <th>Endereço Destino</th>
                        <th>Foto</th>
                        <th>Assinatura</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($comprovantes as $c): ?>
                    <?php
                        $origem_full = '';
                        if (!empty(trim($c['endereco_origem'] ?? ''))) {
                            $origem_full = $c['endereco_origem'];
                        } else {
                            $origem_full = (trim($c['vendedor_rua'] ?? '') !== '' ? ($c['vendedor_rua'] . ', ') : '')
                                . ($c['vendedor_numero'] ?? '')
                                . (isset($c['vendedor_cidade']) ? ' - ' . $c['vendedor_cidade'] : '')
                                . (isset($c['vendedor_estado']) ? '/' . $c['vendedor_estado'] : '')
                                . (!empty($c['vendedor_cep'] ?? '') ? ' - CEP: ' . $c['vendedor_cep'] : '');
                        }
                    ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td><?php echo htmlspecialchars($c['produto_nome'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($c['comprador_nome'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($c['vendedor_nome'] ?? '—'); ?></td>
                        <td><?php echo ($c['data_entrega'] ? date('d/m/Y', strtotime($c['data_entrega'])) : '-'); ?></td>
                        <?php
                            $orig_display = $origem_full ?: '-';
                            $orig_query = rawurlencode($origem_full ?: ($c['endereco_origem'] ?? ''));
                            $dest_full = trim($c['endereco_destino'] ?? '');
                            $dest_query = rawurlencode($dest_full ?: '');
                        ?>
                        <td>
                            <?php if (!empty(trim($orig_display)) && $orig_display !== '-'): ?>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $orig_query; ?>" target="_blank">
                                    <?php echo htmlspecialchars(mb_substr($orig_display, 0, 40)); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($dest_full)): ?>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $dest_query; ?>" target="_blank">
                                    <?php echo htmlspecialchars(mb_substr($dest_full, 0, 40)); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($c['foto_comprovante'])): ?>
                                <a class="preview-link" href="../../uploads/entregas/<?php echo htmlspecialchars($c['foto_comprovante']); ?>" target="_blank" rel="noopener noreferrer" title="Foto do comprovante">
                                    <img class="thumb" src="../../uploads/entregas/<?php echo htmlspecialchars($c['foto_comprovante']); ?>" alt="Comprovante">
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($c['assinatura_comprovante'])): ?>
                                <a class="preview-link" href="../../uploads/entregas/<?php echo htmlspecialchars($c['assinatura_comprovante']); ?>" target="_blank" rel="noopener noreferrer" title="Assinatura do recebedor">
                                    <img class="thumb" src="../../uploads/entregas/<?php echo htmlspecialchars($c['assinatura_comprovante']); ?>" alt="Assinatura">
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
