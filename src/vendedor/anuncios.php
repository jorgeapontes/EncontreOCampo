<?php
// src/vendedor/anuncios.php
require_once 'auth.php'; // Inclui a proteção de acesso e carrega os dados do vendedor

$mensagem_sucesso = $_SESSION['mensagem_anuncio_sucesso'] ?? '';
unset($_SESSION['mensagem_anuncio_sucesso']); // Limpa a mensagem após exibir

// Lógica para buscar TODOS os anúncios (independente do status) do vendedor
$anuncios = [];
$query_anuncios = "SELECT id, nome, estoque, preco, status, data_criacao, data_atualizacao
                   FROM produtos 
                   WHERE vendedor_id = :vendedor_id 
                   ORDER BY status DESC, data_criacao DESC"; // Prioriza status ativos no topo
                   
$stmt_anuncios = $db->prepare($query_anuncios);
$stmt_anuncios->bindParam(':vendedor_id', $vendedor['id']); // ID da tabela 'vendedores'
$stmt_anuncios->execute();
$anuncios = $stmt_anuncios->fetchAll(PDO::FETCH_ASSOC);

$total_anuncios = count($anuncios);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Anúncios - Vendedor</title>
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
            <a href="anuncios.php" class="nav-link active"><i class="fas fa-bullhorn"></i> Meus Anúncios</a>
            <a href="propostas.php" class="nav-link"><i class="fas fa-handshake"></i> Painel de Propostas</a>
            <a href="precos.php" class="nav-link"><i class="fas fa-chart-line"></i> Médias de Preços</a>
            <a href="perfil.php" class="nav-link"><i class="fas fa-user-circle"></i> Meu Perfil</a>
            <a href="../logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="header">
            <h1>Gestão de Anúncios</h1>
        </header>

        <section class="section-anuncios">
            <h2>Todos os Meus Anúncios (<?php echo $total_anuncios; ?>)</h2>
            <a href="anuncio_novo.php" class="cta-button"><i class="fas fa-plus-circle"></i> Novo Anúncio</a>
            
            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="alert success-alert" style="float: none; margin-bottom: 20px;"><?php echo $mensagem_sucesso; ?></div>
            <?php endif; ?>

            <div class="tabela-anuncios full-list">
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
                                <th>Última Atualização</th>
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
                                <td><?php echo $anuncio['data_atualizacao'] ? date('d/m/Y H:i', strtotime($anuncio['data_atualizacao'])) : '-'; ?></td>
                                <td>
                                    <a href="anuncio_editar.php?id=<?php echo $anuncio['id']; ?>" class="action-btn edit" title="Editar"><i class="fas fa-edit"></i></a>
                                    <form method="POST" action="processar_anuncio.php" style="display: inline;">
                                        <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                                        <?php if ($anuncio['status'] === 'ativo'): ?>
                                            <button type="submit" name="acao" value="inativar" class="action-btn inactive" title="Inativar/Pausar"><i class="fas fa-pause-circle"></i></button>
                                        <?php else: ?>
                                            <button type="submit" name="acao" value="ativar" class="action-btn active-icon" title="Ativar"><i class="fas fa-play-circle"></i></button>
                                        <?php endif; ?>
                                        <button type="submit" name="acao" value="deletar" class="action-btn delete" title="Excluir Definitivamente" onclick="return confirm('Tem certeza que deseja DELETAR este anúncio? Esta ação é irreversível.');"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="empty-state">Você ainda não possui nenhum anúncio cadastrado.</p>
                <?php endif; ?>
            </div>
        </section>
        
    </div>
</body>
</html>