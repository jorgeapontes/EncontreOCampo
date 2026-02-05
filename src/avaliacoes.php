<?php
// avaliacoes.php - Página reutilizável para exibir todas as avaliações
session_start();
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissions.php';

// Verificar acesso
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
}

// Verificar parâmetros obrigatórios
if (!isset($_GET['tipo']) || !isset($_GET['id'])) {
    header("Location: dashboard.php?erro=" . urlencode("Parâmetros inválidos."));
    exit();
}

$tipo_avaliacao = $_GET['tipo']; // 'produto', 'vendedor', 'comprador', 'transportador'
$id_referencia = (int)$_GET['id']; // ID do produto, vendedor, etc.
$pagina_titulo = '';
$subtitulo = '';
$mostrar_botao_avaliar = false;

$database = new Database();
$conn = $database->getConnection();

// Configurar título e consulta baseado no tipo
switch ($tipo_avaliacao) {
    case 'produto':
        // Buscar informações do produto
        try {
            $sql_produto = "SELECT p.nome, p.id as produto_id, v.nome_comercial, v.id as vendedor_id FROM produtos p 
                           JOIN vendedores v ON p.vendedor_id = v.id 
                           WHERE p.id = :id AND p.status = 'ativo'";
            $stmt_produto = $conn->prepare($sql_produto);
            $stmt_produto->bindParam(':id', $id_referencia, PDO::PARAM_INT);
            $stmt_produto->execute();
            
            if ($produto = $stmt_produto->fetch(PDO::FETCH_ASSOC)) {
                $pagina_titulo = "Avaliações do Produto: " . htmlspecialchars($produto['nome']);
                $subtitulo = "Vendedor: " . htmlspecialchars($produto['nome_comercial']);
                $produto_id = $produto['produto_id'];
                $vendedor_id = $produto['vendedor_id'];
                
                // Verificar se o usuário atual pode avaliar este produto
                if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo' && $_SESSION['usuario_tipo'] === 'comprador') {
                    $usuario_logado = $_SESSION['usuario_id'];
                    
                    // Verificar se o usuário já comprou este produto (mesma lógica do view_ad.php)
                    $sql_check = "SELECT p.opcao_frete, p.comprador_id FROM propostas p 
                                 LEFT JOIN compradores c ON p.comprador_id = c.id 
                                 WHERE p.produto_id = :produto_id 
                                 AND (p.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) 
                                 AND p.status = 'aceita' 
                                 ORDER BY p.data_inicio DESC LIMIT 1";
                    $stc = $conn->prepare($sql_check);
                    $stc->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                    $stc->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                    $stc->execute();
                    $rowc = $stc->fetch(PDO::FETCH_ASSOC);
                    
                    if ($rowc) {
                        $op = $rowc['opcao_frete'] ?? null;
                        if (in_array($op, ['vendedor','comprador'])) {
                            $mostrar_botao_avaliar = true;
                        } elseif ($op === 'entregador') {
                            // Verificar se houve entrega concluída
                            $sql_ent = "SELECT e.id FROM entregas e 
                                       LEFT JOIN compradores c ON e.comprador_id = c.id 
                                       WHERE e.produto_id = :produto_id 
                                       AND (e.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) 
                                       AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') 
                                       LIMIT 1";
                            $ste = $conn->prepare($sql_ent);
                            $ste->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                            $ste->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                            $ste->execute();
                            if ($ste->fetch(PDO::FETCH_ASSOC)) {
                                $mostrar_botao_avaliar = true;
                            }
                        }
                    }
                }
            } else {
                header("Location: dashboard.php?erro=" . urlencode("Produto não encontrado."));
                exit();
            }
        } catch (PDOException $e) {
            die("Erro ao buscar informações do produto: " . $e->getMessage());
        }
        break;
        
    case 'vendedor':
        // Buscar informações do vendedor
        try {
            $sql_vendedor = "SELECT nome_comercial FROM vendedores WHERE usuario_id = :id";
            $stmt_vendedor = $conn->prepare($sql_vendedor);
            $stmt_vendedor->bindParam(':id', $id_referencia, PDO::PARAM_INT);
            $stmt_vendedor->execute();
            
            if ($vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC)) {
                $pagina_titulo = "Avaliações do Vendedor: " . htmlspecialchars($vendedor['nome_comercial']);
                $subtitulo = "Perfil do vendedor";
            } else {
                header("Location: dashboard.php?erro=" . urlencode("Vendedor não encontrado."));
                exit();
            }
        } catch (PDOException $e) {
            die("Erro ao buscar informações do vendedor: " . $e->getMessage());
        }
        break;
        
    case 'comprador':
        // Buscar informações do comprador
        try {
            $sql_comprador = "SELECT nome_comercial FROM compradores WHERE usuario_id = :id";
            $stmt_comprador = $conn->prepare($sql_comprador);
            $stmt_comprador->bindParam(':id', $id_referencia, PDO::PARAM_INT);
            $stmt_comprador->execute();
            
            if ($comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC)) {
                $pagina_titulo = "Avaliações do Comprador: " . htmlspecialchars($comprador['nome_comercial']);
                $subtitulo = "Perfil do comprador";
            } else {
                header("Location: dashboard.php?erro=" . urlencode("Comprador não encontrado."));
                exit();
            }
        } catch (PDOException $e) {
            die("Erro ao buscar informações do comprador: " . $e->getMessage());
        }
        break;
        
    case 'transportador':
        // Buscar informações do transportador (se houver tabela específica)
        try {
            // Adapte conforme sua estrutura de tabelas
            $sql_transportador = "SELECT nome_comercial FROM transportadores WHERE usuario_id = :id";
            $stmt_transportador = $conn->prepare($sql_transportador);
            $stmt_transportador->bindParam(':id', $id_referencia, PDO::PARAM_INT);
            $stmt_transportador->execute();
            
            if ($transportador = $stmt_transportador->fetch(PDO::FETCH_ASSOC)) {
                $pagina_titulo = "Avaliações do Transportador: " . htmlspecialchars($transportador['nome_comercial']);
                $subtitulo = "Perfil do transportador";
            } else {
                header("Location: dashboard.php?erro=" . urlencode("Transportador não encontrado."));
                exit();
            }
        } catch (PDOException $e) {
            die("Erro ao buscar informações do transportador: " . $e->getMessage());
        }
        break;
        
    default:
        header("Location: dashboard.php?erro=" . urlencode("Tipo de avaliação inválido."));
        exit();
}

