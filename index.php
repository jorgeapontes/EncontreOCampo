<?php

require_once 'src/conexao.php';

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $button_text = 'Login';

    if (isset($_SESSION['usuario_nome'])) {
        $button_text = 'Olá, '.$_SESSION['usuario_nome'];
        $usuario_tipo = $_SESSION['usuario_tipo'];
        $button_action = 'src/'.$_SESSION['usuario_tipo'].'/dashboard.php';
        
    } else {
        $button_text = 'Login';
        $button_action = 'src/login.php';
    }

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encontre o Campo - E-commerce de Frutas</title>
    <link rel="stylesheet" href="index.css">
    <link rel="shortcut icon" href="img/logo-nova.png" type="image/x-icon">
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
                    <img src="../EncontreOCampo/img/logo-nova.png" alt="Logo">
                    <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="#comprar" class="nav-link">Comprar</a>
                    </li>
                    <li class="nav-item">
                        <a href="#vender" class="nav-link">Vender</a>
                    </li>
                    <li class="nav-item">
                        <a href="#transporte" class="nav-link">Transporte</a>
                    </li>
                    <li class="nav-item">
                        <a href="#contato" class="nav-link">Registre-se</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="src/notificacoes.php" class="nav-link no-underline">
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
                        <a href="<?= $button_action ?>" class="nav-link login-button no-underline"> <?= $button_text ?> </a>
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

    <section id="inicio" class="hero">
        <div class="hero-content">
            <h1>O Melhor Mercado de Frutas do Campo</h1>
            <p class="hero-text">Conectamos produtores e compradores com qualidade e agilidade, por todo o Brasil</p>
            <div class="hero-buttons">
                <a href="#comprar" id="btn-comprar" class="cta-button">Compre agora</a>
                <a href="#vender" id="btn-vender" class="cta-button.secondary">Venda conosco</a>
            </div>
        </div>
    </section>

    <section id="comprar" class="section bg-light">
    <div class="container">
        <h2 class="section-title">Compre Frutas</h2>
        
        <!-- Carrossel melhorado -->
        <div class="carousel-container">
            <div class="carousel-wrapper">
                <div class="carousel-track" id="anunciosCarousel">
                    <!-- Anúncios serão carregados aqui via JavaScript -->
                    <div class="loading-state">
                        <p>Carregando anúncios fresquinhos...</p>
                    </div>
                </div>
            </div>
            
            <!-- Controles simplificados -->
            <div class="carousel-nav">
                <button class="nav-btn prev" onclick="prevSlide()" aria-label="Anterior">
                    ‹
                </button>
                <div class="carousel-dots" id="carouselDots">
                    <!-- Pontos de navegação -->
                </div>
                <button class="nav-btn next" onclick="nextSlide()" aria-label="Próximo">
                    ›
                </button>
            </div>
        </div>

        <center>
            <a href="src/anuncios.php" class="cta-button" style="display: inline-block; margin-top: 40px; text-decoration: none; width: 250px; text-align: center;">
                Ver Todos os Anúncios
            </a>
        </center>
    </div>
