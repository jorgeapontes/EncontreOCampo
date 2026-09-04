<?php
// Interface simples de chat para transportador
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login');
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
        $outro_tipo_avaliacao = 'transportador'; // Adicionado
    } elseif (!empty($conv['transportador_id']) && $conv['transportador_id'] == $uid) {
        $outro_nome = $conv['comprador_nome'] ?: 'Comprador';
        $outro_papel = 'Comprador';
        $outro_tipo_avaliacao = 'comprador'; // Adicionado
    } else {
        $outro_nome = $conv['comprador_nome'] ?: 'Usuário';
        $outro_papel = 'Comprador';
        $outro_tipo_avaliacao = 'comprador'; // Adicionado
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
    <link rel="stylesheet" href="css/chat_interface.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="<?php echo $conversa_id ? 'tem-conversa' : ''; ?>">
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
                    <div class="flex-1">
                        <div class="nome"><i class="fas fa-user icon-spaced"></i><?php echo htmlspecialchars($outro_nome); ?></div>
                        <div class="ultima-msg">Conversa com <?php echo htmlspecialchars($outro_papel); ?></div>
                    </div>
                    <div class="sidebar-whatsapp-row">
                        <?php if (!empty($comprador_telefone)): ?>
                            <a href="https://wa.me/<?php echo $comprador_telefone; ?>" target="_blank" class="whatsapp-button"><i class="fab fa-whatsapp"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CARD DE ENDEREÇO DO COMPRADOR -->
            <?php if (!empty($comprador_endereco) && $is_transportador): ?>
                <div class="endereco-card endereco-card-box">
                    <div class="endereco-header-row">
                        <i class="fas fa-map-marker-alt endereco-icon-alerta"></i>
                        <div>
                            <strong class="endereco-titulo-compacto">Endereço do Comprador</strong>
                        </div>
                    </div>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $comprador_endereco_maps; ?>" target="_blank" class="endereco-link-sublinhado">
                        <div class="endereco-texto-truncado">
                            <?php echo htmlspecialchars(strlen($comprador_endereco) > 60 ? substr($comprador_endereco,0,57).'...' : $comprador_endereco); ?>
                        </div>
                    </a>
                    <?php if (!empty($comprador_info['cep'])): ?>
                        <div class="endereco-cep-info">CEP: <?php echo htmlspecialchars($comprador_info['cep']); ?></div>
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
                <div class="sidebar-negociacao-btn">
                    <button type="button" id="btn-negociar-sidebar">
                        <i class="fas fa-handshake"></i>
                        Propor Entrega
                    </button>
                </div>
            <?php endif; ?>

            <div id="sidebar-proposta-container">
            </div>

            
        </div>

        <div class="chat-area">
            <div class="chat-header">
                <div class="chat-header-top-row">
                    <div class="usuario-info">
                    <div class="avatar-container">
                        <img id="outro-avatar" src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Avatar" class="outro-avatar-img">
                    </div>
                    <a href="../verperfil?usuario_id=<?php echo $outro_usuario_id; ?>"
                            class="perfil-link-reset"
                            title="Ver perfil de <?php echo htmlspecialchars($outro_usuario_nome); ?>">
                        <div class="name-and-type">
                            <h3><?php echo htmlspecialchars($outro_nome); ?></h3>
                            <small><?php echo htmlspecialchars($outro_papel); ?></small>
                        </div>
                    </a>
                    </div>
                    <!-- EXIBIÇÃO DA AVALIAÇÃO MÉDIA DO OUTRO USUÁRIO -->
                    <div class="avaliacao-usuario-chat" 
                            onclick="redirectToUserReviews(<?php echo $outro_usuario_id; ?>, '<?php echo $outro_tipo_avaliacao; ?>')"
                            title="Clique para ver todas as avaliações de <?php echo htmlspecialchars($outro_nome); ?>">
                        <div class="avaliacao-mini">
                            <div class="estrela-media-chat">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="nota-media-chat">
                                <?php 
                                // Buscar a avaliação média do outro usuário
                                $sql_avaliacao_outro = "SELECT AVG(nota) as media, COUNT(*) as total 
                                                    FROM avaliacoes 
                                                    WHERE tipo = :tipo_usuario 
                                                    AND ";
                                
                                // Definir o campo correto baseado no tipo de usuário
                                if ($is_comprador) {
                                    // Se o usuário atual é comprador, o outro é transportador
                                    $tipo_avaliacao_outro = 'transportador';
                                    $sql_avaliacao_outro .= "transportador_id = :usuario_id";
                                } else {
                                    // Se o usuário atual é transportador, o outro é comprador
                                    $tipo_avaliacao_outro = 'comprador';
                                    $sql_avaliacao_outro .= "comprador_id = :usuario_id";
                                }
                                
                                $stmt_avaliacao_outro = $conn->prepare($sql_avaliacao_outro);
                                $stmt_avaliacao_outro->bindParam(':tipo_usuario', $tipo_avaliacao_outro);
                                $stmt_avaliacao_outro->bindParam(':usuario_id', $outro_usuario_id, PDO::PARAM_INT);
                                $stmt_avaliacao_outro->execute();
                                $avaliacao_outro = $stmt_avaliacao_outro->fetch(PDO::FETCH_ASSOC);
                                
                                $media_outro = $avaliacao_outro['media'] ? round($avaliacao_outro['media'], 1) : 0;
                                $total_avaliacoes_outro = $avaliacao_outro['total'] ?? 0;
                                
                                echo number_format($media_outro, 1, ',', '.');
                                ?>
                            </div>
                            <div class="total-avaliacoes-chat">
                                (<?php echo $total_avaliacoes_outro; ?>)
                            </div>
                        </div>
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
                <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
                <input type="text" id="message-input" placeholder="Digite sua mensagem..." autocomplete="off">
                <button type="button" id="send-btn" class="btn-send"><i class="fas fa-paper-plane"></i><span>Enviar</span></button>
            </div>
        </div>
    </div>

    <?php if ($is_transportador): ?>
    <div id="modal-proposta-transportador">
        <div class="modal-proposta-content">
            <div class="modal-proposta-header">
                <h3 class="modal-proposta-title"><i class="fas fa-handshake"></i> Propor Entrega</h3>
                <button id="fechar-modal-proposta" class="modal-proposta-close">&times;</button>
            </div>
            <div>
                <label>Valor do frete (R$)</label>
                <input type="number" id="proposta-valor" step="0.01" min="0" class="modal-proposta-input" />
            </div>
            <div>
                <label>Data limite de entrega</label>
                <input type="date" id="proposta-data" class="modal-proposta-input" />
            </div>
            <div class="modal-proposta-footer">
                <button id="enviar-proposta" class="btn-proposta-enviar">Enviar Proposta</button>
                <button id="cancelar-proposta" class="btn-proposta-cancelar">Cancelar</button>
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
                    <h3 class="modal-titulo-compacto">Proposta Aceita!</h3>
                    <p class="modal-subtitulo">Você aceitou a proposta de entrega.</p>
                </div>
            </div>
            <div class="modal-corpo-texto">
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
                modal.className = 'avatar-modal-overlay';
                const img = document.createElement('img');
                img.className = 'avatar-modal-img';
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
            window.location.href = '../transportador/meus_chats';
        }
        
        // Variáveis globais
        const conversaId = <?php echo (int)$conversa_id; ?>;
        const usuarioId = <?php echo (int)$_SESSION['usuario_id']; ?>;
        const usuarioTipo = '<?php echo $_SESSION['usuario_tipo']; ?>';
        let ultimaMensagemId = 0;
        let ultimaPropostaIdRenderizada = 0; // Para controlar a sidebar
        const propostasProcessadas = new Map();

        // Escapa texto antes de inserir via innerHTML (proteção contra XSS).
        // Usada em qualquer mensagem exibida como HTML, não só texto puro.
        function escapeHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto ?? '';
            return div.innerHTML;
        }

        // 1. Carregar mensagens
        async function carregarMensagens() {
            try {
                const res = await fetch(`get_messages?conversa_id=${conversaId}&ultimo_id=${ultimaMensagemId}`);
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
                                img.className = 'mensagem-imagem';
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
            
            let htmlInner = '<strong><i class="fas fa-handshake icon-spaced-sm"></i>Proposta de Entrega</strong>';
            htmlInner += '<div class="mt-8">';

            let valorText = 'Valor não especificado';
            if (dados && dados.valor) valorText = 'R$ ' + parseFloat(dados.valor).toFixed(2).replace('.', ',');
            htmlInner += `<div class="mb-5"><span class="texto-secundario">Valor:</span> <strong class="texto-destaque">${valorText}</strong></div>`;

            if (dados && dados.prazo) htmlInner += `<div class="mb-5"><span class="texto-secundario">Prazo:</span> <span class="texto-destaque">${dados.prazo}</span></div>`;
            if (propostaId) htmlInner += `<div><span class="texto-secundario fonte-pequena">ID: ${propostaId}</span></div>`;
            htmlInner += '</div>';
            
            card.innerHTML = htmlInner;

            // Identificar Status
            let status = 'pendente';
            if (propostaId) {
                if (propostasProcessadas.has(propostaId)) {
                    status = propostasProcessadas.get(propostaId);
                } else {
                    try {
                        const res = await fetch('get_proposta_status?id=' + propostaId);
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
            titulo.className = 'sidebar-proposta-titulo';
            titulo.textContent = "Última Proposta";
            containerSidebar.appendChild(titulo);

            // Criar card idêntico
            const card = document.createElement('div');
            card.className = 'proposta-card';
            
            let valorText = dados.valor ? 'R$ ' + parseFloat(dados.valor).toFixed(2).replace('.', ',') : 'N/A';
            
            let htmlInner = `
                <strong><i class="fas fa-handshake"></i> Entrega</strong>
                <div class="sidebar-proposta-detalhes">
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
                const res = await fetch('responder_proposta', {
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
                await fetch('send_message', {method:'POST', body: form});
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
            
            await fetch('send_proposal', { method: 'POST', body: form });
            modalProp.style.display = 'none';
            carregarMensagens();
        });
        <?php endif; ?>

        // Fechar modal sucesso
        document.getElementById('btn-fechar-modal')?.addEventListener('click', () => document.getElementById('modal-sucesso-aceite').style.display = 'none');
        
        // Redirecionar para minhas compras
        document.getElementById('btn-ver-compras')?.addEventListener('click', () => {
            window.location.href = '../comprador/dashboard';
        });
    
    // Função para redirecionar para avaliações do usuário - CORRIGIDA
function redirectToUserReviews(usuarioId, tipoUsuario) {

    const tipoAvaliacao = tipoUsuario === 'transportador' ? 'transportador' : 'comprador';
    
    // Verificar se o usuário está logado (já está logado pois está no chat)
    const isLoggedIn = <?php echo isset($_SESSION['usuario_id']) ? 'true' : 'false'; ?>;
    
    if (isLoggedIn) {
        // Usuário logado: redireciona diretamente para a página de avaliações
        window.location.href = '../avaliacoes?tipo=' + tipoAvaliacao + '&id=' + usuarioId;
    } else {
        // Usuário não logado (fallback - não deveria acontecer pois está no chat)
        const redirectUrl = encodeURIComponent('../avaliacoes.php?tipo=' + tipoAvaliacao + '&id=' + usuarioId);
        window.location.href = '../login?redirect=' + redirectUrl;
    }
}
    </script>
</body>
</html>