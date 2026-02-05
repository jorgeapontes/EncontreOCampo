<?php
// perfil_vendedor.php
session_start();
require_once 'conexao.php';

// Verificar se o vendedor_id (que na verdade é o usuario_id do vendedor) foi passado
if (!isset($_GET['vendedor_id'])) {
    header('Location: anuncios.php');
    exit();
}

$vendedor_usuario_id = $_GET['vendedor_id']; // Renomeei para ficar claro que é o ID de usuário

// Variáveis de sessão
$is_logged_in = isset($_SESSION['usuario_id']);
$usuario_tipo = $_SESSION['usuario_tipo'] ?? null;
$is_comprador = $usuario_tipo === 'comprador';

// Conexão
$database = new Database();
$conn = $database->getConnection();

$vendedor_info = [];
$anuncios_vendedor = [];
$total_anuncios = 0;

// Função auxiliar para calcular desconto
function getPrecoEfetivo($anuncio) {
    if (isset($anuncio['preco_desconto']) && $anuncio['preco_desconto'] > 0) {
        $is_valid_discount = !isset($anuncio['desconto_data_fim']) || 
                             empty($anuncio['desconto_data_fim']) ||
                             strtotime($anuncio['desconto_data_fim']) > time();
        
        if ($is_valid_discount) {
            $preco_original = (float)$anuncio['preco'];
            $preco_promocional = (float)$anuncio['preco_desconto'];
            $desconto_percentual = ($preco_original > 0) ? round((($preco_original - $preco_promocional) / $preco_original) * 100) : 0;

            return [
                'efetivo' => $preco_promocional,
                'original' => $preco_original,
                'percentual' => $desconto_percentual
            ];
        }
    }
    return [
        'efetivo' => (float)$anuncio['preco'],
        'original' => null,
        'percentual' => 0
    ];
}

