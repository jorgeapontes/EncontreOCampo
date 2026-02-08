<?php
// src/vendedor/chats.php
require_once 'auth.php';
require_once __DIR__ . '/../conexao.php';

$database = new Database();
$conn = $database->getConnection();

$usuario_id = $_SESSION['usuario_id'];
$vendedor_id = $vendedor['id'];

// Adicionar vendedor_id à sessão se não estiver
if (!isset($_SESSION['vendedor_id']) && isset($vendedor_id)) {
    $_SESSION['vendedor_id'] = $vendedor_id;
}

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
                $notificar_outro_usuario = false;
                $outro_usuario_id = null;
                $outro_usuario_email = null;
                $outro_usuario_nome = null;
                $produto_id = null;
                $produto_nome = null;

                // Buscar informações da conversa para notificação
                if ($tipo_chat === 'vendedor') {
                    // Como vendedor, notificar o comprador
                    $sql_info = "SELECT 
                        cc.produto_id,
                        cc.comprador_id as outro_usuario_id,
                        u.email as outro_usuario_email,
                        u.nome as outro_usuario_nome,
                        p.nome as produto_nome
                        FROM chat_conversas cc
                        INNER JOIN produtos p ON cc.produto_id = p.id
                        INNER JOIN usuarios u ON cc.comprador_id = u.id
                        WHERE cc.id = :conversa_id";
                } else {
                    // Como comprador, notificar o vendedor
                    $sql_info = "SELECT 
                        cc.produto_id,
                        p.vendedor_id,
                        u.email as outro_usuario_email,
                        u.nome as outro_usuario_nome,
                        p.nome as produto_nome
                        FROM chat_conversas cc
                        INNER JOIN produtos p ON cc.produto_id = p.id
                        INNER JOIN vendedores v ON p.vendedor_id = v.id
                        INNER JOIN usuarios u ON v.usuario_id = u.id
                        WHERE cc.id = :conversa_id";
                }
                
                $stmt_info = $conn->prepare($sql_info);
                $stmt_info->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
                $stmt_info->execute();
                $conversa_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                
                if ($conversa_info) {
                    if ($tipo_chat === 'vendedor') {
                        $outro_usuario_id = $conversa_info['outro_usuario_id'];
                    } else {
                        $outro_usuario_id = $conversa_info['vendedor_id'];
                    }
                    $outro_usuario_email = $conversa_info['outro_usuario_email'];
                    $outro_usuario_nome = $conversa_info['outro_usuario_nome'];
                    $produto_id = $conversa_info['produto_id'];
                    $produto_nome = $conversa_info['produto_nome'];
                }

                // 1. ARQUIVAR
                if ($_POST['action'] === 'arquivar_conversa') {
                    if ($tipo_chat === 'vendedor') {
                        $sql = "UPDATE chat_conversas SET favorito_vendedor = 1 WHERE id = :conversa_id";
                    } else {
                        $sql = "UPDATE chat_conversas SET favorito_comprador = 1 WHERE id = :conversa_id";
                    }
                    $mensagem_sucesso = "Conversa arquivada com sucesso!";
                    $acao_log = 'arquivar_conversa';
                    $notificar_outro_usuario = false;
                
                // 2. RESTAURAR
                } elseif ($_POST['action'] === 'restaurar_conversa') {
                    if ($tipo_chat === 'vendedor') {
                        $sql = "UPDATE chat_conversas SET favorito_vendedor = 0 WHERE id = :conversa_id";
                    } else {
                        $sql = "UPDATE chat_conversas SET favorito_comprador = 0 WHERE id = :conversa_id";
                    }
                    $mensagem_sucesso = "Conversa restaurada com sucesso!";
                    $acao_log = 'restaurar_conversa';
                    $notificar_outro_usuario = false;

                // 3. EXCLUIR (Lógica de Soft Delete)
                } elseif ($_POST['action'] === 'excluir_conversa') {
                    if ($tipo_chat === 'vendedor') {
                        $sql = "UPDATE chat_conversas SET favorito_vendedor = 0, vendedor_excluiu = 1 WHERE id = :conversa_id";
                    } else {
                        $sql = "UPDATE chat_conversas SET favorito_comprador = 0, comprador_excluiu = 1 WHERE id = :conversa_id";
                    }
                    $mensagem_sucesso = "Conversa excluída da sua lista.";
                    $acao_log = 'excluir_conversa_usuario';
                    $notificar_outro_usuario = true;
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
                    
                    // NOTIFICAÇÃO POR EMAIL
                    if ($notificar_outro_usuario && $outro_usuario_email && $acao_log === 'excluir_conversa_usuario') {
                        // Buscar informações do usuário atual
                        $sql_usuario_atual = "SELECT nome, email FROM usuarios WHERE id = :usuario_id";
                        $stmt_usuario_atual = $conn->prepare($sql_usuario_atual);
                        $stmt_usuario_atual->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                        $stmt_usuario_atual->execute();
                        $usuario_atual = $stmt_usuario_atual->fetch(PDO::FETCH_ASSOC);
                        
                        $subject = "Conversa Excluída - Encontre o Campo";
                        $message = "Olá " . htmlspecialchars($outro_usuario_nome) . ",\n\n";
                        $message .= "Um usuário excluiu uma conversa relacionada a um produto.\n\n";
                        $message .= "Detalhes:\n";
                        $message .= "- Produto: " . htmlspecialchars($produto_nome) . "\n";
                        $message .= "- Usuário que excluiu: " . htmlspecialchars($usuario_atual['nome'] ?? 'Usuário') . "\n";
                        $message .= "- Ação: Conversa excluída da lista\n";
                        $message .= "- Data: " . date('d/m/Y H:i') . "\n\n";
                        $message .= "Acesse o sistema para ver mais detalhes.\n\n";
                        $message .= "Atenciosamente,\nEquipe Encontre o Campo";

                        require_once __DIR__ . '/../../includes/send_notification.php';
                        
                        enviarEmailNotificacao($outro_usuario_email, $outro_usuario_nome, $subject, $message);
                    }
                    
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

    // ORDENAR POR DATA DA ÚLTIMA MENSAGEM (MAIS RECENTE PRIMEIRO)
    $sql_vendedor .= " ORDER BY cc.ultima_mensagem_data DESC";
    
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

    // ORDENAR POR DATA DA ÚLTIMA MENSAGEM (MAIS RECENTE PRIMEIRO)
    $sql_comprador .= " ORDER BY cc.ultima_mensagem_data DESC";
    
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

// ORDENAR POR DATA MAIS RECENTE (garantir ordem correta após merge)
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
            
            <div class="conversas-list" id="conversasList">
                <!-- As conversas serão carregadas dinamicamente aqui -->
                <div class="loading-conversas" id="loadingConversas">
                    <i class="fas fa-spinner fa-spin"></i> Carregando conversas...
                </div>
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

        // ============== SISTEMA DINÂMICO DE CONVERSAS ==============
let conversasCache = new Map(); // Cache de conversas já renderizadas
let ultimaVerificacao = Math.floor(Date.now() / 1000);
let estaVerificando = false;
let intervaloAtualizacao = null;
const TEMPO_POLLING = 8000; // 8 segundos

// Inicializar sistema dinâmico
function iniciarSistemaDinamico() {
    // Carregar conversas iniciais via AJAX
    carregarConversasIniciais();
    
    // Iniciar polling para atualizações
    iniciarPolling();
    
    // Eventos de foco/desfoco da janela
    gerenciarEventosJanela();
}

// Carregar conversas iniciais
function carregarConversasIniciais() {
    const loadingEl = document.getElementById('loadingConversas');
    const conversasList = document.getElementById('conversasList');
    
    fetch(`carregar_conversas_ajax.php?aba=<?php echo $aba; ?>`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            mostrarErroCarregamento(data.error);
            return;
        }
        
        // Remover loading
        if (loadingEl) loadingEl.remove();
        
        // Renderizar conversas
        if (data.conversas && data.conversas.length > 0) {
            data.conversas.forEach(conversa => {
                renderizarConversa(conversa);
                conversasCache.set(conversa.conversa_id, conversa);
            });
            
            // Atualizar estatísticas
            atualizarEstatisticas(data);
        } else {
            mostrarEstadoVazio();
        }
        
        // Inicializar timestamp
        if (data.timestamp) {
            ultimaVerificacao = data.timestamp;
        }
    })
    .catch(error => {
        console.error('Erro ao carregar conversas:', error);
        mostrarErroCarregamento('Erro ao carregar conversas');
    });
}

