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

// NOVO: CONTADOR DE PROPOSTAS PENDENTES
$total_propostas_pendentes = 0;

try {
    $query_propostas = "SELECT COUNT(pn.id) as total_pendentes
                        FROM propostas_negociacao pn
                        JOIN produtos p ON pn.produto_id = p.id
                        WHERE p.vendedor_id = :vendedor_id 
                        AND pn.status = 'pendente'";
                        
    $stmt_propostas = $db->prepare($query_propostas);
    $stmt_propostas->bindParam(':vendedor_id', $vendedor['id']);
    $stmt_propostas->execute();
    $resultado = $stmt_propostas->fetch(PDO::FETCH_ASSOC);
    
    $total_propostas_pendentes = $resultado['total_pendentes'] ?? 0;
    
} catch (PDOException $e) {
    // Em caso de erro, mantém o valor 0 e loga o erro
    error_log("Erro ao contar propostas pendentes: " . $e->getMessage());
    $total_propostas_pendentes = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Vendedor - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/vendedor/dashboard.css">
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
                        <a href="" class="nav-link active">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link exit-button no-underline"> Sair </a>
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
                <h1>Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['vendedor_nome']); ?>!</h1>
            </center>
        </section>

        <section class="info-cards">
            <a href="anuncios.php">
            <div class="card">
                    <i class="fas fa-bullhorn"></i>
                    <h3>Anúncios Ativos</h3>
                    <p><?php echo $total_anuncios; ?></p>
            </div>
            </a>
            <a href="propostas.php">
            <div class="card">
                    <i class="fas fa-handshake"></i>
                    <h3>Propostas Pendentes</h3>
                    <p><?php echo $total_propostas_pendentes; ?></p>
            </div>
            </a>
            <a href="vendas.php">
                <div class="card">
                        <i class="fas fa-dollar-sign"></i>
                        <h3>Minhas vendas</h3>
                        <p>Ver</p>
                </div>
            </a>
        </section>

        <section class="section-anuncios">
            <div id="header">
                <h2>Anúncios ativos (<?php echo $total_anuncios; ?>)</h2>
                <a href="anuncio_novo.php" class="cta-button"><i class="fas fa-plus"></i> Novo Anúncio</a>
            </div>
            
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
                                    <form method="POST" action="processar_anuncio.php" style="display: inline;">
                                        <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                                        <button type="submit" name="acao" value="deletar" class="action-btn delete" title="Excluir Definitivamente" onclick="return confirm('Tem certeza que deseja DELETAR este anúncio? Esta ação é irreversível.');">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
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

    <script>
        // Script para menu hamburger (copiado do index.php)
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