<?php
// src/comprador/meus_chats.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar se está logado como comprador
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Comprador');

$database = new Database();
$conn = $database->getConnection();

// Verificar se tem filtro na URL
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todas';

// Buscar todas as conversas do comprador
try {
    $sql = "SELECT 
                cc.id AS conversa_id,
                cc.produto_id,
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                v.id AS vendedor_sistema_id,
                u.id AS vendedor_usuario_id,
                u.nome AS vendedor_nome,
                v.nome_comercial AS vendedor_nome_comercial,
                (SELECT COUNT(*) 
                 FROM chat_mensagens cm 
                 WHERE cm.conversa_id = cc.id 
                 AND cm.remetente_id != :usuario_id 
                 AND cm.lida = 0) AS mensagens_nao_lidas
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN vendedores v ON p.vendedor_id = v.id
            INNER JOIN usuarios u ON v.usuario_id = u.id
            WHERE cc.comprador_id = :usuario_id
            AND cc.status = 'ativo'
            ORDER BY cc.ultima_mensagem_data DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar conversas: " . $e->getMessage());
    $conversas = [];
}

// Contar totais
$total_conversas = count($conversas);
$total_nao_lidas = 0;
foreach ($conversas as $conversa) {
    $total_nao_lidas += $conversa['mensagens_nao_lidas'];
}

