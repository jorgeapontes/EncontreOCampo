<?php
// Interface simples de chat para transportador
session_start();
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit();
}

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
if ($conversa_id <= 0) {
    echo 'Conversa inválida';
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Buscar conversa e participantes (permitir acesso ao comprador ou transportador)
try {
    $sql = "SELECT cc.*, p.nome as produto_nome, p.imagem_url as produto_imagem,
                   uc.nome AS comprador_nome, ut.nome AS transportador_nome
            FROM chat_conversas cc
            LEFT JOIN produtos p ON cc.produto_id = p.id
            LEFT JOIN usuarios uc ON cc.comprador_id = uc.id
            LEFT JOIN usuarios ut ON cc.transportador_id = ut.id
            WHERE cc.id = :conversa_id LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conv) {
        echo 'Conversa não encontrada.';
        exit();
    }

    $uid = (int)$_SESSION['usuario_id'];
    $is_transportador = ($_SESSION['usuario_tipo'] === 'transportador');
    $is_comprador = ($_SESSION['usuario_tipo'] === 'comprador');

    // Verificar se o usuário pertence à conversa
    $belongs = false;
    if ($conv['comprador_id'] == $uid) $belongs = true;
    if (!empty($conv['transportador_id']) && $conv['transportador_id'] == $uid) $belongs = true;
    if (!empty($conv['vendedor_id']) && $conv['vendedor_id'] == $uid) $belongs = true;

    if (!$belongs) {
        echo 'Acesso negado.';
        exit();
    }

    // Definir o nome e papel do outro usuário para exibição
    if ($conv['comprador_id'] == $uid) {
        $outro_nome = $conv['transportador_nome'] ?: 'Transportador';
        $outro_papel = 'Transportador';
    } elseif (!empty($conv['transportador_id']) && $conv['transportador_id'] == $uid) {
        $outro_nome = $conv['comprador_nome'] ?: 'Comprador';
        $outro_papel = 'Comprador';
    } else {
        // se for vendedor ou outro participante, preferir comprador como outro
        $outro_nome = $conv['comprador_nome'] ?: 'Usuário';
        $outro_papel = 'Comprador';
    }

    // Determinar id do outro usuário para buscar foto de perfil
    $outro_usuario_id = null;
    if ($conv['comprador_id'] == $uid) {
        $outro_usuario_id = $conv['transportador_id'];
    } elseif (!empty($conv['transportador_id']) && $conv['transportador_id'] == $uid) {
        $outro_usuario_id = $conv['comprador_id'];
    } else {
        $outro_usuario_id = $conv['comprador_id'];
    }

    // Buscar foto de perfil do outro usuário (suporta comprador/vendedor/transportador)
    $foto_perfil = '../../img/no-user-image.png';
    if (!empty($outro_usuario_id)) {
        $sql_foto = "SELECT u.*, 
            IF(u.tipo = 'comprador', c.foto_perfil_url, 
               IF(u.tipo = 'vendedor', v.foto_perfil_url,
                  IF(u.tipo = 'transportador', t.foto_perfil_url, NULL))) as foto_perfil
            FROM usuarios u
            LEFT JOIN compradores c ON u.tipo = 'comprador' AND u.id = c.usuario_id
            LEFT JOIN vendedores v ON u.tipo = 'vendedor' AND u.id = v.usuario_id
            LEFT JOIN transportadores t ON u.tipo = 'transportador' AND u.id = t.usuario_id
            WHERE u.id = :outro_id LIMIT 1";

        $stmt_foto = $conn->prepare($sql_foto);
        $stmt_foto->bindParam(':outro_id', $outro_usuario_id, PDO::PARAM_INT);
        $stmt_foto->execute();
        $res_foto = $stmt_foto->fetch(PDO::FETCH_ASSOC);
        if ($res_foto && !empty($res_foto['foto_perfil'])) {
            $foto_perfil = $res_foto['foto_perfil'];
        }
    }

} catch (PDOException $e) {
    echo 'Erro ao carregar conversa.';
    exit();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chat - Transportador</title>
    <link rel="stylesheet" href="../chat/css/chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="chat-container">
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-comments"></i> Chat</h2>
                <small><?php echo htmlspecialchars($conv['produto_nome']); ?></small>
            </div>

            <div class="produto-info-sidebar">
                <img src="<?php echo htmlspecialchars($conv['produto_imagem'] ?: '../../img/placeholder.png'); ?>" alt="Produto">
                <div class="info">
                    <h3><?php echo htmlspecialchars($conv['produto_nome']); ?></h3>
                    <div class="preco"></div>
                </div>
            </div>

            <div class="conversas-lista">
                <div class="conversa-item ativa">
                    <div style="flex:1;">
                        <div class="nome"><i class="fas fa-user" style="margin-right:8px;"></i><?php echo htmlspecialchars($outro_nome); ?></div>
                        <div class="ultima-msg">Conversa com <?php echo htmlspecialchars($outro_papel); ?></div>
                    </div>
                </div>
            </div>

            <div style="padding:12px;">
                <a href="#" class="btn-voltar" onclick="goBack(event)">Voltar</a>
            </div>
        </div>

        <div class="chat-area">
            <div class="chat-header">
                <div class="usuario-info">
                    <div class="avatar-container">
                        <img id="outro-avatar" src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Avatar" style="width:56px;height:56px;border-radius:50%;object-fit:cover;cursor:pointer;border:2px solid #eee;">
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($outro_nome); ?></h3>
                        <small><?php echo htmlspecialchars($outro_papel); ?></small>
                    </div>
                </div>
                <a href="#" class="btn-voltar" onclick="goBack(event)">Voltar</a>
            </div>

            <div class="chat-messages" id="chat-messages"></div>

            <div class="chat-input">
                <div class="chat-input-buttons">
                    <button type="button" class="btn-attach" id="btn-attach-image" title="Enviar Imagem"><i class="fas fa-camera"></i></button>
                </div>
                <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                <input type="text" id="message-input" placeholder="Digite sua mensagem..." autocomplete="off">
                <button type="button" id="send-btn" class="btn-send"><i class="fas fa-paper-plane"></i><span>Enviar</span></button>
            </div>
        </div>
    </div>

    <script>
        // Avatar modal
        (function(){
            const avatar = document.getElementById('outro-avatar');
            if (avatar) {
                // criar modal dinamicamente
                const modal = document.createElement('div');
                modal.id = 'avatar-modal';
                modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:12000;align-items:center;justify-content:center;padding:20px;';
                const img = document.createElement('img');
                img.id = 'avatar-modal-img';
                img.style.cssText = 'max-width:96%;max-height:96%;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.5);';
                modal.appendChild(img);
                document.body.appendChild(modal);

                avatar.addEventListener('click', function(){
                    img.src = this.src;
                    modal.style.display = 'flex';
                });

                modal.addEventListener('click', function(){ modal.style.display = 'none'; img.src = ''; });
                document.addEventListener('keydown', function(e){ if (e.key === 'Escape') modal.style.display = 'none'; });
            }
        })();
        function goBack(e) {
            if (e) e.preventDefault();
            try {
                if (history.length > 1) {
                    history.back();
                    return;
                }
            } catch (err) {
                // ignore
            }
            // fallback
            window.location.href = '../transportador/meus_chats.php';
        }
        const conversaId = <?php echo (int)$conversa_id; ?>;
        const usuarioId = <?php echo (int)$_SESSION['usuario_id']; ?>;
        let ultimaMensagemId = 0;

        async function carregarMensagens() {
            try {
                const res = await fetch(`get_messages.php?conversa_id=${conversaId}&ultimo_id=${ultimaMensagemId}`);
                const data = await res.json();
                if (data.success && data.mensagens.length) {
                    const container = document.getElementById('chat-messages');
                    let estavaNaBase = container.scrollHeight - container.scrollTop <= container.clientHeight + 150;
                    data.mensagens.forEach(msg => {
                        if (msg.id > ultimaMensagemId) {
                            const div = document.createElement('div');
                            div.className = 'message ' + (msg.remetente_id == usuarioId ? 'sent' : 'received');
                            div.innerHTML = `<div>${escapeHtml(msg.mensagem)}</div><div class="time">${msg.data_formatada}</div>`;
                            container.appendChild(div);
                            ultimaMensagemId = msg.id;
                        }
                    });
                    if (estavaNaBase) container.scrollTop = container.scrollHeight;
                }
            } catch (e) {
                console.error(e);
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[m]; });
        }

        document.getElementById('send-btn').addEventListener('click', async () => {
            const input = document.getElementById('message-input');
            const mensagem = input.value.trim();
            if (!mensagem) return;
            try {
                const form = new URLSearchParams();
                form.append('conversa_id', conversaId);
                form.append('mensagem', mensagem);
                const res = await fetch('send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form});
                const data = await res.json();
                if (data.success) {
                    input.value = '';
                    carregarMensagens();
                } else {
                    alert(data.erro || 'Erro ao enviar mensagem');
                }
            } catch (e) { console.error(e); }
        });

        document.getElementById('message-input').addEventListener('keypress', (e) => { if (e.key === 'Enter') document.getElementById('send-btn').click(); });

        // Inicial
        carregarMensagens();
        setInterval(carregarMensagens, 2000);
    </script>
</body>
</html>
