<?php
// src/vendedor/gerenciar_assinatura.php
require_once 'auth.php'; // Garante que $vendedor e $db estão carregados

// Buscamos os dados atualizados do plano e assinatura
$stmt = $db->prepare("
    SELECT v.*, p.nome as nome_plano, p.preco_mensal 
    FROM vendedores v 
    LEFT JOIN planos p ON v.plano_id = p.id 
    WHERE v.id = ?
");
$stmt->execute([$vendedor['id']]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

// Contagem de produtos (ajustado de 'anuncios' para 'produtos')
$stmt_prod = $db->prepare("SELECT COUNT(*) FROM produtos WHERE vendedor_id = ?");
$stmt_prod->execute([$vendedor['id']]);
$total_produtos = $stmt_prod->fetchColumn();

// Lógica para definir se está ativo
$assinatura_ativa = ($dados['plano_id'] > 1 && $dados['status_assinatura'] === 'ativo');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Assinatura</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

    <div class="main-content">
        <div class="container">
            <h1>Minha Assinatura</h1>

            <?php if ($assinatura_ativa): ?>
                <div class="card-assinatura-ativa" style="border-left: 5px solid #28a745; padding: 20px; background: #f8fff9;">
                    <h2>Plano: <?php echo htmlspecialchars($dados['nome_plano']); ?></h2>
                    <p>Status: <span class="badge-ativo" style="color: green; font-weight: bold;">ATIVA</span></p>
                    <p>Valor: R$ <?php echo number_format($dados['preco_mensal'], 2, ',', '.'); ?>/mês</p>
                    <p>Produtos cadastrados: <?php echo $total_produtos; ?></p>
                    <br>
                    <small>Sua assinatura é renovada automaticamente pelo Mercado Pago.</small>
                </div>
            <?php else: ?>
                <div class="card-no-subscription" style="text-align: center; padding: 40px; border: 2px dashed #ccc;">
                    <img src="../../assets/img/no-plan.png" alt="" style="width: 80px; opacity: 0.5;">
                    <h2>Você ainda não possui uma assinatura ativa</h2>
                    <p>Escolha um plano profissional para começar a vender seus produtos hoje mesmo!</p>
                    <a href="escolher_plano.php" class="btn-save" style="text-decoration: none; display: inline-block; background: #009ee3;">Ver Planos Disponíveis</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>