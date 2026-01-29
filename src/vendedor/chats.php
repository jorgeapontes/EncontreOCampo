<?php
// src/vendedor/chats.php
require_once 'auth.php';
require_once __DIR__ . '/../conexao.php';

$database = new Database();
$conn = $database->getConnection();

$usuario_id = $_SESSION['usuario_id'];
$vendedor_id = $vendedor['id'];

// Verificar se está visualizando arquivados ou ativos
$aba = isset($_GET['aba']) ? $_GET['aba'] : 'ativos';
$mostrar_arquivados = ($aba === 'arquivados');

// Processar arquivamento/restauração E EXCLUSÃO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $conversa_id = $_POST['conversa_id'] ?? 0;
        $tipo_chat = $_POST['tipo_chat'] ?? '';
        
        if ($conversa_id > 0) {
            try {
                $sql = null;
                $acao_log = '';

                // 1. ARQUIVAR
                if ($_POST['action'] === 'arquivar_conversa') {
                    if ($tipo_chat === 'vendedor') {
                        $sql = "UPDATE chat_conversas SET favorito_vendedor = 1 WHERE id = :conversa_id";
                    } else {
                        $sql = "UPDATE chat_conversas SET favorito_comprador = 1 WHERE id = :conversa_id";
                    }
                    $mensagem_sucesso = "Conversa arquivada com sucesso!";
                    $acao_log = 'arquivar_conversa';
                
                // 2. RESTAURAR
                } elseif ($_POST['action'] === 'restaurar_conversa') {
                    if ($tipo_chat === 'vendedor') {
                        $sql = "UPDATE chat_conversas SET favorito_vendedor = 0 WHERE id = :conversa_id";
                    } else {
                        $sql = "UPDATE chat_conversas SET favorito_comprador = 0 WHERE id = :conversa_id";
                    }
                    $mensagem_sucesso = "Conversa restaurada com sucesso!";
                    $acao_log = 'restaurar_conversa';

                // 3. EXCLUIR (Lógica de Soft Delete)
                } elseif ($_POST['action'] === 'excluir_conversa') {
                    if ($tipo_chat === 'vendedor') {
                        // Vendedor exclui sua visualização

                        $sql = "UPDATE chat_conversas SET favorito_vendedor = 0, vendedor_excluiu = 1 WHERE id = :conversa_id";
                    } else {
                        // Comprador exclui sua visualização
                        $sql = "UPDATE chat_conversas SET favorito_comprador = 0, comprador_excluiu = 1 WHERE id = :conversa_id";
                    }
                    $mensagem_sucesso = "Conversa excluída da sua lista.";
                    $acao_log = 'excluir_conversa_usuario';
                }
                
                if (isset($sql)) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    // Registrar na auditoria
                    $sql_audit = "INSERT INTO chat_auditoria (conversa_id, usuario_id, acao, detalhes) 
                                 VALUES (:conversa_id, :usuario_id, :acao, 'Ação realizada pelo usuário')";
                    $stmt_audit = $conn->prepare($sql_audit);
                    $stmt_audit->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
                    $stmt_audit->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                    $stmt_audit->bindParam(':acao', $acao_log);
                    $stmt_audit->execute();
                    
                    // Redirecionar para evitar reenvio do formulário
                    header("Location: chats.php?aba=" . $aba . "&success=1&msg=" . urlencode($mensagem_sucesso));
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Erro ao processar conversa: " . $e->getMessage());
                $error = "Erro ao processar conversa. Tente novamente.";
            }
        }
    }
}

// Verificar mensagens de sucesso/erro via GET
$success = isset($_GET['success']) && $_GET['success'] == 1;
$success_msg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';

// BUSCAR CHATS COMO VENDEDOR (recebendo de compradores)
try {
    // Nota: Adicionado filtro AND cc.vendedor_excluiu = 0
    $sql_vendedor = "SELECT 
                cc.id AS conversa_id,
                cc.produto_id,
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                u.id AS outro_usuario_id,
                u.nome AS outro_usuario_nome,
                'vendedor' AS tipo_chat,
                cc.favorito_vendedor AS arquivado,
                (SELECT COUNT(*) 
                 FROM chat_mensagens cm 
                 WHERE cm.conversa_id = cc.id 
                 AND cm.remetente_id != :usuario_id 
                 AND cm.lida = 0) AS mensagens_nao_lidas
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN usuarios u ON cc.comprador_id = u.id
            WHERE p.vendedor_id = :vendedor_id
            AND cc.status = 'ativo'
            AND cc.vendedor_excluiu = 0
            AND cc.transportador_id IS NULL";
    
    if ($mostrar_arquivados) {
        $sql_vendedor .= " AND cc.favorito_vendedor = 1";
    } else {
        $sql_vendedor .= " AND cc.favorito_vendedor = 0";
    }
    
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $conversas_vendedor = $stmt_vendedor->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar conversas como vendedor: " . $e->getMessage());
    $conversas_vendedor = [];
}

