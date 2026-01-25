<?php
// src/comprador/negociacoes.php
require_once __DIR__ . '/../conexao.php';
if (session_status() == PHP_SESSION_NONE) session_start();
// Verifica se é comprador
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}
$database = new Database();
$db = $database->getConnection();
$usuario_id = $_SESSION['usuario_id'];

// Garantir colunas auxiliares (confirmado, arquivado) em `propostas` quando ausentes.
try {
    $db->query("SELECT confirmado FROM propostas LIMIT 1");
} catch (Exception $e) {
    try {
        $db->exec("ALTER TABLE propostas ADD COLUMN confirmado TINYINT(1) DEFAULT 0");
    } catch (Exception $ex) {
        error_log('Não foi possível adicionar coluna `confirmado`: ' . $ex->getMessage());
    }
}
try {
    $db->query("SELECT arquivado FROM propostas LIMIT 1");
} catch (Exception $e) {
    try {
        $db->exec("ALTER TABLE propostas ADD COLUMN arquivado TINYINT(1) DEFAULT 0");
    } catch (Exception $ex) {
        error_log('Não foi possível adicionar coluna `arquivado`: ' . $ex->getMessage());
    }
}

// Arquivamento não disponível para compradores (remoção intencional).

// Preparar lista de propostas aceitas (compras)
$period = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
$filtro_arquivado = ""; // não usamos arquivar para compradores
// $filtro_periodo will be set later (kept for compatibility)
// ensure it's defined for downstream code
if (!isset($filtro_periodo)) $filtro_periodo = ($period === 'geral') ? '' : "AND DATE_FORMAT(p.data_inicio, '%Y-%m') = :period";
$filtro_periodo = ($period === 'geral') ? '' : "AND DATE_FORMAT(p.data_inicio, '%Y-%m') = :period";

$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$filtro_status = '';
if ($status_filter === 'confirmadas') {
    $filtro_status = 'AND COALESCE(p.confirmado,0) = 1';
} elseif ($status_filter === 'aguardando') {
    $filtro_status = 'AND COALESCE(p.confirmado,0) = 0';
}

$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : 'data_desc';
$order_sql = 'p.data_inicio DESC';
switch ($order_by) {
    case 'data_asc': $order_sql = 'p.data_inicio ASC'; break;
    case 'valor_desc': $order_sql = 'p.valor_total DESC'; break;
    case 'valor_asc': $order_sql = 'p.valor_total ASC'; break;
    default: $order_sql = 'p.data_inicio DESC';
}

$propostas = [];
$total_confirmadas = 0;
$valor_total_confirmadas = 0.00;

