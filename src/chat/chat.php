<?php
// src/chat/chat.php
session_start();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/chat_config.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
$conversa_id_get = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;

// Capturar de onde o usuário veio
$referrer = isset($_GET['ref']) ? $_GET['ref'] : '';

if ($produto_id <= 0) {
    header("Location: ../anuncios.php?erro=" . urlencode("Produto não encontrado"));
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Buscar informações do produto
$sql_produto = "SELECT p.*, v.id AS vendedor_sistema_id, u.id AS vendedor_usuario_id, 
                v.nome_comercial AS nome_vendedor, u.nome AS vendedor_nome
                FROM produtos p
                JOIN vendedores v ON p.vendedor_id = v.id
                JOIN usuarios u ON v.usuario_id = u.id
                WHERE p.id = :produto_id";

$stmt = $conn->prepare($sql_produto);
$stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
$stmt->execute();
$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    header("Location: ../anuncios.php?erro=" . urlencode("Produto não encontrado"));
    exit();
}

$vendedor_usuario_id = $produto['vendedor_usuario_id'];
$eh_vendedor_produto = ($vendedor_usuario_id == $usuario_id);

// Lógica para VENDEDOR
if ($eh_vendedor_produto) {
    if ($conversa_id_get > 0) {
        $conversa_id = $conversa_id_get;
        
        $sql_conversa = "SELECT c.comprador_id, u.nome AS comprador_nome
                        FROM chat_conversas c
                        JOIN usuarios u ON c.comprador_id = u.id
                        WHERE c.id = :conversa_id";
        
        $stmt_conv = $conn->prepare($sql_conversa);
        $stmt_conv->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt_conv->execute();
        $conversa_info = $stmt_conv->fetch(PDO::FETCH_ASSOC);
        
        if ($conversa_info) {
            $outro_usuario_id = $conversa_info['comprador_id'];
            $outro_usuario_nome = $conversa_info['comprador_nome'];
        } else {
            $conversa_id = null;
            $outro_usuario_id = null;
            $outro_usuario_nome = null;
        }
    } else {
        $conversa_id = null;
        $outro_usuario_id = null;
        $outro_usuario_nome = null;
    }
    
    $sql_conversas = "SELECT DISTINCT c.*, 
                      u.nome AS comprador_nome,
                      (SELECT COUNT(*) FROM chat_mensagens 
                       WHERE conversa_id = c.id AND lida = 0 AND remetente_id != :usuario_id) as nao_lidas
                      FROM chat_conversas c
                      JOIN usuarios u ON c.comprador_id = u.id
                      WHERE c.produto_id = :produto_id 
                      AND c.vendedor_id = :usuario_id
                      ORDER BY c.ultima_mensagem_data DESC";
    
    $stmt_conversas = $conn->prepare($sql_conversas);
    $stmt_conversas->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt_conversas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_conversas->execute();
    $conversas = $stmt_conversas->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    // COMPRADOR
    $conversa_id = obterOuCriarConversa($conn, $produto_id, $usuario_id, $vendedor_usuario_id);
    $outro_usuario_id = $vendedor_usuario_id;
    $outro_usuario_nome = $produto['nome_vendedor'] ?: $produto['vendedor_nome'];
    $conversas = null;
}

// Determinar URL de volta
if ($eh_vendedor_produto) {
    $url_voltar = "../../src/vendedor/chats.php";
} else {
    if ($referrer === 'meus_chats') {
        $url_voltar = "../comprador/meus_chats.php";
    } else {
        $url_voltar = "../comprador/proposta_nova.php?anuncio_id=" . $produto_id;
    }
}