// Renderizar uma conversa individual
// Substitua a função renderizarConversa por esta versão corrigida:
function renderizarConversa(conversa) {
    const conversasList = document.getElementById('conversasList');
    const estaArquivado = conversa.arquivado == 1;
    const mostraArquivados = <?php echo $mostrar_arquivados ? 'true' : 'false'; ?>;
    
    // Verificar se já existe de forma mais segura
    const existingCard = document.getElementById(`conversa-${conversa.conversa_id}`);
    if (existingCard) {
        atualizarConversaExistente(existingCard, conversa);
        return;
    }
    
    // Criar novo card
    const card = document.createElement('div');
    card.className = `conversa-card ${conversa.mensagens_nao_lidas > 0 ? 'nao-lida' : ''} ${estaArquivado ? 'arquivado' : ''} nova`;
    card.id = `conversa-${conversa.conversa_id}`;
    card.dataset.tipo = conversa.mensagens_nao_lidas > 0 ? 'nao-lida' : 'lida';
    card.dataset.conversaId = conversa.conversa_id;
    
    const imagemProduto = conversa.produto_imagem || '../../img/placeholder.png';
    const chatUrl = !mostraArquivados ? 
        `../chat/chat.php?produto_id=${conversa.produto_id}&conversa_id=${conversa.conversa_id}&ref=vendedor_chats&aba=ativos` : 
        '#';
    
    const ehVendedorChat = conversa.tipo_chat === 'vendedor';
    const ultimaMsgTratada = tratarUltimaMensagem(conversa.ultima_mensagem);
    const dataFormatada = conversa.ultima_mensagem_data ? 
        formatarData(conversa.ultima_mensagem_data) : '';
    
    // Construir HTML do card de forma mais segura
    card.innerHTML = `
        ${!mostraArquivados ? 
            `<a href="${chatUrl}" class="conversa-link" style="display: flex; gap: 1.5rem; align-items: center; text-decoration: none; color: inherit; flex: 1;">` : 
            `<div class="conversa-content" style="display: flex; gap: 1.5rem; align-items: center; flex: 1; cursor: default;">`
        }
            <div class="produto-thumb">
                <img src="${escapeHtml(imagemProduto)}" alt="${escapeHtml(conversa.produto_nome)}" onerror="this.src='../../img/placeholder.png'">
            </div>
            
            <div class="conversa-info">
                <div class="conversa-top">
                    <div class="comprador-nome">
                        <i class="fas fa-${ehVendedorChat ? 'user-circle' : 'store'}"></i>
                        ${escapeHtml(conversa.outro_usuario_nome)}
                        <span class="badge-tipo">${ehVendedorChat ? 'Venda' : 'Compra'}</span>
                        ${estaArquivado ? 
                            `<span class="badge-arquivado"><i class="fas fa-archive"></i> Arquivado</span>` : 
                            ''
                        }
                        ${conversa.mensagens_nao_lidas > 0 && !mostraArquivados ? 
                            `<span class="badge-novo">${conversa.mensagens_nao_lidas} nova${conversa.mensagens_nao_lidas > 1 ? 's' : ''}</span>` : 
                            ''
                        }
                    </div>
                </div>
                
                <div class="produto-nome">
                    <i class="fas fa-box"></i>
                    ${escapeHtml(conversa.produto_nome)}
                    <span class="produto-preco">- R$ ${formatarPreco(conversa.produto_preco)}</span>
                </div>
                
                ${conversa.ultima_mensagem ? 
                    `<div class="ultima-mensagem">
                        ${dataFormatada} - ${ultimaMsgTratada}
                    </div>` : 
                    ''
                }
            </div>
        
        ${!mostraArquivados ? '</a>' : '</div>'}
        
        <div class="conversa-actions">
            ${mostraArquivados ? 
                `<button type="button" class="btn-restaurar" data-conversa-id="${conversa.conversa_id}" data-tipo-chat="${conversa.tipo_chat}">
                    <i class="fas fa-box-open"></i> Restaurar
                </button>
                <button type="button" class="btn-excluir" data-conversa-id="${conversa.conversa_id}" data-tipo-chat="${conversa.tipo_chat}">
                    <i class="fas fa-trash"></i> Excluir
                </button>` : 
                `<button type="button" class="btn-arquivar" data-conversa-id="${conversa.conversa_id}" data-tipo-chat="${conversa.tipo_chat}">
                    <i class="fas fa-archive"></i> Arquivar
                </button>
                <a href="${chatUrl}" class="btn-chat"><i class="fas fa-comments"></i></a>`
            }
        </div>
    `;
    
    // Adicionar event listeners para os botões
    setTimeout(() => {
        const cardElement = document.getElementById(`conversa-${conversa.conversa_id}`);
        if (cardElement) {
            const btnArquivar = cardElement.querySelector('.btn-arquivar');
            const btnRestaurar = cardElement.querySelector('.btn-restaurar');
            const btnExcluir = cardElement.querySelector('.btn-excluir');
            
            if (btnArquivar) {
                btnArquivar.addEventListener('click', function() {
                    confirmarArquivamento(this.dataset.conversaId, this.dataset.tipoChat);
                });
            }
            
            if (btnRestaurar) {
                btnRestaurar.addEventListener('click', function() {
                    confirmarRestauracao(this.dataset.conversaId, this.dataset.tipoChat);
                });
            }
            
            if (btnExcluir) {
                btnExcluir.addEventListener('click', function() {
                    confirmarExclusao(this.dataset.conversaId, this.dataset.tipoChat);
                });
            }
        }
    }, 100);
    
    // Inserir na lista de forma segura
    if (conversasList) {
        // Verificar se há estado vazio para remover
        const emptyState = conversasList.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }
        
        const loadingEl = conversasList.querySelector('.loading-conversas');
        if (loadingEl) {
            loadingEl.remove();
        }
        
        function inserirConversaNaOrdemCorreta(card, conversaData) {
            const conversasList = document.getElementById('conversasList');
            const cards = conversasList.querySelectorAll('.conversa-card');
            
            // Se não há outras conversas, insere no início
            if (cards.length === 0) {
                conversasList.appendChild(card);
                return;
            }
            
            // Encontrar a posição correta baseada na data
            const novaData = new Date(conversaData.ultima_mensagem_data);
            let inserido = false;
            
            for (let i = 0; i < cards.length; i++) {
                const cardExistente = cards[i];
                const dataExistente = new Date(cardExistente.dataset.ultimaData || cardExistente.querySelector('.ultima-mensagem')?.textContent?.match(/\d{2}\/\d{2}\/\d{4}/)?.[0] || 0);
                
                // Se a nova conversa é mais recente, insere antes
                if (novaData > dataExistente) {
                    conversasList.insertBefore(card, cardExistente);
                    inserido = true;
                    break;
                }
            }
            
            // Se não encontrou posição (é a mais antiga), insere no final
            if (!inserido) {
                conversasList.appendChild(card);
            }
        }

        // E na função renderizarConversa(), substitua a inserção por:
        inserirConversaNaOrdemCorreta(card, conversa);

    }
    
    // Remover classe de nova após animação
    setTimeout(() => {
        if (card.classList) {
            card.classList.remove('nova');
        }
    }, 2000);
    
    // Adicionar ao cache
    conversasCache.set(conversa.conversa_id, conversa);
}

