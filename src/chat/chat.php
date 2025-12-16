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