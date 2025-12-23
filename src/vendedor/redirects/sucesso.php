// EncontreOCampo/src/vendedor/redirects/sucesso.php
<?php
require_once __DIR__ . '/../conexao.php';

// Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Buscar última assinatura do vendedor
$stmt = $conn->prepare("
    SELECT a.*, p.nome as plano_nome, p.preco_mensal, p.descricao as plano_descricao
    FROM vendedor_assinaturas a
    JOIN planos p ON a.plano_id = p.id
    WHERE a.vendedor_id IN (SELECT id FROM vendedores WHERE usuario_id = ?)
    ORDER BY a.created_at DESC 
    LIMIT 1
");
$stmt->execute([$_SESSION['usuario_id']]);
$assinatura = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Aprovado - EncontreOCampo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .assinatura-info {
            background: #f0f9ff;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            text-align: left;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #009ee3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #007bb5;
        }
        .btn-secondary {
            background-color: #666;
        }
        .btn-secondary:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✓</div>
        <h1>Pagamento Aprovado!</h1>
        <p>Sua assinatura foi ativada com sucesso.</p>
        
        <?php if ($assinatura): ?>
            <div class="assinatura-info">
                <h3>Detalhes da Assinatura:</h3>
                <p><strong>Plano:</strong> <?php echo htmlspecialchars($assinatura['plano_nome']); ?></p>
                <p><strong>Descrição:</strong> <?php echo htmlspecialchars($assinatura['plano_descricao']); ?></p>
                <p><strong>Valor:</strong> R$ <?php echo number_format($assinatura['preco_mensal'], 2, ',', '.'); ?> /mês</p>
                <p><strong>Status:</strong> <span style="color: #4CAF50;"><?php echo ucfirst($assinatura['status']); ?></span></p>
                <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($assinatura['created_at'])); ?></p>
            </div>
        <?php endif; ?>
        
        <p>Você receberá um email de confirmação em breve.</p>
        <p>Obrigado por escolher o EncontreOCampo!</p>
        
        <a href="../perfil.php" class="btn">Voltar para o Perfil</a>
        <a href="../../index.php" class="btn btn-secondary">Ir para a Home</a>
    </div>
</body>
</html>