try {
    $sql_propostas = "SELECT p.*, pr.nome as produto_nome, pr.imagem_url as produto_imagem, u.nome as vendedor_nome
                      FROM propostas p
                      LEFT JOIN produtos pr ON p.produto_id = pr.id
                      LEFT JOIN usuarios u ON p.vendedor_id = u.id
                      WHERE p.comprador_id = :comprador_id AND p.status = 'aceita' " . $filtro_arquivado . ' ' . $filtro_periodo . ' ' . $filtro_status . "
                      ORDER BY " . $order_sql;
    $stmt = $db->prepare($sql_propostas);
    $stmt->bindParam(':comprador_id', $usuario_id, PDO::PARAM_INT);
    if ($period !== 'geral') $stmt->bindParam(':period', $period);
    $stmt->execute();
    $propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totais de confirmadas
    $totSql = "SELECT COUNT(*) as total_confirmadas, COALESCE(SUM(valor_total),0) as soma_valor FROM propostas WHERE comprador_id = :comprador_id AND confirmado = 1";
    if ($period !== 'geral') $totSql .= " AND DATE_FORMAT(data_inicio, '%Y-%m') = :period";
    $totStmt = $db->prepare($totSql);
    $totStmt->bindParam(':comprador_id', $usuario_id, PDO::PARAM_INT);
    if ($period !== 'geral') $totStmt->bindParam(':period', $period);
    $totStmt->execute();
    $tot = $totStmt->fetch(PDO::FETCH_ASSOC);
    $total_confirmadas = intval($tot['total_confirmadas']);
    $valor_total_confirmadas = floatval($tot['soma_valor']);

} catch (PDOException $e) {
    error_log('Erro ao buscar propostas comprador: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Compras - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/vendedor/vendas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
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
                    <li class="nav-item"><a href="../anuncios.php" class="nav-link">Anúncios</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item"><a href="../notificacoes.php" class="nav-link no-underline"><i class="fas fa-bell"></i></a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <br>
    <div class="main-content">
        <section class="header">
            <center><h1>Histórico de Compras</h1></center>
        </section>

        <form id="periodForm" method="get" style="margin-bottom:12px;display:flex;align-items:center;gap:8px;">
            <label for="period_select">Período:</label>
            <select id="period_select" name="period" onchange="document.getElementById('periodForm').submit()">
                <?php
                $months = [];
                $meses_pt = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
                $months['geral'] = 'Geral';
                for ($i = 0; $i < 12; $i++) {
                    $dt = new DateTime("first day of -$i month");
                    $key = $dt->format('Y-m');
                    $label = $meses_pt[$dt->format('m')] . ' ' . $dt->format('Y');
                    $months[$key] = $label;
                }
                foreach ($months as $k => $label) {
                    $sel = ($k === $period) ? 'selected' : '';
                    echo "<option value=\"$k\" $sel>$label</option>";
                }
                ?>
            </select>
                        <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <input type="hidden" name="order_by" value="<?php echo htmlspecialchars($order_by); ?>">
        </form>

        <section class="info-cards">
            <div class="cardbox"><div class="card"><i class="fas fa-shopping-basket"></i><h3>Total de Compras Confirmadas</h3><p id="counter-total"><?php echo $total_confirmadas; ?></p></div></div>
            <div class="cardbox"><div class="card"><i class="fas fa-dollar-sign"></i><h3>Gasto Total Confirmado</h3><p id="counter-valor">R$ <?php echo number_format($valor_total_confirmadas, 2, ',', '.'); ?></p></div></div>
        </section>

        <section class="section-anuncios">
            <div id="header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <h2 style="margin:0;">Compras (<?php echo count($propostas); ?>)</h2>
                    <form id="filterForm" method="get" style="margin:0;display:flex;align-items:center;gap:8px;">
                        <label for="status_filter">Status:</label>
                        <select id="status_filter" name="status_filter" onchange="document.getElementById('filterForm').submit()">
                            <option value="all" <?php echo ($status_filter==='all')?'selected':''; ?>>Todos</option>
                            <option value="confirmadas" <?php echo ($status_filter==='confirmadas')?'selected':''; ?>>Confirmadas</option>
                            <option value="aguardando" <?php echo ($status_filter==='aguardando')?'selected':''; ?>>Aguardando vendedor confirmar pagamento</option>
                        </select>

                        <label for="order_by">Ordenar:</label>
                        <select id="order_by" name="order_by" onchange="document.getElementById('filterForm').submit()">
                            <option value="data_desc" <?php echo ($order_by==='data_desc')?'selected':''; ?>>Data (mais recentes)</option>
                            <option value="data_asc" <?php echo ($order_by==='data_asc')?'selected':''; ?>>Data (mais antigas)</option>
                            <option value="valor_desc" <?php echo ($order_by==='valor_desc')?'selected':''; ?>>Valor (maior)</option>
                            <option value="valor_asc" <?php echo ($order_by==='valor_asc')?'selected':''; ?>>Valor (menor)</option>
                        </select>

                        <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                    </form>
                </div>
                <div></div>
            </div>

            <div class="tabela-anuncios">
                <?php if (count($propostas) > 0): ?>
                    <div class="cards-list">
                        <?php foreach ($propostas as $p): ?>
                            <div class="proposal-card" id="proposal-<?php echo $p['ID']; ?>">
                                <div class="card-image">
                                    <?php $img = !empty($p['produto_imagem']) ? $p['produto_imagem'] : '../../img/placeholder.png';
                                          $link = '../visualizar_anuncio.php?anuncio_id=' . intval($p['produto_id']); ?>
                                    <a href="<?php echo $link; ?>"><img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($p['produto_nome'] ?? 'Produto'); ?>"></a>
                                </div>
                                <div class="card-content">
                                    <div class="card-row">
                                        <div class="card-title">
                                            <a href="<?php echo $link; ?>" class="card-link"><h3><?php echo htmlspecialchars($p['produto_nome'] ?? 'Produto'); ?></h3></a>
                                            <small class="date"><?php echo date('d/m/Y H:i', strtotime($p['data_inicio'])); ?></small>
                                        </div>
                                    </div>

                                    <div class="card-grid">
                                        <div><strong>Vendedor</strong><div>
                                            <?php if (!empty($p['vendedor_id'])): ?>
                                                <a href="../verperfil.php?usuario_id=<?php echo intval($p['vendedor_id']); ?>"><?php echo htmlspecialchars($p['vendedor_nome'] ?? '—'); ?></a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($p['vendedor_nome'] ?? '—'); ?>
                                            <?php endif; ?>
                                        </div></div>
                                        <div><strong>Quantidade</strong><div><?php echo intval($p['quantidade_proposta']); ?></div></div>
                                        <div><strong>Valor</strong><div>R$ <?php echo number_format($p['valor_total'], 2, ',', '.'); ?></div></div>
                                    </div>

                                    <div class="card-actions">
                                        <?php if (empty($p['confirmado'])): ?>
                                            <span class="pending-badge">Aguardando vendedor confirmar pagamento</span>
                                        <?php else: ?>
                                            <span class="confirmed-badge">Compra confirmada</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Nenhuma compra registrada para exibir.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        hamburger.addEventListener("click", () => {
            hamburger.classList.toggle("active");
            navMenu.classList.toggle("active");
        });

        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));

        // Arquivamento removido para compradores — nenhuma ação cliente necessária aqui.
    </script>
</body>
</html>
