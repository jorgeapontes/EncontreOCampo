<?php
// src/anuncios.php
session_start();
require_once 'conexao.php';

$is_logged_in = isset($_SESSION['usuario_id']);
$usuario_tipo = $_SESSION['usuario_tipo'] ?? null;
$usuario_id = $_SESSION['usuario_id'] ?? null;
$is_comprador = $usuario_tipo === 'comprador' || $usuario_tipo === 'vendedor';

// Conexão e busca dos anúncios
$database = new Database();
$conn = $database->getConnection();
$anuncios = [];

// Parâmetros de filtro e ordenação
$termo_pesquisa = $_GET['pesquisa'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$ordenacao = $_GET['ordenacao'] ?? 'recentes';

// Inicializamos o array de condições
// O status='ativo' será tratado dentro da subquery para gerar o ranking correto
$where_conditions = [];
$params = [];

// Filtro por pesquisa
if (!empty(trim($termo_pesquisa))) {
    $termo_pesquisa = trim($termo_pesquisa);
    // Nota: p.nome refere-se à tabela derivada 'p' definida no SQL abaixo
    $where_conditions[] = "(p.nome LIKE :pesquisa OR p.descricao LIKE :pesquisa OR u.nome LIKE :pesquisa)";
    $params[':pesquisa'] = '%' . $termo_pesquisa . '%';
}

// Filtro por categoria
if (!empty($filtro_categoria)) {
    $where_conditions[] = "p.categoria = :categoria";
    $params[':categoria'] = $filtro_categoria;
}

// Filtro por estado
if (!empty($filtro_estado)) {
    $where_conditions[] = "v.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

// Se o usuário estiver logado como vendedor, não mostrar seus próprios anúncios
if ($is_logged_in && $usuario_tipo === 'vendedor') {
    $where_conditions[] = "v.usuario_id != :usuario_id";
    $params[':usuario_id'] = $usuario_id;
}

// Ordenação
$order_by = '';
switch ($ordenacao) {
    case 'preco_menor':
        $order_by = 'COALESCE(NULLIF(p.preco_desconto, 0), p.preco) ASC';
        break;
    case 'preco_maior':
        $order_by = 'COALESCE(NULLIF(p.preco_desconto, 0), p.preco) DESC';
        break;
    case 'nome':
        $order_by = 'p.nome ASC';
        break;
    case 'estoque':
        $order_by = 'p.estoque DESC';
        break;
    default:
        $order_by = 'p.data_criacao DESC'; // Mostra os mais recentes primeiro na listagem geral
        break;
}

// Categorias e Estados disponíveis para filtros
$categorias_disponiveis = [
    'Frutas Cítricas', 'Frutas Tropicais', 'Frutas de Caroço',
    'Frutas Vermelhas', 'Frutas Secas', 'Frutas Exóticas',
];

$estados_disponiveis = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 
    'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 
    'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
];

try {
    /* LÓGICA PRINCIPAL DE FILTRO POR PLANO:
       1. Criamos uma tabela derivada (p) que seleciona todos os produtos ativos.
       2. Usamos ROW_NUMBER() para numerar os anúncios de cada vendedor (rn), 
          ordenando por ID ASC (os mais antigos recebem números menores: 1, 2, 3...).
       3. Fazemos JOIN com a tabela 'planos' (pl).
       4. No WHERE final, filtramos: p.rn <= pl.limite_total_anuncios.
       Isso oculta automaticamente os anúncios mais novos que excedem o limite do plano atual.
    */
    $sql = "SELECT 
                p.id, 
                p.nome AS produto, 
                p.preco, 
                p.preco_desconto,
                p.desconto_percentual,
                p.desconto_ativo,
                p.desconto_data_inicio,
                p.desconto_data_fim,
                p.estoque AS estoque_kg,
                p.estoque_unidades,
                p.modo_precificacao,
                p.embalagem_peso_kg,
                p.embalagem_unidades,
                p.unidade_medida,
                p.paletizado,
                p.descricao, 
                p.imagem_url, 
                p.categoria,
                p.data_criacao,
                v.nome_comercial AS nome_vendedor, 
                v.estado AS estado_vendedor,
                u.id AS vendedor_usuario_id,
                pl.limite_total_anuncios
            FROM (
                SELECT 
                    produtos.*,
                    ROW_NUMBER() OVER (PARTITION BY vendedor_id ORDER BY id ASC) as rn
                FROM produtos 
                WHERE status = 'ativo'
            ) p
            JOIN vendedores v ON p.vendedor_id = v.id 
            JOIN planos pl ON v.plano_id = pl.id
            JOIN usuarios u ON v.usuario_id = u.id 
            WHERE p.rn <= pl.limite_total_anuncios";

    // Adiciona as condições extras (pesquisa, categoria, estado) se houverem
    if (!empty($where_conditions)) {
        $sql .= " AND " . implode(' AND ', $where_conditions);
    }

    $sql .= " ORDER BY " . $order_by;
            
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->execute();
    $anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar anúncios: " . $e->getMessage()); 
}

// Ajusta campos de exibição para compatibilidade com o template
foreach ($anuncios as &$a) {
    $modo = $a['modo_precificacao'] ?? 'por_quilo';
    
    // Define a quantidade disponível visual
    if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
        $a['quantidade_disponivel'] = $a['estoque_unidades'] ?? 0;
    } else {
        $a['quantidade_disponivel'] = $a['estoque_kg'] ?? 0;
    }

    // Define unidade de medida para exibição
    switch ($modo) {
        case 'por_unidade':
            $a['unidade_medida_exib'] = 'unidade';
            break;
        case 'por_quilo':
            $a['unidade_medida_exib'] = 'kg';
            break;
        case 'caixa_unidades':
            $emb_u = $a['embalagem_unidades'] ? intval($a['embalagem_unidades']) : '';
            $a['unidade_medida_exib'] = 'caixa' . ($emb_u ? " ({$emb_u} unid)" : '');
            break;
        case 'caixa_quilos':
            $emb_k = $a['embalagem_peso_kg'] ? $a['embalagem_peso_kg'] : '';
            $a['unidade_medida_exib'] = 'caixa' . ($emb_k ? " ({$emb_k} kg)" : '');
            break;
        case 'saco_unidades':
            $emb_u = $a['embalagem_unidades'] ? intval($a['embalagem_unidades']) : '';
            $a['unidade_medida_exib'] = 'saco' . ($emb_u ? " ({$emb_u} unid)" : '');
            break;
        case 'saco_quilos':
            $emb_k = $a['embalagem_peso_kg'] ? $a['embalagem_peso_kg'] : '';
            $a['unidade_medida_exib'] = 'saco' . ($emb_k ? " ({$emb_k} kg)" : '');
            break;
        default:
            $a['unidade_medida_exib'] = $a['unidade_medida'] ?? 'kg';
    }
    $a['unidade_medida'] = $a['unidade_medida_exib'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anúncios - Encontre Ocampo</title>
    <link rel="stylesheet" href="css/anuncios.css">
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <img src="../img/logo-nova.png" alt="Logo">
                    <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                        <li class="nav-item">
                            <a href="<?= $_SESSION['usuario_tipo'] ?>/dashboard.php" class="nav-link">Painel</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= $_SESSION['usuario_tipo'] ?>/perfil.php" class="nav-link">Meu Perfil</a>
                        </li>
                        <li class="nav-item">
                            <a href="notificacoes.php" class="nav-link no-underline">
                                <i class="fas fa-bell"></i>
                                <?php
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                                ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link exit-button no-underline">Sair</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="nav-link login-button no-underline">Login</a>
                        </li>
                    <?php endif; ?>
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

    <main class="main-content">
        <section class="header">
            <center>
                <h1>
                    <?php if (!empty($termo_pesquisa)): ?>
                        Resultados para "<?php echo htmlspecialchars($termo_pesquisa); ?>"
                    <?php elseif (!empty($filtro_estado)): ?>
                        Anúncios do estado <?php echo htmlspecialchars($filtro_estado); ?>
                    <?php elseif (!empty($filtro_categoria)): ?>
                        Anúncios de <?php echo htmlspecialchars($filtro_categoria); ?>
                    <?php else: ?>
                        Anúncios Ativos
                    <?php endif; ?>
                </h1>
                <p>
                    <?php if (!empty($termo_pesquisa)): ?>
                        <?php echo count($anuncios); ?> anúncio(s) encontrado(s)
                    <?php else: ?>
                        Explore as ofertas de frutas e legumes dos nossos vendedores
                    <?php endif; ?>
                </p>
            </center>
        </section>

        <div class="search-filters-area">
            <div class="search-container">
                <form action="anuncios.php" method="GET" class="search-form">
                    <div class="search-box">
                        <input type="text" 
                               name="pesquisa" 
                               placeholder="Pesquisar produtos, vendedores..." 
                               value="<?php echo htmlspecialchars($termo_pesquisa); ?>"
                               class="search-input">
                        <?php if (!empty($termo_pesquisa)): ?>
                            <a href="anuncios.php" class="btn-clear-search">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="filtros-simples">
                <div class="filtros-botoes">
                    <div class="dropdown">
                        <button class="filtro-btn">
                            <i class="fas fa-filter"></i>
                            Filtrar
                            <?php if (!empty($filtro_categoria)): ?>
                                <span class="filtro-ativo-indicator"></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-content">
                            <form method="GET" action="anuncios.php" class="filtro-form">
                                <?php if (!empty($termo_pesquisa)): ?> <input type="hidden" name="pesquisa" value="<?= htmlspecialchars($termo_pesquisa) ?>"> <?php endif; ?>
                                <?php if (!empty($filtro_estado)): ?> <input type="hidden" name="estado" value="<?= htmlspecialchars($filtro_estado) ?>"> <?php endif; ?>
                                <input type="hidden" name="ordenacao" value="<?= htmlspecialchars($ordenacao) ?>">
                                
                                <div class="filtro-header">
                                    <span><i class="fas fa-apple-alt"></i> Categorias</span>
                                    <?php if (!empty($filtro_categoria)): ?>
                                        <a href="anuncios.php?<?= http_build_query(array_merge($_GET, ['categoria' => null])) ?>" class="remove-filtro">Limpar</a>
                                    <?php endif; ?>
                                </div>
                                <div class="categorias-list">
                                    <?php foreach ($categorias_disponiveis as $cat): ?>
                                        <label class="filtro-option">
                                            <input type="radio" name="categoria" value="<?= htmlspecialchars($cat) ?>" 
                                                <?= ($filtro_categoria === $cat) ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <span><?= htmlspecialchars($cat) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="dropdown">
                        <button class="filtro-btn">
                            <i class="fas fa-map-marker-alt"></i>
                            Localização
                            <?php if (!empty($filtro_estado)): ?>
                                <span class="filtro-ativo-indicator"></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-content localizacao-dropdown">
                            <form method="GET" action="anuncios.php" class="filtro-form">
                                <?php if (!empty($termo_pesquisa)): ?> <input type="hidden" name="pesquisa" value="<?= htmlspecialchars($termo_pesquisa) ?>"> <?php endif; ?>
                                <?php if (!empty($filtro_categoria)): ?> <input type="hidden" name="categoria" value="<?= htmlspecialchars($filtro_categoria) ?>"> <?php endif; ?>
                                <input type="hidden" name="ordenacao" value="<?= htmlspecialchars($ordenacao) ?>">
                                
                                <div class="filtro-header">
                                    <span><i class="fas fa-map"></i> Estados</span>
                                    <?php if (!empty($filtro_estado)): ?>
                                        <a href="anuncios.php?<?= http_build_query(array_merge($_GET, ['estado' => null])) ?>" class="remove-filtro">Limpar</a>
                                    <?php endif; ?>
                                </div>
                                <div class="estados-grid">
                                    <?php foreach ($estados_disponiveis as $est): ?>
                                        <label class="estado-option">
                                            <input type="radio" name="estado" value="<?= htmlspecialchars($est) ?>" 
                                                <?= ($filtro_estado === $est) ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <span class="estado-sigla"><?= htmlspecialchars($est) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="dropdown">
                        <button class="filtro-btn">
                            <i class="fas fa-sort"></i>
                            Ordenar
                        </button>
                        <div class="dropdown-content">
                            <form method="GET" action="anuncios.php">
                                <?php foreach($_GET as $key => $val): if($key != 'ordenacao') echo "<input type='hidden' name='$key' value='$val'>"; endforeach; ?>
                                <div class="ordenacao-options">
                                    <label class="ordenacao-option">
                                        <input type="radio" name="ordenacao" value="recentes" <?= ($ordenacao === 'recentes') ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <span>Mais recentes</span>
                                    </label>
                                    <label class="ordenacao-option">
                                        <input type="radio" name="ordenacao" value="preco_menor" <?= ($ordenacao === 'preco_menor') ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <span>Menor preço</span>
                                    </label>
                                    <label class="ordenacao-option">
                                        <input type="radio" name="ordenacao" value="preco_maior" <?= ($ordenacao === 'preco_maior') ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <span>Maior preço</span>
                                    </label>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if (!empty($filtro_categoria) || !empty($filtro_estado)): ?>
                    <div class="filtros-ativos">
                        <?php if (!empty($filtro_categoria)): ?>
                            <span class="filtro-ativo-tag">
                                <?= htmlspecialchars($filtro_categoria) ?>
                                <a href="anuncios.php?<?= http_build_query(array_merge($_GET, ['categoria' => null])) ?>"><i class="fas fa-times"></i></a>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($filtro_estado)): ?>
                            <span class="filtro-ativo-tag">
                                <?= htmlspecialchars($filtro_estado) ?>
                                <a href="anuncios.php?<?= http_build_query(array_merge($_GET, ['estado' => null])) ?>"><i class="fas fa-times"></i></a>
                            </span>
                        <?php endif; ?>
                        
                        <a href="anuncios.php" class="limpar-todos">Limpar tudo</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($anuncios)): ?>
            <div class="empty-state">
                <div class="empty-search">
                    <i class="fas fa-search fa-3x"></i>
                    <h3>Nenhum resultado encontrado</h3>
                    <p>Tente outros filtros ou <a href="anuncios.php">veja todos os anúncios</a></p>
                </div>
            </div>
        <?php else: ?>
            <div class="anuncios-grid">
                <?php foreach ($anuncios as $anuncio): 
                    $tem_desconto = false;
                    $preco_final = $anuncio['preco'];
                    $percentual_off = 0;
                    if ($anuncio['desconto_ativo'] == 1) {
                        $agora = date('Y-m-d H:i:s');
                        if ((!$anuncio['desconto_data_inicio'] || $agora >= $anuncio['desconto_data_inicio']) && 
                            (!$anuncio['desconto_data_fim'] || $agora <= $anuncio['desconto_data_fim'])) {
                            if ($anuncio['preco_desconto'] > 0 && $anuncio['preco_desconto'] < $anuncio['preco']) {
                                $tem_desconto = true;
                                $preco_final = $anuncio['preco_desconto'];
                                $percentual_off = intval($anuncio['desconto_percentual']);
                            }
                        }
                    }
                ?>
                <div class="anuncio-card <?= $tem_desconto ? 'card-desconto' : '' ?>">
                    <div class="card-image">
                        <span class="categoria-badge"><?= htmlspecialchars($anuncio['categoria']) ?></span>
                        <?php if ($tem_desconto): ?><div class="badge-desconto">-<?= $percentual_off ?>%</div><?php endif; ?>
                        <?php 
                            $imagePath = $anuncio['imagem_url'] ? htmlspecialchars($anuncio['imagem_url']) : '../img/placeholder.png';
                            if (strpos($imagePath, '../') === 0) $imagePath = substr($imagePath, 3);
                            if ($anuncio['imagem_url'] && !file_exists($imagePath)) $imagePath = '../img/placeholder.png';
                        ?>
                        <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($anuncio['produto']) ?>" onerror="this.src='../img/placeholder.png'">
                    </div>
                    <div class="card-content">
                        <div class="card-header">
                            <h3><?= htmlspecialchars($anuncio['produto']) ?></h3>
                            <div class="card-subheader">
                                <span class="vendedor"><i class="fas fa-store"></i> <?= htmlspecialchars($anuncio['nome_vendedor']) ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($anuncio['estado_vendedor']) ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="price-container">
                                <?php if ($tem_desconto): ?>
                                    <div class="preco-original">R$ <?= number_format($anuncio['preco'], 2, ',', '.') ?></div>
                                    <div class="price price-desconto">
                                        R$ <?= number_format($preco_final, 2, ',', '.') ?><span>/<?= htmlspecialchars($anuncio['unidade_medida']) ?></span>
                                    </div>
                                    <div class="economia-info">Economia de R$ <?= number_format($anuncio['preco'] - $preco_final, 2, ',', '.') ?></div>
                                <?php else: ?>
                                    <p class="price">R$ <?= number_format($anuncio['preco'], 2, ',', '.') ?><span>/<?= htmlspecialchars($anuncio['unidade_medida']) ?></span></p>
                                <?php endif; ?>
                            </div>
                            <p class="estoque"><i class="fas fa-box"></i> <?= htmlspecialchars($anuncio['quantidade_disponivel']) ?> disponíveis</p>
                        </div>
                        <div class="card-actions">
                            <?php if ($is_logged_in): ?>
                                <?php if ($usuario_tipo === 'comprador' || $usuario_tipo === 'vendedor'): ?>
                                    <a href="comprador/view_ad.php?anuncio_id=<?= $anuncio['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-handshake"></i> Comprar
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-primary" disabled>Apenas Compradores</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="visualizar_anuncio.php?anuncio_id=<?= $anuncio['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> Ver Detalhes
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Acesso Negociador</h3>
            <p>Faça login para continuar.</p>
            <form action="login.php" method="POST">
                <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Senha</label><input type="password" name="password" required></div>
                <button type="submit" class="btn btn-primary">Entrar</button>
            </form>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Encontre o Campo</h4>
                    <ul>
                        <li><a href="../index.php">Página Inicial</a></li>
                        <li><a href="anuncios.php">Ver Anúncios</a></li>
                        <li><a href="favoritos.php">Meus Favoritos</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="../ajuda.php">Central de Ajuda</a></li>
                        <li><a href="../contato.php">Fale Conosco</a></li>
                        <li><a href="sobre.php">Sobre Nós</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="faq.php">FAQ</a></li>
                        <li><a href="termos.php">Termos de Uso</a></li>
                        <li><a href="privacidade.php">Política de Privacidade</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contato</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> contato@encontreocampo.com.br</p>
                        <p><i class="fas fa-phone"></i> (11) 99999-9999</p>
                        <div class="social-links">
                            <a href="#"><i class="fab fa-facebook"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dropdown Logic
        document.querySelectorAll('.filtro-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = this.nextElementSibling;
                document.querySelectorAll('.dropdown-content').forEach(d => { if (d !== dropdown) d.classList.remove('show'); });
                dropdown.classList.toggle('show');
            });
        });
        window.onclick = function(e) {
            if (!e.target.matches('.filtro-btn') && !e.target.closest('.dropdown-content')) {
                document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
            }
            if (e.target == document.getElementById('loginModal')) document.getElementById('loginModal').style.display = "none";
        }
        document.querySelector('.modal-close').onclick = () => document.getElementById('loginModal').style.display = "none";
        document.querySelectorAll('.open-login-modal').forEach(b => b.onclick = () => document.getElementById('loginModal').style.display = "block");
        
        // Hamburger
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        hamburger.addEventListener("click", () => { hamburger.classList.toggle("active"); navMenu.classList.toggle("active"); });
    });
    </script>
</body>
</html>