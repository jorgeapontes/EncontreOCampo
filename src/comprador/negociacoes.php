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

// Buscar entregas em andamento e finalizadas para o comprador
$sql = "SELECT e.id, e.endereco_origem, e.endereco_destino, e.valor_frete, e.data_entrega, e.foto_comprovante, p.nome as produto_nome, t.nome_comercial as transportador_nome, e.status, e.status_detalhado, p.imagem_url
    FROM entregas e
    INNER JOIN produtos p ON e.produto_id = p.id
    INNER JOIN transportadores t ON e.transportador_id = t.id
    WHERE e.comprador_id = :comprador_id
    ORDER BY (e.status = 'pendente') DESC, e.data_entrega DESC, e.id DESC";
$stmt = $db->prepare($sql);
$stmt->bindParam(':comprador_id', $comprador_id);
$stmt->execute();
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Minhas Negociações</title>
    <link rel="stylesheet" href="../../index.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    .negociacoes-list { display: flex; flex-direction: column; gap: 24px; margin-bottom: 40px; }
    .negociacao-card { background: var(--white); border-radius: 12px; box-shadow: 0 2px 12px rgba(44,62,80,0.07); padding: 28px 32px 20px 32px; display: flex; flex-direction: column; gap: 18px; border: 1px solid var(--gray); }
    .negociacao-card:hover { box-shadow: 0 6px 24px rgba(44,62,80,0.13); }
    .negociacao-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .negociacao-pedido { font-size: 1.1rem; font-weight: 600; color: var(--primary-color); }
    .negociacao-status { font-size: 0.95rem; font-weight: 600; padding: 4px 14px; border-radius: 16px; background: var(--gray); color: var(--text-light); text-transform: capitalize; }
    .status-pendente { background: #fffbe6; color: #bfa100; }
    .status-aguardando_entrega { background: #e3f2fd; color: #1976d2; }
    .status-em_transporte { background: #fffde7; color: #fbc02d; }
    .status-entregue { background: #e8f5e9; color: #388e3c; }
    .status-finalizada { background: #c8e6c9; color: #2e7d32; }
    .negociacao-dados { display: flex; gap: 32px; font-size: 1rem; color: var(--text-color); margin-bottom: 6px; flex-wrap: wrap; }
    .negociacao-acoes { margin-top: 10px; }
    .negociacao-img { width: 90px; height: 90px; object-fit: cover; border-radius: 10px; background: #f5f5f5; border: 1px solid #eee; }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <li class="nav-item"><a href="../../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <main class="container" style="margin-top: 100px;">
        <h1 style="font-size:2rem; font-weight:700; margin-bottom: 10px;">Minhas Negociações</h1>
        <p style="color:var(--text-light); margin-bottom: 30px;">Acompanhe o andamento das suas entregas e negociações.</p>
        <?php if (count($compras) === 0): ?>
            <div class="empty-state" style="text-align:center; margin: 60px 0; color:var(--text-light);">
                <h3 style="font-weight:600;">Nenhuma negociação encontrada.</h3>
            </div>
        <?php else: ?>
        <div class="negociacoes-list">
            <?php foreach ($compras as $c):
                $img_url = isset($c['imagem_url']) && $c['imagem_url'] ? htmlspecialchars($c['imagem_url']) : '../../img/no-user-image.png';
            ?>
            <div class="negociacao-card">
                <div style="display:flex; gap:24px; align-items:center;">
                    <img src="<?php echo $img_url; ?>" alt="<?php echo htmlspecialchars($c['produto_nome']); ?>" class="negociacao-img">
                    <div style="flex:1;">
                        <div class="negociacao-header">
                            <span class="negociacao-pedido">#<?php echo $c['id']; ?> - <?php echo htmlspecialchars($c['produto_nome']); ?></span>
                            <span class="negociacao-status status-<?php echo htmlspecialchars($c['status_detalhado']); ?>">
                                <?php
                                    $status_map = [
                                        'pendente' => 'Pendente',
                                        'aguardando_entrega' => 'Aguardando Entrega',
                                        'em_transporte' => 'Em Transporte',
                                        'entregue' => 'Entregue',
                                        'finalizada' => 'Finalizada',
                                    ];
                                    $label = $status_map[$c['status_detalhado']] ?? ucfirst(str_replace('_',' ',$c['status_detalhado']));
                                    echo $label;
                                    if ($c['status'] === 'pendente') echo ' (pendente)';
                                ?>
                            </span>
                        </div>
                        <div class="negociacao-dados">
                            <span><b>Transportador:</b> <?php echo htmlspecialchars($c['transportador_nome']); ?></span>
                            <span><b>Frete:</b> R$ <?php echo number_format($c['valor_frete'], 2, ',', '.'); ?></span>
                            <span><b>Origem:</b> <?php echo htmlspecialchars($c['endereco_origem']); ?></span>
                            <span><b>Destino:</b> <?php echo htmlspecialchars($c['endereco_destino']); ?></span>
                            <span><b>Data Entrega:</b> <?php echo ($c['data_entrega'] ? date('d/m/Y', strtotime($c['data_entrega'])) : '-'); ?></span>
                        </div>
                        <?php if ($c['foto_comprovante']): ?>
                            <div class="negociacao-acoes"><a href="../../uploads/entregas/<?php echo htmlspecialchars($c['foto_comprovante']); ?>" target="_blank">Ver Comprovante</a></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