try {
    // 1. BUSCAR INFORMAÇÕES DO VENDEDOR E O LIMITE DO PLANO
    // Adicionamos o JOIN com 'planos' para pegar o 'limite_total_anuncios'
    $sql_vendedor = "SELECT u.nome AS nome_vendedor, v.cidade, v.estado, v.nome_comercial, v.foto_perfil_url, 
                     pl.limite_total_anuncios
                     FROM usuarios u 
                     JOIN vendedores v ON u.id = v.usuario_id 
                     JOIN planos pl ON v.plano_id = pl.id
                     WHERE u.id = ? AND u.status = 'ativo'";
    
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->execute([$vendedor_usuario_id]);
    $vendedor_info = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor_info) {
        die("Vendedor não encontrado ou inativo.");
    }

    $limite_plano = $vendedor_info['limite_total_anuncios'] ?? 1;

    // 2. BUSCAR AVALIAÇÕES DO VENDEDOR E CALCULAR MÉDIA
    $avaliacoes_vendedor = [];
    $media_avaliacao = 0;
    $total_avaliacoes = 0;
    
    $sql_avaliacoes = "SELECT nota 
                       FROM avaliacoes 
                       WHERE tipo = 'vendedor' 
                       AND vendedor_id = ?
                       ORDER BY data_criacao DESC";
    
    $stmt_avaliacoes = $conn->prepare($sql_avaliacoes);
    $stmt_avaliacoes->execute([$vendedor_usuario_id]);
    $avaliacoes_vendedor = $stmt_avaliacoes->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular média das avaliações
    if (!empty($avaliacoes_vendedor)) {
        $soma_notas = 0;
        foreach ($avaliacoes_vendedor as $av) {
            $soma_notas += (int)$av['nota'];
        }
        $media_avaliacao = round($soma_notas / count($avaliacoes_vendedor), 1);
        $total_avaliacoes = count($avaliacoes_vendedor);
    }

    // 3. BUSCAR ANÚNCIOS DO VENDEDOR COM FILTRO DE LIMITE
    // Usamos ROW_NUMBER() para ordenar os anúncios por antiguidade (ID ASC) e filtrar pelo limite
    $sql_anuncios = "SELECT * FROM (
                        SELECT 
                            p.id, p.nome AS produto, 
                            p.preco, 
                            p.preco_desconto,             
                            p.desconto_data_fim,    
                            p.estoque AS estoque_kg,
                            p.estoque_unidades,
                            p.modo_precificacao,
                            p.embalagem_peso_kg,
                            p.embalagem_unidades,
                            p.unidade_medida, 
                            p.descricao, 
                            p.imagem_url,
                            ROW_NUMBER() OVER (ORDER BY p.id ASC) as ranking
                        FROM produtos p 
                        WHERE p.vendedor_id IN (SELECT id FROM vendedores WHERE usuario_id = ?) 
                        AND p.status = 'ativo'
                    ) sub
                    WHERE ranking <= ?"; // Aqui aplicamos o limite do plano
    
    $stmt_anuncios = $conn->prepare($sql_anuncios);
    $stmt_anuncios->execute([$vendedor_usuario_id, $limite_plano]);
    $anuncios_vendedor = $stmt_anuncios->fetchAll(PDO::FETCH_ASSOC);
    
    $total_anuncios = count($anuncios_vendedor);

    // Ajustar exibição de estoque/unidade para compatibilidade
    foreach ($anuncios_vendedor as &$av) {
        $modo = $av['modo_precificacao'] ?? 'por_quilo';
        if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
            $av['quantidade_disponivel'] = $av['estoque_unidades'] ?? 0;
        } else {
            $av['quantidade_disponivel'] = $av['estoque_kg'] ?? 0;
        }

        switch ($modo) {
            case 'por_unidade': $av['unidade_medida'] = 'unidade'; break;
            case 'por_quilo': $av['unidade_medida'] = 'kg'; break;
            case 'caixa_unidades': $av['unidade_medida'] = 'caixa' . (!empty($av['embalagem_unidades']) ? " ({$av['embalagem_unidades']} unid)" : ''); break;
            case 'caixa_quilos': $av['unidade_medida'] = 'caixa' . (!empty($av['embalagem_peso_kg']) ? " ({$av['embalagem_peso_kg']} kg)" : ''); break;
            case 'saco_unidades': $av['unidade_medida'] = 'saco' . (!empty($av['embalagem_unidades']) ? " ({$av['embalagem_unidades']} unid)" : ''); break;
            case 'saco_quilos': $av['unidade_medida'] = 'saco' . (!empty($av['embalagem_peso_kg']) ? " ({$av['embalagem_peso_kg']} kg)" : ''); break;
            default: $av['unidade_medida'] = 'kg';
        }
    }

} catch (PDOException $e) {
    die("Erro ao carregar informações: " . $e->getMessage());
}

