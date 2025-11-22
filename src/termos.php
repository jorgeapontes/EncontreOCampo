<?php
// src/termos.php
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
    <title>Termos de Uso - Encontre Ocampo</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="css/termos.css">
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
                            <a href="logout.php" class="nav-link logout">Sair</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="page-header">
            <h2>Termos de Uso</h2>
            <p>Última atualização: <?php echo date('d/m/Y'); ?></p>
        </div>

        <div class="legal-content">
            <div class="legal-section">
                <h3><i class="fas fa-gavel"></i> 1. Aceitação dos Termos</h3>
                <p>Ao acessar e usar a plataforma Encontre o Campo, você concorda em cumprir e estar vinculado a estes Termos de Uso. Se você não concordar com qualquer parte destes termos, não poderá usar nossos serviços.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-user-check"></i> 2. Cadastro na Plataforma</h3>
                <p>Para usar todas as funcionalidades da plataforma, é necessário:</p>
                <ul>
                    <li>Fornecer informações verdadeiras e completas no cadastro</li>
                    <li>Manter suas informações de contato atualizadas</li>
                    <li>Ser maior de 18 anos ou ter autorização legal</li>
                    <li>Utilizar apenas uma conta por perfil (comprador, vendedor, transportador)</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-shopping-cart"></i> 3. Transações e Negociações</h3>
                <p><strong>3.1 Responsabilidades:</strong> A plataforma atua como intermediária nas negociações. Compradores e vendedores são responsáveis por:</p>
                <ul>
                    <li>Verificar a qualidade e quantidade dos produtos</li>
                    <li>Combinar condições de pagamento e entrega</li>
                    <li>Cumprir com os acordos estabelecidos</li>
                </ul>
                
                <p><strong>3.2 Propostas:</strong> As propostas feitas através da plataforma são juridicamente vinculativas quando aceitas pela outra parte.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-ban"></i> 4. Condutas Proibidas</h3>
                <p>É expressamente proibido:</p>
                <ul>
                    <li>Fornecer informações falsas ou enganosas</li>
                    <li>Utilizar a plataforma para atividades ilegais</li>
                    <li>Manipular preços ou criar anúncios enganosos</li>
                    <li>Discriminar outros usuários por qualquer motivo</li>
                    <li>Violar direitos de propriedade intelectual</li>
                    <li>Tentar acessar contas de outros usuários</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-lock"></i> 5. Contas e Segurança</h3>
                <p><strong>5.1 Senha:</strong> Você é responsável por manter a confidencialidade de sua senha e por todas as atividades que ocorram em sua conta.</p>
                <p><strong>5.2 Notificação:</strong> Notifique-nos imediatamente sobre qualquer uso não autorizado de sua conta ou qualquer outra violação de segurança.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-exclamation-triangle"></i> 6. Limitação de Responsabilidade</h3>
                <p>A plataforma Encontre o Campo não se responsabiliza por:</p>
                <ul>
                    <li>Qualidade, quantidade ou entrega dos produtos negociados</li>
                    <li>Descumprimento de acordos entre usuários</li>
                    <li>Danos ou prejuízos decorrentes de transações</li>
                    <li>Problemas técnicos temporários na plataforma</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-balance-scale"></i> 7. Propriedade Intelectual</h3>
                <p>Todo o conteúdo da plataforma, incluindo logos, textos, gráficos e software, é propriedade da Encontre o Campo ou de seus licenciadores e está protegido por leis de propriedade intelectual.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-times-circle"></i> 8. Suspensão e Cancelamento</h3>
                <p>Reservamo-nos o direito de suspender ou cancelar contas que:</p>
                <ul>
                    <li>Violarem estes Termos de Uso</li>
                    <li>Praticarem atividades fraudulentas</li>
                    <li>Causarem danos a outros usuários</li>
                    <li>Descumprirem leis aplicáveis</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-pencil-alt"></i> 9. Alterações nos Termos</h3>
                <p>Podemos modificar estes Termos de Uso a qualquer momento. As alterações entrarão em vigor após a publicação na plataforma. O uso continuado da plataforma após as alterações constitui aceitação dos novos termos.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-balance-scale"></i> 10. Lei Aplicável</h3>
                <p>Estes Termos são regidos pelas leis da República Federativa do Brasil. Qualquer disputa será resolvida no foro da comarca de Jundiaí/SP.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-envelope"></i> 11. Contato</h3>
                <p>Para questões sobre estes Termos de Uso, entre em contato conosco:</p>
                <ul>
                    <li><strong>Email:</strong> legal@encontreocampo.com.br</li>
                    <li><strong>Endereço:</strong> Jundiaí, São Paulo, Brasil</li>
                </ul>
            </div>

            <div class="acceptance-section">
                <div class="acceptance-box">
                    <i class="fas fa-check-circle"></i>
                    <h4>Aceitação dos Termos</h4>
                    <p>Ao usar nossa plataforma, você declara que leu, compreendeu e concordou com estes Termos de Uso.</p>
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
    });
    </script>
</body>
</html>