// Atualizar conversa existente
function atualizarConversaExistente(card, conversa) {
    if (!card || !card.classList) return;
    
    // Guardar o valor anterior de mensagens não lidas para comparação
    const badgeNovoAnterior = card.querySelector('.badge-novo');
    const mensagensAnteriores = badgeNovoAnterior ? 
        parseInt(badgeNovoAnterior.textContent.match(/\d+/)[0]) : 0;
    const mensagensAtuais = conversa.mensagens_nao_lidas || 0;
    
    // Atualizar badge de mensagens não lidas
    const badgeNovo = card.querySelector('.badge-novo');
    const compradorNomeDiv = card.querySelector('.comprador-nome');
    
    if (conversa.mensagens_nao_lidas > 0) {
        card.classList.add('nao-lida');
        card.dataset.tipo = 'nao-lida';
        
        if (!badgeNovo && compradorNomeDiv) {
            const novoBadge = document.createElement('span');
            novoBadge.className = 'badge-novo';
            novoBadge.textContent = `${conversa.mensagens_nao_lidas} nova${conversa.mensagens_nao_lidas > 1 ? 's' : ''}`;
            compradorNomeDiv.appendChild(novoBadge);
            
            // Aplicar animação para novo badge
            novoBadge.style.animation = 'pulse 1s ease-in-out';
            setTimeout(() => {
                novoBadge.style.animation = '';
            }, 1000);
            
        } else if (badgeNovo) {
            // Verificar se o número mudou (aumentou)
            if (mensagensAtuais > mensagensAnteriores) {
                // Aplicar animação apenas quando o número aumenta
                if (badgeNovo.getAttribute('data-animating') !== 'true') {
                    badgeNovo.setAttribute('data-animating', 'true');
                    badgeNovo.textContent = `${conversa.mensagens_nao_lidas} nova${conversa.mensagens_nao_lidas > 1 ? 's' : ''}`;
                    badgeNovo.style.animation = 'pulse 1s ease-in-out';
                    
                    setTimeout(() => {
                        badgeNovo.style.animation = '';
                        badgeNovo.setAttribute('data-animating', 'false');
                    }, 1000);
                }           
            } else if (mensagensAtuais !== mensagensAnteriores) {
                // Apenas atualizar texto sem animação se o número diminuiu ou mudou
                badgeNovo.textContent = `${conversa.mensagens_nao_lidas} nova${conversa.mensagens_nao_lidas > 1 ? 's' : ''}`;
            }
        }
    } else {
        card.classList.remove('nao-lida');
        card.dataset.tipo = 'lida';
        if (badgeNovo) badgeNovo.remove();
    }
    
    // Atualizar última mensagem
    const ultimaMsgElement = card.querySelector('.ultima-mensagem');
    if (ultimaMsgElement && conversa.ultima_mensagem) {
        const ultimaMsgTratada = tratarUltimaMensagem(conversa.ultima_mensagem);
        const dataFormatada = conversa.ultima_mensagem_data ? 
            formatarData(conversa.ultima_mensagem_data) : '';
        
        ultimaMsgElement.innerHTML = `${dataFormatada} - ${ultimaMsgTratada}`;
    }
    
    // Atualizar cache
    conversasCache.set(conversa.conversa_id, conversa);
}

