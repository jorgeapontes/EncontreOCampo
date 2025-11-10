<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anúncios - Encontre o Campo</title>
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="anuncios.css">
    <link rel="shortcut icon" href="img/Logo - Copia.jpg" type="image/x-icon">
</head>
<body>
    <?php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Conexão com o banco de dados usando MySQLi (compatível com PHPMyAdmin)
    $host = 'localhost';
    $dbname = 'encontre_ocampo';
    $username = 'root';
    $password = '';
    
    // Criar conexão
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Verificar conexão
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }
    
    // Processar filtros
    $categoria_filtro = isset($_GET['categoria']) ? $_GET['categoria'] : '';
    $preco_min = isset($_GET['preco_min']) ? $_GET['preco_min'] : '';
    $preco_max = isset($_GET['preco_max']) ? $_GET['preco_max'] : '';
    $pagina = max(1, intval(isset($_GET['pagina']) ? $_GET['pagina'] : 1));
    $itens_por_pagina = 12;
    $offset = ($pagina - 1) * $itens_por_pagina;
    
    // Construir query base
    $sql = "SELECT p.*, v.nome_comercial as vendedor_nome 
            FROM produtos p 
            INNER JOIN vendedores v ON p.vendedor_id = v.id 
            WHERE p.status = 'ativo'";
    
    $params = [];
    $types = "";
    
    if (!empty($categoria_filtro)) {
        $sql .= " AND p.categoria = ?";
        $params[] = $categoria_filtro;
        $types .= "s";
    }
    
    if (!empty($preco_min) && is_numeric($preco_min)) {
        $sql .= " AND p.preco >= ?";
        $params[] = floatval($preco_min);
        $types .= "d";
    }
    
    if (!empty($preco_max) && is_numeric($preco_max)) {
        $sql .= " AND p.preco <= ?";
        $params[] = floatval($preco_max);
        $types .= "d";
    }
    
    // Query para contar total (para paginação)
    $sql_count = "SELECT COUNT(*) as total FROM produtos p WHERE p.status = 'ativo'";
    $params_count = [];
    $types_count = "";
    
    if (!empty($categoria_filtro)) {
        $sql_count .= " AND p.categoria = ?";
        $params_count[] = $categoria_filtro;
        $types_count .= "s";
    }
    
    if (!empty($preco_min) && is_numeric($preco_min)) {
        $sql_count .= " AND p.preco >= ?";
        $params_count[] = floatval($preco_min);
        $types_count .= "d";
    }
    
    if (!empty($preco_max) && is_numeric($preco_max)) {
        $sql_count .= " AND p.preco <= ?";
        $params_count[] = floatval($preco_max);
        $types_count .= "d";
    }
    
    // Executar contagem
    $stmt_count = $conn->prepare($sql_count);
    if (!empty($params_count)) {
        $stmt_count->bind_param($types_count, ...$params_count);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $total_anuncios = $row_count['total'];
    $total_paginas = ceil($total_anuncios / $itens_por_pagina);
    $stmt_count->close();
    
    // Query principal com ordenação e paginação
    $sql .= " ORDER BY p.data_criacao DESC LIMIT ? OFFSET ?";
    $params[] = $itens_por_pagina;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $anuncios = [];
    while ($row = $result->fetch_assoc()) {
        $anuncios[] = $row;
    }
    $stmt->close();
    
    // Buscar categorias disponíveis
    $categorias_result = $conn->query("SELECT DISTINCT categoria FROM produtos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
    $categorias = [];
    while ($row = $categorias_result->fetch_assoc()) {
        $categorias[] = $row['categoria'];
    }
    
    // Fechar conexão
    $conn->close();
    ?>
    
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">Início</a>
                    </li>
                    <li class="nav-item">
                        <a href="#filtros" class="nav-link">Filtrar</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php#vender" class="nav-link">Vender</a>
                    </li>
                    <li class="nav-item">
                        <a href="src/login.php" class="nav-link login-button no-underline">Login</a>
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

    <section class="anuncios-container">
        <div class="container">
            <h2 class="section-title">Nossos Anúncios</h2>
            
            <div id="filtros" class="filtros-container">
                <form method="GET" action="anuncios.php">
                    <div class="filtros-grid">
                        <div class="filtro-group">
                            <label for="categoria">Categoria</label>
                            <select id="categoria" name="categoria">
                                <option value="">Todas as categorias</option>
                                <?php foreach($categorias as $categoria): ?>
                                    <option value="<?= htmlspecialchars($categoria) ?>" 
                                        <?= $categoria_filtro === $categoria ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filtro-group">
                            <label for="preco_min">Preço Mínimo (R$)</label>
                            <input type="number" id="preco_min" name="preco_min" 
                                   value="<?= htmlspecialchars($preco_min) ?>" 
                                   placeholder="0.00" step="0.01" min="0">
                        </div>
                        
                        <div class="filtro-group">
                            <label for="preco_max">Preço Máximo (R$)</label>
                            <input type="number" id="preco_max" name="preco_max" 
                                   value="<?= htmlspecialchars($preco_max) ?>" 
                                   placeholder="1000.00" step="0.01" min="0">
                        </div>
                        
                        <div class="filtro-group">
                            <button type="submit" class="btn-filtrar">Filtrar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="resultados-info">
                <?php if ($total_anuncios > 0): ?>
                    Mostrando <?= min($itens_por_pagina, count($anuncios)) ?> de <?= $total_anuncios ?> anúncio(s) encontrado(s)
                <?php endif; ?>
            </div>

            <?php if (empty($anuncios)): ?>
                <div class="sem-anuncios">
                    <h3>Nenhum anúncio encontrado</h3>
                    <p>Não encontramos anúncios com os filtros selecionados.</p>
                </div>
            <?php else: ?>
                <div class="anuncios-grid">
                    <?php foreach($anuncios as $anuncio): ?>
                        <div class="anuncio-card">
                            <div class="anuncio-imagem <?= empty($anuncio['imagem_url']) ? 'anuncio-sem-imagem' : '' ?>" 
                                 style="<?= !empty($anuncio['imagem_url']) ? "background-image: url('" . htmlspecialchars($anuncio['imagem_url']) . "')" : '' ?>">
                                <?php if (empty($anuncio['imagem_url'])): ?>
                                    <span>Sem imagem</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="anuncio-info">
                                <h3 class="anuncio-titulo"><?= htmlspecialchars($anuncio['nome']) ?></h3>
                                
                                <?php if (!empty($anuncio['descricao'])): ?>
                                    <p class="anuncio-descricao"><?= htmlspecialchars($anuncio['descricao']) ?></p>
                                <?php endif; ?>
                                
                                <div class="anuncio-detalhes">
                                    <span class="anuncio-preco">R$ <?= number_format($anuncio['preco'], 2, ',', '.') ?></span>
                                    <?php if (!empty($anuncio['categoria'])): ?>
                                        <span class="anuncio-categoria"><?= htmlspecialchars($anuncio['categoria']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="anuncio-vendedor">
                                    <span>Por: <?= htmlspecialchars($anuncio['vendedor_nome']) ?></span>
                                    <span class="anuncio-estoque">Estoque: <?= $anuncio['estoque'] ?></span>
                                </div>
                                
                                <button class="btn-comprar" 
                                        onclick="comprarProduto(<?= $anuncio['id'] ?>)">
                                    Comprar Agora
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_paginas > 1): ?>
                    <div class="paginacao">
                        <button class="btn-pagina" 
                                onclick="mudarPagina(<?= $pagina - 1 ?>)" 
                                <?= $pagina <= 1 ? 'disabled' : '' ?>>
                            ← Anterior
                        </button>
                        
                        <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                            <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina - 2 && $i <= $pagina + 2)): ?>
                                <button class="btn-pagina <?= $i == $pagina ? 'ativo' : '' ?>" 
                                        onclick="mudarPagina(<?= $i ?>)">
                                    <?= $i ?>
                                </button>
                            <?php elseif ($i == $pagina - 3 || $i == $pagina + 3): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <button class="btn-pagina" 
                                onclick="mudarPagina(<?= $pagina + 1 ?>)" 
                                <?= $pagina >= $total_paginas ? 'disabled' : '' ?>>
                            Próxima →
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <h2>ENCONTRE</h2>
                    <h3>O CAMPO</h3>
                    <p>Conectando o campo à cidade</p>
                </div>
                <div class="footer-links">
                    <div class="footer-column">
                        <h4>Navegação</h4>
                        <ul>
                            <li><a href="index.php">Início</a></li>
                            <li><a href="anuncios.php">Anúncios</a></li>
                            <li><a href="index.php#vender">Vender</a></li>
                            <li><a href="index.php#transporte">Transporte</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h4>Suporte</h4>
                        <ul>
                            <li><a href="index.php#contato">Contato</a></li>
                            <li><a href="#">FAQ</a></li>
                            <li><a href="#">Termos de Uso</a></li>
                            <li><a href="#">Política de Privacidade</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        // Função para comprar produto
        function comprarProduto(produtoId) {
            alert('Para comprar este produto, faça login em sua conta.');
            window.location.href = 'src/login.php';
        }

        // Função para mudar página
        function mudarPagina(pagina) {
            const url = new URL(window.location.href);
            url.searchParams.set('pagina', pagina);
            window.location.href = url.toString();
        }

        // Mobile menu toggle
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        if (hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
        }

        // Close mobile menu when clicking on a link
        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            if (hamburger) hamburger.classList.remove("active");
            if (navMenu) navMenu.classList.remove("active");
        }));

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 70,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (navbar && window.scrollY > 50) {
                navbar.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
            } else if (navbar) {
                navbar.style.backgroundColor = 'var(--white)';
                navbar.style.backdropFilter = 'none';
            }
        });
    </script>
</body>
</html>