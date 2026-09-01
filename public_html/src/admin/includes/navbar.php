<?php
/**
 * Navbar compartilhada do painel administrativo.
 *
 * Uso:
 *   <?php $active_nav = 'usuarios'; require __DIR__ . '/includes/navbar.php'; ?>
 *
 * Requer o CSS:  <link rel="stylesheet" href="css/navbar.css">
 *
 * $active_nav aceita: dashboard | usuarios | chats | comprovantes
 * Se não for definido, o item ativo é detectado pelo nome do arquivo atual.
 */

$nav_items = [
    'dashboard'    => ['label' => 'Dashboard',         'href' => 'dashboard'],
    'usuarios'     => ['label' => 'Todos os Usuários', 'href' => 'todos_usuarios'],
    'chats'        => ['label' => 'Chats',             'href' => 'chats_admin'],
    'comprovantes' => ['label' => 'Comprovantes',      'href' => 'manage_comprovantes'],
];

if (!isset($active_nav)) {
    $arquivo_atual = basename($_SERVER['SCRIPT_NAME'], '.php');
    $mapa_paginas = [
        'dashboard'           => 'dashboard',
        'todos_usuarios'      => 'usuarios',
        'chats_admin'         => 'chats',
        'manage_comprovantes' => 'comprovantes',
    ];
    $active_nav = $mapa_paginas[$arquivo_atual] ?? '';
}
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <a href="../../index" class="logo-link">
                <img src="../../img/logo-nova.png" class="logo" alt="Logo Encontre o Campo">
                <div>
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
            </a>
        </div>
        <div class="nav-links">
            <?php foreach ($nav_items as $chave => $item): ?>
                <a href="<?= $item['href'] ?>" class="nav-link<?= $active_nav === $chave ? ' active' : '' ?>"><?= $item['label'] ?></a>
            <?php endforeach; ?>
            <a href="../../index" class="nav-link">Home</a>
            <a href="../logout" class="nav-link logout">Sair</a>
        </div>
    </div>
</nav>
