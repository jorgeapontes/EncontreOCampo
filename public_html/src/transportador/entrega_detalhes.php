<?php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$db = $database->getConnection();

// Buscar o id do transportador pelo usuario_id
$transportador_id = null;
try {
    $sql_transportador = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id";
    $stmt_transportador = $db->prepare($sql_transportador);
    $stmt_transportador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_transportador->execute();
    $row = $stmt_transportador->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $transportador_id = $row['id'];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar id do transportador: " . $e->getMessage());
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$entrega = null;
if ($id > 0 && $transportador_id) {
        $sql = "SELECT e.*, p.nome AS produto_nome,
                                     v.nome_comercial AS vendedor_nome, v.usuario_id AS vendedor_usuario_id,
                                     v.cep AS vendedor_cep, v.rua AS vendedor_rua, v.numero AS vendedor_numero, v.cidade AS vendedor_cidade, v.estado AS vendedor_estado,
                                     c.nome_comercial AS comprador_nome, c.usuario_id AS comprador_usuario_id,
                                     (
                                             SELECT prop.data_entrega_estimada
                                             FROM propostas prop
                                             WHERE prop.produto_id = e.produto_id
                                                 AND prop.comprador_id = e.comprador_id
                                                 AND prop.vendedor_id = e.vendedor_id
                                             ORDER BY prop.data_inicio DESC
                                             LIMIT 1
                                     ) AS data_entrega_estimada
                        FROM entregas e
                        INNER JOIN produtos p ON e.produto_id = p.id
                        INNER JOIN vendedores v ON p.vendedor_id = v.id
                        LEFT JOIN compradores c ON e.comprador_id = c.usuario_id
                        WHERE e.id = :id AND e.transportador_id = :transportador_id LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt->execute();
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Entrega</title>
    <link rel="stylesheet" href="../css/transportador/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
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
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="entregas.php" class="nav-link">Entregas</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <main class="main-content">
        <?php if ($entrega): ?>
        <section class="entrega-detalhes" style="margin-top:48px;">
            <div style="display:flex;justify-content:center;">
                <div style="flex:1;max-width:1000px;">
                    <div class="entrega-info entrega-info-destacada" style="margin:0;">
                <div class="entrega-info-header">
                    <h2>Entrega #<?php echo $entrega['id']; ?></h2>
                        <span class="status <?php echo $entrega['status']; ?>">Status: <?php echo ucfirst($entrega['status']); ?></span>
                    </div>
                <div class="entrega-info-grid">
                        <?php
                            // preparar variáveis reutilizáveis
                            $produto_link = '../visualizar_anuncio.php?anuncio_id=' . intval($entrega['produto_id']);
                            $origem_full = '';
                            if (!empty(trim($entrega['endereco_origem'] ?? ''))) {
                                $origem_full = $entrega['endereco_origem'];
                            } else {
                                $origem_full = (trim($entrega['vendedor_rua'] ?? '') !== '' ? ($entrega['vendedor_rua'] . ', ') : '')
                                    . ($entrega['vendedor_numero'] ?? '')
                                    . (isset($entrega['vendedor_cidade']) ? ' - ' . $entrega['vendedor_cidade'] : '')
                                    . (isset($entrega['vendedor_estado']) ? '/' . $entrega['vendedor_estado'] : '')
                                    . (!empty($entrega['vendedor_cep'] ?? '') ? ' - CEP: ' . $entrega['vendedor_cep'] : '');
                            }
                            $destino_full = $entrega['endereco_destino'] ?? '';
                            $data_limite = !empty($entrega['data_entrega_estimada']) ? $entrega['data_entrega_estimada'] : ($entrega['data_solicitacao'] ?? null);
                        ?>

                        <!-- Primeira linha: Comprador e Vendedor -->
                        <div class="entrega-info-item"><span class="label">Comprador:</span> <span>
                            <?php if (!empty($entrega['comprador_usuario_id'])): ?>
                                <a href="../verperfil.php?usuario_id=<?php echo intval($entrega['comprador_usuario_id']); ?>"><?php echo htmlspecialchars($entrega['comprador_nome'] ?? '—'); ?></a>
                            <?php else: echo htmlspecialchars($entrega['comprador_nome'] ?? '—'); endif; ?>
                        </span></div>
                        <div class="entrega-info-item"><span class="label">Vendedor:</span> <span>
                            <?php if (!empty($entrega['vendedor_usuario_id'])): ?>
                                <a href="../verperfil.php?usuario_id=<?php echo intval($entrega['vendedor_usuario_id']); ?>"><?php echo htmlspecialchars($entrega['vendedor_nome']); ?></a>
                            <?php else: echo htmlspecialchars($entrega['vendedor_nome']); endif; ?>
                        </span></div>

                        <!-- Segunda linha: Valor do frete e Data Limite -->
                        <div class="entrega-info-item"><span class="label">Valor do Frete:</span> <span>R$ <?php echo number_format($entrega['valor_frete'], 2, ',', '.'); ?></span></div>
                        <div class="entrega-info-item"><span class="label">Data Limite de Entrega:</span> <span><?php echo $data_limite ? date('d/m/Y', strtotime($data_limite)) : '—'; ?></span></div>

                        <!-- Terceira linha: Endereços -->
                        <div class="entrega-info-item" style="grid-column:1 / -1;"><span class="label">Coleta:</span> <span><?php echo htmlspecialchars($origem_full ?: '—'); ?></span></div>
                        <div class="entrega-info-item" style="grid-column:1 / -1;"><span class="label">Destino:</span> <span><?php echo htmlspecialchars($destino_full ?: '—'); ?></span></div>

                        <!-- Última linha: Produto clicável (ocupar largura) -->
                        <div class="entrega-info-item" style="grid-column:1 / -1;"><span class="label">Produto:</span> <span><a href="<?php echo $produto_link; ?>"><?php echo htmlspecialchars($entrega['produto_nome']); ?></a></span></div>
                </div>
                    <?php
                        $origem = $origem_full;
                        $destino = $entrega['endereco_destino'] ?? '';
                        $google_maps_url = 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($origem) . '&destination=' . urlencode($destino) . '&travelmode=driving';
                    ?>
                    <div class="entrega-info-actions" style="display:flex;justify-content:flex-end;gap:12px;">
                        <a href="<?php echo $google_maps_url; ?>" class="cta-button" target="_blank"><i class="fas fa-route"></i> Ver rota</a>
                        <a href="#" class="cta-button btn-voltar" onclick="window.history.back();return false;"><i class="fas fa-arrow-left"></i> Voltar</a>
                    </div>
                    </div>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="entrega-detalhes">
            <div class="entrega-info">
                <p>Entrega não encontrada ou você não tem permissão para visualizá-la.</p>
                <button type="button" class="btn-voltar" onclick="window.history.back()"><i class="fas fa-arrow-left"></i> Voltar</button>
            </div>
        </section>
        <?php endif; ?>
    </main>
    <style>
    .entrega-info-destacada {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        padding: 32px 24px;
        max-width: 900px;
        margin: 48px auto 0 auto;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .entrega-info-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .entrega-info-header h2 {
        font-size: 2rem;
        color: var(--primary-color, #4CAF50);
        margin: 0;
    }
    .entrega-info-header .status {
        font-weight: 600;
        padding: 6px 16px;
        border-radius: 8px;
        background: var(--primary-light, #C8E6C9);
        color: var(--primary-dark, #388E3C);
        font-size: 1rem;
    }
    .entrega-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 32px;
    }
    .entrega-info-item .label {
        font-weight: 600;
        color: var(--primary-color, #4CAF50);
        margin-right: 6px;
    }
    .entrega-info-actions {
        display: flex;
        justify-content: flex-end;
        gap: 16px;
    }
    .cta-button {
        background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 12px 28px;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s, box-shadow 0.2s;
        box-shadow: 0 2px 8px rgba(76,175,80,0.08);
        cursor: pointer;
    }
    .cta-button:hover {
        background: linear-gradient(135deg, #388E3C 0%, #4CAF50 100%);
        color: #fff;
        box-shadow: 0 4px 16px rgba(76,175,80,0.15);
    }
    .btn-voltar {
        background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
        color: #333;
        border: none;
        border-radius: 8px;
        padding: 12px 28px;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s, box-shadow 0.2s;
        box-shadow: 0 2px 8px rgba(160,160,160,0.08);
        cursor: pointer;
        margin-left: 12px;
    }
    .btn-voltar:hover {
        background: linear-gradient(135deg, #bdbdbd 0%, #e0e0e0 100%);
        color: #111;
        box-shadow: 0 4px 16px rgba(160,160,160,0.15);
    }
    @media (max-width: 600px) {
        .entrega-info-destacada {
            padding: 16px 6px;
        }
        .entrega-info-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .entrega-info-header h2 {
            font-size: 1.2rem;
        }
    }
    </style>
    <script>
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