// BUSCAR CHATS COMO COMPRADOR (enviando para outros vendedores)
try {
    // Nota: Adicionado filtro AND cc.comprador_excluiu = 0
    $sql_comprador = "SELECT 
                cc.id AS conversa_id,
                cc.produto_id,
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                uv.id AS outro_usuario_id,
                COALESCE(v.nome_comercial, uv.nome) AS outro_usuario_nome,
                'comprador' AS tipo_chat,
                cc.favorito_comprador AS arquivado,
                (SELECT COUNT(*) 
                 FROM chat_mensagens cm 
                 WHERE cm.conversa_id = cc.id 
                 AND cm.remetente_id != :usuario_id 
                 AND cm.lida = 0) AS mensagens_nao_lidas
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN vendedores v ON p.vendedor_id = v.id
            INNER JOIN usuarios uv ON v.usuario_id = uv.id
            WHERE cc.comprador_id = :usuario_id
            AND cc.status = 'ativo'
            AND cc.comprador_excluiu = 0
            AND cc.transportador_id IS NULL";
    
    if ($mostrar_arquivados) {
        $sql_comprador .= " AND cc.favorito_comprador = 1";
    } else {
        $sql_comprador .= " AND cc.favorito_comprador = 0";
    }
    
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $conversas_comprador = $stmt_comprador->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar conversas como comprador: " . $e->getMessage());
    $conversas_comprador = [];
}

// COMBINAR TODAS AS CONVERSAS
$conversas = array_merge($conversas_vendedor, $conversas_comprador);

// ORDENAR POR DATA MAIS RECENTE
usort($conversas, function($a, $b) {
    return strtotime($b['ultima_mensagem_data']) - strtotime($a['ultima_mensagem_data']);
});

// Contar total de mensagens não lidas
try {
    // Contar como vendedor
    $sql_vendedor_nao_lidas = "SELECT COUNT(DISTINCT cm.conversa_id) as total
                               FROM chat_mensagens cm
                               INNER JOIN chat_conversas cc ON cm.conversa_id = cc.id
                               INNER JOIN produtos p ON cc.produto_id = p.id
                               WHERE p.vendedor_id = :vendedor_id 
                               AND cm.remetente_id != :usuario_id
                               AND cm.lida = 0
                               AND cc.vendedor_excluiu = 0
                               AND cc.transportador_id IS NULL";
    
    if ($mostrar_arquivados) {
        $sql_vendedor_nao_lidas .= " AND cc.favorito_vendedor = 1";
    } else {
        $sql_vendedor_nao_lidas .= " AND cc.favorito_vendedor = 0";
    }
    
    $stmt_v = $conn->prepare($sql_vendedor_nao_lidas);
    $stmt_v->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_v->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_v->execute();
    $total_vendedor_nao_lidas = $stmt_v->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Contar como comprador
    $sql_comprador_nao_lidas = "SELECT COUNT(DISTINCT cm.conversa_id) as total
                                FROM chat_mensagens cm
                                INNER JOIN chat_conversas cc ON cm.conversa_id = cc.id
                                WHERE cc.comprador_id = :usuario_id
                                AND cm.remetente_id != :usuario_id
                                AND cm.lida = 0
                                AND cc.comprador_excluiu = 0
                                AND cc.transportador_id IS NULL";
    
    if ($mostrar_arquivados) {
        $sql_comprador_nao_lidas .= " AND cc.favorito_comprador = 1";
    } else {
        $sql_comprador_nao_lidas .= " AND cc.favorito_comprador = 0";
    }
    
    $stmt_c = $conn->prepare($sql_comprador_nao_lidas);
    $stmt_c->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_c->execute();
    $total_comprador_nao_lidas = $stmt_c->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $total_mensagens_nao_lidas = $total_vendedor_nao_lidas + $total_comprador_nao_lidas;
    
} catch (PDOException $e) {
    error_log("Erro ao contar mensagens não lidas (dashboard style): " . $e->getMessage());
    $total_mensagens_nao_lidas = 0;
}

