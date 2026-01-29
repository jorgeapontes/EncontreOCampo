<?php
// src/vendedor/anuncios.php
session_start();
require_once '../conexao.php'; 

// Verificar se o usuário está logado como vendedor
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();
$usuario_id = $_SESSION['usuario_id'];

// 1. BUSCAR VENDEDOR E O LIMITE DO PLANO ATUAL (JOIN com a tabela planos)
// Pegamos o limite_total_anuncios direto do banco de dados
$sql_dados = "SELECT v.id as vendedor_id, v.plano_id, p.nome as nome_plano, p.limite_total_anuncios
              FROM vendedores v
              LEFT JOIN planos p ON v.plano_id = p.id
              WHERE v.usuario_id = :usuario_id";

$stmt = $conn->prepare($sql_dados);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$dados_vendedor = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dados_vendedor) {
    $vendedor_id = $dados_vendedor['vendedor_id'];
    $_SESSION['vendedor_id'] = $vendedor_id;
    
    // Define o limite. Se por algum erro vier vazio, assume 1 (Grátis)
    $limite_permitido = $dados_vendedor['limite_total_anuncios'] ?? 1;
    $nome_plano_atual = $dados_vendedor['nome_plano'] ?? 'Plano Básico';
} else {
    // Se não achar vendedor, redireciona
    header('Location: ../index.php');
    exit();
}

// 2. BUSCAR ANÚNCIOS ORDENADOS PELO ID (Antigos primeiro)
// Os anúncios com ID menor (mais antigos) terão prioridade na listagem
$sql = "SELECT p.* FROM produtos p 
        WHERE p.vendedor_id = :vendedor_id 
        ORDER BY p.id ASC"; 

$stmt = $conn->prepare($sql);
$stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
$stmt->execute();
$anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processamento dos dados para exibição (Unidades, Imagens, etc)
foreach ($anuncios as &$a) {
    // Lógica de estoque visual
    $modo = $a['modo_precificacao'] ?? 'por_quilo';
    $estoque_unidades = isset($a['estoque_unidades']) ? $a['estoque_unidades'] : null;
    $estoque_kg = isset($a['estoque']) ? $a['estoque'] : null;

    if ($estoque_unidades !== null && $estoque_kg !== null) {
        // Ambos os estoques existem: usar o menor valor para evitar inconsistência visual
        $a['quantidade_disponivel'] = min((float)$estoque_kg, (float)$estoque_unidades);
    } else {
        if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
            $a['quantidade_disponivel'] = $estoque_unidades ?? ($estoque_kg ?? 0);
        } else {
            $a['quantidade_disponivel'] = $estoque_kg ?? ($estoque_unidades ?? 0);
        }
    }

    // Lógica de unidade de medida
    switch ($modo) {
        case 'por_unidade': $a['unidade_medida_exib'] = 'unidade'; break;
        case 'por_quilo': $a['unidade_medida_exib'] = 'kg'; break;
        case 'caixa_unidades': $a['unidade_medida_exib'] = 'caixa' . (!empty($a['embalagem_unidades']) ? " ({$a['embalagem_unidades']} unid)" : ''); break;
        case 'caixa_quilos': $a['unidade_medida_exib'] = 'caixa' . (!empty($a['embalagem_peso_kg']) ? " ({$a['embalagem_peso_kg']} kg)" : ''); break;
        case 'saco_unidades': $a['unidade_medida_exib'] = 'saco' . (!empty($a['embalagem_unidades']) ? " ({$a['embalagem_unidades']} unid)" : ''); break;
        case 'saco_quilos': $a['unidade_medida_exib'] = 'saco' . (!empty($a['embalagem_peso_kg']) ? " ({$a['embalagem_peso_kg']} kg)" : ''); break;
        default: $a['unidade_medida_exib'] = $a['unidade_medida'] ?? 'kg';
    }
    $a['unidade_medida'] = $a['unidade_medida_exib'];
}

