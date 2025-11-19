<?php
session_start();

// Verificar se é admin
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexao.php';

$database = new Database();
$conn = $database->getConnection();

// FILTRO
$filtro_tipo = $_GET['filtro_tipo'] ?? '';

// ORDENAÇÃO
$ordenar = $_GET['ordenar'] ?? '';
$orderBy = "data_criacao DESC";

if ($ordenar === "novo_velho") {
    $orderBy = "data_criacao DESC";
} elseif ($ordenar === "velho_novo") {
    $orderBy = "data_criacao ASC";
} elseif ($ordenar === "az") {
    $orderBy = "nome ASC";
} elseif ($ordenar === "za") {
    $orderBy = "nome DESC";
}

// Query com filtro + ordenação
$sql = "SELECT id, nome, email, tipo, status, data_criacao FROM usuarios";
$params = [];

if (!empty($filtro_tipo)) {
    $sql .= " WHERE tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

$sql .= " ORDER BY $orderBy";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
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
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">

    <style>
        /* Nenhuma regra existente foi alterada. Apenas adicionando pequenos ajustes mínimos */
        .table-controls {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
            gap: 10px;
        }
        .table-controls select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            cursor: pointer;
        }
    </style>
</head>

<body>

<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <img src="././img/logo-nova.png" alt="Logo Encontre Ocampo" class="logo">
            <div>
                <h1>ENCONTRE</h1>
                <h2>O CAMPO</h2>
            </div>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="todos_usuarios.php" class="nav-link active">Todos os Usuários</a>
            <a href="././index.php" class="nav-link">Home</a>
            <a href="./logout.php" class="nav-link logout">Sair</a>
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

    <!-- BOTÕES DE FILTRO E ORDENAR -->
    <form method="GET">
        <div class="table-controls">

            <!-- FILTRO -->
            <select name="filtro_tipo" onchange="this.form.submit()">
                <option value="">Filtrar por tipo</option>
                <option value="admin" <?= $filtro_tipo === "admin" ? "selected" : "" ?>>Admin</option>
                <option value="comprador" <?= $filtro_tipo === "comprador" ? "selected" : "" ?>>Comprador</option>
                <option value="vendedor" <?= $filtro_tipo === "vendedor" ? "selected" : "" ?>>Vendedor</option>
                <option value="transportador" <?= $filtro_tipo === "transportador" ? "selected" : "" ?>>Transportador</option>
            </select>

            <!-- ORDENAR -->
            <select name="ordenar" onchange="this.form.submit()">
                <option value="">Ordenar por</option>
                <option value="novo_velho" <?= $ordenar === "novo_velho" ? "selected" : "" ?>>Mais novo → Mais velho</option>
                <option value="velho_novo" <?= $ordenar === "velho_novo" ? "selected" : "" ?>>Mais velho → Mais novo</option>
                <option value="az" <?= $ordenar === "az" ? "selected" : "" ?>>A → Z</option>
                <option value="za" <?= $ordenar === "za" ? "selected" : "" ?>>Z → A</option>
            </select>

        </div>
    </form>

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
                    <td><?= htmlspecialchars($usuario['id']); ?></td>
                    <td><?= htmlspecialchars($usuario['nome']); ?></td>
                    <td><?= htmlspecialchars($usuario['email']); ?></td>

                    <td>
                        <span class="badge badge-<?= $usuario['tipo']; ?>">
                            <?= ucfirst(htmlspecialchars($usuario['tipo'])); ?>
                        </span>
                    </td>

                    <td>
                        <span class="status-badge status-<?= $usuario['status']; ?>">
                            <?= ucfirst(htmlspecialchars($usuario['status'])); ?>
                        </span>
                    </td>

                    <td><?= date('d/m/Y H:i', strtotime($usuario['data_criacao'])); ?></td>

                    <td class="actions">

                        <?php if ($usuario['tipo'] !== 'admin'): ?>
                            <?php if ($usuario['status'] === 'ativo'): ?>
                                <a href="alterar_status.php?id=<?= $usuario['id']; ?>&status=inativo"
                                   class="btn btn-warning btn-sm">Desativar</a>
                            <?php else: ?>
                                <a href="alterar_status.php?id=<?= $usuario['id']; ?>&status=ativo"
                                   class="btn btn-success btn-sm">Ativar</a>
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
