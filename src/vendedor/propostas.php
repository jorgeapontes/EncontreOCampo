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
    <link rel="stylesheet" href="../css/vendedor/dashboard.css">
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
    /* Estilos da Listagem de Propostas */
    .propostas-container {
        margin-top: 80px;
        padding: 20px;
        max-width: 1200px; /* Alterado de 1100px para 1200px para igualar ao dashboard */
        margin-left: auto;
        margin-right: auto;
    }

    .proposta-card {
        background-color: var(--white);
        border-left: 5px solid var(--secondary-color);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        width: 100%; /* Garante que ocupe toda a largura disponível */
    }
    
    .proposta-card.status-accepted { border-left-color: var(--primary-color); }
    .proposta-card.status-rejected { border-left-color: #E53935; }
    .proposta-card.status-negotiation { border-left-color: #2196F3; }
    .proposta-card.status-pending { border-left-color: #FF9800; }

    .proposta-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px dashed #eee;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    .proposta-header h3 {
        margin: 0;
        color: var(--dark-color);
        font-size: 1.5em;
    }

    .proposta-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .info-group p {
        margin-bottom: 5px;
        font-size: 0.95em;
    }

    .info-group p strong {
        display: block;
        font-weight: bold;
        color: var(--text-color);
        margin-bottom: 3px;
        font-size: 1.05em;
    }
    
    .info-group p span {
        color: var(--secondary-color);
        font-weight: 600;
    }
    
    /* Status Badges */
    .status-badge {
        font-weight: bold;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.9em;
        text-transform: uppercase;
    }

    .status-pending { background-color: #FFF3E0; color: #FF9800; border: 1px solid #FF9800; }
    .status-accepted { background-color: #E8F5E9; color: #4CAF50; border: 1px solid #4CAF50; }
    .status-rejected { background-color: #FFEBEE; color: #F44336; border: 1px solid #F44336; }
    .status-negotiation { background-color: #E3F2FD; color: #2196F3; border: 1px solid #2196F3; }

    .proposta-actions {
        text-align: right;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
    }
    
    .btn-action {
        background-color: var(--primary-color);
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        transition: background-color 0.3s;
        margin-left: 10px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .btn-action:hover {
        background-color: var(--primary-dark);
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        background-color: var(--white);
        border-radius: 8px;
        border: 1px dashed #ccc;
        margin-top: 30px;
    }

    @media (max-width: 768px) {
        .proposta-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        .proposta-info {
            grid-template-columns: 1fr;
        }
        .proposta-actions {
            text-align: center;
        }
        .btn-action {
            display: block;
            width: 100%;
            margin: 5px 0 0;
        }
    }
</style>
</head>
<body>
    <!-- Nova Navbar no estilo do index.php -->
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link">Meus Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="propostas.php" class="nav-link active">Propostas</a>
                    </li>
                    <li class="nav-item">
                        <a href="precos.php" class="nav-link">Médias de Preços</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link login-button no-underline">Sair</a>
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

    <main class="propostas-container">
        <header class="header">
            <h1>Propostas de Negociação Recebidas</h1>
            <p>Gerencie as propostas de compra enviadas para os seus produtos anunciados.</p>
        </header>

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
                                Proposta de **<?php echo htmlspecialchars($proposta['nome_comprador']); ?>**
                            </h3>
                            <span class="status-badge <?php echo $status_info['class']; ?>">
                                <?php echo $status_info['text']; ?>
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
                            <a href="detalhes_proposta.php?id=<?php echo $proposta['proposta_id']; ?>" class="btn-action">
                                <i class="fas fa-search"></i>
                                Ver Detalhes / Negociar
                            </a>
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