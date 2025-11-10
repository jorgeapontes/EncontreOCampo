<?php
// src/admin/todos_usuarios.php
session_start();

// Verificar se é admin
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexao.php';

$database = new Database();
$conn = $database->getConnection();

// Buscar todos os usuários
$sql = "SELECT id, nome, email, tipo, status, data_criacao FROM usuarios ORDER BY data_criacao DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$feedback_msg = $_GET['msg'] ?? '';
$is_error = strpos($feedback_msg, 'erro') !== false || strpos($feedback_msg, 'Erro') !== false;

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos os Usuários - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="../../img/Logo - Copia.jpg" alt="Logo Encontre Ocampo" class="logo">
                <span class="brand-name">Encontre Ocampo</span>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="todos_usuarios.php" class="nav-link active">Todos os Usuários</a>
                <a href="../../index.php" class="nav-link">Home</a>
                <a href="../logout.php" class="nav-link logout">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($feedback_msg): ?>
            <div class="alert <?php echo $is_error ? 'alert-error' : 'alert-success'; ?>">
                <?php echo htmlspecialchars(urldecode($feedback_msg)); ?>
            </div>
        <?php endif; ?>

        <div class="header-section">
            <h1>Todos os Usuários Cadastrados</h1>
            <p>Gerencie todos os usuários do sistema</p>
        </div>

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Data de Criação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $usuario['tipo']; ?>">
                                <?php echo htmlspecialchars(ucfirst($usuario['tipo'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $usuario['status']; ?>">
                                <?php echo htmlspecialchars(ucfirst($usuario['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($usuario['data_criacao'])); ?></td>
                        <td class="actions">
                            <?php if ($usuario['tipo'] !== 'admin'): ?>
                                <?php if ($usuario['status'] == 'ativo'): ?>
                                    <a href="alterar_status.php?id=<?php echo $usuario['id']; ?>&status=inativo" class="btn btn-warning btn-sm">Desativar</a>
                                <?php else: ?>
                                    <a href="alterar_status.php?id=<?php echo $usuario['id']; ?>&status=ativo" class="btn btn-success btn-sm">Ativar</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Admin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>