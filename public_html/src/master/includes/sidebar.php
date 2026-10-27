<?php
/**
 * Sidebar da área do master (src/master): menu offcanvas no mobile e sidebar
 * recolhível no desktop — a mesma do master do Risenglish.
 * Não mexe em sessão/autenticação — cada página continua responsável por isso.
 * Espera (opcional):
 *   $paginaAtiva   chave de $itensMenu abaixo, ex: 'saude'
 *   $tituloMobile  título exibido no cabeçalho mobile (fallback: label do item ativo)
 */

$paginaAtiva = $paginaAtiva ?? '';

$itensMenu = [
    'saude'         => ['href' => 'monitoramento?aba=saude',         'icon' => 'fa-heartbeat',      'label' => 'Saúde'],
    'auditoria'     => ['href' => 'monitoramento?aba=auditoria',     'icon' => 'fa-clipboard-list', 'label' => 'Auditoria'],
    'seguranca'     => ['href' => 'monitoramento?aba=seguranca',     'icon' => 'fa-shield-alt',     'label' => 'Segurança'],
    'pagamentos'    => ['href' => 'monitoramento?aba=pagamentos',    'icon' => 'fa-credit-card',    'label' => 'Pagamentos'],
    'admins'        => ['href' => 'admins',                          'icon' => 'fa-user-shield',    'label' => 'Admins'],
    'lgpd'          => ['href' => 'lgpd',                            'icon' => 'fa-user-lock',      'label' => 'LGPD'],
    'checklist'     => ['href' => 'checklist',                       'icon' => 'fa-tasks',          'label' => 'Checklist'],
    'erros'         => ['href' => 'monitoramento?aba=erros',         'icon' => 'fa-bug',            'label' => 'Erros'],
    'backups'       => ['href' => 'monitoramento?aba=backups',       'icon' => 'fa-database',       'label' => 'Backups'],
    'configuracoes' => ['href' => 'monitoramento?aba=configuracoes', 'icon' => 'fa-cog',            'label' => 'Configurações'],
];

/** Iniciais em cinza claro (o master não tem foto), como os avatares sem foto do Risenglish. */
function masterAvatar(string $nome, int $tamanho): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $iniciais = mb_strtoupper(mb_substr($partes[0] ?? 'M', 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : ''));
    $estilo = "width:{$tamanho}px;height:{$tamanho}px;font-size:" . round($tamanho * 0.4) . 'px;';
    return '<span class="avatar avatar-iniciais" style="' . $estilo . '" role="img" aria-label="' . htmlspecialchars($nome) . '">'
        . htmlspecialchars($iniciais) . '</span>';
}

$masterNomeBruto = $_SESSION['usuario_nome'] ?? 'Master';
$masterNome = htmlspecialchars($masterNomeBruto);
$tituloMobile = $tituloMobile ?? ($itensMenu[$paginaAtiva]['label'] ?? 'Menu');
?>
<?php require __DIR__ . '/sem_reenvio.php'; ?>
<?php require __DIR__ . '/responsivo.php'; ?>
<script>
    // Aplica o estado salvo da sidebar antes dela ser pintada, evitando flash do estado errado.
    (function () {
        try {
            if (localStorage.getItem('master_sidebar_collapsed') === '1') {
                document.documentElement.classList.add('master-sidebar-collapsed');
            }
        } catch (e) {}
    })();
</script>

<header class="d-flex d-xl-none mobile-navbar-custom border-bottom shadow-sm p-3 align-items-center sticky-top">
    <button class="btn btn-outline-light me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#masterSidebarOffcanvas" aria-controls="masterSidebarOffcanvas" aria-label="Abrir Menu">
        <i class="fas fa-bars"></i>
    </button>
    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($tituloMobile); ?></h5>
</header>

<div class="offcanvas offcanvas-start text-white mobile-offcanvas" tabindex="-1" id="masterSidebarOffcanvas" aria-labelledby="masterSidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="masterSidebarOffcanvasLabel"><?php echo masterAvatar($masterNomeBruto, 36); ?><span><?php echo $masterNome; ?></span></h5>
        <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <div class="d-flex flex-column flex-grow-1 mb-5">
            <?php foreach ($itensMenu as $chave => $item): ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>" class="rounded<?php echo $chave === $paginaAtiva ? ' active' : ''; ?>">
                <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>&nbsp;&nbsp;<?php echo htmlspecialchars($item['label']); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="mt-auto">
            <a href="../logout" id="botao-sair" class="btn btn-outline-danger w-100"><i class="fas fa-sign-out-alt me-2"></i>Sair</a>
        </div>
    </div>
</div>

<div class="col-xl-2 d-none d-xl-flex flex-column sidebar p-3">
    <button type="button" id="sidebarToggle" class="sidebar-toggle-btn" title="Recolher/expandir menu" aria-label="Recolher ou expandir menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-usuario mb-3">
        <?php echo masterAvatar($masterNomeBruto, 56); ?>
        <h5 class="user-name"><?php echo $masterNome; ?></h5>
    </div>

    <div class="d-flex flex-column flex-grow-1 mb-5">
        <?php foreach ($itensMenu as $chave => $item): ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>"
           class="rounded<?php echo $chave === $paginaAtiva ? ' active' : ''; ?>"
           aria-label="<?php echo htmlspecialchars($item['label']); ?>"
           title="<?php echo htmlspecialchars($item['label']); ?>">
            <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i><span class="nav-label"><?php echo htmlspecialchars($item['label']); ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="mt-auto">
        <a href="../logout" id="botao-sair" class="btn btn-outline-danger w-100" aria-label="Sair" title="Sair">
            <i class="fas fa-sign-out-alt me-2"></i><span class="nav-label">Sair</span>
        </a>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('sidebarToggle');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var collapsed = document.documentElement.classList.toggle('master-sidebar-collapsed');
        try {
            localStorage.setItem('master_sidebar_collapsed', collapsed ? '1' : '0');
        } catch (e) {}
    });
})();
</script>
