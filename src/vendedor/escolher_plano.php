<?php
// Ativar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../conexao.php'; 
require_once __DIR__ . '/../../config/MercadoPagoConfig.php';

// 1. Verificar Sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// 2. Conectar ao Banco usando sua classe Database
$database = new Database();
$conn = $database->getConnection();

// 3. Buscar dados do Vendedor
try {
    $stmt = $conn->prepare("
        SELECT u.nome, u.email, v.id as vendedor_id 
        FROM usuarios u 
        INNER JOIN vendedores v ON v.usuario_id = u.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        die("Erro: Perfil de vendedor não encontrado para este usuário.");
    }
} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}

// 4. Lógica do Mercado Pago ao selecionar um plano
$preference_id = null;
if (isset($_GET['plano_id'])) {
    $plano_id = (int)$_GET['plano_id'];
    
    // Definição dos planos (id deve bater com o que inserimos no SQL)
    $planos_detalhes = [
        1 => ['nome' => 'Plano Básico', 'preco' => 49.90],
        2 => ['nome' => 'Plano Profissional', 'preco' => 99.90],
        3 => ['nome' => 'Plano Premium', 'preco' => 149.90]
    ];

    if (array_key_exists($plano_id, $planos_detalhes)) {
        $plano = $planos_detalhes[$plano_id];
        
        // Referência para o seu Webhook identificar quem pagou
        $external_reference = "vendedor_" . $usuario['vendedor_id'] . "_plano_" . $plano_id;

        try {
            $preference_id = MercadoPagoAPI::createPreference(
                [
                    'title' => "Assinatura: " . $plano['nome'],
                    'quantity' => 1,
                    'unit_price' => $plano['preco']
                ],
                [
                    'name' => $usuario['nome'],
                    'email' => $usuario['email']
                ],
                $external_reference,
                [
                    "success" => "http://localhost/EncontreOCampo/src/vendedor/redirects/sucesso.php",
                    "failure" => "http://localhost/EncontreOCampo/src/vendedor/redirects/falha.php",
                    "pending" => "http://localhost/EncontreOCampo/src/vendedor/redirects/pendente.php"
                ]
            );
        } catch (Exception $e) {
            $erro_mp = "Erro ao gerar pagamento: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escolher Plano - Encontre o Campo</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> <script src="https://sdk.mercadopago.com/js/v2"></script>
    <style>
        .container { max-width: 1000px; margin: 50px auto; font-family: sans-serif; text-align: center; }
        .grid-planos { display: flex; justify-content: center; gap: 20px; margin-top: 30px; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; width: 250px; transition: 0.3s; }
        .card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .preco { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .btn-selecionar { display: inline-block; padding: 10px 20px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
        #wallet_container { margin-top: 40px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Olá, <?php echo htmlspecialchars($usuario['nome']); ?>!</h1>
        <p>Selecione o melhor plano para destacar seus campos e gerenciar suas reservas.</p>

        <div class="grid-planos">
            <div class="card">
                <h3>Básico</h3>
                <p class="preco">R$ 49,90</p>
                <p>1 campo ativo</p>
                <a href="?plano_id=1" class="btn-selecionar">Selecionar</a>
            </div>
            <div class="card" style="border-color: #27ae60;">
                <h3 style="color: #27ae60;">Profissional</h3>
                <p class="preco">R$ 99,90</p>
                <p>Até 5 campos ativos</p>
                <a href="?plano_id=2" class="btn-selecionar">Selecionar</a>
            </div>
            <div class="card">
                <h3>Premium</h3>
                <p class="preco">R$ 149,90</p>
                <p>Campos ilimitados</p>
                <a href="?plano_id=3" class="btn-selecionar">Selecionar</a>
            </div>
        </div>

        <?php if (isset($erro_mp)): ?>
            <p style="color:red; margin-top:20px;"><?php echo $erro_mp; ?></p>
        <?php endif; ?>

        <div id="wallet_container"></div>
    </div>

    <?php if ($preference_id): ?>
    <script>
        const mp = new MercadoPago('<?php echo $_ENV['MP_PUBLIC_KEY'] ?? "SUA_CHAVE_PUBLICA_AQUI"; ?>');
        const bricksBuilder = mp.bricks();
        bricksBuilder.create("wallet", "wallet_container", {
            initialization: {
                preferenceId: '<?php echo $preference_id; ?>',
                redirectMode: 'modal'
            },
        });
    </script>
    <?php endif; ?>
</body>
</html>