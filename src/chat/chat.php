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

// NOVO: Capturar de onde o usuário veio
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
    // Se veio com conversa_id específica na URL
    if ($conversa_id_get > 0) {
        $conversa_id = $conversa_id_get;
        
        // Buscar informações do comprador desta conversa
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
        // Sem conversa_id, mostrar lista de conversas
        $conversa_id = null;
        $outro_usuario_id = null;
        $outro_usuario_nome = null;
    }
    
    // Buscar todas as conversas deste produto
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
    // COMPRADOR - criar/obter conversa única
    $conversa_id = obterOuCriarConversa($conn, $produto_id, $usuario_id, $vendedor_usuario_id);
    $outro_usuario_id = $vendedor_usuario_id;
    $outro_usuario_nome = $produto['nome_vendedor'] ?: $produto['vendedor_nome'];
    $conversas = null;
}

// NOVO: Determinar URL de volta baseado na origem
if ($eh_vendedor_produto) {
    // Vendedor sempre volta para chats.php
    $url_voltar = "../../src/vendedor/chats.php";
} else {
    // Comprador: verificar de onde veio
    if ($referrer === 'meus_chats') {
        // Veio da lista de chats
        $url_voltar = "../comprador/meus_chats.php";
    } else {
        // Veio do produto (proposta_nova.php)
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        /* Sidebar */
        .chat-sidebar {
            width: 350px;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            background: #fafafa;
        }
        
        .sidebar-header {
            padding: 20px;
            background: #2E7D32;
            color: white;
            border-bottom: 1px solid #1B5E20;
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .produto-info-sidebar {
            padding: 15px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .produto-info-sidebar img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .produto-info-sidebar .info {
            flex: 1;
        }
        
        .produto-info-sidebar .info h3 {
            font-size: 14px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .produto-info-sidebar .info .preco {
            color: #2E7D32;
            font-weight: bold;
        }
        
        .conversas-lista {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversa-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .conversa-item:hover {
            background: #f0f0f0;
        }
        
        .conversa-item.ativa {
            background: #e8f5e9;
            border-left: 3px solid #2E7D32;
        }
        
        .conversa-item .nome {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .conversa-item .ultima-msg {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        
        .conversa-item .badge-nao-lidas {
            background: #2E7D32;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Área do Chat */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 20px;
            background: #2E7D32;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .chat-header h3 {
            font-size: 18px;
        }
        
        .chat-header .usuario-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-voltar {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .btn-voltar:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .message {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 12px;
            word-wrap: break-word;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.sent {
            align-self: flex-end;
            background: #2E7D32;
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received {
            align-self: flex-start;
            background: #f1f1f1;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        
        .message .time {
            font-size: 11px;
            margin-top: 5px;
            opacity: 0.7;
        }
        
        .message.sent .time {
            text-align: right;
        }
        
        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }
        
        .chat-input input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
        }
        
        .chat-input input:focus {
            border-color: #2E7D32;
        }
        
        .chat-input button {
            background: #2E7D32;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .chat-input button:hover {
            background: #1B5E20;
        }
        
        .chat-placeholder {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #999;
            gap: 15px;
        }
        
        .chat-placeholder i {
            font-size: 80px;
            opacity: 0.3;
        }
        
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
        <!-- Sidebar -->
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-comments"></i> Chat</h2>
                <small>Produto: <?php echo htmlspecialchars($produto['nome']); ?></small>
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
                                <div>
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
                        <div style="padding: 20px; text-align: center; color: #999;">
                            Nenhuma conversa ainda
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif (!$eh_vendedor_produto): ?>
                <div class="conversas-lista">
                    <div class="conversa-item ativa">
                        <div>
                            <div class="nome"><?php echo htmlspecialchars($outro_usuario_nome); ?></div>
                            <div class="ultima-msg">Conversa com o vendedor</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Área do Chat -->
        <div class="chat-area">
            <?php if ($conversa_id && $outro_usuario_id): ?>
                <div class="chat-header">
                    <div class="usuario-info">
                        <i class="fas fa-user-circle" style="font-size: 32px;"></i>
                        <div>
                            <h3><?php echo htmlspecialchars($outro_usuario_nome); ?></h3>
                            <small><?php echo $eh_vendedor_produto ? 'Comprador' : 'Vendedor'; ?></small>
                        </div>
                    </div>
                    <a href="<?php echo $url_voltar; ?>" class="btn-voltar">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
                
                <div class="chat-messages" id="chat-messages"></div>
                
                <div class="chat-input">
                    <input type="text" id="message-input" placeholder="Digite sua mensagem..." autocomplete="off">
                    <button type="button" id="send-btn">
                        <i class="fas fa-paper-plane"></i> Enviar
                    </button>
                </div>
            <?php else: ?>
                <div class="chat-placeholder">
                    <i class="fas fa-comments"></i>
                    <p><?php echo $eh_vendedor_produto ? 'Selecione uma conversa para começar' : 'Carregando chat...'; ?></p>
                    <?php if ($eh_vendedor_produto): ?>
                        <a href="<?php echo $url_voltar; ?>" class="btn-voltar" style="margin-top: 20px;">
                            <i class="fas fa-arrow-left"></i> Voltar para Chats
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
        
        function carregarMensagens() {
            if (carregandoMensagens) return;
            carregandoMensagens = true;
            
            fetch(`get_messages.php?conversa_id=${conversaId}&ultimo_id=${ultimaMensagemId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.mensagens.length > 0) {
                        const messagesDiv = document.getElementById('chat-messages');
                        const estavaNaBase = messagesDiv.scrollHeight - messagesDiv.scrollTop <= messagesDiv.clientHeight + 100;
                        
                        data.mensagens.forEach(msg => {
                            if (msg.id > ultimaMensagemId) {
                                const div = document.createElement('div');
                                div.className = 'message ' + (msg.remetente_id == usuarioId ? 'sent' : 'received');
                                div.innerHTML = `
                                    <div>${escapeHtml(msg.mensagem)}</div>
                                    <div class="time">${msg.data_formatada}</div>
                                `;
                                messagesDiv.appendChild(div);
                                ultimaMensagemId = msg.id;
                            }
                        });
                        
                        if (estavaNaBase) {
                            messagesDiv.scrollTop = messagesDiv.scrollHeight;
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
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
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
        
        document.getElementById('send-btn').addEventListener('click', enviarMensagem);
        
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                enviarMensagem();
            }
        });
        
        // Carregar mensagens iniciais
        carregarMensagens();
        
        // Atualizar a cada 1 segundo
        setInterval(carregarMensagens, 1000);
    </script>
    <?php endif; ?>
</body>
</html>