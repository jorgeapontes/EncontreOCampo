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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo htmlspecialchars($produto['nome']); ?></title>
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        /* Sidebar */
        .chat-sidebar {
            width: 360px;
            background: #ffffff;
            border-right: 1px solid #e4e6eb;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 20px;
            background: #2E7D32;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .sidebar-header small {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .produto-info-sidebar {
            padding: 16px;
            background: #f8f9fa;
            border-bottom: 1px solid #e4e6eb;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .produto-info-sidebar img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e4e6eb;
        }
        
        .produto-info-sidebar .info {
            flex: 1;
        }
        
        .produto-info-sidebar .info h3 {
            font-size: 14px;
            margin-bottom: 4px;
            color: #1c1e21;
            font-weight: 600;
        }
        
        .produto-info-sidebar .info .preco {
            color: #2E7D32;
            font-weight: 700;
            font-size: 16px;
        }
        
        .conversas-lista {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversas-lista::-webkit-scrollbar {
            width: 8px;
        }
        
        .conversas-lista::-webkit-scrollbar-track {
            background: #f0f2f5;
        }
        
        .conversas-lista::-webkit-scrollbar-thumb {
            background: #c5c7ca;
            border-radius: 10px;
        }
        
        .conversas-lista::-webkit-scrollbar-thumb:hover {
            background: #a8abaf;
        }
        
        .conversa-item {
            padding: 14px 16px;
            border-bottom: 1px solid #e4e6eb;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .conversa-item:hover {
            background: #f0f2f5;
        }
        
        .conversa-item.ativa {
            background: #e7f3e8;
            border-left: 4px solid #2E7D32;
        }
        
        .conversa-item .nome {
            font-weight: 600;
            margin-bottom: 4px;
            color: #1c1e21;
            font-size: 15px;
        }
        
        .conversa-item .ultima-msg {
            font-size: 13px;
            color: #65676b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }
        
        .conversa-item .badge-nao-lidas {
            background: #2E7D32;
            color: white;
            border-radius: 12px;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 700;
        }
        
        /* Área do Chat */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #ffffff;
        }
        
        .chat-header {
            padding: 16px 24px;
            background: #ffffff;
            border-bottom: 1px solid #e4e6eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header .usuario-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .chat-header .avatar {
            width: 44px;
            height: 44px;
            background: #2E7D32;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .chat-header h3 {
            font-size: 17px;
            font-weight: 600;
            color: #1c1e21;
            margin-bottom: 2px;
        }
        
        .chat-header small {
            font-size: 13px;
            color: #65676b;
            font-weight: 400;
        }
        
        .btn-voltar {
            background: #f0f2f5;
            color: #1c1e21;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background 0.2s;
            font-weight: 500;
            font-size: 14px;
        }
        
        .btn-voltar:hover {
            background: #e4e6eb;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: #c5c7ca;
            border-radius: 10px;
        }
        
        .message {
            max-width: 70%;
            padding: 10px 14px;
            border-radius: 18px;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease;
            font-size: 15px;
            line-height: 1.4;
            position: relative;
        }

        /* Estilos para imagem no chat */
        .message .chat-image-container {
            margin: -5px -9px; /* Compensa o padding da mensagem para a imagem ficar maior */
        }

        .message .chat-image {
            max-width: 100%;
            border-radius: 12px;
            display: block;
            min-height: 100px; /* Altura mínima enquanto carrega */
            background: #f0f2f5;
            transition: opacity 0.3s ease-in;
        }

        /* Classe para lazy load */
        .chat-image.lazy-loading {
            opacity: 0.5;
            filter: blur(2px);
        }

        .chat-image.lazy-loaded {
            opacity: 1;
            filter: blur(0);
        }
        
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(10px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }
        
        .message.sent {
            align-self: flex-end;
            background: #2E7D32;
            color: white;
        }
        
        .message.received {
            align-self: flex-start;
            background: #ffffff;
            color: #1c1e21;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message .time {
            font-size: 11px;
            margin-top: 4px;
            opacity: 0.7;
        }
        
        .message.sent .time {
            text-align: right;
        }

        /* Debug de imagens - remover depois */
.message img {
    border: 2px solid red;
}

.message img[src*="data:image"] {
    border: 2px solid blue;
}

.message img[data-src] {
    border: 2px solid green;
}
        
        .chat-input {
            padding: 16px 20px;
            background: #ffffff;
            border-top: 1px solid #e4e6eb;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .chat-input input[type="text"] {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ccd0d5;
            border-radius: 24px;
            font-size: 15px;
            outline: none;
            transition: border 0.2s;
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
        }
        
        .chat-input input[type="text"]:focus {
            border-color: #2E7D32;
            background: #ffffff;
        }

        /* Botão de Anexo */
        .btn-attach {
            background: #f0f2f5;
            color: #65676b;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .btn-attach:hover {
            background: #e4e6eb;
            color: #2E7D32;
        }

        .btn-attach.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
             100% { transform: rotate(360deg); }
        }
        
        .chat-input .btn-send {
            background: #2E7D32;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        
        .chat-input .btn-send:hover {
            background: #1B5E20;
        }
        
        .chat-input .btn-send:active {
            transform: scale(0.98);
        }
        
        .chat-placeholder {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #65676b;
            gap: 16px;
            background: #f0f2f5;
        }
        
        .chat-placeholder i {
            font-size: 64px;
            opacity: 0.3;
            color: #8a8d91;
        }
        
        .chat-placeholder p {
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .chat-sidebar {
                width: 320px;
            }
        }
        
        @media (max-width: 768px) {
            .chat-sidebar {
                width: 100%;
                display: <?php echo $conversa_id ? 'none' : 'flex'; ?>;
            }
            
            .chat-area {
                display: <?php echo $conversa_id ? 'flex' : 'none'; ?>;
            }
            
            .message {
                max-width: 85%;
            }
            
            .chat-header {
                padding: 12px 16px;
            }
            
            .chat-messages {
                padding: 16px;
            }
            
            .chat-input {
                padding: 12px 16px;
                gap: 8px;
            }

             .chat-input .btn-send {
                 padding: 12px;
             }
             .chat-input .btn-send span {
                 display: none; /* Esconde texto "Enviar" no mobile */
             }
             .btn-attach {
                 width: 36px;
                 height: 36px;
                 font-size: 16px;
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
                    <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                    
                    <button type="button" class="btn-attach" id="btn-attach-image" title="Enviar Imagem">
                        <i class="fas fa-camera"></i>
                    </button>

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
    </script>
    <?php endif; ?>
</body>
</html>