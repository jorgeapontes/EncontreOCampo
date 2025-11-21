<?php
// src/comprador/proposta_nova.php (Layout Melhorado com Favoritos e Footer - CORRIGIDO)

session_start();
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

// 2. OBTENÇÃO DO ID DO ANÚNCIO
if (!isset($_GET['anuncio_id']) || !is_numeric($_GET['anuncio_id'])) {
    header("Location: dashboard.php?erro=" . urlencode("Anúncio não especificado ou inválido."));
    exit();
}

$anuncio_id = (int)$_GET['anuncio_id'];
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();
$anuncio = null;

// 3. BUSCA DOS DETALHES DO ANÚNCIO
try {
    $sql = "SELECT 
                p.id, 
                p.nome AS produto, 
                p.descricao,
                p.estoque AS quantidade_disponivel, 
                p.preco, 
                p.unidade_medida,
                p.imagem_url, 
                v.id AS vendedor_sistema_id, 
                u.id AS vendedor_usuario_id, 
                u.nome AS nome_vendedor
            FROM produtos p
            JOIN vendedores v ON p.vendedor_id = v.id 
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE p.id = :anuncio_id AND p.status = 'ativo'";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt->execute();
    $anuncio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anuncio) {
        header("Location: dashboard.php?erro=" . urlencode("Anúncio não encontrado ou inativo."));
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao carregar anúncio: " . $e->getMessage()); 
}

// Verificar se o produto já está nos favoritos do usuário
$is_favorito = false;
try {
    $sql_favorito = "SELECT id FROM favoritos WHERE usuario_id = :usuario_id AND produto_id = :produto_id";
    $stmt_favorito = $conn->prepare($sql_favorito);
    $stmt_favorito->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_favorito->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_favorito->execute();
    $is_favorito = $stmt_favorito->rowCount() > 0;
} catch (PDOException $e) {
    // Se a tabela não existir, ignora o erro
}

$preco_formatado = 'R$ ' . number_format($anuncio['preco'], 2, ',', '.');
$unidade = htmlspecialchars($anuncio['unidade_medida']);
$imagePath = $anuncio['imagem_url'] ? htmlspecialchars($anuncio['imagem_url']) : '../../img/placeholder.png';
?>