// Verifica se existe tabela de propostas (para evitar erro se não existir)
$propostas_existe = false;
try {
    $conn->query("SELECT 1 FROM propostas LIMIT 1");
    $propostas_existe = true;
} catch (PDOException $e) {
    $propostas_existe = false;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Anúncios - Encontre O Campo</title>
    <link rel="stylesheet" href="../css/vendedor/anuncios.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    
    <style>
        /* Estilos Visuais para Bloqueio */
        .locked-row {
            background-color: #f1f2f6;
            opacity: 0.7;
        }
        .locked-row td {
            color: #636e72;
        }
        .locked-row img {
            filter: grayscale(100%);
            opacity: 0.6;
        }
        .locked-badge {
            background-color: #2d3436;
            color: #fab1a0;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
        }
        .limit-alert {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            margin: 20px auto;
            max-width: 800px;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .limit-alert a {
            color: #856404;
            text-decoration: underline;
            font-weight: bold;
        }
        
        /* Ajuste responsivo */
        @media (max-width: 768px) {
            .limit-alert {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
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
                <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="anuncios.php" class="nav-link active">Meus Anúncios</a></li>
                <li class="nav-item"><a href="perfil.php" class="nav-link">Perfil</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
            </ul>

            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>
    <br>

    <main class="main-content">
        <div class="header">
            <center>
                <h1>Meus Anúncios</h1>
                <p>Gerencie seus produtos. Seu plano atual é: <strong><?php echo htmlspecialchars($nome_plano_atual); ?></strong></p>
                <p style="font-size: 0.9em; color: #666;">Limite do plano: <strong><?php echo $limite_permitido; ?></strong> anúncios ativos.</p>
            </center>

            <?php if (count($anuncios) > $limite_permitido): ?>
                <div class="limit-alert">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <div>
                        Você possui mais anúncios do que seu plano permite. 
                        Os anúncios mais recentes foram <strong>bloqueados</strong> automaticamente.
                        <a href="escolher_plano.php">Faça um upgrade</a> para desbloquear.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($anuncios) < $limite_permitido): ?>
            <a href="anuncio_novo.php" class="cta-button">
                <i class="fas fa-plus"></i> Novo Anúncio
            </a>
        <?php else: ?>
             <button class="cta-button" style="background-color: #b2bec3; cursor: not-allowed;" onclick="alert('Você atingiu o limite de <?php echo $limite_permitido; ?> anúncios do seu plano. Faça upgrade ou exclua um item antigo.');">
                <i class="fas fa-lock"></i> Limite Atingido
            </button>
        <?php endif; ?>

        <section class="section-anuncios">
            <h2>Todos os Anúncios (<?php echo count($anuncios); ?>)</h2>
            
            <?php if (empty($anuncios)): ?>
                <div class="empty-state">
                    <p>Você ainda não tem anúncios cadastrados.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                            <?php if ($propostas_existe): ?><th>Propostas</th><?php endif; ?>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contador = 0;
                        foreach ($anuncios as $anuncio): 
                            $contador++;
                            // AQUI ESTÁ A MÁGICA: Se o contador passar do limite do banco, bloqueia
                            $bloqueado = ($contador > $limite_permitido);
                        ?>
                            <?php
                            // Cálculos de preço e desconto (mantido original)
                            $preco_final = $anuncio['preco'];
                            if (!empty($anuncio['preco_desconto']) && $anuncio['preco_desconto'] > 0) {
                                $desconto_ativo = false; $agora = time();
                                if (!empty($anuncio['desconto_data_inicio'])) { if ($agora < strtotime($anuncio['desconto_data_inicio'])) $desconto_ativo = false; }
                                if (!empty($anuncio['desconto_data_fim'])) { $desconto_ativo = ($agora <= strtotime($anuncio['desconto_data_fim'])); } else { $desconto_ativo = true; }
                                if ($desconto_ativo) $preco_final = $anuncio['preco_desconto'];
                            }
                            
                            // Contagem de propostas (mantido original)
                            if ($propostas_existe) {
                                $sql_propostas = "SELECT COUNT(*) as total FROM propostas WHERE produto_id = :pid";
                                $stmt_p = $conn->prepare($sql_propostas); $stmt_p->execute([':pid' => $anuncio['id']]);
                                $propostas_count = $stmt_p->fetch(PDO::FETCH_ASSOC);
                                
                                $sql_pendentes = "SELECT COUNT(*) as total FROM propostas WHERE produto_id = :pid AND status = 'pendente'";
                                $stmt_pe = $conn->prepare($sql_pendentes); $stmt_pe->execute([':pid' => $anuncio['id']]);
                                $pendentes_count = $stmt_pe->fetch(PDO::FETCH_ASSOC);
                            }
                            ?>
                            
                            <tr class="<?php echo $bloqueado ? 'locked-row' : ''; ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php 
                                        $img = $anuncio['imagem_url'] ?: '../img/placeholder.png';
                                        if (empty($img)) $img = '../img/placeholder.png';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($img); ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;"
                                             onerror="this.src='../img/placeholder.png';">
                                        <div>
                                            <strong><?php echo htmlspecialchars($anuncio['nome']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($anuncio['unidade_medida']); ?></small>
                                            <div style="font-size: 0.7em; color: #999;">ID: <?php echo $anuncio['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($anuncio['categoria']); ?></td>
                                <td>R$ <?php echo number_format($preco_final, 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?></td>
                                
                                <td>
                                    <?php if ($bloqueado): ?>
                                        <span class="locked-badge"><i class="fas fa-lock"></i> Plano Excedido</span>
                                    <?php else: ?>
                                        <?php 
                                        $cls = 'ativo'; $txt = 'Ativo';
                                        if ($anuncio['status'] == 'inativo') { $cls = 'inativo'; $txt = 'Inativo'; }
                                        elseif ($anuncio['status'] == 'pendente') { $cls = 'pendente'; $txt = 'Pendente'; }
                                        elseif ($anuncio['estoque'] <= 0) { $cls = 'esgotado'; $txt = 'Esgotado'; }
                                        ?>
                                        <span class="status <?php echo $cls; ?>"><?php echo $txt; ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php if ($propostas_existe): ?>
                                    <td>
                                        <?php if ($bloqueado): ?>-<?php else: ?>
                                            <center>
                                                <b><?php echo $propostas_count['total'] ?? 0; ?></b>
                                                <?php if (($pendentes_count['total']??0) > 0): ?>
                                                    <br><small style="color:red;"><?php echo $pendentes_count['total']; ?> pend.</small>
                                                <?php endif; ?>
                                            </center>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                
                                <td>
                                    <?php if ($bloqueado): ?>
                                        <button class="action-btn" disabled style="opacity:0.3; cursor:not-allowed;"><i class="fas fa-edit"></i></button>
                                        <button class="action-btn delete" onclick="confirmarExclusao(<?php echo $anuncio['id']; ?>)" title="Excluir para liberar espaço"><i class="fas fa-trash"></i></button>
                                        <button class="action-btn" disabled style="opacity:0.3; cursor:not-allowed;"><i class="fas fa-power-off"></i></button>
                                    <?php else: ?>
                                        <button class="action-btn edit" onclick="window.location.href='anuncio_editar.php?id=<?php echo $anuncio['id']; ?>'"><i class="fas fa-edit"></i></button>
                                        <button class="action-btn delete" onclick="confirmarExclusao(<?php echo $anuncio['id']; ?>)"><i class="fas fa-trash"></i></button>
                                        <button class="action-btn <?php echo $anuncio['status']=='ativo'?'inactive':'active-icon'; ?>" 
                                                onclick="toggleStatus(<?php echo $anuncio['id']; ?>, '<?php echo $anuncio['status']; ?>')">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <script>
    // JS Básico para Menu e Ações
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');
    if(hamburger){
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }

    function toggleStatus(id, currentStatus) {
        const novo = currentStatus === 'ativo' ? 'inativo' : 'ativo';
        if (confirm('Deseja alterar o status deste anúncio?')) {
            fetch('anuncios_alterar_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&status=${novo}`
            })
            .then(r => r.json())
            .then(d => { if(d.success) location.reload(); else alert('Erro: ' + d.message); })
            .catch(e => console.error(e));
        }
    }

    function confirmarExclusao(id) {
        if (confirm('Tem certeza? Isso liberará espaço no seu limite de anúncios.')) {
            fetch('anuncio_excluir.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            })
            .then(r => r.json())
            .then(d => { if(d.success) location.reload(); else alert('Erro: ' + d.message); })
            .catch(e => console.error(e));
        }
    }
    
    // Responsividade da tabela
    if (window.innerWidth <= 480) {
        const ths = document.querySelectorAll('table thead th');
        document.querySelectorAll('table tbody tr').forEach(row => {
            row.querySelectorAll('td').forEach((td, i) => {
                if(ths[i]) td.setAttribute('data-label', ths[i].textContent);
            });
        });
    }
    </script>
</body>
</html>