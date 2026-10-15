<?php
// notificacoes.php
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

// Marcar notificação como lida
if (isset($_GET['ler'])) {
    $notificacao_id = $_GET['ler'];
    $sql_ler = "UPDATE notificacoes SET lida = 1 WHERE id = :id AND usuario_id = :usuario_id";
    $stmt_ler = $conn->prepare($sql_ler);
    $stmt_ler->bindParam(':id', $notificacao_id, PDO::PARAM_INT);
    $stmt_ler->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_ler->execute();
    header("Location: notificacoes");
    exit();
}

// Marcar todas como lidas
if (isset($_GET['ler_todas'])) {
    $sql_ler_todas = "UPDATE notificacoes SET lida = 1 WHERE usuario_id = :usuario_id AND lida = 0";
    $stmt_ler_todas = $conn->prepare($sql_ler_todas);
    $stmt_ler_todas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_ler_todas->execute();
    header("Location: notificacoes");
    exit();
}

// Buscar notificações
$sql = "SELECT * FROM notificacoes WHERE usuario_id = :usuario_id ORDER BY data_criacao DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar notificações não lidas
$sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
$stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
$stmt_nao_lidas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_nao_lidas->execute();
$total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];

// Menu conforme o tipo de usuário (admin não tem página de perfil)
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
$nav_items = [];
if (in_array($tipo_usuario, ['admin', 'vendedor', 'comprador', 'transportador'], true)) {
    $nav_items[] = ['key' => 'painel', 'label' => 'Painel', 'href' => $tipo_usuario . '/dashboard'];
}
if (in_array($tipo_usuario, ['vendedor', 'comprador', 'transportador'], true)) {
    $nav_items[] = ['key' => 'perfil', 'label' => 'Meu Perfil', 'href' => $tipo_usuario . '/perfil'];
}

/**
 * Converte a URL gravada na notificação em um link válido a partir desta
 * página (que fica em src/). O sistema gravou URLs em vários formatos ao
 * longo do tempo, então isso também corrige as notificações antigas:
 *   "src/vendedor/x.php"  -> relativa à raiz do site
 *   "/src/vendedor/x"     -> absoluta
 *   "../avaliar.php"      -> escrita a partir de uma subpasta de src/
 *   "avaliar.php"         -> já relativa a src/
 */
function urlNotificacao($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    // Qualquer outro esquema (javascript:, data:...) é descartado
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) || strpos($url, '//') === 0) {
        return '';
    }
    // Página que nunca existiu: as solicitações pendentes ficam no dashboard do admin
    $url = preg_replace('#^/?src/admin/solicitacoes(\.php)?#', 'src/admin/dashboard', $url);

    if ($url[0] === '/') {
        return '..' . $url;
    }
    if (strpos($url, 'src/') === 0) {
        return '../' . $url;
    }
    while (strpos($url, '../') === 0) {
        $url = substr($url, 3);
    }
    return $url;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Encontre o Campo</title>
    <link rel="stylesheet" href="css/notificacoes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/navbar.css">
</head>
<body>
    <?php
    $active_nav = 'notificacoes';
    require __DIR__ . '/includes/navbar.php';
    ?>

    <main class="notificacoes-container">
        <center>
            <div class="header">
                <h1>Suas Notificações</h1>
                <?php if ($total_nao_lidas > 0): ?>
                    <a href="notificacoes?ler_todas=1" class="btn-ler-todas">Marcar todas como lidas</a>
                <?php endif; ?>
            </div>
        </center>
        
        <?php if (empty($notificacoes)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>Nenhuma notificação</h3>
                <p>Quando você tiver notificações, elas aparecerão aqui.</p>
            </div>
        <?php else: ?>
            <div class="notificacoes-list">
                <?php foreach ($notificacoes as $notificacao): ?>
                    <div class="notificacao-item <?php echo $notificacao['lida'] ? '' : 'nao-lida'; ?> tipo-<?php echo $notificacao['tipo']; ?>">
                        <div class="notificacao-mensagem">
                            <?php echo htmlspecialchars($notificacao['mensagem']); ?>
                        </div>
                        <div class="notificacao-data">
                            <?php echo date('d/m/Y H:i', strtotime($notificacao['data_criacao'])); ?>
                        </div>
                        <div class="notificacao-actions">
                            <?php if (!$notificacao['lida']): ?>
                                <a href="notificacoes?ler=<?php echo $notificacao['id']; ?>">Marcar como lida</a>
                            <?php endif; ?>
                            <?php $link_notificacao = urlNotificacao($notificacao['url']); ?>
                            <?php if ($link_notificacao): ?>
                                <a href="<?php echo htmlspecialchars($link_notificacao); ?>">Ver</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>