<?php
// src/transportador/dashboard.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

// Verificar se o usuário tem permissão para ver dashboard completo
$usuario_status = $_SESSION['usuario_status'] ?? 'pendente';
$is_pendente = ($usuario_status === 'pendente');

$usuario_nome = htmlspecialchars($_SESSION['transportador_nome'] ?? 'Transportador');
$usuario_id = $_SESSION['usuario_id'];

// Conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Buscar dados do transportador
$transportador_id = null;
$transportador_nome_comercial = '';

try {
    $sql_transportador = "SELECT id, nome_comercial 
                         FROM transportadores 
                         WHERE usuario_id = :usuario_id";
                     
    $stmt_transportador = $db->prepare($sql_transportador);
    $stmt_transportador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_transportador->execute();
    $transportador = $stmt_transportador->fetch(PDO::FETCH_ASSOC);
    
    if ($transportador) {
        $transportador_id = $transportador['id'];
        $transportador_nome_comercial = $transportador['nome_comercial'] ?? $usuario_nome;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do transportador: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Transportador - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/transportador/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
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
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="entregas.php" class="nav-link">Entregas</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            if (isset($_SESSION['usuario_id'])) {
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $db->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
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
    <br>
    <div class="main-content">
        <section class="header">
            <center>
                <h1>Bem-vindo(a), <?php echo htmlspecialchars($transportador_nome_comercial); ?>!</h1>
                <?php if ($is_pendente): ?>
                    <p class="subtitulo">(Cadastro aguardando aprovação)</p>
                <?php endif; ?>
            </center>
        </section>

        <?php if ($is_pendente): ?>
            <div class="aviso-status">
                <i class="fas fa-info-circle"></i>
                <strong>Seu cadastro está aguardando aprovação.</strong>
            </div>
        <?php endif; ?>

        <section class="historico-entregas">
            <h2>Histórico de Entregas Finalizadas</h2>
            <?php
            if (!$is_pendente && $transportador_id) {
                $sql_hist = "SELECT e.id, e.endereco_origem, e.endereco_destino, e.valor_frete, e.data_entrega, e.foto_comprovante, e.assinatura_comprovante, p.nome as produto_nome, c.nome_comercial as comprador_nome, v.nome_comercial as vendedor_nome, v.cep as vendedor_cep, v.rua as vendedor_rua, v.numero as vendedor_numero, v.cidade as vendedor_cidade, v.estado as vendedor_estado
                    FROM entregas e
                    INNER JOIN produtos p ON e.produto_id = p.id
                    LEFT JOIN compradores c ON e.comprador_id = c.usuario_id
                    INNER JOIN vendedores v ON v.id = COALESCE(e.vendedor_id, p.vendedor_id)
                    WHERE e.transportador_id = :transportador_id AND e.status = 'entregue' AND e.status_detalhado = 'finalizada'
                    ORDER BY e.data_entrega DESC";
                $stmt_hist = $db->prepare($sql_hist);
                $stmt_hist->bindParam(':transportador_id', $transportador_id);
                $stmt_hist->execute();
                $entregas_finalizadas = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
                if (count($entregas_finalizadas) === 0) {
                    echo '<p>Nenhuma entrega finalizada ainda.</p>';
                } else {
                    echo '<table><thead><tr><th>ID</th><th>Produto</th><th>Comprador</th><th>Vendedor</th><th>Origem</th><th>Destino</th><th>Valor Frete</th><th>Data Entrega</th><th>Comprovante</th><th>Assinatura</th></tr></thead><tbody>';
                    foreach ($entregas_finalizadas as $e) {
                        $origem_full = '';
                        if (!empty(trim($e['endereco_origem'] ?? ''))) {
                            $origem_full = $e['endereco_origem'];
                        } else {
                            $origem_full = (trim($e['vendedor_rua'] ?? '') !== '' ? ($e['vendedor_rua'] . ', ') : '')
                                . ($e['vendedor_numero'] ?? '')
                                . (isset($e['vendedor_cidade']) ? ' - ' . $e['vendedor_cidade'] : '')
                                . (isset($e['vendedor_estado']) ? '/' . $e['vendedor_estado'] : '')
                                . (!empty($e['vendedor_cep'] ?? '') ? ' - CEP: ' . $e['vendedor_cep'] : '');
                        }
                        $destino_full = $e['endereco_destino'] ?? '';

                        echo '<tr>';
                        echo '<td>' . $e['id'] . '</td>';
                        echo '<td>' . htmlspecialchars($e['produto_nome']) . '</td>';
                        echo '<td>' . htmlspecialchars($e['comprador_nome'] ?? '—') . '</td>';
                        echo '<td>' . htmlspecialchars($e['vendedor_nome']) . '</td>';
                        echo '<td>' . htmlspecialchars(mb_substr($origem_full, 0, 20)) . (mb_strlen($origem_full) > 20 ? '...' : '') . '</td>';
                        echo '<td>' . htmlspecialchars(mb_substr($destino_full, 0, 20)) . (mb_strlen($destino_full) > 20 ? '...' : '') . '</td>';
                        echo '<td>R$ ' . number_format($e['valor_frete'], 2, ',', '.') . '</td>';
                        echo '<td>' . ($e['data_entrega'] ? date('d/m/Y', strtotime($e['data_entrega'])) : '-') . '</td>';
                        if (!empty($e['foto_comprovante'])) {
                            echo '<td><a href="../../uploads/entregas/' . htmlspecialchars($e['foto_comprovante']) . '" target="_blank">Ver Foto</a></td>';
                        } else {
                            echo '<td>-</td>';
                        }

                        if (!empty($e['assinatura_comprovante'])) {
                            echo '<td><img src="../../uploads/entregas/' . htmlspecialchars($e['assinatura_comprovante']) . '" alt="Assinatura" style="max-width:120px;max-height:80px;border:1px solid #ddd;border-radius:4px;" /></td>';
                        } else {
                            echo '<td>-</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            }
            ?>
        </section>
    </div>

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