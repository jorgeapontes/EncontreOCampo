<?php
// src/faq.php
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
    } elseif ($usuario_tipo == 'transportador') {
        $button_action = 'transportador/dashboard.php';
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
    <title>FAQ - Encontre Ocampo</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="css/faq.css">
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
                    <a href="../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                
                <!-- Menu Hamburguer (adicionado) -->
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
                
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link">Comprar</a>
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
            <h2>Perguntas Frequentes</h2>
            <p>Encontre respostas para as dúvidas mais comuns sobre nossa plataforma</p>
        </div>

        <div class="faq-container">
            <!-- Seção: Cadastro e Conta -->
            <div class="faq-section">
                <div class="section-header">
                    <i class="fas fa-user-circle"></i>
                    <h3>Cadastro e Conta</h3>
                </div>
                <div class="faq-items">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Como faço para me cadastrar na plataforma?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Para se cadastrar, vá até o formulário no final da página principal ou clique em "Login" no menu superior e depois em "Registre-se". Preencha os dados solicitados conforme seu perfil (comprador, vendedor ou transportador). Aguarde seu cadastro ser analisado e ativado.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Esqueci minha senha, o que fazer?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Na página de login, clique em "Esqueci minha senha". Você receberá um email com instruções para redefinir sua senha. Caso não receba, verifique sua pasta de spam ou entre em contato conosco.</p>
                        </div>
                    </div>

                    <!-- <div class="faq-item">
                        <div class="faq-question">
                            <h4>Posso ter mais de um tipo de conta?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Sim! Você pode ter múltiplos perfis usando o mesmo email. Por exemplo, pode ser vendedor e comprador ao mesmo tempo. Cada perfil terá seu próprio dashboard e funcionalidades específicas.</p>
                        </div>
                    </div> -->
                </div>
            </div>

            <!-- Seção: Compras e Propostas -->
            <div class="faq-section">
                <div class="section-header">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Compras e Propostas</h3>
                </div>
                <div class="faq-items">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Como faço uma proposta de compra?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Navegue pelos anúncios, clique em "Comprar" no produto desejado e use o botão "Fazer Proposta". Informe o preço desejado, quantidade e condições de pagamento/entrega. O vendedor será notificado e poderá aceitar, recusar ou fazer uma contraproposta.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Posso cancelar uma proposta enviada?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Sim, é possível cancelar uma proposta. Acesse "Minhas Propostas" no seu dashboard e clique em "Cancelar Proposta". Após o vendedor responder, não é mais possível cancelar.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Como funciona a negociação de preços?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Nossa plataforma permite negociação direta entre comprador e vendedor. Você faz uma proposta, o vendedor pode aceitar, recusar ou fazer uma contraproposta. Todo o histórico fica registrado para acompanhamento.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Vendas e Anúncios -->
            <div class="faq-section">
                <div class="section-header">
                    <i class="fas fa-store"></i>
                    <h3>Vendas e Anúncios</h3>
                </div>
                <div class="faq-items">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Como criar um anúncio de produto?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Acesse seu dashboard de vendedor, clique em "Criar Anúncio" e preencha todas as informações: nome do produto, descrição, preço, quantidade disponível, unidade de medida e fotos. Após salvar, o anúncio ficará visível para todos os compradores.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Posso editar um anúncio depois de publicado?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Sim! Acesse "Meus Anúncios" no dashboard, clique no anúncio desejado e depois em "Editar". Você pode alterar preço, quantidade, descrição e fotos. As alterações são refletidas imediatamente.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Como gerencio as propostas recebidas?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>No dashboard de vendedor, acesse "Propostas Recebidas". Você verá todas as propostas pendentes e poderá aceitar, recusar ou fazer contrapropostas.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Pagamentos e Entregas -->
            <div class="faq-section">
                <div class="section-header">
                    <i class="fas fa-truck"></i>
                    <h3>Pagamentos e Entregas</h3>
                </div>
                <div class="faq-items">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Quais formas de pagamento são aceitas?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>A forma de pagamento pode ser escolhida diretamente pelo comprador durante o checkout. Pix e cartões são as mais comuns.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Como funciona o transporte dos produtos?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>O transporte pode ser combinado diretamente entre as partes ou através de transportadores cadastrados em nossa plataforma. As condições de entrega são negociadas junto com o preço do produto.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Quem paga o frete?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Por padrão, o vendedor é responsável pelo frete, mas isso pode ser alterado durante a negociação. Pode ser pago pelo comprador, pelo vendedor ou rateado entre ambos. Esta informação deve ficar clara nas condições da proposta.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção: Segurança e Suporte -->
            <div class="faq-section">
                <div class="section-header">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Segurança e Suporte</h3>
                </div>
                <div class="faq-items">
                    <!-- <div class="faq-item">
                        <div class="faq-question">
                            <h4>Como a plataforma garante a segurança das transações?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Verificamos todos os usuários cadastrados, mantemos histórico das negociações e oferecemos um canal de comunicação seguro dentro da plataforma. Recomendamos que todo acordo seja documentado através do sistema.</p>
                        </div>
                    </div> -->

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>O que fazer em caso de problema com uma transação?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Entre em contato conosco imediatamente através do email suporte@encontreocampo.com.br. Nossa equipe mediará a situação e ajudará a encontrar a melhor solução para ambas as partes.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h4>Meus dados pessoais estão seguros?</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Sim! Seguimos a LGPD e implementamos medidas de segurança para proteger seus dados. Suas informações só são compartilhadas quando necessário para a conclusão das transações.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Ajuda Adicional -->
        <div class="help-section">
            <div class="help-content">
                <i class="fas fa-headset"></i>
                <h3>Não encontrou o que procurava?</h3>
                <p>Nossa equipe de suporte está pronta para ajudar você</p>
                <div class="help-actions">
                    <a href="mailto:suporte@encontreocampo.com.br" class="btn btn-primary">
                        <i class="fas fa-envelope"></i> Enviar Email
                    </a>
                    <a href="../index.php#contato" class="btn btn-secondary">
                        <i class="fas fa-phone"></i> Outros Contatos
                    </a>
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
        // Funcionalidade do Accordion FAQ
        const faqQuestions = document.querySelectorAll('.faq-question');
        
        faqQuestions.forEach(question => {
            question.addEventListener('click', function() {
                const faqItem = this.parentElement;
                const answer = this.nextElementSibling;
                const icon = this.querySelector('i');
                
                // Fechar outros itens abertos
                document.querySelectorAll('.faq-item.active').forEach(item => {
                    if (item !== faqItem) {
                        item.classList.remove('active');
                        item.querySelector('.faq-answer').style.maxHeight = null;
                        item.querySelector('.faq-question i').classList.remove('fa-chevron-up');
                        item.querySelector('.faq-question i').classList.add('fa-chevron-down');
                    }
                });
                
                // Alternar item atual
                faqItem.classList.toggle('active');
                
                if (faqItem.classList.contains('active')) {
                    answer.style.maxHeight = answer.scrollHeight + "px";
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    answer.style.maxHeight = null;
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            });
        });

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
        
        // Menu Hamburguer functionality (adicionado)
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        
        if (hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
            
            // Fechar menu ao clicar em um link
            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            }));
        }
    });
    </script>
</body>
</html>