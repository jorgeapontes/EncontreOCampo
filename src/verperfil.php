<?php
// src/verperfil.php
session_start();
require_once 'conexao.php';

$database = new Database();
$db = $database->getConnection();

$viewer_id = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : null;
$profile_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;

if (!$profile_id) {
    echo 'Usuário não informado.';
    exit;
}

// Buscar dados do usuário (com possíveis campos de vendedor)
$stmt = $db->prepare("SELECT u.*, v.foto_perfil_url AS v_foto, v.cep AS v_cep, v.rua AS v_rua, v.numero AS v_numero, v.complemento AS v_complemento, v.cidade AS v_cidade, v.estado AS v_estado, v.telefone1 AS v_telefone1,
                      c.foto_perfil_url AS c_foto, c.cep AS c_cep, c.rua AS c_rua, c.numero AS c_numero, c.complemento AS c_complemento, c.cidade AS c_cidade, c.estado AS c_estado, c.telefone1 AS c_telefone1
                      FROM usuarios u
                      LEFT JOIN vendedores v ON v.usuario_id = u.id
                      LEFT JOIN compradores c ON c.usuario_id = u.id
                      WHERE u.id = :id LIMIT 1");
$stmt->bindParam(':id', $profile_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
    echo 'Usuário não encontrado.';
    exit;
}

function resolveWebImagePath(array $candidates, $fallback) {
    $projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    $srcDir = realpath(__DIR__);
    foreach ($candidates as $cand) {
        // try path as given relative to src/
        $fs1 = realpath(__DIR__ . DIRECTORY_SEPARATOR . $cand);
        if ($fs1 && file_exists($fs1)) {
            if ($projectRoot && strpos($fs1, $projectRoot . DIRECTORY_SEPARATOR) === 0) {
                $rel = substr($fs1, strlen($projectRoot) + 1);
                return '../' . str_replace('\\', '/', $rel);
            }
            if ($srcDir && strpos($fs1, $srcDir . DIRECTORY_SEPARATOR) === 0) {
                $rel = substr($fs1, strlen($srcDir) + 1);
                return str_replace('\\', '/', $rel);
            }
            return str_replace('\\', '/', $cand);
        }

        // try relative to project root (one level up)
        $fs2 = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $cand);
        if ($fs2 && file_exists($fs2)) {
            if ($projectRoot && strpos($fs2, $projectRoot . DIRECTORY_SEPARATOR) === 0) {
                $rel = substr($fs2, strlen($projectRoot) + 1);
                return '../' . str_replace('\\', '/', $rel);
            }
            return str_replace('\\', '/', $cand);
        }

        // try two levels up
        $fs3 = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $cand);
        if ($fs3 && file_exists($fs3)) {
            if ($projectRoot && strpos($fs3, $projectRoot . DIRECTORY_SEPARATOR) === 0) {
                $rel = substr($fs3, strlen($projectRoot) + 1);
                return '../' . str_replace('\\', '/', $rel);
            }
            return str_replace('\\', '/', $cand);
        }
    }
    return $fallback;
}

function getImagePath($path) {
    $default = '../img/no-user-image.png';
    if (empty($path)) return $default;
    if (preg_match('#^https?://#i', $path)) return $path;

    // Build candidates by removing leading ./ or ../ sequences
    $raw = $path;
    while (substr($raw, 0, 2) === './') $raw = substr($raw, 2);
    while (substr($raw, 0, 3) === '../') $raw = substr($raw, 3);
    $candidates = [$path, $raw];
    return resolveWebImagePath($candidates, $default);
}

function getNormalizedImage($path, $fallback = '../img/placeholder.png') {
    if (empty($path)) return $fallback;
    if (preg_match('#^https?://#i', $path)) return $path;

    $raw = $path;
    while (substr($raw, 0, 2) === './') $raw = substr($raw, 2);
    while (substr($raw, 0, 3) === '../') $raw = substr($raw, 3);
    $candidates = [$path, $raw];
    return resolveWebImagePath($candidates, $fallback);
}

