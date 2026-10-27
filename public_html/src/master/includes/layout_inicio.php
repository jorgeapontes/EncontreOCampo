<?php
/**
 * Começo do HTML das páginas do master (cabeçalho, menu lateral e mensagem do topo).
 * Espera: $paginaAtiva (chave do menu), $tituloPagina; opcional: $mensagem ([tipo, texto]).
 * Fechar com includes/layout_fim.php.
 */
$mensagem = $mensagem ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($tituloPagina) ?> - Encontre o Campo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="../css/master/base.css?v=<?= @filemtime(__DIR__ . '/../../css/master/base.css') ?>">
    <link rel="stylesheet" href="../css/master/monitoramento.css?v=<?= @filemtime(__DIR__ . '/../../css/master/monitoramento.css') ?>">
</head>
<body>

<div class="d-flex">
    <?php $tituloMobile = $tituloPagina; require __DIR__ . '/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="page-header">
            <div>
                <div class="sobretitulo">Monitoramento</div>
                <h1 class="page-title"><?= e($tituloPagina) ?></h1>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= e($mensagem[0]) ?> alert-dismissible fade show" role="alert">
                <?= e($mensagem[1]) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>