$foto_perfil_url = $vendedor_info['foto_perfil_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil do Vendedor - Encontre Ocampo</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="css/anuncios.css?v=1.1"> 
    <link rel="stylesheet" href="css/vendedor/perfil.css">
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        .avaliacao-vendedor {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            background: linear-gradient(135deg, #fff8e1 0%, #fff3e0 100%);
            border-radius: 8px;
            border: 1px solid #ffd54f;
            width: fit-content;
            height: fit-content ;
        }
        
        .media-avaliacao-vendedor {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .numero-media-vendedor {
            font-size: 1.8em;
            font-weight: 700;
            color: #ff9800;
        }
        
        .estrelas-media-vendedor {
            display: flex;
            gap: 3px;
        }
        
        .estrela-cheia-vendedor {
            color: #ffc107;
        }
        
        .estrela-vazia-vendedor {
            color: #ddd;
        }
        
        .total-avaliacoes-vendedor {
            color: #666;
            font-size: 0.9em;
        }
        
        .sem-avaliacoes-vendedor {
            color: #999;
            font-style: italic;
            margin-top: 10px;
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
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item"><a href="<?= $usuario_tipo ?>/dashboard.php" class="nav-link">Painel</a></li>
                        <li class="nav-item"><a href="<?= $usuario_tipo ?>/perfil.php" class="nav-link">Meu Perfil</a></li>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link exit-button no-underline">Sair</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="nav-link login-button no-underline">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <main class="container content-container">
        <div class="page-header">
            <h2>Perfil do Vendedor</h2>
        </div>

        <div class="section-perfil">
            <div class="forms-area">
                <center>
                <div class="foto-perfil-display">                  
                        <?php if (!empty($foto_perfil_url)): 
                            $foto_path = $foto_perfil_url;
                            if (strpos($foto_path, '../') === 0) {
                                $foto_path = substr($foto_path, 3);
                            }
                        ?>
                            <img id="profile-img-preview" 
                                src="<?php echo htmlspecialchars($foto_path); ?>" 
                                alt="Foto de Perfil"
                                onerror="this.style.display='none'; document.getElementById('default-avatar').style.display='block';">
                            <div id="default-avatar" class="default-avatar" style="display: none;">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        <?php else: ?>
                            <div class="default-avatar">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        <?php endif; ?>                   
                </div>
                </center>
                <div style="text-align: center; align-items: center; display: flex; flex-direction: column;">
                    <h3><?php echo htmlspecialchars($vendedor_info['nome_comercial'] ?? $vendedor_info['nome_vendedor']); ?></h3>
                    <p style="color: var(--text-light); margin-bottom: 10px;">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($vendedor_info['cidade'] . ' - ' . $vendedor_info['estado']); ?>
                    </p>
                    
                    <!-- EXIBIÇÃO DA AVALIAÇÃO MÉDIA DO VENDEDOR -->
                    <?php if ($total_avaliacoes > 0): ?>
                        <div class="avaliacao-vendedor" 
                            style="cursor: pointer;" 
                            onclick="redirectToVendorReviews(<?php echo $vendedor_usuario_id; ?>, <?php echo $is_logged_in ? 'true' : 'false'; ?>)">
                            <div class="media-avaliacao-vendedor">
                                <div class="numero-media-vendedor"><?php echo $media_avaliacao; ?></div>
                                <div class="estrelas-media-vendedor">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= floor($media_avaliacao)) {
                                            echo '<i class="fas fa-star estrela-cheia-vendedor"></i>';
                                        } elseif ($i - 0.5 <= $media_avaliacao) {
                                            echo '<i class="fas fa-star-half-alt estrela-cheia-vendedor"></i>';
                                        } else {
                                            echo '<i class="far fa-star estrela-vazia-vendedor"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="total-avaliacoes-vendedor">
                                    <?php echo $total_avaliacoes; ?> <?php echo $total_avaliacoes === 1 ? 'avaliação' : 'avaliações'; ?>
                                    <i class="fas fa-external-link-alt" style="margin-left: 5px; font-size: 0.8em;"></i>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="sem-avaliacoes-vendedor">
                            <i class="far fa-star"></i> Este vendedor ainda não tem avaliações
                        </div>
                    <?php endif; ?>
                    
                    <p style="color: var(--primary-color); font-weight: 600; margin-top: 15px;">
                        <i class="fas fa-box"></i>
                        <?php echo $total_anuncios; ?> anúncio(s) ativo(s)
                    </p>
                </div>
            </div><br>

            <div class="forms-area">
                <center><h2>Anúncios Publicados</h2></center>
                
                <?php if (empty($anuncios_vendedor)): ?>
                    <div class="empty-state">
                        <p>Este vendedor não possui anúncios ativos no momento.</p>
                    </div>
                <?php else: ?>
                    <div class="anuncios-grid">
                        <?php foreach ($anuncios_vendedor as $anuncio): 
                            // Calcular desconto para o card
                            $info_preco = getPrecoEfetivo($anuncio);
                            $has_discount = $info_preco['original'] !== null;
                        ?>
                            <div class="anuncio-card <?php echo $has_discount ? 'discount-active-card' : ''; ?>">
                                
                                <?php if ($has_discount): ?>
                                    <div class="discount-badge">
                                        -<?php echo $info_preco['percentual']; ?>%
                                    </div>
                                <?php endif; ?>

                                <div class="card-image">
                                    <?php 
                                        $imagePath = $anuncio['imagem_url'] ? htmlspecialchars($anuncio['imagem_url']) : '../img/placeholder.png';
                                        if (strpos($imagePath, '../') === 0) {
                                            $imagePath = substr($imagePath, 3);
                                        }
                                    ?>
                                    <img src="<?php echo $imagePath; ?>" 
                                         alt="Imagem de <?php echo htmlspecialchars($anuncio['produto']); ?>" 
                                         onerror="this.src='../img/placeholder.png'">
                                </div>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($anuncio['produto']); ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-price-container">
                                            <?php if ($has_discount): ?>
                                                <span class="preco-original">R$ <?php echo number_format($info_preco['original'], 2, ',', '.');?></span>
                                                <span class="price price-desconto">R$ <?php echo number_format($info_preco['efetivo'], 2, ',', '.');?>
                                                    <span style="font-size: 0.9rem; color: #7f8c8d;">/<?php echo htmlspecialchars($anuncio['unidade_medida']);?></span>
                                                </span>
                                            <?php else: ?>
                                                <span class="price">
                                                    R$ <?php echo number_format($info_preco['efetivo'], 2, ',', '.'); ?>
                                                    <span style="font-size: 0.9rem; color: #7f8c8d;">/<?php echo htmlspecialchars($anuncio['unidade_medida']);?></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <p class="estoque">
                                            <i class="fas fa-box"></i>
                                            <?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> disponíveis
                                        </p>
                                        
                                    </div>
                                    <div class="card-actions">
                                        <?php if ($is_comprador): ?>
                                            <a href="comprador/proposta_nova.php?anuncio_id=<?php echo $anuncio['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-handshake"></i> Comprar
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-primary open-login-modal" data-target="#loginModal">
                                                <i class="fas fa-handshake"></i> Comprar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Acesso Negociador</h3>
            <p>É necessário estar logado como Comprador para fazer uma proposta.</p>
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="modal-email">Email</label>
                    <input type="email" id="modal-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="modal-password">Senha</label>
                    <input type="password" id="modal-password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Entrar</button>
                <div style="text-align: center; margin-top: 15px;">
                    Não tem conta? <a href="../index.php#contato" target="_blank">Registre-se</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('loginModal');
        const closeButton = document.querySelector('.modal-close');
        
        function openModal(e) { e.preventDefault(); modal.style.display = 'block'; }
        document.querySelectorAll('.open-login-modal').forEach(el => el.addEventListener('click', openModal));
        if (closeButton) closeButton.onclick = function() { modal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target === modal) modal.style.display = 'none'; }
    });

    function redirectToVendorReviews(vendedorUsuarioId, isLoggedIn) {
    if (isLoggedIn) {
        // Usuário logado: redireciona diretamente para a página de avaliações
        window.location.href = 'avaliacoes.php?tipo=vendedor&id=' + vendedorUsuarioId;
    } else {
        // Usuário não logado: redireciona para login com parâmetro de redirecionamento
        const redirectUrl = encodeURIComponent('avaliacoes.php?tipo=vendedor&id=' + vendedorUsuarioId);
        window.location.href = 'login.php?redirect=' + redirectUrl;
    }
}

// Adiciona efeito de hover para feedback visual
document.addEventListener('DOMContentLoaded', function() {
    const avaliacaoDiv = document.querySelector('.avaliacao-vendedor');
    if (avaliacaoDiv) {
        avaliacaoDiv.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
            this.style.transition = 'all 0.3s ease';
        });
        
        avaliacaoDiv.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
        
        // Adiciona título para acessibilidade
        avaliacaoDiv.title = "Clique para ver todas as avaliações deste vendedor";
    }
});
    </script>
</body>
</html>