// Remover conversa da lista
function removerConversa(conversaId) {
    const card = document.getElementById(`conversa-${conversaId}`);
    if (card) {
        card.style.opacity = '0';
        card.style.transform = 'translateX(-100%)';
        card.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            card.remove();
            conversasCache.delete(conversaId);
            
            // Verificar se a lista ficou vazia
            const conversasList = document.getElementById('conversasList');
            if (conversasList.children.length === 0) {
                mostrarEstadoVazio();
            }
        }, 300);
    }
}

// Adicione esta função utilitária
function elementoExiste(elemento) {
    return elemento && elemento.nodeType === 1;
}

// Modifique a função removerConversa para ser mais segura:
function removerConversa(conversaId) {
    const card = document.getElementById(`conversa-${conversaId}`);
    if (elementoExiste(card)) {
        card.style.opacity = '0';
        card.style.transform = 'translateX(-100%)';
        card.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            if (elementoExiste(card) && card.parentNode) {
                card.remove();
                conversasCache.delete(conversaId);
                
                // Verificar se a lista ficou vazia
                const conversasList = document.getElementById('conversasList');
                if (conversasList && conversasList.children.length === 0) {
                    mostrarEstadoVazio();
                }
            }
        }, 300);
    }
}

// Função de polling para atualizações
function iniciarPolling() {
    intervaloAtualizacao = setInterval(verificarAtualizacoes, TEMPO_POLLING);
}

