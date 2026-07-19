<?php
// src/privacidade.php
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
            <h2>Política de Privacidade</h2>
            <p>Última atualização: <?php echo date('d/m/Y'); ?></p>
        </div>

        <div class="legal-content">
            <div class="legal-section">
                <h3><i class="fas fa-shield-alt"></i> 1. Introdução e Controlador dos Dados</h3>
                <p>A Encontre o Campo valoriza sua privacidade e está comprometida em proteger seus dados pessoais. Esta política explica como coletamos, usamos e protegemos suas informações, em conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018).</p>
                <p>O controlador responsável pelo tratamento dos seus dados pessoais é:</p>
                <ul>
                    <li><strong>Razão Social:</strong> [PREENCHER RAZÃO SOCIAL] — CNPJ: [PREENCHER CNPJ QUANDO DISPONÍVEL]</li>
                    <li><strong>Endereço:</strong> Jundiaí, São Paulo, Brasil</li>
                </ul>
                <p><em>Nota interna: atualizar razão social e CNPJ assim que a empresa estiver formalmente constituída, antes de abrir a plataforma ao público.</em></p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-database"></i> 2. Dados que Coletamos</h3>
                <p><strong>2.1 Informações de Cadastro:</strong></p>
                <ul>
                    <li>Nome completo e email</li>
                    <li>CPF/CNPJ</li>
                    <li>Endereço e telefone</li>
                    <li>Fotos de documento de identidade e foto de rosto (para verificação de identidade de vendedores e compradores)</li>
                    <li>Informações comerciais (para vendedores)</li>
                    <li>Dados do veículo (para transportadores)</li>
                </ul>

                <p><strong>2.2 Dados de Uso:</strong></p>
                <ul>
                    <li>Histórico de transações</li>
                    <li>Propostas e negociações</li>
                    <li>Preferências e favoritos</li>
                    <li>Logs de acesso (endereço IP, data/hora e navegador utilizado)</li>
                </ul>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-cog"></i> 3. Como Usamos Seus Dados e Base Legal</h3>
                <p>Utilizamos suas informações para:</p>
                <ul>
                    <li>Fornecer e melhorar nossos serviços <em>(execução de contrato)</em></li>
                    <li>Facilitar transações entre usuários <em>(execução de contrato)</em></li>
                    <li>Verificar a identidade de vendedores e compradores <em>(execução de contrato e prevenção a fraudes)</em></li>
                    <li>Enviar notificações importantes <em>(execução de contrato)</em></li>
                    <li>Personalizar sua experiência <em>(legítimo interesse)</em></li>
                    <li>Cumprir obrigações legais e fiscais <em>(cumprimento de obrigação legal)</em></li>
                    <li>Prevenir fraudes e abusos <em>(legítimo interesse)</em></li>
                </ul>
                <p>Cada finalidade acima está associada a uma base legal prevista no Art. 7º da LGPD, conforme indicado entre parênteses.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-share-alt"></i> 4. Compartilhamento de Dados</h3>
                <p><strong>4.1 Compartilhamento Necessário:</strong> Seus dados são compartilhados apenas quando necessário para:</p>
                <ul>
                    <li>Concretizar transações (comprador ↔ vendedor ↔ transportador)</li>
                    <li>Cumprir ordens judiciais</li>
                    <li>Proteger nossos direitos legais</li>
                </ul>

                <p><strong>4.2 Parceiros de Serviço:</strong> Trabalhamos com os seguintes parceiros para operar a plataforma:</p>
                <ul>
                    <li><strong>Stripe:</strong> processamento de pagamentos de assinatura de vendedores. A plataforma não armazena dados de cartão de crédito — esses dados são processados diretamente pelo Stripe.</li>
                    <li><strong>Hostinger:</strong> hospedagem da plataforma e armazenamento do banco de dados.</li>
                </ul>
                <p>Todos os parceiros de serviço são contratualmente obrigados a proteger seus dados de acordo com padrões de segurança adequados.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-lock"></i> 5. Segurança dos Dados</h3>
                <p>Implementamos medidas de segurança técnicas e organizacionais para proteger seus dados:</p>
                <ul>
                    <li>Senhas armazenadas com hash criptográfico (nunca em texto simples)</li>
                    <li>Conexão HTTPS obrigatória em toda a plataforma</li>
                    <li>Controle de acesso restrito a documentos de identidade, disponível somente para administradores autenticados</li>
                    <li>Registro de tentativas de acesso e bloqueio automático em caso de tentativas suspeitas de login</li>
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
                <p>Dados de transações são mantidos por 5 anos para fins fiscais e legais. Logs de acesso são mantidos por até 6 meses, sendo excluídos automaticamente após esse período.</p>
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
                    <li><strong>Informação sobre compartilhamento:</strong> Saber com quem seus dados são compartilhados</li>
                </ul>
                <p>Para exercer qualquer um desses direitos, entre em contato pelo e-mail informado na seção 12.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-cookie"></i> 8. Cookies e Tecnologias Similares</h3>
                <p>Utilizamos cookies para:</p>
                <ul>
                    <li>Manter sua sessão ativa (essencial para o funcionamento da plataforma)</li>
                    <li>Lembrar suas preferências</li>
                    <li>Analisar o uso da plataforma</li>
                    <li>Melhorar nossa performance</li>
                </ul>
                <p>Você pode controlar cookies através das configurações do seu navegador. Note que desabilitar cookies essenciais pode impedir o funcionamento correto da plataforma (ex: manter você logado).</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-exchange-alt"></i> 9. Transferência Internacional</h3>
                <p>Seus dados são processados e armazenados primariamente no Brasil. No entanto, o processamento de pagamentos de assinatura é feito pelo Stripe, empresa com infraestrutura nos Estados Unidos, o que configura transferência internacional de dados (nome, e-mail e metadados da transação — nunca dados de cartão, que vão direto pro Stripe sem passar pelo nosso servidor).</p>
                <p>Essa transferência é feita com base em cláusulas contratuais e mecanismos de proteção do próprio Stripe, empresa certificada e em conformidade com padrões internacionais de proteção de dados (incluindo GDPR europeu, com exigências equivalentes ou superiores às da LGPD).</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-child"></i> 10. Proteção de Menores</h3>
                <p>Nossos serviços são destinados exclusivamente a maiores de 18 anos. O cadastro na plataforma não está disponível para menores de idade, mesmo com autorização de responsável legal. Não coletamos intencionalmente dados de menores de 18 anos. Se tomarmos conhecimento de cadastro feito por um menor, a conta será suspensa e os dados excluídos.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-triangle-exclamation"></i> 11. Notificação de Incidentes de Segurança</h3>
                <p>Em caso de incidente de segurança que possa acarretar risco ou dano relevante aos titulares dos dados, comunicaremos o fato à Autoridade Nacional de Proteção de Dados (ANPD) e aos titulares afetados, conforme exigido pelo Art. 48 da LGPD, informando a natureza dos dados afetados, as medidas técnicas adotadas e as recomendações ao titular.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-pencil-alt"></i> 12. Alterações nesta Política</h3>
                <p>Podemos atualizar esta política periodicamente. Notificaremos sobre mudanças significativas através de email ou aviso na plataforma. O uso continuado após alterações constitui aceitação da nova versão.</p>
            </div>

            <div class="legal-section">
                <h3><i class="fas fa-envelope"></i> 13. Contato do Encarregado</h3>
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
    </script>
</body>
</html>