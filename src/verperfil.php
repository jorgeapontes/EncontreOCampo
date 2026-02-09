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
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <title>Ver Perfil - <?php echo htmlspecialchars($user['nome'] ?? 'Usuário'); ?></title>
    <link rel="stylesheet" href="css/vendedor/vendas.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
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
                <div style="display: flex; align-items: center; flex-direction: column;">
                <?php
            // Buscar avaliações do usuário (vendedor, comprador ou transportador)
            $media_avaliacao = 0;
            $total_avaliacoes = 0;
            $tipo_avaliacao = ''; // Para saber qual tipo de avaliação mostrar
            
            // Buscar e exibir avaliações do usuário
            $media_avaliacao = 0;
            $total_avaliacoes = 0;
            $tipo_avaliacao = null;
            
            if ($profile_id > 0) {
                try {
                    // Verificar se é vendedor e buscar o ID da tabela vendedores
                    $sql_check_vendedor = "SELECT id FROM vendedores WHERE usuario_id = ?";
                    $stmt_check = $db->prepare($sql_check_vendedor);
                    $stmt_check->execute([$profile_id]);
                    $vendedor_row = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    // Verificar se é comprador e buscar o ID da tabela compradores
                    $sql_check_comprador = "SELECT id FROM compradores WHERE usuario_id = ?";
                    $stmt_check2 = $db->prepare($sql_check_comprador);
                    $stmt_check2->execute([$profile_id]);
                    $comprador_row = $stmt_check2->fetch(PDO::FETCH_ASSOC);
                    
                    // Verificar se é transportador e buscar o ID da tabela transportadores
                    $sql_check_transportador = "SELECT id FROM transportadores WHERE usuario_id = ?";
                    $stmt_check3 = $db->prepare($sql_check_transportador);
                    $stmt_check3->execute([$profile_id]);
                    $transportador_row = $stmt_check3->fetch(PDO::FETCH_ASSOC);
                    
                    // Determinar qual tipo de avaliação buscar e qual ID usar
                    $id_para_buscar = null;
                    
                    if ($vendedor_row) {
                        $tipo_avaliacao = 'vendedor';
                        $coluna_id = 'vendedor_id';
                        $id_para_buscar = $profile_id; 
                    } elseif ($comprador_row) {
                        $tipo_avaliacao = 'comprador';
                        $coluna_id = 'comprador_id';
                        $id_para_buscar = $profile_id; 
                    } elseif ($transportador_row) {
                        $tipo_avaliacao = 'transportador';
                        $coluna_id = 'transportador_id';
                        $id_para_buscar = $profile_id; 
                    }
                    
                    // Buscar avaliações se houver tipo definido
                    if (!empty($tipo_avaliacao) && $id_para_buscar) {
                        $sql_avaliacoes = "SELECT nota 
                                           FROM avaliacoes 
                                           WHERE tipo = :tipo 
                                           AND $coluna_id = :id_tabela
                                           ORDER BY data_criacao DESC";
                        
                        $stmt_avaliacoes = $db->prepare($sql_avaliacoes);
                        $stmt_avaliacoes->bindParam(':tipo', $tipo_avaliacao);
                        $stmt_avaliacoes->bindParam(':id_tabela', $id_para_buscar, PDO::PARAM_INT);
                        $stmt_avaliacoes->execute();
                        $avaliacoes = $stmt_avaliacoes->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Calcular média das avaliações
                        if (!empty($avaliacoes)) {
                            $soma_notas = 0;
                            foreach ($avaliacoes as $av) {
                                $soma_notas += (int)$av['nota'];
                            }
                            $media_avaliacao = round($soma_notas / count($avaliacoes), 1);
                            $total_avaliacoes = count($avaliacoes);
                        }
                    }
                } catch (Exception $e) {
                    // Ignorar erros na busca de avaliações
                    error_log("Erro ao buscar avaliações: " . $e->getMessage());
                }
            }
            
            // Determinar rótulo para o tipo
            $rotulo_tipo = '';
            switch ($tipo_avaliacao) {
                case 'vendedor': $rotulo_tipo = 'Vendedor'; break;
                case 'comprador': $rotulo_tipo = 'Comprador'; break;
                case 'transportador': $rotulo_tipo = 'Transportador'; break;
            }
        ?>
        
        <?php if (!empty($tipo_avaliacao) && $total_avaliacoes > 0): ?>
        <div class="avaliacao-vendedor" >
            <div class="media-avaliacao-vendedor" style="display: flex; align-items: center; gap: 10px;">
                <div class="numero-media-vendedor" style="font-size: 1.8em; font-weight: 700; color: #ff9800;">
                    <?php echo $media_avaliacao; ?>
                </div>
                <div>
                    <div class="estrelas-media-vendedor" style="display: flex; gap: 3px; margin-bottom: 5px;">
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
                    <div class="total-avaliacoes-vendedor" style="color: #666; font-size: 0.9em;">
                        <?php echo $total_avaliacoes; ?> <?php echo $total_avaliacoes === 1 ? 'avaliação' : 'avaliações'; ?>
                    </div>
                </div>
            </div>
            <div style="margin-top: 8px;">
                <a href="avaliacoes.php?tipo=<?php echo $tipo_avaliacao; ?>&id=<?php echo urlencode($profile_id); ?>" 
                   style="font-size: 0.85em; color: #007bff; text-decoration: none;">
                    Ver todas as avaliações <i class="fas fa-external-link-alt" ></i>
                </a>
            </div>
        </div>
        <?php elseif (!empty($tipo_avaliacao)): ?>
        <div style="margin: 10px 0 15px 0; padding: 10px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; width: fit-content;">
            <div style="color: #999; font-size: 0.9em;">
                <i class="far fa-star"></i> <?php echo $rotulo_tipo; ?> ainda não tem avaliações
            </div>
        </div>
        <?php endif; ?>
                <div class="btn-avaliar-vendedor">
                <?php
                    if ($viewer_id && isset($user['id']) && $viewer_id != $profile_id) {
                        try {
                            $tipo_avaliacao = null;
                            $id_para_avaliar = null;
                            $mostrar_botao = false;
                            
                            // ========================================
                            // CENÁRIO 1: Viewer (VENDEDOR) avaliando COMPRADOR
                            // ========================================
                            $sql_vendedor_comprador = "
                                SELECT p.comprador_id
                                FROM propostas p 
                                WHERE p.vendedor_id = :viewer
                                AND p.comprador_id = :profile
                                AND p.status = 'aceita' 
                                LIMIT 1
                            ";
                            
                            $stmt1 = $db->prepare($sql_vendedor_comprador);
                            $stmt1->bindParam(':viewer', $viewer_id, PDO::PARAM_INT);
                            $stmt1->bindParam(':profile', $profile_id, PDO::PARAM_INT);
                            $stmt1->execute();
                            $result1 = $stmt1->fetch(PDO::FETCH_ASSOC);
                            
                            if ($result1) {
                                $tipo_avaliacao = 'comprador';
                                // Buscar o ID real da tabela compradores
                                $sql_comp_id = "SELECT id FROM compradores WHERE usuario_id = :usuario_id LIMIT 1";
                                $stmt_cid = $db->prepare($sql_comp_id);
                                $stmt_cid->bindParam(':usuario_id', $profile_id, PDO::PARAM_INT);
                                $stmt_cid->execute();
                                $comp_row = $stmt_cid->fetch(PDO::FETCH_ASSOC);
                                if ($comp_row) {
                                    $id_para_avaliar = $comp_row['id'];
                                    $mostrar_botao = true;
                                }
                            }
                            
                            // ========================================
                            // CENÁRIO 2: Viewer (COMPRADOR) avaliando VENDEDOR
                            // ========================================
                            if (!$mostrar_botao) {
                                $sql_comprador_vendedor = "
                                    SELECT p.vendedor_id
                                    FROM propostas p 
                                    WHERE p.vendedor_id = :profile 
                                    AND p.comprador_id = :viewer
                                    AND p.status = 'aceita' 
                                    LIMIT 1
                                ";
                                
                                $stmt2 = $db->prepare($sql_comprador_vendedor);
                                $stmt2->bindParam(':profile', $profile_id, PDO::PARAM_INT);
                                $stmt2->bindParam(':viewer', $viewer_id, PDO::PARAM_INT);
                                $stmt2->execute();
                                $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                                
                                if ($result2) {
                                    $tipo_avaliacao = 'vendedor';
                                    // Buscar o ID real da tabela vendedores
                                    $sql_vend_id = "SELECT id FROM vendedores WHERE usuario_id = :usuario_id LIMIT 1";
                                    $stmt_vid = $db->prepare($sql_vend_id);
                                    $stmt_vid->bindParam(':usuario_id', $profile_id, PDO::PARAM_INT);
                                    $stmt_vid->execute();
                                    $vend_row = $stmt_vid->fetch(PDO::FETCH_ASSOC);
                                    if ($vend_row) {
                                        $id_para_avaliar = $vend_row['id'];
                                        $mostrar_botao = true;
                                    }
                                }
                            }
                            
                            // ========================================
                            // CENÁRIO 3: Avaliando TRANSPORTADOR
                            // ========================================
                            if (!$mostrar_botao) {
                                // Viewer (vendedor ou comprador) avaliando transportador
                                $sql_avaliar_transportador = "
                                    SELECT e.transportador_id
                                    FROM entregas e
                                    LEFT JOIN transportadores t ON e.transportador_id = t.id
                                    WHERE t.usuario_id = :profile
                                    AND (e.vendedor_id = :viewer OR e.comprador_id = :viewer)
                                    AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada')
                                    LIMIT 1
                                ";
                                
                                $stmt3 = $db->prepare($sql_avaliar_transportador);
                                $stmt3->bindParam(':profile', $profile_id, PDO::PARAM_INT);
                                $stmt3->bindParam(':viewer', $viewer_id, PDO::PARAM_INT);
                                $stmt3->execute();
                                $result3 = $stmt3->fetch(PDO::FETCH_ASSOC);
                                
                                if ($result3) {
                                    $tipo_avaliacao = 'transportador';
                                    $id_para_avaliar = $result3['transportador_id'];
                                    $mostrar_botao = true;
                                }
                            }
                            
                            // ========================================
                            // CENÁRIO 4: TRANSPORTADOR avaliando VENDEDOR
                            // ========================================
                            if (!$mostrar_botao) {
                                $sql_transp_vendedor = "
                                    SELECT e.vendedor_id
                                    FROM entregas e
                                    LEFT JOIN transportadores t ON e.transportador_id = t.id
                                    WHERE t.usuario_id = :viewer
                                    AND e.vendedor_id = :profile
                                    AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada')
                                    LIMIT 1
                                ";
                                
                                $stmt4 = $db->prepare($sql_transp_vendedor);
                                $stmt4->bindParam(':viewer', $viewer_id, PDO::PARAM_INT);
                                $stmt4->bindParam(':profile', $profile_id, PDO::PARAM_INT);
                                $stmt4->execute();
                                $result4 = $stmt4->fetch(PDO::FETCH_ASSOC);
                                
                                if ($result4) {
                                    $tipo_avaliacao = 'vendedor';
                                    $sql_vend_id = "SELECT id FROM vendedores WHERE usuario_id = :usuario_id LIMIT 1";
                                    $stmt_vid = $db->prepare($sql_vend_id);
                                    $stmt_vid->bindParam(':usuario_id', $profile_id, PDO::PARAM_INT);
                                    $stmt_vid->execute();
                                    $vend_row = $stmt_vid->fetch(PDO::FETCH_ASSOC);
                                    if ($vend_row) {
                                        $id_para_avaliar = $vend_row['id'];
                                        $mostrar_botao = true;
                                    }
                                }
                            }
                            
                            // ========================================
                            // CENÁRIO 5: TRANSPORTADOR avaliando COMPRADOR
                            // ========================================
                            if (!$mostrar_botao) {
                                $sql_transp_comprador = "
                                    SELECT e.comprador_id
                                    FROM entregas e
                                    LEFT JOIN transportadores t ON e.transportador_id = t.id
                                    WHERE t.usuario_id = :viewer
                                    AND e.comprador_id = :profile
                                    AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada')
                                    LIMIT 1
                                ";
                                
                                $stmt5 = $db->prepare($sql_transp_comprador);
                                $stmt5->bindParam(':viewer', $viewer_id, PDO::PARAM_INT);
                                $stmt5->bindParam(':profile', $profile_id, PDO::PARAM_INT);
                                $stmt5->execute();
                                $result5 = $stmt5->fetch(PDO::FETCH_ASSOC);
                                
                                if ($result5) {
                                    $tipo_avaliacao = 'comprador';
                                    $sql_comp_id = "SELECT id FROM compradores WHERE usuario_id = :usuario_id LIMIT 1";
                                    $stmt_cid = $db->prepare($sql_comp_id);
                                    $stmt_cid->bindParam(':usuario_id', $profile_id, PDO::PARAM_INT);
                                    $stmt_cid->execute();
                                    $comp_row = $stmt_cid->fetch(PDO::FETCH_ASSOC);
                                    if ($comp_row) {
                                        $id_para_avaliar = $comp_row['id'];
                                        $mostrar_botao = true;
                                    }
                                }
                            }
                            
                            // ========================================
                            // EXIBIR BOTÃO
                            // ========================================
                            if ($mostrar_botao && $tipo_avaliacao && $id_para_avaliar) {
                                $textos = [
                                    'vendedor' => 'Avaliar este vendedor',
                                    'comprador' => 'Avaliar este comprador',
                                    'transportador' => 'Avaliar este transportador'
                                ];
                                
                                $texto_botao = $textos[$tipo_avaliacao] ?? '';
                                $param_nome = $tipo_avaliacao . '_id';
                                $url_avaliacao = 'avaliar.php?tipo=' . urlencode($tipo_avaliacao) . '&' . $param_nome . '=' . urlencode($profile_id);
                                
                                if ($texto_botao) {
                                    echo '<div style="margin-top:12px;">';
                                    echo '<a href="' . htmlspecialchars($url_avaliacao) . '" class="btn btn-info">';
                                    echo htmlspecialchars($texto_botao);
                                    echo '</a>';
                                    echo '</div>';
                                }
                            }
                            
                        } catch (Exception $e) {
                            error_log("Erro ao verificar permissão de avaliação: " . $e->getMessage());
                        }
                    }
                ?>
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
                                    $link = './comprador/view_ad.php?anuncio_id=' . intval($n['produto_id']); ?>
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
