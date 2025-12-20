<?php
// src/vendedor/escolher_plano.php
require_once 'auth.php';
require_once __DIR__ . '/../../config/MercadoPagoConfig.php';

$database = new Database();
$db = $database->getConnection();

// Buscar os planos disponíveis no banco
$stmt = $db->query("SELECT * FROM planos ORDER BY preco_mensal ASC");
$planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$link_assinatura = "";

if (isset($_GET['plano_id'])) {
    $plano_escolhido_id = (int)$_GET['plano_id'];
    
    // Buscar detalhes do plano selecionado
    $stmt_p = $db->prepare("SELECT * FROM planos WHERE id = ?");
    $stmt_p->execute([$plano_escolhido_id]);
    $plano_info = $stmt_p->fetch();

    if ($plano_info) {
        try {
            // Chamamos a função de assinatura que configuramos no MercadoPagoConfig.php
            // Passamos: Nome do Plano, Preço, ID do Vendedor e ID do Plano
            $link_assinatura = MercadoPagoAPI::createSubscription(
                "Assinatura: " . $plano_info['nome'],
                $plano_info['preco_mensal'],
                $vendedor['id'],
                $plano_info['id']
            );
            
            // Redireciona imediatamente para o checkout do Mercado Pago
            header("Location: " . $link_assinatura);
            exit;
        } catch (Exception $e) {
            $erro = "Erro ao gerar assinatura: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Escolha seu Plano - Encontre o Campo</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .planos-container { display: flex; gap: 20px; justify-content: center; padding: 50px; }
        .card-plano { border: 1px solid #ddd; padding: 20px; border-radius: 10px; width: 250px; text-align: center; background: white; transition: 0.3s; }
        .card-plano:hover { transform: translateY(-10px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .preco { font-size: 24px; color: #009ee3; font-weight: bold; }
        .btn-assinar { display: inline-block; background: #009ee3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Selecione seu Plano de Assinatura</h1>
        <p>O valor será debitado automaticamente todo mês no seu cartão.</p>

        <?php if (isset($erro)): ?>
            <div style="color:red;"><?php echo $erro; ?></div>
        <?php endif; ?>

        <div class="planos-container">
            <?php foreach ($planos as $plano): ?>
                <div class="card-plano">
                    <h3><?php echo htmlspecialchars($plano['nome']); ?></h3>
                    <p class="preco">R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?><small>/mês</small></p>
                    <p>Acesso total aos recursos do sistema.</p>
                    <a href="?plano_id=<?php echo $plano['id']; ?>" class="btn-assinar">Assinar Agora</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>