// Buscar todas as avaliações do tipo específico
$avaliacoes = [];
$media_avaliacao = 0;
$total_avaliacoes = 0;

try {
    $sql_avaliacoes = "SELECT a.*, u.nome 
                      FROM avaliacoes a 
                      LEFT JOIN usuarios u ON a.avaliador_usuario_id = u.id 
                      WHERE a.tipo = :tipo AND a.";
    
    // Definir o campo correto baseado no tipo
    switch ($tipo_avaliacao) {
        case 'produto':
            $sql_avaliacoes .= "produto_id = :id";
            break;
        case 'vendedor':
            $sql_avaliacoes .= "vendedor_id = :id";
            break;
        case 'comprador':
            $sql_avaliacoes .= "comprador_id = :id";
            break;
        case 'transportador':
            $sql_avaliacoes .= "transportador_id = :id";
            break;
    }
    
    $sql_avaliacoes .= " ORDER BY a.data_criacao DESC";
    
    $stmt_avaliacoes = $conn->prepare($sql_avaliacoes);
    $stmt_avaliacoes->bindParam(':tipo', $tipo_avaliacao);
    $stmt_avaliacoes->bindParam(':id', $id_referencia, PDO::PARAM_INT);
    $stmt_avaliacoes->execute();
    $avaliacoes = $stmt_avaliacoes->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular média
    if (!empty($avaliacoes)) {
        $soma_notas = 0;
        foreach ($avaliacoes as $av) {
            $soma_notas += (int)$av['nota'];
        }
        $media_avaliacao = round($soma_notas / count($avaliacoes), 1);
        $total_avaliacoes = count($avaliacoes);
    }
} catch (PDOException $e) {
    die("Erro ao buscar avaliações: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pagina_titulo; ?> - Encontre o Campo</title>
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #2E7D32;
            --light-gray: #f5f5f5;
            --dark-gray: #333;
            --border-color: #ddd;
            --radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--light-gray);
            color: var(--dark-gray);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 100px auto auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            justify-content: space-between;
            display: flex;
        }
        
        .header h1 {
            color: var(--primary-dark);
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1em;
        }

        .header-info{
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .voltar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            width: 110.64px;
            height: 45.6px;
        }
        
        .voltar-btn:hover {
            background: var(--primary-dark);
            transform: translateX(-5px);
        }
        
        .resumo-avaliacoes {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .media-container {
            align-items: center;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .numero-media {
            font-size: 3.5em;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .estrelas-media {
            display: flex;
            gap: 5px;
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        
        .estrela-cheia {
            color: #ffc107;
        }
        
        .estrela-vazia {
            color: #ddd;
        }
        
        .total-avaliacoes {
            font-size: 1.2em;
            color: #666;
        }
        
        .lista-avaliacoes {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .avaliacao-item {
            padding: 25px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s ease;
        }
        
        /* Cor para itens pares (segundo, quarto, etc.) */
        .avaliacao-item:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        /* Cor para itens ímpares (primeiro, terceiro, etc.) */
        .avaliacao-item:nth-child(odd) {
            background-color: #ffffff;
        }
        
        /* Efeito hover para melhor interatividade */
        .avaliacao-item:hover {
            background-color: #f0f7f0 !important;
        }
        
        /* Ajuste na cor do comentário para ficar bem com ambas as cores de fundo */
        .avaliacao-comentario {
            color: #555;
            line-height: 1.7;
            font-size: 1em;
            background: rgba(76, 175, 80, 0.05);
            padding: 15px;
            border-radius: var(--radius);
            border-left: 3px solid var(--primary-color);
        }
        
        .avaliacao-item:last-child {
            border-bottom: none;
        }
        
        .avaliacao-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .avaliacoes-info {
            flex: 1;
            padding: 30px;
            border-bottom: 1px solid var(--border-color);
            justify-content: space-between;
            display: flex;
        }
        
        .avaliacoes-info h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.3em;
        }
        
        .avaliacoes-info p {
            color: #666;
            margin: 5px 0;
        }

        .avaliador-info {
            flex: 1;
        }
        
        .avaliador-nome {
            font-weight: 600;
            font-size: 1.1em;
            color: var(--dark-gray);
        }
        
        .avaliacao-data {
            color: #999;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .avaliacao-nota {
            display: flex;
            gap: 3px;
            font-size: 1.2em;
        }
        
        .sem-avaliacoes {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }
        
        .sem-avaliacoes i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #ddd;
        }

        .botao-avaliar {
            margin-top: 15px;
            text-align: center;
        }
        
        .botao-avaliar .btn-avaliar {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .botao-avaliar .btn-avaliar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 152, 0, 0.3);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .media-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .numero-media {
                font-size: 2.5em;
            }
            
            .avaliacao-header {
                flex-direction: column;
                align-items: start;
            }
            
            .avaliacao-item {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link">Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="vendedor/dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="vendedor/perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link exit-button no-underline">Sair</a>
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
    <div class="container">
        <div class="header">
            <div class="header-info">
                <div>
                    <h1><?php echo $pagina_titulo; ?></h1>
                <?php if (isset($subtitulo)): ?>
                    <p><?php echo $subtitulo; ?></p>
                <?php endif; ?>
                </div>
                <a href="javascript:history.back()" class="voltar-btn">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
            <div class="resumo-avaliacoes">
                <div class="media-container">
                    <div class="numero-media"><?php echo $media_avaliacao; ?></div>
                    <div>
                        <div class="estrelas-media">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= floor($media_avaliacao)) {
                                    echo '<i class="fas fa-star estrela-cheia"></i>';
                                } elseif ($i - 0.5 <= $media_avaliacao) {
                                    echo '<i class="fas fa-star-half-alt estrela-cheia"></i>';
                                } else {
                                    echo '<i class="far fa-star estrela-vazia"></i>';
                                }
                            }
                            ?>
                        </div>
                        <div class="total-avaliacoes">
                            <?php echo $total_avaliacoes; ?> <?php echo $total_avaliacoes === 1 ? 'avaliação' : 'avaliações'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($total_avaliacoes > 0): ?>
            
            
            <div class="lista-avaliacoes">
                <div class="avaliacoes-info">
                    <div>
                        <h3><i class="fas fa-comments"></i> Avaliações dos Clientes</h3>
                        <p>Veja o que os compradores acham deste 
                            <?php if ($tipo_avaliacao === 'produto'): ?>
                                produto
                            <?php elseif ($tipo_avaliacao === 'vendedor'): ?>
                                vendedor
                            <?php elseif ($tipo_avaliacao === 'comprador'): ?>
                                comprador
                            <?php elseif ($tipo_avaliacao === 'transportador'): ?>
                                transportador
                            <?php endif; ?>    
                        </p>
                    </div>
                    <?php if ($tipo_avaliacao === 'produto' && $mostrar_botao_avaliar): ?>
                        <div class="botao-avaliar">
                            <a href="./avaliar.php?tipo=produto&produto_id=<?php echo $id_referencia; ?>" class="btn-avaliar">
                            <i class="fas fa-star"></i> Avaliar este produto
                        </a>
                    </div>
                    <?php elseif ($tipo_avaliacao === 'produto' && isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                        <div style="padding: 10px 15px; background: #f8f9fa; border-radius: var(--radius); color: #666; font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> Você só pode avaliar produtos que comprou
                        </div>
                    <?php endif; ?>  
                </div>
                <?php 
                    $contador = 0;
                    foreach ($avaliacoes as $av): 
                        $contador++;
                        $classe_cor = ($contador % 2 == 0) ? 'avaliacao-par' : 'avaliacao-impar';
                ?>
                    <div class="avaliacao-item <?php echo $classe_cor; ?>">
                        <div class="avaliacao-header">
                            <div class="avaliador-info">
                                <div class="avaliador-nome">
                                    <?php echo htmlspecialchars($av['nome'] ?? 'Anônimo'); ?>
                                </div>
                                <div class="avaliacao-data">
                                    <?php 
                                    $data = new DateTime($av['data_criacao']);
                                    echo $data->format('d/m/Y à\s H:i');
                                    ?>
                                </div>
                            </div>
                            <div class="avaliacao-nota">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= (int)$av['nota']) {
                                        echo '<i class="fas fa-star estrela-cheia"></i>';
                                    } else {
                                        echo '<i class="far fa-star estrela-vazia"></i>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($av['comentario'])): ?>
                            <div class="avaliacao-comentario ">
                                <?php echo nl2br(htmlspecialchars($av['comentario'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
        <?php else: ?>
            <div class="sem-avaliacoes">
                <div><i class="fas fa-star"></i></div>
                <p>Nenhuma avaliação encontrada.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>