<?php
// src/visualizar_anuncio.php
// página para usuário sem login ativo
session_start();
require_once 'conexao.php';

// 1. OBTENÇÃO DO ID DO ANÚNCIO
if (!isset($_GET['anuncio_id']) || !is_numeric($_GET['anuncio_id'])) {
    header("Location: anuncios.php?erro=" . urlencode("Anúncio não especificado ou inválido."));
    exit();
}

$anuncio_id = (int)$_GET['anuncio_id'];
$is_logged_in = isset($_SESSION['usuario_id']);
$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? null;
$usuario_status = $_SESSION['usuario_status'] ?? null;

$database = new Database();
$conn = $database->getConnection();
$anuncio = null;

// Função auxiliar para calcular desconto
function calcularDesconto($preco, $preco_desconto, $data_expiracao) {
    if ($preco_desconto && $preco_desconto > 0 && $preco_desconto < $preco) {
        $agora = date('Y-m-d H:i:s');
        if (empty($data_expiracao) || $data_expiracao > $agora) {
            return [
                'ativo' => true,
                'preco_original' => $preco,
                'preco_final' => $preco_desconto,
                'porcentagem' => round((($preco - $preco_desconto) / $preco) * 100)
            ];
        }
    }
    return [
        'ativo' => false,
        'preco_final' => $preco
    ];
}

// 2. BUSCA DOS DETALHES DO ANÚNCIO
try {
    $sql = "SELECT 
                p.id, 
                p.nome AS produto, 
                p.descricao,
                p.preco,
                p.preco_desconto,
                p.desconto_data_fim,
                p.estoque AS estoque_kg,
                p.estoque_unidades,
                p.modo_precificacao,
                p.embalagem_peso_kg,
                p.embalagem_unidades,
                p.unidade_medida,
                p.imagem_url, 
                v.id AS vendedor_sistema_id, 
                u.id AS vendedor_usuario_id, 
                v.nome_comercial AS nome_vendedor,
                v.estado AS estado_vendedor
            FROM produtos p
            JOIN vendedores v ON p.vendedor_id = v.id 
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE p.id = :anuncio_id AND p.status = 'ativo'";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt->execute();
    $anuncio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anuncio) {
        header("Location: anuncios.php?erro=" . urlencode("Anúncio não encontrado ou inativo."));
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao carregar anúncio: " . $e->getMessage()); 
}

// Ajustar campos de exibição conforme modo de precificação
$modo = $anuncio['modo_precificacao'] ?? 'por_quilo';
if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
    $anuncio['quantidade_disponivel'] = $anuncio['estoque_unidades'] ?? 0;
} else {
    $anuncio['quantidade_disponivel'] = $anuncio['estoque_kg'] ?? 0;
}
switch ($modo) {
    case 'por_unidade': $anuncio['unidade_medida'] = 'unidade'; break;
    case 'por_quilo': $anuncio['unidade_medida'] = 'kg'; break;
    case 'caixa_unidades': $anuncio['unidade_medida'] = 'caixa' . (!empty($anuncio['embalagem_unidades']) ? " ({$anuncio['embalagem_unidades']} unid)" : ''); break;
    case 'caixa_quilos': $anuncio['unidade_medida'] = 'caixa' . (!empty($anuncio['embalagem_peso_kg']) ? " ({$anuncio['embalagem_peso_kg']} kg)" : ''); break;
    case 'saco_unidades': $anuncio['unidade_medida'] = 'saco' . (!empty($anuncio['embalagem_unidades']) ? " ({$anuncio['embalagem_unidades']} unid)" : ''); break;
    case 'saco_quilos': $anuncio['unidade_medida'] = 'saco' . (!empty($anuncio['embalagem_peso_kg']) ? " ({$anuncio['embalagem_peso_kg']} kg)" : ''); break;
}

// Calcular desconto do produto principal
$info_desconto = calcularDesconto($anuncio['preco'], $anuncio['preco_desconto'], $anuncio['desconto_data_fim']);

// BUSCAR IMAGENS DO PRODUTO - USANDO A MESMA LÓGICA DO CÓDIGO ORIGINAL
$imagens_produto = [];

