<?php
// perfil_vendedor.php
session_start();
require_once 'conexao.php';

// Verificar se o vendedor_id foi passado
if (!isset($_GET['vendedor_id'])) {
    header('Location: anuncios.php');
    exit();
}

$vendedor_id = $_GET['vendedor_id'];

// Variáveis de sessão
$is_logged_in = isset($_SESSION['usuario_id']);
$usuario_tipo = $_SESSION['usuario_tipo'] ?? null;
$is_comprador = $usuario_tipo === 'comprador';

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

// Conexão e busca dos dados do vendedor
$database = new Database();
$conn = $database->getConnection();

$vendedor_info = [];
$anuncios_vendedor = [];
$total_anuncios = 0;

try {
    // Buscar informações do vendedor (CORRIGIDO)
    $sql_vendedor = "SELECT u.nome AS nome_vendedor, v.cidade, v.estado, v.nome_comercial, v.foto_perfil_url 
                     FROM usuarios u 
                     JOIN vendedores v ON u.id = v.usuario_id 
                     WHERE u.id = ? AND u.status = 'ativo'";
    
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->execute([$vendedor_id]);
    $vendedor_info = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor_info) {
        die("Vendedor não encontrado ou inativo.");
    }

    // Buscar anúncios do vendedor
    $sql_anuncios = "SELECT p.id, p.nome AS produto, p.preco, p.estoque AS quantidade_disponivel, 
                            p.unidade_medida, p.descricao, p.imagem_url 
                     FROM produtos p 
                     WHERE p.vendedor_id IN (SELECT id FROM vendedores WHERE usuario_id = ?) 
                     AND p.status = 'ativo'";
    
    $stmt_anuncios = $conn->prepare($sql_anuncios);
    $stmt_anuncios->execute([$vendedor_id]);
    $anuncios_vendedor = $stmt_anuncios->fetchAll(PDO::FETCH_ASSOC);
    
    $total_anuncios = count($anuncios_vendedor);

} catch (PDOException $e) {
    die("Erro ao carregar informações do vendedor: " . $e->getMessage());
}

$foto_perfil_url = $vendedor_info['foto_perfil_url'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil do Vendedor - Encontre Ocampo</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="css/anuncios.css">
    <link rel="stylesheet" href="css/vendedor/perfil.css">
    <link rel="shortcut icon" href="../img/Logo - Copia.jpg" type="image/x-icon">
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
                        <a href="anuncios.php" class="nav-link">Anúncios</a>
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
            <h2>Perfil do Vendedor</h2>
        </div>

        <div class="section-perfil">
            <div class="forms-area">
                <!-- Informações do Vendedor -->
                <div class="foto-perfil-display">
                    <center>
                        <?php if (!empty($foto_perfil_url)): 
                            $foto_path = $foto_perfil_url;
                            if (strpos($foto_path, '../') === 0) {
                                $foto_path = substr($foto_path, 3);
                            }
                        ?>
                            <img id="profile-img-preview" 
                                src="<?php echo htmlspecialchars($foto_path); ?>" 
                                alt="Foto de Perfil"
                                onerror="this.style.display='none'; document.getElementById('default-avatar').style.display='block';">
                            <div id="default-avatar" class="default-avatar" style="display: none;">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        <?php else: ?>
                            <div class="default-avatar">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        <?php endif; ?>
                    </center>
                </div>
                    <div style="text-align: center;">
                        <h3><?php echo htmlspecialchars($vendedor_info['nome_comercial'] ?? $vendedor_info['nome_vendedor']); ?></h3>
                        <p style="color: var(--text-light); margin-bottom: 10px;">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($vendedor_info['cidade'] . ' - ' . $vendedor_info['estado']); ?>
                        </p>
                        <p style="color: var(--primary-color); font-weight: 600;">
                            <i class="fas fa-box"></i>
                            <?php echo $total_anuncios; ?> anúncio(s) ativo(s)
                        </p>
                    </div>
                </div><br>

                <!-- Anúncios do Vendedor -->
                <div class="forms-area">
                    <center><h2>Anúncios Publicados</h2></center>
                    
                    <?php if (empty($anuncios_vendedor)): ?>
                        <div class="empty-state">
                            <p>Este vendedor não possui anúncios ativos no momento.</p>
                        </div>
                    <?php else: ?>
                        <div class="anuncios-grid">
                            <?php foreach ($anuncios_vendedor as $anuncio): ?>
                                <div class="anuncio-card">
                                    <div class="card-image">
                                        <?php 
                                            $imagePath = $anuncio['imagem_url'] ? htmlspecialchars($anuncio['imagem_url']) : '../img/placeholder.png';
                                            
                                            if (strpos($imagePath, '../') === 0) {
                                                $imagePath = substr($imagePath, 3);
                                            }
                                            
                                            $fullImagePath = $imagePath;
                                            if ($anuncio['imagem_url'] && !file_exists($fullImagePath)) {
                                                $imagePath = '../img/placeholder.png';
                                            }
                                        ?>
                                        <img src="<?php echo $imagePath; ?>" 
                                             alt="Imagem de <?php echo htmlspecialchars($anuncio['produto']); ?>" 
                                             onerror="this.src='../img/placeholder.png'">
                                    </div>
                                    <div class="card-content">
                                        <div class="card-header">
                                            <h3><?php echo htmlspecialchars($anuncio['produto']); ?></h3>
                                        </div>
                                        <div class="card-body">
                                            <p class="price">
                                                R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?>
                                                <span>/<?php echo htmlspecialchars($anuncio['unidade_medida']); ?></span>
                                            </p>
                                            <p class="estoque">
                                                <i class="fas fa-box"></i>
                                                <?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> disponíveis
                                            </p>
                                            <p class="descricao">
                                                <?php 
                                                $descricao = $anuncio['descricao'] ?? 'Sem descrição.';
                                                $descricao = htmlspecialchars($descricao);
                                                
                                                $limite = 120;
                                                
                                                if (strlen($descricao) > $limite) {
                                                    $descricao_curta = substr($descricao, 0, $limite);
                                                    $ultimo_espaco = strrpos($descricao_curta, ' ');
                                                    
                                                    if ($ultimo_espaco !== false) {
                                                        echo substr($descricao_curta, 0, $ultimo_espaco) . '...';
                                                    } else {
                                                        echo $descricao_curta . '...';
                                                    }
                                                } else {
                                                    echo $descricao;
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        <div class="card-actions">
                                            <?php if ($is_comprador): ?>
                                                <a href="comprador/proposta_nova.php?anuncio_id=<?php echo $anuncio['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-handshake"></i> Fazer Proposta
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-primary open-login-modal" data-target="#loginModal">
                                                    <i class="fas fa-handshake"></i> Fazer Proposta
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Login (reutilizado da página de anúncios) -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Acesso Negociador</h3>
            <p>
                É necessário estar logado como Comprador para fazer uma proposta.
            </p>
            
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
    });
    </script>
</body>
</html>