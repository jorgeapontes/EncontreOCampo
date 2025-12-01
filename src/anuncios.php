<?php
// src/anuncios.php (COM LÓGICA DE DESCONTO APLICADA)
session_start();
require_once 'conexao.php'; 

// Variáveis de sessão
$is_logged_in = isset($_SESSION['usuario_id']);
$usuario_tipo = $_SESSION['usuario_tipo'] ?? null;
$is_comprador = $usuario_tipo === 'comprador';

// Lógica para o botão de acesso/perfil na navbar
if ($is_logged_in) {
    $button_text = 'Olá, ' . htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário');
    if ($usuario_tipo == 'admin') {
        $button_action = 'admin/dashboard.php';
    } elseif ($usuario_tipo == 'comprador') {
        $button_action = 'comprador/dashboard.php';
    } elseif ($usuario_tipo == 'vendedor') {
        $button_action = 'vendedor/dashboard.php';
    } else {
        $button_action = '#'; // Fallback
    }
} else {
    $button_text = 'Login';
    $button_action = '#'; // Abrirá o modal de login
}

// Conexão e busca dos anúncios
$database = new Database();
$conn = $database->getConnection();
$anuncios = [];

// Parâmetros de filtro e ordenação
$termo_pesquisa = $_GET['pesquisa'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$ordenacao = $_GET['ordenacao'] ?? 'recentes';

$where_conditions = ["p.status = 'ativo'"];
$params = [];

// Filtro por pesquisa
if (!empty(trim($termo_pesquisa))) {
    $termo_pesquisa = trim($termo_pesquisa);
    $where_conditions[] = "(p.nome LIKE :pesquisa OR p.descricao LIKE :pesquisa OR u.nome LIKE :pesquisa)";
    $params[':pesquisa'] = '%' . $termo_pesquisa . '%';
}

// Filtro por categoria
if (!empty($filtro_categoria)) {
    $where_conditions[] = "p.categoria = :categoria";
    $params[':categoria'] = $filtro_categoria;
}

// Ordenação
$order_by = '';
switch ($ordenacao) {
    case 'preco_menor':
        // Ordena pelo preço real (considerando desconto se houver)
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
    default: // recentes
        $order_by = 'p.data_criacao DESC';
        break;
}

// Categorias disponíveis para filtro
$categorias_disponiveis = [
    'Frutas Cítricas',
    'Frutas Tropicais',
    'Frutas de Caroço',
    'Frutas Vermelhas',
    'Frutas Secas',
    'Frutas Exóticas',
];

try {
    // ATUALIZAÇÃO SQL: Adicionados campos de desconto
    $sql = "SELECT 
                p.id, 
                p.nome AS produto, 
                p.preco, 
                p.preco_desconto,
                p.desconto_percentual,
                p.desconto_ativo,
                p.desconto_data_inicio,
                p.desconto_data_fim,
                p.estoque AS quantidade_disponivel, 
                p.unidade_medida, 
                p.descricao, 
                p.imagem_url, 
                p.categoria,
                u.nome AS nome_vendedor, 
                u.id AS vendedor_usuario_id 
            FROM produtos p
            JOIN vendedores v ON p.vendedor_id = v.id 
            JOIN usuarios u ON v.usuario_id = u.id 
            WHERE " . implode(' AND ', $where_conditions) . "
            ORDER BY " . $order_by;
            
    $stmt = $conn->prepare($sql);
    
    // Bind dos parâmetros
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar anúncios: " . $e->getMessage()); 
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

                <div class="search-container">
                    <form action="anuncios.php" method="GET" class="search-form">
                        <div class="search-box">
                            <input type="text" 
                                   name="pesquisa" 
                                   placeholder="Pesquisar produtos, vendedores..." 
                                   value="<?php echo htmlspecialchars($termo_pesquisa); ?>"
                                   class="search-input">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link active">Comprar</a>
                    </li>
                    <li class="nav-item">
                        <a href="comprador/favoritos.php" class="nav-link">Favoritos</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo htmlspecialchars($button_action); ?>" 
                           class="nav-link <?php echo $is_logged_in ? 'user-profile' : 'open-login-modal'; ?>"
                           <?php if (!$is_logged_in) echo 'data-target="#loginModal"'; ?>>
                            <?php echo htmlspecialchars($button_text); ?>
                        </a>
                    </li>
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link exit-button no-underline">Sair</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="page-header">
            <h2>
                <?php if (!empty($termo_pesquisa)): ?>
                    Resultados para "<?php echo htmlspecialchars($termo_pesquisa); ?>"
                <?php else: ?>
                    Anúncios Ativos
                <?php endif; ?>
            </h2>
            <p>
                <?php if (!empty($termo_pesquisa)): ?>
                    <?php echo count($anuncios); ?> anúncio(s) encontrado(s)
                <?php else: ?>
                    Explore as ofertas dos nossos vendedores
                <?php endif; ?>
            </p>
            
            <?php if (!empty($termo_pesquisa)): ?>
                <div class="search-actions">
                    <a href="anuncios.php" class="btn-clear-search">
                        <i class="fas fa-times"></i> Limpar pesquisa
                    </a>
                </div>
            <?php endif; ?>
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
                            <?php if (!empty($termo_pesquisa)): ?>
                                <input type="hidden" name="pesquisa" value="<?php echo htmlspecialchars($termo_pesquisa); ?>">
                            <?php endif; ?>
                            <input type="hidden" name="ordenacao" value="<?php echo htmlspecialchars($ordenacao); ?>">
                            
                            <div class="categorias-list">
                                <div class="categoria-header">Categorias</div>
                                <?php foreach ($categorias_disponiveis as $categoria_option): ?>
                                    <label class="categoria-option">
                                        <input type="radio" name="categoria" value="<?php echo htmlspecialchars($categoria_option); ?>" 
                                            <?php echo ($filtro_categoria === $categoria_option) ? 'checked' : ''; ?>
                                            onchange="this.form.submit()">
                                        <span><?php echo htmlspecialchars($categoria_option); ?></span>
                                    </label>
                                <?php endforeach; ?>
                                
                                <?php if (!empty($filtro_categoria)): ?>
                                    <div class="categoria-actions">
                                        <button type="submit" name="categoria" value="" class="btn-limpar">
                                            <i class="fas fa-times"></i> Limpar filtro
                                        </button>
                                    </div>
                                <?php endif; ?>
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
                        <form method="GET" action="anuncios.php" class="filtro-form">
                            <?php if (!empty($termo_pesquisa)): ?>
                                <input type="hidden" name="pesquisa" value="<?php echo htmlspecialchars($termo_pesquisa); ?>">
                            <?php endif; ?>
                            <?php if (!empty($filtro_categoria)): ?>
                                <input type="hidden" name="categoria" value="<?php echo htmlspecialchars($filtro_categoria); ?>">
                            <?php endif; ?>
                            
                            <div class="ordenacao-options">
                                <label class="ordenacao-option">
                                    <input type="radio" name="ordenacao" value="recentes" 
                                        <?php echo ($ordenacao === 'recentes') ? 'checked' : ''; ?>
                                        onchange="this.form.submit()">
                                    <span>Mais recentes</span>
                                </label>
                                <label class="ordenacao-option">
                                    <input type="radio" name="ordenacao" value="preco_menor" 
                                        <?php echo ($ordenacao === 'preco_menor') ? 'checked' : ''; ?>
                                        onchange="this.form.submit()">
                                    <span>Menor preço</span>
                                </label>
                                <label class="ordenacao-option">
                                    <input type="radio" name="ordenacao" value="preco_maior" 
                                        <?php echo ($ordenacao === 'preco_maior') ? 'checked' : ''; ?>
                                        onchange="this.form.submit()">
                                    <span>Maior preço</span>
                                </label>
                                <label class="ordenacao-option">
                                    <input type="radio" name="ordenacao" value="nome" 
                                        <?php echo ($ordenacao === 'nome') ? 'checked' : ''; ?>
                                        onchange="this.form.submit()">
                                    <span>Nome (A-Z)</span>
                                </label>
                                <label class="ordenacao-option">
                                    <input type="radio" name="ordenacao" value="estoque" 
                                        <?php echo ($ordenacao === 'estoque') ? 'checked' : ''; ?>
                                        onchange="this.form.submit()">
                                    <span>Maior estoque</span>
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php if (!empty($filtro_categoria)): ?>
                <div class="filtro-info">
                    <span class="filtro-ativo-texto">
                        Filtro: <strong><?php echo htmlspecialchars($filtro_categoria); ?></strong>
                        <a href="anuncios.php<?php echo !empty($termo_pesquisa) ? '?pesquisa=' . urlencode($termo_pesquisa) : ''; ?>" class="remove-filtro">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($anuncios)): ?>
            <div class="empty-state">
                <?php if (!empty($termo_pesquisa) || !empty($filtro_categoria)): ?>
                    <div class="empty-search">
                        <i class="fas fa-search fa-3x" style="color: var(--text-light); margin-bottom: 20px;"></i>
                        <h3>Nenhum resultado encontrado</h3>
                        <p>Tente outros termos ou <a href="anuncios.php">veja todos os anúncios</a></p>
                    </div>
                <?php else: ?>
                    <p>Nenhum anúncio ativo encontrado no momento.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="anuncios-grid">
                <?php foreach ($anuncios as $anuncio): ?>
                    <?php
                        // LÓGICA DE VERIFICAÇÃO DO DESCONTO
                        $tem_desconto = false;
                        $preco_final = $anuncio['preco'];
                        $percentual_off = 0;

                        if ($anuncio['desconto_ativo'] == 1) {
                            $agora = date('Y-m-d H:i:s');
                            $inicio = $anuncio['desconto_data_inicio'];
                            $fim = $anuncio['desconto_data_fim'];
                            
                            // Verifica se as datas são válidas (ou se são nulas/permanentes)
                            $data_valida = true;
                            if ($inicio && $agora < $inicio) $data_valida = false;
                            if ($fim && $agora > $fim) $data_valida = false;

                            if ($data_valida && $anuncio['preco_desconto'] > 0 && $anuncio['preco_desconto'] < $anuncio['preco']) {
                                $tem_desconto = true;
                                $preco_final = $anuncio['preco_desconto'];
                                $percentual_off = intval($anuncio['desconto_percentual']);
                            }
                        }
                    ?>

                    <div class="anuncio-card <?php echo $tem_desconto ? 'card-desconto' : ''; ?>">
                        <div class="card-image">
                            <?php if ($tem_desconto): ?>
                                <div class="badge-desconto">-<?php echo $percentual_off; ?>%</div>
                            <?php endif; ?>

                            <?php 
                                $imagePath = $anuncio['imagem_url'] ? htmlspecialchars($anuncio['imagem_url']) : '../img/placeholder.png';
                                if (strpos($imagePath, '../') === 0) $imagePath = substr($imagePath, 3);
                                if ($anuncio['imagem_url'] && !file_exists($imagePath)) $imagePath = '../img/placeholder.png';
                            ?>
                            <img src="<?php echo $imagePath; ?>" alt="Imagem de <?php echo htmlspecialchars($anuncio['produto']); ?>" 
                                onerror="this.src='../img/placeholder.png'">
                        </div>
                        <div class="card-content">
                            <div class="card-header">
                                <h3><?php echo htmlspecialchars($anuncio['produto']); ?></h3>
                                <span class="vendedor">por <a href="perfil_vendedor.php?vendedor_id=<?php echo $anuncio['vendedor_usuario_id']; ?>" 
                                    style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                    <?php echo htmlspecialchars($anuncio['nome_vendedor']); ?>   </a>   </span>
                                <span class="categoria-badge"><?php echo htmlspecialchars($anuncio['categoria']); ?></span>
                            </div>
                            
                            <div class="card-body">
                                <div class="price-container">
                                    <?php if ($tem_desconto): ?>
                                        <div class="preco-original">R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?></div>
                                        <div class="price price-desconto">
                                            R$ <?php echo number_format($preco_final, 2, ',', '.'); ?>
                                            <span>/<?php echo htmlspecialchars($anuncio['unidade_medida']); ?></span>
                                        </div>
                                        <div class="economia-info">
                                            <i class="fas fa-tag"></i> Economia de R$ <?php echo number_format($anuncio['preco'] - $preco_final, 2, ',', '.'); ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="price">
                                            R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?>
                                            <span>/<?php echo htmlspecialchars($anuncio['unidade_medida']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <p class="estoque">
                                    <i class="fas fa-box"></i>
                                    <?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> disponíveis
                                </p>
                                
                                <p class="descricao">
                                    <?php 
                                    $descricao = htmlspecialchars($anuncio['descricao'] ?? 'Sem descrição.');
                                    $limite = 120;
                                    if (strlen($descricao) > $limite) {
                                        $descricao_curta = substr($descricao, 0, $limite);
                                        $ultimo_espaco = strrpos($descricao_curta, ' ');
                                        echo ($ultimo_espaco !== false) ? substr($descricao_curta, 0, $ultimo_espaco) . '...' : $descricao_curta . '...';
                                    } else {
                                        echo $descricao;
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <div class="card-actions">
                                <?php if ($is_comprador): ?>
                                    <a href="comprador/proposta_nova.php?anuncio_id=<?php echo $anuncio['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-handshake"></i> Comprar
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-primary open-login-modal" data-target="#loginModal">
                                        <i class="fas fa-handshake"></i> Comprar
                                    </button>
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
            <p>
                É necessário estar logado para comprar ou para fazer uma proposta.
            </p>
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="modal-email">Email</label>
                    <input type="email" id="modal-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="modal-password">Senha</label>
                    <input type="password" id="modal-password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Entrar</button>
                <div style="text-align: center; margin-top: 15px;">
                    Não tem conta? <a href="../index.php#contato" target="_blank">Registre-se</a>
                </div>
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
                        <li><a href="comprador/favoritos.php">Meus Favoritos</a></li>
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
        // Modal Logic
        const modal = document.getElementById('loginModal');
        const closeButton = document.querySelector('.modal-close');
        
        function openModal(e) { e.preventDefault(); modal.style.display = 'block'; }
        document.querySelectorAll('.open-login-modal').forEach(e => e.addEventListener('click', openModal));
        if (closeButton) closeButton.onclick = () => modal.style.display = 'none';
        window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; }
        
        // Navbar Scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (navbar && window.scrollY > 50) {
                navbar.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
                navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.05)';
            } else if (navbar) {
                navbar.style.backgroundColor = 'var(--white)';
                navbar.style.backdropFilter = 'none';
                navbar.style.boxShadow = 'none';
            }
        });

        // Search Focus
        const searchBtn = document.querySelector('.search-btn');
        const searchInput = document.querySelector('.search-input');
        if (searchBtn && searchInput) {
            searchBtn.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        }
    });
    </script>
</body>
</html>