<?php
// src/comprador/meus_chats.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar se está logado como comprador ou vendedor
if (!isset($_SESSION['usuario_tipo']) || ($_SESSION['usuario_tipo'] !== 'comprador' && $_SESSION['usuario_tipo'] !== 'vendedor' && $_SESSION['usuario_tipo'] !== 'transportador')) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Comprador');

$database = new Database();
$conn = $database->getConnection();

// Verificar se está visualizando arquivados ou ativos
$aba = isset($_GET['aba']) ? $_GET['aba'] : 'ativos';
$mostrar_arquivados = ($aba === 'arquivados');

// Processar arquivamento/restauração E EXCLUSÃO de conversa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $conversa_id = $_POST['conversa_id'] ?? 0;
        
        if ($conversa_id > 0) {
            try {
                $sql = null;
                // Prepara variáveis para auditoria
                $acao_audit = '';
                $detalhes_audit = '';

                if ($_POST['action'] === 'arquivar_conversa') {
                    $sql = "UPDATE chat_conversas SET favorito_comprador = 1 WHERE id = :conversa_id AND comprador_id = :usuario_id";
                    $mensagem_sucesso = "Conversa arquivada com sucesso!";
                    $acao_audit = 'arquivar_conversa';
                } elseif ($_POST['action'] === 'restaurar_conversa') {
                    $sql = "UPDATE chat_conversas SET favorito_comprador = 0 WHERE id = :conversa_id AND comprador_id = :usuario_id";
                    $mensagem_sucesso = "Conversa restaurada com sucesso!";
                    $acao_audit = 'restaurar_conversa';
                } elseif ($_POST['action'] === 'excluir_conversa') {
                    // --- NOVA LÓGICA DE EXCLUSÃO ---
                    // Apenas marca como excluído para o comprador
                    $sql = "UPDATE chat_conversas SET comprador_excluiu = 1 WHERE id = :conversa_id AND comprador_id = :usuario_id";
                    $mensagem_sucesso = "Conversa excluída com sucesso!";
                    $acao_audit = 'excluir_conversa_usuario';
                    $detalhes_audit = 'Comprador excluiu o chat da sua lista';
                }
                
                if (isset($sql)) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
                    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    // Registrar na auditoria
                    $sql_audit = "INSERT INTO chat_auditoria (conversa_id, usuario_id, acao, detalhes) 
                                 VALUES (:conversa_id, :usuario_id, :acao, :detalhes)";
                    $stmt_audit = $conn->prepare($sql_audit);
                    $stmt_audit->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
                    $stmt_audit->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                    $stmt_audit->bindParam(':acao', $acao_audit);
                    $detalhes_final = $detalhes_audit ?: 'Ação realizada pelo comprador';
                    $stmt_audit->bindParam(':detalhes', $detalhes_final);
                    $stmt_audit->execute();
                    
                    header("Location: meus_chats.php?aba=" . $aba . "&success=1&msg=" . urlencode($mensagem_sucesso));
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Erro ao processar conversa: " . $e->getMessage());
                $error = "Erro ao processar conversa. Tente novamente.";
            }
        }
    }
}

// Verificar se veio de um redirecionamento com sucesso
$success = isset($_GET['success']) && $_GET['success'] == 1;
$success_msg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';

// Verificar se tem filtro na URL
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todas';

// BUSCAR CONVERSAS DO COMPRADOR
try {
    // --- ALTERAÇÃO NO SELECT: Filtrar comprador_excluiu = 0 ---
    $sql = "SELECT 
                cc.id AS conversa_id,
                cc.produto_id,
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                v.id AS vendedor_sistema_id,
                u.id AS vendedor_usuario_id,
                u.nome AS vendedor_nome,
                v.nome_comercial AS vendedor_nome_comercial,
                cc.favorito_comprador AS arquivado,
                (SELECT COUNT(*) 
                 FROM chat_mensagens cm 
                 WHERE cm.conversa_id = cc.id 
                 AND cm.remetente_id != :usuario_id 
                 AND cm.lida = 0) AS mensagens_nao_lidas
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN vendedores v ON p.vendedor_id = v.id
            INNER JOIN usuarios u ON v.usuario_id = u.id
            WHERE cc.comprador_id = :usuario_id
            AND cc.status = 'ativo'
            AND cc.comprador_excluiu = 0
            AND cc.transportador_id IS NULL"; // mostrar apenas conversas sem transportador
    
    if ($mostrar_arquivados) {
        $sql .= " AND cc.favorito_comprador = 1";
    } else {
        $sql .= " AND cc.favorito_comprador = 0";
    }
    
    $sql .= " ORDER BY cc.ultima_mensagem_data DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar conversas: " . $e->getMessage());
    $conversas = [];
}

