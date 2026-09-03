<?php
/**
 * Navbar compartilhada da área do vendedor.
 *
 * Uso:
 *   <?php $active_nav = 'painel'; require __DIR__ . '/includes/navbar.php'; ?>
 *
 * Requer o CSS:  <link rel="stylesheet" href="../css/vendedor/navbar.css">
 *
 * Variáveis opcionais (definir ANTES do require):
 *   $active_nav    string  chave do item ativo (ex.: painel|anuncios|chats|perfil|propostas)
 *   $nav_items     array   sobrescreve os itens do meio do menu. Cada item:
 *                          ['key' => 'painel', 'label' => 'Painel', 'href' => 'dashboard']
 *                          (Home, sino e Sair são sempre renderizados automaticamente)
 *   $mostrar_sino  bool    exibe o sino de notificações (padrão: true)
 *
 * A conexão PDO usada para contar notificações é detectada em $db ou $conn.
 */

if (!isset($nav_items)) {
    $nav_items = [
        ['key' => 'anuncios', 'label' => 'Anúncios',   'href' => '../anuncios'],
        ['key' => 'painel',   'label' => 'Painel',     'href' => 'dashboard'],
        ['key' => 'chats',    'label' => 'Chats',      'href' => 'chats'],
        ['key' => 'perfil',   'label' => 'Meu Perfil', 'href' => 'perfil'],
    ];
}

$active_nav   = $active_nav   ?? '';
$mostrar_sino = $mostrar_sino ?? true;

// Contagem de notificações não lidas (badge do sino)
$nav_total_nao_lidas = 0;
if ($mostrar_sino && isset($_SESSION['usuario_id'])) {
    $nav_pdo = null;
    if (isset($db) && $db instanceof PDO) {
        $nav_pdo = $db;
    } elseif (isset($conn) && $conn instanceof PDO) {
        $nav_pdo = $conn;
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
                <a href="../../index" class="logo-link">
                    <img src="../../img/logo-nova.png" alt="Logo">
                    <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
                </a>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../../index" class="nav-link">Home</a></li>
                <?php foreach ($nav_items as $item): ?>
                    <li class="nav-item">
                        <a href="<?= $item['href'] ?>" class="nav-link<?= $active_nav === $item['key'] ? ' active' : '' ?>"><?= $item['label'] ?></a>
                    </li>
                <?php endforeach; ?>
                <?php if ($mostrar_sino && isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php if ($nav_total_nao_lidas > 0): ?>
                                <span class="notificacao-badge"><?= $nav_total_nao_lidas ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item"><a href="../logout" class="nav-link exit-button no-underline">Sair</a></li>
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
