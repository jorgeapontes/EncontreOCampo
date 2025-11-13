<?php
// src/vendedor/dashboard.php
require_once 'auth.php'; // Inclui a proteção de acesso e carrega os dados do vendedor

// Lógica para buscar os anúncios ATIVOS do vendedor
$anuncios = [];

// CORREÇÃO: 'fruta' foi substituído por 'nome' e 'quantidade' por 'estoque'
$query_anuncios = "SELECT id, nome, estoque, preco, status, data_criacao 
                   FROM produtos 
                   WHERE vendedor_id = :vendedor_id 
                   AND status = 'ativo' 
                   ORDER BY data_criacao DESC";
                   
$stmt_anuncios = $db->prepare($query_anuncios);
$stmt_anuncios->bindParam(':vendedor_id', $vendedor['id']); // Usa o ID da tabela 'vendedores'
$stmt_anuncios->execute(); // A linha 14 que causava o erro agora está correta.
$anuncios = $stmt_anuncios->fetchAll(PDO::FETCH_ASSOC);

$total_anuncios = count($anuncios);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Vendedor - Encontre Ocampo</title>
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
            <a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Painel</a>
            <a href="anuncios.php" class="nav-link"><i class="fas fa-bullhorn"></i> Meus Anúncios</a>
            <a href="propostas.php" class="nav-link"><i class="fas fa-handshake"></i> Painel de Propostas</a>
            <a href="precos.php" class="nav-link"><i class="fas fa-chart-line"></i> Médias de Preços</a>
            <a href="perfil.php" class="nav-link"><i class="fas fa-user-circle"></i> Meu Perfil</a>
            <a href="../logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <h1>Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['vendedor_nome']); ?>!</h1>
        </header>

        <section class="info-cards">
            <div class="card">
                <i class="fas fa-bullhorn"></i>
                <h3>Anúncios Ativos</h3>
                <p><?php echo $total_anuncios; ?></p>
            </div>
            <div class="card">
                <i class="fas fa-handshake"></i>
                <h3>Propostas Pendentes</h3>
                <p>0</p>
            </div>
            <div class="card">
                <i class="fas fa-dollar-sign"></i>
                <h3>Vendas Mês</h3>
                <p>R$ 0,00</p>
            </div>
        </section>

        <section class="section-anuncios">
            <h2>Meus Anúncios Recentes (<?php echo $total_anuncios; ?>)</h2>
            <a href="anuncio_novo.php" class="cta-button"><i class="fas fa-plus-circle"></i> Novo Anúncio</a>
            
            <div class="tabela-anuncios">
                <?php if ($total_anuncios > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fruta/Produto</th>
                                <th>Estoque (Kg)</th>
                                <th>Preço/Kg</th>
                                <th>Status</th>
                                <th>Criação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($anuncios as $anuncio): ?>
                            <tr>
                                <td><?php echo $anuncio['id']; ?></td>
                                <td><?php echo htmlspecialchars($anuncio['nome']); ?></td>
                                <td><?php echo number_format($anuncio['estoque'], 0, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?></td>
                                <td><span class="status <?php echo $anuncio['status']; ?>"><?php echo ucfirst($anuncio['status']); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($anuncio['data_criacao'])); ?></td>
                                <td>
                                    <a href="anuncio_editar.php?id=<?php echo $anuncio['id']; ?>" class="action-btn edit" title="Editar"><i class="fas fa-edit"></i></a>
                                    <button class="action-btn delete" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">Você ainda não tem anúncios ativos. Crie seu primeiro anúncio!</p>
                <?php endif; ?>
            </div>
        </section>
        
    </div>
</body>
</html>