// Filtrar conversas por não lidas se necessário
$conversas_filtradas = $conversas;
if (!$mostrar_arquivados && $filtro === 'nao-lidos') {
    $conversas_filtradas = array_filter($conversas, function($conv) {
        return $conv['mensagens_nao_lidas'] > 0;
    });
}

// Contar totais
$total_conversas = count($conversas);
$total_nao_lidas = 0;
foreach ($conversas as $conversa) {
    $total_nao_lidas += $conversa['mensagens_nao_lidas'];
}

// Contar conversas arquivadas para mostrar no badge
try {
    // --- ALTERAÇÃO NA CONTAGEM: Filtrar excluídos ---
    $sql_arquivadas = "SELECT COUNT(*) as total 
                      FROM chat_conversas cc
                      WHERE cc.comprador_id = :usuario_id 
                      AND cc.status = 'ativo'
                      AND cc.favorito_comprador = 1
                      AND cc.comprador_excluiu = 0
                      AND cc.transportador_id IS NULL"; // contar apenas conversas sem transportador
    
    $stmt_arquivadas = $conn->prepare($sql_arquivadas);
    $stmt_arquivadas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_arquivadas->execute();
    $total_arquivadas = $stmt_arquivadas->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (PDOException $e) {
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
                        <a href="meus_chats.php" class="nav-link active">Chats</a>
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
                                $stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
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
                <p>Gerencie seus chats com vendedores sobre produtos de interesse</p>
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
                    <div><div class="label">Não Lidas</div><div class="value"><?php echo $total_nao_lidas; ?></div></div>
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
            <button class="aba <?php echo !$mostrar_arquivados ? 'active' : ''; ?>" onclick="window.location.href='meus_chats.php?aba=ativos'">
                <i class="fas fa-comments"></i> Conversas Ativas
            </button>
            <button class="aba <?php echo $mostrar_arquivados ? 'active' : ''; ?>" onclick="window.location.href='meus_chats.php?aba=arquivados'">
                <i class="fas fa-archive"></i> Arquivadas
                <?php if ($total_arquivadas > 0): ?><span class="badge-aba"><?php echo $total_arquivadas; ?></span><?php endif; ?>
            </button>
        </div>

        <div class="conversas-container">
            <div class="conversas-header">
                <h2><?php echo $mostrar_arquivados ? 'Conversas Arquivadas' : 'Conversas Recentes'; ?></h2>
                <?php if (!$mostrar_arquivados): ?>
                <div class="filter-buttons">
                    <button class="filter-btn <?php echo $filtro === 'todas' ? 'active' : ''; ?>" onclick="filtrarConversas('todas')"><i class="fas fa-list"></i> Todas</button>
                    <button class="filter-btn <?php echo $filtro === 'nao-lidos' ? 'active' : ''; ?>" onclick="filtrarConversas('nao-lidas')"><i class="fas fa-envelope"></i> Não Lidas</button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="conversas-list">
                <?php if (count($conversas_filtradas) > 0): ?>
                    <?php foreach ($conversas_filtradas as $conversa): 
                        $imagem_produto = $conversa['produto_imagem'] ? htmlspecialchars($conversa['produto_imagem']) : '../../img/placeholder.png';
                        $tem_nao_lidas = $conversa['mensagens_nao_lidas'] > 0;
                        $data_formatada = $conversa['ultima_mensagem_data'] ? date('d/m/Y H:i', strtotime($conversa['ultima_mensagem_data'])) : '';
                        $vendedor_display = $conversa['vendedor_nome_comercial'] ?: $conversa['vendedor_nome'];
                        $esta_arquivado = $conversa['arquivado'] == 1;
                        
                        if ($mostrar_arquivados || $esta_arquivado) {
                            $chat_url = '#';
                        } else {
                            $chat_url = "../chat/chat.php?produto_id={$conversa['produto_id']}&ref=meus_chats&aba=" . ($mostrar_arquivados ? 'arquivados' : 'ativos');
                        }
                    ?>
                        <div class="conversa-card <?php echo $tem_nao_lidas ? 'nao-lida' : ''; ?> <?php echo $esta_arquivado ? 'arquivado' : ''; ?>" 
                             data-tipo="<?php echo $tem_nao_lidas ? 'nao-lida' : 'lida'; ?>"
                             id="conversa-<?php echo $conversa['conversa_id']; ?>">
                            
                            <?php if (!$mostrar_arquivados && !$esta_arquivado): ?>
                                <a href="<?php echo $chat_url; ?>" style="display: flex; gap: 1.5rem; align-items: center; text-decoration: none; color: inherit; flex: 1;">
                            <?php else: ?>
                                <div style="display: flex; gap: 1.5rem; align-items: center; flex: 1; cursor: default;">
                            <?php endif; ?>
                                
                                <div class="produto-thumb">
                                    <img src="<?php echo $imagem_produto; ?>" alt="<?php echo htmlspecialchars($conversa['produto_nome']); ?>">
                                </div>
                                
                                <div class="conversa-info">
                                    <div class="conversa-top">
                                        <div class="produto-nome-principal">
                                            <i class="fas fa-box"></i>
                                            <?php echo htmlspecialchars($conversa['produto_nome']); ?>
                                            <?php if ($esta_arquivado): ?>
                                                <span class="badge-arquivado"><i class="fas fa-archive"></i> Arquivado</span>
                                            <?php endif; ?>
                                            <?php if ($tem_nao_lidas && !$mostrar_arquivados && !$esta_arquivado): ?>
                                                <span class="badge-novo"><?php echo $conversa['mensagens_nao_lidas']; ?> nova<?php echo $conversa['mensagens_nao_lidas'] > 1 ? 's' : ''; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="conversa-data">
                                            <i class="far fa-clock"></i> <?php echo $data_formatada; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="vendedor-info">
                                        <i class="fas fa-store"></i>
                                        Vendedor: <?php echo htmlspecialchars($vendedor_display); ?>
                                        <span class="produto-preco">- R$ <?php echo number_format($conversa['produto_preco'], 2, ',', '.'); ?></span>
                                    </div>
                                    
                                    <?php if ($conversa['ultima_mensagem']): ?>
                                        <div class="ultima-mensagem">
                                            <i class="fas fa-comment"></i>
                                            <?php 
                                            if (strpos($conversa['ultima_mensagem'], '[Imagem]') !== false) {
                                                echo '<i class="fas fa-image"></i> [Imagem]';
                                            } else {
                                                echo htmlspecialchars($conversa['ultima_mensagem']);
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            
                            <?php if (!$mostrar_arquivados && !$esta_arquivado): ?>
                                </a>
                            <?php else: ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="conversa-actions">
                                <?php if ($mostrar_arquivados || $esta_arquivado): ?>
                                    <button type="button" class="btn-restaurar" onclick="confirmarRestauracao(<?php echo $conversa['conversa_id']; ?>)">
                                        <i class="fas fa-box-open"></i> Restaurar
                                    </button>
                                    
                                    <button type="button" class="btn-excluir" onclick="confirmarExclusao(<?php echo $conversa['conversa_id']; ?>)">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>

                                <?php else: ?>
                                    <a href="<?php echo $chat_url; ?>" class="btn-chat">
                                        <i class="fas fa-comments"></i> Abrir Chat
                                    </a>
                                    <button type="button" class="btn-arquivar" onclick="confirmarArquivamento(<?php echo $conversa['conversa_id']; ?>)">
                                        <i class="fas fa-archive"></i> Arquivar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-<?php echo $mostrar_arquivados ? 'archive' : 'comments'; ?>"></i>
                        <h3><?php echo $mostrar_arquivados ? 'Nenhuma conversa arquivada' : 'Nenhuma conversa ' . ($filtro === 'nao-lidos' ? 'não lida' : 'encontrada'); ?></h3>
                        <p>
                            <?php if ($mostrar_arquivados): ?>
                                As conversas que você arquivar aparecerão aqui.
                            <?php elseif ($filtro === 'nao-lidos'): ?>
                                Você não tem mensagens novas no momento.
                            <?php else: ?>
                                Quando você conversar com vendedores sobre produtos, as conversas aparecerão aqui.
                            <?php endif; ?>
                        </p>
                        <?php if (!$mostrar_arquivados && $filtro === 'nao-lidos'): ?>
                            <a href="meus_chats.php?aba=ativos" class="btn-anuncios">Ver Todas as Conversas</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-overlay modal-arquivar" id="arquivarModal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-archive"></i> Arquivar Conversa</h3></div>
            <div class="modal-body">
                <p>Tem certeza que deseja arquivar esta conversa? <strong>Após arquivar:</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px; color: #666;">
                    <li>A conversa será movida para a seção "Arquivadas"</li>
                    <li>Para voltar a conversar, será necessário restaurar a conversa</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="fecharModal('arquivar')">Cancelar</button>
                <form id="arquivarForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="arquivar_conversa">
                    <input type="hidden" id="conversa_id_arquivar" name="conversa_id">
                    <button type="submit" class="btn-confirm-arquivar"><i class="fas fa-archive"></i> Sim, Arquivar</button>
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
                    <button type="submit" class="btn-confirm-restaurar"><i class="fas fa-box-open"></i> Sim, Restaurar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay modal-excluir" id="excluirModal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-trash"></i> Excluir Conversa</h3></div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir esta conversa?</p>
                <ul style="margin-left: 20px; margin-top: 10px; color: #666;">
                    <li>A conversa sumirá da sua lista <strong>permanentemente</strong>.</li>
                    <li>O vendedor ainda poderá ver a conversa.</li>
                    <li>O administrador ainda poderá auditar a conversa.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="fecharModal('excluir')">Cancelar</button>
                <form id="excluirForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="excluir_conversa">
                    <input type="hidden" id="conversa_id_excluir" name="conversa_id">
                    <button type="submit" class="btn-confirm-excluir"><i class="fas fa-trash"></i> Sim, Excluir</button>
                </form>
            </div>
        </div>
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
        
        function filtrarConversas(tipo) {
            const cards = document.querySelectorAll('.conversa-card');
            const buttons = document.querySelectorAll('.filter-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.closest('.filter-btn').classList.add('active');
            
            cards.forEach(card => {
                if (tipo === 'todas') {
                    card.style.display = 'flex';
                } else if (tipo === 'nao-lidas') {
                    if (card.dataset.tipo === 'nao-lida') {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }
        
        function confirmarArquivamento(conversaId) {
            document.getElementById('conversa_id_arquivar').value = conversaId;
            document.getElementById('arquivarModal').style.display = 'flex';
        }
        
        function confirmarRestauracao(conversaId) {
            document.getElementById('conversa_id_restaurar').value = conversaId;
            document.getElementById('restaurarModal').style.display = 'flex';
        }

        // Função para abrir modal de exclusão
        function confirmarExclusao(conversaId) {
            document.getElementById('conversa_id_excluir').value = conversaId;
            document.getElementById('excluirModal').style.display = 'flex';
        }
        
        function fecharModal(tipo) {
            document.getElementById(tipo + 'Modal').style.display = 'none';
        }
        
        // Fechar modal ao clicar fora ou ESC
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = "none";
            }
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => modal.style.display = 'none');
            }
        });
        
        <?php if ($success): ?>
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>

        // ============== ATUALIZAÇÃO DINÂMICA VIA AJAX ==============
let ultimaVerificacao = Math.floor(Date.now() / 1000);
let estaVerificando = false;
let intervaloAtualizacao = null;
const TEMPO_POLLING = 10000; // 10 segundos

function iniciarPolling() {
    // Verificar imediatamente
    verificarAtualizacoes();
    
    // Configurar intervalo
    intervaloAtualizacao = setInterval(verificarAtualizacoes, TEMPO_POLLING);
    
    // Verificar quando a janela ganha foco
    window.addEventListener('focus', function() {
        if (!estaVerificando) {
            verificarAtualizacoes();
        }
    });
    
    // Parar polling quando a janela perde foco (opcional, economiza recursos)
    window.addEventListener('blur', function() {
        if (intervaloAtualizacao) {
            clearInterval(intervaloAtualizacao);
            intervaloAtualizacao = null;
        }
    });
    
    // Retomar quando ganha foco novamente
    window.addEventListener('focus', function() {
        if (!intervaloAtualizacao) {
            intervaloAtualizacao = setInterval(verificarAtualizacoes, TEMPO_POLLING);
        }
    });
}

function verificarAtualizacoes() {
    if (estaVerificando) return;
    
    estaVerificando = true;
    
    fetch(`atualizar_chats_ajax.php?aba=<?php echo $aba; ?>&ultima_verificacao=${ultimaVerificacao}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro na rede');
        }
        return response.json();
    })
    .then(data => {
        // Atualizar timestamp
        ultimaVerificacao = data.timestamp || Math.floor(Date.now() / 1000);
        
        if (data.error) {
            console.error('Erro:', data.error);
            return;
        }
        
        // Se houve atualizações
        if (data.atualizado) {
            // 1. Atualizar badges de mensagens não lidas
            if (data.contadores && Array.isArray(data.contadores)) {
                data.contadores.forEach(contador => {
                    atualizarBadgeConversa(contador.conversa_id, contador.nao_lidas);
                });
            }
            
            // 2. Atualizar contador na stats-bar
            if (data.total_nao_lidas !== undefined) {
                const elementoTotal = document.querySelector('.stats-bar .stat-item:nth-child(2) .value');
                if (elementoTotal) {
                    elementoTotal.textContent = data.total_nao_lidas;
                }
            }
            
            // 3. Atualizar últimas mensagens
            if (data.ultimas_mensagens && Array.isArray(data.ultimas_mensagens)) {
                data.ultimas_mensagens.forEach(msg => {
                    atualizarUltimaMensagem(msg.conversa_id, msg.ultima_mensagem, msg.ultima_mensagem_data);
                });
            }
            
            // 4. Mostrar notificação sutil (opcional)
            if (data.novas_mensagens && data.novas_mensagens.length > 0) {
                mostrarNotificacaoNovasMensagens(data.novas_mensagens.length);
            }
            
            // 5. Atualizar filtro se estiver ativo
            const filtroAtivo = document.querySelector('.filter-btn.active');
            if (filtroAtivo && filtroAtivo.textContent.includes('Não Lidas')) {
                filtrarConversas('nao-lidas');
            }
        }
    })
    .catch(error => {
        console.error('Erro na verificação:', error);
        // Tentar novamente mais tarde
        setTimeout(verificarAtualizacoes, TEMPO_POLLING * 2);
    })
    .finally(() => {
        estaVerificando = false;
    });
}

function atualizarBadgeConversa(conversaId, quantidade) {
    const card = document.getElementById(`conversa-${conversaId}`);
    if (!card) return;
    
    // Encontrar ou criar badge
    let badge = card.querySelector('.badge-novo');
    const produtoNomeDiv = card.querySelector('.produto-nome-principal');
    
    if (quantidade > 0) {
        // Adicionar classe de não lida
        card.classList.add('nao-lida');
        card.setAttribute('data-tipo', 'nao-lida');
        
        if (!badge && produtoNomeDiv) {
            badge = document.createElement('span');
            badge.className = 'badge-novo';
            produtoNomeDiv.appendChild(badge);
        }
        
        if (badge) {
            badge.textContent = `${quantidade} nova${quantidade > 1 ? 's' : ''}`;
            badge.style.display = 'inline-block';
            
            // Animação sutil
            badge.style.animation = 'pulse 1s ease-in-out';
            setTimeout(() => {
                badge.style.animation = '';
            }, 1000);
        }
    } else {
        // Remover badge se não houver mensagens não lidas
        card.classList.remove('nao-lida');
        card.setAttribute('data-tipo', 'lida');
        
        if (badge) {
            badge.remove();
        }
    }
}

function atualizarUltimaMensagem(conversaId, mensagem, dataStr) {
    const card = document.getElementById(`conversa-${conversaId}`);
    if (!card) return;
    
    // Atualizar data
    const dataElement = card.querySelector('.conversa-data');
    if (dataElement && dataStr) {
        const data = new Date(dataStr);
        const dataFormatada = data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        dataElement.innerHTML = `<i class="far fa-clock"></i> ${dataFormatada}`;
    }
    
    // Atualizar mensagem
    const msgElement = card.querySelector('.ultima-mensagem');
    if (msgElement && mensagem) {
        if (mensagem.includes('[Imagem]')) {
            msgElement.innerHTML = '<i class="fas fa-comment"></i> <i class="fas fa-image"></i> [Imagem]';
        } else {
            // Limitar tamanho da mensagem
            const msgTruncada = mensagem.length > 80 ? mensagem.substring(0, 77) + '...' : mensagem;
            msgElement.innerHTML = `<i class="fas fa-comment"></i> ${msgTruncada}`;
        }
    }
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
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
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

// Iniciar polling quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(iniciarPolling, 2000); // Esperar 2 segundos após carregamento
});

// ============== FIM DA ATUALIZAÇÃO DINÂMICA ==============
    </script>
</body>
</html>