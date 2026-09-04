<?php
/**
 * Navbar compartilhada das páginas soltas de src/
 * (fora das áreas admin/comprador/vendedor/transportador, que têm o próprio partial).
 *
 * Uso:
 *   <?php $active_nav = 'anuncios'; require __DIR__ . '/includes/navbar.php'; ?>
 *
 * Requer o CSS:  <link rel="stylesheet" href="css/navbar.css">
 *
 * Caminhos são relativos a src/ (ex.: ../index, anuncios, logout, notificacoes).
 *
 * Variáveis opcionais (definir ANTES do require):
 *   $active_nav    string  chave do item ativo (ex.: anuncios|painel|perfil|notificacoes)
 *   $nav_items     array   sobrescreve os itens do meio. Cada item:
 *                          ['key' => 'anuncios', 'label' => 'Anúncios', 'href' => 'anuncios']
 *                          (Home, sino e Sair/Login são sempre renderizados)
 *   $mostrar_sino  bool    exibe o sino de notificações quando logado (padrão: true)
 *
 * A conexão PDO para contar notificações é detectada em $conn ou $db.
 */

$nav_logado = isset($_SESSION['usuario_id']);
$nav_tipo   = $_SESSION['usuario_tipo'] ?? '';
$nav_chats  = $nav_tipo === 'vendedor' ? 'chats' : 'meus_chats';

$active_nav   = $active_nav   ?? '';
$mostrar_sino = $mostrar_sino ?? true;

if (!isset($nav_items)) {
    if ($nav_logado && $nav_tipo !== '') {
        $nav_items = [
            ['key' => 'anuncios', 'label' => 'Anúncios',   'href' => 'anuncios'],
            ['key' => 'painel',   'label' => 'Painel',     'href' => "$nav_tipo/dashboard"],
            ['key' => 'chats',    'label' => 'Chats',      'href' => "$nav_tipo/$nav_chats"],
            ['key' => 'perfil',   'label' => 'Meu Perfil', 'href' => "$nav_tipo/perfil"],
        ];
    } else {
        $nav_items = [
            ['key' => 'anuncios', 'label' => 'Anúncios', 'href' => 'anuncios'],
            ['key' => 'sobre',    'label' => 'Sobre',    'href' => 'sobre'],
            ['key' => 'faq',      'label' => 'FAQ',      'href' => 'faq'],
        ];
    }
}

$nav_total_nao_lidas = 0;
if ($mostrar_sino && $nav_logado) {
    $nav_pdo = null;
    if (isset($conn) && $conn instanceof PDO) {
        $nav_pdo = $conn;
    } elseif (isset($db) && $db instanceof PDO) {
        $nav_pdo = $db;
    }
    if ($nav_pdo) {
        try {
            $nav_stmt = $nav_pdo->prepare(
                "SELECT COUNT(*) FROM notificacoes WHERE usuario_id = :uid AND lida = 0"
            );
            $nav_stmt->bindValue(':uid', $_SESSION['usuario_id'], PDO::PARAM_INT);
            $nav_stmt->execute();
            $nav_total_nao_lidas = (int) $nav_stmt->fetchColumn();
        } catch (PDOException $e) {
            $nav_total_nao_lidas = 0;
        }
    }
}
?>
<header>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../index" class="logo-link">
                    <img src="../img/logo-nova.png" alt="Logo">
                    <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
                </a>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../index" class="nav-link">Home</a></li>
                <?php foreach ($nav_items as $item): ?>
                    <li class="nav-item">
                        <a href="<?= $item['href'] ?>" class="nav-link<?= $active_nav === $item['key'] ? ' active' : '' ?>"><?= $item['label'] ?></a>
                    </li>
                <?php endforeach; ?>
                <?php if ($mostrar_sino && $nav_logado): ?>
                    <li class="nav-item">
                        <a href="notificacoes" class="nav-link no-underline<?= $active_nav === 'notificacoes' ? ' active' : '' ?>">
                            <i class="fas fa-bell"></i>
                            <?php if ($nav_total_nao_lidas > 0): ?>
                                <span class="notificacao-badge"><?= $nav_total_nao_lidas ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($nav_logado): ?>
                    <li class="nav-item"><a href="logout" class="nav-link exit-button no-underline">Sair</a></li>
                <?php else: ?>
                    <li class="nav-item"><a href="login" class="nav-link login-button no-underline">Login</a></li>
                <?php endif; ?>
            </ul>
            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>
</header>
<script>
    (function () {
        var hamburger = document.querySelector(".navbar .hamburger");
        var navMenu = document.querySelector(".navbar .nav-menu");
        if (!hamburger || !navMenu) return;
        hamburger.addEventListener("click", function () {
            hamburger.classList.toggle("active");
            navMenu.classList.toggle("active");
        });
        navMenu.querySelectorAll(".nav-link").forEach(function (n) {
            n.addEventListener("click", function () {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            });
        });
    })();
</script>