// Buscar negociações entre viewer e profile (se viewer existir)
$negociacoes = [];
if ($viewer_id) {
    $sql = "SELECT p.*, pr.nome as produto_nome, pr.imagem_url as produto_imagem, uc.nome as comprador_nome, uv.nome as vendedor_nome
            FROM propostas p
            LEFT JOIN produtos pr ON p.produto_id = pr.id
            LEFT JOIN usuarios uc ON p.comprador_id = uc.id
            LEFT JOIN usuarios uv ON p.vendedor_id = uv.id
            WHERE (p.vendedor_id = :viewer AND p.comprador_id = :profile)
               OR (p.vendedor_id = :profile AND p.comprador_id = :viewer)
            ORDER BY p.data_inicio DESC LIMIT 100";
    $st = $db->prepare($sql);
    $st->bindParam(':viewer', $viewer_id, PDO::PARAM_INT);
    $st->bindParam(':profile', $profile_id, PDO::PARAM_INT);
    $st->execute();
    $negociacoes = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Ver Perfil - <?php echo htmlspecialchars($user['nome'] ?? 'Usuário'); ?></title>
    <link rel="stylesheet" href="css/vendedor/vendas.css">
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
                        <a href="../../index.php" class="nav-link">Home</a>
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
            <?php
                // Mostrar botão de avaliar vendedor se o viewer comprou deste vendedor e situação permitir
                if ($viewer_id && isset($user['id'])) {
                    try {
                        $sql_check = "SELECT p.produto_id, p.opcao_frete FROM propostas p LEFT JOIN compradores c ON p.comprador_id = c.id WHERE p.vendedor_id = :profile AND (p.comprador_id = :viewer OR c.usuario_id = :viewer) AND p.status = 'aceita' ORDER BY p.data_inicio DESC LIMIT 1";
                        $stc = $db->prepare($sql_check);
                        $stc->bindParam(':profile', $profile_id, PDO::PARAM_INT);
                        $stc->bindParam(':viewer', $viewer_id, PDO::PARAM_INT);
                        $stc->execute();
                        $rowc = $stc->fetch(PDO::FETCH_ASSOC);
                        $mostrar_avaliar_vendedor = false;
                        if ($rowc) {
                            $op = $rowc['opcao_frete'] ?? null;
                            $produto_rel = $rowc['produto_id'] ?? null;
                            if (in_array($op, ['vendedor','comprador'])) {
                                $mostrar_avaliar_vendedor = true;
                            } elseif ($op === 'entregador' && $produto_rel) {
                                $sql_ent = "SELECT id FROM entregas WHERE produto_id = :produto_id AND comprador_id = :viewer AND (status = 'entregue' OR status_detalhado = 'finalizada') LIMIT 1";
                                $ste = $db->prepare($sql_ent);
                                $ste->bindParam(':produto_id', $produto_rel, PDO::PARAM_INT);
                                $ste->bindParam(':viewer', $viewer_id, PDO::PARAM_INT);
                                $ste->execute();
                                if ($ste->fetch(PDO::FETCH_ASSOC)) $mostrar_avaliar_vendedor = true;
                            }
                        }

                        if ($mostrar_avaliar_vendedor) {
                            echo '<div style="margin-top:12px;"><a href="avaliar.php?tipo=vendedor&vendedor_id='.urlencode($profile_id).'" class="btn btn-info">Avaliar este vendedor</a></div>';
                        }
                    } catch (Exception $e) {
                        // ignorar erros de verificação
                    }
                }
            ?>
        </nav>
    </header>

    <div class="main-content">
        <section class="section-anuncios">
            <div style="display:flex;gap:20px;align-items:flex-start;margin-top:16px;">
                <div style="width:140px;">
                    <?php
                        // escolher foto do vendedor > comprador > usuário
                        $foto = $user['v_foto'] ?? $user['c_foto'] ?? ($user['foto_perfil_url'] ?? '');
                        $foto_path = getImagePath($foto);
                    ?>
                    <img src="<?php echo htmlspecialchars($foto_path); ?>" alt="Foto" style="width:140px;height:140px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
                </div>
                <div style="flex:1;">
                    <h2><?php echo htmlspecialchars($user['nome']); ?></h2>
                    <?php
                        // mostrar email, se existir
                        $email = $user['email'] ?? '';
                        if (!empty($email)):
                    ?>
                        <div><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></div>
                    <?php endif; ?>

                    <?php
                        // telefone preferencial: vendedor.telefone1 > comprador.telefone1 > usuários.telefone
                        $phone = $user['v_telefone1'] ?? $user['c_telefone1'] ?? $user['telefone'] ?? '';
                        if (!empty($phone)):
                    ?>
                        <div><strong>Telefone:</strong> <?php echo htmlspecialchars($phone); ?></div>
                    <?php endif; ?>

                    <div style="margin-top:8px;"><strong>Endereço:</strong>
                        <div>
                            <?php
                            // preferir dados do vendedor, senão comprador
                            $parts = [];
                            $rua = $user['v_rua'] ?? $user['c_rua'] ?? '';
                            $numero = $user['v_numero'] ?? $user['c_numero'] ?? '';
                            $complemento = $user['v_complemento'] ?? $user['c_complemento'] ?? '';
                            $cidade = $user['v_cidade'] ?? $user['c_cidade'] ?? '';
                            $estado = $user['v_estado'] ?? $user['c_estado'] ?? '';
                            if (!empty($rua)) $parts[] = $rua;
                            if (!empty($numero)) $parts[] = $numero;
                            if (!empty($complemento)) $parts[] = $complemento;
                            if (!empty($cidade)) $parts[] = $cidade;
                            if (!empty($estado)) $parts[] = $estado;
                            $endereco = implode(', ', array_filter($parts));
                            if (!empty($endereco)):
                                $maps = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($endereco);
                                echo '<a href="' . htmlspecialchars($maps) . '" target="_blank" rel="noopener">' . htmlspecialchars($endereco) . '</a>';
                            else:
                                echo '—';
                            endif;
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <hr style="margin:18px 0;">

            <h3>Negociações com você</h3>
            <?php if ($viewer_id): ?>
                <?php if (count($negociacoes) > 0): ?>
                    <div class="cards-list" style="margin-top:12px;">
                        <?php foreach ($negociacoes as $n): ?>
                            <div class="proposal-card" id="proposal-<?php echo $n['ID']; ?>">
                                <div class="card-image">
                                <?php $raw_img = !empty($n['produto_imagem']) ? $n['produto_imagem'] : '';
                                    $img = getNormalizedImage($raw_img, '../img/placeholder.png');
                                    $link = 'visualizar_anuncio.php?anuncio_id=' . intval($n['produto_id']); ?>
                                <a href="<?php echo $link; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($n['produto_nome'] ?? 'Produto'); ?>"></a>
                              </div>
                                <div class="card-content">
                                    <div class="card-row">
                                        <div class="card-title">
                                            <a href="<?php echo $link; ?>" class="card-link"><h3><?php echo htmlspecialchars($n['produto_nome'] ?? 'Produto'); ?></h3></a>
                                            <small class="date"><?php echo date('d/m/Y H:i', strtotime($n['data_inicio'])); ?></small>
                                        </div>
                                    </div>

                                    <div class="card-grid">
                                        <div><strong>Quantidade</strong><div><?php echo intval($n['quantidade_proposta']); ?></div></div>
                                        <div><strong>Valor</strong><div>R$ <?php echo number_format($n['valor_total'], 2, ',', '.'); ?></div></div>
                                    </div>

                                    <div class="card-actions">
                                        <?php if (!empty($n['confirmado'])): ?>
                                            <span class="confirmed-badge"> Pagamento Confirmado</span>
                                        <?php else: ?>
                                            <span class="status"><?php echo htmlspecialchars($n['status']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Nenhuma negociação encontrada entre você e este usuário.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Faça login para ver negociações relacionadas a este perfil.</p>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
