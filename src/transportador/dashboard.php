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

// VERIFICAR PREFERÊNCIAS DE AVISOS DO USUÁRIO
$exibir_aviso_veiculos = true;
try {
    $sql_avisos = "SELECT aviso_veiculos FROM usuario_avisos_preferencias WHERE usuario_id = :usuario_id";
    $stmt_avisos = $db->prepare($sql_avisos);
    $stmt_avisos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_avisos->execute();
    $preferencias_avisos = $stmt_avisos->fetch(PDO::FETCH_ASSOC);
    
    if ($preferencias_avisos) {
        $exibir_aviso_veiculos = (bool)$preferencias_avisos['aviso_veiculos'];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar preferências de avisos: " . $e->getMessage());
}

// Inicializar variáveis
$total_entregas = 0;
$entregas = [];
$total_entregas_pendentes = 0;
$total_entregas_em_transporte = 0;
$total_mensagens_nao_lidas = 0;

// Só busca estatísticas se o transportador for ativo
if (!$is_pendente && $transportador_id) {
    // 1. BUSCAR TODAS AS ENTREGAS DO TRANSPORTADOR
        $query_entregas = "SELECT e.id, e.endereco_origem, e.endereco_destino, e.status, 
                      e.data_solicitacao, e.valor_frete, 
                      p.nome as produto_nome, 
                      v.nome_comercial as vendedor_nome
                  FROM entregas e
                  INNER JOIN produtos p ON e.produto_id = p.id
                  INNER JOIN vendedores v ON p.vendedor_id = v.id
                  WHERE e.transportador_id = :transportador_id 
                  AND e.status NOT IN ('entregue', 'cancelada')
                  ORDER BY e.data_solicitacao DESC 
                  LIMIT 10";
                       
    $stmt_entregas = $db->prepare($query_entregas);
    $stmt_entregas->bindParam(':transportador_id', $transportador_id);
    $stmt_entregas->execute();
    $entregas = $stmt_entregas->fetchAll(PDO::FETCH_ASSOC);

    $total_entregas = count($entregas);

    // CONTADOR DE ENTREGAS PENDENTES
    try {
        $query_pendentes = "SELECT COUNT(id) as total_pendentes
                            FROM entregas 
                            WHERE transportador_id = :transportador_id 
                            AND status = 'pendente'";
                            
        $stmt_pendentes = $db->prepare($query_pendentes);
        $stmt_pendentes->bindParam(':transportador_id', $transportador_id);
        $stmt_pendentes->execute();
        $resultado = $stmt_pendentes->fetch(PDO::FETCH_ASSOC);
        
        $total_entregas_pendentes = $resultado['total_pendentes'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Erro ao contar entregas pendentes: " . $e->getMessage());
    }

    // CONTADOR DE ENTREGAS EM TRANSPORTE
    try {
        $query_transporte = "SELECT COUNT(id) as total_transporte
                             FROM entregas 
                             WHERE transportador_id = :transportador_id 
                             AND status = 'em_transporte'";
                             
        $stmt_transporte = $db->prepare($query_transporte);
        $stmt_transporte->bindParam(':transportador_id', $transportador_id);
        $stmt_transporte->execute();
        $resultado_transporte = $stmt_transporte->fetch(PDO::FETCH_ASSOC);
        
        $total_entregas_em_transporte = $resultado_transporte['total_transporte'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Erro ao contar entregas em transporte: " . $e->getMessage());
    }

    // CONTADOR DE MENSAGENS NÃO LIDAS
    try {
        $query_mensagens = "SELECT COUNT(DISTINCT cm.conversa_id) as total_conversas_nao_lidas
                    FROM chat_mensagens cm
                    INNER JOIN chat_conversas cc ON cm.conversa_id = cc.id
                    WHERE cc.transportador_id = :usuario_id
                    AND cm.remetente_id != :usuario_id
                    AND cm.lida = 0";

        $stmt_mensagens = $db->prepare($query_mensagens);
        $stmt_mensagens->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_mensagens->execute();
        $resultado_msg = $stmt_mensagens->fetch(PDO::FETCH_ASSOC);
        
        $total_mensagens_nao_lidas = $resultado_msg['total_conversas_nao_lidas'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Erro ao contar mensagens não lidas: " . $e->getMessage());
    }
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
                    <li class="nav-item"><a href="" class="nav-link active">Painel</a></li>
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
        
        <section class="info-cards">
            <?php if (!$is_pendente): ?>
                <a href="disponiveis.php">
                    <div class="card">
                        <i class="fas fa-truck-moving"></i>
                        <h3>Entregas disponíveis</h3>
                        <p>Ver</p>
                    </div>
                </a>
                <a href="meus_chats.php">
                    <div class="card">
                        <i class="fas fa-comments"></i>
                        <h3>Chats</h3>
                        <p><?php echo $total_mensagens_nao_lidas; ?> não lidas</p>
                    </div>
                </a>
                <a href="entregas.php">
                    <div class="card">
                        <i class="fas fa-clock"></i>
                        <h3>Minhas entregas</h3>
                        <p><?php echo $total_entregas_pendentes; ?></p>
                    </div>
                </a>
                <a href="historico.php">
                    <div class="card">
                        <i class="fas fa-book"></i>
                        <h3>Histórico</h3>
                        <p>Ver</p>
                    </div>
                </a>
                <a href="favoritos.php">
                    <div class="card">
                        <i class="fas fa-heart"></i>
                        <h3>Favoritas</h3>
                        <p>Ver</p>
                    </div>
                </a>
            <?php endif; ?>
        </section>

        <?php if (!$is_pendente && $transportador_id): ?>
            <section class="section-entregas">
                <div id="header">
                    <h2>Minhas Entregas Recentes</h2>
                    <a href="entregas.php" class="cta-button"><i class="fas fa-list"></i> Ver Todas</a>
                </div>
                
                <div class="tabela-entregas">
                    <?php if ($total_entregas > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Produto</th>
                                    <th>Vendedor</th>
                                    <th>Origem</th>
                                    <th>Destino</th>
                                    <th>Valor Frete</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entregas as $entrega): ?>
                                <tr>
                                    <td><?php echo $entrega['id']; ?></td>
                                    <td><?php echo htmlspecialchars($entrega['produto_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($entrega['vendedor_nome']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($entrega['endereco_origem'], 0, 20)) . '...'; ?></td>
                                    <td><?php echo htmlspecialchars(substr($entrega['endereco_destino'], 0, 20)) . '...'; ?></td>
                                    <td>R$ <?php echo number_format($entrega['valor_frete'], 2, ',', '.'); ?></td>
                                    <td>
                                        <span class="status <?php echo $entrega['status']; ?>">
                                            <?php 
                                            $status_text = '';
                                            switch($entrega['status']) {
                                                case 'pendente': $status_text = 'Pendente'; break;
                                                case 'em_transporte': $status_text = 'Em Transporte'; break;
                                                case 'entregue': $status_text = 'Entregue'; break;
                                                case 'cancelada': $status_text = 'Cancelada'; break;
                                                default: $status_text = ucfirst($entrega['status']);
                                            }
                                            echo $status_text;
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($entrega['data_solicitacao'])); ?></td>
                                    <td>
                                        <a href="entrega_detalhes.php?id=<?php echo $entrega['id']; ?>" class="action-btn edit" title="Ver Detalhes"><i class="fas fa-eye"></i></a>
                                        <?php if ($entrega['status'] == 'pendente' || $entrega['status'] == 'em_transporte'): ?>
                                            <a href="concluir_entrega.php?id=<?php echo $entrega['id']; ?>" class="action-btn" title="Concluir Entrega" style="color: #2196F3;"><i class="fas fa-check-double"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="cards-entregas-mobile">
                            <?php foreach ($entregas as $entrega): ?>
                            <div class="card-entrega">
                                <div class="card-entrega-header">
                                    <div class="card-entrega-title">
                                        <h3><?php echo htmlspecialchars($entrega['produto_nome']); ?></h3>
                                        <span class="card-entrega-id">ID: <?php echo $entrega['id']; ?></span>
                                    </div>
                                    <span class="card-entrega-status status <?php echo $entrega['status']; ?>">
                                        <?php 
                                        $status_text = '';
                                        switch($entrega['status']) {
                                            case 'pendente': $status_text = 'Pendente'; break;
                                            case 'em_transporte': $status_text = 'Em Transporte'; break;
                                            case 'entregue': $status_text = 'Entregue'; break;
                                            case 'cancelada': $status_text = 'Cancelada'; break;
                                            default: $status_text = ucfirst($entrega['status']);
                                        }
                                        echo $status_text;
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="card-entrega-body">
                                    <div class="card-info-item">
                                        <span class="card-info-label">Vendedor</span>
                                        <span class="card-info-value"><?php echo htmlspecialchars($entrega['vendedor_nome']); ?></span>
                                    </div>
                                    <div class="card-info-item">
                                        <span class="card-info-label">Valor Frete</span>
                                        <span class="card-info-value">R$ <?php echo number_format($entrega['valor_frete'], 2, ',', '.'); ?></span>
                                    </div>
                                    <div class="card-info-item">
                                        <span class="card-info-label">Origem</span>
                                        <span class="card-info-value small"><?php echo htmlspecialchars(substr($entrega['endereco_origem'], 0, 20)) . '...'; ?></span>
                                    </div>
                                    <div class="card-info-item">
                                        <span class="card-info-label">Destino</span>
                                        <span class="card-info-value small"><?php echo htmlspecialchars(substr($entrega['endereco_destino'], 0, 20)) . '...'; ?></span>
                                    </div>
                                    <div class="card-entrega-data">
                                        <i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($entrega['data_solicitacao'])); ?>
                                    </div>
                                </div>
                                
                                <div class="card-entrega-actions">
                                    <a href="entrega_detalhes.php?id=<?php echo $entrega['id']; ?>" class="card-action-btn edit" title="Ver Detalhes"><i class="fas fa-eye"></i></a>
                                    <?php if ($entrega['status'] == 'pendente' || $entrega['status'] == 'em_transporte'): ?>
                                        <a href="concluir_entrega.php?id=<?php echo $entrega['id']; ?>" class="card-action-btn" title="Concluir Entrega" style="background: #2196F3; color: white;"><i class="fas fa-check-double"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-container">
                            <div class="empty-state-icon"><i class="fas fa-truck"></i></div>
                            <h3>Você ainda não tem entregas</h3>
                            <p>Quando aceitar uma entrega, ela aparecerá aqui.</p>
                            <a href="disponiveis.php" class="empty-state-button"><i class="fas fa-search"></i> Buscar Entregas Disponíveis</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
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