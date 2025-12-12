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

// Buscar informações do vendedor logado
$vendedor_id = $_SESSION['vendedor_id'] ?? null;

if (!$vendedor_id) {
    // Se não tiver vendedor_id na sessão, buscar pelo usuario_id
    $sql_vendedor = "SELECT id FROM vendedores WHERE usuario_id = :usuario_id";
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);
    
    if ($vendedor) {
        $vendedor_id = $vendedor['id'];
        $_SESSION['vendedor_id'] = $vendedor_id;
    } else {
        header('Location: ../index.php');
        exit();
    }
}

// Versão simplificada - sem contar propostas
$sql = "SELECT p.* FROM produtos p 
        WHERE p.vendedor_id = :vendedor_id 
        ORDER BY p.data_criacao DESC";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
$stmt->execute();
$anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ajusta campos para exibição compatível com o novo modo de precificação
foreach ($anuncios as &$a) {
    $modo = $a['modo_precificacao'] ?? 'por_quilo';
    if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
        $a['quantidade_disponivel'] = $a['estoque_unidades'] ?? 0;
    } else {
        $a['quantidade_disponivel'] = $a['estoque'] ?? 0;
    }

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

// Opcionalmente, se você quiser verificar se a tabela propostas existe antes de usar:
try {
    // Testar se a tabela propostas existe
    $teste_propostas = $conn->query("SELECT 1 FROM propostas LIMIT 1");
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
    <title>Meus Anúncios - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/vendedor/anuncios.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
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
                    <a href="dashboard.php" class="nav-link">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="anuncios.php" class="nav-link active">Meus Anúncios</a>
                </li>
                <li class="nav-item">
                    <a href="propostas.php" class="nav-link">Propostas</a>
                </li>
                <li class="nav-item">
                    <a href="perfil.php" class="nav-link">Perfil</a>
                </li>
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
    <br>

    <!-- Conteúdo Principal -->
    <main class="main-content">
        <div class="header">
            <center>
                <h1>Meus Anúncios</h1>
                <p>Gerencie todos os seus produtos anunciados</p>
            </center>
        </div>

        <!-- Botão para Novo Anúncio -->
        <a href="anuncio_novo.php" class="cta-button">
            <i class="fas fa-plus"></i> Novo Anúncio
        </a>

        <!-- Seção de Anúncios -->
        <section class="section-anuncios">
            <h2>Todos os Anúncios (<?php echo count($anuncios); ?>)</h2>
            
            <?php if (empty($anuncios)): ?>
                <div class="empty-state">
                    <p>Você ainda não tem anúncios cadastrados.</p>
                    <a href="anuncio_cadastrar.php" class="cta-button" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Criar Primeiro Anúncio
                    </a>
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
                            <?php if ($propostas_existe): ?>
                                <th>Propostas</th>
                            <?php endif; ?>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($anuncios as $anuncio): ?>
                            <?php
                            // Determinar preço para exibição
                            $preco_final = $anuncio['preco'];
                            if (!empty($anuncio['preco_desconto']) && $anuncio['preco_desconto'] > 0) {
                                // Verificar se o desconto está ativo
                                $desconto_ativo = false;
                                $agora = time();
                                
                                if (!empty($anuncio['desconto_data_inicio'])) {
                                    $inicio = strtotime($anuncio['desconto_data_inicio']);
                                    if ($agora < $inicio) {
                                        $desconto_ativo = false;
                                    }
                                }
                                
                                if (!empty($anuncio['desconto_data_fim'])) {
                                    $fim = strtotime($anuncio['desconto_data_fim']);
                                    if ($agora > $fim) {
                                        $desconto_ativo = false;
                                    } else {
                                        $desconto_ativo = true;
                                    }
                                } else {
                                    $desconto_ativo = true;
                                }
                                
                                if ($desconto_ativo) {
                                    $preco_final = $anuncio['preco_desconto'];
                                }
                            }
                            
                            // Se propostas existirem, contar propostas
                            if ($propostas_existe) {
                                $sql_propostas = "SELECT COUNT(*) as total FROM propostas WHERE produto_id = :produto_id";
                                $stmt_propostas = $conn->prepare($sql_propostas);
                                $stmt_propostas->bindParam(':produto_id', $anuncio['id'], PDO::PARAM_INT);
                                $stmt_propostas->execute();
                                $propostas_count = $stmt_propostas->fetch(PDO::FETCH_ASSOC);
                                
                                $sql_pendentes = "SELECT COUNT(*) as total FROM propostas WHERE produto_id = :produto_id AND status = 'pendente'";
                                $stmt_pendentes = $conn->prepare($sql_pendentes);
                                $stmt_pendentes->bindParam(':produto_id', $anuncio['id'], PDO::PARAM_INT);
                                $stmt_pendentes->execute();
                                $pendentes_count = $stmt_pendentes->fetch(PDO::FETCH_ASSOC);
                            }
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php 
                                        // CORREÇÃO CRÍTICA: Ajustar caminho das imagens
                                        $imagem_url = $anuncio['imagem_url'] ?: '../img/placeholder.png';
                                        
                                        // Se o caminho começa com '../', substituir por caminho correto
                                        if (strpos($imagem_url, '../') === 0) {
                                            // Exemplo: '../uploads/produtos/prod_xxx.jpg'
                                            // Deve se tornar: 'uploads/produtos/prod_xxx.jpg'
                                            // Porque estamos em src/vendedor/
                                            // Precisamos: ../uploads/produtos/prod_xxx.jpg
                                            
                                            // Na verdade, o caminho '../uploads/' já está correto!
                                            // Mas precisamos garantir que é relativo à pasta src/vendedor/
                                            // Pasta atual: src/vendedor/
                                            // Imagem: src/uploads/produtos/...
                                            // Caminho relativo: ../uploads/produtos/...
                                            
                                            // O caminho no banco já está certo! Só precisamos garantir
                                            $imagem_url = $anuncio['imagem_url'];
                                        }
                                        
                                        // Se não tiver caminho, usar placeholder
                                        if (empty($imagem_url)) {
                                            $imagem_url = '../img/placeholder.png';
                                        }
                                        
                                        $final_url = htmlspecialchars($imagem_url);
                                        ?>
                                        <img src="<?php echo $final_url; ?>" 
                                             alt="<?php echo htmlspecialchars($anuncio['nome']); ?>"
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;"
                                             onerror="console.log('Erro ao carregar: <?php echo addslashes($final_url); ?>'); this.onerror=null; this.src='../img/placeholder.png';">
                                        <div>
                                            <strong><?php echo htmlspecialchars($anuncio['nome']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($anuncio['unidade_medida']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($anuncio['categoria']); ?></td>
                                <td>
                                    <?php if (!empty($anuncio['preco_desconto']) && $anuncio['preco_desconto'] < $anuncio['preco']): ?>
                                        <div style="text-decoration: line-through; color: #999; font-size: 0.9em;">
                                            R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?>
                                        </div>
                                        <div style="color: #e53935; font-weight: bold;">
                                            R$ <?php echo number_format($preco_final, 2, ',', '.'); ?>
                                        </div>
                                    <?php else: ?>
                                        R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?>
                                    <small><?php echo htmlspecialchars($anuncio['unidade_medida']); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $status_class = 'ativo';
                                    $status_text = 'Ativo';
                                    
                                    if ($anuncio['status'] == 'inativo') {
                                        $status_class = 'inativo';
                                        $status_text = 'Inativo';
                                    } elseif ($anuncio['status'] == 'pendente') {
                                        $status_class = 'pendente';
                                        $status_text = 'Pendente';
                                    } elseif ($anuncio['estoque'] <= 0) {
                                        $status_class = 'esgotado';
                                        $status_text = 'Esgotado';
                                    }
                                    ?>
                                    <span class="status <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <?php if ($propostas_existe): ?>
                                    <td>
                                        <div style="text-align: center;">
                                            <div style="font-weight: bold; font-size: 1.2em;">
                                                <?php echo $propostas_count['total'] ?? 0; ?>
                                            </div>
                                            <?php if (($pendentes_count['total'] ?? 0) > 0): ?>
                                                <small style="color: #e53935; font-weight: bold;">
                                                    <?php echo $pendentes_count['total'] ?? 0; ?> pendente(s)
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <button class="action-btn edit" title="Editar" onclick="window.location.href='anuncio_editar.php?id=<?php echo $anuncio['id']; ?>'">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" title="Excluir" onclick="confirmarExclusao(<?php echo $anuncio['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="action-btn <?php echo $anuncio['status'] == 'ativo' ? 'inactive' : 'active-icon'; ?>" 
                                            title="<?php echo $anuncio['status'] == 'ativo' ? 'Desativar' : 'Ativar'; ?>"
                                            onclick="toggleStatus(<?php echo $anuncio['id']; ?>, '<?php echo $anuncio['status']; ?>')">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <script>
    // Toggle do menu hamburger
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');

    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('active');
    });

    document.querySelectorAll('.nav-link').forEach(n => n.addEventListener('click', () => {
        hamburger.classList.remove('active');
        navMenu.classList.remove('active');
    }));

    // Função para alternar status - CORRIGIDA
    function toggleStatus(anuncioId, currentStatus) {
        const novoStatus = currentStatus === 'ativo' ? 'inativo' : 'ativo';
        const confirmacao = currentStatus === 'ativo' 
            ? 'Tem certeza que deseja desativar este anúncio?' 
            : 'Tem certeza que deseja ativar este anúncio?';
        
        if (confirm(confirmacao)) {
            fetch('anuncios_alterar_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${anuncioId}&status=${novoStatus}`
            })
            .then(response => {
                // Verificar se a resposta é JSON válido
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.error('Resposta não é JSON:', text);
                        throw new Error('Resposta do servidor não é JSON válido');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    // Recarregar a página para atualizar os dados
                    location.reload();
                } else {
                    alert('Erro ao alterar status: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro detalhado:', error);
                alert('Erro ao comunicar com o servidor. Verifique o console para mais detalhes.');
            });
        }
    }

    // Função para confirmar exclusão
    function confirmarExclusao(anuncioId) {
        if (confirm('Tem certeza que deseja excluir este anúncio?\n\nEsta ação não pode ser desfeita!')) {
            fetch('anuncio_excluir.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${anuncioId}`
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.includes("application/json")) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.error('Resposta não é JSON:', text);
                        throw new Error('Resposta do servidor não é JSON válido');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro ao excluir: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao comunicar com o servidor');
            });
        }
    }
    </script>
</body>
</html>