<!-- O RESTANTE DO HTML PERMANECE IGUAL -->

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anuncio['produto']); ?> - Encontre o Campo</title>
    <link rel="stylesheet" href="../../index.css">
    <link rel="stylesheet" href="../css/comprador/proposta_nova.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <img src="../../img/logo-nova.png" alt="Logo">
                <div>
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="../anuncios.php" class="nav-link">Ver Anúncios</a></li>
                <li class="nav-item"><a href="minhas_propostas.php" class="nav-link">Minhas Propostas</a></li>
                <li class="nav-item"><a href="favoritos.php" class="nav-link">Favoritos</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
            </ul>
        </div>
    </nav>

    <main class="container produto-container">
        <div class="produto-content">
            <!-- Seção de Imagem do Produto -->
            <div class="produto-imagem">
                <div class="imagem-principal">
                    <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($anuncio['produto']); ?>">
                </div>
                
                <!-- Botão de Favoritar -->
                <button class="btn-favoritar <?php echo $is_favorito ? 'favoritado' : ''; ?>" 
                        id="btn-favoritar" 
                        data-produto-id="<?php echo $anuncio_id; ?>">
                    <i class="<?php echo $is_favorito ? 'fas' : 'far'; ?> fa-heart"></i>
                    <span><?php echo $is_favorito ? 'Favoritado' : 'Favoritar'; ?></span>
                </button>
            </div>

            <!-- Seção de Informações do Produto -->
            <div class="produto-info">
                <div class="info-header">
                    <h1><?php echo htmlspecialchars($anuncio['produto']); ?></h1>
                    <div class="vendedor-info">
                        <span class="vendedor-label">Vendido por:</span>
                        <a href="../perfil_vendedor.php?id=<?php echo $anuncio['vendedor_usuario_id']; ?>" class="vendedor-nome">
                            <?php echo htmlspecialchars($anuncio['nome_vendedor']); ?>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>

                <div class="preco-section">
                    <span class="preco-atual"><?php echo $preco_formatado; ?></span>
                    <span class="unidade">por <?php echo $unidade; ?></span>
                </div>

                <div class="estoque-info">
                    <i class="fas fa-box"></i>
                    <span><?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?> disponíveis</span>
                </div>

                <!-- Formulário de Compra -->
                <div class="compra-section">
                    <div class="quantidade-selector">
                        <label for="quantidade">Quantidade:</label>
                        <div class="quantidade-control">
                            <button type="button" class="qty-btn" id="decrease-qty">-</button>
                            <input type="number" id="quantidade" name="quantidade" value="1" min="1" max="<?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?>">
                            <button type="button" class="qty-btn" id="increase-qty">+</button>
                        </div>
                        <span class="unidade-info"><?php echo $unidade; ?></span>
                    </div>

                    <div class="botoes-compra">
                        <button type="button" class="btn-comprar" id="btn-comprar">
                            <i class="fas fa-shopping-cart"></i>
                            Comprar Agora
                        </button>
                        
                        <div class="proposta-option">
                            <p class="proposta-text">Algo não te agradou?</p>
                            <button type="button" class="btn-proposta" id="btn-fazer-proposta">
                                <i class="fas fa-handshake"></i>
                                Fazer Proposta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Descrição do Produto (Abaixo da seção principal) -->
        <?php if ($anuncio['descricao']): ?>
        <div class="descricao-completa">
            <div class="descricao-header">
                <h3><i class="fas fa-file-alt"></i> Descrição do Produto</h3>
            </div>
            <div class="descricao-content">
                <p><?php echo nl2br(htmlspecialchars($anuncio['descricao'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulário de Proposta (Inicialmente Oculto) -->
        <div class="proposta-section" id="proposta-section">
            <div class="proposta-header">
                <h2><i class="fas fa-handshake"></i> Fazer Proposta</h2>
                <p>Negocie diretamente com o vendedor</p>
            </div>

            <form action="processar_proposta.php" method="POST" class="proposta-form">
                <input type="hidden" name="produto_id" value="<?php echo $anuncio_id; ?>">
                <input type="hidden" name="comprador_usuario_id" value="<?php echo $usuario_id; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="preco_proposto">
                            <i class="fas fa-tag"></i>
                            Seu Preço Proposto
                            <span class="unit">(por <?php echo $unidade; ?>)</span>
                        </label>
                        <div class="input-with-symbol">
                            <span class="currency-symbol">R$</span>
                            <input type="number" id="preco_proposto" name="preco_proposto" 
                                   step="0.01" min="0.01" required
                                   value="<?php echo htmlspecialchars($anuncio['preco']); ?>"
                                   placeholder="0.00">
                        </div>
                        <small>Digite o valor que você deseja pagar por unidade</small>
                    </div>

                    <div class="form-group">
                        <label for="quantidade_proposta">
                            <i class="fas fa-box"></i>
                            Quantidade Desejada
                            <span class="unit">(em <?php echo $unidade; ?>)</span>
                        </label>
                        <input type="number" id="quantidade_proposta" name="quantidade_proposta" 
                               min="1" max="<?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?>" 
                               required value="1">
                        <small>Máximo disponível: <?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?></small>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="condicoes">
                        <i class="fas fa-file-alt"></i>
                        Condições de Pagamento/Entrega
                        <span class="optional">(Opcional)</span>
                    </label>
                    <textarea id="condicoes" name="condicoes" rows="4" 
                              placeholder="Adicione aqui detalhes para a negociação..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Proposta
                    </button>
                    <button type="button" class="btn btn-secondary" id="btn-cancelar-proposta">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Encontre o Campo</h4>
                    <ul>
                        <li><a href="../../index.php">Página Inicial</a></li>
                        <li><a href="../anuncios.php">Ver Anúncios</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="favoritos.php">Meus Favoritos</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="../../ajuda.php">Central de Ajuda</a></li>
                        <li><a href="../../contato.php">Fale Conosco</a></li>
                        <li><a href="../../sobre.php">Sobre Nós</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="../../termos.php">Termos de Uso</a></li>
                        <li><a href="../../privacidade.php">Política de Privacidade</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contato</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> contato@encontreocampo.com.br</p>
                        <p><i class="fas fa-phone"></i> (11) 99999-9999</p>
                        <div class="social-links">
                            <a href="#"><i class="fab fa-facebook"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        // Controles de quantidade
        const quantidadeInput = document.getElementById('quantidade');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');
        const btnFazerProposta = document.getElementById('btn-fazer-proposta');
        const btnCancelarProposta = document.getElementById('btn-cancelar-proposta');
        const propostaSection = document.getElementById('proposta-section');
        const quantidadeProposta = document.getElementById('quantidade_proposta');
        const btnFavoritar = document.getElementById('btn-favoritar');

        // Inicialmente ocultar a seção de proposta
        propostaSection.style.display = 'none';

        // Controles de quantidade
        decreaseBtn.addEventListener('click', () => {
            if (quantidadeInput.value > 1) {
                quantidadeInput.value = parseInt(quantidadeInput.value) - 1;
                if (quantidadeProposta) quantidadeProposta.value = quantidadeInput.value;
            }
        });

        increaseBtn.addEventListener('click', () => {
            const max = parseInt(quantidadeInput.max);
            if (quantidadeInput.value < max) {
                quantidadeInput.value = parseInt(quantidadeInput.value) + 1;
                if (quantidadeProposta) quantidadeProposta.value = quantidadeInput.value;
            }
        });

        quantidadeInput.addEventListener('change', () => {
            let value = parseInt(quantidadeInput.value);
            const max = parseInt(quantidadeInput.max);
            const min = parseInt(quantidadeInput.min);
            
            if (value < min) value = min;
            if (value > max) value = max;
            
            quantidadeInput.value = value;
            if (quantidadeProposta) quantidadeProposta.value = value;
        });

        // Toggle do formulário de proposta
        let propostaAberta = false;

        btnFazerProposta.addEventListener('click', () => {
            if (!propostaAberta) {
                // Abrir proposta
                propostaSection.style.display = 'block';
                propostaSection.classList.add('show');
                btnFazerProposta.innerHTML = '<i class="fas fa-times"></i>Fechar Proposta';
                btnFazerProposta.classList.add('active');
                propostaSection.scrollIntoView({ behavior: 'smooth' });
                propostaAberta = true;
            } else {
                // Fechar proposta
                propostaSection.classList.remove('show');
                setTimeout(() => {
                    propostaSection.style.display = 'none';
                }, 300);
                btnFazerProposta.innerHTML = '<i class="fas fa-handshake"></i>Fazer Proposta';
                btnFazerProposta.classList.remove('active');
                propostaAberta = false;
            }
        });

        btnCancelarProposta.addEventListener('click', () => {
            propostaSection.classList.remove('show');
            setTimeout(() => {
                propostaSection.style.display = 'none';
            }, 300);
            btnFazerProposta.innerHTML = '<i class="fas fa-handshake"></i>Fazer Proposta';
            btnFazerProposta.classList.remove('active');
            propostaAberta = false;
        });

        // Sincronizar quantidade do formulário principal com o da proposta
        if (quantidadeProposta) {
            quantidadeInput.addEventListener('change', () => {
                quantidadeProposta.value = quantidadeInput.value;
            });
        }

        // Formatação do preço
        const precoInput = document.getElementById('preco_proposto');
        if (precoInput) {
            precoInput.addEventListener('blur', function() {
                this.value = parseFloat(this.value).toFixed(2);
            });
        }

        // Efeito de pulso no botão de proposta
        btnFazerProposta.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.02)';
        });

        btnFazerProposta.addEventListener('mouseleave', function() {
            if (!propostaAberta) {
                this.style.transform = 'translateY(0) scale(1)';
            }
        });

        // Funcionalidade de Favoritar
        if (btnFavoritar) {
            btnFavoritar.addEventListener('click', function() {
                const produtoId = this.getAttribute('data-produto-id');
                const isCurrentlyFavorito = this.classList.contains('favoritado');
                
                // Alternar estado visual
                if (isCurrentlyFavorito) {
                    // Remover dos favoritos
                    this.classList.remove('favoritado');
                    this.querySelector('i').classList.remove('fas');
                    this.querySelector('i').classList.add('far');
                    this.querySelector('span').textContent = 'Favoritar';
                } else {
                    // Adicionar aos favoritos
                    this.classList.add('favoritado');
                    this.querySelector('i').classList.remove('far');
                    this.querySelector('i').classList.add('fas');
                    this.querySelector('span').textContent = 'Favoritado';
                }
                
                // Enviar requisição AJAX para salvar/remover favorito
                fetch('favoritar_produto.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `produto_id=${produtoId}&acao=${isCurrentlyFavorito ? 'remover' : 'adicionar'}`
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        // Reverter visualmente em caso de erro
                        if (isCurrentlyFavorito) {
                            this.classList.add('favoritado');
                            this.querySelector('i').classList.add('fas');
                            this.querySelector('i').classList.remove('far');
                            this.querySelector('span').textContent = 'Favoritado';
                        } else {
                            this.classList.remove('favoritado');
                            this.querySelector('i').classList.add('far');
                            this.querySelector('i').classList.remove('fas');
                            this.querySelector('span').textContent = 'Favoritar';
                        }
                        alert('Erro ao atualizar favoritos: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    // Reverter visualmente em caso de erro
                    if (isCurrentlyFavorito) {
                        this.classList.add('favoritado');
                        this.querySelector('i').classList.add('fas');
                        this.querySelector('i').classList.remove('far');
                        this.querySelector('span').textContent = 'Favoritado';
                    } else {
                        this.classList.remove('favoritado');
                        this.querySelector('i').classList.add('far');
                        this.querySelector('i').classList.remove('fas');
                        this.querySelector('span').textContent = 'Favoritar';
                    }
                });
            });
        }
    </script>
</body>
</html>