// array com opções de pagamento após a definição das variáveis
$opcoes_pagamento = [
    'pagamento_ato' => 'Pagamento no Ato',
    'pagamento_entrega' => 'Pagamento na Entrega'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo htmlspecialchars($produto['nome']); ?></title>
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="css/chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @media (max-width: 768px) {
            .chat-sidebar {
                width: 100%;
                display: <?php echo $conversa_id ? 'none' : 'flex'; ?>;
            }
            
            .chat-area {
                display: <?php echo $conversa_id ? 'flex' : 'none'; ?>;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <h2>
                    <i class="fas fa-comments"></i>
                    Chat
                </h2>
                <small><?php echo htmlspecialchars($produto['nome']); ?></small>
            </div>
            
            <div class="produto-info-sidebar">
                <img src="<?php echo htmlspecialchars($produto['imagem_url'] ?: '../../img/placeholder.png'); ?>" alt="Produto">
                <div class="info">
                    <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                    <div class="preco">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                </div>
            </div>
            
            <?php if ($eh_vendedor_produto && $conversas): ?>
                <div class="conversas-lista">
                    <?php if (count($conversas) > 0): ?>
                        <?php foreach ($conversas as $conv): ?>
                            <div class="conversa-item <?php echo ($conversa_id && $conv['id'] == $conversa_id) ? 'ativa' : ''; ?>" 
                                 onclick="location.href='chat.php?produto_id=<?php echo $produto_id; ?>&conversa_id=<?php echo $conv['id']; ?>'">
                                <div style="flex: 1;">
                                    <div class="nome"><?php echo htmlspecialchars($conv['comprador_nome']); ?></div>
                                    <?php if ($conv['ultima_mensagem']): ?>
                                        <div class="ultima-msg"><?php echo htmlspecialchars($conv['ultima_mensagem']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($conv['nao_lidas'] > 0): ?>
                                    <div class="badge-nao-lidas"><?php echo $conv['nao_lidas']; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 40px 20px; text-align: center; color: #65676b;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 12px; opacity: 0.3;"></i>
                            <p style="font-size: 14px;">Nenhuma conversa ainda</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif (!$eh_vendedor_produto): ?>
                <div class="conversas-lista">
                    <div class="conversa-item ativa">
                        <div>
                            <div class="nome">
                                <i class="fas fa-store" style="margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($outro_usuario_nome); ?>
                            </div>
                            <div class="ultima-msg">Conversa com o vendedor</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="chat-area">
            <?php if ($conversa_id && $outro_usuario_id): ?>
                <div class="chat-header">
                    <div class="usuario-info">
                        <div class="avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($outro_usuario_nome); ?></h3>
                            <small><?php echo $eh_vendedor_produto ? 'Comprador' : 'Vendedor'; ?></small>
                        </div>
                    </div>
                    <a href="<?php echo $url_voltar; ?>" class="btn-voltar">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                </div>
                
                <div class="chat-messages" id="chat-messages"></div>
                
                <div class="chat-input">
                    <div class="chat-input-buttons">
                        <button type="button" class="btn-attach" id="btn-attach-image" title="Enviar Imagem">
                            <i class="fas fa-camera"></i>
                        </button>
                        
                        <!-- BOTÃO NOVO: ABRIR NEGOCIAÇÃO -->
                        <button type="button" class="btn-negociar-chat" id="btn-negociar" title="Acordo de Compra">
                            <i class="fas fa-handshake"></i>
                        </button>
                    </div>
                    
                    <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">

                    <input type="text" id="message-input" placeholder="Digite sua mensagem..." autocomplete="off">
                    <button type="button" id="send-btn" class="btn-send">
                        <i class="fas fa-paper-plane"></i>
                        <span>Enviar</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="chat-placeholder">
                    <i class="fas fa-comments"></i>
                    <p><?php echo $eh_vendedor_produto ? 'Selecione uma conversa para começar' : 'Carregando chat...'; ?></p>
                    <?php if ($eh_vendedor_produto): ?>
                        <a href="<?php echo $url_voltar; ?>" class="btn-voltar" style="margin-top: 20px;">
                            <i class="fas fa-arrow-left"></i>
                            Voltar para Chats
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
<!-- MODAL DE NEGOCIAÇÃO -->
    <div class="modal-negociacao" id="modal-negociacao">
        <div class="modal-negociacao-content">
            <div class="modal-header">
                <h3><i class="fas fa-handshake" style="margin-right: 8px;"></i> Acordo de Compra</h3>
                <button class="btn-fechar-modal" id="fechar-modal">&times;</button>
            </div>
            
            <div class="modal-body">
                <!-- Dados do Produto -->
                <div class="produto-info-modal">
                    <img src="<?php echo htmlspecialchars($produto['imagem_url'] ?: '../../img/placeholder.png'); ?>" 
                         alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                    <div style="flex: 1;">
                        <h4><?php echo htmlspecialchars($produto['nome']); ?></h4>
                        <div class="preco" id="preco-produto-modal">
                            R$ <?php 
                            $preco_exibir = $produto['desconto_ativo'] && $produto['preco_desconto'] 
                                ? $produto['preco_desconto'] 
                                : $produto['preco'];
                            echo number_format($preco_exibir, 2, ',', '.'); 
                            ?>
                            <?php if ($produto['desconto_ativo'] && $produto['preco_desconto']): ?>
                                <small style="color: #999; text-decoration: line-through; margin-left: 8px;">
                                    R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                                </small>
                                <small style="color: #42b72a; margin-left: 8px;">
                                    -<?php echo number_format($produto['desconto_percentual'], 0); ?>%
                                </small>
                            <?php endif; ?>
                        </div>
                        <small>Estoque: <?php echo $produto['estoque']; ?> unidades</small>
                    </div>
                </div>
                
                <!-- Etapas -->
                <div class="etapas-negociacao">
                    <div class="etapa active" data-etapa="1">1. Dados da Negociação</div>
                    <div class="etapa" data-etapa="2">2. Logística e Frete</div>
                </div>
                
                <!-- Conteúdo das Etapas -->
                <form id="form-negociacao">
                    <!-- ETAPA 1 -->
                    <div class="etapa-conteudo active" data-etapa="1">
                        <div class="form-group">
                            <label for="quantidade">Quantidade *</label>
                            <input type="number" 
                                   id="quantidade" 
                                   name="quantidade" 
                                   min="1" 
                                   max="<?php echo $produto['estoque']; ?>"
                                   value="1"
                                   required>
                            <small>Máximo: <?php echo $produto['estoque']; ?> unidades disponíveis</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_unitario">Valor Unitário (R$) *</label>
                            <input type="number" 
                                   id="valor_unitario" 
                                   name="valor_unitario" 
                                   step="0.01"
                                   min="0.01"
                                   value="<?php echo number_format($preco_exibir, 2, '.', ''); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label>Forma de Pagamento *</label>
                            <div class="radio-group">
                                <?php foreach ($opcoes_pagamento as $valor => $label): ?>
                                    <label class="radio-option">
                                        <input type="radio" 
                                               name="forma_pagamento" 
                                               value="<?php echo $valor; ?>"
                                               <?php echo $valor == 'pagamento_ato' ? 'checked' : ''; ?>
                                               required>
                                        <span><?php echo $label; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="alert alert-info" id="info-etapa1">
                            Preencha os dados da negociação para prosseguir.
                        </div>
                    </div>
                    
                    <!-- ETAPA 2 -->
                    <div class="etapa-conteudo" data-etapa="2">
                        <div class="form-group">
                            <label>Opção de Frete *</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="opcao_frete" value="frete_vendedor" required>
                                    <span>Frete por Conta do Vendedor</span>
                                </label>
                                
                                <label class="radio-option">
                                    <input type="radio" name="opcao_frete" value="retirada_comprador" required>
                                    <span>Frete por Conta do Comprador (Retirada)</span>
                                </label>
                                
                                <label class="radio-option">
                                    <input type="radio" name="opcao_frete" value="buscar_transportador" required>
                                    <span>A plataforma busca entregador</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Conteúdo dinâmico baseado na opção de frete -->
                        <div id="conteudo-frete-vendedor" style="display: none;">
                            <div class="form-group">
                                <label for="valor_frete">Valor do Frete (R$)</label>
                                <input type="number" 
                                       id="valor_frete" 
                                       name="valor_frete" 
                                       step="0.01"
                                       min="0"
                                       value="0.00">
                            </div>
                        </div>
                        
                        <div id="conteudo-retirada" style="display: none;">
                            <?php 
                            // Buscar endereço do vendedor
                            $sql_endereco_vendedor = "SELECT 
                                v.rua, v.numero, v.complemento, v.cidade, v.estado,
                                v.cep, v.nome_comercial
                                FROM vendedores v
                                WHERE v.usuario_id = :vendedor_id";
                            
                            $stmt_end = $conn->prepare($sql_endereco_vendedor);
                            $stmt_end->bindParam(':vendedor_id', $vendedor_usuario_id, PDO::PARAM_INT);
                            $stmt_end->execute();
                            $endereco_vendedor = $stmt_end->fetch(PDO::FETCH_ASSOC);
                            
                            if ($endereco_vendedor):
                                $endereco_completo = "{$endereco_vendedor['rua']}, {$endereco_vendedor['numero']}";
                                if (!empty($endereco_vendedor['complemento'])) {
                                    $endereco_completo .= " - {$endereco_vendedor['complemento']}";
                                }
                                $endereco_completo .= ", {$endereco_vendedor['cidade']} - {$endereco_vendedor['estado']}";
                                
                                $endereco_maps = urlencode($endereco_completo);
                            ?>
                            <div class="form-group">
                                <label>Endereço para Retirada</label>
                                <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                                    <strong><?php echo htmlspecialchars($endereco_vendedor['nome_comercial']); ?></strong><br>
                                    <?php echo htmlspecialchars($endereco_completo); ?><br>
                                    CEP: <?php echo htmlspecialchars($endereco_vendedor['cep']); ?>
                                </div>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $endereco_maps; ?>" 
                                   target="_blank" 
                                   style="color: #1877f2; text-decoration: none; font-weight: 500;">
                                    <i class="fas fa-map-marker-alt"></i> Ver no Google Maps
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div id="conteudo-buscar-transportador" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                A plataforma irá buscar um transportador disponível para a entrega.
                                Você receberá cotações de transportadores cadastrados.
                            </div>
                            
                            <div id="aviso-pagamento-transportador" style="display: none;" class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Atenção:</strong> Para contratar transportador pela plataforma, 
                                o pagamento deve ser feito no ato. Transportadores não aceitam pagamento na entrega.
                            </div>
                        </div>
                        
                        <!-- Resumo da Negociação -->
                        <div class="resumo-negociacao">
                            <h5><i class="fas fa-receipt"></i> Resumo da Negociação</h5>
                            <div class="resumo-item">
                                <span>Quantidade:</span>
                                <span id="resumo-quantidade">1</span>
                            </div>
                            <div class="resumo-item">
                                <span>Valor Unitário:</span>
                                <span id="resumo-valor-unitario">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                            </div>
                            <div class="resumo-item">
                                <span>Subtotal:</span>
                                <span id="resumo-subtotal">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                            </div>
                            <div class="resumo-item">
                                <span>Frete:</span>
                                <span id="resumo-frete">R$ 0,00</span>
                            </div>
                            <div class="resumo-item">
                                <span>Forma de Pagamento:</span>
                                <span id="resumo-pagamento">Pagamento no Ato</span>
                            </div>
                            <div class="resumo-total">
                                <span>Total:</span>
                                <span id="resumo-total">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Avisos dinâmicos -->
                        <div id="aviso-pagamento-entrega" style="display: none;" class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>O Vendedor deverá cobrar o valor total de R$ <span id="valor-total-aviso">0,00</span> no ato da entrega.</strong>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-voltar-etapa" id="btn-voltar-etapa" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>
                
                <div style="flex: 1;"></div>
                
                <button type="button" class="btn-proximo-etapa" id="btn-proximo-etapa">
                    Próxima Etapa <i class="fas fa-arrow-right"></i>
                </button>
                
                <button type="button" class="btn-finalizar-negociacao" id="btn-finalizar-negociacao" style="display: none;">
                    <i class="fas fa-check"></i> Finalizar Negociação
                </button>
            </div>
        </div>
    </div>

    <?php if ($conversa_id && $outro_usuario_id): ?>
    <script>
        const conversaId = <?php echo $conversa_id; ?>;
        const usuarioId = <?php echo $usuario_id; ?>;
        let ultimaMensagemId = 0;
        let carregandoMensagens = false;

        // Configuração do Lazy Loading usando Intersection Observer
        // Usa uma imagem de placeholder leve enquanto a real não carrega
        const placeholderImage = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 200'%3E%3Crect width='300' height='200' fill='%23f0f2f5'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='14' fill='%23a8abaf'%3ECarregando...%3C/text%3E%3C/svg%3E";

        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const src = img.getAttribute('data-src');
                    if (src) {
                        img.src = src;
                        img.onload = () => {
                            img.classList.remove('lazy-loading');
                            img.classList.add('lazy-loaded');
                        };
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        }, {
            root: document.getElementById('chat-messages'), // Observa em relação ao container do chat
            rootMargin: '200px 0px', // Começa a carregar 200px antes de aparecer
            threshold: 0.01
        });

        function carregarMensagens() {
            if (carregandoMensagens) return;
            carregandoMensagens = true;
            
            // IMPORTANTE: Seu get_messages.php deve retornar a coluna 'tipo' agora
            fetch(`get_messages.php?conversa_id=${conversaId}&ultimo_id=${ultimaMensagemId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.mensagens.length > 0) {
                        const messagesDiv = document.getElementById('chat-messages');
                        // Verifica se o usuário estava perto do fim antes de adicionar novas mensagens
                        const estavaNaBase = messagesDiv.scrollHeight - messagesDiv.scrollTop <= messagesDiv.clientHeight + 150;
                        let novasImagens = [];

                        data.mensagens.forEach(msg => {
                            if (msg.id > ultimaMensagemId) {
                                const div = document.createElement('div');
                                div.className = 'message ' + (msg.remetente_id == usuarioId ? 'sent' : 'received');
                                
                                let conteudoMensagem = '';
                                // Verifica o tipo da mensagem
                                if (msg.tipo === 'imagem') {
                                    // Estrutura para Lazy Load
                                    conteudoMensagem = `
                                        <div class="chat-image-container">
                                            <img src="${placeholderImage}" 
                                                 data-src="${msg.mensagem}" 
                                                 class="chat-image lazy-loading" 
                                                 alt="Imagem enviada"
                                                 loading="lazy">
                                        </div>`;
                                } else {
                                    conteudoMensagem = `<div>${escapeHtml(msg.mensagem)}</div>`;
                                }

                                div.innerHTML = `
                                    ${conteudoMensagem}
                                    <div class="time">${msg.data_formatada}</div>
                                `;
                                messagesDiv.appendChild(div);
                                ultimaMensagemId = msg.id;

                                // Se for imagem, adiciona ao array para observar depois
                                if (msg.tipo === 'imagem') {
                                    novasImagens.push(div.querySelector('.chat-image'));
                                }
                            }
                        });
                        
                        // Inicia observação das novas imagens para lazy load
                        novasImagens.forEach(img => imageObserver.observe(img));

                        if (estavaNaBase) {
                             // Pequeno delay para garantir que o DOM atualizou antes de rolar
                             setTimeout(() => {
                                messagesDiv.scrollTop = messagesDiv.scrollHeight;
                             }, 50);
                        }
                    }
                    carregandoMensagens = false;
                })
                .catch(err => {
                    console.error('Erro ao carregar mensagens:', err);
                    carregandoMensagens = false;
                });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        // --- Lógica de Envio de Texto ---
        function enviarMensagem() {
            const input = document.getElementById('message-input');
            const mensagem = input.value.trim();
            
            if (!mensagem) return;
            
            fetch('send_message.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `conversa_id=${conversaId}&mensagem=${encodeURIComponent(mensagem)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    carregarMensagens();
                }
            })
            .catch(err => console.error('Erro ao enviar mensagem:', err));
        }
        
        // --- Lógica de Envio de Imagem ---
        const btnAttach = document.getElementById('btn-attach-image');
        const fileInput = document.getElementById('image-input');
        const attachIcon = btnAttach.querySelector('i');

        btnAttach.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function() {
            if (this.files.length === 0) return;
            const file = this.files[0];
            uploadImagem(file);
        });

        function uploadImagem(file) {
            // Feedback visual de carregamento no botão
            btnAttach.classList.add('loading');
            attachIcon.className = 'fas fa-spinner';
            btnAttach.disabled = true;

            const formData = new FormData();
            formData.append('imagem', file);
            formData.append('conversa_id', conversaId);

            fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Limpa o input file e recarrega mensagens
                    fileInput.value = '';
                    carregarMensagens();
                } else {
                    alert('Erro ao enviar imagem: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(err => {
                console.error('Erro no upload:', err);
                alert('Erro de conexão ao enviar imagem.');
            })
            .finally(() => {
                // Remove feedback visual
                btnAttach.classList.remove('loading');
                attachIcon.className = 'fas fa-camera';
                btnAttach.disabled = false;
            });
        }

        // Event Listeners existentes
        document.getElementById('send-btn').addEventListener('click', enviarMensagem);
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                enviarMensagem();
            }
        });
        
        // Carregar mensagens iniciais
        carregarMensagens();
        
        // Atualizar a cada 2 segundos (aumentei um pouco para não sobrecarregar com imagens)
        setInterval(carregarMensagens, 2000);

         // ==================== LÓGICA DO MODAL DE NEGOCIAÇÃO ====================
        
        // Elementos do modal
        const modalNegociacao = document.getElementById('modal-negociacao');
        const btnAbrirNegociacao = document.getElementById('btn-negociar');
        const btnFecharModal = document.getElementById('fechar-modal');
        const btnVoltarEtapa = document.getElementById('btn-voltar-etapa');
        const btnProximoEtapa = document.getElementById('btn-proximo-etapa');
        const btnFinalizarNegociacao = document.getElementById('btn-finalizar-negociacao');
        
        // Elementos das etapas
        const etapas = document.querySelectorAll('.etapa');
        const conteudosEtapas = document.querySelectorAll('.etapa-conteudo');
        let etapaAtual = 1;
        
        // Elementos do formulário
        const quantidadeInput = document.getElementById('quantidade');
        const valorUnitarioInput = document.getElementById('valor_unitario');
        const formaPagamentoInputs = document.querySelectorAll('input[name="forma_pagamento"]');
        const opcaoFreteInputs = document.querySelectorAll('input[name="opcao_frete"]');
        const valorFreteInput = document.getElementById('valor_frete');
        
        // Elementos de conteúdo dinâmico
        const conteudoFreteVendedor = document.getElementById('conteudo-frete-vendedor');
        const conteudoRetirada = document.getElementById('conteudo-retirada');
        const conteudoBuscarTransportador = document.getElementById('conteudo-buscar-transportador');
        const avisoPagamentoTransportador = document.getElementById('aviso-pagamento-transportador');
        const avisoPagamentoEntrega = document.getElementById('aviso-pagamento-entrega');
        const valorTotalAviso = document.getElementById('valor-total-aviso');
        
        // Elementos do resumo
        const resumoQuantidade = document.getElementById('resumo-quantidade');
        const resumoValorUnitario = document.getElementById('resumo-valor-unitario');
        const resumoSubtotal = document.getElementById('resumo-subtotal');
        const resumoFrete = document.getElementById('resumo-frete');
        const resumoPagamento = document.getElementById('resumo-pagamento');
        const resumoTotal = document.getElementById('resumo-total');
        
        // Abrir modal
        btnAbrirNegociacao.addEventListener('click', () => {
            modalNegociacao.classList.add('active');
            atualizarResumo();
        });
        
        // Fechar modal
        btnFecharModal.addEventListener('click', () => {
            modalNegociacao.classList.remove('active');
            resetarFormulario();
        });
        
        // Fechar modal ao clicar fora
        modalNegociacao.addEventListener('click', (e) => {
            if (e.target === modalNegociacao) {
                modalNegociacao.classList.remove('active');
                resetarFormulario();
            }
        });
        
        // Navegação entre etapas
        etapas.forEach(etapa => {
            etapa.addEventListener('click', () => {
                if (!etapa.classList.contains('active')) {
                    const novaEtapa = parseInt(etapa.dataset.etapa);
                    if (validarEtapaAtual() && novaEtapa >= etapaAtual) {
                        irParaEtapa(novaEtapa);
                    }
                }
            });
        });
        
        btnProximoEtapa.addEventListener('click', () => {
            if (validarEtapaAtual()) {
                if (etapaAtual < 2) {
                    irParaEtapa(etapaAtual + 1);
                }
            }
        });
        
        btnVoltarEtapa.addEventListener('click', () => {
            if (etapaAtual > 1) {
                irParaEtapa(etapaAtual - 1);
            }
        });
        
        function irParaEtapa(numeroEtapa) {
            // Atualizar visual das etapas
            etapas.forEach(etapa => {
                if (parseInt(etapa.dataset.etapa) === numeroEtapa) {
                    etapa.classList.add('active');
                } else {
                    etapa.classList.remove('active');
                }
            });
            
            // Mostrar conteúdo da etapa
            conteudosEtapas.forEach(conteudo => {
                if (parseInt(conteudo.dataset.etapa) === numeroEtapa) {
                    conteudo.classList.add('active');
                } else {
                    conteudo.classList.remove('active');
                }
            });
            
            // Atualizar botões
            etapaAtual = numeroEtapa;
            
            if (numeroEtapa === 1) {
                btnVoltarEtapa.style.display = 'none';
                btnProximoEtapa.style.display = 'block';
                btnFinalizarNegociacao.style.display = 'none';
            } else if (numeroEtapa === 2) {
                btnVoltarEtapa.style.display = 'block';
                btnProximoEtapa.style.display = 'none';
                btnFinalizarNegociacao.style.display = 'block';
            }
        }
        
        function validarEtapaAtual() {
            if (etapaAtual === 1) {
                // Validar etapa 1
                if (!quantidadeInput.value || parseInt(quantidadeInput.value) < 1) {
                    alert('Por favor, insira uma quantidade válida.');
                    quantidadeInput.focus();
                    return false;
                }
                
                if (parseInt(quantidadeInput.value) > <?php echo $produto['estoque']; ?>) {
                    alert('Quantidade excede o estoque disponível.');
                    quantidadeInput.focus();
                    return false;
                }
                
                if (!valorUnitarioInput.value || parseFloat(valorUnitarioInput.value) <= 0) {
                    alert('Por favor, insira um valor unitário válido.');
                    valorUnitarioInput.focus();
                    return false;
                }
                
                let formaPagamentoSelecionada = false;
                formaPagamentoInputs.forEach(input => {
                    if (input.checked) formaPagamentoSelecionada = true;
                });
                
                if (!formaPagamentoSelecionada) {
                    alert('Por favor, selecione uma forma de pagamento.');
                    return false;
                }
            }
            
            return true;
        }
        
        // Atualizar conteúdo dinâmico baseado na opção de frete
        opcaoFreteInputs.forEach(input => {
            input.addEventListener('change', () => {
                atualizarConteudoFrete();
                atualizarResumo();
            });
        });
        
        function atualizarConteudoFrete() {
            // Esconder tudo primeiro
            conteudoFreteVendedor.style.display = 'none';
            conteudoRetirada.style.display = 'none';
            conteudoBuscarTransportador.style.display = 'none';
            avisoPagamentoTransportador.style.display = 'none';
            
            // Mostrar conteúdo baseado na opção selecionada
            const opcaoSelecionada = document.querySelector('input[name="opcao_frete"]:checked');
            
            if (opcaoSelecionada) {
                switch (opcaoSelecionada.value) {
                    case 'frete_vendedor':
                        conteudoFreteVendedor.style.display = 'block';
                        break;
                    case 'retirada_comprador':
                        conteudoRetirada.style.display = 'block';
                        break;
                    case 'buscar_transportador':
                        conteudoBuscarTransportador.style.display = 'block';
                        
                        // Verificar se pagamento é na entrega
                        const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
                        if (formaPagamentoSelecionada && formaPagamentoSelecionada.value === 'pagamento_entrega') {
                            avisoPagamentoTransportador.style.display = 'block';
                        }
                        break;
                }
            }
        }
        
        // Atualizar aviso de pagamento na entrega
        formaPagamentoInputs.forEach(input => {
            input.addEventListener('change', () => {
                atualizarAvisosPagamento();
                atualizarResumo();
            });
        });
        
        function atualizarAvisosPagamento() {
            const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
            const opcaoFreteSelecionada = document.querySelector('input[name="opcao_frete"]:checked');
            
            if (formaPagamentoSelecionada && formaPagamentoSelecionada.value === 'pagamento_entrega') {
                // Mostrar aviso para frete do vendedor
                if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'frete_vendedor') {
                    avisoPagamentoEntrega.style.display = 'block';
                    atualizarValorAviso();
                } else {
                    avisoPagamentoEntrega.style.display = 'none';
                }
                
                // Mostrar aviso para buscar transportador
                if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'buscar_transportador') {
                    avisoPagamentoTransportador.style.display = 'block';
                }
            } else {
                avisoPagamentoEntrega.style.display = 'none';
                if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'buscar_transportador') {
                    avisoPagamentoTransportador.style.display = 'none';
                }
            }
        }
        
        function atualizarValorAviso() {
            const quantidade = parseFloat(quantidadeInput.value) || 1;
            const valorUnitario = parseFloat(valorUnitarioInput.value) || <?php echo $preco_exibir; ?>;
            const valorFrete = parseFloat(valorFreteInput.value) || 0;
            
            const total = (quantidade * valorUnitario) + valorFrete;
            valorTotalAviso.textContent = total.toFixed(2).replace('.', ',');
        }
        
        // Atualizar resumo quando os valores mudam
        quantidadeInput.addEventListener('input', atualizarResumo);
        valorUnitarioInput.addEventListener('input', atualizarResumo);
        valorFreteInput.addEventListener('input', atualizarResumo);
        
        function atualizarResumo() {
            const quantidade = parseFloat(quantidadeInput.value) || 1;
            const valorUnitario = parseFloat(valorUnitarioInput.value) || <?php echo $preco_exibir; ?>;
            const valorFrete = parseFloat(valorFreteInput.value) || 0;
            
            const subtotal = quantidade * valorUnitario;
            const total = subtotal + valorFrete;
            
            // Atualizar elementos do resumo
            resumoQuantidade.textContent = quantidade.toLocaleString('pt-BR');
            resumoValorUnitario.textContent = 'R$ ' + valorUnitario.toFixed(2).replace('.', ',');
            resumoSubtotal.textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
            resumoFrete.textContent = 'R$ ' + valorFrete.toFixed(2).replace('.', ',');
            resumoTotal.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
            
            // Atualizar forma de pagamento no resumo
            const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
            if (formaPagamentoSelecionada) {
                if (formaPagamentoSelecionada.value === 'pagamento_ato') {
                    resumoPagamento.textContent = 'Pagamento no Ato';
                } else {
                    resumoPagamento.textContent = 'Pagamento na Entrega';
                }
            }
            
            // Atualizar avisos
            atualizarAvisosPagamento();
        }
        
        // Finalizar negociação
        btnFinalizarNegociacao.addEventListener('click', () => {
            if (!validarEtapa2()) {
                return;
            }
            
            // Coletar dados da negociação
            const dadosNegociacao = {
                produto_id: <?php echo $produto_id; ?>,
                conversa_id: conversaId,
                quantidade: quantidadeInput.value,
                valor_unitario: valorUnitarioInput.value,
                forma_pagamento: document.querySelector('input[name="forma_pagamento"]:checked').value,
                opcao_frete: document.querySelector('input[name="opcao_frete"]:checked').value,
                valor_frete: valorFreteInput.value || '0',
                total: calcularTotal()
            };
            
            // Enviar para o servidor
            enviarNegociacao(dadosNegociacao);
        });
        
        function validarEtapa2() {
            let opcaoFreteSelecionada = false;
            opcaoFreteInputs.forEach(input => {
                if (input.checked) opcaoFreteSelecionada = true;
            });
            
            if (!opcaoFreteSelecionada) {
                alert('Por favor, selecione uma opção de frete.');
                return false;
            }
            
            const opcaoSelecionada = document.querySelector('input[name="opcao_frete"]:checked').value;
            const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked').value;
            
            // Validação específica para buscar transportador
            if (opcaoSelecionada === 'buscar_transportador' && formaPagamentoSelecionada === 'pagamento_entrega') {
                alert('Para contratar transportador pela plataforma, o pagamento deve ser feito no ato. Por favor, altere a forma de pagamento.');
                return false;
            }
            
            return true;
        }
        
        function calcularTotal() {
            const quantidade = parseFloat(quantidadeInput.value) || 1;
            const valorUnitario = parseFloat(valorUnitarioInput.value) || <?php echo $preco_exibir; ?>;
            const valorFrete = parseFloat(valorFreteInput.value) || 0;
            
            return (quantidade * valorUnitario) + valorFrete;
        }
        
        // No arquivo chat.php, dentro do script, atualize a função enviarNegociacao:
        function enviarNegociacao(dados) {
            // Mostrar loading
            btnFinalizarNegociacao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            btnFinalizarNegociacao.disabled = true;
            
            // Adicionar dados adicionais necessários
            dados.total = calcularTotal();
            dados.valor_frete = valorFreteInput.value || '0';
            
            // Enviar para o servidor
            fetch('salvar_negociacao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dados)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Enviar mensagem automática no chat com os detalhes da negociação
                    const mensagemNegociacao = `*NOVA PROPOSTA DE COMPRA*\n\n` +
                        `Produto: ${document.querySelector('.produto-info-modal h4').textContent}\n` +
                        `Quantidade: ${dados.quantidade} unidades\n` +
                        `Valor unitário: R$ ${parseFloat(dados.valor_unitario).toFixed(2).replace('.', ',')}\n` +
                        `Forma de pagamento: ${dados.forma_pagamento === 'pagamento_ato' ? 'Pagamento no Ato' : 'Pagamento na Entrega'}\n` +
                        `Frete: ${obterDescricaoFrete(dados.opcao_frete)}\n` +
                        `Valor do frete: R$ ${parseFloat(dados.valor_frete).toFixed(2).replace('.', ',')}\n` +
                        `Total: R$ ${parseFloat(dados.total).toFixed(2).replace('.', ',')}\n\n` +
                        `ID da proposta: ${data.proposta_id}`;
                    
                    // Enviar como mensagem no chat
                    return fetch('send_message.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `conversa_id=${conversaId}&mensagem=${encodeURIComponent(mensagemNegociacao)}`
                    }).then(() => {
                        alert('✅ Negociação enviada com sucesso!');
                        modalNegociacao.classList.remove('active');
                        resetarFormulario();
                        
                        // Recarregar mensagens para mostrar a nova proposta
                        setTimeout(() => carregarMensagens(), 1000);
                    });
                    
                } else {
                    alert('❌ Erro ao salvar negociação: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(err => {
                console.error('Erro:', err);
                alert('❌ Erro de conexão ao salvar negociação.');
            })
            .finally(() => {
                btnFinalizarNegociacao.innerHTML = '<i class="fas fa-check"></i> Finalizar Negociação';
                btnFinalizarNegociacao.disabled = false;
            });
        }
        
        function obterDescricaoFrete(opcao) {
            switch(opcao) {
                case 'frete_vendedor': return 'Frete por conta do vendedor';
                case 'retirada_comprador': return 'Retirada pelo comprador';
                case 'buscar_transportador': return 'Buscar transportador na plataforma';
                default: return '';
            }
        }
        
        function resetarFormulario() {
            // Resetar para etapa 1
            etapaAtual = 1;
            irParaEtapa(1);
            
            // Resetar valores
            quantidadeInput.value = 1;
            valorUnitarioInput.value = <?php echo $preco_exibir; ?>;
            
            // Resetar radio buttons
            document.querySelector('input[name="forma_pagamento"][value="pagamento_ato"]').checked = true;
            opcaoFreteInputs[0].checked = false;
            opcaoFreteInputs[1].checked = false;
            opcaoFreteInputs[2].checked = false;
            valorFreteInput.value = '0.00';
            
            // Esconder conteúdos dinâmicos
            conteudoFreteVendedor.style.display = 'none';
            conteudoRetirada.style.display = 'none';
            conteudoBuscarTransportador.style.display = 'none';
            avisoPagamentoEntrega.style.display = 'none';
            avisoPagamentoTransportador.style.display = 'none';
            
            // Atualizar resumo
            atualizarResumo();
        }
        
        // Inicializar
        atualizarConteudoFrete();
        atualizarAvisosPagamento();
    </script>
    <?php endif; ?>
</body>
</html>