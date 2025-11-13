<?php
// src/vendedor/propostas.php
require_once 'auth.php'; // Inclui a proteção de acesso e carrega os dados do vendedor ($vendedor)

$mensagem_sucesso = '';
$mensagem_erro = '';
$vendedor_id_fk = $vendedor['id'];

// Lógica para buscar todas as propostas relacionadas aos anúncios deste vendedor
$propostas = [];
$query_propostas = "SELECT 
                        p.id AS proposta_id, 
                        p.preco_proposto, 
                        p.quantidade_proposta, 
                        p.status, 
                        p.data_proposta,
                        a.nome AS nome_produto, 
                        a.id AS anuncio_id,
                        u.nome AS nome_comprador, 
                        c.nome_comercial AS loja_comprador
                    FROM propostas_negociacao p 
                    JOIN produtos a ON p.produto_id = a.id
                    JOIN vendedores v ON a.vendedor_id = v.id
                    JOIN compradores c ON p.comprador_id = c.id
                    JOIN usuarios u ON c.usuario_id = u.id
                    WHERE a.vendedor_id = :vendedor_id_fk 
                    ORDER BY 
                        CASE p.status 
                            WHEN 'pendente' THEN 1
                            WHEN 'aceita' THEN 2
                            ELSE 3
                        END, 
                        p.data_proposta DESC";
                   
try {
    $stmt_propostas = $db->prepare($query_propostas);
    $stmt_propostas->bindParam(':vendedor_id_fk', $vendedor_id_fk);
    $stmt_propostas->execute();
    $propostas = $stmt_propostas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se a tabela 'propostas_negociacao' não existir, informa um erro
    if ($e->errorInfo[1] === 1146) {
        $mensagem_erro = "Erro: A tabela 'propostas_negociacao' não foi encontrada. Certifique-se de que ela está criada no banco de dados.";
    } else {
        $mensagem_erro = "Erro ao buscar propostas: " . $e->getMessage();
    }
}

$total_propostas = count($propostas);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Propostas - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h2>ENCONTRE OCAMPO</h2>
            <p>Vendedor</p>
        </div>
        <nav class="nav-menu">
            <a href="../../index.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
            <a href="dashboard.php" class="nav-link active"><i class="fas fa-desktop"></i>Painel</a>
            <a href="anuncios.php" class="nav-link"><i class="fas fa-bullhorn"></i> Meus Anúncios</a>
            <a href="propostas.php" class="nav-link active"><i class="fas fa-handshake"></i> Painel de Propostas</a>
            <a href="precos.php" class="nav-link"><i class="fas fa-chart-line"></i> Médias de Preços</a>
            <a href="perfil.php" class="nav-link"><i class="fas fa-user-circle"></i> Meu Perfil</a>
            <a href="../logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <h1>Painel de Propostas Recebidas</h1>
        </header>

        <section class="section-propostas">
            <h2>Negociações Ativas (<?php echo $total_propostas; ?>)</h2>

            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert" style="float: none; margin-bottom: 20px;"><?php echo $mensagem_erro; ?></div>
            <?php endif; ?>
            
            <div class="tabela-propostas">
                <?php if ($total_propostas > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Anúncio</th>
                                <th>Comprador</th>
                                <th>Qtd. Proposta (Kg)</th>
                                <th>Preço Proposto/Kg</th>
                                <th>Valor Total</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($propostas as $proposta): ?>
                            <tr>
                                <td><a href="anuncio_editar.php?id=<?php echo $proposta['anuncio_id']; ?>" title="Ver Anúncio"><?php echo htmlspecialchars($proposta['nome_produto']); ?></a></td>
                                <td><?php echo htmlspecialchars($proposta['loja_comprador'] ?? $proposta['nome_comprador']); ?></td>
                                <td><?php echo number_format($proposta['quantidade_proposta'], 0, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($proposta['preco_proposto'], 2, ',', '.'); ?></td>
                                <td>
                                    <strong>R$ <?php echo number_format($proposta['preco_proposto'] * $proposta['quantidade_proposta'], 2, ',', '.'); ?></strong>
                                </td>
                                <td><span class="status proposta-status-<?php echo strtolower($proposta['status']); ?>"><?php echo ucfirst($proposta['status']); ?></span></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($proposta['data_proposta'])); ?></td>
                                <td>
                                    <?php if ($proposta['status'] === 'pendente'): ?>
                                        <button class="action-btn accept" title="Aceitar Proposta"><i class="fas fa-check"></i></button>
                                        <button class="action-btn reject" title="Rejeitar Proposta"><i class="fas fa-times"></i></button>
                                        <button class="action-btn chat" title="Iniciar Negociação/Chat"><i class="fas fa-comment-dots"></i></button>
                                    <?php elseif ($proposta['status'] === 'aceita'): ?>
                                        <span class="status success-text"><i class="fas fa-shipping-fast"></i> Preparar Envio</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">Você não recebeu nenhuma proposta de compra ainda.</p>
                <?php endif; ?>
            </div>
        </section>
        
    </div>
</body>
</html>