<?php
// src/vendedor/vendas.php
require_once 'auth.php';

// Consulta para obter as vendas (propostas aceitas) do vendedor - ATUALIZADA
$vendas = [];
$total_vendas = 0;
$valor_total_vendas = 0;

// Consulta para obter as vendas - CORRIGIDA
try {
    $query_vendas = "SELECT 
                        pn.id,
                        pn.proposta_comprador_id,
                        pn.produto_id,
                        p.nome as produto_nome,
                        c.nome_comercial as comprador_nome,
                        pn.quantidade_final as quantidade_vendida,
                        pn.preco_final as preco_unitario,
                        (pn.quantidade_final * pn.preco_final) as valor_total,
                        pn.data_criacao as data_venda,
                        pn.status
                    FROM propostas_negociacao pn
                    INNER JOIN produtos p ON pn.produto_id = p.id
                    INNER JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
                    INNER JOIN compradores c ON pc.comprador_id = c.id
                    WHERE p.vendedor_id = :vendedor_id 
                    AND pn.status IN ('aceita', 'finalizada')
                    ORDER BY pn.data_criacao DESC";
    
    $stmt_vendas = $db->prepare($query_vendas);
    $stmt_vendas->bindParam(':vendedor_id', $vendedor['id']);
    $stmt_vendas->execute();
    $vendas = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais
    $total_vendas = count($vendas);
    foreach ($vendas as $venda) {
        $valor_total_vendas += $venda['valor_total'];
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar vendas: " . $e->getMessage());
    $vendas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Vendas - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/vendedor/vendas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <img src="../../img/logo-nova.png" alt="Logo">
                    <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            // Contar notificações não lidas
                            if (isset($_SESSION['usuario_id'])) {
                                $database = new Database();
                                $conn = $database->getConnection();
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) {
                                    echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                                }
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link exit-button no-underline">Sair</a>
                    </li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <br>
    <div class="main-content">
        <section class="header">
            <center>
                <h1>Minhas Vendas</h1>
            </center>
        </section>

        <section class="info-cards">
            <div class="cardbox">
                <div class="card">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Total de Vendas</h3>
                    <p><?php echo $total_vendas; ?></p>
                </div>
            </div>
            <div class="cardbox">
                <div class="card">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>Valor Total</h3>
                    <p>R$ <?php echo number_format($valor_total_vendas, 2, ',', '.'); ?></p>
                </div>
            </div>
            <div class="cardbox">
                <div class="card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Status</h3>
                    <p>Ativas</p>
                </div>
            </div>
        </section>

        <section class="section-anuncios">
            <div id="header">
                <h2>Histórico de Vendas (<?php echo $total_vendas; ?>)</h2>
            </div>
            
            <div class="tabela-anuncios">
                <?php if ($total_vendas > 0): ?>
                    <!-- Na seção da tabela, atualize as colunas: -->
                    <table>
                        <thead>
                            <tr>
                                <th>ID Venda</th>
                                <th>Produto</th>
                                <th>Comprador</th>
                                <th>Quantidade</th>
                                <th>Preço Unit.</th>
                                <th>Valor Total</th>
                                <th>Data</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendas as $venda): ?>
                            <tr>
                                <td><?php echo $venda['id']; ?></td>
                                <td><?php echo htmlspecialchars($venda['produto_nome']); ?></td>
                                <td><?php echo htmlspecialchars($venda['comprador_nome']); ?></td>
                                <td><?php echo number_format($venda['quantidade_vendida'], 0, ',', '.'); ?> Kg</td>
                                <td>R$ <?php echo number_format($venda['preco_unitario'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($venda['data_venda'])); ?></td>
                                <td>
                                    <span class="status <?php echo $venda['status']; ?>">
                                        <?php 
                                            if ($venda['status'] == 'aceita') echo 'Aceita';
                                            elseif ($venda['status'] == 'finalizada') echo 'Finalizada';
                                            else echo ucfirst($venda['status']); 
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">Você ainda não tem vendas realizadas. As propostas aceitas aparecerão aqui!</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        // Script para menu hamburger
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        hamburger.addEventListener("click", () => {
            hamburger.classList.toggle("active");
            navMenu.classList.toggle("active");
        });

        // Fechar menu mobile ao clicar em um link
        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));
    </script>
</body>
</html>