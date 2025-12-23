<?php
// EncontreOCampo/src/vendedor/escolher_plano_simples.php
require_once __DIR__ . '/../conexao.php';

session_start();

// Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$usuario_id = $_SESSION['usuario_id'];

// Buscar dados do vendedor
$stmt = $conn->prepare("
    SELECT v.* FROM vendedores v 
    WHERE v.usuario_id = ?
");
$stmt->execute([$usuario_id]);
$vendedor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendedor) {
    header('Location: ../perfil.php?erro=vendedor_nao_encontrado');
    exit();
}

// Buscar planos ativos
$stmt = $conn->prepare("SELECT * FROM planos WHERE ativo = 1 ORDER BY preco_mensal");
$stmt->execute();
$planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar seleção de plano (versão simplificada)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plano_id'])) {
    $plano_id = (int)$_POST['plano_id'];
    
    // Redirecionar para uma página intermediária
    $_SESSION['plano_selecionado'] = $plano_id;
    $_SESSION['vendedor_id'] = $vendedor['id'];
    
    header('Location: processar_pagamento.php');
    exit();
}

// Buscar assinatura atual ativa
$stmt = $conn->prepare("
    SELECT a.*, p.nome as plano_nome, p.preco_mensal 
    FROM vendedor_assinaturas a
    JOIN planos p ON a.plano_id = p.id
    WHERE a.vendedor_id = ? AND a.status = 'active'
    ORDER BY a.created_at DESC 
    LIMIT 1
");
$stmt->execute([$vendedor['id']]);
$assinatura_atual = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escolher Plano - EncontreOCampo</title>
    <style>
        /* Mesmos estilos anteriores */
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .planos-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
        }
        .plano-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 25px;
            width: 280px;
            text-align: center;
            transition: transform 0.3s;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .plano-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .plano-card.destaque {
            border: 2px solid #009ee3;
            background-color: #f0f9ff;
        }
        .plano-preco {
            font-size: 32px;
            font-weight: bold;
            color: #009ee3;
            margin: 15px 0;
        }
        .btn-assinar {
            background-color: #009ee3;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 15px;
            transition: background-color 0.3s;
        }
        .btn-assinar:hover {
            background-color: #007bb5;
        }
        .btn-assinar:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .recursos {
            text-align: left;
            margin: 20px 0;
            padding: 0;
            list-style: none;
        }
        .recursos li {
            margin: 8px 0;
            padding-left: 20px;
            position: relative;
        }
        .recursos li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #4CAF50;
        }
        .badge-destaque {
            background-color: #009ee3;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 10px;
            display: inline-block;
        }
        .assinatura-atual {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
            border-left: 4px solid #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Escolha seu Plano</h1>
        
        <?php if ($assinatura_atual): ?>
            <div class="assinatura-atual">
                <h3>📋 Seu Plano Atual</h3>
                <p><strong>Plano:</strong> <?php echo htmlspecialchars($assinatura_atual['plano_nome']); ?></p>
                <p><strong>Valor:</strong> R$ <?php echo number_format($assinatura_atual['preco_mensal'], 2, ',', '.'); ?> /mês</p>
                <p><strong>Status:</strong> <span style="color: #4CAF50;">Ativo</span></p>
            </div>
        <?php endif; ?>
        
        <div class="planos-container">
            <?php foreach ($planos as $index => $plano): ?>
                <?php 
                $is_plano_atual = $assinatura_atual && $assinatura_atual['plano_id'] == $plano['id'];
                $is_destaque = ($plano['preco_mensal'] > 0 && $index == 1);
                ?>
                <div class="plano-card <?php echo $is_destaque ? 'destaque' : ''; ?>">
                    <?php if ($is_destaque): ?>
                        <div class="badge-destaque">MAIS POPULAR</div>
                    <?php endif; ?>
                    
                    <h2><?php echo htmlspecialchars($plano['nome']); ?></h2>
                    
                    <div class="plano-preco">
                        R$ <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?>
                        <small style="display: block; font-size: 14px; color: #666;">/mês</small>
                    </div>
                    
                    <p style="color: #666; min-height: 40px;"><?php echo htmlspecialchars($plano['descricao'] ?? ''); ?></p>
                    
                    <ul class="recursos">
                        <li><?php echo $plano['quantidade_anuncios_pagos']; ?> anúncios pagos</li>
                        <li><?php echo $plano['quantidade_anuncios_gratis']; ?> anúncio gratuito</li>
                        <li>Total de <?php echo $plano['limite_total_anuncios']; ?> anúncios ativos</li>
                    </ul>
                    
                    <form method="POST">
                        <input type="hidden" name="plano_id" value="<?php echo $plano['id']; ?>">
                        <button type="submit" class="btn-assinar" <?php echo $is_plano_atual ? 'disabled' : ''; ?>>
                            <?php 
                                if ($is_plano_atual) {
                                    echo '✅ Plano Atual';
                                } elseif ($plano['preco_mensal'] == 0) {
                                    echo 'Selecionar Grátis';
                                } else {
                                    echo 'Assinar Agora';
                                }
                            ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="perfil.php" style="color: #009ee3; text-decoration: none;">← Voltar para o perfil</a>
        </div>
    </div>
</body>
</html>