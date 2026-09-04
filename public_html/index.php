<?php

require_once 'src/conexao.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CORREÇÃO XSS: Sanitizar saída de dados do usuário
// ============================================================
function safe($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

$button_text = 'Login';
$button_action = 'src/login';

if (isset($_SESSION['usuario_nome'])) {
    // CORREÇÃO: Escapar o nome do usuário para evitar XSS
    $button_text = 'Olá, ' . safe($_SESSION['usuario_nome']);
    $usuario_tipo = isset($_SESSION['usuario_tipo']) ? safe($_SESSION['usuario_tipo']) : '';
    $button_action = 'src/' . $usuario_tipo . '/dashboard.php';
} else {
    $button_text = 'Login';
    $button_action = 'src/login';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encontre o Campo</title>
    <link rel="stylesheet" href="index.css">
    <link rel="shortcut icon" href="img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <img src="img/logo-nova.png" alt="Logo">
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
                        <a href="src/notificacoes" class="nav-link no-underline">
                            <span class="icon-wrapper">
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
                                        echo '<span class="notificacao-badge">' . safe($total_nao_lidas) . '</span>';
                                    }
                                }
                                ?>
                            </span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <!-- CORREÇÃO XSS: Escapar URLs e textos -->
                        <a href="<?= safe($button_action) ?>" class="nav-link login-button no-underline"> <?= safe($button_text) ?> </a>
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
            <h1>O Melhor Mercado do Campo</h1>
            <p class="hero-text">Conectamos produtores e compradores com qualidade e agilidade, por todo o Brasil</p>
            <div class="hero-buttons">
                <a href="#comprar" id="btn-comprar" class="cta-button">Compre agora</a>
                <a href="#vender" id="btn-comprar" class="cta-button">Venda conosco</a>
            </div>
        </div>
    </section>

    <section id="comprar" class="section bg-light">
        <div class="container">
            <h2 class="section-title">Anúncios</h2>
            
            <!-- Carrossel -->
            <div class="carousel-container">
                <div class="carousel-wrapper">
                    <div class="carousel-track" id="anunciosCarousel">
                        <div class="loading-state">
                            <p>Carregando anúncios fresquinhos...</p>
                        </div>
                    </div>
                </div>
                
                <div class="carousel-nav">
                    <button class="nav-btn prev" onclick="prevSlide()" aria-label="Anterior">‹</button>
                    <div class="carousel-dots" id="carouselDots"></div>
                    <button class="nav-btn next" onclick="nextSlide()" aria-label="Próximo">›</button>
                </div>
            </div>

            <center>
                <a href="src/anuncios" class="cta-button cta-anuncios-link">
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
                        <img src="img/logo-nova.png" alt="Logo" loading="lazy" decoding="async">
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
                    <img src="https://images.unsplash.com/photo-1500937386664-56d1dfef3854?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80" alt="Agricultor colhendo frutas" loading="lazy" decoding="async">
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
                    </div>
                </div>
                <div class="contact-form">
                    <form id="mainForm" action="src/processar_solicitacao" method="POST" enctype="multipart/form-data">
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

                        <!-- Checkbox de Termos -->
                        <div class="form-group form-group-termos">
                            <label class="label-aceite-termos">
                                <input type="checkbox" id="aceite_termos" name="aceite_termos" value="1" required class="checkbox-termos">
                                <span>Li e aceito os <a href="src/termos" target="_blank" rel="noopener noreferrer" class="link-termos">termos e condições</a> e a <a href="src/privacidade" target="_blank" rel="noopener noreferrer" class="link-termos">política de privacidade</a></span>
                            </label>
                            <small class="form-help">Você precisa aceitar os termos para continuar</small>
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
                        <div id="compradorFields" class="hidden">
                            <div class="multi-step-form">
                                <div class="progress-indicator">
                                    <div class="progress-step active" data-step="1">1</div>
                                    <div class="progress-step" data-step="2">2</div>
                                    <div class="progress-step" data-step="3">3</div>
                                </div>

                                <!-- PASSO 1 -->
                                <div id="compradorStep1" class="step-content active">
                                    <h4 class="step-heading">Dados Pessoais</h4>
                                    
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
                                        <small class="form-help">Digite apenas números para CPF ou letras e números para CNPJ; a pontuação será aplicada automaticamente</small>
                                    </div>
                                    
                                    <div class="form-group" id="nomeComercialGroup">
                                        <label id="labelNomeComercialComprador" for="nomeComercialComprador" class="required">Nome de Exibição </label>
                                        <input type="text" id="nomeComercialComprador" name="nomeComercialComprador" required placeholder="Como você quer ser chamado na plataforma">
                                        <small class="form-help">Esse nome será exibido para os outros usuários na plataforma.</small>
                                    </div>
                                    
                                    <div class="step-navigation">
                                        <div></div>
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('comprador')">
                                            Próximo →
                                        </button>
                                    </div>
                                </div>

                                <!-- PASSO 2 -->
                                <div id="compradorStep2" class="step-content">
                                    <h4 class="step-heading">Endereço</h4>
                                    
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
                                        <button type="button" class="step-btn btn-prev" onclick="prevStep('comprador')">← Voltar</button>
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('comprador')">Próximo →</button>
                                    </div>
                                </div>

                                <!-- PASSO 3 -->
                                <div id="compradorStep3" class="step-content">
                                    <h4 class="step-heading">Contato e Plano</h4>
                                    
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

                                    <h4 class="step-heading-doc">Documentação</h4>
                                    <p class="doc-instrucoes">Para validar sua identidade, envie as fotos abaixo:</p>
                                    
                                    <div class="form-group">
                                        <label for="fotoRostoComprador" class="required">Foto Facial</label>
                                        <!-- CORREÇÃO: Validação frontend de arquivo -->
                                        <input type="file" id="fotoRostoComprador" name="fotoRostoComprador" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Foto Facial')">
                                        <div class="file-error" id="fotoRostoComprador-error"></div>
                                        <small class="form-help">Envie uma foto clara do seu rosto. Formatos: JPG, PNG, WebP. Máx: 10MB</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fotoDocumentoFrenteComprador" class="required">Documento - Frente </label>
                                        <input type="file" id="fotoDocumentoFrenteComprador" name="fotoDocumentoFrenteComprador" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Documento Frente')">
                                        <div class="file-error" id="fotoDocumentoFrenteComprador-error"></div>
                                        <small class="form-help">Envie uma foto clara da frente do seu documento (RG, CNH ou Passaporte)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fotoDocumentoVersoComprador" class="required">Documento - Verso </label>
                                        <input type="file" id="fotoDocumentoVersoComprador" name="fotoDocumentoVersoComprador" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Documento Verso')">
                                        <div class="file-error" id="fotoDocumentoVersoComprador-error"></div>
                                        <small class="form-help">Envie uma foto clara do verso do seu documento</small>
                                    </div>
                                    
                                    <div class="step-navigation">
                                        <button type="button" class="step-btn btn-prev" onclick="prevStep('comprador')">← Voltar</button>
                                        <button type="button" class="step-btn btn-ajax-submit">Finalizar Cadastro</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FORMULÁRIO DO VENDEDOR -->
                        <div id="vendedorFields" class="hidden">
                            <div class="multi-step-form">
                                <div class="progress-indicator">
                                    <div class="progress-step active" data-step="1">1</div>
                                    <div class="progress-step" data-step="2">2</div>
                                    <div class="progress-step" data-step="3">3</div>
                                </div>

                                <div id="vendedorStep1" class="step-content active">
                                    <h4 class="step-heading">Dados da Empresa</h4>
                                    
                                    <div class="form-group">
                                        <label for="nomeComercialVendedor" class="required">Nome Comercial </label>
                                        <input type="text" id="nomeComercialVendedor" name="nomeComercialVendedor" required placeholder="Nome da empresa/fazenda">
                                        <small class="form-help">Esse será seu nome de exibição</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cpfCnpjVendedor" class="required">CNPJ </label>
                                        <input type="text" id="cpfCnpjVendedor" name="cpfCnpjVendedor" required placeholder="00.000.000/0000-00">
                                        <small class="form-help">Para vendedor, é obrigatório CNPJ (14 caracteres)</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cipVendedor">CIP (opcional)</label>
                                        <input type="text" id="cipVendedor" name="cipVendedor" placeholder="Código de Identificação do Produtor">
                                    </div>
                                    
                                    <div class="step-navigation">
                                        <div></div>
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('vendedor')">Próximo →</button>
                                    </div>
                                </div>

                                <div id="vendedorStep2" class="step-content">
                                    <h4 class="step-heading">Endereço</h4>
                                    
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
                                        <button type="button" class="step-btn btn-prev" onclick="prevStep('vendedor')">← Voltar</button>
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('vendedor')">Próximo →</button>
                                    </div>
                                </div>

                                <div id="vendedorStep3" class="step-content">
                                    <h4 class="step-heading">Contato e Plano</h4>
                                    
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
                                            <option value="free" selected>Plano 1 - Grátis</option>
                                        </select>
                                        <small class="form-help form-help-italic">
                                            * Todos começam com plano gratuito. Você poderá alterar o plano posteriormente em seu painel.
                                        </small>
                                    </div>

                                    <h4 class="step-heading-doc">Documentação</h4>
                                    <p class="doc-instrucoes">Para validar sua identidade, envie as fotos abaixo:</p>
                                    
                                    <div class="form-group">
                                        <label for="fotoRostoVendedor" class="required">Foto do Rosto </label>
                                        <input type="file" id="fotoRostoVendedor" name="fotoRostoVendedor" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Foto do Rosto')">
                                        <div class="file-error" id="fotoRostoVendedor-error"></div>
                                        <small class="form-help">Envie uma foto clara do seu rosto. Formatos: JPG, PNG, WebP. Máx: 10MB</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fotoDocumentoFrenteVendedor" class="required">Documento - Frente </label>
                                        <input type="file" id="fotoDocumentoFrenteVendedor" name="fotoDocumentoFrenteVendedor" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Documento Frente')">
                                        <div class="file-error" id="fotoDocumentoFrenteVendedor-error"></div>
                                        <small class="form-help">Envie uma foto clara da frente do seu documento (RG, CNH ou Passaporte)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fotoDocumentoVersoVendedor" class="required">Documento - Verso </label>
                                        <input type="file" id="fotoDocumentoVersoVendedor" name="fotoDocumentoVersoVendedor" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Documento Verso')">
                                        <div class="file-error" id="fotoDocumentoVersoVendedor-error"></div>
                                        <small class="form-help">Envie uma foto clara do verso do seu documento</small>
                                    </div>
                                    
                                    <div class="step-navigation">
                                        <button type="button" class="step-btn btn-prev" onclick="prevStep('vendedor')">← Voltar</button>
                                        <button type="button" class="step-btn btn-ajax-submit">Finalizar Cadastro</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FORMULÁRIO DO TRANSPORTADOR -->
                        <div id="transportadorFields" class="hidden">
                            <div class="multi-step-form">
                                <div class="progress-indicator">
                                    <div class="progress-step active" data-step="1">1</div>
                                    <div class="progress-step" data-step="2">2</div>
                                    <div class="progress-step" data-step="3">3</div>
                                </div>

                                <div id="transportadorStep1" class="step-content active">
                                    <h4 class="step-heading">Dados Pessoais</h4>
                                    
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
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('transportador')">Próximo →</button>
                                    </div>
                                </div>

                                <div id="transportadorStep2" class="step-content">
                                    <h4 class="step-heading">Dados do Veículo</h4>
                                    
                                    <div class="form-group">
                                        <label for="placaVeiculo" class="required">Placa do Veículo </label>
                                        <div class="placa-container">
                                            <input type="text" id="placaVeiculo" name="placaVeiculo" required placeholder="AAA-1234 ou AAA1B23" maxlength="8">
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
                                        <textarea id="descricaoVeiculo" name="descricaoVeiculo" rows="3" required placeholder="Ex: Caminhão baú refrigerado, capacidade 20 toneladas, 3 eixos"></textarea>
                                        <small class="form-help">Descreva as características do veículo para melhor identificação</small>
                                    </div>
                                    
                                    <div class="step-navigation">
                                        <button type="button" class="step-btn btn-prev" onclick="prevStep('transportador')">← Voltar</button>
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('transportador')">Próximo →</button>
                                    </div>
                                </div>

                                <div id="transportadorStep3" class="step-content">
                                    <h4 class="step-heading">Endereço</h4>
                                    
                                    <div class="form-group">
                                        <label for="cepTransportador">CEP (opcional)</label>
                                        <div class="cep-container">
                                            <input type="text" id="cepTransportador" name="cepTransportador" maxlength="9" placeholder="00000-000">
                                            <button type="button" class="cep-btn" onclick="buscarCEPTransportador()">Buscar CEP</button>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="ruaTransportador" class="required">Rua </label>
                                        <input type="text" id="ruaTransportador" name="ruaTransportador" required placeholder="Nome da rua">
                                    </div>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="numeroTransportador" class="required">Número </label>
                                            <input type="text" id="numeroTransportador" name="numeroTransportador" required placeholder="Número">
                                        </div>
                                        <div class="form-group">
                                            <label for="complementoTransportador">Complemento (opcional)</label>
                                            <input type="text" id="complementoTransportador" name="complementoTransportador" placeholder="Apto, Sala, etc.">
                                        </div>
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
                                            <input type="text" id="cidadeTransportador" name="cidadeTransportador" required placeholder="Nome da cidade">
                                        </div>
                                    </div>

                                    <h4 class="step-heading-doc">Documentação</h4>
                                    <p class="doc-instrucoes">Para validar sua identidade, envie as fotos abaixo:</p>
                                    
                                    <div class="form-group">
                                        <label for="fotoRostoTransportador" class="required">Foto do Rosto </label>
                                        <input type="file" id="fotoRostoTransportador" name="fotoRostoTransportador" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Foto do Rosto')">
                                        <div class="file-error" id="fotoRostoTransportador-error"></div>
                                        <small class="form-help">Envie uma foto clara do seu rosto. Formatos: JPG, PNG, WebP. Máx: 10MB</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fotoDocumentoFrenteTransportador" class="required">Documento - Frente </label>
                                        <input type="file" id="fotoDocumentoFrenteTransportador" name="fotoDocumentoFrenteTransportador" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Documento Frente')">
                                        <div class="file-error" id="fotoDocumentoFrenteTransportador-error"></div>
                                        <small class="form-help">Envie uma foto clara da frente do seu documento (RG, CNH ou Passaporte)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fotoDocumentoVersoTransportador" class="required">Documento - Verso </label>
                                        <input type="file" id="fotoDocumentoVersoTransportador" name="fotoDocumentoVersoTransportador" accept="image/jpeg,image/png,image/webp" required onchange="validarArquivo(this, 'Documento Verso')">
                                        <div class="file-error" id="fotoDocumentoVersoTransportador-error"></div>
                                        <small class="form-help">Envie uma foto clara do verso do seu documento</small>
                                    </div>

                                    <div class="step-navigation">
                                        <button type="button" class="step-btn btn-prev" onclick="prevStep('transportador')">← Voltar</button>
                                        <button type="button" class="step-btn btn-ajax-submit">Finalizar Cadastro</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mensagem (opcional) -->
                        <div class="form-group hidden message-group-box" id="messageGroup">
                            <label for="message">Mensagem (opcional)</label>
                            <textarea id="message" name="message" rows="4" placeholder="Conte-nos mais sobre o que você precisa..."></textarea>
                        </div>
                        
                        <!-- Botão de envio genérico -->
                        <div class="end">
                            <button type="button" id="submitOther" class="cta-button btn-submit-other">
                                Enviar Solicitação de Cadastro
                            </button>
                            <small class="form-help form-help-center">
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
                            <li><a href="src/anuncios">Anúncios</a>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h4>Suporte</h4>
                        <ul>
                            <li><a href="#contato">Contato</a></li>
                            <li><a href="src/faq">FAQ</a></li>
                            <li><a href="src/sobre">Sobre Nós</a></li>
                            <li><a href="src/termos">Termos de Uso</a></li>
                            <li><a href="src/privacidade">Política de Privacidade</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h4>Redes Sociais</h4>
                        <ul>
                            <p><i class="fas fa-envelope"></i> contato@encontreocampo.com.br</p>
                            <li><a href="#">Instagram</a></li>
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
    
    <!-- ============================================================
    CORREÇÃO: Validação Frontend de Arquivos
    ============================================================ -->
    <script>
    function validarArquivo(input, nomeCampo) {
        const errorDiv = document.getElementById(input.id + '-error');
        const maxSize = 10 * 1024 * 1024; // 10MB
        const tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
        
        // Limpar erro anterior
        errorDiv.classList.remove('show');
        errorDiv.textContent = '';
        input.classList.remove('file-input-error');
        
        // Verificar se há arquivo selecionado
        if (input.files.length === 0) {
            return;
        }
        
        const file = input.files[0];
        let erro = '';
        
        // Verificar tipo de arquivo
        if (!tiposPermitidos.includes(file.type)) {
            erro = '❌ Tipo de arquivo não permitido para "' + nomeCampo + '". Use JPG, PNG ou WebP.';
        }
        
        // Verificar tamanho
        if (!erro && file.size > maxSize) {
            erro = '❌ Arquivo muito grande para "' + nomeCampo + '". Máximo permitido: 10MB.';
        }
        
        // Verificar dimensões (opcional - apenas para imagens)
        if (!erro) {
            const img = new Image();
            img.onload = function() {
                if (this.width < 100 || this.height < 100) {
                    erro = '❌ Imagem muito pequena para "' + nomeCampo + '". Mínimo: 100x100 pixels.';
                    mostrarErro();
                }
                if (this.width > 4096 || this.height > 4096) {
                    erro = '❌ Imagem muito grande para "' + nomeCampo + '". Máximo: 4096x4096 pixels.';
                    mostrarErro();
                }
                if (this.width / this.height < 0.3 || this.width / this.height > 3.0) {
                    erro = '❌ Proporção da imagem inválida para "' + nomeCampo + '". A imagem está muito distorcida.';
                    mostrarErro();
                }
            };
            img.src = URL.createObjectURL(file);
        }
        
        function mostrarErro() {
            if (erro) {
                errorDiv.textContent = erro;
                errorDiv.classList.add('show');
                input.classList.add('file-input-error');
                input.value = ''; // Limpar o input
            }
        }
        
        // Mostrar erro imediatamente para validações síncronas
        if (erro) {
            mostrarErro();
        }
    }
    
    // Adicionar validação para todos os inputs file
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input[type="file"]').forEach(function(input) {
            // Garantir que o atributo accept esteja correto
            if (!input.getAttribute('accept')) {
                input.setAttribute('accept', 'image/jpeg,image/png,image/webp');
            }
        });
    });
    </script>
    
</body>
</html>