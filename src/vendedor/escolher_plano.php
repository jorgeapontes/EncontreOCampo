<?php
require_once dirname(__DIR__) . '/conexao.php'; 

$database = new Database();
$db = $database->getConnection();

// Ajustado: Tabela correta é 'planos_assinatura'
$query = "SELECT id, nome, preco_mensal FROM planos ORDER BY preco_mensal ASC";
$result = $db->query($query); 
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Planos - Encontre O Campo</title>
    <style>
        .planos-container { display: flex; justify-content: center; gap: 20px; padding: 50px; font-family: sans-serif; }
        .card-plano { border: 2px solid #e0e0e0; padding: 30px; border-radius: 15px; width: 250px; text-align: center; transition: 0.3s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card-plano:hover { border-color: #28a745; transform: translateY(-5px); }
        .preco { font-size: 28px; color: #28a745; font-weight: bold; margin: 15px 0; }
        .btn-assinar { display: inline-block; background: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <h1 style="text-align:center; margin-top: 30px;">Escolha seu Plano de Assinatura</h1>
    <div class="planos-container">
        <?php while($plano = $result->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="card-plano">
                <h2><?php echo htmlspecialchars($plano['nome']); ?></h2>
                <div class="preco">R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?></div>
                <p style="color: #666;">Assinatura Mensal</p>
                <a href="processar_assinatura.php?id=<?php echo $plano['id']; ?>" class="btn-assinar">Assinar Agora</a>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>