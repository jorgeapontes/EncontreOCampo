<?php

    session_start();

    $button_text = 'Não está funcionando';

    if (isset($_SESSION['usuario_nome'])) {
        $button_text = 'Olá, '.$_SESSION['usuario_nome'];
        $usuario_tipo = $_SESSION['usuario_tipo'];

        if ($usuario_tipo == 'admin') {
            $button_action = 'src/admin/dashboard.php';
        } else {
            $button_action = 'src/'.$_SESSION['usuario_tipo'].'/perfil';
        }
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
    <link rel="shortcut icon" href="img/Logo - Copia.jpg" type="image/x-icon">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
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
            <h1>O melhor mercado de frutas do campo</h1>
            <p class="hero-text">Conectamos produtores e compradores com qualidade e agilidade</p>
            <a href="#comprar" class="cta-button">Compre agora</a>
            <a href="#vender" class="cta-button secondary">Venda conosco</a>
        </div>
    </section>

    <section id="comprar" class="section bg-light">
        <div class="container">
            <h2 class="section-title">Compre Frutas Frescas</h2>
            <div class="products-grid">
                <div class="product-card">
                    <div class="product-image" style="background-image: url('https://images.unsplash.com/photo-1610832958506-aa56368176cf?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="product-info">
                        <h3>Maçãs Vermelhas</h3>
                        <p>Frescas direto do pomar</p>
                        <span class="price">R$ 4,50/kg</span>
                        <button class="buy-btn">Ver</button>
                    </div>
                </div>
                <div class="product-card">
                    <div class="product-image" style="background-image: url('https://images.unsplash.com/photo-1550258987-190a2d41a8ba?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="product-info">
                        <h3>Laranjas Doces</h3>
                        <p>Colhidas no ponto certo</p>
                        <span class="price">R$ 3,20/kg</span>
                        <button class="buy-btn">Ver</button>
                    </div>
                </div>
                <div class="product-card">
                    <div class="product-image" style="background-image: url('https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1160&q=80');"></div>
                    <div class="product-info">
                        <h3>Bananas Prata</h3>
                        <p>Maduras e saborosas</p>
                        <span class="price">R$ 2,80/kg</span>
                        <button class="buy-btn">Ver</button>
                    </div>
                </div>
                <div class="product-card">
                    <div class="product-image" style="background-image: url('https://images.unsplash.com/photo-1560769684-55015cee73a8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80');"></div>
                    <div class="product-info">
                        <h3>Uvas Verdes</h3>
                        <p>Dulces e sem sementes</p>
                        <span class="price">R$ 8,90/kg</span>
                        <button class="buy-btn">Ver</button>
                    </div>
                </div>
            </div>
            <center>
            <a a href="anuncios.php" class="cta-button" style="display: inline-block; margin-top: 40px; text-decoration: none; width: 250px; text-align: center;">Acessar plataforma</a>
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
                        <img src="img/Logo - Copia.jpg" alt="Logo">
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
                            <h4>Entrega </h4>
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
                            <label for="name" class="required">Nome</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="required">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
    <label for="senha" class="required">Senha</label>
    <input type="password" id="senha" name="senha" required minlength="8" placeholder="Mínimo 8 caracteres">
</div>
<div class="form-group">
    <label for="confirma_senha" class="required">Confirme a Senha</label>
    <input type="password" id="confirma_senha" name="confirma_senha" required>
</div>
                        <div class="form-group">
                            <label for="subject" class="required">Quero me tornar:</label>
                            <select id="subject" name="subject" onchange="toggleAdditionalFields()" required>
                                <option value="">Selecione...</option>
                                <option value="comprador">Comprador</option>
                                <option value="vendedor">Vendedor</option>
                                <option value="transportador">Transportador</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>

                        <div id="compradorFields" style="display: none;">
                            <div class="multi-step-form">
                                <div class="progress-indicator">
                                    <div class="progress-step active" data-step="1">1</div>
                                    <div class="progress-step" data-step="2">2</div>
                                    <div class="progress-step" data-step="3">3</div>
                                </div>

                                <div id="compradorStep1" class="step-content active">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Dados Pessoais</h4>
                                    
                                    <div class="form-group">
                                        <label for="nomeComercialComprador">Nome Comercial</label>
                                        <input type="text" id="nomeComercialComprador" name="nomeComercialComprador">
                                    </div>
                                    <div class="form-group">
                                        <label for="cpfCnpjComprador" class="required">CPF/CNPJ</label>
                                        <input type="text" id="cpfCnpjComprador" name="cpfCnpjComprador" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cipComprador">CIP</label>
                                        <input type="text" id="cipComprador" name="cipComprador">
                                    </div>
                                    
                                    <div class="step-navigation">
                                        <div></div>
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('comprador')">
                                            Próximo →
                                        </button>
                                    </div>
                                </div>

                                <div id="compradorStep2" class="step-content">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Endereço</h4>
                                    
                                    <div class="form-group">
                                        <label for="cepComprador">CEP</label>
                                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                                            <input type="text" id="cepComprador" name="cepComprador" maxlength="9" placeholder="00000-000" style="flex: 1;">
                                            <button type="button" onclick="buscarCEPComprador()">Buscar CEP</button>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="ruaComprador" class="required">Rua</label>
                                        <input type="text" id="ruaComprador" name="ruaComprador" required>
                                    </div>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="numeroComprador" class="required">Número</label>
                                            <input type="text" id="numeroComprador" name="numeroComprador" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="complementoComprador">Complemento</label>
                                            <input type="text" id="complementoComprador" name="complementoComprador">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="estadoComprador" class="required">Estado</label>
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
                                            <label for="cidadeComprador" class="required">Cidade</label>
                                            <input type="text" id="cidadeComprador" name="cidadeComprador" required>
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

                                <div id="compradorStep3" class="step-content">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Contato e Plano</h4>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="telefone1Comprador" class="required">Telefone/Celular</label>
                                            <input type="text" id="telefone1Comprador" name="telefone1Comprador" maxlength="15" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="telefone2Comprador">Telefone/Celular (opcional)</label>
                                            <input type="text" id="telefone2Comprador" name="telefone2Comprador" maxlength="15">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="planoComprador">Plano</label>
                                        <select id="planoComprador" name="planoComprador">
                                            <option value="">Selecione...</option>
                                            <option value="basico">Básico</option>
                                            <option value="premium">Premium</option>
                                            <option value="empresarial">Empresarial</option>
                                        </select>
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

                        <div id="vendedorFields" style="display: none;">
                            <div class="multi-step-form">
                                <div class="progress-indicator">
                                    <div class="progress-step active" data-step="1">1</div>
                                    <div class="progress-step" data-step="2">2</div>
                                    <div class="progress-step" data-step="3">3</div>
                                </div>

                                <div id="vendedorStep1" class="step-content active">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Dados Pessoais</h4>
                                    
                                    <div class="form-group">
                                        <label for="nomeComercialVendedor">Nome Comercial</label>
                                        <input type="text" id="nomeComercialVendedor" name="nomeComercialVendedor">
                                    </div>
                                    <div class="form-group">
                                        <label for="cpfCnpjVendedor" class="required">CPF/CNPJ</label>
                                        <input type="text" id="cpfCnpjVendedor" name="cpfCnpjVendedor" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cipVendedor">CIP</label>
                                        <input type="text" id="cipVendedor" name="cipVendedor">
                                    </div>
                                    
                                    <div class="step-navigation">
                                        <div></div>
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('vendedor')">
                                            Próximo →
                                        </button>
                                    </div>
                                </div>

                                <div id="vendedorStep2" class="step-content">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Endereço</h4>
                                    
                                    <div class="form-group">
                                        <label for="cepVendedor">CEP</label>
                                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                                            <input type="text" id="cepVendedor" name="cepVendedor" maxlength="9" placeholder="00000-000" style="flex: 1;">
                                            <button type="button" onclick="buscarCEPVendedor()">Buscar CEP</button>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="ruaVendedor" class="required">Rua</label>
                                        <input type="text" id="ruaVendedor" name="ruaVendedor" required>
                                    </div>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="numeroVendedor" class="required">Número</label>
                                            <input type="text" id="numeroVendedor" name="numeroVendedor" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="complementoVendedor">Complemento</label>
                                            <input type="text" id="complementoVendedor" name="complementoVendedor">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="estadoVendedor" class="required">Estado</label>
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
                                            <label for="cidadeVendedor" class="required">Cidade</label>
                                            <input type="text" id="cidadeVendedor" name="cidadeVendedor" required>
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

                                <div id="vendedorStep3" class="step-content">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Contato e Plano</h4>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="telefone1Vendedor" class="required">Telefone/Celular</label>
                                            <input type="text" id="telefone1Vendedor" name="telefone1Vendedor" maxlength="15" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="telefone2Vendedor">Telefone/Celular (opcional)</label>
                                            <input type="text" id="telefone2Vendedor" name="telefone2Vendedor" maxlength="15">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="planoVendedor">Plano</label>
                                        <select id="planoVendedor" name="planoVendedor">
                                            <option value="">Selecione...</option>
                                            <option value="basico">Básico</option>
                                            <option value="premium">Premium</option>
                                            <option value="empresarial">Empresarial</option>
                                        </select>
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

                        <div id="transportadorFields" style="display: none;">
                            <div class="multi-step-form">
                                <div class="progress-indicator">
                                    <div class="progress-step active" data-step="1">1</div>
                                    <div class="progress-step" data-step="2">2</div>
                                    <div class="progress-step" data-step="3">3</div>
                                </div>

                                <div id="transportadorStep1" class="step-content active">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Dados Pessoais</h4>
                                    
                                    <div class="form-group">
                                        <label for="telefoneTransportador" class="required">Telefone/Celular</label>
                                        <input type="text" id="telefoneTransportador" name="telefoneTransportador" maxlength="15" required>
                                    </div>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="ANTT">ANTT</label>
                                            <input type="text" id="ANTT" name="ANTT">
                                        </div>
                                        <div class="form-group">
                                            <label for="numeroANTT" class="required">Número ANTT</label>
                                            <input type="text" id="numeroANTT" name="numeroANTT" required>
                                        </div>
                                    </div>
                                    
                                    <div class="step-navigation">
                                        <div></div>
                                        <button type="button" class="step-btn btn-next" onclick="nextStep('transportador')">
                                            Próximo →
                                        </button>
                                    </div>
                                </div>

                                <div id="transportadorStep2" class="step-content">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Dados do Veículo</h4>
                                    
                                    <div class="form-group">
                                        <label for="placaVeiculo" class="required">Placa do Veículo</label>
                                        <input type="text" id="placaVeiculo" name="placaVeiculo" required 
                                               placeholder="AAA-0A00" maxlength="8">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="modeloVeiculo" class="required">Modelo do Veículo</label>
                                        <input type="text" id="modeloVeiculo" name="modeloVeiculo" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="descricaoVeiculo" class="required">Descrição do Veículo</label>
                                        <textarea id="descricaoVeiculo" name="descricaoVeiculo" rows="3" required 
                                                  placeholder="Ex: Caminhão baú, Carreta, etc."></textarea>
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

                                <div id="transportadorStep3" class="step-content">
                                    <h4 style="margin-bottom: 20px; color: var(--dark-color);">Localização</h4>
                                    
                                    <div class="form-group">
                                        <label class="required">Selecione a cidade onde está instalado:</label>
                                    </div>
                                    
                                    <div class="form-group-row">
                                        <div class="form-group">
                                            <label for="estadoTransportador" class="required">Estado</label>
                                            <select id="estadoTransportador" name="estadoTransportador" required>
                                                <option value="">Selecione o estado...</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="cidadeTransportador" class="required">Cidade</label>
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

                        <div class="form-group" id="messageGroup">
                            <label for="message" class="required">Mensagem</label>
                            <textarea id="message" name="message" rows="5" required></textarea>
                        </div>

                        <button type="button" id="submitOther" class="cta-button">Enviar solicitação</button>
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
                            <li><a href="#">FAQ</a></li>
                            <li><a href="#">Termos de Uso</a></li>
                            <li><a href="#">Política de Privacidade</a></li>
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
                <p>&copy; 2025 Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <div id="loginModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 400px; border-radius: 10px;">
            <span class="close" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h2 style="color: var(--dark-color); margin-bottom: 20px;">Login</h2>
            
            <?php if (isset($_SESSION['erro_login'])): ?>
                <div style="background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <?php echo $_SESSION['erro_login']; unset($_SESSION['erro_login']); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email" class="required">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="senha" class="required">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <button type="submit" class="cta-button" style="width: 100%;">Entrar</button>
            </form>
        </div>
    </div>
    
    <script src="script.js"></script>
    
    </body>
</html>