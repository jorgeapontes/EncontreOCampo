<?php
// src/sobre.php
session_start();
require_once 'conexao.php';

// Variáveis de sessão
$is_logged_in = isset($_SESSION['usuario_id']);
$usuario_tipo = $_SESSION['usuario_tipo'] ?? null;

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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nós - Encontre Ocampo</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="css/sobre.css">
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
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link">Comprar</a>
                    </li>
                    <li class="nav-item">
                        <a href="faq.php" class="nav-link">FAQ</a>
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
            <h2>Sobre Nós</h2>
            <p>Conheça a história e missão do Encontre o Campo</p>
        </div>

        <div class="about-content">
            <!-- Seção Hero -->
            <div class="hero-section">
                <div class="hero-image">
                    <img src="../img/logo-nova.png" alt="Encontre o Campo - Conectando o campo à cidade">
                    <div class="image-overlay"></div>
                </div>
                <div class="hero-text">
                    <h1>Conectando o Campo à Cidade</h1>
                    <p>Uma plataforma inovadora que une produtores rurais, compradores e transportadores em um ambiente seguro e eficiente.</p>
                </div>
            </div>

            <!-- Nossa História -->
            <div class="story-section">
                <div class="section-header">
                    <i class="fas fa-history"></i>
                    <h3>Nossa História</h3>
                </div>
                <div class="story-content">
                    <p>O <strong>Encontre o Campo</strong> nasceu da necessidade de criar pontes mais eficientes entre os produtores rurais e o mercado consumidor. Percebemos que muitos agricultores tinham dificuldade em alcançar bons preços para seus produtos, enquanto compradores buscavam por fornecedores confiáveis e produtos de qualidade.</p>
                    
                    <p>Em 2024, decidimos criar uma solução que democratizasse o acesso ao mercado agrícola, utilizando tecnologia para conectar diretamente quem produz com quem precisa comprar, eliminando intermediários e garantindo transações mais justas para todos.</p>
                </div>
            </div>

            <!-- Missão, Visão e Valores -->
            <div class="mvw-section">
                <div class="mvw-grid">
                    <div class="mvw-card">
                        <div class="mvw-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h4>Missão</h4>
                        <p>Conectar produtores rurais, compradores e transportadores através de uma plataforma digital inovadora, promovendo negociações justas, transparentes e eficientes no setor agrícola.</p>
                    </div>

                    <div class="mvw-card">
                        <div class="mvw-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h4>Visão</h4>
                        <p>Ser a principal plataforma de negociação agrícola do Brasil, reconhecida pela confiabilidade, inovação e impacto positivo na cadeia produtiva do agronegócio.</p>
                    </div>

                    <div class="mvw-card">
                        <div class="mvw-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4>Valores</h4>
                        <ul>
                            <li><strong>Transparência:</strong> Todas as negociações são claras e abertas</li>
                            <li><strong>Confiança:</strong> Relações baseadas na honestidade e integridade</li>
                            <li><strong>Inovação:</strong> Busca constante por melhorias e novas soluções</li>
                            <li><strong>Sustentabilidade:</strong> Compromisso com o desenvolvimento sustentável</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- O que Fazemos -->
            <div class="services-section">
                <div class="section-header">
                    <i class="fas fa-handshake"></i>
                    <h3>O que Fazemos</h3>
                </div>
                <div class="services-grid">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-tractor"></i>
                        </div>
                        <h4>Para Produtores</h4>
                        <p>Oferecemos uma vitrine digital para seus produtos, conectando você diretamente com compradores interessados, sem intermediários e com melhor remuneração.</p>
                    </div>

                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-store"></i>
                        </div>
                        <h4>Para Compradores</h4>
                        <p>Proporcionamos acesso a uma variedade de produtos agrícolas diretamente dos produtores, com preços competitivos e garantia de qualidade.</p>
                    </div>

                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h4>Para Transportadores</h4>
                        <p>Conectamos você com oportunidades de fretes regulares, otimizando suas rotas e garantindo melhor aproveitamento da capacidade de carga.</p>
                    </div>
                </div>
            </div>

            <!-- Diferenciais -->
            <div class="features-section">
                <div class="section-header">
                    <i class="fas fa-star"></i>
                    <h3>Nossos Diferenciais</h3>
                </div>
                <div class="features-list">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <div class="feature-text">
                            <h5>Plataforma Segura</h5>
                            <p>Todas as transações são monitoradas e protegidas, garantindo segurança para todos os usuários.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <div class="feature-text">
                            <h5>Preços Justos</h5>
                            <p>Negociação direta entre as partes, eliminando intermediários e garantindo melhores preços.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-bolt"></i>
                        <div class="feature-text">
                            <h5>Agilidade</h5>
                            <p>Processo de negociação rápido e eficiente, economizando tempo para todos os envolvidos.</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <div class="feature-text">
                            <h5>Comunidade Ativa</h5>
                            <p>Rede de usuários verificados e comprometidos com negócios sérios e transparentes.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="cta-section">
                <div class="cta-content">
                    <h3>Junte-se à Nossa Comunidade</h3>
                    <p>Faça parte dessa revolução no agronegócio e descubra como podemos ajudar seu negócio a crescer.</p>
                    <div class="cta-buttons">
                        <a href="anuncios.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart"></i> Ver Anúncios
                        </a>
                        <a href="../index.php#contato" class="btn btn-secondary">
                            <i class="fas fa-user-plus"></i> Cadastrar-se
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Login -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Acesso à Plataforma</h3>
            <p>Faça login para acessar todas as funcionalidades</p>
            
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal functionality
        const modal = document.getElementById('loginModal');
        const closeButton = document.querySelector('.modal-close');
        
        function openModal(e) {
            e.preventDefault();
            modal.style.display = 'block';
        }

        document.querySelectorAll('.open-login-modal').forEach(element => {
            element.addEventListener('click', openModal);
        });

        if (closeButton) {
            closeButton.onclick = function() {
                modal.style.display = 'none';
            }
        }

        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Navbar scroll behavior
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

        // Animação de entrada para os cards
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observar elementos para animação
        document.querySelectorAll('.mvw-card, .service-card, .feature-item').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    });
    </script>
</body>
</html>