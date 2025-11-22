<?php
// src/comprador/favoritos.php
session_start();
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

// Buscar produtos favoritos
$favoritos = [];
try {
    $sql = "SELECT p.*, f.data_criacao as data_favorito 
            FROM favoritos f 
            JOIN produtos p ON f.produto_id = p.id 
            WHERE f.usuario_id = :usuario_id AND p.status = 'ativo'
            ORDER BY f.data_criacao DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro = "Erro ao carregar favoritos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Favoritos - Encontre o Campo</title>
    <link rel="stylesheet" href="../../index.css">
    <link rel="stylesheet" href="../css/comprador/favoritos.css">
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
                <li class="nav-item"><a href="favoritos.php" class="nav-link active">Favoritos</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
            </ul>
        </div>
    </nav>

    <main class="container favoritos-container">
        <div class="page-header">
            <h1><i class="fas fa-heart"></i> Meus Favoritos</h1>
            <p class="page-subtitle">Produtos que você salvou para ver depois</p>
        </div>

        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($favoritos)): ?>
            <div class="empty-state">
                <i class="fas fa-heart-broken"></i>
                <h2>Nenhum favorito ainda</h2>
                <p>Você ainda não adicionou nenhum produto aos favoritos.</p>
                <a href="../anuncios.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i>
                    Explorar Anúncios
                </a>
            </div>
        <?php else: ?>
            <div class="favoritos-grid">
                <?php foreach ($favoritos as $produto): ?>
                    <div class="favorito-card">
                        <div class="favorito-image">
                            <img src="<?php echo $produto['imagem_url'] ? htmlspecialchars($produto['imagem_url']) : '../../img/placeholder.png'; ?>" 
                                 alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                            <button class="btn-remover-favorito" 
                                    data-produto-id="<?php echo $produto['id']; ?>"
                                    title="Remover dos favoritos">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="favorito-info">
                            <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                            <div class="favorito-preco">
                                R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                            </div>
                            <div class="favorito-estoque">
                                <i class="fas fa-box"></i>
                                <?php echo htmlspecialchars($produto['estoque']); ?> disponíveis
                            </div>
                            <div class="favorito-actions">
                                <a href="proposta_nova.php?anuncio_id=<?php echo $produto['id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-handshake"></i>
                                    Comprar
                                </a>
                                <a href="proposta_nova.php?anuncio_id=<?php echo $produto['id']; ?>" 
                                   class="btn btn-secondary">
                                    <i class="fas fa-eye"></i>
                                    Ver Detalhes
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

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
                        <li><a href="../ajuda.php">Central de Ajuda</a></li>
                        <li><a href="../contato.php">Fale Conosco</a></li>
                        <li><a href="../sobre.php">Sobre Nós</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="../termos.php">Termos de Uso</a></li>
                        <li><a href="../privacidade.php">Política de Privacidade</a></li>
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
        // Remover dos favoritos
        document.querySelectorAll('.btn-remover-favorito').forEach(btn => {
            btn.addEventListener('click', function() {
                const produtoId = this.getAttribute('data-produto-id');
                const card = this.closest('.favorito-card');
                
                fetch('favoritar_produto.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `produto_id=${produtoId}&acao=remover`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            // Recarregar a página se não houver mais favoritos
                            if (document.querySelectorAll('.favorito-card').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        alert('Erro ao remover dos favoritos: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao remover dos favoritos');
                });
            });
        });
    </script>
</body>
</html>