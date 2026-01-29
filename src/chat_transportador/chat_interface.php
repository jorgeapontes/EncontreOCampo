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
    // Considerar `is_comprador` sempre que o usuário for o participante comprador
    // (permite que vendedores que fizeram a compra atuem como comprador na conversa)
    $is_comprador = ($conv['comprador_id'] == $uid);

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
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="../chat/css/chat.css">
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
        /* Remover bordas/outlines indesejadas em imagens do chat */
        .chat-messages img { border: none !important; outline: none !important; box-shadow: none !important; }
        .chat-messages img:focus, .chat-messages img:active { outline: none !important; box-shadow: none !important; border: none !important; }
        /* Estilo para status de proposta */
        .proposta-status {
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 700;
            display: inline-block;
            margin-top: 10px;
            font-size: 14px;
        }
        .proposta-aceita {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .proposta-recusada {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .proposta-pendente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .proposta-card {
            border: 1px solid #e1e4e8;
            padding: 12px;
            border-radius: 8px;
            background: #f8f9fa;
            max-width: 420px;
            color: #1c1e21;
            margin: 4px 0;
        }
        .proposta-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 10px;
        }
        .btn-aceitar {
            background: #42b72a;
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-recusar {
            background: #ff4444;
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-aceitar:disabled, .btn-recusar:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        /* Modal de sucesso */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 14000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-content {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #d4edda;
            color: #155724;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .btn-modal-primary {
            background: #42b72a;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-modal-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }
    </style>
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
                <a href="#" class="btn-voltar" onclick="goBack(event)"> <i class="fas fa-arrow-left"></i>Voltar</a>
            </div>

            <div class="chat-messages" id="chat-messages"></div>

            <div class="chat-input">
                <div class="chat-input-buttons">
                    <button type="button" class="btn-attach" id="btn-attach-image" title="Enviar Imagem"><i class="fas fa-camera"></i></button>
                    <?php if ($is_comprador): ?>
                        <button type="button" class="btn-negociar" id="btn-negociar" title="Propor Entrega"><i class="fas fa-handshake"></i></button>
                    <?php endif; ?>
                </div>
                <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                <input type="text" id="message-input" placeholder="Digite sua mensagem..." autocomplete="off">
                <button type="button" id="send-btn" class="btn-send"><i class="fas fa-paper-plane"></i><span>Enviar</span></button>
            </div>
        </div>
    </div>

    <!-- Modal de proposta para transportador (apenas comprador) -->
    <div id="modal-proposta-transportador" style="display:none; position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:13000;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;padding:20px;border-radius:8px;max-width:520px;width:100%;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 style="margin:0;font-size:18px;"><i class="fas fa-handshake"></i> Propor Entrega</h3>
                <button id="fechar-modal-proposta" style="background:transparent;border:none;font-size:22px;">&times;</button>
            </div>
            <div>
                <label>Valor do frete (R$)</label>
                <input type="number" id="proposta-valor" step="0.01" min="0" style="width:100%;padding:8px;margin:6px 0;border:1px solid #ddd;border-radius:6px;" />
            </div>
            <div>
                <label>Data limite de entrega</label>
                <input type="date" id="proposta-data" style="width:100%;padding:8px;margin:6px 0;border:1px solid #ddd;border-radius:6px;" />
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
                <button id="enviar-proposta" style="background:#42b72a;color:#fff;border:none;padding:8px 12px;border-radius:6px;">Enviar Proposta</button>
                <button id="cancelar-proposta" style="background:#ccc;border:none;padding:8px 12px;border-radius:6px;">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal de sucesso ao aceitar proposta -->
    <div id="modal-sucesso-aceite" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3 style="margin:0 0 5px 0;">Proposta Aceita!</h3>
                    <p style="margin:0;color:#666;">Entrega criada com sucesso.</p>
                </div>
            </div>
            <div style="margin:15px 0;">
                <p>✅ A entrega foi criada e já está disponível para acompanhamento.</p>
                <p>📦 Informações de entrega foram repassadas ao transportador.</p>
                <p>⏳ Aguarde a coleta e entrega do produto.</p>
            </div>
            <div class="modal-buttons">
                <button id="btn-fechar-modal" class="btn-modal-secondary">Continuar no Chat</button>
                <button id="btn-ver-entregas" class="btn-modal-primary">Ver Minhas Entregas</button>
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

        // Cache para status das propostas já processadas
        const propostasProcessadas = new Map();

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
                            const content = document.createElement('div');
                            
                            if (msg.tipo === 'imagem') {
                                const img = document.createElement('img');
                                img.src = msg.mensagem;
                                img.style.maxWidth = '320px';
                                img.style.borderRadius = '8px';
                                img.style.display = 'block';
                                img.alt = 'Imagem enviada';
                                img.addEventListener('click', () => {
                                    const modal = document.createElement('div');
                                    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:13000;padding:20px;';
                                    const big = document.createElement('img');
                                    big.src = img.src; big.style.maxWidth = '98%'; big.style.maxHeight = '98%'; big.style.borderRadius = '8px';
                                    modal.appendChild(big);
                                    modal.addEventListener('click', () => modal.remove());
                                    document.body.appendChild(modal);
                                });
                                content.appendChild(img);
                            } else if (msg.tipo === 'proposta' || (msg.mensagem && (msg.mensagem.toUpperCase().indexOf('PROPOSTA') !== -1 || msg.mensagem.indexOf('ID') !== -1))) {
                                // É uma mensagem de proposta - renderizar como card
                                renderizarPropostaCard(msg, content);
                            } else if (msg.tipo === 'aceite') {
                                // Mensagem automática de aceite - mostrar como notificação
                                const notif = document.createElement('div');
                                notif.className = 'proposta-status proposta-aceita';
                                notif.innerHTML = `<i class="fas fa-check-circle" style="margin-right:5px;"></i> ${escapeHtml(msg.mensagem)}`;
                                content.appendChild(notif);
                            } else {
                                // Mensagem de texto normal
                                content.textContent = msg.mensagem;
                            }
                            
                            const time = document.createElement('div');
                            time.className = 'time';
                            time.textContent = msg.data_formatada;
                            div.appendChild(content);
                            div.appendChild(time);
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

        async function renderizarPropostaCard(msg, content) {
            // Extrair dados da proposta
            let dados = null;
            let propostaId = null;
            
            // Tentar extrair do dados_json
            if (msg.dados_json) {
                try {
                    dados = JSON.parse(msg.dados_json);
                    if (dados && dados.propostas_transportador_id) {
                        propostaId = dados.propostas_transportador_id;
                    }
                } catch(e) {
                    console.error('Erro ao parsear dados_json:', e);
                }
            }
            
            // Se não tem dados_json, tentar extrair do texto
            if (!dados && msg.mensagem) {
                dados = extrairDadosDoTexto(msg.mensagem);
                if (dados && dados.propostas_transportador_id) {
                    propostaId = dados.propostas_transportador_id;
                }
            }
            
            // Criar o card da proposta
            const card = document.createElement('div');
            card.className = 'proposta-card';
            
            // Titulo
            const title = document.createElement('div');
            title.innerHTML = '<strong><i class="fas fa-handshake" style="margin-right:5px;"></i>Proposta de Entrega</strong>';
            card.appendChild(title);
            
            // Detalhes
            const detail = document.createElement('div');
            detail.style.marginTop = '8px';
            
            // Valor
            let valorText = 'Valor não especificado';
            if (dados && dados.valor) {
                valorText = 'R$ ' + parseFloat(dados.valor).toFixed(2).replace('.', ',');
            } else if (msg.mensagem) {
                // Tentar extrair valor do texto
                const valorMatch = msg.mensagem.match(/R\$\s*([0-9.,]+)/i) || msg.mensagem.match(/Valor[:\-\s]+([0-9.,]+)/i);
                if (valorMatch) {
                    const vraw = valorMatch[1].replace(/\./g, '').replace(/,/g, '.');
                    valorText = 'R$ ' + parseFloat(vraw).toFixed(2).replace('.', ',');
                }
            }
            
            detail.innerHTML = `<div style="margin-bottom:5px;"><span style="color:#666;">Valor:</span> <strong style="color:#333;">${valorText}</strong></div>`;
            
            // Prazo
            if (dados && dados.prazo) {
                const dataFormatada = formatarData(dados.prazo);
                detail.innerHTML += `<div style="margin-bottom:5px;"><span style="color:#666;">Prazo:</span> <span style="color:#333;">${dataFormatada}</span></div>`;
            }
            
            // ID da proposta (se disponível)
            if (propostaId) {
                detail.innerHTML += `<div><span style="color:#666;font-size:12px;">ID: ${propostaId}</span></div>`;
            }
            
            card.appendChild(detail);
            
            // Se for transportador, verificar status e mostrar ações apropriadas
            <?php if ($is_transportador): ?>
            if (propostaId) {
                // Verificar status da proposta
                let status = propostasProcessadas.get(propostaId);
                
                if (!status) {
                    // Buscar status do servidor
                    try {
                        const res = await fetch('get_proposta_status.php?id=' + propostaId);
                        const data = await res.json();
                        status = data.status || 'pendente';
                        propostasProcessadas.set(propostaId, status);
                    } catch(e) {
                        console.error('Erro ao buscar status:', e);
                        status = 'pendente';
                    }
                }
                
                // Criar área de ações/status
                const actionsContainer = document.createElement('div');
                actionsContainer.className = 'proposta-actions';
                
                if (status === 'pendente') {
                    // Mostrar botões Aceitar/Recusar
                    const btnAceitar = document.createElement('button');
                    btnAceitar.className = 'btn-aceitar';
                    btnAceitar.textContent = 'Aceitar';
                    btnAceitar.addEventListener('click', async () => {
                        if (!propostaId) return alert('ID da proposta não encontrado');
                        await performPropostaAction('aceitar', propostaId, btnAceitar, btnRecusar, actionsContainer);
                    });

                    const btnRecusar = document.createElement('button');
                    btnRecusar.className = 'btn-recusar';
                    btnRecusar.textContent = 'Recusar';
                    btnRecusar.addEventListener('click', async () => {
                        if (!propostaId) return alert('ID da proposta não encontrado');
                        if (!confirm('Deseja recusar esta proposta?')) return;
                        await performPropostaAction('recusar', propostaId, btnRecusar, btnAceitar, actionsContainer);
                    });

                    actionsContainer.appendChild(btnRecusar);
                    actionsContainer.appendChild(btnAceitar);
                } else {
                    // Mostrar status
                    const statusDiv = document.createElement('div');
                    statusDiv.className = `proposta-status proposta-${status}`;
                    statusDiv.textContent = status === 'aceita' ? '✓ Proposta aceita' : 
                                           status === 'recusada' ? '✗ Proposta recusada' : 
                                           '⏳ Pendente';
                    actionsContainer.appendChild(statusDiv);
                }
                
                card.appendChild(actionsContainer);
            }
            <?php else: ?>
            // Para compradores, mostrar apenas o status se disponível
            if (propostaId) {
                let status = propostasProcessadas.get(propostaId);
                
                if (!status) {
                    try {
                        const res = await fetch('get_proposta_status.php?id=' + propostaId);
                        const data = await res.json();
                        status = data.status || 'pendente';
                        propostasProcessadas.set(propostaId, status);
                    } catch(e) {
                        status = 'pendente';
                    }
                }
                
                if (status !== 'pendente') {
                    const statusDiv = document.createElement('div');
                    statusDiv.className = `proposta-status proposta-${status}`;
                    statusDiv.innerHTML = status === 'aceita' ? 
                        '<i class="fas fa-check-circle" style="margin-right:5px;"></i> Proposta aceita pelo transportador. Informações de entrega repassadas. Aguarde a entrega.' : 
                        '<i class="fas fa-times-circle" style="margin-right:5px;"></i> Proposta recusada pelo transportador.';
                    card.appendChild(statusDiv);
                }
            }
            <?php endif; ?>
            
            content.appendChild(card);
        }

        function extrairDadosDoTexto(texto) {
            const dados = {};
            if (!texto) return dados;
            
            // Normalizar texto
            let textoLimpo = texto.replace(/\*/g, '').replace(/\u00A0/g, ' ').replace(/\s+/g, ' ').trim();
            
            // Extrair ID
            const idMatch = textoLimpo.match(/\bID\b\s*[:\-\s]?\s*(\d+)/i);
            if (idMatch) dados.propostas_transportador_id = parseInt(idMatch[1]);
            
            // Extrair valor
            const valorMatch = textoLimpo.match(/\bValor\b\s*[:\-\s]?\s*(?:R\$\s*)?([0-9.,]+)/i);
            if (valorMatch) {
                const vraw = valorMatch[1].replace(/\./g, '').replace(/,/g, '.');
                dados.valor = parseFloat(vraw);
            }
            
            // Extrair prazo
            const prazoMatch = textoLimpo.match(/\bPrazo\b\s*[:\-\s]?\s*([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}\/[0-9]{2}\/[0-9]{4})/i);
            if (prazoMatch) {
                let p = prazoMatch[1];
                if (p.indexOf('/') !== -1) {
                    const parts = p.split('/');
                    p = parts[2] + '-' + parts[1] + '-' + parts[0];
                }
                dados.prazo = p;
            }
            
            return dados;
        }

        function formatarData(dataStr) {
            try {
                const data = new Date(dataStr);
                return data.toLocaleDateString('pt-BR');
            } catch(e) {
                return dataStr;
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[m]; });
        }

        async function performPropostaAction(action, ptId, primaryBtn, secondaryBtn, actionsContainer) {
            // Desabilitar botões durante processamento
            if (primaryBtn) primaryBtn.disabled = true;
            if (secondaryBtn) secondaryBtn.disabled = true;
            
            const oldText1 = primaryBtn ? primaryBtn.textContent : '';
            const oldText2 = secondaryBtn ? secondaryBtn.textContent : '';
            
            if (primaryBtn) primaryBtn.textContent = 'Processando...';
            if (secondaryBtn) secondaryBtn.textContent = 'Processando...';
            
            try {
                const res = await fetch('responder_proposta.php', {
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({acao: action, id: ptId})
                });
                const j = await res.json();
                
                if (j.success) {
                    const finalStatus = (action === 'aceitar') ? 'aceita' : 'recusada';
                    
                    // Atualizar cache
                    propostasProcessadas.set(ptId, finalStatus);
                    
                    // Atualizar UI
                    if (actionsContainer) {
                        actionsContainer.innerHTML = '';
                        const statusDiv = document.createElement('div');
                        statusDiv.className = `proposta-status proposta-${finalStatus}`;
                        statusDiv.textContent = finalStatus === 'aceita' ? '✓ Proposta aceita' : '✗ Proposta recusada';
                        actionsContainer.appendChild(statusDiv);
                    }
                    
                    // Se aceitou, mostrar modal de sucesso
                    if (action === 'aceitar') {
                        setTimeout(() => {
                            document.getElementById('modal-sucesso-aceite').style.display = 'flex';
                        }, 300);
                    } else {
                        alert('Proposta recusada com sucesso');
                    }
                    
                    // Recarregar mensagens para garantir consistência
                    setTimeout(carregarMensagens, 500);
                } else {
                    alert(j.erro || j.error || 'Erro ao processar');
                    // Reabilitar botões
                    if (primaryBtn) {
                        primaryBtn.disabled = false;
                        primaryBtn.textContent = oldText1;
                    }
                    if (secondaryBtn) {
                        secondaryBtn.disabled = false;
                        secondaryBtn.textContent = oldText2;
                    }
                }
            } catch (e) {
                console.error(e);
                alert('Erro de conexão');
                // Reabilitar botões
                if (primaryBtn) {
                    primaryBtn.disabled = false;
                    primaryBtn.textContent = oldText1;
                }
                if (secondaryBtn) {
                    secondaryBtn.disabled = false;
                    secondaryBtn.textContent = oldText2;
                }
            }
        }

        // Modal de sucesso
        document.getElementById('btn-fechar-modal').addEventListener('click', function() {
            document.getElementById('modal-sucesso-aceite').style.display = 'none';
        });

        document.getElementById('btn-ver-entregas').addEventListener('click', function() {
            window.location.href = '../transportador/entregas.php';
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('modal-sucesso-aceite').style.display = 'none';
            }
        });

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

        // Envio de imagem
        const attachBtn = document.getElementById('btn-attach-image');
        const imageInput = document.getElementById('image-input');
        if (attachBtn && imageInput) {
            attachBtn.addEventListener('click', () => imageInput.click());
            imageInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;
                const allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                if (!allowed.includes(file.type)) { alert('Formato não suportado'); imageInput.value = ''; return; }
                if (file.size > 5 * 1024 * 1024) { alert('Arquivo muito grande (max 5MB)'); imageInput.value = ''; return; }
                const fd = new FormData();
                fd.append('conversa_id', conversaId);
                fd.append('imagem', file);
                try {
                    const res = await fetch('send_message.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        imageInput.value = '';
                        carregarMensagens();
                    } else {
                        alert(data.erro || 'Erro ao enviar imagem');
                    }
                } catch (err) { console.error(err); alert('Erro de conexão'); }
            });
        }

        // Inicial
        carregarMensagens();
        setInterval(carregarMensagens, 2000);

        // Modal de proposta - apenas comprador
        const btnNegociar = document.getElementById('btn-negociar');
        const modalProposta = document.getElementById('modal-proposta-transportador');
        const fecharModalProposta = document.getElementById('fechar-modal-proposta');
        const cancelarProposta = document.getElementById('cancelar-proposta');
        const enviarProposta = document.getElementById('enviar-proposta');

        if (btnNegociar && modalProposta) {
            btnNegociar.addEventListener('click', () => { modalProposta.style.display = 'flex'; });
            fecharModalProposta.addEventListener('click', () => modalProposta.style.display = 'none');
            cancelarProposta.addEventListener('click', () => modalProposta.style.display = 'none');

            enviarProposta.addEventListener('click', async () => {
                const valor = document.getElementById('proposta-valor').value;
                const data_entrega = document.getElementById('proposta-data').value;
                if (!valor || !data_entrega) return alert('Preencha valor e data');
                enviarProposta.disabled = true;
                enviarProposta.textContent = 'Enviando...';
                try {
                    const form = new FormData();
                    form.append('conversa_id', conversaId);
                    form.append('valor', valor);
                    form.append('data_entrega', data_entrega);
                    const res = await fetch('send_proposal.php', { method: 'POST', body: form });
                    const j = await res.json();
                    if (j.success) {
                        alert('Proposta enviada');
                        modalProposta.style.display = 'none';
                        document.getElementById('proposta-valor').value = '';
                        document.getElementById('proposta-data').value = '';
                        carregarMensagens();
                    } else {
                        alert(j.error || j.erro || 'Erro ao enviar proposta');
                    }
                } catch (e) { console.error(e); alert('Erro de conexão'); }
                enviarProposta.disabled = false;
                enviarProposta.textContent = 'Enviar Proposta';
            });
        }
    </script>
</body>
</html>