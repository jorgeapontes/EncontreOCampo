<?php
// src/vendedor/escolher_plano.php
require_once 'auth.php'; // Isso carrega $usuario e $vendedor do banco/sessão
require_once __DIR__ . '/../../config/MercadoPagoConfig.php';

$database = new Database();
$db = $database->getConnection();

// 1. Buscar os planos disponíveis
$stmt = $db->query("SELECT * FROM planos ORDER BY preco_mensal ASC");
$planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$erro = "";

// 2. Lógica quando o usuário clica em um plano
if (isset($_GET['plano_id'])) {
    $plano_escolhido_id = (int)$_GET['plano_id'];
    
    // Buscar detalhes do plano no banco
    $stmt_p = $db->prepare("SELECT * FROM planos WHERE id = ?");
    $stmt_p->execute([$plano_escolhido_id]);
    $plano_info = $stmt_p->fetch();

    if ($plano_info) {
        try {
            // VERIFICAÇÃO CRÍTICA: O e-mail precisa existir na sessão ou no objeto $usuario
            // Se o seu auth.php não colocar o email em $usuario['email'], pegamos da sessão
            $email_usuario = $usuario['email'] ?? $_SESSION['usuario_email'] ?? null;

            if (!$email_usuario) {
                throw new Exception("E-mail do usuário não encontrado. Por favor, faça login novamente.");
            }

            // Chamamos a função de assinatura configurada no MercadoPagoConfig.php
            $link_assinatura = MercadoPagoAPI::createSubscription(
                "Assinatura: " . $plano_info['nome'],
                $plano_info['preco_mensal'],
                $vendedor['id'],
                $plano_info['id'],
                $email_usuario // Passando o e-mail que estava faltando
            );
            
            // Redireciona para o checkout do Mercado Pago
            header("Location: " . $link_assinatura);
            exit;
            
        } catch (Exception $e) {
            $erro = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Planos de Assinatura</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .planos-grid { display: flex; gap: 20px; justify-content: center; margin-top: 50px; }
        .card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; width: 250px; text-align: center; }
        .preco { font-size: 24px; font-weight: bold; color: #009ee3; }
        .btn { display: inline-block; background: #009ee3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
        .alert-erro { background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin: 20px auto; max-width: 600px; text-align: center; }
    </style>
</head>
<body>

    <div class="main-content">
        <h1>Escolha seu Plano Mensal</h1>
        <p>A cobrança será automática todo mês.</p>

        <?php if ($erro): ?>
            <div class="alert-erro">
                <strong>Erro:</strong> <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <div class="planos-grid">
            <?php foreach ($planos as $plano): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($plano['nome']); ?></h3>
                    <p class="preco">R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?></p>
                    <a href="?plano_id=<?php echo $plano['id']; ?>" class="btn">Selecionar Plano</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>