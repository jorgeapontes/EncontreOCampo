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

// Buscar conversa e participantes
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

    // Definir o nome e papel do outro usuário
    if ($conv['comprador_id'] == $uid) {
        $outro_nome = $conv['transportador_nome'] ?: 'Transportador';
        $outro_papel = 'Transportador';
    } elseif (!empty($conv['transportador_id']) && $conv['transportador_id'] == $uid) {
        $outro_nome = $conv['comprador_nome'] ?: 'Comprador';
        $outro_papel = 'Comprador';
    } else {
        $outro_nome = $conv['comprador_nome'] ?: 'Usuário';
        $outro_papel = 'Comprador';
    }

    // Foto de perfil
    $outro_usuario_id = null;
    if ($conv['comprador_id'] == $uid) {
        $outro_usuario_id = $conv['transportador_id'];
    } elseif (!empty($conv['transportador_id']) && $conv['transportador_id'] == $uid) {
        $outro_usuario_id = $conv['comprador_id'];
    } else {
        $outro_usuario_id = $conv['comprador_id'];
    }

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

    // Buscar endereço do comprador (para exibir na sidebar quando aplicável)
    $comprador_endereco = null;
    $comprador_endereco_maps = null;
    $comprador_telefone = null;
    if (!empty($conv['comprador_id'])) {
        $sql_end = "SELECT rua, numero, complemento, cidade, estado, cep, telefone1
                    FROM compradores WHERE usuario_id = :comprador_id LIMIT 1";
        $stmt_end = $conn->prepare($sql_end);
        $stmt_end->bindParam(':comprador_id', $conv['comprador_id'], PDO::PARAM_INT);
        $stmt_end->execute();
        $comprador_info = $stmt_end->fetch(PDO::FETCH_ASSOC);
        if ($comprador_info) {
            $end_completo = $comprador_info['rua'] . ', ' . $comprador_info['numero'];
            if (!empty($comprador_info['complemento'])) $end_completo .= ' - ' . $comprador_info['complemento'];
            $end_completo .= ', ' . $comprador_info['cidade'] . ' - ' . $comprador_info['estado'];
            $comprador_endereco = $end_completo;
            $comprador_endereco_maps = urlencode($end_completo);
            $comprador_telefone = $comprador_info['telefone1'] ?? null;

            if ($comprador_telefone) {
                $tel = preg_replace('/[^0-9]/', '', $comprador_telefone);
                if (substr($tel, 0, 1) == '0') $tel = substr($tel, 1);
                if (strlen($tel) <= 11) $tel = '55' . $tel;
                $comprador_telefone = $tel;
            }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS ESPECÍFICO PARA A LÓGICA DE DUPLICAÇÃO DE CARDS */
        
        /* Estilos gerais do card na sidebar */
        #sidebar-proposta-container {
            padding: 10px 16px;
            background: #fff;
            border-bottom: 1px solid #e4e6eb;
        }
        
        #sidebar-proposta-container .proposta-card {
            margin: 0;
            width: 100%;
            font-size: 0.9em;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #ddd;
        }

        /* LÓGICA DE VISIBILIDADE DOS BOTÕES (DESKTOP VS MOBILE) */
        
        /* Por padrão (Mobile First ou genérico), os botões do chat aparecem */
        .acoes-chat {
            display: flex;
        }
        
        /* A sidebar normalmente não aparece em mobile, controlado pelo chat.css */
        
        /* DESKTOP (maior que 768px) */
        @media (min-width: 769px) {
            /* No Desktop, ESCONDER botões dentro da mensagem do chat */
            .acoes-chat {
                display: none !important;
            }
            
            /* No Desktop, MOSTRAR container da sidebar */
            #sidebar-proposta-container {
                display: block;
            }
        }

        /* MOBILE (menor ou igual a 768px) */
        @media (max-width: 768px) {
            .chat-sidebar {
                width: 100%;
                display: <?php echo $conversa_id ? 'none' : 'flex'; ?>;
            }
            .chat-area {
                display: <?php echo $conversa_id ? 'flex' : 'none'; ?>;
            }
            
            /* No Mobile, garantir que os botões do chat apareçam */
            .acoes-chat {
                display: flex !important;
            }
            
            /* Esconder container extra da sidebar no mobile (caso a sidebar apareça de alguma forma) */
            #sidebar-proposta-container {
                display: none;
            }
        }

        /* Estilos auxiliares */
        .chat-messages img { border: none !important; outline: none !important; }
        .proposta-status {
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 700;
            display: inline-block;
            margin-top: 10px;
            font-size: 14px;
        }
        .proposta-aceita { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .proposta-recusada { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .proposta-pendente { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        
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
            gap: 8px;
            justify-content: flex-end;
            margin-top: 10px;
        }
        
        .btn-aceitar { background: #42b72a; color: #fff; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-recusar { background: #ff4444; color: #fff; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; }
        
        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 14000; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: #fff; padding: 25px; border-radius: 12px; max-width: 500px; width: 100%; }
        .modal-header { display: flex; align-items: center; margin-bottom: 15px; }
        .modal-icon { width: 48px; height: 48px; border-radius: 50%; background: #d4edda; color: #155724; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 15px; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-modal-primary { background: #42b72a; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-modal-secondary { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }

        .whatsapp-button {
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #25D366;
    color: white;
    padding: 13.75px 15px;
    border-radius: 100%;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
    border: #25D366;
    cursor: pointer;
}

.whatsapp-button:hover {
    background-color: #1da851;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.2);
}

.whatsapp-button i {
    font-size: 20px ;
}

.sidebar-negociarcao-btn:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
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
                    <div style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end;">
                        <?php if (!empty($comprador_telefone)): ?>
                            <a href="https://wa.me/<?php echo $comprador_telefone; ?>" target="_blank" class="whatsapp-button"><i class="fab fa-whatsapp" style="color: #ffffff;"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CARD DE ENDEREÇO DO COMPRADOR -->
            <?php if (!empty($comprador_endereco) && $is_transportador): ?>
                <div class="endereco-card" style="padding:12px;background:#fff;border-bottom:1px solid #e9ecef;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <i class="fas fa-map-marker-alt" style="font-size:18px;color:#d9534f;"></i>
                        <div>
                            <strong style="font-size:13px;">Endereço do Comprador</strong>
                        </div>
                    </div>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $comprador_endereco_maps; ?>" target="_blank" style="color:#1877f2;text-decoration:underline;display:block;">
                        <div style="font-size:13px;color:inherit;margin-bottom:6px;">
                            <?php echo htmlspecialchars(strlen($comprador_endereco) > 60 ? substr($comprador_endereco,0,57).'...' : $comprador_endereco); ?>
                        </div>
                    </a>
                    <?php if (!empty($comprador_info['cep'])): ?>
                        <div style="font-size:12px;color:#777;">CEP: <?php echo htmlspecialchars($comprador_info['cep']); ?></div>
                    <?php endif; ?>
                    <div class="endereco-usuario-footer">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Este endereço é fornecido para fins de negociação.
                        </small>
                    </div>
                </div>
            <?php endif; ?>
            <!-- BOTÃO MAIOR NA SIDEBAR PARA ENVIAR PROPOSTA (mesma ação do btn-negociar) -->
            <?php if ($is_transportador): ?>
                <div class="sidebar-negociacao-btn" style="padding:12px;">
                    <button type="button" id="btn-negociar-sidebar" style="width:100%;background:#42b72a;color:#fff;border:none;padding:10px 12px;border-radius:8px;font-size:15px;display:flex;gap:8px;align-items:center;justify-content:center;">
                        <i class="fas fa-handshake"></i>
                        Propor Entrega
                    </button>
                </div>
            <?php endif; ?>

            <div id="sidebar-proposta-container" style="display:none;">
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
                    <?php if ($is_transportador): ?>
                        <button type="button" class="btn-negociar" id="btn-negociar" title="Propor Entrega"><i class="fas fa-handshake"></i></button>
                    <?php endif; ?>
                </div>
                <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                <input type="text" id="message-input" placeholder="Digite sua mensagem..." autocomplete="off">
                <button type="button" id="send-btn" class="btn-send"><i class="fas fa-paper-plane"></i><span>Enviar</span></button>
            </div>
        </div>
    </div>

    <?php if ($is_transportador): ?>
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
    <?php endif; ?>

    <?php if ($is_comprador): ?>
    <div id="modal-sucesso-aceite" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon"> <i class="fas fa-check"></i> </div>
                <div>
                    <h3 style="margin:0 0 5px 0;">Proposta Aceita!</h3>
                    <p style="margin:0;color:#666;">Você aceitou a proposta de entrega.</p>
                </div>
            </div>
            <div style="margin:15px 0;">
                <p>✅ Você aceitou a proposta de entrega.</p>
                <p>📦 O transportador foi notificado e irá proceder com a coleta e entrega.</p>
            </div>
            <div class="modal-buttons">
                <button id="btn-fechar-modal" class="btn-modal-secondary">Continuar no Chat</button>
                <button id="btn-ver-compras" class="btn-modal-primary">Ver Minhas Compras</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Funções de utilidade e navegação
        (function(){
            const avatar = document.getElementById('outro-avatar');
            if (avatar) {
                const modal = document.createElement('div');
                modal.id = 'avatar-modal';
                modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:12000;align-items:center;justify-content:center;padding:20px;';
                const img = document.createElement('img');
                img.style.cssText = 'max-width:96%;max-height:96%;border-radius:8px;';
                modal.appendChild(img);
                document.body.appendChild(modal);
                avatar.addEventListener('click', function(){ img.src = this.src; modal.style.display = 'flex'; });
                modal.addEventListener('click', function(){ modal.style.display = 'none'; });
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
        
        // Variáveis globais
        const conversaId = <?php echo (int)$conversa_id; ?>;
        const usuarioId = <?php echo (int)$_SESSION['usuario_id']; ?>;
        const usuarioTipo = '<?php echo $_SESSION['usuario_tipo']; ?>';
        let ultimaMensagemId = 0;
        let ultimaPropostaIdRenderizada = 0; // Para controlar a sidebar
        const propostasProcessadas = new Map();

        // 1. Carregar mensagens
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
                                img.addEventListener('click', () => { /* logica zoom */ });
                                content.appendChild(img);
                            } else if (msg.tipo === 'proposta' || (msg.mensagem && msg.mensagem.indexOf('ID') !== -1)) {
                                renderizarPropostaCard(msg, content);
                            } else if (msg.tipo === 'aceite') {
                                const notif = document.createElement('div');
                                notif.className = 'proposta-status proposta-aceita';
                                notif.innerHTML = `<i class="fas fa-check-circle"></i> ${escapeHtml(msg.mensagem)}`;
                                content.appendChild(notif);
                            } else {
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
            } catch (e) { console.error(e); }
        }

        // 2. Extrair dados
        function extrairDadosDoTexto(texto) {
            const dados = {};
            if (!texto) return dados;
            let textoLimpo = texto.replace(/\*/g, '').replace(/\u00A0/g, ' ').replace(/\s+/g, ' ').trim();
            const idMatch = textoLimpo.match(/\bID\b\s*[:\-\s]?\s*(\d+)/i);
            if (idMatch) dados.propostas_transportador_id = parseInt(idMatch[1]);
            const valorMatch = textoLimpo.match(/\bValor\b\s*[:\-\s]?\s*(?:R\$\s*)?([0-9.,]+)/i);
            if (valorMatch) dados.valor = parseFloat(valorMatch[1].replace(/\./g, '').replace(/,/g, '.'));
            const prazoMatch = textoLimpo.match(/\bPrazo\b\s*[:\-\s]?\s*([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}\/[0-9]{2}\/[0-9]{4})/i);
            if (prazoMatch) dados.prazo = prazoMatch[1];
            return dados;
        }

        // 3. Renderizar Card (Lógica Principal modificada)
        async function renderizarPropostaCard(msg, content) {
            let dados = null;
            let propostaId = null;
            
            if (msg.dados_json) {
                try { dados = JSON.parse(msg.dados_json); } catch(e){}
                if (dados?.propostas_transportador_id) propostaId = dados.propostas_transportador_id;
            }
            if (!dados && msg.mensagem) {
                dados = extrairDadosDoTexto(msg.mensagem);
                if (dados?.propostas_transportador_id) propostaId = dados.propostas_transportador_id;
            }

            // Construir HTML do card
            const card = document.createElement('div');
            card.className = 'proposta-card';
            
            let htmlInner = '<strong><i class="fas fa-handshake" style="margin-right:5px;"></i>Proposta de Entrega</strong>';
            htmlInner += '<div style="margin-top:8px;">';
            
            let valorText = 'Valor não especificado';
            if (dados && dados.valor) valorText = 'R$ ' + parseFloat(dados.valor).toFixed(2).replace('.', ',');
            htmlInner += `<div style="margin-bottom:5px;"><span style="color:#666;">Valor:</span> <strong style="color:#333;">${valorText}</strong></div>`;
            
            if (dados && dados.prazo) htmlInner += `<div style="margin-bottom:5px;"><span style="color:#666;">Prazo:</span> <span style="color:#333;">${dados.prazo}</span></div>`;
            if (propostaId) htmlInner += `<div><span style="color:#666;font-size:12px;">ID: ${propostaId}</span></div>`;
            htmlInner += '</div>';
            
            card.innerHTML = htmlInner;

            // Identificar Status
            let status = 'pendente';
            if (propostaId) {
                if (propostasProcessadas.has(propostaId)) {
                    status = propostasProcessadas.get(propostaId);
                } else {
                    try {
                        const res = await fetch('get_proposta_status.php?id=' + propostaId);
                        const data = await res.json();
                        status = data.status || 'pendente';
                        propostasProcessadas.set(propostaId, status);
                    } catch(e) { }
                }
            }

            // Ações do Chat (Botões) - Visíveis apenas no mobile via CSS (.acoes-chat)
            const actionsContainer = document.createElement('div');
            actionsContainer.className = 'proposta-actions acoes-chat'; // Classe acoes-chat controla visibilidade
            
            adicionarBotoesOuStatus(actionsContainer, status, propostaId, 'chat');
            card.appendChild(actionsContainer);
            content.appendChild(card);

            // ATUALIZAÇÃO DA SIDEBAR
            // Se esta for uma proposta válida e for mais recente do que a que temos na sidebar
            if (propostaId && propostaId >= ultimaPropostaIdRenderizada) {
                ultimaPropostaIdRenderizada = propostaId;
                atualizarSidebarProposta(dados, propostaId, status);
            }
        }

        // 4. Nova função para atualizar a sidebar
        function atualizarSidebarProposta(dados, propostaId, status) {
            const containerSidebar = document.getElementById('sidebar-proposta-container');
            if (!containerSidebar) return;
            
            // Limpar conteúdo anterior
            containerSidebar.innerHTML = '';
            containerSidebar.style.display = 'block'; // Garante que a div container apareça (o CSS media query controla o pai)

            // Título na sidebar
            const titulo = document.createElement('h4');
            titulo.style.cssText = "margin:0 0 10px 0; font-size:14px; color:#666;";
            titulo.textContent = "Última Proposta";
            containerSidebar.appendChild(titulo);

            // Criar card idêntico
            const card = document.createElement('div');
            card.className = 'proposta-card';
            
            let valorText = dados.valor ? 'R$ ' + parseFloat(dados.valor).toFixed(2).replace('.', ',') : 'N/A';
            
            let htmlInner = `
                <strong><i class="fas fa-handshake"></i> Entrega</strong>
                <div style="margin-top:8px; font-size:13px;">
                    <div>Valor: <strong>${valorText}</strong></div>
                    <div>Prazo: ${dados.prazo || '--'}</div>
                </div>
            `;
            card.innerHTML = htmlInner;

            // Ações da Sidebar - Visíveis apenas no desktop via CSS (.acoes-sidebar logicamente, ou por default block)
            const actionsContainer = document.createElement('div');
            actionsContainer.className = 'proposta-actions acoes-sidebar';
            
            // Adicionar lógica de botões específica para sidebar
            adicionarBotoesOuStatus(actionsContainer, status, propostaId, 'sidebar');
            
            card.appendChild(actionsContainer);
            containerSidebar.appendChild(card);
        }

        // 5. Função auxiliar para gerar botões (reutilizável)
        function adicionarBotoesOuStatus(container, status, propostaId, contexto) {
            container.innerHTML = ''; // Limpar
            
            <?php if ($is_comprador): ?>
            if (status === 'pendente') {
                // Botão Aceitar
                const btnAceitar = document.createElement('button');
                btnAceitar.className = 'btn-aceitar';
                btnAceitar.textContent = 'Aceitar';
                btnAceitar.onclick = () => actionProposta('aceitar', propostaId);

                // Botão Recusar
                const btnRecusar = document.createElement('button');
                btnRecusar.className = 'btn-recusar';
                btnRecusar.textContent = 'Recusar';
                btnRecusar.onclick = () => {
                    if(confirm('Recusar proposta?')) actionProposta('recusar', propostaId);
                };

                container.appendChild(btnRecusar);
                container.appendChild(btnAceitar);
            } else {
                const div = document.createElement('div');
                div.className = `proposta-status proposta-${status}`;
                div.innerHTML = status === 'aceita' ? '<i class="fas fa-check"></i> Aceita' : '<i class="fas fa-times"></i> Recusada';
                container.appendChild(div);
            }
            <?php else: // Transportador ?>
            const div = document.createElement('div');
            div.className = `proposta-status proposta-${status}`;
            div.textContent = status === 'aceita' ? 'Aceita pelo cliente' : (status === 'recusada' ? 'Recusada' : 'Aguardando...');
            container.appendChild(div);
            <?php endif; ?>
        }

        // 6. Ação AJAX unificada
        async function actionProposta(acao, id) {
            if (!id) return;
            
            // Feedback visual em todos os botões da tela
            document.querySelectorAll('.btn-aceitar, .btn-recusar').forEach(b => {
                b.disabled = true; 
                b.textContent = '...';
            });

            try {
                const res = await fetch('responder_proposta.php', {
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({acao: acao, id: id})
                });
                const j = await res.json();
                
                if (j.success) {
                    const novoStatus = (acao === 'aceitar') ? 'aceita' : 'recusada';
                    propostasProcessadas.set(id, novoStatus);
                    
                    // Atualizar visualmente sem recarregar tudo imediatamente
                    // Atualiza chat
                    document.querySelectorAll('.acoes-chat').forEach(el => {
                       // Encontrar se esse elemento pertence ao ID certo seria ideal, 
                       // mas recarregar mensagens resolve a consistência
                    });
                    
                    <?php if ($is_comprador): ?>
                    if (acao === 'aceitar') {
                        document.getElementById('modal-sucesso-aceite').style.display = 'flex';
                    } else {
                        alert('Proposta recusada.');
                    }
                    <?php endif; ?>
                    
                    // Recarrega mensagens para atualizar status nos cards e sidebar
                    setTimeout(() => {
                        document.getElementById('chat-messages').innerHTML = ''; // Limpa para forçar redraw correto
                        ultimaMensagemId = 0; // Reset simples
                        carregarMensagens();
                    }, 500);
                    
                } else {
                    alert(j.erro || 'Erro ao processar');
                    document.querySelectorAll('.btn-aceitar, .btn-recusar').forEach(b => b.disabled = false);
                }
            } catch (e) {
                console.error(e);
                alert('Erro de conexão');
            }
        }

        // Handlers de input
        document.getElementById('send-btn').addEventListener('click', async () => {
            const input = document.getElementById('message-input');
            if (!input.value.trim()) return;
            const form = new URLSearchParams();
            form.append('conversa_id', conversaId);
            form.append('mensagem', input.value);
            try {
                await fetch('send_message.php', {method:'POST', body: form});
                input.value = '';
                carregarMensagens();
            } catch(e){}
        });
        
        document.getElementById('message-input').addEventListener('keypress', (e) => { if (e.key === 'Enter') document.getElementById('send-btn').click(); });

        // Inicialização
        carregarMensagens();
        setInterval(carregarMensagens, 3000);

        // Lógica de envio de proposta (Transportador)
        <?php if ($is_transportador): ?>
        const modalProp = document.getElementById('modal-proposta-transportador');
        document.getElementById('btn-negociar')?.addEventListener('click', () => modalProp.style.display = 'flex');
        // Também abrir modal a partir do botão grande na sidebar
        document.getElementById('btn-negociar-sidebar')?.addEventListener('click', () => modalProp.style.display = 'flex');
        document.getElementById('fechar-modal-proposta')?.addEventListener('click', () => modalProp.style.display = 'none');
        document.getElementById('cancelar-proposta')?.addEventListener('click', () => modalProp.style.display = 'none');
        
        document.getElementById('enviar-proposta')?.addEventListener('click', async () => {
            const v = document.getElementById('proposta-valor').value;
            const d = document.getElementById('proposta-data').value;
            if(!v || !d) return alert('Preencha tudo');
            
            const form = new FormData();
            form.append('conversa_id', conversaId);
            form.append('valor', v);
            form.append('data_entrega', d);
            
            await fetch('send_proposal.php', { method: 'POST', body: form });
            modalProp.style.display = 'none';
            carregarMensagens();
        });
        <?php endif; ?>

        // Fechar modal sucesso
        document.getElementById('btn-fechar-modal')?.addEventListener('click', () => document.getElementById('modal-sucesso-aceite').style.display = 'none');
    </script>
</body>
</html>