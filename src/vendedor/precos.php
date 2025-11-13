<?php
// src/vendedor/precos.php
require_once 'auth.php'; // Inclui a proteção de acesso e carrega os dados do vendedor ($vendedor)

$vendedor_id_fk = $vendedor['id'];
$analises_preco = [];
$mensagem_erro = '';

// Lógica para buscar a média de preços dos produtos DA CONCORRÊNCIA
$query_analise = "
    SELECT 
        nome,
        categoria,
        AVG(preco) AS preco_medio,
        MIN(preco) AS preco_min,
        MAX(preco) AS preco_max,
        COUNT(id) AS total_anuncios_mercado
    FROM produtos
    WHERE vendedor_id != :vendedor_id_fk  -- EXCLUI os anúncios do vendedor logado
    AND status = 'ativo'               -- Considera apenas anúncios ativos no mercado
    GROUP BY nome, categoria
    ORDER BY nome ASC
";
                   
try {
    $stmt_analise = $db->prepare($query_analise);
    $stmt_analise->bindParam(':vendedor_id_fk', $vendedor_id_fk);
    $stmt_analise->execute();
    $analises_preco = $stmt_analise->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem_erro = "Erro ao buscar dados de análise: " . $e->getMessage();
}

$total_produtos_analisados = count($analises_preco);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Médias de Preços - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>ENCONTRE OCAMPO</h2>
            <p>Vendedor</p>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Painel</a>
            <a href="anuncios.php" class="nav-link"><i class="fas fa-bullhorn"></i> Meus Anúncios</a>
            <a href="propostas.php" class="nav-link"><i class="fas fa-handshake"></i> Painel de Propostas</a>
            <a href="precos.php" class="nav-link active"><i class="fas fa-chart-line"></i> Médias de Preços</a>
            <a href="perfil.php" class="nav-link"><i class="fas fa-user-circle"></i> Meu Perfil</a>
            <a href="../logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <h1>Médias de Preços (Análise da Concorrência)</h1>
        </header>

        <section class="info-card-large">
            <p>Esta seção mostra a análise de preços dos **seus concorrentes** na plataforma. Utilize esses dados para precificar seus produtos de forma estratégica e competitiva.</p>
        </section>

        <section class="section-analise">
            <h2>Análise por Produto (<?php echo $total_produtos_analisados; ?> itens no mercado)</h2>

            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert" style="float: none; margin-bottom: 20px;"><?php echo $mensagem_erro; ?></div>
            <?php endif; ?>
            
            <div class="tabela-analise">
                <?php if ($total_produtos_analisados > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Categoria</th>
                                <th>Total Anúncios</th>
                                <th>Preço Mínimo/Kg</th>
                                <th>Preço Médio/Kg</th>
                                <th>Preço Máximo/Kg</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analises_preco as $analise): ?>
                            <tr>
                                <td><i class="fas fa-seedling" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($analise['nome']); ?></td>
                                <td><?php echo htmlspecialchars($analise['categoria']); ?></td>
                                <td><?php echo $analise['total_anuncios_mercado']; ?></td>
                                <td>R$ <?php echo number_format($analise['preco_min'], 2, ',', '.'); ?></td>
                                <td style="font-weight: bold; color: var(--dark-color);">R$ <?php echo number_format($analise['preco_medio'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($analise['preco_max'], 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">Não há outros vendedores ativos anunciando produtos para realizar a análise de preços.</p>
                <?php endif; ?>
            </div>
        </section>
        
    </div>
</body>
</html>