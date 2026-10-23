<?php
// Interface simples de chat para transportador
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../chat/chat_config.php';

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
    <meta name="csrf-token" content="<?php echo htmlspecialchars(chatCsrfToken()); ?>">
    <title>Chat - Transportador</title>
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="../chat/css/chat.css?v=<?php echo filemtime(__DIR__ . '/../chat/css/chat.css'); ?>">
    <link rel="stylesheet" href="../chat/css/chat_comum.css?v=<?php echo filemtime(__DIR__ . '/../chat/css/chat_comum.css'); ?>">
    <link rel="stylesheet" href="css/chat_interface.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="<?php echo $conversa_id ? 'tem-conversa' : ''; ?>">
    <div class="chat-container">
        <div class="chat-sidebar">
            <!-- Produto no topo, alinhado com o cabeçalho da conversa -->
            <div class="produto-info-sidebar">
                <img src="<?php echo htmlspecialchars($conv['produto_imagem'] ?: '../../img/placeholder.png'); ?>" alt="Produto">
                <div class="info">
                    <small class="produto-contexto"><i class="fas fa-truck" aria-hidden="true"></i> Entrega de</small>
                    <h3><?php echo htmlspecialchars($conv['produto_nome']); ?></h3>
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
                            title="Ver perfil de <?php echo htmlspecialchars($outro_nome); ?>">
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
                    <button type="button" class="btn-attach" id="btn-attach-image" title="Enviar imagem" aria-label="Enviar imagem"><i class="fas fa-camera" aria-hidden="true"></i></button>
                    <?php if ($is_transportador): ?>
                        <button type="button" class="btn-negociar" id="btn-negociar" title="Propor entrega" aria-label="Propor entrega"><i class="fas fa-handshake" aria-hidden="true"></i></button>
                    <?php endif; ?>
                </div>
                <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" multiple class="hidden">
                <textarea id="message-input" rows="1" maxlength="5000" placeholder="Digite sua mensagem..." autocomplete="off" aria-label="Mensagem"></textarea>
                <button type="button" id="send-btn" class="btn-send" aria-label="Enviar mensagem"><i class="fas fa-paper-plane" aria-hidden="true"></i><span>Enviar</span></button>
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

    <script src="../chat/js/chat_comum.js?v=<?php echo filemtime(__DIR__ . '/../chat/js/chat_comum.js'); ?>"></script>
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
        const ehComprador = <?php echo $is_comprador ? 'true' : 'false'; ?>;
        const ehTransportador = <?php echo $is_transportador ? 'true' : 'false'; ?>;
        const nomeOutro = <?php echo json_encode($outro_nome, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
        let ultimaPropostaIdRenderizada = 0; // Para controlar a sidebar
        // Status conhecido de cada proposta de frete (id em propostas_transportadores)
        const statusPropostas = new Map();

        // 1. Extrair dados (mensagens antigas sem dados_json)
        function extrairDadosDoTexto(texto) {
            const dados = {};
            if (!texto) return dados;
            let textoLimpo = texto.replace(/\*/g, '').replace(/ /g, ' ').replace(/\s+/g, ' ').trim();
            const idMatch = textoLimpo.match(/\bID\b\s*[:\-\s]?\s*(\d+)/i);
            if (idMatch) dados.propostas_transportador_id = parseInt(idMatch[1]);
            const valorMatch = textoLimpo.match(/\bValor\b\s*[:\-\s]?\s*(?:R\$\s*)?([0-9.,]+)/i);
            if (valorMatch) dados.valor = parseFloat(valorMatch[1].replace(/\./g, '').replace(/,/g, '.'));
            const prazoMatch = textoLimpo.match(/\bPrazo\b\s*[:\-\s]?\s*([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}\/[0-9]{2}\/[0-9]{4})/i);
            if (prazoMatch) dados.prazo = prazoMatch[1];
            return dados;
        }

        function dadosDaProposta(msg) {
            let dados = null;
            if (msg.dados_json) {
                try { dados = JSON.parse(msg.dados_json); } catch (e) { /* JSON inválido */ }
            }
            if (!dados || !dados.propostas_transportador_id) {
                dados = Object.assign(extrairDadosDoTexto(msg.mensagem), dados || {});
            }
            return dados;
        }

        function formatarValor(valor) {
            return valor ? 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',') : null;
        }

        // 2. Conteúdo dos tipos próprios deste chat. Só mensagens do tipo
        // 'proposta' viram card (antes qualquer texto com "ID" em maiúsculas virava).
        function renderizarConteudo(msg) {
            if (msg.tipo === 'aceite') {
                const notif = document.createElement('div');
                notif.className = 'proposta-status proposta-aceita';
                notif.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i> ';
                notif.appendChild(document.createTextNode(msg.mensagem));
                return notif;
            }
            if (msg.tipo !== 'proposta') return null;

            const dados = dadosDaProposta(msg);
            const propostaId = dados.propostas_transportador_id || null;
            let status = 'pendente';
            if (propostaId) {
                status = statusPropostas.get(propostaId) || msg.proposta_status || 'pendente';
                statusPropostas.set(propostaId, status);
            }

            const content = document.createElement('div');
            content.appendChild(montarCardProposta(dados, propostaId, status, 'chat'));

            // ATUALIZAÇÃO DA SIDEBAR
            // Se esta for uma proposta válida e for mais recente do que a que temos na sidebar
            if (propostaId && propostaId >= ultimaPropostaIdRenderizada) {
                ultimaPropostaIdRenderizada = propostaId;
                atualizarSidebarProposta(dados, propostaId, status);
            }
            return content;
        }

        // 3. Card de proposta (no chat e na sidebar)
        function montarCardProposta(dados, propostaId, status, contexto) {
            const card = document.createElement('div');
            card.className = 'proposta-card';

            const valorText = formatarValor(dados.valor) || 'Valor não especificado';
            const e = ChatComum.escapeHtml;
            let htmlInner;
            if (contexto === 'sidebar') {
                htmlInner = `
                    <strong><i class="fas fa-handshake" aria-hidden="true"></i> Entrega</strong>
                    <div class="sidebar-proposta-detalhes">
                        <div>Valor: <strong>${e(formatarValor(dados.valor) || 'N/A')}</strong></div>
                        <div>Prazo: ${e(dados.prazo || '--')}</div>
                    </div>`;
            } else {
                htmlInner = '<strong><i class="fas fa-handshake icon-spaced-sm" aria-hidden="true"></i>Proposta de Entrega</strong><div class="mt-8">';
                htmlInner += `<div class="mb-5"><span class="texto-secundario">Valor:</span> <strong class="texto-destaque">${e(valorText)}</strong></div>`;
                if (dados.prazo) htmlInner += `<div class="mb-5"><span class="texto-secundario">Prazo:</span> <span class="texto-destaque">${e(dados.prazo)}</span></div>`;
                if (propostaId) htmlInner += `<div><span class="texto-secundario fonte-pequena">ID: ${e(propostaId)}</span></div>`;
                htmlInner += '</div>';
            }
            card.innerHTML = htmlInner;

            // Ações: .acoes-chat aparece no mobile e .acoes-sidebar no desktop (via CSS)
            const actionsContainer = document.createElement('div');
            actionsContainer.className = 'proposta-actions ' + (contexto === 'sidebar' ? 'acoes-sidebar' : 'acoes-chat');
            if (propostaId) actionsContainer.dataset.propostaId = propostaId;
            adicionarBotoesOuStatus(actionsContainer, status, propostaId);
            card.appendChild(actionsContainer);
            return card;
        }

        // 4. Atualizar a sidebar
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

            containerSidebar.appendChild(montarCardProposta(dados, propostaId, status, 'sidebar'));
        }

        // 5. Botões (comprador com proposta pendente) ou o status
        function adicionarBotoesOuStatus(container, status, propostaId) {
            container.innerHTML = ''; // Limpar

            if (ehComprador) {
                if (status === 'pendente') {
                    // Botão Aceitar
                    const btnAceitar = document.createElement('button');
                    btnAceitar.type = 'button';
                    btnAceitar.className = 'btn-aceitar';
                    btnAceitar.textContent = 'Aceitar';
                    btnAceitar.onclick = () => actionProposta('aceitar', propostaId);

                    // Botão Recusar
                    const btnRecusar = document.createElement('button');
                    btnRecusar.type = 'button';
                    btnRecusar.className = 'btn-recusar';
                    btnRecusar.textContent = 'Recusar';
                    btnRecusar.onclick = async () => {
                        const ok = await ChatComum.confirmar('Tem certeza que deseja recusar esta proposta de entrega?', {
                            titulo: 'Recusar proposta', textoConfirmar: 'Sim, recusar', textoCancelar: 'Não', perigo: true
                        });
                        if (ok) actionProposta('recusar', propostaId);
                    };

                    container.appendChild(btnRecusar);
                    container.appendChild(btnAceitar);
                } else {
                    const div = document.createElement('div');
                    div.className = `proposta-status proposta-${status}`;
                    div.innerHTML = status === 'aceita' ? '<i class="fas fa-check"></i> Aceita' : '<i class="fas fa-times"></i> Recusada';
                    container.appendChild(div);
                }
            } else {
                // Transportador
                const div = document.createElement('div');
                div.className = `proposta-status proposta-${status}`;
                div.textContent = status === 'aceita' ? 'Aceita pelo cliente' : (status === 'recusada' ? 'Recusada' : 'Aguardando...');
                container.appendChild(div);
            }
        }

        // Atualiza no lugar todos os cards (chat e sidebar) de uma proposta
        function aplicarStatus(propostaId, status) {
            statusPropostas.set(propostaId, status);
            document.querySelectorAll(`.proposta-actions[data-proposta-id="${propostaId}"]`).forEach(container => {
                adicionarBotoesOuStatus(container, status, propostaId);
            });
        }

        // 6. Ação AJAX unificada
        async function actionProposta(acao, id) {
            if (!id) return;

            // Feedback visual nos botões desta proposta
            document.querySelectorAll(`.proposta-actions[data-proposta-id="${id}"] button`).forEach(b => {
                b.disabled = true;
                b.textContent = '...';
            });

            try {
                await ChatComum.postar('responder_proposta', JSON.stringify({acao: acao, id: id}), { json: true });

                aplicarStatus(id, acao === 'aceitar' ? 'aceita' : 'recusada');
                if (acao === 'aceitar') {
                    document.getElementById('modal-sucesso-aceite').style.display = 'flex';
                } else {
                    ChatComum.toast('Proposta recusada.', 'info');
                }
                // Busca a mensagem automática da resposta
                conversa.atualizarAgora();
            } catch (e) {
                ChatComum.toast(e.message || 'Erro ao processar', 'error');
                aplicarStatus(id, statusPropostas.get(id) || 'pendente');
            }
        }

        // Mensagens
        const conversa = new ChatComum.Conversa({
            container: document.getElementById('chat-messages'),
            usuarioId,
            nomeOutro,
            urlMensagens: params => `get_messages?${params}`,
            paramsExtras: () => {
                const pendentes = [...statusPropostas].filter(([, s]) => s === 'pendente').map(([id]) => id);
                return pendentes.length
                    ? { conversa_id: conversaId, status_propostas: pendentes.join(',') }
                    : { conversa_id: conversaId };
            },
            onResposta: dados => {
                Object.entries(dados.status_propostas || {}).forEach(([id, status]) => {
                    if (statusPropostas.get(Number(id)) !== status) aplicarStatus(Number(id), status);
                });
            },
            renderizarConteudo,
            enviarTexto: texto => ChatComum.postar('send_message', new URLSearchParams({ conversa_id: conversaId, mensagem: texto })),
            enviarImagem: (arquivo, onProgresso) => {
                const formData = new FormData();
                formData.append('imagem', arquivo, arquivo.name || 'foto.jpg');
                formData.append('conversa_id', conversaId);
                return ChatComum.postar('send_message', formData, { onProgresso });
            },
        });

        conversa.conectarEntrada({
            campo: document.getElementById('message-input'),
            botaoEnviar: document.getElementById('send-btn'),
            botaoAnexo: document.getElementById('btn-attach-image'),
            inputArquivo: document.getElementById('image-input'),
        });
        ChatComum.criarBotaoNotificacoes(document.querySelector('.chat-header .btn-voltar'));
        conversa.iniciar();

        // Lógica de envio de proposta (Transportador)
        if (ehTransportador) {
            const modalProp = document.getElementById('modal-proposta-transportador');
            const abrirModalProposta = () => {
                // Prazo de entrega não pode ser no passado
                const hoje = new Date();
                document.getElementById('proposta-data').min =
                    `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(hoje.getDate()).padStart(2, '0')}`;
                modalProp.style.display = 'flex';
            };
            document.getElementById('btn-negociar')?.addEventListener('click', abrirModalProposta);
            // Também abrir modal a partir do botão grande na sidebar
            document.getElementById('btn-negociar-sidebar')?.addEventListener('click', abrirModalProposta);
            document.getElementById('fechar-modal-proposta')?.addEventListener('click', () => modalProp.style.display = 'none');
            document.getElementById('cancelar-proposta')?.addEventListener('click', () => modalProp.style.display = 'none');

            document.getElementById('enviar-proposta')?.addEventListener('click', async function () {
                const v = document.getElementById('proposta-valor').value;
                const d = document.getElementById('proposta-data').value;
                if (!v || parseFloat(v) <= 0 || !d) {
                    ChatComum.toast('Preencha o valor do frete e a data limite de entrega.', 'warning');
                    return;
                }

                const form = new FormData();
                form.append('conversa_id', conversaId);
                form.append('valor', v);
                form.append('data_entrega', d);

                this.disabled = true;
                try {
                    await ChatComum.postar('send_proposal', form);
                    modalProp.style.display = 'none';
                    ChatComum.toast('Proposta enviada!', 'success');
                    conversa.atualizarAgora();
                } catch (e) {
                    ChatComum.toast(e.message || 'Não foi possível enviar a proposta.', 'error');
                } finally {
                    this.disabled = false;
                }
            });
        }

        // Fechar modal sucesso
        document.getElementById('btn-fechar-modal')?.addEventListener('click', () => document.getElementById('modal-sucesso-aceite').style.display = 'none');

        // Redirecionar para minhas compras
        document.getElementById('btn-ver-compras')?.addEventListener('click', () => {
            window.location.href = '../comprador/dashboard';
        });

    // Função para redirecionar para avaliações do usuário
    // (a página do chat exige login, então vai direto para as avaliações)
    function redirectToUserReviews(usuarioId, tipoUsuario) {
        const tipoAvaliacao = tipoUsuario === 'transportador' ? 'transportador' : 'comprador';
        window.location.href = '../avaliacoes?tipo=' + tipoAvaliacao + '&id=' + usuarioId;
    }
    </script>
</body>
</html>