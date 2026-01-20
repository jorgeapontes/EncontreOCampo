<?php
// src/comprador/favoritos.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificação mais robusta de sessão
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

// Verificação adicional se o tipo de usuário é válido
if (!in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}


$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

// Mostrar mensagem de feedback se existir
$mensagem = $_SESSION['mensagem'] ?? '';
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? '';
unset($_SESSION['mensagem']);
unset($_SESSION['tipo_mensagem']);

// Buscar produtos favoritos
$favoritos = [];
try {
    $sql = "SELECT 
                p.*, 
                f.id as favorito_id, 
                f.data_criacao as data_favorito,
                v.nome_comercial as nome_vendedor,
                v.id as vendedor_id
            FROM favoritos f 
            JOIN produtos p ON f.produto_id = p.id 
            JOIN vendedores v ON p.vendedor_id = v.id
            WHERE f.usuario_id = :usuario_id AND p.status = 'ativo'
            ORDER BY f.data_criacao DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Ajustar exibição de estoque/unidade para cada produto
    foreach ($favoritos as &$pf) {
        $modo = $pf['modo_precificacao'] ?? 'por_quilo';
        if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
            $pf['quantidade_disponivel'] = $pf['estoque_unidades'] ?? 0;
        } else {
            $pf['quantidade_disponivel'] = $pf['estoque'] ?? 0;
        }
        switch ($modo) {
            case 'por_unidade': $pf['unidade_medida'] = 'unidade'; break;
            case 'por_quilo': $pf['unidade_medida'] = 'kg'; break;
            case 'caixa_unidades': $pf['unidade_medida'] = 'caixa' . (!empty($pf['embalagem_unidades']) ? " ({$pf['embalagem_unidades']} unid)" : ''); break;
            case 'caixa_quilos': $pf['unidade_medida'] = 'caixa' . (!empty($pf['embalagem_peso_kg']) ? " ({$pf['embalagem_peso_kg']} kg)" : ''); break;
            case 'saco_unidades': $pf['unidade_medida'] = 'saco' . (!empty($pf['embalagem_unidades']) ? " ({$pf['embalagem_unidades']} unid)" : ''); break;
            case 'saco_quilos': $pf['unidade_medida'] = 'saco' . (!empty($pf['embalagem_peso_kg']) ? " ({$pf['embalagem_peso_kg']} kg)" : ''); break;
        }
    }
} catch (PDOException $e) {
    $erro = "Erro ao carregar favoritos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Favoritos - Encontre o Campo</title>
    <link rel="stylesheet" href="../css/comprador/favoritos.css">
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
                    <a href="../../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="../anuncios.php" class="nav-link">Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo ($_SESSION['usuario_tipo'] === 'vendedor') ? '../vendedor/dashboard.php' : 'dashboard.php'; ?>" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="favoritos.php" class="nav-link active">Favoritos</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo ($_SESSION['usuario_tipo'] === 'vendedor') ? '../vendedor/perfil.php' : 'perfil.php'; ?>" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            // Contar notificações não lidas
                            if (isset($_SESSION['usuario_id'])) {
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

    <div class="main-content">
        <div class="header">
            <center>
                <h1>Meus Favoritos</h1>
                <p><?php echo count($favoritos); ?> produto(s) favorito(s)</p>
            </center>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert-message <?php echo $tipo_mensagem === 'erro' ? 'alert-error' : 'alert-success'; ?>" style="padding: 15px; margin: 20px auto; border-radius: 5px; text-align: center; max-width: 600px; background-color: <?php echo $tipo_mensagem === 'erro' ? '#f8d7da' : '#d4edda'; ?>; color: <?php echo $tipo_mensagem === 'erro' ? '#721c24' : '#155724'; ?>; border: 1px solid <?php echo $tipo_mensagem === 'erro' ? '#f5c6cb' : '#c3e6cb'; ?>;">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($erro)): ?>
            <div class="alert-message alert-error" style="padding: 15px; margin: 20px auto; border-radius: 5px; text-align: center; max-width: 600px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($favoritos)): ?>
            <div class="empty-state">
                <div class="empty-search">
                    <i class="fas fa-heart-broken fa-3x"></i>
                    <h3>Nenhum favorito ainda</h3>
                    <p>Você ainda não adicionou nenhum produto aos favoritos.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="anuncios-grid">
                <?php foreach ($favoritos as $produto): ?>
                    <?php
                        $tem_desconto = false;
                        $preco_final = $produto['preco'];
                        $percentual_off = 0;

                        if ($produto['desconto_ativo'] == 1) {
                            $agora = date('Y-m-d H:i:s');
                            $inicio = $produto['desconto_data_inicio'];
                            $fim = $produto['desconto_data_fim'];
                            
                            $data_valida = true;
                            if ($inicio && $agora < $inicio) $data_valida = false;
                            if ($fim && $agora > $fim) $data_valida = false;

                            if ($data_valida && $produto['preco_desconto'] > 0 && $produto['preco_desconto'] < $produto['preco']) {
                                $tem_desconto = true;
                                $preco_final = $produto['preco_desconto'];
                                $percentual_off = intval($produto['desconto_percentual']);
                            }
                        }
                    ?>

                    <div class="anuncio-card favorito-card">
                        <div class="card-image">
                            <span class="categoria-badge"><?php echo htmlspecialchars($produto['categoria']); ?></span>
                            <?php if ($tem_desconto): ?>
                                <div class="badge-desconto">-<?php echo $percentual_off; ?>%</div>
                            <?php endif; ?>

                            <?php 
                                $imagePath = $produto['imagem_url'] ? htmlspecialchars($produto['imagem_url']) : '../img/placeholder.png';
                                if (strpos($imagePath, '../../') === 0) $imagePath = substr($imagePath, 3);
                                if ($produto['imagem_url'] && !file_exists($imagePath)) $imagePath = '../img/placeholder.png';
                            ?>
                            <img src="<?php echo $imagePath; ?>" alt="Imagem de <?php echo htmlspecialchars($produto['nome']); ?>" 
                                onerror="this.src='../img/placeholder.png'">
                        </div>
                        <div class="card-content">
                            <div class="card-header">
                                <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                                <span class="vendedor">
                                    por <a href="../perfil_vendedor.php?vendedor_id=<?php echo $produto['vendedor_id']; ?>">
                                        <?php echo htmlspecialchars($produto['nome_vendedor']); ?>
                                    </a>
                                </span>
                            </div>
                            
                            <div class="card-body">
                                <div class="price-container">
                                    <?php if ($tem_desconto): ?>
                                        <div class="preco-original">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                                        <div class="price price-desconto">
                                            R$ <?php echo number_format($preco_final, 2, ',', '.'); ?>
                                            <span>/<?php echo htmlspecialchars($produto['unidade_medida']); ?></span>
                                        </div>
                                        <div class="economia-info">
                                            <i class="fas fa-tag"></i> Economia de R$ <?php echo number_format($produto['preco'] - $preco_final, 2, ',', '.'); ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="price">
                                            R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                                            <span>/<?php echo htmlspecialchars($produto['unidade_medida']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <p class="estoque">
                                    <i class="fas fa-box"></i>
                                    <?php echo htmlspecialchars($produto['quantidade_disponivel'] ?? $produto['estoque']); ?> disponíveis
                                </p>
                                
                                <p class="descricao">
                                    <?php 
                                    $descricao = htmlspecialchars($produto['descricao'] ?? 'Sem descrição.');
                                    $limite = 100;
                                    if (strlen($descricao) > $limite) {
                                        $descricao_curta = substr($descricao, 0, $limite);
                                        $ultimo_espaco = strrpos($descricao_curta, ' ');
                                        echo ($ultimo_espaco !== false) ? substr($descricao_curta, 0, $ultimo_espaco) . '...' : $descricao_curta . '...';
                                    } else {
                                        echo $descricao;
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <div class="card-actions">
                                <div style="display: flex; gap: 10px; width: 100%;">
                                    <a href="view_ad.php?anuncio_id=<?php echo $produto['id']; ?>" class="btn btn-primary" style="flex: 2;">
                                        <i class="fas fa-eye"></i> Ver Detalhes
                                    </a>
                                    <a href="remover_favorito.php?favorito_id=<?php echo $produto['favorito_id']; ?>" 
                                       class="btn btn-remover-favorito" 
                                       onclick="return confirm('Tem certeza que deseja remover este item dos favoritos?');"
                                       style="flex: 1; background-color: #dc3545; color: white; text-decoration: none; text-align: center;">
                                        <i class="fas fa-xmark"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Menu Hamburguer
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector(".hamburger");
            const navMenu = document.querySelector(".nav-menu");

            if (hamburger && navMenu) {
                hamburger.addEventListener("click", () => {
                    hamburger.classList.toggle("active");
                    navMenu.classList.toggle("active");
                });

                document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                    hamburger.classList.remove("active");
                    navMenu.classList.remove("active");
                }));
            }
        });
    </script>
</body>
</html>