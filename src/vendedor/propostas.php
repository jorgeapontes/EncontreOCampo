<?php
// src/vendedor/propostas.php - ATUALIZADO

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();
$propostas = [];
$vendedor_id = null;

// 2. OBTENDO O ID DO VENDEDOR
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

// 3. BUSCA DAS PROPOSTAS RECEBIDAS - ATUALIZADA COM DEBUG
try {
    $sql = "SELECT 
                pc.id AS proposta_id,
                pc.data_proposta,
                pc.preco_proposto AS preco_comprador,
                pc.quantidade_proposta AS quantidade_comprador,
                pc.status AS proposta_status_comprador,  -- Status da proposta do comprador
                pc.condicoes_compra,
                pn.id AS negociacao_id,
                pn.status AS negociacao_status,
                pn.produto_id,
                p.nome AS produto_nome,
                p.unidade_medida,
                p.preco AS preco_anuncio_original,
                p.vendedor_id,
                u.nome AS nome_comprador,
                -- Subconsulta para obter a última contraproposta do vendedor
                (SELECT pv.preco_proposto 
                 FROM propostas_vendedor pv 
                 WHERE pv.proposta_comprador_id = pc.id 
                 ORDER BY pv.data_contra_proposta DESC LIMIT 1) AS preco_vendedor,
                (SELECT pv.quantidade_proposta 
                 FROM propostas_vendedor pv 
                 WHERE pv.proposta_comprador_id = pc.id 
                 ORDER BY pv.data_contra_proposta DESC LIMIT 1) AS quantidade_vendedor,
                (SELECT pv.data_contra_proposta 
                 FROM propostas_vendedor pv 
                 WHERE pv.proposta_comprador_id = pc.id 
                 ORDER BY pv.data_contra_proposta DESC LIMIT 1) AS data_contraproposta
            FROM propostas_comprador pc
            JOIN propostas_negociacao pn ON pc.id = pn.proposta_comprador_id
            JOIN produtos p ON pn.produto_id = p.id
            JOIN compradores c ON pc.comprador_id = c.id
            JOIN usuarios u ON c.usuario_id = u.id
            WHERE p.vendedor_id = :vendedor_id
            ORDER BY pc.data_proposta DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt->execute();
    $propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erro ao carregar propostas: " . $e->getMessage()); 
}

// Função para traduzir o status - ATUALIZADA para considerar ambos os status
function formatarStatusVendedor($status_negociacao, $status_comprador = null) {
    // Se o status da negociação for 'aceita' ou 'recusada', usa esses status
    if (in_array($status_negociacao, ['aceita', 'recusada'])) {
        $map = [
            'aceita' => ['text' => 'Aceita', 'class' => 'status-accepted'],
            'recusada' => ['text' => 'Recusada', 'class' => 'status-rejected']
        ];
        return $map[$status_negociacao] ?? ['text' => ucfirst($status_negociacao), 'class' => 'status-default'];
    }
    
    // Se o status da negociação for 'negociacao', verifica o status do comprador
    if ($status_negociacao === 'negociacao') {
        if ($status_comprador === 'enviada') {
            return ['text' => 'Nova Proposta', 'class' => 'status-pending'];
        } elseif ($status_comprador === 'pendente') {
            return ['text' => 'Aguardando Cliente', 'class' => 'status-negotiation'];
        }
    }
    
    // Fallback
    return ['text' => ucfirst($status_negociacao), 'class' => 'status-default'];
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
                    $status_info = formatarStatusVendedor($proposta['negociacao_status'], $proposta['proposta_status_comprador']);
                    
                    // Verificar se existe contraproposta do vendedor e se o status do comprador é 'pendente'
                    $temContraproposta = !empty($proposta['preco_vendedor']) && $proposta['proposta_status_comprador'] === 'pendente';
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
                                <?php if ($temContraproposta): ?>
                                    <!-- Exibir dados da contraproposta do vendedor -->
                                    <p><strong>Proposto:</strong> <span>R$ <?php echo number_format($proposta['preco_vendedor'], 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></span></p>
                                    <p><strong>Qtde Proposta:</strong> <?php echo htmlspecialchars($proposta['quantidade_vendedor']) . ' ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                                <?php else: ?>
                                    <!-- Exibir dados da proposta do comprador -->
                                    <p><strong>Proposto:</strong> <span>R$ <?php echo number_format($proposta['preco_comprador'], 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></span></p>
                                    <p><strong>Qtde Proposta:</strong> <?php echo htmlspecialchars($proposta['quantidade_comprador']) . ' ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="info-group">
                                <p><strong>Seu Preço Original:</strong> <?php echo 'R$ ' . number_format($proposta['preco_anuncio_original'], 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                            </div>
                        </div>
                        
                        <div class="proposta-actions">
                            <?php if ($proposta['negociacao_status'] == 'aceita' || $proposta['negociacao_status'] == 'recusada'): ?>
                                <a href="detalhes_proposta.php?id=<?php echo $proposta['proposta_id']; ?>" class="btn-action <?= $status_info['class'] ?>">
                                    <i class="fas fa-eye"></i>
                                    Ver Detalhes
                                </a>
                            <?php else: ?>
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