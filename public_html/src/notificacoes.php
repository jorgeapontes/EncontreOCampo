<?php
// notificacoes.php
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: src/login");
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

if($_SESSION['usuario_tipo'] == 'vendedor') {
    $painel_href = 'vendedor/dashboard';
    $perfil_href = 'vendedor/perfil';
} else if ($_SESSION['usuario_tipo'] == 'comprador') {
    $painel_href = 'comprador/dashboard';
    $perfil_href = 'comprador/perfil';
} else if ($_SESSION['usuario_tipo'] == 'transportador') {
    $painel_href = 'transportador/dashboard';
    $perfil_href = 'transportador/perfil';
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
    $nav_items = [
        ['key' => 'painel', 'label' => 'Painel',     'href' => $painel_href],
        ['key' => 'perfil', 'label' => 'Meu Perfil', 'href' => $perfil_href],
    ];
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
                            <?php if ($notificacao['url']): ?>
                                <a href="<?php echo htmlspecialchars($notificacao['url']); ?>">Ver</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>