function verificarAtualizacoes() {
    if (estaVerificando) return;
    
    estaVerificando = true;
    
    fetch(`atualizar_chats_ajax.php?aba=<?php echo $aba; ?>&ultima_verificacao=${ultimaVerificacao}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data || data.error) {
            if (data && data.error) {
                console.error('Erro na resposta:', data.error);
            }
            return;
        }
        
        ultimaVerificacao = data.timestamp || Math.floor(Date.now() / 1000);
        
        if (data.atualizado) {
            // 1. Processar novas conversas
            if (data.novas_conversas && Array.isArray(data.novas_conversas)) {
                data.novas_conversas.forEach(conversa => {
                    if (conversa && conversa.conversa_id) {
                        renderizarConversa(conversa);
                    }
                });
                
                if (data.novas_conversas.length > 0) {
                    mostrarNotificacao(`Nova${data.novas_conversas.length > 1 ? 's' : ''} conversa${data.novas_conversas.length > 1 ? 's' : ''} iniciada${data.novas_conversas.length > 1 ? 's' : ''}`);
                }
            }
            
            // 2. Processar conversas removidas
            if (data.conversas_removidas && Array.isArray(data.conversas_removidas)) {
                data.conversas_removidas.forEach(conv => {
                    if (conv && conv.conversa_id) {
                        removerConversa(conv.conversa_id);
                    }
                });
            }
            
            // 3. Atualizar mensagens não lidas
            if (data.contadores && Array.isArray(data.contadores)) {
                data.contadores.forEach(contador => {
                    if (contador && contador.conversa_id) {
                        const conversa = conversasCache.get(parseInt(contador.conversa_id));
                        if (conversa) {
                            conversa.mensagens_nao_lidas = parseInt(contador.nao_lidas) || 0;
                            const card = document.getElementById(`conversa-${contador.conversa_id}`);
                            if (card) {
                                atualizarConversaExistente(card, conversa);
                            }
                        }
                    }
                });
            }
            
            // 4. Atualizar últimas mensagens
            if (data.ultimas_mensagens && Array.isArray(data.ultimas_mensagens)) {
                data.ultimas_mensagens.forEach(msg => {
                    if (msg && msg.conversa_id) {
                        const conversa = conversasCache.get(parseInt(msg.conversa_id));
                        if (conversa) {
                            conversa.ultima_mensagem = msg.ultima_mensagem;
                            conversa.ultima_mensagem_data = msg.ultima_mensagem_data;
                            const card = document.getElementById(`conversa-${msg.conversa_id}`);
                            if (card) {
                                atualizarConversaExistente(card, conversa);
                            }
                            if (data.novas_conversas.length <= 0) {
                                mostrarNotificacaoNovasMensagens(data.ultimas_mensagens.length);
                                // Animação sutil
                                badgeNovo.style.animation = 'pulse 1s ease-in-out';
                                setTimeout(() => {
                                    badge.style.animation = '';
                                }, 1000);
                            }
                        }
                    }
                });
            }
            
            // 5. Atualizar estatísticas
            atualizarEstatisticas(data);
            
            // 6. Atualizar filtro se necessário
            const filtroAtivo = document.querySelector('.filter-btn.active');
            if (filtroAtivo && filtroAtivo.textContent && filtroAtivo.textContent.includes('Não Lidas')) {
                aplicarFiltroDinamico('nao-lidas');
            }
        }
    })
    .catch(error => {
        console.error('Erro na verificação:', error);
        setTimeout(verificarAtualizacoes, TEMPO_POLLING * 2);
    })
    .finally(() => {
        estaVerificando = false;
    });
}

function mostrarNotificacaoNovasMensagens(quantidade) {
    // Criar notificação sutil
    const notif = document.createElement('div');
    notif.className = 'notificacao-flutuante';
    notif.innerHTML = `
        <i class="fas fa-comment-dots"></i>
        <span>${quantidade} nova${quantidade > 1 ? 's' : ''} mensagem${quantidade > 1 ? 's' : ''}</span>
        <button onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Estilos
    notif.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 9999;
        animation: slideInRight 0.3s ease-out;
    `;
    
    document.body.appendChild(notif);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (notif.parentElement) {
            notif.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => notif.remove(), 300);
        }
    }, 5000);
}

// Adicionar estilos CSS para animações
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        50% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .badge-novo {
        animation: none; /* Reset inicial */
        display: inline-block;
        position: relative;
    }

    .notificacao-flutuante {
        font-size: 14px;
        font-weight: 500;
    }
    
    .notificacao-flutuante button {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        margin-left: 10px;
    }
    
    .conversa-card.nao-lida {
        background-color: #f0f9ff !important;
        border-left: 4px solid #2196F3 !important;
    }
`;
document.head.appendChild(style);

// Funções auxiliares
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatarPreco(preco) {
    return parseFloat(preco).toFixed(2).replace('.', ',');
}

function formatarData(dataStr) {
    const data = new Date(dataStr);
    return data.toLocaleDateString('pt-BR') + ' ' + 
           data.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
}

function tratarUltimaMensagem(mensagem) {
    if (mensagem.includes('[Imagem]')) {
        return '<i class="fas fa-image"></i>&nbsp;&nbsp;Enviou uma imagem';
    }
    // Truncar mensagem muito longa
    if (mensagem.length > 60) {
        return escapeHtml(mensagem.substring(0, 57)) + '...';
    }
    return escapeHtml(mensagem);
}

function mostrarEstadoVazio() {
    const conversasList = document.getElementById('conversasList');
    if (!conversasList) return;
    
    const mostraArquivados = <?php echo $mostrar_arquivados ? 'true' : 'false'; ?>;
    
    // Limpar lista
    conversasList.innerHTML = '';
    
    // Adicionar estado vazio
    const emptyState = document.createElement('div');
    emptyState.className = 'empty-state';
    emptyState.innerHTML = `
        <i class="fas fa-${mostraArquivados ? 'archive' : 'comments'}"></i>
        <h3>${mostraArquivados ? 'Nenhuma conversa arquivada' : 'Nenhuma conversa ainda'}</h3>
        <p>
            ${mostraArquivados ? 
                'As conversas que você arquivar aparecerão aqui.' : 
                'Quando você conversar com compradores ou vendedores, as conversas aparecerão aqui.'
            }
        </p>
    `;
    
    conversasList.appendChild(emptyState);
}

function mostrarErroCarregamento(mensagem) {
    const conversasList = document.getElementById('conversasList');
    const loadingEl = document.getElementById('loadingConversas');
    
    if (loadingEl) loadingEl.remove();
    
    conversasList.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Erro ao carregar conversas</h3>
            <p>${mensagem}</p>
            <button onclick="carregarConversasIniciais()" class="btn-chat" style="margin-top: 15px;">
                <i class="fas fa-redo"></i> Tentar novamente
            </button>
        </div>
    `;
}

function atualizarEstatisticas(data) {
    // Atualizar contadores na stats-bar
    const elementosValor = document.querySelectorAll('.stats-bar .stat-item .value');
    if (elementosValor.length >= 3) {
        // Conversas Ativas
        elementosValor[0].textContent = data.total_conversas || 0;
        // Não Lidas
        elementosValor[1].textContent = data.total_nao_lidas || 0;
        // Arquivadas
        elementosValor[2].textContent = data.total_arquivadas || 0;
    }
    
    // Atualizar badge da aba arquivados
    const badgeArquivados = document.querySelector('.aba:nth-child(2) .badge-aba');
    if (badgeArquivados) {
        if (data.total_arquivadas > 0) {
            badgeArquivados.textContent = data.total_arquivadas;
            badgeArquivados.style.display = 'inline-block';
        } else {
            badgeArquivados.style.display = 'none';
        }
    }
}

function aplicarFiltroDinamico(tipo) {
    const cards = document.querySelectorAll('.conversa-card');
    cards.forEach(card => {
        if (tipo === 'todas') {
            card.style.display = 'flex';
        } else if (tipo === 'nao-lidas') {
            card.style.display = (card.dataset.tipo === 'nao-lida') ? 'flex' : 'none';
        }
    });
}

function mostrarNotificacao(mensagem) {
    // Criar notificação sutil
    const notif = document.createElement('div');
    notif.className = 'notificacao-flutuante';
    notif.innerHTML = `
        <i class="fas fa-comment-alt"></i>
        <span>${mensagem}</span>
        <button onclick="this.parentElement.remove()">&times;</button>
    `;
    
    notif.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 9999;
        animation: slideInRight 0.3s ease-out;
    `;
    
    document.body.appendChild(notif);
    
    setTimeout(() => {
        if (notif.parentElement) {
            notif.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => notif.remove(), 300);
        }
    }, 4000);
}

function gerenciarEventosJanela() {
    window.addEventListener('focus', function() {
        if (!estaVerificando) {
            verificarAtualizacoes();
        }
    });
    
    window.addEventListener('blur', function() {
        if (intervaloAtualizacao) {
            clearInterval(intervaloAtualizacao);
            intervaloAtualizacao = null;
        }
    });
    
    window.addEventListener('focus', function() {
        if (!intervaloAtualizacao) {
            iniciarPolling();
        }
    });
}

// Modificar a função filtrarConversas existente
function filtrarConversas(tipo) {
    const buttons = document.querySelectorAll('.filter-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.closest('.filter-btn').classList.add('active');
    
    aplicarFiltroDinamico(tipo);
}

// Iniciar sistema quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(iniciarSistemaDinamico, 500);
});

    </script>
</body>
</html>