// Contar conversas arquivadas para mostrar no badge
try {
    // Contagem deve ignorar as excluídas
    $sql_arquivadas_vendedor = "SELECT COUNT(*) as total 
                               FROM chat_conversas cc
                               INNER JOIN produtos p ON cc.produto_id = p.id
                               WHERE p.vendedor_id = :vendedor_id 
                               AND cc.status = 'ativo'
                               AND cc.favorito_vendedor = 1
                               AND cc.vendedor_excluiu = 0
                               AND cc.transportador_id IS NULL";
    
    $stmt_arquivadas_vendedor = $conn->prepare($sql_arquivadas_vendedor);
    $stmt_arquivadas_vendedor->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_arquivadas_vendedor->execute();
    $arquivadas_vendedor = $stmt_arquivadas_vendedor->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_arquivadas_comprador = "SELECT COUNT(*) as total 
                                FROM chat_conversas cc
                                WHERE cc.comprador_id = :usuario_id 
                                AND cc.status = 'ativo'
                                AND cc.favorito_comprador = 1
                                AND cc.comprador_excluiu = 0
                                AND cc.transportador_id IS NULL";
    
    $stmt_arquivadas_comprador = $conn->prepare($sql_arquivadas_comprador);
    $stmt_arquivadas_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_arquivadas_comprador->execute();
    $arquivadas_comprador = $stmt_arquivadas_comprador->fetch(PDO::FETCH_ASSOC)['total'];
    
    $total_arquivadas = $arquivadas_vendedor + $arquivadas_comprador;
    
} catch (PDOException $e) {
    error_log("Erro ao contar conversas arquivadas: " . $e->getMessage());
    $total_arquivadas = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Chats - Encontre o Campo</title>
    <link rel="stylesheet" href="../chat/css/conversas.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        /* Alerts */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .conversas-list { max-height: 600px; overflow-y: auto; }
        
        /* Arquivados */
        .conversa-card.arquivado { background: #f8f9fa; border-left: 4px solid #6c757d; opacity: 0.8; }
        .conversa-card.arquivado:hover { background: #f8f9fa; cursor: default; }
        .conversa-card.arquivado .produto-thumb img { filter: grayscale(50%); }
        .conversa-card.arquivado .comprador-nome, .conversa-card.arquivado .produto-nome, .conversa-card.arquivado .ultima-mensagem { color: #6c757d; }
        .conversa-card.arquivado .produto-preco { color: #28a745; }
        
        .comprador-nome { font-weight: 700; color: #333; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .badge-novo { background: #dc3545; color: white; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 600; }
        .badge-tipo { background: #17a2b8; color: white; font-size: 10px; padding: 3px 8px; border-radius: 12px; font-weight: 600; }
        .badge-arquivado { background: #6c757d; color: white; font-size: 10px; padding: 3px 8px; border-radius: 12px; font-weight: 600; }
        
        /* NOVO BOTÃO EXCLUIR */
        .btn-excluir { background: #dc3545; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 5px; transition: all 0.3s; white-space: nowrap; border: none; cursor: pointer; }
        .btn-excluir:hover { background: #c82333; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3); }
        
        .empty-state { padding: 4rem 2rem; text-align: center; color: #999; }
        .empty-state i { font-size: 80px; margin-bottom: 1rem; opacity: 0.3; }
        .empty-state h3 { font-size: 20px; margin-bottom: 0.5rem; }
        
        /* Modais */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 2000; }
        .modal-content { background: white; border-radius: 10px; width: 90%; max-width: 500px; overflow: hidden; }
        .modal-header { padding: 1.5rem; background: #f8f9fa; border-bottom: 1px solid #e9ecef; }
        .modal-header h3 { font-size: 20px; }
        .modal-arquivar .modal-header h3 { color: #6c757d; }
        .modal-restaurar .modal-header h3 { color: #28a745; }
        
        /* Modal Excluir Estilo */
        .modal-excluir .modal-header h3 { color: #dc3545; }
        
        .modal-body { padding: 1.5rem; }
        .modal-body p { color: #666; margin-bottom: 1.5rem; }
        
        .modal-footer { padding: 1rem 1.5rem; background: #f8f9fa; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 10px; }
        
        .btn-cancel { padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 5px; cursor: pointer; font-size: 14px; transition: all 0.3s; }
        .btn-cancel:hover { background: #f8f9fa; }
        
        .btn-confirm-arquivar { padding: 10px 20px; border: none; background: #6c757d; color: white; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; }
        .btn-confirm-arquivar:hover { background: #5a6268; }
        
        .btn-confirm-restaurar { padding: 10px 20px; border: none; background: #28a745; color: white; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; }
        .btn-confirm-restaurar:hover { background: #218838; }
        
        .btn-confirm-excluir { padding: 10px 20px; border: none; background: #dc3545; color: white; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; }
        .btn-confirm-excluir:hover { background: #c82333; }
        
        @media (max-width: 768px) {
            .conversas-container { border-radius: 0 0 10px 10px; }
            .conversa-card { flex-direction: column; align-items: flex-start; }
            .conversa-top { flex-direction: column; gap: 5px; }
            .conversa-actions { width: 100%; align-items: stretch; flex-direction: row; justify-content: space-between; }
            .btn-chat, .btn-arquivar, .btn-restaurar, .btn-excluir { flex: 1; justify-content: center; }
            .ultima-mensagem { max-width: 100%; }
            .stats-bar { flex-wrap: wrap; }
            .modal-content { width: 95%; }
        }
    </style>
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
                    <li class="nav-item"><a href="../anuncios.php" class="nav-link">Anúncios</a></li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="chats.php" class="nav-link active">Chats</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            // Contar notificações não lidas
                            if (isset($_SESSION['usuario_id'])) {
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $db->prepare($sql_nao_lidas);
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
                <h1><i class="fas fa-comments"></i> Meus Chats</h1>
                <p>Gerencie todas as suas conversas - vendas e compras</p>
            </center>
        </div>

        <center>
            <div class="stats-bar">
                <div class="stat-item">
                    <i class="fas fa-comments"></i>
                    <div><div class="label">Conversas Ativas</div><div class="value"><?php echo $mostrar_arquivados ? 0 : count($conversas); ?></div></div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-envelope"></i>
                    <div><div class="label">Não Lidas</div><div class="value"><?php echo $total_mensagens_nao_lidas; ?></div></div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-archive"></i>
                    <div><div class="label">Arquivadas</div><div class="value"><?php echo $total_arquivadas; ?></div></div>
                </div>
            </div>
        </center>

        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div><?php endif; ?>
        <?php if (isset($error)): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>

        <div class="abas-container">
            <button class="aba <?php echo !$mostrar_arquivados ? 'active' : ''; ?>" onclick="window.location.href='chats.php?aba=ativos'">
                <i class="fas fa-comments"></i> Conversas Ativas
            </button>
            <button class="aba <?php echo $mostrar_arquivados ? 'active' : ''; ?>" onclick="window.location.href='chats.php?aba=arquivados'">
                <i class="fas fa-archive"></i> Arquivadas
                <?php if ($total_arquivadas > 0): ?><span class="badge-aba"><?php echo $total_arquivadas; ?></span><?php endif; ?>
            </button>
        </div>

        <div class="conversas-container">
            <div class="conversas-header">
                <h2><?php echo $mostrar_arquivados ? 'Conversas Arquivadas' : 'Conversas Recentes'; ?></h2>
                <?php if (!$mostrar_arquivados): ?>
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filtrarConversas('todas')"><i class="fas fa-list"></i> Todas</button>
                    <button class="filter-btn" onclick="filtrarConversas('nao-lidas')"><i class="fas fa-envelope"></i> Não Lidas</button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="conversas-list">
                <?php if (count($conversas) > 0): ?>
                    <?php foreach ($conversas as $conversa): 
                        $imagem_produto = $conversa['produto_imagem'] ? htmlspecialchars($conversa['produto_imagem']) : '../../img/placeholder.png';
                        $tem_nao_lidas = $conversa['mensagens_nao_lidas'] > 0;
                        $data_formatada = $conversa['ultima_mensagem_data'] ? date('d/m/Y H:i', strtotime($conversa['ultima_mensagem_data'])) : '';
                        $eh_vendedor_chat = $conversa['tipo_chat'] === 'vendedor';
                        $esta_arquivado = $conversa['arquivado'] == 1;
                        
                        $chat_url = $mostrar_arquivados ? '#' : "../chat/chat.php?produto_id={$conversa['produto_id']}&conversa_id={$conversa['conversa_id']}&ref=vendedor_chats&aba=" . ($mostrar_arquivados ? 'arquivados' : 'ativos');
                    ?>
                        <div class="conversa-card <?php echo $tem_nao_lidas ? 'nao-lida' : ''; ?> <?php echo $esta_arquivado ? 'arquivado' : ''; ?>" 
                             data-tipo="<?php echo $tem_nao_lidas ? 'nao-lida' : 'lida'; ?>"
                             id="conversa-<?php echo $conversa['conversa_id']; ?>">
                            
                            <?php if (!$mostrar_arquivados): ?>
                                <a href="<?php echo $chat_url; ?>" style="display: flex; gap: 1.5rem; align-items: center; text-decoration: none; color: inherit; flex: 1;">
                            <?php else: ?>
                                <div style="display: flex; gap: 1.5rem; align-items: center; flex: 1; cursor: default;">
                            <?php endif; ?>
                                
                                <div class="produto-thumb">
                                    <img src="<?php echo $imagem_produto; ?>" alt="<?php echo htmlspecialchars($conversa['produto_nome']); ?>">
                                </div>
                                
                                <div class="conversa-info">
                                    <div class="conversa-top">
                                        <div class="comprador-nome">
                                            <i class="fas fa-<?php echo $eh_vendedor_chat ? 'user-circle' : 'store'; ?>"></i>
                                            <?php echo htmlspecialchars($conversa['outro_usuario_nome']); ?>
                                            <span class="badge-tipo"><?php echo $eh_vendedor_chat ? 'Venda' : 'Compra'; ?></span>
                                            <?php if ($esta_arquivado): ?>
                                                <span class="badge-arquivado"><i class="fas fa-archive"></i> Arquivado</span>
                                            <?php endif; ?>
                                            <?php if ($tem_nao_lidas && !$mostrar_arquivados): ?>
                                                <span class="badge-novo"><?php echo $conversa['mensagens_nao_lidas']; ?> nova<?php echo $conversa['mensagens_nao_lidas'] > 1 ? 's' : ''; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="produto-nome">
                                        <i class="fas fa-box"></i>
                                        <?php echo htmlspecialchars($conversa['produto_nome']); ?>
                                        <span class="produto-preco">- R$ <?php echo number_format($conversa['produto_preco'], 2, ',', '.'); ?></span>
                                    </div>
                                    
                                    <?php if ($conversa['ultima_mensagem']): ?>
                                        <div class="ultima-mensagem">
                                            <?= $data_formatada  ?> -
                                            <?php 
                                            if (strpos($conversa['ultima_mensagem'], '[Imagem]') !== false) { echo '<i class="fas fa-image"></i>&nbsp;&nbsp;Enviou uma imagem'; } 
                                            else { echo htmlspecialchars($conversa['ultima_mensagem']); }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            
                            <?php if (!$mostrar_arquivados): ?>
                                </a>
                            <?php else: ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="conversa-actions">
                                <?php if ($mostrar_arquivados): ?>
                                    <button type="button" class="btn-restaurar" onclick="confirmarRestauracao(<?php echo $conversa['conversa_id']; ?>, '<?php echo $conversa['tipo_chat']; ?>')">
                                        <i class="fas fa-box-open"></i> Restaurar
                                    </button>

                                    <button type="button" class="btn-excluir" onclick="confirmarExclusao(<?php echo $conversa['conversa_id']; ?>, '<?php echo $conversa['tipo_chat']; ?>')">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-arquivar" onclick="confirmarArquivamento(<?php echo $conversa['conversa_id']; ?>, '<?php echo $conversa['tipo_chat']; ?>')">
                                        <i class="fas fa-archive"></i> Arquivar
                                    </button>
                                    <a href="<?php echo $chat_url; ?>" class="btn-chat"><i class="fas fa-comments"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-<?php echo $mostrar_arquivados ? 'archive' : 'comments'; ?>"></i>
                        <h3><?php echo $mostrar_arquivados ? 'Nenhuma conversa arquivada' : 'Nenhuma conversa ainda'; ?></h3>
                        <p>
                            <?php if ($mostrar_arquivados): ?>As conversas que você arquivar aparecerão aqui.
                            <?php else: ?>Quando você conversar com compradores ou vendedores, as conversas aparecerão aqui.<?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-overlay modal-arquivar" id="arquivarModal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-archive"></i> Arquivar Conversa</h3></div>
            <div class="modal-body">
                <p>Tem certeza que deseja arquivar esta conversa?</p>
                <ul style="margin-left: 20px; color: #666;">
                    <li>A conversa será movida para a seção "Arquivadas"</li>
                    <li>Você não poderá mais visualizar o histórico</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="fecharModal('arquivar')">Cancelar</button>
                <form id="arquivarForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="arquivar_conversa">
                    <input type="hidden" id="conversa_id_arquivar" name="conversa_id">
                    <input type="hidden" id="tipo_chat_arquivar" name="tipo_chat">
                    <button type="submit" class="btn-confirm-arquivar">Sim, Arquivar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay modal-restaurar" id="restaurarModal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-box-open"></i> Restaurar Conversa</h3></div>
            <div class="modal-body">
                <p>Tem certeza que deseja restaurar esta conversa? Ela voltará para a lista principal.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="fecharModal('restaurar')">Cancelar</button>
                <form id="restaurarForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="restaurar_conversa">
                    <input type="hidden" id="conversa_id_restaurar" name="conversa_id">
                    <input type="hidden" id="tipo_chat_restaurar" name="tipo_chat">
                    <button type="submit" class="btn-confirm-restaurar">Sim, Restaurar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay modal-excluir" id="excluirModal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-trash-alt"></i> Excluir Conversa</h3></div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir esta conversa?</p>
                <ul style="margin-left: 20px; color: #666;">
                    <li>A conversa será removida da sua lista <strong>permanentemente</strong>.</li>
                    <li>O outro usuário <strong>ainda terá acesso</strong> ao histórico.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="fecharModal('excluir')">Cancelar</button>
                <form id="excluirForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="excluir_conversa">
                    <input type="hidden" id="conversa_id_excluir" name="conversa_id">
                    <input type="hidden" id="tipo_chat_excluir" name="tipo_chat">
                    <button type="submit" class="btn-confirm-excluir">Sim, Excluir</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        if (hamburger) {
            hamburger.addEventListener("click", () => { hamburger.classList.toggle("active"); navMenu.classList.toggle("active"); });
            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => { hamburger.classList.remove("active"); navMenu.classList.remove("active"); }));
        }
        
        function filtrarConversas(tipo) {
            const cards = document.querySelectorAll('.conversa-card');
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.closest('.filter-btn').classList.add('active');
            cards.forEach(card => {
                if (tipo === 'todas') { card.style.display = 'flex'; } 
                else if (tipo === 'nao-lidas') { card.style.display = (card.dataset.tipo === 'nao-lida') ? 'flex' : 'none'; }
            });
        }
        
        function confirmarArquivamento(conversaId, tipoChat) {
            document.getElementById('conversa_id_arquivar').value = conversaId;
            document.getElementById('tipo_chat_arquivar').value = tipoChat;
            document.getElementById('arquivarModal').style.display = 'flex';
        }
        
        function confirmarRestauracao(conversaId, tipoChat) {
            document.getElementById('conversa_id_restaurar').value = conversaId;
            document.getElementById('tipo_chat_restaurar').value = tipoChat;
            document.getElementById('restaurarModal').style.display = 'flex';
        }

        function confirmarExclusao(conversaId, tipoChat) {
            document.getElementById('conversa_id_excluir').value = conversaId;
            document.getElementById('tipo_chat_excluir').value = tipoChat;
            document.getElementById('excluirModal').style.display = 'flex';
        }
        
        function fecharModal(tipo) {
            if (tipo === 'arquivar') document.getElementById('arquivarModal').style.display = 'none';
            else if (tipo === 'restaurar') document.getElementById('restaurarModal').style.display = 'none';
            else if (tipo === 'excluir') document.getElementById('excluirModal').style.display = 'none';
        }
        
        // Fechar ao clicar fora
        window.onclick = function(e) { if(e.target.classList.contains('modal-overlay')) e.target.style.display = 'none'; }
        
        // Fechar com ESC
        document.addEventListener('keydown', function(e) { 
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => modal.style.display = 'none');
            }
        });
        
        // Auto-fechar alertas
        <?php if ($success): ?>
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) { alert.style.transition = 'opacity 0.5s'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>