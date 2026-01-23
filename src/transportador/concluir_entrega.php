<?php
// src/transportador/concluir_entrega.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$entrega_id = intval($_GET['id'] ?? 0);

$database = new Database();
$db = $database->getConnection();

// Buscar id do transportador
$sql = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id";
$stmt = $db->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$transportador = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$transportador) {
    die('Transportador não encontrado.');
}
$transportador_id = $transportador['id'];

// Verificar se entrega pertence ao transportador e está em transporte
$sql = "SELECT * FROM entregas WHERE id = :id AND transportador_id = :transportador_id AND status IN ('pendente','em_transporte')";
$stmt = $db->prepare($sql);
$stmt->bindParam(':id', $entrega_id, PDO::PARAM_INT);
$stmt->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
$stmt->execute();
$entrega = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$entrega) {
    die('Entrega não encontrada ou não disponível para conclusão.');
}

$erro = '';
$sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_comprovante'])) {
    $foto = $_FILES['foto_comprovante'];
    if ($foto['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg','jpeg','png','gif'];
        if (in_array($ext, $permitidas)) {
            $nome_arquivo = 'entrega_' . $entrega_id . '_' . time() . '.' . $ext;
            $destino = '../../uploads/entregas/' . $nome_arquivo;
            if (!is_dir('../../uploads/entregas/')) {
                mkdir('../../uploads/entregas/', 0777, true);
            }
            if (move_uploaded_file($foto['tmp_name'], $destino)) {
                $sql_update = "UPDATE entregas SET status = 'entregue', status_detalhado = 'finalizada', foto_comprovante = :foto, data_entrega = NOW() WHERE id = :id";
                $stmt_update = $db->prepare($sql_update);
                $stmt_update->bindParam(':foto', $nome_arquivo);
                $stmt_update->bindParam(':id', $entrega_id, PDO::PARAM_INT);
                $stmt_update->execute();
                $sucesso = 'Entrega concluída com sucesso!';
            } else {
                $erro = 'Erro ao salvar o arquivo.';
            }
        } else {
            $erro = 'Formato de arquivo não permitido.';
        }
    } else {
        $erro = 'Erro no upload da foto.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Concluir Entrega</title>
    <link rel="stylesheet" href="../css/transportador/dashboard.css">
</head>
<body>
    <h1>Concluir Entrega #<?php echo $entrega_id; ?></h1>
    <?php if ($erro): ?><div style="color:red;"><?php echo $erro; ?></div><?php endif; ?>
    <?php if ($sucesso): ?><div style="color:green;"><?php echo $sucesso; ?></div><?php endif; ?>
    <?php if (!$sucesso): ?>
    <form method="POST" enctype="multipart/form-data">
        <label for="foto_comprovante">Foto do comprovante de entrega:</label><br>
        <input type="file" name="foto_comprovante" id="foto_comprovante" accept="image/*" required><br><br>
        <button type="submit">Concluir Entrega</button>
    </form>
    <?php endif; ?>
    <p><a href="entregas.php">Voltar para Entregas</a></p>
</body>
</html>
