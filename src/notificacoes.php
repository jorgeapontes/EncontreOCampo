<?php
// notificacoes.php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: src/login.php");
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
    header("Location: notificacoes.php");
    exit();
}

// Marcar todas como lidas
if (isset($_GET['ler_todas'])) {
    $sql_ler_todas = "UPDATE notificacoes SET lida = 1 WHERE usuario_id = :usuario_id AND lida = 0";
    $stmt_ler_todas = $conn->prepare($sql_ler_todas);
    $stmt_ler_todas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_ler_todas->execute();
    header("Location: notificacoes.php");
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
    $painel_href = 'vendedor/dashboard.php';
    $perfil_href = 'vendedor/perfil.php';
} else if ($_SESSION['usuario_tipo'] == 'comprador') {
    $painel_href = 'comprador/dashboard.php';
    $perfil_href = 'comprador/perfil.php';
} else if ($_SESSION['usuario_tipo'] == 'transportador') {
    $painel_href = 'transportador/dashboard.php';
    $perfil_href = 'transportador/perfil.php';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Encontre o Campo</title>
    <link rel="stylesheet" href="css/notificacoes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $painel_href ?>?>" class="nav-link">Painel</a>
                    </li>
                     <li class="nav-item">
                        <a href="<?= $perfil_href ?>?>" class="nav-link">Meu Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a href="notificacoes.php" class="nav-link active no-underline">
                            <i class="fas fa-bell"></i>
                            <?php if ($total_nao_lidas > 0): ?>
                                <span class="notificacao-badge"><?php echo $total_nao_lidas; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php if (isset($_SESSION['usuario_nome'])): ?>
                            <a href="logout.php" class="nav-link exit-button no-underline">
                                Sair
                            </a>
                        <?php else: ?>
                            <a href="src/login.php" class="nav-link login-button no-underline">Login</a>
                        <?php endif; ?>
                    </li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>

    <main class="notificacoes-container">
        <center>
            <div class="header">
                <h1>Suas Notificações</h1>
                <?php if ($total_nao_lidas > 0): ?>
                    <a href="notificacoes.php?ler_todas=1" class="btn-ler-todas">Marcar todas como lidas</a>
                <?php endif; ?>
            </div>
        </center>
        
        <?php if (empty($notificacoes)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash" style="font-size: 48px; margin-bottom: 20px;"></i>
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
                                <a href="notificacoes.php?ler=<?php echo $notificacao['id']; ?>">Marcar como lida</a>
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

    <script>
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        hamburger.addEventListener("click", () => {
            hamburger.classList.toggle("active");
            navMenu.classList.toggle("active");
        });

        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));
    </script>
</body>
</html>