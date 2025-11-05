<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encontre Ocampo - E-commerce de Frutas</title>
    <link rel="stylesheet" href="index.css">
    <link rel="shortcut icon" href="img/Logo - Copia.jpg" type="image/x-icon">
</head>
<body>
    <!-- Header/Navbar -->
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <h1>ENCONTRE</h1>
                    <h2>OCAMPO</h2>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="#inicio" class="nav-link">Início</a>
                    </li>
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
                        <a href="#contato" class="nav-link">Contato</a>
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

    <!-- Seção Hero/Banner -->
    <section id="inicio" class="hero">
        <div class="hero-content">
            <h1>O melhor mercado de frutas do campo</h1>
            <p>Conectamos produtores e compradores com qualidade e agilidade</p>
            <a href="#comprar" class="cta-button">Compre agora</a>
            <a href="#vender" class="cta-button secondary">Venda conosco</a>
        </div>
    </section>

    <!-- Seção Comprar -->
    <section id="comprar" class="section">
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
            <button id="accesbtn">Acessar plataforma</button>
        </div>
    </section>

    <!-- Seção Vender -->
    <section id="vender" class="section bg-light">
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
                    <img src="https://images.unsplash.com/photo-1500937386664-56d1dfef3854?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1170&q=80" alt="Agricultor colhendo frutas">
                </div>
            </div>
        </div>
    </section>

    <!-- Seção Transporte -->
    <section id="transporte" class="section">
        <div class="container">
            <h2 class="section-title">Transporte</h2>
            <div class="transport-content">
                <div class="transport-image">
                    <img src="https://images.unsplash.com/photo-1542838132-92c53300491e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1074&q=80" alt="Caminhão de transporte de frutas">
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
                            <h4>Rastreamento</h4>
                            <p>Acompanhe sua carga em tempo real</p>
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

    <!-- Seção Contato -->
    <section id="contato" class="section bg-light">
        <div class="container">
            <h2 class="section-title">Entre em Contato</h2>
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
                    <form>
                        <div class="form-group">
                            <label for="name">Nome</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="subject">Quero me tornar:</label>
                            <select id="subject" name="subject">
                                <option value="compra">Vendedor</option>
                                <option value="venda">Transportador</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="message">Mensagem</label>
                            <textarea id="message" name="message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="cta-button">Enviar Mensagem</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <h2>ENCONTRE</h2>
                    <h3>OCAMPO</h3>
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
                <p>&copy; 2025 Encontre Ocampo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>