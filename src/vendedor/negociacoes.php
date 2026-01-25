<?php
// src/vendedor/vendas.php
require_once 'auth.php';

// Garantir colunas auxiliares (confirmado, arquivado) em `propostas` quando ausentes.
try {
    // testa seleção simples
    $db->query("SELECT confirmado FROM propostas LIMIT 1");
} catch (Exception $e) {
    try {
        $db->exec("ALTER TABLE propostas ADD COLUMN confirmado TINYINT(1) DEFAULT 0");
    } catch (Exception $ex) {
        // se não for possível alterar, apenas segue (não bloqueia a página)
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

// Ações via POST (confirmar / arquivar) — responde JSON quando XHR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false];
    $action = $_POST['action'];
    $proposta_id = isset($_POST['proposta_id']) ? intval($_POST['proposta_id']) : 0;

            if ($proposta_id && in_array($action, ['confirm', 'archive'])) {
        try {
            if ($action === 'confirm') {
                $sql = "UPDATE propostas SET confirmado = 1 WHERE ID = :id AND vendedor_id = :vendedor_id";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':id', $proposta_id, PDO::PARAM_INT);
                $stmt->bindParam(':vendedor_id', $usuario_id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // toggle arquivado
                $get = $db->prepare("SELECT arquivado FROM propostas WHERE ID = :id AND vendedor_id = :vendedor_id LIMIT 1");
                $get->bindParam(':id', $proposta_id, PDO::PARAM_INT);
                $get->bindParam(':vendedor_id', $usuario_id, PDO::PARAM_INT);
                $get->execute();
                $cur = $get->fetch(PDO::FETCH_ASSOC);
                $novo = ($cur && $cur['arquivado']) ? 0 : 1;
                $upd = $db->prepare("UPDATE propostas SET arquivado = :novo WHERE ID = :id AND vendedor_id = :vendedor_id");
                $upd->bindParam(':novo', $novo, PDO::PARAM_INT);
                $upd->bindParam(':id', $proposta_id, PDO::PARAM_INT);
                $upd->bindParam(':vendedor_id', $usuario_id, PDO::PARAM_INT);
                $upd->execute();
            }

            // recalcula contadores (confirmadas) aplicando possível filtro de período enviado via POST
            $period_post = isset($_POST['period']) ? $_POST['period'] : null;
            $totSql = "SELECT COUNT(*) as total_confirmadas, COALESCE(SUM(valor_total),0) as soma_valor FROM propostas WHERE vendedor_id = :vendedor_id AND confirmado = 1 AND COALESCE(arquivado,0) = 0";
            if ($period_post && $period_post !== 'geral') $totSql .= " AND DATE_FORMAT(data_inicio, '%Y-%m') = :period";
            $totStmt = $db->prepare($totSql);
            $totStmt->bindParam(':vendedor_id', $usuario_id, PDO::PARAM_INT);
            if ($period_post && $period_post !== 'geral') $totStmt->bindParam(':period', $period_post);
            $totStmt->execute();
            $tot = $totStmt->fetch(PDO::FETCH_ASSOC);

            $response['success'] = true;
            $response['total_confirmadas'] = intval($tot['total_confirmadas']);
            $response['soma_valor'] = number_format($tot['soma_valor'], 2, ',', '.');
        } catch (PDOException $e) {
            $response['error'] = $e->getMessage();
        }
    } else {
        $response['error'] = 'Parâmetros inválidos';
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

// Preparar lista de propostas aceitas (pendentes de confirmação)
// Período: mês no formato YYYY-MM ou 'geral' para todos os registros
$show_archived = isset($_GET['show_archived']) && $_GET['show_archived'] == '1';
$arquivado_flag = $show_archived ? 1 : 0;
$period = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
$filtro_arquivado = "AND COALESCE(p.arquivado,0) = $arquivado_flag"; // show archived only when flag=1, otherwise only active
$filtro_periodo = ($period === 'geral') ? '' : "AND DATE_FORMAT(p.data_inicio, '%Y-%m') = :period";

// Status filter (all / confirmadas / aguardando)
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$filtro_status = '';
if ($status_filter === 'confirmadas') {
    $filtro_status = 'AND COALESCE(p.confirmado,0) = 1';
} elseif ($status_filter === 'aguardando') {
    $filtro_status = 'AND COALESCE(p.confirmado,0) = 0';
}

// Order by
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
    $sql_propostas = "SELECT p.*, pr.nome as produto_nome, pr.imagem_url as produto_imagem, u.nome as comprador_nome
                      FROM propostas p
                      LEFT JOIN produtos pr ON p.produto_id = pr.id
                      LEFT JOIN usuarios u ON p.comprador_id = u.id
                      WHERE p.vendedor_id = :vendedor_id AND p.status = 'aceita' " . $filtro_arquivado . ' ' . $filtro_periodo . ' ' . $filtro_status . "
                      ORDER BY " . $order_sql;
    $stmt = $db->prepare($sql_propostas);
    $stmt->bindParam(':vendedor_id', $usuario_id, PDO::PARAM_INT);
    if ($period !== 'geral') $stmt->bindParam(':period', $period);
    $stmt->execute();
    $propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totais de confirmadas
    $totSql = "SELECT COUNT(*) as total_confirmadas, COALESCE(SUM(valor_total),0) as soma_valor FROM propostas WHERE vendedor_id = :vendedor_id AND confirmado = 1 AND COALESCE(arquivado,0) = 0";
    if ($period !== 'geral') $totSql .= " AND DATE_FORMAT(data_inicio, '%Y-%m') = :period";
    $totStmt = $db->prepare($totSql);
    $totStmt->bindParam(':vendedor_id', $usuario_id, PDO::PARAM_INT);
    if ($period !== 'geral') $totStmt->bindParam(':period', $period);
    $totStmt->execute();
    $tot = $totStmt->fetch(PDO::FETCH_ASSOC);
    $total_confirmadas = intval($tot['total_confirmadas']);
    $valor_total_confirmadas = floatval($tot['soma_valor']);

} catch (PDOException $e) {
    error_log('Erro ao buscar propostas: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Vendas - Encontre Ocampo</title>
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
                    <li class="nav-item">
                        <a href="../../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="../anuncios.php" class="nav-link">Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            // Contar notificações não lidas
                            if (isset($_SESSION['usuario_id'])) {
                                $database = new Database();
                                $conn = $database->getConnection();
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) {
                                    echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                                }
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link exit-button no-underline">Sair</a>
                    </li>
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
            <center>
                <h1>Minhas Vendas</h1>
            </center>
        </section>

        <!-- seletor de período (mês/Geral) acima dos contadores -->
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
            <!-- preservar filtros atuais ao trocar o período -->
            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
            <input type="hidden" name="order_by" value="<?php echo htmlspecialchars($order_by); ?>">
            <?php if ($show_archived): ?>
                <input type="hidden" name="show_archived" value="1">
            <?php endif; ?>
        </form>

        <section class="info-cards">
            <div class="cardbox">
                <div class="card">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Total de Vendas Confirmadas</h3>
                    <p id="counter-total"><?php echo $total_confirmadas; ?></p>
                </div>
            </div>
            <div class="cardbox">
                <div class="card">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>Valor Total Confirmado</h3>
                    <p id="counter-valor">R$ <?php echo number_format($valor_total_confirmadas, 2, ',', '.'); ?></p>
                </div>
            </div>
            <!-- <div class="cardbox">
                <div class="card">
                    <i class="fas fa-filter"></i>
                    <h3>Ver</h3>
                    <p><?php echo $show_archived ? 'Incluindo arquivadas' : 'Somente ativas'; ?></p>
                </div>
            </div> -->
        </section>

        <section class="section-anuncios">
            <div id="header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <h2 style="margin:0;">Pedidos Aceitos (<?php echo count($propostas); ?>)</h2>
                    <!-- filtro: status + ordenação (mantém period e show_archived via hidden inputs) -->
                    <form id="filterForm" method="get" style="margin:0;display:flex;align-items:center;gap:8px;">
                        <label for="status_filter">Status:</label>
                        <select id="status_filter" name="status_filter" onchange="document.getElementById('filterForm').submit()">
                            <option value="all" <?php echo ($status_filter==='all')?'selected':''; ?>>Todos</option>
                            <option value="confirmadas" <?php echo ($status_filter==='confirmadas')?'selected':''; ?>>Confirmadas</option>
                            <option value="aguardando" <?php echo ($status_filter==='aguardando')?'selected':''; ?>>Aguardando pagamento</option>
                        </select>

                        <label for="order_by">Ordenar:</label>
                        <select id="order_by" name="order_by" onchange="document.getElementById('filterForm').submit()">
                            <option value="data_desc" <?php echo ($order_by==='data_desc')?'selected':''; ?>>Data (mais recentes)</option>
                            <option value="data_asc" <?php echo ($order_by==='data_asc')?'selected':''; ?>>Data (mais antigas)</option>
                            <option value="valor_desc" <?php echo ($order_by==='valor_desc')?'selected':''; ?>>Valor (maior)</option>
                            <option value="valor_asc" <?php echo ($order_by==='valor_asc')?'selected':''; ?>>Valor (menor)</option>
                        </select>

                        <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                        <?php if ($show_archived): ?>
                            <input type="hidden" name="show_archived" value="1">
                        <?php endif; ?>
                    </form>
                </div>
                <div>
                    <?php if ($show_archived): ?>
                        <a href="?<?php echo ($period ? 'period=' . urlencode($period) . '&' : '') ?>">Esconder arquivadas</a>
                    <?php else: ?>
                        <a href="?show_archived=1&<?php echo ($period ? 'period=' . urlencode($period) : '') ?>">Exibir arquivadas</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tabela-anuncios">
                <?php if (count($propostas) > 0): ?>
                    <div class="cards-list">
                        <?php foreach ($propostas as $p): ?>
                            <div class="proposal-card" id="proposal-<?php echo $p['ID']; ?>">
                                <button class="archive-btn" onclick="toggleArchive(<?php echo $p['ID']; ?>)" title="Arquivar"><i class="fas fa-archive"></i></button>
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
                                        <div><strong>Comprador</strong><div><?php echo htmlspecialchars($p['comprador_nome'] ?? '—'); ?></div></div>
                                        <div><strong>Quantidade</strong><div><?php echo intval($p['quantidade_proposta']); ?></div></div>
                                        <div><strong>Valor</strong><div>R$ <?php echo number_format($p['valor_total'], 2, ',', '.'); ?></div></div>
                                    </div>

                                    <div class="card-actions">
                                        <?php if (empty($p['confirmado'])): ?>
                                            <div class="confirm-block">
                                                <div class="confirm-tip">Clique para confirmar a venda:</div>
                                                <button onclick="hideTipThenConfirm(this, <?php echo $p['ID']; ?>)" class="confirm-btn">Recebi o pagamento</button>
                                            </div>
                                        <?php else: ?>
                                            <span class="confirmed-badge">Venda confirmada</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">Nenhum pedido aceito para exibir.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        // Script para menu hamburger
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        hamburger.addEventListener("click", () => {
            hamburger.classList.toggle("active");
            navMenu.classList.toggle("active");
        });

        // Fechar menu mobile ao clicar em um link
        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));

        // Funções para confirmar e arquivar via AJAX
        function hideTipThenConfirm(btn, id) {
            // esconder a dica imediatamente para feedback visual
            try {
                const tip = btn.parentElement.querySelector('.confirm-tip');
                if (tip) tip.style.display = 'none';
            } catch (e) {}
            confirmProposal(id);
        }

        function confirmProposal(id) {
            if (!confirm('Confirma que você recebeu o pagamento dessa venda?')) return;
            fetch('', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: 'confirm', proposta_id: id})
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    // recarrega a página para atualizar todos os contadores
                    window.location.reload();
                } else {
                    alert('Erro: ' + (data.error || 'Falha ao confirmar'));
                }
            }).catch(err => { alert('Erro na requisição'); });
        }

        function toggleArchive(id) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({action: 'archive', proposta_id: id, period: '<?php echo $period; ?>'})
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    // atualizar contadores
                    document.getElementById('counter-total').innerText = data.total_confirmadas;
                    document.getElementById('counter-valor').innerText = 'R$ ' + data.soma_valor;
                    // remover card da lista (se não estivermos exibindo arquivadas)
                    <?php if (!$show_archived): ?>
                    const card = document.getElementById('proposal-' + id);
                    if (card) card.remove();
                    <?php else: ?>
                    // em modo arquivadas, apenas trocar texto do botão
                    const cardA = document.getElementById('proposal-' + id);
                    if (cardA) {
                        const btn = cardA.querySelector('.archive-btn');
                        if (btn) btn.innerHTML = '<i class="fas fa-archive"></i>';
                    }
                    <?php endif; ?>
                } else {
                    alert('Erro: ' + (data.error || 'Falha ao arquivar'));
                }
            }).catch(err => { alert('Erro na requisição'); });
        }
    </script>
</body>
</html>