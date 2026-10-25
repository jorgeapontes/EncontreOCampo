<?php
// src/visualizar_anuncio.php
// Página do anúncio, para visitantes e usuários logados.
// Visitante: vê tudo, com aviso para fazer login e ações desativadas.
// Comprador/vendedor logado: favoritos, conversa com o vendedor e aviso de logística.
require_once 'conexao.php';

// 1. OBTENÇÃO DO ID DO ANÚNCIO
if (!isset($_GET['anuncio_id']) || !is_numeric($_GET['anuncio_id'])) {
    header("Location: anuncios?erro=" . urlencode("Anúncio não especificado ou inválido."));
    exit();
}

$anuncio_id = (int)$_GET['anuncio_id'];
$is_logged_in = isset($_SESSION['usuario_id']);
$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? null;
$usuario_status = $_SESSION['usuario_status'] ?? null;

// Só comprador e vendedor negociam (transportador vê o anúncio, sem ações)
$pode_negociar = $is_logged_in && in_array($usuario_tipo, ['comprador', 'vendedor']);
$conta_ativa = $usuario_status === 'ativo';

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

// Imagens são gravadas relativas a src/ ("../uploads/...") ou à raiz; esta
// página está em src/, então o "../" inicial sai. Arquivo ausente vira placeholder.
function caminhoImagem($url) {
    if (!$url) return '../img/placeholder.png';
    $caminho = strpos($url, '../') === 0 ? substr($url, 3) : $url;
    return file_exists($caminho) ? $caminho : '../img/placeholder.png';
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
                p.paletizado,
                p.unidade_medida,
                p.imagem_url,
                v.id AS vendedor_sistema_id,
                u.id AS vendedor_usuario_id,
                v.nome_comercial AS nome_vendedor,
                v.estado AS estado_vendedor,
                v.estados_atendidos
            FROM produtos p
            JOIN vendedores v ON p.vendedor_id = v.id
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE p.id = :anuncio_id AND p.status = 'ativo'";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt->execute();
    $anuncio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anuncio) {
        header("Location: anuncios?erro=" . urlencode("Anúncio não encontrado ou inativo."));
        exit();
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar anúncio {$anuncio_id}: " . $e->getMessage());
    header("Location: anuncios?erro=" . urlencode("Não foi possível carregar o anúncio."));
    exit();
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

// 3. O VENDEDOR VENDO O PRÓPRIO ANÚNCIO: mostra a página, mas sem ações de compra
$eh_dono = $is_logged_in && (int)$anuncio['vendedor_usuario_id'] === (int)$usuario_id;
if ($eh_dono) {
    $pode_negociar = false;
}

// 4. LOGÍSTICA DE ENTREGA (estado do comprador x estados atendidos pelo vendedor)
$estado_comprador = '';
$estados_atendidos = [];
$entrega_disponivel = true;

if ($pode_negociar) {
    try {
        $stmt_estado = $conn->prepare("SELECT estado FROM compradores WHERE usuario_id = :usuario_id");
        $stmt_estado->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_estado->execute();
        if ($comprador_estado = $stmt_estado->fetch(PDO::FETCH_ASSOC)) {
            $estado_comprador = strtoupper(trim($comprador_estado['estado']));
        }

        $estados_json = $anuncio['estados_atendidos'];
        if (!empty($estados_json) && $estados_json !== 'null') {
            $estados_atendidos = json_decode($estados_json, true) ?: [];
            if (!empty($estados_atendidos) && !empty($estado_comprador)) {
                $entrega_disponivel = in_array($estado_comprador, $estados_atendidos);
            }
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar logística: " . $e->getMessage());
    }
}

// 5. VENDEDOR COMPRANDO: garante um perfil em `compradores` (o chat usa o
// endereço dele na sidebar). Criado a partir dos dados do perfil de vendedor.
if ($pode_negociar && $usuario_tipo === 'vendedor') {
    try {
        $stmt_comprador = $conn->prepare("SELECT id FROM compradores WHERE usuario_id = :usuario_id");
        $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_comprador->execute();

        if (!$stmt_comprador->fetch()) {
            $stmt_vendedor = $conn->prepare("SELECT v.*, u.nome FROM vendedores v JOIN usuarios u ON v.usuario_id = u.id WHERE v.usuario_id = :usuario_id");
            $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_vendedor->execute();
            $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

            if ($vendedor) {
                $sql_criar_comprador = "INSERT INTO compradores
                                        (usuario_id, tipo_pessoa, nome_comercial, cpf_cnpj, cep, rua, numero, complemento, estado, cidade, telefone1, plano)
                                        VALUES
                                        (:usuario_id, 'cpf', :nome_comercial, :cpf_cnpj, :cep, :rua, :numero, '', :estado, :cidade, :telefone1, 'free')";
                $stmt_criar = $conn->prepare($sql_criar_comprador);
                $stmt_criar->execute([
                    ':usuario_id' => $usuario_id,
                    ':nome_comercial' => !empty($vendedor['nome_comercial']) ? $vendedor['nome_comercial'] : $vendedor['nome'],
                    ':cpf_cnpj' => $vendedor['cpf_cnpj'] ?? '',
                    ':cep' => $vendedor['cep'] ?? '',
                    ':rua' => $vendedor['rua'] ?? '',
                    ':numero' => $vendedor['numero'] ?? '',
                    ':estado' => $vendedor['estado'] ?? '',
                    ':cidade' => $vendedor['cidade'] ?? '',
                    ':telefone1' => $vendedor['telefone1'] ?? '',
                ]);
            }
        }
    } catch (PDOException $e) {
        error_log("Erro ao preparar perfil de comprador do vendedor {$usuario_id}: " . $e->getMessage());
    }
}

// Calcular desconto do produto principal
$info_desconto = calcularDesconto($anuncio['preco'], $anuncio['preco_desconto'], $anuncio['desconto_data_fim']);

// 6. IMAGENS DO PRODUTO
$imagens_produto = [];
try {
    $sql_imagens = "SELECT imagem_url FROM produto_imagens WHERE produto_id = :anuncio_id ORDER BY ordem ASC";
    $stmt_imagens = $conn->prepare($sql_imagens);
    $stmt_imagens->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_imagens->execute();
    foreach ($stmt_imagens->fetchAll(PDO::FETCH_ASSOC) as $imagem) {
        $imagens_produto[] = ['url' => $imagem['imagem_url'], 'principal' => false];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar imagens do produto: " . $e->getMessage());
}

// Sem imagens múltiplas, usa a imagem principal; sem nenhuma, o placeholder
if (empty($imagens_produto) && !empty($anuncio['imagem_url'])) {
    $imagens_produto[] = ['url' => $anuncio['imagem_url'], 'principal' => true];
}
if (empty($imagens_produto)) {
    $imagens_produto[] = ['url' => '../img/placeholder.png', 'principal' => true];
}

// 7. OUTROS ANÚNCIOS (só os que cabem no limite do plano de cada vendedor)
$produtos_relacionados = [];
try {
    $sql_relacionados = "SELECT
                            final.id,
                            final.nome,
                            final.preco,
                            final.preco_desconto,
                            final.desconto_data_fim,
                            final.imagem_url,
                            final.unidade_medida,
                            v.nome_comercial AS nome_vendedor,
                            v.estado AS estado_vendedor
                         FROM (
                            -- Numera os anúncios de cada vendedor (mais antigos primeiro)
                            SELECT
                                p.id, p.vendedor_id, p.nome, p.preco, p.preco_desconto,
                                p.desconto_data_fim, p.imagem_url, p.unidade_medida,
                                ROW_NUMBER() OVER (PARTITION BY p.vendedor_id ORDER BY p.id ASC) as ranking
                            FROM produtos p
                            WHERE p.status = 'ativo' AND p.estoque > 0
                         ) final
                         JOIN vendedores v ON final.vendedor_id = v.id
                         JOIN planos pl ON v.plano_id = pl.id
                         WHERE final.ranking <= pl.limite_total_anuncios
                         AND final.id != :anuncio_id
                         ORDER BY RAND()
                         LIMIT 4";

    $stmt_relacionados = $conn->prepare($sql_relacionados);
    $stmt_relacionados->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_relacionados->execute();
    $produtos_relacionados = $stmt_relacionados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro produtos relacionados: " . $e->getMessage());
}

// 8. FAVORITO
$favorito_id = null;
if ($pode_negociar) {
    try {
        $stmt_favorito = $conn->prepare("SELECT id FROM favoritos WHERE usuario_id = :usuario_id AND produto_id = :produto_id");
        $stmt_favorito->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_favorito->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
        $stmt_favorito->execute();
        $favorito_id = $stmt_favorito->fetchColumn() ?: null;
    } catch (PDOException $e) {
        error_log("Erro ao verificar favorito: " . $e->getMessage());
    }
}

// 9. AVALIAÇÕES DO PRODUTO
$avaliacoes = [];
$avaliacoes_limitadas = [];
$media_avaliacao = 0;
$total_avaliacoes = 0;
try {
    $sql_aval = "SELECT a.*, u.nome FROM avaliacoes a LEFT JOIN usuarios u ON a.avaliador_usuario_id = u.id WHERE a.produto_id = :produto_id AND a.tipo = 'produto' ORDER BY a.data_criacao DESC";
    $stmt_aval = $conn->prepare($sql_aval);
    $stmt_aval->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_aval->execute();
    $avaliacoes = $stmt_aval->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($avaliacoes)) {
        $soma_notas = 0;
        foreach ($avaliacoes as $av) {
            $soma_notas += (int)$av['nota'];
        }
        $media_avaliacao = round($soma_notas / count($avaliacoes), 1);
        $total_avaliacoes = count($avaliacoes);

        // Limitar a 3 avaliações mais recentes para exibição
        $avaliacoes_limitadas = array_slice($avaliacoes, 0, 3);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar avaliações: " . $e->getMessage());
}

// 10. PODE AVALIAR? Comprador com proposta aceita (e entrega concluída, quando foi por transportador)
$mostrar_avaliar = false;
if ($is_logged_in && $conta_ativa) {
    try {
        $sql_check = "SELECT p.opcao_frete FROM propostas p LEFT JOIN compradores c ON p.comprador_id = c.id WHERE p.produto_id = :produto_id AND (p.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) AND p.status = 'aceita' ORDER BY p.data_inicio DESC LIMIT 1";
        $stc = $conn->prepare($sql_check);
        $stc->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
        $stc->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stc->execute();
        $rowc = $stc->fetch(PDO::FETCH_ASSOC);
        if ($rowc) {
            $op = $rowc['opcao_frete'] ?? null;
            if (in_array($op, ['vendedor', 'comprador'])) {
                $mostrar_avaliar = true;
            } elseif ($op === 'entregador') {
                $sql_ent = "SELECT e.id FROM entregas e LEFT JOIN compradores c ON e.comprador_id = c.id WHERE e.produto_id = :produto_id AND (e.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') LIMIT 1";
                $ste = $conn->prepare($sql_ent);
                $ste->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
                $ste->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                $ste->execute();
                $mostrar_avaliar = (bool)$ste->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        // não bloquear a exibição do anúncio em caso de erro na verificação
        error_log("Erro verificação avaliação: " . $e->getMessage());
    }
}

$preco_unitario = $info_desconto['preco_final'];
$preco_display = number_format($preco_unitario, 2, ',', '.');
$unidade = htmlspecialchars($anuncio['unidade_medida']);

// ============================================================
// COMPARTILHAMENTO (prévia no WhatsApp/redes via Open Graph)
// ============================================================
$url_site = 'https://encontreocampo.com.br';
$url_anuncio = $url_site . '/src/visualizar_anuncio?anuncio_id=' . $anuncio_id;

// Imagens são gravadas relativas a src/ ("../uploads/...") ou à raiz ("uploads/...")
$og_imagem = $url_site . '/img/logo-nova.png';
$caminho_imagem = $imagens_produto[0]['url'] ?? '';
if ($caminho_imagem && !preg_match('#^https?://#i', $caminho_imagem)) {
    $relativo = ltrim(str_replace('../', '', $caminho_imagem), '/');
    foreach ([$relativo, 'src/' . $relativo] as $candidato) {
        if (is_file(__DIR__ . '/../' . $candidato)) {
            $og_imagem = $url_site . '/' . str_replace('%2F', '/', rawurlencode($candidato));
            break;
        }
    }
} elseif ($caminho_imagem) {
    $og_imagem = $caminho_imagem;
}

$og_titulo = $anuncio['produto'] . ' - R$ ' . $preco_display . '/' . $anuncio['unidade_medida'];
$og_descricao = trim(preg_replace('/\s+/', ' ', strip_tags($anuncio['descricao'] ?? '')));
if ($og_descricao === '') {
    $og_descricao = 'Direto do produtor' . (!empty($anuncio['nome_vendedor']) ? ' ' . $anuncio['nome_vendedor'] : '') . '. Negocie pelo Encontre o Campo.';
}
if (mb_strlen($og_descricao) > 155) {
    $og_descricao = mb_substr($og_descricao, 0, 152) . '...';
}
$texto_whatsapp = $og_titulo . ' no Encontre o Campo: ' . $url_anuncio;
$url_volta_favorito = urlencode('../visualizar_anuncio?anuncio_id=' . $anuncio_id);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anuncio['produto']); ?> - Encontre o Campo</title>
    <meta name="description" content="<?= htmlspecialchars($og_descricao) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($url_anuncio) ?>">
    <meta property="og:type" content="product">
    <meta property="og:site_name" content="Encontre o Campo">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="<?= htmlspecialchars($og_titulo) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_descricao) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($url_anuncio) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_imagem) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="css/comprador/view_ad.css?v=<?= filemtime(__DIR__ . '/css/comprador/view_ad.css') ?>">
    <link rel="stylesheet" href="css/visualizar_anuncio.css?v=<?= filemtime(__DIR__ . '/css/visualizar_anuncio.css') ?>">
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/footer.css">
</head>
<body>
    <?php $active_nav = 'anuncios'; require __DIR__ . '/includes/navbar.php'; ?>

    <main class="main-content">
        <?php if (!$is_logged_in): ?>
        <!-- Aviso para usuários não logados -->
        <div class="aviso-login">
            <h3><i class="fas fa-info-circle"></i> Acesso Limitado</h3>
            <p>Você está visualizando este anúncio como visitante. Para fazer negócios, conversar com o vendedor ou fazer propostas, você precisa ter uma conta ativa.</p>
            <div class="botoes-login">
                <a href="login" class="btn-login-cta">
                    <i class="fas fa-sign-in-alt"></i> Fazer Login
                </a>
                <a href="../index#contato" class="btn-register-cta">
                    <i class="fas fa-user-plus"></i> Criar Conta
                </a>
            </div>
        </div>
        <?php elseif ($pode_negociar && $usuario_status === 'pendente'): ?>
            <div class="aviso-pendente">
                <i class="fas fa-clock"></i> <strong>Seu cadastro está aguardando aprovação.</strong> Enquanto isso, você pode visualizar os anúncios, mas ainda não pode fazer negócios.
            </div>
        <?php elseif ($eh_dono): ?>
            <div class="aviso-pendente aviso-dono">
                <i class="fas fa-store"></i> <strong>Este anúncio é seu.</strong> É assim que os compradores o veem. <a href="vendedor/anuncios">Gerenciar meus anúncios</a>
            </div>
        <?php endif; ?>

        <div class="produto-container">
            <div class="produto-content">
                <div class="produto-imagem">
                    <div class="carrossel-container <?php echo count($imagens_produto) <= 1 ? 'single-image' : ''; ?>" id="carrossel-container">
                        <?php if ($info_desconto['ativo']): ?>
                            <div class="badge-desconto">-<?php echo $info_desconto['porcentagem']; ?>%</div>
                        <?php endif; ?>

                        <div class="carrossel-slides" id="carrossel-slides">
                            <?php foreach ($imagens_produto as $index => $imagem): ?>
                                <div class="carrossel-slide">
                                    <img src="<?php echo htmlspecialchars(caminhoImagem($imagem['url'])); ?>"
                                         alt="<?php echo htmlspecialchars($anuncio['produto']); ?> - Imagem <?php echo $index + 1; ?>"
                                         loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
                                         <?php echo $index === 0 ? 'fetchpriority="high"' : ''; ?>
                                         decoding="async"
                                         onerror="this.src='../img/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($imagens_produto) > 1): ?>
                            <button class="carrossel-btn prev" id="carrossel-prev" aria-label="Imagem anterior">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="carrossel-btn next" id="carrossel-next" aria-label="Próxima imagem">
                                <i class="fas fa-chevron-right"></i>
                            </button>

                            <div class="carrossel-counter" id="carrossel-counter">
                                1/<?php echo count($imagens_produto); ?>
                            </div>

                            <div class="carrossel-indicators" id="carrossel-indicators">
                                <?php for ($i = 0; $i < count($imagens_produto); $i++): ?>
                                    <div class="carrossel-indicator <?php echo $i === 0 ? 'active' : ''; ?>"
                                         data-index="<?php echo $i; ?>"></div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($imagens_produto) > 1): ?>
                        <div class="carrossel-miniaturas" id="carrossel-miniaturas">
                            <?php foreach ($imagens_produto as $index => $imagem): ?>
                                <div class="miniatura <?php echo $index === 0 ? 'active' : ''; ?>"
                                     data-index="<?php echo $index; ?>">
                                    <img src="<?php echo htmlspecialchars(caminhoImagem($imagem['url'])); ?>"
                                         alt="Miniatura <?php echo $index + 1; ?>"
                                         loading="lazy" decoding="async"
                                         onerror="this.src='../img/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="produto-actions">
                        <?php if ($pode_negociar): ?>
                            <?php if ($favorito_id): ?>
                                <a href="comprador/remover_favorito?favorito_id=<?php echo (int)$favorito_id; ?>" class="btn-action favoritado">
                                    <i class="fas fa-heart"></i><span>Remover dos Favoritos</span>
                                </a>
                            <?php else: ?>
                                <a href="comprador/adicionar_favorito?produto_id=<?php echo $anuncio_id; ?>&redirect=<?php echo $url_volta_favorito; ?>" class="btn-action">
                                    <i class="far fa-heart"></i><span>Adicionar aos Favoritos</span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button type="button" class="btn-action" id="btn-compartilhar" data-url="<?= htmlspecialchars($url_anuncio) ?>">
                            <i class="fas fa-share-alt"></i><span>Copiar link</span>
                        </button>
                    </div>
                </div>

                <div class="compra-section">
                    <div class="produto-info">
                        <div class="info-header">
                            <h2><?php echo htmlspecialchars($anuncio['produto']); ?></h2>
                            <div class="vendedor-info">
                                <span class="vendedor-label">Vendido por:</span>
                                <a href="perfil_vendedor?vendedor_id=<?php echo (int)$anuncio['vendedor_usuario_id']; ?>" class="vendedor-nome">
                                    <?php echo htmlspecialchars($anuncio['nome_vendedor']); ?> <i class="fas fa-external-link-alt"></i>
                                </a>
                                <span class="vendedor-local">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($anuncio['estado_vendedor']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="preco-section preco-dinamico">
                            <?php if ($info_desconto['ativo']): ?>
                                <div class="preco-container">
                                    <span class="preco-antigo">R$ <?php echo number_format($info_desconto['preco_original'], 2, ',', '.'); ?></span>
                                    <span class="preco destaque-oferta" id="preco-atual">R$ <?php echo $preco_display; ?></span>
                                </div>
                                <div class="economia-info">
                                    <i class="fas fa-tag"></i> Economia de R$ <?php echo number_format($info_desconto['preco_original'] - $info_desconto['preco_final'], 2, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                <span class="preco" id="preco-atual">R$ <?php echo $preco_display; ?></span>
                            <?php endif; ?>

                            <div class="unidade-info"><span class="unidade">por <?php echo $unidade; ?></span></div>
                            <div class="valor-info">
                                <div class="valor-unitario" id="valor-unitario">Preço unitário: R$ <?php echo $preco_display; ?></div>
                                <div class="valor-total-label" id="valor-total-label">Total: <span id="valor-total">R$ <?php echo $preco_display; ?></span></div>
                            </div>
                        </div>

                        <div class="estoque-info">
                            <i class="fas fa-box"></i>
                            <span><?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?> disponíveis</span>
                        </div>

                        <?php if ((int)$anuncio['quantidade_disponivel'] <= 0): ?>
                            <div class="aviso-estoque">
                                <i class="fas fa-exclamation-circle"></i>
                                Este anúncio está com o estoque zerado no momento. Você ainda pode visualizar, conversar com o vendedor e solicitar informações sobre reposição ou prazo de disponibilidade.
                            </div>
                        <?php endif; ?>

                        <div class="quantidade-selector">
                            <label for="quantidade">Quantidade:</label>
                            <div class="quantidade-control">
                                <button type="button" class="qty-btn" id="decrease-qty" aria-label="Diminuir quantidade">-</button>
                                <input type="number" id="quantidade" name="quantidade" value="1" min="1" max="<?php echo max(1, (int)$anuncio['quantidade_disponivel']); ?>">
                                <button type="button" class="qty-btn" id="increase-qty" aria-label="Aumentar quantidade">+</button>
                            </div>
                            <span class="unidade-info"><?php echo $unidade; ?></span>
                        </div>

                        <div class="botoes-compra <?php echo !$is_logged_in ? 'botoes-compra-desativados' : ''; ?>">
                            <?php if (!empty($anuncio['paletizado']) && $anuncio['paletizado'] == 1): ?><div><i class="fas fa-cube"></i> Paletizado</div><?php endif; ?>

                            <?php if (!$eh_dono): ?>
                            <div class="proposta-option">
                                <?php if ($pode_negociar && $conta_ativa): ?>
                                    <a href="chat/chat?produto_id=<?php echo $anuncio_id; ?>" class="btn-chat">
                                        <i class="fas fa-comments"></i> Conversar com o Vendedor
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn-chat btn-disabled <?php echo !$is_logged_in ? 'tooltip-desativado' : ''; ?>" disabled>
                                        <i class="fas fa-comments"></i> Conversar com o Vendedor
                                    </button>
                                    <?php if ($pode_negociar): ?>
                                        <div class="status-info alerta"><i class="fas fa-info-circle"></i> Aguarde a aprovação</div>
                                    <?php elseif ($is_logged_in): ?>
                                        <div class="status-info alerta"><i class="fas fa-info-circle"></i> Apenas compradores e vendedores negociam produtos</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <p class="proposta-text">Negocie diretamente com o vendedor</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <a href="https://wa.me/?text=<?= rawurlencode($texto_whatsapp) ?>" class="btn-compartilhar-whatsapp" target="_blank" rel="noopener">
                            <i class="fab fa-whatsapp"></i>
                            Compartilhar no WhatsApp
                        </a>

                        <?php if (!$is_logged_in): ?>
                            <div class="aviso-acao">
                                <p class="status-info alerta">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Para realizar ações neste anúncio, <a href="login"><strong>faça login</strong></a> ou <a href="../index#contato"><strong>crie uma conta</strong></a>.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($estado_comprador) && !empty($estados_atendidos)): ?>
                <?php if ($entrega_disponivel): ?>
                <div class="alerta-logistica alerta-sucesso">
                    <div class="alerta-header"><i class="fas fa-check-circle"></i><h4>Entrega disponível para sua região</h4></div>
                    <div class="alerta-body"><p><i class="fas fa-truck"></i> O vendedor <strong><?php echo htmlspecialchars($anuncio['nome_vendedor']); ?></strong> realiza entregas para o estado de <strong><?php echo htmlspecialchars($estado_comprador); ?></strong>.</p></div>
                </div>
                <?php else: ?>
                <div class="alerta-logistica alerta-atencao">
                    <div class="alerta-header"><i class="fas fa-map-marker-alt"></i><h4>Verifique a disponibilidade de entrega</h4></div>
                    <div class="alerta-body">
                        <p>O vendedor <strong><?php echo htmlspecialchars($anuncio['nome_vendedor']); ?></strong> não realiza entregas diretas para <strong><?php echo htmlspecialchars($estado_comprador); ?></strong>.</p>
                        <p class="alerta-detalhes"><i class="fas fa-map"></i> <strong>Estados onde este vendedor entrega:</strong> <?php echo htmlspecialchars(implode(', ', $estados_atendidos)); ?></p>
                        <p class="alerta-recomendacao"><i class="fas fa-lightbulb"></i> <strong>Sugestões:</strong> Entre em contato via chat ou transportadora.</p>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

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

            <!-- Avaliações do Produto -->
            <div class="avaliacoes-section">
                <div class="avaliacoes-header">
                    <?php if ($total_avaliacoes > 0): ?>
                        <div class="media-avaliacao">
                            <div class="numero-media"><?php echo $media_avaliacao; ?></div>
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
                            <div class="total-avaliacoes"><?php echo $total_avaliacoes; ?> <?php echo $total_avaliacoes === 1 ? 'avaliação' : 'avaliações'; ?></div>
                        </div>
                        <div class="avaliacoes-info">
                            <h3><i class="fas fa-comments"></i> Avaliações dos Clientes</h3>
                            <p>Veja o que os compradores acham deste produto</p>
                        </div>
                    <?php else: ?>
                        <div class="avaliacoes-info">
                            <h3><i class="fas fa-comments"></i> Avaliações dos Clientes</h3>
                            <p>Este produto ainda não tem avaliações. Seja o primeiro a avaliar!</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($mostrar_avaliar): ?>
                        <div class="botao-avaliar">
                            <a href="avaliar?tipo=produto&produto_id=<?php echo $anuncio_id; ?>" class="btn-avaliar">
                                <i class="fas fa-star"></i>Avaliar este produto
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($avaliacoes_limitadas)): ?>
                    <div class="avaliacoes-lista">
                        <?php foreach ($avaliacoes_limitadas as $av): ?>
                            <div class="avaliacao-item">
                                <div class="avaliacao-header">
                                    <div>
                                        <div class="avaliador-nome">
                                            <?php echo htmlspecialchars($av['nome'] ?? 'Comprador Anônimo'); ?>
                                        </div>
                                        <div class="avaliacao-data">
                                            <?php echo (new DateTime($av['data_criacao']))->format('d/m/Y'); ?>
                                        </div>
                                    </div>
                                    <div class="avaliacao-nota">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= (int)$av['nota']
                                                ? '<i class="fas fa-star estrela-cheia"></i>'
                                                : '<i class="far fa-star estrela-vazia"></i>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php if (!empty($av['comentario'])): ?>
                                    <div class="avaliacao-comentario">
                                        <?php echo nl2br(htmlspecialchars($av['comentario'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_avaliacoes > 3): ?>
                        <div class="ver-mais-avaliacoes">
                            <?php if ($is_logged_in): ?>
                                <a href="avaliacoes?tipo=produto&id=<?php echo $anuncio_id; ?>" class="btn-ver-mais">
                                    <i class="fas fa-eye"></i> Ver todas as <?php echo $total_avaliacoes; ?> avaliações
                                </a>
                            <?php else: ?>
                                <a href="login" class="btn-ver-mais btn-ver-mais-login">
                                    <i class="fas fa-sign-in-alt"></i> Faça login para ver todas as <?php echo $total_avaliacoes; ?> avaliações
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sem-avaliacoes">
                        <div><i class="fas fa-star"></i></div>
                        <p>Nenhuma avaliação ainda. Compre este produto e deixe sua avaliação!</p>
                    </div>
                <?php endif; ?>
            </div>

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
                    ?>
                        <div class="anuncio-card <?php echo $desc_rel['ativo'] ? 'card-desconto' : ''; ?>">
                            <a href="visualizar_anuncio?anuncio_id=<?php echo (int)$produto['id']; ?>" class="produto-link">
                                <div class="card-image">
                                    <?php if ($desc_rel['ativo']): ?>
                                        <div class="badge-desconto">-<?php echo $desc_rel['porcentagem']; ?>%</div>
                                    <?php endif; ?>
                                    <img src="<?php echo htmlspecialchars(caminhoImagem($produto['imagem_url'])); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" loading="lazy" decoding="async" onerror="this.src='../img/placeholder.png'">
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

    <?php require __DIR__ . '/includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Seletor de quantidade e total
            const quantidadeInput = document.getElementById('quantidade');
            const decreaseBtn = document.getElementById('decrease-qty');
            const increaseBtn = document.getElementById('increase-qty');
            const precoAtualElement = document.getElementById('preco-atual');
            const valorTotalElement = document.getElementById('valor-total');
            const valorUnitarioElement = document.getElementById('valor-unitario');
            const valorTotalLabel = document.getElementById('valor-total-label');
            const precoUnitario = <?php echo json_encode((float)$preco_unitario); ?>;
            const unidade = <?php echo json_encode($anuncio['unidade_medida'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;

            function formatarValor(valor) { return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
            function atualizarValorTotal() {
                const quantidade = parseInt(quantidadeInput.value);
                if (!quantidade || quantidade <= 0) return;
                const valorUnitarioFormatado = formatarValor(precoUnitario);
                const valorTotalFormatado = formatarValor(precoUnitario * quantidade);
                if (quantidade === 1) {
                    precoAtualElement.textContent = `R$ ${valorUnitarioFormatado}`;
                    valorTotalLabel.style.display = 'none';
                } else {
                    precoAtualElement.textContent = `R$ ${valorTotalFormatado}`;
                    valorTotalLabel.style.display = 'block';
                    valorTotalElement.textContent = `R$ ${valorTotalFormatado}`;
                }
                valorUnitarioElement.textContent = `Preço unitário: R$ ${valorUnitarioFormatado} por ${unidade}`;
            }
            atualizarValorTotal();

            decreaseBtn.addEventListener('click', () => { if (quantidadeInput.value > 1) { quantidadeInput.value = parseInt(quantidadeInput.value) - 1; atualizarValorTotal(); } });
            increaseBtn.addEventListener('click', () => { const max = parseInt(quantidadeInput.max); if (quantidadeInput.value < max) { quantidadeInput.value = parseInt(quantidadeInput.value) + 1; atualizarValorTotal(); } });
            quantidadeInput.addEventListener('change', () => { let value = parseInt(quantidadeInput.value); const max = parseInt(quantidadeInput.max); const min = parseInt(quantidadeInput.min); if (!value || value < min) value = min; if (value > max) value = max; quantidadeInput.value = value; atualizarValorTotal(); });
            quantidadeInput.addEventListener('input', atualizarValorTotal);

            // Copiar link do anúncio (endereço público, que abre sem login)
            const btnCompartilhar = document.getElementById('btn-compartilhar');
            if (btnCompartilhar) {
                btnCompartilhar.addEventListener('click', function() {
                    const rotulo = this.querySelector('span');
                    navigator.clipboard.writeText(this.dataset.url).then(() => {
                        rotulo.textContent = 'Link copiado!';
                        setTimeout(() => { rotulo.textContent = 'Copiar link'; }, 2000);
                    });
                });
            }

            // Botões desativados para visitantes
            document.querySelectorAll('.tooltip-desativado').forEach(tooltip => {
                tooltip.addEventListener('mouseenter', function() { this.style.opacity = '0.7'; });
                tooltip.addEventListener('mouseleave', function() { this.style.opacity = '0.6'; });
            });

            // Carrossel
            const carrosselSlides = document.getElementById('carrossel-slides');
            const carrosselPrev = document.getElementById('carrossel-prev');
            const carrosselNext = document.getElementById('carrossel-next');
            const carrosselCounter = document.getElementById('carrossel-counter');
            const carrosselIndicators = document.querySelectorAll('.carrossel-indicator');
            const carrosselMiniaturas = document.querySelectorAll('.miniatura');
            const totalSlides = <?php echo count($imagens_produto); ?>;
            let currentSlide = 0;

            function updateCarrossel() {
                carrosselSlides.style.transform = `translateX(-${currentSlide * 100}%)`;
                if (carrosselCounter) carrosselCounter.textContent = `${currentSlide + 1}/${totalSlides}`;
                carrosselIndicators.forEach((ind, i) => ind.classList.toggle('active', i === currentSlide));
                carrosselMiniaturas.forEach((min, i) => {
                    min.classList.toggle('active', i === currentSlide);
                    if (i === currentSlide) min.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                });
                if (carrosselPrev) carrosselPrev.disabled = currentSlide === 0;
                if (carrosselNext) carrosselNext.disabled = currentSlide === totalSlides - 1;
            }
            function irPara(i) { if (i >= 0 && i < totalSlides && i !== currentSlide) { currentSlide = i; updateCarrossel(); } }

            if (totalSlides > 1 && carrosselPrev && carrosselNext) {
                carrosselPrev.addEventListener('click', () => irPara(currentSlide - 1));
                carrosselNext.addEventListener('click', () => irPara(currentSlide + 1));
                carrosselIndicators.forEach(ind => ind.addEventListener('click', () => irPara(parseInt(ind.dataset.index))));
                carrosselMiniaturas.forEach(min => min.addEventListener('click', () => irPara(parseInt(min.dataset.index))));
                document.addEventListener('keydown', (e) => {
                    if (e.target.matches('input, textarea')) return;
                    if (e.key === 'ArrowLeft') irPara(currentSlide - 1);
                    else if (e.key === 'ArrowRight') irPara(currentSlide + 1);
                });

                // Arrastar com o dedo no celular
                let toqueX = null;
                carrosselSlides.addEventListener('touchstart', e => { toqueX = e.touches[0].clientX; }, { passive: true });
                carrosselSlides.addEventListener('touchend', e => {
                    if (toqueX === null) return;
                    const dx = e.changedTouches[0].clientX - toqueX;
                    if (Math.abs(dx) > 40) irPara(currentSlide + (dx < 0 ? 1 : -1));
                    toqueX = null;
                });
                updateCarrossel();
            }
        });
    </script>
</body>
</html>