// Primeiro, verificar se existe uma tabela de imagens múltiplas (igual ao proposta_nova.php)
$tabela_imagens_existe = false;
try {
    // Verificar se a tabela produto_imagens existe
    $sql_verifica_tabela = "SHOW TABLES LIKE 'produto_imagens'";
    $stmt_verifica = $conn->query($sql_verifica_tabela);
    $tabela_imagens_existe = $stmt_verifica->rowCount() > 0;
} catch (Exception $e) {
    $tabela_imagens_existe = false;
}

if ($tabela_imagens_existe) {
    // Se a tabela existe, buscar todas as imagens
    try {
        $sql_imagens = "SELECT imagem_url FROM produto_imagens WHERE produto_id = :anuncio_id ORDER BY ordem ASC";
        $stmt_imagens = $conn->prepare($sql_imagens);
        $stmt_imagens->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
        $stmt_imagens->execute();
        $imagens_temp = $stmt_imagens->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($imagens_temp as $imagem) {
            $imagens_produto[] = [
                'url' => $imagem['imagem_url'],
                'principal' => false
            ];
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar imagens do produto: " . $e->getMessage());
    }
}

// Se não encontrou imagens múltiplas, usar a imagem principal do produto (igual ao original)
if (empty($imagens_produto) && !empty($anuncio['imagem_url'])) {
    $imagens_produto[] = [
        'url' => $anuncio['imagem_url'],
        'principal' => true
    ];
}

// Se ainda não tem imagens, usar um placeholder (igual ao original)
if (empty($imagens_produto)) {
    $imagens_produto[] = [
        'url' => '../img/placeholder.png',
        'principal' => true
    ];
}

// Buscar produtos relacionados
$produtos_relacionados = [];
try {
    $sql_relacionados = "SELECT 
                            p.id, 
                            p.nome, 
                            p.preco, 
                            p.preco_desconto,
                            p.desconto_data_fim,
                            p.imagem_url,
                            p.unidade_medida,
                            v.nome_comercial AS nome_vendedor,
                            v.estado AS estado_vendedor
                         FROM produtos p
                         JOIN vendedores v ON p.vendedor_id = v.id 
                         JOIN usuarios u ON v.usuario_id = u.id
                         WHERE p.id != :anuncio_id 
                         AND p.status = 'ativo' 
                         AND p.estoque > 0
                         ORDER BY RAND() 
                         LIMIT 4";
    
    $stmt_relacionados = $conn->prepare($sql_relacionados);
    $stmt_relacionados->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_relacionados->execute();
    $produtos_relacionados = $stmt_relacionados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se der erro, continua sem produtos relacionados
}

$preco_unitario = $info_desconto['preco_final'];
$preco_display = number_format($preco_unitario, 2, ',', '.');
$unidade = htmlspecialchars($anuncio['unidade_medida']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anuncio['produto']); ?> - Encontre o Campo</title>
    <link rel="stylesheet" href="css/comprador/view_ad.css">
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        .aviso-login {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(33, 150, 243, 0.1);
        }
        
        .aviso-login h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .aviso-login p {
            color: #424242;
            margin-bottom: 15px;
        }
        
        .botoes-login {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-login-cta {
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            color: white;
            padding: 12px 25px;
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
        
        .btn-login-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(33, 150, 243, 0.3);
        }
        
        .btn-register-cta {
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
            color: white;
            padding: 12px 25px;
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
        
        .btn-register-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(76, 175, 80, 0.3);
        }
        
        .botoes-compra-desativados {
            opacity: 0.6;
            pointer-events: none;
            filter: grayscale(20%);
        }
        
        .botoes-compra-desativados .btn-comprar,
        .botoes-compra-desativados .btn-chat {
            cursor: not-allowed;
        }
        
        .btn-chat {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }

        .btn-chat:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }

        .btn-chat i {
            font-size: 20px;
        }

        .tooltip-desativado {
            position: relative;
            cursor: not-allowed;
        }
        
        .tooltip-desativado::after {
            content: "Faça login para acessar esta funcionalidade";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        
        .tooltip-desativado:hover::after {
            opacity: 1;
        }
        
        /* Estilos específicos para visualização pública */
        .visao-publica .produto-actions,
        .visao-publica .proposta-section {
            display: none;
        }

        .status-info, .status-alert {
            padding: 8px 12px;
            margin-top: 10px;
        }

        .status-info i, .status-alert i {
            margin-right: 5px;
        }
        
        /* Estilos para o carrossel de imagens (mantendo o mesmo estilo do original) */
        .carrossel-container {
            position: relative;
            width: 100%;
            height: 400px;
            border-radius: 10px;
            overflow: hidden;
            background: #f5f5f5;
        }
        
        .carrossel-slides {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.5s ease-in-out;
        }
        
        .carrossel-slide {
            min-width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .carrossel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .carrossel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(76, 175, 80, 0.9);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s ease;
            z-index: 10;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .carrossel-btn:hover {
            background: #4CAF50;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .carrossel-btn.prev {
            left: 15px;
        }
        
        .carrossel-btn.next {
            right: 15px;
        }
        
        .carrossel-btn:disabled {
            background: rgba(76, 175, 80, 0.5);
            cursor: not-allowed;
            transform: translateY(-50%);
        }
        
        .carrossel-btn:disabled:hover {
            transform: translateY(-50%);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .carrossel-indicators {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        
        .carrossel-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .carrossel-indicator.active {
            background: #4CAF50;
            transform: scale(1.2);
        }
        
        .carrossel-indicator:hover {
            background: rgba(255, 255, 255, 0.8);
        }
        
        .carrossel-counter {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 10;
        }
        
        .badge-desconto {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #4CAF50;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1rem;
            z-index: 11;
        }
        
        /* Miniaturas das imagens */
        .carrossel-miniaturas {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            overflow-x: auto;
            padding: 5px 0;
        }
        
        .miniatura {
            width: 80px;
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .miniatura:hover {
            border-color: #4CAF50;
            transform: translateY(-2px);
        }
        
        .miniatura.active {
            border-color: #4CAF50;
            box-shadow: 0 3px 8px rgba(76, 175, 80, 0.3);
        }
        
        .miniatura img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        @media (max-width: 768px) {
            .carrossel-container {
                height: 300px;
            }
            
            .carrossel-btn {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .miniatura {
                width: 60px;
                height: 45px;
            }
        }
        
        @media (max-width: 576px) {
            .carrossel-container {
                height: 250px;
            }
            
            .carrossel-indicators {
                bottom: 10px;
            }
            
            .carrossel-indicator {
                width: 10px;
                height: 10px;
            }
        }
        
        /* Quando só tem uma imagem, esconder controles */
        .single-image .carrossel-btn,
        .single-image .carrossel-indicators,
        .single-image .carrossel-counter {
            display: none;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <img src="../img/logo-nova.png" alt="Logo">
                    <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link">Anúncios</a>
                    </li>
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item">
                            <a href="<?= $usuario_tipo ?>/dashboard.php" class="nav-link">Painel</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= $usuario_tipo ?>/perfil.php" class="nav-link">Meu Perfil</a>
                        </li>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link exit-button no-underline">Sair</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="nav-link login-button no-underline">Login</a>
                        </li>
                    <?php endif; ?>
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

    <main class="main-content visao-publica">
        <!-- Aviso para usuários não logados -->
        <?php if (!$is_logged_in): ?>
        <div class="aviso-login">
            <h3><i class="fas fa-info-circle"></i> Acesso Limitado</h3>
            <p>Você está visualizando este anúncio como visitante. Para fazer negócios, conversar com o vendedor ou fazer propostas, você precisa ter uma conta ativa.</p>
            <div class="botoes-login">
                <a href="login.php?redirect=visualizar_anuncio.php?anuncio_id=<?php echo $anuncio_id; ?>" class="btn-login-cta">
                    <i class="fas fa-sign-in-alt"></i> Fazer Login
                </a>
                <a href="../index.php#contato" class="btn-register-cta">
                    <i class="fas fa-user-plus"></i> Criar Conta
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="produto-container">
            <div class="produto-content">
                <!-- Seção de Imagem do Produto - CARROSSEL COM A MESMA LÓGICA DO ORIGINAL -->
                <div class="produto-imagem">
                    <div class="carrossel-container <?php echo count($imagens_produto) <= 1 ? 'single-image' : ''; ?>" id="carrossel-container">
                        <?php if ($info_desconto['ativo']): ?>
                            <div class="badge-desconto">-<?php echo $info_desconto['porcentagem']; ?>%</div>
                        <?php endif; ?>
                        
                        <div class="carrossel-slides" id="carrossel-slides">
                            <?php foreach ($imagens_produto as $index => $imagem): 
                                // USANDO A MESMA LÓGICA DO CÓDIGO ORIGINAL PARA AJUSTAR O CAMINHO
                                $imagePath = $imagem['url'];
                                if (strpos($imagePath, '../') === 0) {
                                    $imagePath = substr($imagePath, 3); // Remove o '../' do início
                                }
                                // Verifica se o arquivo existe no servidor
                                if ($imagem['url'] && !file_exists($imagePath)) {
                                    $imagePath = '../img/placeholder.png';
                                }
                            ?>
                                <div class="carrossel-slide">
                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                         alt="<?php echo htmlspecialchars($anuncio['produto']); ?> - Imagem <?php echo $index + 1; ?>"
                                         onerror="this.src='../img/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Botões de navegação -->
                        <?php if (count($imagens_produto) > 1): ?>
                            <button class="carrossel-btn prev" id="carrossel-prev">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="carrossel-btn next" id="carrossel-next">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            
                            <!-- Contador de imagens -->
                            <div class="carrossel-counter" id="carrossel-counter">
                                1/<?php echo count($imagens_produto); ?>
                            </div>
                            
                            <!-- Indicadores -->
                            <div class="carrossel-indicators" id="carrossel-indicators">
                                <?php for ($i = 0; $i < count($imagens_produto); $i++): ?>
                                    <div class="carrossel-indicator <?php echo $i === 0 ? 'active' : ''; ?>" 
                                         data-index="<?php echo $i; ?>"></div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Miniaturas -->
                    <?php if (count($imagens_produto) > 1): ?>
                        <div class="carrossel-miniaturas" id="carrossel-miniaturas">
                            <?php foreach ($imagens_produto as $index => $imagem): 
                                // USANDO A MESMA LÓGICA DO CÓDIGO ORIGINAL PARA AJUSTAR O CAMINHO
                                $imagePath = $imagem['url'];
                                if (strpos($imagePath, '../') === 0) {
                                    $imagePath = substr($imagePath, 3);
                                }
                                if ($imagem['url'] && !file_exists($imagePath)) {
                                    $imagePath = '../img/placeholder.png';
                                }
                            ?>
                                <div class="miniatura <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     data-index="<?php echo $index; ?>">
                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                         alt="Miniatura <?php echo $index + 1; ?>"
                                         onerror="this.src='../img/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Informações do Produto -->
                <div class="compra-section">
                    <div class="produto-info">
                        <div class="info-header">
                            <h2><?php echo htmlspecialchars($anuncio['produto']); ?></h2>
                            <div class="vendedor-info">
                                <span class="vendedor-label">Vendido por:</span>
                                <span class="vendedor-nome">
                                    <?php echo htmlspecialchars($anuncio['nome_vendedor']); ?>
                                </span>
                                <span class="vendedor-local">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($anuncio['estado_vendedor']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="preco-section">
                            <?php if ($info_desconto['ativo']): ?>
                                <div class="preco-container">
                                    <span class="preco-antigo">R$ <?php echo number_format($info_desconto['preco_original'], 2, ',', '.'); ?></span>
                                    <span class="preco destaque-oferta">R$ <?php echo $preco_display; ?></span>
                                </div>
                                <div class="economia-info">
                                    <i class="fas fa-tag"></i> Economia de R$ <?php echo number_format($info_desconto['preco_original'] - $info_desconto['preco_final'], 2, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                <span class="preco">R$ <?php echo $preco_display; ?></span>
                            <?php endif; ?>
                            
                            <div class="unidade-info">
                                <span class="unidade">por <?php echo $unidade; ?></span>
                            </div>
                        </div>
                        
                        <div class="estoque-info">
                            <i class="fas fa-box"></i>
                            <span><?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?> disponíveis</span>
                        </div>

                        <!-- Seção de Botões (Desativada para não-logados) -->
                        <div class="botoes-compra <?php echo !$is_logged_in ? 'botoes-compra-desativados' : ''; ?>">
                            <?php if ($is_logged_in && $usuario_status === 'ativo'): ?>
                                <!-- BOTÃO DE COMPRA COMENTADO PARA USO FUTURO -->
                                <!-- <a href="comprador/proposta_nova.php?anuncio_id=<?php echo $anuncio_id; ?>" class="btn-comprar">
                                    <i class="fas fa-shopping-cart"></i>
                                    Comprar Agora
                                </a> -->
                                
                                <a href="chat/chat.php?produto_id=<?php echo $anuncio_id; ?>" class="btn-chat">
                                    <i class="fas fa-comments"></i>
                                    Conversar com o Vendedor
                                </a>
                            <?php else: ?>
                                <!-- BOTÃO DE COMPRA COMENTADO PARA USO FUTURO -->
                                <!-- <button class="btn-comprar tooltip-desativado" disabled>
                                    <i class="fas fa-shopping-cart"></i>
                                    Comprar Agora
                                </button> -->
                                
                                <button class="btn-chat tooltip-desativado" disabled>
                                    <i class="fas fa-comments"></i>
                                    Conversar com o Vendedor
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$is_logged_in): ?>
                            <div class="aviso-acao">
                                <p class="status-info" style="color: #ff6b6b; font-size: 0.9em; margin-top: 5px;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Para realizar ações neste anúncio, <a href="login.php?redirect=visualizar_anuncio.php?anuncio_id=<?php echo $anuncio_id; ?>"><strong>faça login</strong></a> ou <a href="../index.php#contato"><strong>crie uma conta</strong></a>.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Descrição do Produto -->
            <?php if ($anuncio['descricao']): ?>
            <div class="descricao-completa">
                <div class="descricao-header">
                    <h3><i class="fas fa-file-alt"></i> Descrição do Produto</h3>
                </div>
                <div class="descricao-content">
                    <p><?php echo nl2br(htmlspecialchars($anuncio['descricao'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Produtos Relacionados -->
            <?php if (!empty($produtos_relacionados)): ?>
            <div class="produtos-relacionados">
                <div class="section-header">
                    <h3><i class="fas fa-star"></i> Outros Anúncios</h3>
                    <p>Produtos que podem te interessar</p>
                </div>
                <div class="anuncios-grid">
                    <?php foreach ($produtos_relacionados as $produto): 
                        $desc_rel = calcularDesconto($produto['preco'], $produto['preco_desconto'], $produto['desconto_data_fim']);
                        // USANDO A MESMA LÓGICA DO CÓDIGO ORIGINAL
                        $imagem_produto = $produto['imagem_url'] ? htmlspecialchars($produto['imagem_url']) : '../img/placeholder.png';
                        if (strpos($imagem_produto, '../') === 0) {
                            $imagem_produto = substr($imagem_produto, 3);
                        }
                        if ($produto['imagem_url'] && !file_exists($imagem_produto)) {
                            $imagem_produto = '../img/placeholder.png';
                        }
                    ?>
                        <div class="anuncio-card <?php echo $desc_rel['ativo'] ? 'card-desconto' : ''; ?>">
                            <a href="visualizar_anuncio.php?anuncio_id=<?php echo $produto['id']; ?>" class="produto-link">
                                <div class="card-image">
                                    <?php if ($desc_rel['ativo']): ?>
                                        <div class="badge-desconto">-<?php echo $desc_rel['porcentagem']; ?>%</div>
                                    <?php endif; ?>
                                    <img src="<?php echo $imagem_produto; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" onerror="this.src='../img/placeholder.png'">
                                </div>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                                        <span class="vendedor">por <?php echo htmlspecialchars($produto['nome_vendedor']); ?></span>
                                        <span class="local"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($produto['estado_vendedor']); ?></span>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="price-container">
                                            <?php if ($desc_rel['ativo']): ?>
                                                <div class="preco-original">R$ <?php echo number_format($desc_rel['preco_original'], 2, ',', '.'); ?></div>
                                                <div class="price price-desconto">
                                                    R$ <?php echo number_format($desc_rel['preco_final'], 2, ',', '.'); ?>
                                                    <span>/<?php echo htmlspecialchars($produto['unidade_medida']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <p class="price">
                                                    R$ <?php echo number_format($desc_rel['preco_final'], 2, ',', '.'); ?>
                                                    <span>/<?php echo htmlspecialchars($produto['unidade_medida']); ?></span>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Encontre o Campo</h4>
                    <ul>
                        <li><a href="../../index.php">Página Inicial</a></li>
                        <li><a href="../anuncios.php">Ver Anúncios</a></li>
                        <li><a href="favoritos.php">Meus Favoritos</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="ajuda.php">Central de Ajuda</a></li>
                        <li><a href="contato.php">Fale Conosco</a></li>
                        <li><a href="sobre.php">Sobre Nós</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="faq.php">FAQ</a></li>
                        <li><a href="termos.php">Termos de Uso</a></li>
                        <li><a href="privacidade.php">Política de Privacidade</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contato</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> contato@encontreocampo.com.br</p>
                        <p><i class="fas fa-phone"></i> (11) 99999-9999</p>
                        <div class="social-links">
                            <a href="#"><i class="fab fa-facebook"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu Hamburguer
            const hamburger = document.querySelector(".hamburger");
            const navMenu = document.querySelector(".nav-menu");

            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });

            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            }));
            
            // Mostrar tooltip nos botões desativados
            const tooltips = document.querySelectorAll('.tooltip-desativado');
            tooltips.forEach(tooltip => {
                tooltip.addEventListener('mouseenter', function() {
                    this.style.opacity = '0.7';
                });
                
                tooltip.addEventListener('mouseleave', function() {
                    this.style.opacity = '0.6';
                });
            });
            
            // SCRIPT DO CARROSSEL - FUNCIONAL
            const carrosselSlides = document.getElementById('carrossel-slides');
            const carrosselPrev = document.getElementById('carrossel-prev');
            const carrosselNext = document.getElementById('carrossel-next');
            const carrosselCounter = document.getElementById('carrossel-counter');
            const carrosselIndicators = document.querySelectorAll('.carrossel-indicator');
            const carrosselMiniaturas = document.querySelectorAll('.miniatura');
            const totalSlides = <?php echo count($imagens_produto); ?>;
            
            let currentSlide = 0;
            
            // Atualizar carrossel
            function updateCarrossel() {
                // Mover slides
                if (carrosselSlides) {
                    carrosselSlides.style.transform = `translateX(-${currentSlide * 100}%)`;
                }
                
                // Atualizar contador
                if (carrosselCounter) {
                    carrosselCounter.textContent = `${currentSlide + 1}/${totalSlides}`;
                }
                
                // Atualizar indicadores
                carrosselIndicators.forEach((indicator, index) => {
                    if (index === currentSlide) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
                
                // Atualizar miniaturas
                carrosselMiniaturas.forEach((miniatura, index) => {
                    if (index === currentSlide) {
                        miniatura.classList.add('active');
                        // Rolar para a miniatura ativa
                        miniatura.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    } else {
                        miniatura.classList.remove('active');
                    }
                });
                
                // Atualizar botões de navegação
                if (carrosselPrev) {
                    carrosselPrev.disabled = currentSlide === 0;
                }
                if (carrosselNext) {
                    carrosselNext.disabled = currentSlide === totalSlides - 1;
                }
            }
            
            // Só inicializar o carrossel se tiver mais de uma imagem
            if (totalSlides > 1 && carrosselPrev && carrosselNext) {
                // Event listeners para botões
                carrosselPrev.addEventListener('click', () => {
                    if (currentSlide > 0) {
                        currentSlide--;
                        updateCarrossel();
                    }
                });
                
                carrosselNext.addEventListener('click', () => {
                    if (currentSlide < totalSlides - 1) {
                        currentSlide++;
                        updateCarrossel();
                    }
                });
                
                // Event listeners para indicadores
                carrosselIndicators.forEach(indicator => {
                    indicator.addEventListener('click', () => {
                        const index = parseInt(indicator.getAttribute('data-index'));
                        if (index !== currentSlide) {
                            currentSlide = index;
                            updateCarrossel();
                        }
                    });
                });
                
                // Event listeners para miniaturas
                carrosselMiniaturas.forEach(miniatura => {
                    miniatura.addEventListener('click', () => {
                        const index = parseInt(miniatura.getAttribute('data-index'));
                        if (index !== currentSlide) {
                            currentSlide = index;
                            updateCarrossel();
                        }
                    });
                });
                
                // Navegação por teclado
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') {
                        if (currentSlide > 0) {
                            currentSlide--;
                            updateCarrossel();
                        }
                    } else if (e.key === 'ArrowRight') {
                        if (currentSlide < totalSlides - 1) {
                            currentSlide++;
                            updateCarrossel();
                        }
                    }
                });
                
                // Inicializar estado dos botões
                updateCarrossel();
            }
        });
    </script>
</body>
</html>