</section>

    <section id="vender" class="section">
        <div class="container">
            <h2 class="section-title">Torne-se um Vendedor</h2>
            <div class="sell-content">
                <div class="sell-text">
                    <h3>Venda para compradores de todo o país</h3>
                    <p>Oferecemos uma plataforma segura para que produtores rurais possam vender suas frutas diretamente para comerciantes, atacadistas e consumidores finais.</p>
                    <ul class="benefits-list">
                        <li>Alcance nacional</li>
                        <li>Compra segura</li>
                        <li>Suporte ao produtor</li>
                    </ul>
                    <a href="#contato" class="cta-button">Inscreva-se como vendedor</a>
                </div>
                <div class="sell-image">
                    <div class="logo-large">
                        <img src="../EncontreOCampo/img/logo-nova.png" alt="Logo">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="transporte" class="section bg-light">
        <div class="container">
            <h2 class="section-title">Transporte</h2>
            <div class="transport-content">
                <div class="transport-image">
                    <img src="https://images.unsplash.com/photo-1500937386664-56d1dfef3854?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80" alt="Agricultor colhendo frutas">
                </div>
                <div class="transport-text">
                    <h3>Logística especializada para frutas</h3>
                    <p>Cadastre-se como transportador, escolha um destino e receba por isso!</p>
                    <div class="transport-features">
                        <div class="feature">
                            <h4>Transporte Seguro</h4>
                            <p>Apenas transportadores aprovados podem fazer entregas por nossa plataforma.</p>
                        </div>
                        <div class="feature">
                            <h4>Variedade e Qualidade</h4>
                            <p>As frutas mais exóticas às verduras mais tradicionais.</p>
                        </div>
                        <div class="feature">
                            <h4>Alcance</h4>
                            <p>Entregas para todo o país.</p>
                        </div>
                    </div>
                    <a href="#contato" class="cta-button">Inscreva-se</a>
                </div>
            </div>
        </div>
    </section>

  <section id="contato" class="section">
    <div class="container">
        <h2 class="section-title">Registre-se</h2>
        <div class="contact-content">
            <div class="contact-info">
                <h3>Fale Conosco</h3>
                <p>Estamos aqui para ajudar produtores e compradores a se conectarem.</p>
                <div class="contact-details">
                    <div class="contact-item">
                        <h4>Email</h4>
                        <p>contato@encontreocampo.com.br</p>
                    </div>
                    <div class="contact-item">
                        <h4>Telefone</h4>
                        <p>(11) 3456-7890</p>
                    </div>
                    <div class="contact-item">
                        <h4>Endereço</h4>
                        <p>Rua das Frutas, 123 - Centro, São Paulo - SP</p>
                    </div>
                </div>
            </div>
            <div class="contact-form">
                <form id="mainForm" action="src/processar_solicitacao.php" method="POST">
                    <div class="form-group">
                        <label for="name" class="required">Nome </label>
                        <input type="text" id="name" name="name" required placeholder="Seu nome completo">
                    </div>
                    <div class="form-group">
                        <label for="email" class="required">Email </label>
                        <input type="email" id="email" name="email" required placeholder="seu@email.com">
                    </div>
                    <div class="form-group">
                        <label for="senha" class="required">Senha </label>
                        <input type="password" id="senha" name="senha" required minlength="8" placeholder="Mínimo 8 caracteres">
                        <small class="form-help">Use pelo menos 8 caracteres com letras e números</small>
                    </div>
                    <div class="form-group">
                        <label for="confirma_senha" class="required">Confirme a Senha </label>
                        <input type="password" id="confirma_senha" name="confirma_senha" required placeholder="Digite a senha novamente">
                    </div>
                    <div class="form-group">
                        <label for="subject" class="required">Quero me tornar: </label>
                        <select id="subject" name="subject" onchange="toggleAdditionalFields()" required>
                            <option value="">Selecione...</option>
                            <option value="comprador">Comprador</option>
                            <option value="vendedor">Vendedor</option>
                            <option value="transportador">Transportador</option>
                        </select>
                    </div>

                    <!-- FORMULÁRIO DO COMPRADOR -->
                    <div id="compradorFields" style="display: none;">
                        <div class="multi-step-form">
                            <div class="progress-indicator">
                                <div class="progress-step active" data-step="1">1</div>
                                <div class="progress-step" data-step="2">2</div>
                                <div class="progress-step" data-step="3">3</div>
                            </div>

                            <!-- PASSO 1: Dados Pessoais -->
                            <div id="compradorStep1" class="step-content active">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Dados Pessoais</h4>
                                
                                <div class="form-group">
                                    <label class="required">Tipo de Pessoa:</label>
                                    <div class="radio-group">
                                        <label class="radio-label">
                                            <input type="radio" name="tipoPessoaComprador" value="cpf" required checked>
                                            <span class="radio-custom"></span>
                                            <span class="radio-text">CPF</span>
                                        </label>
                                        <label class="radio-label">
                                            <input type="radio" name="tipoPessoaComprador" value="cnpj" required>
                                            <span class="radio-custom"></span>
                                            <span class="radio-text">CNPJ</span>
                                        </label>
                                    </div>
                                    <small class="form-help">CPF selecionado por padrão. Clique em CNPJ se for uma empresa.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cpfCnpjComprador" class="required">CPF/CNPJ </label>
                                    <input type="text" id="cpfCnpjComprador" name="cpfCnpjComprador" required placeholder="000.000.000-00">
                                    <small class="form-help">Digite apenas números, a máscara será aplicada automaticamente</small>
                                </div>
                                
                                <div class="form-group" id="nomeComercialGroup">
                                    <label id="labelNomeComercialComprador" for="nomeComercialComprador" class="required">Nome de Exibição </label>
                                    <input type="text" id="nomeComercialComprador" name="nomeComercialComprador" required placeholder="Como você quer ser chamado na plataforma">
                                    <small class="form-help">Salvo na coluna nome_comercial do banco de dados</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cipComprador">CIP (opcional)</label>
                                    <input type="text" id="cipComprador" name="cipComprador" placeholder="Código de Identificação do Produtor">
                                    <small class="form-help">Apenas para produtores rurais cadastrados</small>
                                </div>
                                
                                <div class="step-navigation">
                                    <div></div>
                                    <button type="button" class="step-btn btn-next" onclick="nextStep('comprador')">
                                        Próximo →
                                    </button>
                                </div>
                            </div>

                            <!-- PASSO 2: Endereço -->
                            <div id="compradorStep2" class="step-content" style="display: none;">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Endereço</h4>
                                
                                <div class="form-group">
                                    <label for="cepComprador">CEP (opcional)</label>
                                    <div class="cep-container">
                                        <input type="text" id="cepComprador" name="cepComprador" maxlength="9" placeholder="00000-000">
                                        <button type="button" class="cep-btn" onclick="buscarCEPComprador()">Buscar CEP</button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ruaComprador" class="required">Rua </label>
                                    <input type="text" id="ruaComprador" name="ruaComprador" required placeholder="Nome da rua">
                                </div>
                                
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label for="numeroComprador" class="required">Número </label>
                                        <input type="text" id="numeroComprador" name="numeroComprador" required placeholder="Número">
                                    </div>
                                    <div class="form-group">
                                        <label for="complementoComprador">Complemento (opcional)</label>
                                        <input type="text" id="complementoComprador" name="complementoComprador" placeholder="Apto, Sala, etc.">
                                    </div>
                                </div>
                                
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label for="estadoComprador" class="required">Estado </label>
                                        <select id="estadoComprador" name="estadoComprador" required>
                                            <option value="">Selecione...</option>
                                            <option value="AC">Acre</option>
                                            <option value="AL">Alagoas</option>
                                            <option value="AP">Amapá</option>
                                            <option value="AM">Amazonas</option>
                                            <option value="BA">Bahia</option>
                                            <option value="CE">Ceará</option>
                                            <option value="DF">Distrito Federal</option>
                                            <option value="ES">Espírito Santo</option>
                                            <option value="GO">Goiás</option>
                                            <option value="MA">Maranhão</option>
                                            <option value="MT">Mato Grosso</option>
                                            <option value="MS">Mato Grosso do Sul</option>
                                            <option value="MG">Minas Gerais</option>
                                            <option value="PA">Pará</option>
                                            <option value="PB">Paraíba</option>
                                            <option value="PR">Paraná</option>
                                            <option value="PE">Pernambuco</option>
                                            <option value="PI">Piauí</option>
                                            <option value="RJ">Rio de Janeiro</option>
                                            <option value="RN">Rio Grande do Norte</option>
                                            <option value="RS">Rio Grande do Sul</option>
                                            <option value="RO">Rondônia</option>
                                            <option value="RR">Roraima</option>
                                            <option value="SC">Santa Catarina</option>
                                            <option value="SP">São Paulo</option>
                                            <option value="SE">Sergipe</option>
                                            <option value="TO">Tocantins</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="cidadeComprador" class="required">Cidade </label>
                                        <input type="text" id="cidadeComprador" name="cidadeComprador" required placeholder="Nome da cidade">
                                    </div>
                                </div>
                                
                                <div class="step-navigation">
                                    <button type="button" class="step-btn btn-prev" onclick="prevStep('comprador')">
                                        ← Voltar
                                    </button>
                                    <button type="button" class="step-btn btn-next" onclick="nextStep('comprador')">
                                        Próximo →
                                    </button>
                                </div>
                            </div>

                            <!-- PASSO 3: Contato e Plano -->
                            <div id="compradorStep3" class="step-content" style="display: none;">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Contato e Plano</h4>
                                
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label for="telefone1Comprador" class="required">Telefone/Celular </label>
                                        <input type="text" id="telefone1Comprador" name="telefone1Comprador" maxlength="15" required placeholder="(11) 99999-9999">
                                    </div>
                                    <div class="form-group">
                                        <label for="telefone2Comprador">Telefone/Celular (opcional)</label>
                                        <input type="text" id="telefone2Comprador" name="telefone2Comprador" maxlength="15" placeholder="(11) 99999-9999">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="planoComprador">Plano</label>
                                    <select id="planoComprador" name="planoComprador">
                                        <option value="free" selected>Grátis</option>
                                        <option value="basico" disabled>Básico (R$ 29,90/mês)</option>
                                        <option value="premium" disabled>Premium (R$ 79,90/mês)</option>
                                        <option value="empresarial" disabled>Empresarial (R$ 199,90/mês)</option>
                                    </select>
                                    <small class="form-help" style="color: #666; font-style: italic; display: block; margin-top: 5px;">
                                        * Todos começam com plano gratuito. Você poderá alterar o plano posteriormente em seu painel.
                                    </small>
                                </div>
                                
                                <div class="step-navigation">
                                    <button type="button" class="step-btn btn-prev" onclick="prevStep('comprador')">
                                        ← Voltar
                                    </button>
                                    <button type="button" class="step-btn btn-ajax-submit">
                                        Finalizar Cadastro
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FORMULÁRIO DO VENDEDOR -->
                    <div id="vendedorFields" style="display: none;">
                        <div class="multi-step-form">
                            <div class="progress-indicator">
                                <div class="progress-step active" data-step="1">1</div>
                                <div class="progress-step" data-step="2">2</div>
                                <div class="progress-step" data-step="3">3</div>
                            </div>

                            <!-- PASSO 1: Dados da Empresa -->
                            <div id="vendedorStep1" class="step-content active">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Dados da Empresa</h4>
                                
                                <div class="form-group">
                                    <label for="nomeComercialVendedor" class="required">Nome Comercial </label>
                                    <input type="text" id="nomeComercialVendedor" name="nomeComercialVendedor" required placeholder="Razão Social da empresa/fazenda">
                                    <small class="form-help">Salvo na coluna nome_comercial do banco de dados</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cpfCnpjVendedor" class="required">CNPJ </label>
                                    <input type="text" id="cpfCnpjVendedor" name="cpfCnpjVendedor" required placeholder="00.000.000/0000-00">
                                    <small class="form-help">Para vendedor, é obrigatório CNPJ (14 dígitos)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cipVendedor">CIP (opcional)</label>
                                    <input type="text" id="cipVendedor" name="cipVendedor" placeholder="Código de Identificação do Produtor">
                                </div>
                                
                                <div class="step-navigation">
                                    <div></div>
                                    <button type="button" class="step-btn btn-next" onclick="nextStep('vendedor')">
                                        Próximo →
                                    </button>
                                </div>
                            </div>

                            <!-- PASSO 2: Endereço -->
                            <div id="vendedorStep2" class="step-content" style="display: none;">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Endereço</h4>
                                
                                <div class="form-group">
                                    <label for="cepVendedor">CEP (opcional)</label>
                                    <div class="cep-container">
                                        <input type="text" id="cepVendedor" name="cepVendedor" maxlength="9" placeholder="00000-000">
                                        <button type="button" class="cep-btn" onclick="buscarCEPVendedor()">Buscar CEP</button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ruaVendedor" class="required">Rua </label>
                                    <input type="text" id="ruaVendedor" name="ruaVendedor" required placeholder="Nome da rua">
                                </div>
                                
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label for="numeroVendedor" class="required">Número </label>
                                        <input type="text" id="numeroVendedor" name="numeroVendedor" required placeholder="Número">
                                    </div>
                                    <div class="form-group">
                                        <label for="complementoVendedor">Complemento (opcional)</label>
                                        <input type="text" id="complementoVendedor" name="complementoVendedor" placeholder="Apto, Sala, etc.">
                                    </div>
                                </div>
                                
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label for="estadoVendedor" class="required">Estado </label>
                                        <select id="estadoVendedor" name="estadoVendedor" required>
                                            <option value="">Selecione...</option>
                                            <option value="AC">Acre</option>
                                            <option value="AL">Alagoas</option>
                                            <option value="AP">Amapá</option>
                                            <option value="AM">Amazonas</option>
                                            <option value="BA">Bahia</option>
                                            <option value="CE">Ceará</option>
                                            <option value="DF">Distrito Federal</option>
                                            <option value="ES">Espírito Santo</option>
                                            <option value="GO">Goiás</option>
                                            <option value="MA">Maranhão</option>
                                            <option value="MT">Mato Grosso</option>
                                            <option value="MS">Mato Grosso do Sul</option>
                                            <option value="MG">Minas Gerais</option>
                                            <option value="PA">Pará</option>
                                            <option value="PB">Paraíba</option>
                                            <option value="PR">Paraná</option>
                                            <option value="PE">Pernambuco</option>
                                            <option value="PI">Piauí</option>
                                            <option value="RJ">Rio de Janeiro</option>
                                            <option value="RN">Rio Grande do Norte</option>
                                            <option value="RS">Rio Grande do Sul</option>
                                            <option value="RO">Rondônia</option>
                                            <option value="RR">Roraima</option>
                                            <option value="SC">Santa Catarina</option>
                                            <option value="SP">São Paulo</option>
                                            <option value="SE">Sergipe</option>
                                            <option value="TO">Tocantins</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="cidadeVendedor" class="required">Cidade </label>
                                        <input type="text" id="cidadeVendedor" name="cidadeVendedor" required placeholder="Nome da cidade">
                                    </div>
                                </div>
                                
                                <div class="step-navigation">
                                    <button type="button" class="step-btn btn-prev" onclick="prevStep('vendedor')">
                                        ← Voltar
                                    </button>
                                    <button type="button" class="step-btn btn-next" onclick="nextStep('vendedor')">
                                        Próximo →
                                    </button>
                                </div>
                            </div>

                            <!-- PASSO 3: Contato e Plano -->
                            <div id="vendedorStep3" class="step-content" style="display: none;">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Contato e Plano</h4>
                                
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label for="telefone1Vendedor" class="required">Telefone/Celular </label>
                                        <input type="text" id="telefone1Vendedor" name="telefone1Vendedor" maxlength="15" required placeholder="(11) 99999-9999">
                                    </div>
                                    <div class="form-group">
                                        <label for="telefone2Vendedor">Telefone/Celular (opcional)</label>
                                        <input type="text" id="telefone2Vendedor" name="telefone2Vendedor" maxlength="15" placeholder="(11) 99999-9999">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="planoVendedor">Plano</label>
                                    <select id="planoVendedor" name="planoVendedor">
                                        <option value="free" selected>Grátis</option>
                                        <option value="basico" disabled>Básico (R$ 49,90/mês)</option>
                                        <option value="premium" disabled>Premium (R$ 99,90/mês)</option>
                                        <option value="empresarial" disabled>Empresarial (R$ 249,90/mês)</option>
                                    </select>
                                    <small class="form-help" style="color: #666; font-style: italic; display: block; margin-top: 5px;">
                                        * Todos começam com plano gratuito. Você poderá alterar o plano posteriormente em seu painel.
                                    </small>
                                </div>
                                
                                <div class="step-navigation">
                                    <button type="button" class="step-btn btn-prev" onclick="prevStep('vendedor')">
                                        ← Voltar
                                    </button>
                                    <button type="button" class="step-btn btn-ajax-submit">
                                        Finalizar Cadastro
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FORMULÁRIO DO TRANSPORTADOR -->
                    <div id="transportadorFields" style="display: none;">
                        <div class="multi-step-form">
                            <div class="progress-indicator">
                                <div class="progress-step active" data-step="1">1</div>
                                <div class="progress-step" data-step="2">2</div>
                                <div class="progress-step" data-step="3">3</div>
                            </div>

                            <!-- PASSO 1: Dados Pessoais -->
                            <div id="transportadorStep1" class="step-content active">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Dados Pessoais</h4>
                                
                                <div class="form-group">
                                    <label for="telefoneTransportador" class="required">Telefone/Celular </label>
                                    <input type="text" id="telefoneTransportador" name="telefoneTransportador" maxlength="15" required placeholder="(11) 99999-9999">
                                </div>
                                
                                <div class="form-group">
                                    <label for="numeroANTT" class="required">Número ANTT </label>
                                    <input type="text" id="numeroANTT" name="numeroANTT" required placeholder="Somente números - Registro na ANTT">
                                    <small class="form-help">Digite apenas números (ex: 12345678901234)</small>
                                </div>
                                
                                <div class="step-navigation">
                                    <div></div>
                                    <button type="button" class="step-btn btn-next" onclick="nextStep('transportador')">
                                        Próximo →
                                    </button>
                                </div>
                            </div>

                            <!-- PASSO 2: Dados do Veículo -->
                            <div id="transportadorStep2" class="step-content" style="display: none;">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Dados do Veículo</h4>
                                
                                <div class="form-group">
                                    <label for="placaVeiculo" class="required">Placa do Veículo </label>
                                    <div class="placa-container">
                                        <input type="text" id="placaVeiculo" name="placaVeiculo" required 
                                               placeholder="AAA-1234 ou AAA1B23" maxlength="8">
                                        <button type="button" class="placa-btn" onclick="buscarPlacaVeiculo()">Buscar Info</button>
                                    </div>
                                    <small class="form-help">Formato aceito: AAA-1234 (antigo) ou AAA1B23 (Mercosul)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="modeloVeiculo" class="required">Modelo do Veículo </label>
                                    <input type="text" id="modeloVeiculo" name="modeloVeiculo" required placeholder="Ex: Mercedes-Benz Actros 2020">
                                    <small class="form-help">Clique no botão "Buscar Info" acima para tentar preencher automaticamente</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="descricaoVeiculo" class="required">Descrição do Veículo </label>
                                    <textarea id="descricaoVeiculo" name="descricaoVeiculo" rows="3" required 
                                              placeholder="Ex: Caminhão baú refrigerado, capacidade 20 toneladas, 3 eixos"></textarea>
                                    <small class="form-help">Descreva as características do veículo para melhor identificação</small>
                                </div>
                                
                                <div class="step-navigation">
                                    <button type="button" class="step-btn btn-prev" onclick="prevStep('transportador')">
                                        ← Voltar
                                    </button>
                                    <button type="button" class="step-btn btn-next" onclick="nextStep('transportador')">
                                        Próximo →
                                    </button>
                                </div>
                            </div>

                            <!-- PASSO 3: Localização -->
                            <div id="transportadorStep3" class="step-content" style="display: none;">
                                <h4 style="margin-bottom: 20px; color: var(--dark-color);">Localização</h4>
                                
                                <div class="form-group">
                                    <label class="required">Selecione a cidade onde está instalado:</label>
                                </div>
                                
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label for="estadoTransportador" class="required">Estado </label>
                                        <select id="estadoTransportador" name="estadoTransportador" required>
                                            <option value="">Selecione o estado...</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="cidadeTransportador" class="required">Cidade </label>
                                        <select id="cidadeTransportador" name="cidadeTransportador" required>
                                            <option value="">Selecione a cidade...</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="step-navigation">
                                    <button type="button" class="step-btn btn-prev" onclick="prevStep('transportador')">
                                        ← Voltar
                                    </button>
                                    <button type="button" class="step-btn btn-ajax-submit">
                                        Finalizar Cadastro
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mensagem (opcional) -->
                    <div class="form-group" id="messageGroup" style="display: none; margin-top: 20px;">
                        <label for="message">Mensagem (opcional)</label>
                        <textarea id="message" name="message" rows="4" placeholder="Conte-nos mais sobre o que você precisa..."></textarea>
                    </div>
                    
                    <!-- Botão de envio genérico -->
                    <div class="end" style="margin-top: 30px;">
                        <button type="button" id="submitOther" class="cta-button" style="width: 100%; padding: 15px; font-size: 1.1em;">
                            Enviar Solicitação de Cadastro
                        </button>
                        <small class="form-help" style="text-align: center; display: block; margin-top: 10px; color: #666;">
                            * Campos obrigatórios
                        </small>
                    </div>
                </form>
            </div>
        </div>
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
                            <li><a href="#inicio">Início</a></li>
                            <li><a href="#comprar">Comprar</a></li>
                            <li><a href="#vender">Vender</a></li>
                            <li><a href="#transporte">Transporte</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h4>Suporte</h4>
                        <ul>
                            <li><a href="#contato">Contato</a></li>
                            <li><a href="src/faq.php">FAQ</a></li>
                            <li><a href="src/termos.php">Termos de Uso</a></li>
                            <li><a href="src/privacidade.php">Política de Privacidade</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h4>Redes Sociais</h4>
                        <ul>
                            <li><a href="#">Facebook</a></li>
                            <li><a href="#">Instagram</a></li>
                            <li><a href="#">LinkedIn</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
    
</body>
</html>