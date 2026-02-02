<?php
// src/admin/visualizar_chat.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;

if ($conversa_id <= 0) {
    header("Location: chats_admin.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Buscar info da conversa
try {
    $sql_conversa = "SELECT
                cc.*,
                p.id as produto_id,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                p.preco_desconto AS produto_preco_desconto,
                p.desconto_data_fim AS produto_desconto_data_fim,
                uc.nome AS comprador_nome,
                uc.email AS comprador_email,
                CASE 
                    WHEN cc.transportador_id IS NOT NULL THEN 'transportador' 
                    ELSE 'vendedor' 
                END AS tipo_conversa,
                -- Informações do outro participante (transportador OU vendedor)
                CASE 
                    WHEN cc.transportador_id IS NOT NULL THEN ut.nome
                    ELSE uv.nome
                END AS outro_participante_nome,
                CASE 
                    WHEN cc.transportador_id IS NOT NULL THEN ut.email
                    ELSE uv.email
                END AS outro_participante_email
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN usuarios uc ON cc.comprador_id = uc.id
            LEFT JOIN vendedores v ON p.vendedor_id = v.id
            LEFT JOIN usuarios uv ON v.usuario_id = uv.id
            LEFT JOIN transportadores trans ON cc.transportador_id = trans.usuario_id
            LEFT JOIN usuarios ut ON cc.transportador_id = ut.id
            WHERE cc.id = :conversa_id";
    
    $stmt = $conn->prepare($sql_conversa);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $conversa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversa) {
        header("Location: chats_admin.php");
        exit();
    }
    
    // Buscar acordos de compra relacionados a esta conversa (produto + comprador + vendedor)
    $sql_acordos = "SELECT 
                    pr.ID as proposta_id,
                    pr.preco_proposto,
                    pr.quantidade_proposta,
                    pr.forma_pagamento,
                    pr.opcao_frete,
                    pr.valor_total,
                    pr.status,
                    pr.data_inicio,
                    pr.data_atualizacao,
                    -- Contar assinaturas
                    (SELECT COUNT(*) FROM propostas_assinaturas pa WHERE pa.proposta_id = pr.ID) as total_assinaturas
                FROM propostas pr
                WHERE pr.produto_id = :produto_id
                AND pr.comprador_id = :comprador_id
                AND pr.vendedor_id = :vendedor_id
                ORDER BY pr.data_inicio DESC";
    
    $stmt_acordos = $conn->prepare($sql_acordos);
    $stmt_acordos->bindParam(':produto_id', $conversa['produto_id'], PDO::PARAM_INT);
    $stmt_acordos->bindParam(':comprador_id', $conversa['comprador_id'], PDO::PARAM_INT);
    $stmt_acordos->bindParam(':vendedor_id', $conversa['vendedor_id'], PDO::PARAM_INT);
    $stmt_acordos->execute();
    $acordos = $stmt_acordos->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada acordo, buscar as assinaturas correspondentes
    foreach ($acordos as &$acordo) {
        $sql_assinaturas = "SELECT pa.*, u.nome as nome_assinante, u.tipo as tipo_assinante
                           FROM propostas_assinaturas pa
                           INNER JOIN usuarios u ON pa.usuario_id = u.id
                           WHERE pa.proposta_id = :proposta_id
                           ORDER BY pa.data_assinatura";
        
        $stmt_assinaturas = $conn->prepare($sql_assinaturas);
        $stmt_assinaturas->bindParam(':proposta_id', $acordo['proposta_id'], PDO::PARAM_INT);
        $stmt_assinaturas->execute();
        $acordo['assinaturas'] = $stmt_assinaturas->fetchAll(PDO::FETCH_ASSOC);
    }
    
    unset($acordo); // Quebrar referência
    
    // Calcular preço final com desconto se aplicável
    $preco_final = $conversa['produto_preco'];
    $tem_desconto = false;
    $porcentagem_desconto = 0;
    
    if ($conversa['produto_preco_desconto'] && 
        $conversa['produto_preco_desconto'] > 0 && 
        $conversa['produto_preco_desconto'] < $conversa['produto_preco']) {
        
        // Verificar se o desconto ainda está válido
        $agora = date('Y-m-d H:i:s');
        if (empty($conversa['produto_desconto_data_fim']) || $conversa['produto_desconto_data_fim'] > $agora) {
            $tem_desconto = true;
            $preco_final = $conversa['produto_preco_desconto'];
            $porcentagem_desconto = round((($conversa['produto_preco'] - $conversa['produto_preco_desconto']) / $conversa['produto_preco']) * 100);
        }
    }
    
} catch (PDOException $e) {
    die("Erro ao buscar conversa: " . $e->getMessage());
}

// Buscar TODAS as mensagens (incluindo deletadas)
try {
    // ALTERAÇÃO AQUI: Adicionado 'tipo' e 'data_delecao' no SELECT
    $sql_mensagens = "SELECT 
                cm.*,
                cm.tipo, 
                cm.data_delecao,
                u.nome AS remetente_nome,
                DATE_FORMAT(cm.data_envio, '%d/%m/%Y %H:%i:%s') as data_formatada
            FROM chat_mensagens cm
            INNER JOIN usuarios u ON cm.remetente_id = u.id
            WHERE cm.conversa_id = :conversa_id
            ORDER BY cm.data_envio ASC";
    
    $stmt = $conn->prepare($sql_mensagens);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $mensagens = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Chat - Admin</title>
    <link rel="stylesheet" href="css/visualizar_chat.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="top-controls">
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="chats_admin.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Voltar para Lista
                </a>
                
                <?php if (count($acordos) > 0): ?>
                    <button id="btnOpenModal" class="btn-acordos">
                        <i class="fas fa-file-signature"></i> Ver Acordos de Compra
                        <span class="badge"><?php echo count($acordos); ?></span>
                    </button>
                <?php else: ?>
                    <button id="btnOpenModal" class="btn-acordos" style="opacity: 0.7; cursor: not-allowed;" disabled>
                        <i class="fas fa-file-signature"></i> Nenhum Acordo
                    </button>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <a href="exportar_pdf.php?conversa_id=<?php echo $conversa_id; ?>" target="_blank" class="btn-export">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
            </div>
        </div>
        
        <div class="header">
            <h1>
                <i class="fas fa-eye"></i>
                Visualização de Chat (Admin)
            </h1>
            
            <?php if ($conversa['deletado']): ?>
                <div class="alert-deletada">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>ATENÇÃO:</strong> Esta conversa foi deletada em 
                    <?php echo date('d/m/Y H:i', strtotime($conversa['data_delecao'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="produto-info">
                <img src="<?php echo htmlspecialchars($conversa['produto_imagem'] ?: '../../img/placeholder.png'); ?>" alt="Produto">
                <div class="produto-details">
                    <h3><?php echo htmlspecialchars($conversa['produto_nome']); ?></h3>
                    <div class="produto-preco">
                        <?php if ($tem_desconto): ?>
                            <span class="preco-original">R$ <?php echo number_format($conversa['produto_preco'], 2, ',', '.'); ?></span>
                            <span>R$ <?php echo number_format($preco_final, 2, ',', '.'); ?></span>
                            <span class="badge-desconto">-<?php echo $porcentagem_desconto; ?>%</span>
                        <?php else: ?>
                            R$ <?php echo number_format($preco_final, 2, ',', '.'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="usuarios-box">
                <div class="usuario-box">
                    <h4><i class="fas fa-user"></i> Comprador</h4>
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($conversa['comprador_nome']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($conversa['comprador_email']); ?></p>
                    <p><strong>ID:</strong> <?php echo $conversa['comprador_id']; ?></p>
                </div>
                
                <div class="usuario-box">
                    <h4><?php if ($conversa['tipo_conversa'] === 'transportador'): ?><i class="fas fa-truck"></i> Transportador<?php else: ?><i class="fas fa-store"></i> Vendedor<?php endif; ?></h4>
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($conversa['outro_participante_nome']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($conversa['outro_participante_email']); ?></p>
                    <p><strong>ID:</strong> 
                        <?php 
                        if ($conversa['tipo_conversa'] === 'transportador') {
                            echo $conversa['transportador_id'];
                        } else {
                            echo $conversa['vendedor_id'];
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="chat-box">
            <?php if (count($mensagens) > 0): ?>
                <?php foreach ($mensagens as $msg): 
                    $eh_comprador = $msg['remetente_id'] == $conversa['comprador_id'];
                    $classe = $eh_comprador ? 'comprador' : 'vendedor';
                    if ($msg['deletado']) $classe .= ' deletada';
                ?>
                    <div class="message <?php echo $classe; ?>">
                        <div class="message-header">
                            <span class="message-remetente">
                                <?php echo htmlspecialchars($msg['remetente_nome']); ?>
                                (<?php echo $eh_comprador ? 'Comprador' : ($conversa['tipo_conversa'] === 'transportador' ? 'Transportador' : 'Vendedor'); ?>)
                            </span>
                            <span class="message-data"><?php echo $msg['data_formatada']; ?></span>
                        </div>
                        <div class="message-conteudo">
                            <?php if ($msg['tipo'] === 'imagem'): ?>
                                <a href="<?php echo htmlspecialchars($msg['mensagem']); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($msg['mensagem']); ?>" alt="Imagem Enviada" class="message-imagem">
                                </a>
                            <?php else: ?>
                                <?php echo nl2br(htmlspecialchars($msg['mensagem'])); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($msg['deletado']): ?>
                            <div class="message-deletada-info">
                                <i class="fas fa-trash"></i>
                                Mensagem deletada em <?php echo date('d/m/Y H:i', strtotime($msg['data_delecao'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-chat">
                    <i class="fas fa-comments"></i>
                    <h3>Nenhuma mensagem nesta conversa</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Acordos -->
    <div id="modalAcordos" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="fecharModal()">&times;</button>
            
            <div class="modal-header">
                <h2>
                    <i class="fas fa-file-contract"></i>
                    Acordos de Compra
                    <span class="modal-count"><?php echo count($acordos); ?></span>
                </h2>
            </div>
            
            <div class="acordos-container">
                <?php if (count($acordos) > 0): ?>
                    <?php foreach ($acordos as $index => $acordo): ?>
                        <div class="acordo-item" data-id="<?php echo $acordo['proposta_id']; ?>">
                            <div class="acordo-header" onclick="toggleAcordo(<?php echo $index; ?>)">
                                <div class="acordo-title">
                                    <div class="acordo-id">Proposta #<?php echo $acordo['proposta_id']; ?></div>
                                    <?php 
                                        $status_class = '';
                                        $status_text = $acordo['status'] ?? 'pendente';
                                        switch($status_text) {
                                            case 'aceita': $status_class = 'status-aceita'; break;
                                            case 'negociacao': $status_class = 'status-negociacao'; break;
                                            case 'recusada': $status_class = 'status-recusada'; break;
                                            default: $status_class = ''; break;
                                        }
                                    ?>
                                    <span class="acordo-status <?php echo $status_class; ?>">
                                        <?php echo ucfirst($status_text); ?>
                                    </span>
                                </div>
                                <div class="toggle-icon">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            
                            <div class="acordo-details" id="acordoDetails<?php echo $index; ?>">
                                <div class="acordo-info-grid">
                                    <div class="info-item">
                                        <span class="info-label">Valor Total</span>
                                        <span class="info-value">R$ <?php echo number_format($acordo['valor_total'] ?? 0, 2, ',', '.'); ?></span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Quantidade</span>
                                        <span class="info-value"><?php echo $acordo['quantidade_proposta']; ?> unidades</span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Preço Unitário</span>
                                        <span class="info-value">R$ <?php echo number_format($acordo['preco_proposto'] ?? 0, 2, ',', '.'); ?></span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Forma de Pagamento</span>
                                        <span class="info-value"><?php echo htmlspecialchars($acordo['forma_pagamento'] ?? 'Não informado'); ?></span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Opção de Frete</span>
                                        <span class="info-value"><?php echo htmlspecialchars($acordo['opcao_frete'] ?? 'Não informado'); ?></span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Data da Proposta</span>
                                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($acordo['data_inicio'])); ?></span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Última Atualização</span>
                                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($acordo['data_atualizacao'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($acordo['assinaturas'])): ?>
                                    <div class="assinaturas-section">
                                        <div class="assinaturas-title">
                                            <i class="fas fa-signature"></i>
                                            Assinaturas Digitais
                                            <span class="assinaturas-count"><?php echo $acordo['total_assinaturas']; ?></span>
                                        </div>
                                        
                                        <div class="assinaturas-grid">
                                            <?php foreach ($acordo['assinaturas'] as $assinatura): ?>
                                                <div class="assinatura-card">
                                                    <div class="assinatura-header">
                                                        <div class="assinatura-nome"><?php echo htmlspecialchars($assinatura['nome_assinante'] ?? 'Não informado'); ?></div>
                                                        <div class="assinatura-tipo"><?php echo ucfirst($assinatura['tipo_assinante'] ?? ''); ?></div>
                                                    </div>
                                                    
                                                    <?php 
                                                        $imagem_assinatura = $assinatura['assinatura_imagem'] ?? '';
                                                        if (!empty($imagem_assinatura)) {
                                                            // Corrigir formato se necessário
                                                            if (strpos($imagem_assinatura, 'data:image') !== 0) {
                                                                $imagem_assinatura = 'data:image/png;base64,' . $imagem_assinatura;
                                                            }
                                                    ?>
                                                        <div class="assinatura-img-container">
                                                            <img class="assinatura-img" src="<?php echo htmlspecialchars($imagem_assinatura); ?>" 
                                                                 alt="Assinatura de <?php echo htmlspecialchars($assinatura['nome_assinante']); ?>">
                                                        </div>
                                                    <?php } else { ?>
                                                        <div class="no-assinatura">
                                                            <i class="fas fa-signature"></i><br>
                                                            <span>Assinatura sem imagem</span>
                                                        </div>
                                                    <?php } ?>
                                                    
                                                    <div class="assinatura-info">
                                                        <div class="assinatura-info-row">
                                                            <strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($assinatura['data_assinatura'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-acordos-modal">
                        <i class="fas fa-file-contract"></i>
                        <h3>Nenhum acordo de compra encontrado</h3>
                        <p>Esta conversa ainda não gerou nenhum acordo formal de compra.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Modal functions
        function abrirModal() {
            document.getElementById('modalAcordos').style.display = 'flex';
        }
        
        function fecharModal() {
            document.getElementById('modalAcordos').style.display = 'none';
            
            // Fechar todos os acordos ao fechar o modal
            const acordos = document.querySelectorAll('.acordo-details');
            const headers = document.querySelectorAll('.acordo-header');
            
            acordos.forEach(detail => {
                detail.classList.remove('active');
            });
            
            headers.forEach(header => {
                header.classList.remove('active');
            });
        }
        
        // Toggle accordion
        function toggleAcordo(index) {
            const details = document.getElementById('acordoDetails' + index);
            const header = document.querySelector(`.acordo-item[data-id="${index}"] .acordo-header`);
            
            details.classList.toggle('active');
            header.classList.toggle('active');
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('modalAcordos').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModal();
            }
        });
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('modalAcordos').style.display === 'flex') {
                fecharModal();
            }
        });
        
        // Abrir modal ao clicar no botão (se houver acordos)
        document.addEventListener('DOMContentLoaded', function() {
            const btnOpenModal = document.getElementById('btnOpenModal');
            if (btnOpenModal && !btnOpenModal.disabled) {
                btnOpenModal.addEventListener('click', abrirModal);
            }
            
            // Se houver apenas 1 acordo, abrir automaticamente
            const acordoCount = <?php echo count($acordos); ?>;
            if (acordoCount === 1) {
                // Abrir o primeiro acordo automaticamente
                setTimeout(() => {
                    const primeiroHeader = document.querySelector('.acordo-header');
                    if (primeiroHeader) {
                        primeiroHeader.click();
                    }
                }, 100);
            }
        });
    </script>
</body>
</html>