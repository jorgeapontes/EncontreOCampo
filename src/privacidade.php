<?php
// src/privacidade.php
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
    <title>Política de Privacidade - Encontre Ocampo</title>
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
            <h2>Política de Privacidade</h2>
            <p>Última atualização: <?php echo date('d/m/Y'); ?></p>
        </div>

        <div class="legal-content">
            <div class="legal-section">
                <h3><i class="fas fa-shield-alt"></i> 1. Introdução</h3>
                <p>A Encontre o Campo valoriza sua privacidade e está comprometida em proteger seus dados pessoais. Esta política explica como coletamos, usamos e protegemos suas informações.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-database"></i> 2. Dados que Coletamos</h3>
                <p><strong>2.1 Informações de Cadastro:</strong></p>
                <ul>
                    <li>Nome completo e email</li>
                    <li>CPF/CNPJ</li>
                    <li>Endereço e telefone</li>
                    <li>Informações comerciais (para vendedores)</li>
                    <li>Dados do veículo (para transportadores)</li>
                </ul>
                
                <p><strong>2.2 Dados de Uso:</strong></p>
                <ul>
                    <li>Histórico de transações</li>
                    <li>Propostas e negociações</li>
                    <li>Preferências e favoritos</li>
                    <li>Logs de acesso</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-cog"></i> 3. Como Usamos Seus Dados</h3>
                <p>Utilizamos suas informações para:</p>
                <ul>
                    <li>Fornecer e melhorar nossos serviços</li>
                    <li>Facilitar transações entre usuários</li>
                    <li>Enviar notificações importantes</li>
                    <li>Personalizar sua experiência</li>
                    <li>Cumprir obrigações legais</li>
                    <li>Prevenir fraudes e abusos</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-share-alt"></i> 4. Compartilhamento de Dados</h3>
                <p><strong>4.1 Compartilhamento Necessário:</strong> Seus dados são compartilhados apenas quando necessário para:</p>
                <ul>
                    <li>Concretizar transações (comprador ↔ vendedor)</li>
                    <li>Cumprir ordens judiciais</li>
                    <li>Proteger nossos direitos legais</li>
                </ul>
                
                <p><strong>4.2 Parceiros de Serviço:</strong> Trabalhamos com provedores que nos ajudam a operar a plataforma, sempre com contratos de proteção de dados.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-lock"></i> 5. Segurança dos Dados</h3>
                <p>Implementamos medidas de segurança técnicas e organizacionais para proteger seus dados:</p>
                <ul>
                    <li>Criptografia de dados sensíveis</li>
                    <li>Controle de acesso restrito</li>
                    <li>Monitoramento de segurança contínuo</li>
                    <li>Backups regulares</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-calendar"></i> 6. Retenção de Dados</h3>
                <p>Mantemos seus dados pessoais apenas pelo tempo necessário para:</p>
                <ul>
                    <li>Cumprir finalidades descritas nesta política</li>
                    <li>Atender obrigações legais</li>
                    <li>Resolver disputas</li>
                    <li>Executar acordos</li>
                </ul>
                <p>Dados de transações são mantidos por 5 anos para fins fiscais e legais.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-user-cog"></i> 7. Seus Direitos</h3>
                <p>De acordo com a LGPD, você tem direito a:</p>
                <ul>
                    <li><strong>Acesso:</strong> Saber que dados temos sobre você</li>
                    <li><strong>Correção:</strong> Retificar dados incompletos ou desatualizados</li>
                    <li><strong>Exclusão:</strong> Solicitar a eliminação de seus dados</li>
                    <li><strong>Portabilidade:</strong> Receber seus dados em formato estruturado</li>
                    <li><strong>Revogação:</strong> Retirar consentimento a qualquer momento</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-cookie"></i> 8. Cookies e Tecnologias Similares</h3>
                <p>Utilizamos cookies para:</p>
                <ul>
                    <li>Manter sua sessão ativa</li>
                    <li>Lembrar suas preferências</li>
                    <li>Analisar o uso da plataforma</li>
                    <li>Melhorar nossa performance</li>
                </ul>
                <p>Você pode controlar cookies através das configurações do seu navegador.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-exchange-alt"></i> 9. Transferência Internacional</h3>
                <p>Seus dados são processados e armazenados no Brasil. Em caso de transferência internacional, garantiremos a adequada proteção conforme exigido pela LGPD.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-child"></i> 10. Proteção de Menores</h3>
                <p>Nossos serviços não são direcionados a menores de 18 anos. Não coletamos intencionalmente dados de menores. Se tomarmos conhecimento de tal coleta, excluiremos essas informações.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-pencil-alt"></i> 11. Alterações nesta Política</h3>
                <p>Podemos atualizar esta política periodicamente. Notificaremos sobre mudanças significativas através de email ou aviso na plataforma. O uso continuado após alterações constitui aceitação da nova versão.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-envelope"></i> 12. Contato do Encarregado</h3>
                <p>Para exercer seus direitos ou dúvidas sobre privacidade, entre em contato com nosso Encarregado de Dados:</p>
                <ul>
                    <li><strong>Email:</strong> privacidade@encontreocampo.com.br</li>
                    <li><strong>Endereço:</strong> Jundiaí, São Paulo, Brasil</li>
                    <li><strong>Prazo de Resposta:</strong> Até 15 dias úteis</li>
                </ul>
            </div>

            <div class="acceptance-section">
                <div class="acceptance-box">
                    <i class="fas fa-user-shield"></i>
                    <h4>Transparência e Confiança</h4>
                    <p>Estamos comprometidos em proteger sua privacidade e ser transparentes sobre como usamos seus dados.</p>
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