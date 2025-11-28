<?php
// src/vendedor/propostas.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

$usuario_id = $_SESSION['usuario_id']; // ID do usuário logado na tabela 'usuarios'
$database = new Database();
$conn = $database->getConnection();
$propostas = [];
$vendedor_id = null;


// 2. OBTENDO O ID DO VENDEDOR (ID da tabela 'vendedores')
try {
    $sql_vendedor = "SELECT id FROM vendedores WHERE usuario_id = :usuario_id";
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $resultado_vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

    if ($resultado_vendedor) {
        $vendedor_id = $resultado_vendedor['id'];
    } else {
        die("Erro: ID de vendedor não encontrado.");
    }
} catch (PDOException $e) {
    die("Erro ao buscar ID do vendedor: " . $e->getMessage());
}

// 3. BUSCA DAS PROPOSTAS RECEBIDAS
try {
    // Busca todas as propostas para os produtos DESTE vendedor
    $sql = "SELECT 
                pn.id AS proposta_id,
                pn.data_proposta,
                pn.preco_proposto,
                pn.quantidade_proposta,
                pn.status,
                p.nome AS produto_nome,
                p.unidade_medida,
                p.preco AS preco_anuncio_original,
                u.nome AS nome_comprador
            FROM propostas_negociacao pn
            JOIN produtos p ON pn.produto_id = p.id
            JOIN vendedores v ON p.vendedor_id = v.id
            JOIN compradores c ON pn.comprador_id = c.id
            JOIN usuarios u ON c.usuario_id = u.id
            WHERE v.id = :vendedor_id
            ORDER BY pn.data_proposta DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt->execute();
    $propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar propostas: " . $e->getMessage()); 
}

// Função para traduzir o status
function formatarStatus($status) {
    $map = [
        'pendente' => ['text' => 'Pendente', 'class' => 'status-pending'],
        'aceita' => ['text' => 'Aceita', 'class' => 'status-accepted'],
        'recusada' => ['text' => 'Recusada', 'class' => 'status-rejected'],
        'negociacao' => ['text' => 'Em Negociação', 'class' => 'status-negotiation'],
    ];
    return $map[$status] ?? ['text' => ucfirst($status), 'class' => 'status-default'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propostas Recebidas - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/propostas.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

    <main class="main-content">
        <center>
            <header class="header">
                <h1>Propostas de Negociação Recebidas</h1>
                <p>Gerencie as propostas de compra enviadas para os seus produtos anunciados.</p>
            </header>
        </center>

        <?php if (empty($propostas)): ?>
            <div class="empty-state">
                <h3>Nenhuma proposta recebida até o momento.</h3>
                <p>Verifique se seus <a href="anuncios.php">anúncios</a> estão ativos.</p>
            </div>
        <?php else: ?>
            <div class="propostas-list">
                <?php foreach ($propostas as $proposta): 
                    $status_info = formatarStatus($proposta['status']);
                ?>
                    <div class="proposta-card <?php echo $status_info['class']; ?>">
                        <div class="proposta-header">
                            <h3>
                                Proposta de <?php echo htmlspecialchars($proposta['nome_comprador']); ?>
                            </h3>
                            <span class="status-badge <?php echo $status_info['class']."-btn" ?>">
                                <?php echo $status_info['text'] ?>
                            </span>
                        </div>
                        
                        <div class="proposta-info">
                            <div class="info-group">
                                <p><strong>Produto:</strong> <?php echo htmlspecialchars($proposta['produto_nome']); ?></p>
                                <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($proposta['data_proposta'])); ?></p>
                            </div>
                            <div class="info-group">
                                <p><strong>Proposto:</strong> <span><?php echo 'R$ ' . number_format($proposta['preco_proposto'], 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></span></p>
                                <p><strong>Qtde Proposta:</strong> <?php echo htmlspecialchars($proposta['quantidade_proposta']) . ' ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                            </div>
                            <div class="info-group">
                                <p><strong>Seu Preço Original:</strong> <?php echo 'R$ ' . number_format($proposta['preco_anuncio_original'], 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                            </div>
                        </div>
                        
                        <div class="proposta-actions">
                            <?php if ($proposta['status'] == 'aceita' || $proposta['status'] == 'recusada'): ?>
                                <!-- Para propostas aceitas ou recusadas - apenas Ver Detalhes -->
                                <a href="detalhes_proposta.php?id=<?php echo $proposta['proposta_id']; ?>" class="btn-action <?= $status_info['class'] ?>">
                                    <i class="fas fa-eye"></i>
                                    Ver Detalhes
                                </a>
                            <?php else: ?>
                                <!-- Para propostas pendentes ou em negociação - Ver Detalhes / Negociar -->
                                <a href="detalhes_proposta.php?id=<?php echo $proposta['proposta_id']; ?>" class="btn-action <?= $status_info['class'] ?>">
                                    <i class="fas fa-search"></i>
                                    Ver Detalhes / Negociar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

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