// Filtrar conversas se necessário
$conversas_filtradas = $conversas;
if ($filtro === 'nao-lidos') {
    $conversas_filtradas = array_filter($conversas, function($conv) {
        return $conv['mensagens_nao_lidas'] > 0;
    });
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Chats - Encontre o Campo</title>
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #2E7D32;
        }
        
        .logo img {
            width: 50px;
            height: 50px;
        }
        
        .logo h1 {
            font-size: 20px;
            font-weight: 700;
        }
        
        .logo h2 {
            font-size: 14px;
            font-weight: 400;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-link {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
        }
        
        .nav-link:hover {
            color: #2E7D32;
        }
        
        .nav-link.active {
            color: #2E7D32;
            font-weight: 700;
        }
        
        .exit-button {
            background: #dc3545;
            color: white !important;
            padding: 8px 20px;
            border-radius: 5px;
        }
        
        .exit-button:hover {
            background: #c82333;
        }
        
        .notificacao-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }
        
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
        }
        
        .bar {
            width: 25px;
            height: 3px;
            background: #2E7D32;
            margin: 3px 0;
            transition: 0.3s;
        }
        
        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            color: #2E7D32;
            font-size: 28px;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        
        .stats-bar {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-item i {
            color: #2E7D32;
            font-size: 20px;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: #666;
        }
        
        .stat-item .value {
            font-size: 20px;
            font-weight: 700;
            color: #2E7D32;
        }
        
        /* Conversas Container */
        .conversas-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .conversas-header {
            padding: 1.5rem 2rem;
            background: #f9f9f9;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .conversas-header h2 {
            font-size: 20px;
            color: #333;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .filter-btn:hover {
            border-color: #2E7D32;
            color: #2E7D32;
        }
        
        .filter-btn.active {
            background: #2E7D32;
            color: white;
            border-color: #2E7D32;
        }
        
        .conversas-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .conversa-card {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            transition: background 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .conversa-card:hover {
            background: #f9f9f9;
        }
        
        .conversa-card.nao-lida {
            background: #e8f5e9;
        }
        
        .produto-thumb {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .produto-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversa-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversa-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }
        
        .produto-nome-principal {
            font-weight: 700;
            color: #333;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge-novo {
            background: #dc3545;
            color: white;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .conversa-data {
            font-size: 13px;
            color: #999;
        }
        
        .vendedor-info {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .produto-preco {
            color: #2E7D32;
            font-weight: 600;
        }
        
        .ultima-mensagem {
            font-size: 14px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 500px;
        }
        
        .conversa-card.nao-lida .ultima-mensagem {
            font-weight: 600;
            color: #333;
        }
        
        .conversa-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }
        
        .btn-chat {
            background: #2E7D32;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .btn-chat:hover {
            background: #1B5E20;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.3);
        }
        
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            color: #999;
        }
        
        .empty-state i {
            font-size: 80px;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            font-size: 14px;
            margin-bottom: 1.5rem;
        }
        
        .btn-anuncios {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #2E7D32;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-anuncios:hover {
            background: #1B5E20;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.3);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hamburger {
                display: flex;
            }
            
            .nav-menu {
                position: fixed;
                left: -100%;
                top: 70px;
                flex-direction: column;
                background: white;
                width: 100%;
                text-align: center;
                transition: 0.3s;
                box-shadow: 0 10px 27px rgba(0,0,0,0.05);
                padding: 2rem 0;
            }
            
            .nav-menu.active {
                left: 0;
            }
            
            .conversa-card {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .conversa-top {
                flex-direction: column;
                gap: 5px;
            }
            
            .conversa-actions {
                width: 100%;
                align-items: stretch;
            }
            
            .btn-chat {
                justify-content: center;
            }
            
            .ultima-mensagem {
                max-width: 100%;
            }
            
            .stats-bar {
                flex-wrap: wrap;
            }
        }
    </style>
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
                        <a href="meus_chats.php" class="nav-link active">Chats</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link">
                            <i class="fas fa-bell"></i>
                            <?php
                            $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                            $stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
                            $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                            $stmt_nao_lidas->execute();
                            $total_notif = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                            if ($total_notif > 0) {
                                echo '<span class="notificacao-badge">'.$total_notif.'</span>';
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link exit-button">Sair</a>
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

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-comments"></i> Minhas Conversas</h1>
            <p>Gerencie seus chats com vendedores sobre produtos de interesse</p>
            
            <div class="stats-bar">
                <div class="stat-item">
                    <i class="fas fa-comments"></i>
                    <div>
                        <div class="label">Total de Conversas</div>
                        <div class="value"><?php echo $total_conversas; ?></div>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <div class="label">Mensagens Novas</div>
                        <div class="value"><?php echo $total_nao_lidas; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="conversas-container">
            <div class="conversas-header">
                <h2>Conversas Recentes</h2>
                <div class="filter-buttons">
                    <a href="meus_chats.php" class="filter-btn <?php echo $filtro === 'todas' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Todas
                    </a>
                    <a href="meus_chats.php?filtro=nao-lidos" class="filter-btn <?php echo $filtro === 'nao-lidos' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i> Não Lidas
                    </a>
                </div>
            </div>
            
            <div class="conversas-list">
                <?php if (count($conversas_filtradas) > 0): ?>
                    <?php foreach ($conversas_filtradas as $conversa): 
                        $imagem_produto = $conversa['produto_imagem'] ? htmlspecialchars($conversa['produto_imagem']) : '../../img/placeholder.png';
                        $tem_nao_lidas = $conversa['mensagens_nao_lidas'] > 0;
                        $data_formatada = $conversa['ultima_mensagem_data'] ? date('d/m/Y H:i', strtotime($conversa['ultima_mensagem_data'])) : '';
                        $vendedor_display = $conversa['vendedor_nome_comercial'] ?: $conversa['vendedor_nome'];
                    ?>
                        <a href="../chat/chat.php?produto_id=<?php echo $conversa['produto_id']; ?>&ref=meus_chats" 
                           class="conversa-card <?php echo $tem_nao_lidas ? 'nao-lida' : ''; ?>">
                            <div class="produto-thumb">
                                <img src="<?php echo $imagem_produto; ?>" alt="<?php echo htmlspecialchars($conversa['produto_nome']); ?>">
                            </div>
                            
                            <div class="conversa-info">
                                <div class="conversa-top">
                                    <div class="produto-nome-principal">
                                        <i class="fas fa-box"></i>
                                        <?php echo htmlspecialchars($conversa['produto_nome']); ?>
                                        <?php if ($tem_nao_lidas): ?>
                                            <span class="badge-novo"><?php echo $conversa['mensagens_nao_lidas']; ?> nova<?php echo $conversa['mensagens_nao_lidas'] > 1 ? 's' : ''; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversa-data">
                                        <i class="far fa-clock"></i> <?php echo $data_formatada; ?>
                                    </div>
                                </div>
                                
                                <div class="vendedor-info">
                                    <i class="fas fa-store"></i>
                                    Vendedor: <?php echo htmlspecialchars($vendedor_display); ?>
                                    <span class="produto-preco">- R$ <?php echo number_format($conversa['produto_preco'], 2, ',', '.'); ?></span>
                                </div>
                                
                                <?php if ($conversa['ultima_mensagem']): ?>
                                    <div class="ultima-mensagem">
                                        <i class="fas fa-comment"></i>
                                        <?php echo htmlspecialchars($conversa['ultima_mensagem']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="conversa-actions">
                                <div class="btn-chat">
                                    <i class="fas fa-comments"></i>
                                    Abrir Chat
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Nenhuma conversa <?php echo $filtro === 'nao-lidos' ? 'não lida' : 'encontrada'; ?></h3>
                        <p>
                            <?php if ($filtro === 'nao-lidos'): ?>
                                Você não tem mensagens novas no momento.
                            <?php else: ?>
                                Quando você conversar com vendedores sobre produtos, as conversas aparecerão aqui.
                            <?php endif; ?>
                        </p>
                        <?php if ($filtro === 'todas'): ?>
                            <a href="../anuncios.php" class="btn-anuncios">
                                <i class="fas fa-shopping-bag"></i> Ver Anúncios
                            </a>
                        <?php else: ?>
                            <a href="meus_chats.php" class="btn-anuncios">
                                <i class="fas fa-list"></i> Ver Todas as Conversas
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Menu Hamburger
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        if (hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });

            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            }));
        }
    </script>
</body>
</html>