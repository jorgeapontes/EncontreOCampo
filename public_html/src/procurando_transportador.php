<?php
// src/procurando_transportador.php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissions.php';

// Permitir acesso a usuários logados como comprador ou vendedor (vendedores podem também comprar)
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_tipo'] ?? '', ['comprador', 'vendedor'])) {
    header("Location: login?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$db = $database->getConnection();

// Buscar conversas do comprador que têm transportador associado
try {
    $sql = "SELECT 
                cc.id AS conversa_id,
                cc.produto_id,
                cc.proposta_id,
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                cc.transportador_id,
                (SELECT u.nome FROM usuarios u WHERE u.id = cc.transportador_id) AS transportador_nome,
                cc.favorito_comprador AS arquivado,
                pr.quantidade_proposta,
                pr.valor_frete_final,
                pr.data_entrega_estimada,
                pr.status AS proposta_status,
                (SELECT COUNT(*) FROM chat_mensagens cm WHERE cm.conversa_id = cc.id AND cm.remetente_id != :usuario_id AND cm.lida = 0) AS mensagens_nao_lidas
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            LEFT JOIN propostas pr ON pr.ID = cc.proposta_id
            WHERE cc.comprador_id = :usuario_id
            AND cc.transportador_id IS NOT NULL
            AND cc.status = 'ativo'
            AND cc.comprador_excluiu = 0
            ORDER BY cc.ultima_mensagem_data DESC";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao buscar conversas comprador-transportador: ' . $e->getMessage());
    $conversas = [];
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Chats com Transportadores - Encontre o Campo</title>
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/procurando_transportador.css">
    <link rel="stylesheet" href="css/navbar.css">
</head>
<body>
    <?php
    $active_nav = '';
    $mostrar_sino = false;
    $nav_items = [
        ['key' => 'painel', 'label' => 'Painel',     'href' => 'comprador/dashboard'],
        ['key' => 'perfil', 'label' => 'Meu Perfil', 'href' => 'comprador/perfil'],
        ['key' => 'chats',  'label' => 'Chats',      'href' => 'comprador/meus_chats'],
    ];
    require __DIR__ . '/includes/navbar.php';
    ?>

    <main class="main-content">

        <div class="conversas-container">
            <div class="conversas-header">
                <h2>Conversas com Transportadores</h2>
            </div>

            <div class="conversas-list" id="conversasList">
                <!-- As conversas serão carregadas dinamicamente aqui -->
                <div class="loading-conversas" id="loadingConversas">
                    <i class="fas fa-spinner fa-spin"></i> Carregando conversas...
                </div>
            </div>
        </div>
    </main>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        // ============== SISTEMA DINÂMICO DE CONVERSAS ==============
        let conversasCache = new Map();
        let ultimaVerificacao = Math.floor(Date.now() / 1000);
        let estaVerificando = false;
        let intervaloAtualizacao = null;
        const TEMPO_POLLING = 8000; // 8 segundos
        const abaAtiva = 'ativos'; // Pode ser dinâmico se adicionar abas

        // Inicializar sistema dinâmico
        function iniciarSistemaDinamico() {
            carregarConversasIniciais();
            iniciarPolling();
            gerenciarEventosJanela();
        }

        // Carregar conversas iniciais via AJAX
        function carregarConversasIniciais() {
            const loadingEl = document.getElementById('loadingConversas');
            const conversasList = document.getElementById('conversasList');
            
            fetch(`carregar_conversas_transportador_ajax?aba=${abaAtiva}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    mostrarErroCarregamento(data.error);
                    return;
                }
                
                if (loadingEl) loadingEl.remove();
                
                if (data.conversas && data.conversas.length > 0) {
                    data.conversas.forEach(conversa => {
                        renderizarConversa(conversa);
                        conversasCache.set(conversa.conversa_id, conversa);
                    });
                } else {
                    mostrarEstadoVazio();
                }
                
                if (data.timestamp) {
                    ultimaVerificacao = data.timestamp;
                }
            })
            .catch(error => {
                console.error('Erro ao carregar conversas:', error);
                mostrarErroCarregamento('Erro ao carregar conversas');
            });
        }

        // Renderizar uma conversa individual
        function renderizarConversa(conversa) {
            const conversasList = document.getElementById('conversasList');
            
            // Verificar se já existe
            const existingCard = document.getElementById(`conversa-${conversa.conversa_id}`);
            if (existingCard) {
                atualizarConversaExistente(existingCard, conversa);
                return;
            }
            
            // Criar novo card
            const card = document.createElement('div');
            card.className = `conversa-card ${conversa.mensagens_nao_lidas > 0 ? 'nao-lida' : ''} nova`;
            card.id = `conversa-${conversa.conversa_id}`;
            card.dataset.tipo = conversa.mensagens_nao_lidas > 0 ? 'nao-lida' : 'lida';
            card.dataset.conversaId = conversa.conversa_id;
            card.dataset.ultimaData = conversa.ultima_mensagem_data;
            
            // Corrigir caminho da imagem
            const imagemProduto = corrigirCaminhoImagem(conversa.produto_imagem);
            const chatUrl = `chat_transportador/chat_interface.php?conversa_id=${conversa.conversa_id}`;
            
            const dataFormatada = conversa.ultima_mensagem_data ? 
                formatarData(conversa.ultima_mensagem_data) : '';
            
            // Construir HTML do card
            card.innerHTML = `
                <a href="${chatUrl}" class="conversa-link-content">
                    <div class="produto-thumb">
                        <img src="${escapeHtml(imagemProduto)}" 
                             alt="${escapeHtml(conversa.produto_nome)}"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <i class="fas fa-image placeholder-icon"></i>
                    </div>
                    <div class="conversa-info">
                        <div class="conversa-linha-topo">
                            <div class="produto-nome-principal">
                                ${escapeHtml(conversa.produto_nome)}
                                ${conversa.proposta_id ?
                                    `<span class="badge-proposta">Proposta #${conversa.proposta_id}</span>` :
                                    `<span class="badge-proposta badge-proposta-generica">Chat #${conversa.conversa_id}</span>`
                                }
                                ${conversa.mensagens_nao_lidas > 0 ? 
                                    `<span class="badge-novo">${conversa.mensagens_nao_lidas} nova${conversa.mensagens_nao_lidas > 1 ? 's' : ''}</span>` : 
                                    ''
                                }
                            </div>
                            <div class="conversa-data">${dataFormatada}</div>
                        </div>
                        <div class="transportador-info">
                            <strong>Transportador:</strong> ${escapeHtml(conversa.transportador_nome || 'Transportador')}
                        </div>
                        ${(conversa.valor_frete_final || conversa.data_entrega_estimada) ?
                            `<div class="proposta-detalhes">
                                <i class="fas fa-truck"></i> Frete negociado:
                                ${conversa.valor_frete_final ? `<strong>R$ ${Number(conversa.valor_frete_final).toFixed(2).replace('.', ',')}</strong>` : ''}
                                ${conversa.data_entrega_estimada ? ` &middot; Prazo: <strong>${formatarDataSimples(conversa.data_entrega_estimada)}</strong>` : ''}
                            </div>` :
                            ''
                        }
                        ${conversa.ultima_mensagem ?
                            `<div class="ultima-mensagem">
                                <i class="fas fa-comment"></i> ${tratarUltimaMensagem(conversa.ultima_mensagem)}
                            </div>` :
                            ''
                        }
                    </div>
                </a>
                <div class="conversa-actions">
                    <a href="${chatUrl}" class="btn-chat">
                        <i class="fas fa-comments"></i> Abrir Chat
                    </a>
                </div>
            `;
            
            // Inserir na lista em ordem cronológica
            inserirConversaNaOrdemCorreta(card, conversa);
            
            // Remover classe de nova após animação
            setTimeout(() => {
                card.classList.remove('nova');
            }, 2000);
            
            // Adicionar ao cache
            conversasCache.set(conversa.conversa_id, conversa);
        }

        // Inserir conversa na ordem correta (mais recente primeiro)
        function inserirConversaNaOrdemCorreta(card, conversaData) {
            const conversasList = document.getElementById('conversasList');
            const cards = conversasList.querySelectorAll('.conversa-card');
            
            if (cards.length === 0) {
                conversasList.appendChild(card);
                return;
            }
            
            const novaData = new Date(conversaData.ultima_mensagem_data);
            let inserido = false;
            
            for (let i = 0; i < cards.length; i++) {
                const cardExistente = cards[i];
                const dataExistente = new Date(cardExistente.dataset.ultimaData || 0);
                
                if (novaData > dataExistente) {
                    conversasList.insertBefore(card, cardExistente);
                    inserido = true;
                    break;
                }
            }
            
            if (!inserido) {
                conversasList.appendChild(card);
            }
            
            // Remover estado vazio se existir
            const emptyState = conversasList.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
            
            // Remover loading se existir
            const loadingEl = conversasList.querySelector('.loading-conversas');
            if (loadingEl) {
                loadingEl.remove();
            }
        }

        // Atualizar conversa existente
        function atualizarConversaExistente(card, conversa) {
            // Atualizar badge de mensagens não lidas
            const badgeNovo = card.querySelector('.badge-novo');
            const produtoNomeDiv = card.querySelector('.produto-nome-principal');
            
            if (conversa.mensagens_nao_lidas > 0) {
                card.classList.add('nao-lida');
                card.dataset.tipo = 'nao-lida';
                
                if (!badgeNovo && produtoNomeDiv) {
                    const novoBadge = document.createElement('span');
                    novoBadge.className = 'badge-novo';
                    novoBadge.textContent = `${conversa.mensagens_nao_lidas} nova${conversa.mensagens_nao_lidas > 1 ? 's' : ''}`;
                    produtoNomeDiv.appendChild(novoBadge);
                    
                    novoBadge.style.animation = 'pulse 1s ease-in-out';
                    setTimeout(() => {
                        novoBadge.style.animation = '';
                    }, 1000);
                    
                } else if (badgeNovo) {
                    badgeNovo.textContent = `${conversa.mensagens_nao_lidas} nova${conversa.mensagens_nao_lidas > 1 ? 's' : ''}`;
                    badgeNovo.style.animation = 'pulse 1s ease-in-out';
                    setTimeout(() => {
                        badgeNovo.style.animation = '';
                    }, 1000);
                }
            } else {
                card.classList.remove('nao-lida');
                card.dataset.tipo = 'lida';
                if (badgeNovo) badgeNovo.remove();
            }
            
            // Atualizar última mensagem
            const ultimaMsgElement = card.querySelector('.ultima-mensagem');
            if (ultimaMsgElement && conversa.ultima_mensagem) {
                ultimaMsgElement.innerHTML = `<i class="fas fa-comment"></i> ${tratarUltimaMensagem(conversa.ultima_mensagem)}`;
            }
            
            // Atualizar data da última mensagem
            const dataElement = card.querySelector('.conversa-data');
            if (dataElement && conversa.ultima_mensagem_data) {
                dataElement.textContent = formatarData(conversa.ultima_mensagem_data);
            }
            
            // Atualizar cache
            conversasCache.set(conversa.conversa_id, conversa);
        }

        // Remover conversa da lista
        function removerConversa(conversaId) {
            const card = document.getElementById(`conversa-${conversaId}`);
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'translateX(-100%)';
                card.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    if (card.parentNode) {
                        card.remove();
                        conversasCache.delete(conversaId);
                        
                        // Verificar se a lista ficou vazia
                        const conversasList = document.getElementById('conversasList');
                        if (conversasList.children.length === 0) {
                            mostrarEstadoVazio();
                        }
                    }
                }, 300);
            }
        }

        // Função de polling para atualizações
        function iniciarPolling() {
            intervaloAtualizacao = setInterval(verificarAtualizacoes, TEMPO_POLLING);
        }

        function verificarAtualizacoes() {
            if (estaVerificando) return;
            
            estaVerificando = true;
            
            fetch(`atualizar_transportador_ajax?aba=${abaAtiva}&ultima_verificacao=${ultimaVerificacao}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (!data || data.error) {
                    if (data && data.error) {
                        console.error('Erro na resposta:', data.error);
                    }
                    return;
                }
                
                ultimaVerificacao = data.timestamp || Math.floor(Date.now() / 1000);
                
                if (data.atualizado) {
                    // 1. Processar novas conversas
                    if (data.novas_conversas && Array.isArray(data.novas_conversas)) {
                        data.novas_conversas.forEach(conversa => {
                            if (conversa && conversa.conversa_id) {
                                renderizarConversa(conversa);
                            }
                        });
                        
                        if (data.novas_conversas.length > 0) {
                            mostrarNotificacao(`Nova${data.novas_conversas.length > 1 ? 's' : ''} conversa${data.novas_conversas.length > 1 ? 's' : ''} com transportador`);
                        }
                    }
                    
                    // 2. Processar conversas removidas
                    if (data.conversas_removidas && Array.isArray(data.conversas_removidas)) {
                        data.conversas_removidas.forEach(conv => {
                            if (conv && conv.conversa_id) {
                                removerConversa(conv.conversa_id);
                            }
                        });
                    }
                    
                    // 3. Atualizar mensagens não lidas
                    if (data.contadores && Array.isArray(data.contadores)) {
                        data.contadores.forEach(contador => {
                            if (contador && contador.conversa_id) {
                                const conversa = conversasCache.get(parseInt(contador.conversa_id));
                                if (conversa) {
                                    conversa.mensagens_nao_lidas = parseInt(contador.nao_lidas) || 0;
                                    const card = document.getElementById(`conversa-${contador.conversa_id}`);
                                    if (card) {
                                        atualizarConversaExistente(card, conversa);
                                    }
                                }
                            }
                        });
                    }
                    
                    // 4. Atualizar últimas mensagens
                    if (data.ultimas_mensagens && Array.isArray(data.ultimas_mensagens)) {
                        data.ultimas_mensagens.forEach(msg => {
                            if (msg && msg.conversa_id) {
                                const conversa = conversasCache.get(parseInt(msg.conversa_id));
                                if (conversa) {
                                    conversa.ultima_mensagem = msg.ultima_mensagem;
                                    conversa.ultima_mensagem_data = msg.ultima_mensagem_data;
                                    const card = document.getElementById(`conversa-${msg.conversa_id}`);
                                    if (card) {
                                        atualizarConversaExistente(card, conversa);
                                    }
                                }
                            }
                        });
                    }
                    
                    // 5. Mostrar notificação de novas mensagens
                    if (data.novas_mensagens && data.novas_mensagens.length > 0) {
                        mostrarNotificacaoNovasMensagens(data.novas_mensagens.length);
                    }
                }
            })
            .catch(error => {
                console.error('Erro na verificação:', error);
                setTimeout(verificarAtualizacoes, TEMPO_POLLING * 2);
            })
            .finally(() => {
                estaVerificando = false;
            });
        }

        // Funções auxiliares
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatarData(dataStr) {
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR') + ' ' + 
                   data.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        }

        // Para campos que são apenas DATE (sem hora), como data_entrega_estimada.
        // new Date("YYYY-MM-DD") é interpretado como UTC meia-noite; ao converter
        // para o fuso local (ex: Brasil, UTC-3) a data "volta" um dia. Por isso,
        // aqui extraímos ano/mês/dia direto da string, sem passar por Date/UTC.
        function formatarDataSimples(dataStr) {
            if (!dataStr) return '';
            const partes = String(dataStr).split('T')[0].split('-');
            if (partes.length !== 3) return dataStr;
            const [ano, mes, dia] = partes;
            return `${dia}/${mes}/${ano}`;
        }

        function tratarUltimaMensagem(mensagem) {
            if (mensagem.includes('[Imagem]')) {
                return '[Imagem]';
            }
            if (mensagem.length > 60) {
                return escapeHtml(mensagem.substring(0, 57)) + '...';
            }
            return escapeHtml(mensagem);
        }

        function corrigirCaminhoImagem(caminho) {
            if (!caminho) return 'img/placeholder.png';
            
            // URLs completos
            if (caminho.startsWith('http') || caminho.startsWith('//')) {
                return caminho;
            }
            
            // Corrigir caminhos do banco: "../uploads/" -> "uploads/"
            if (caminho.startsWith('../uploads/')) {
                return caminho.substring(3);
            }
            
            // Se já estiver correto ou for outro formato
            return caminho;
        }

        function mostrarEstadoVazio() {
            const conversasList = document.getElementById('conversasList');
            if (!conversasList) return;
            
            conversasList.innerHTML = '';
            
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state';
            emptyState.innerHTML = `
                <i class="fas fa-comments"></i>
                <h3>Nenhuma conversa com transportador encontrada</h3>
                <p>Quando transportadores entrarem em contato, as conversas aparecerão aqui.</p>
            `;
            
            conversasList.appendChild(emptyState);
        }

        function mostrarErroCarregamento(mensagem) {
            const conversasList = document.getElementById('conversasList');
            const loadingEl = document.getElementById('loadingConversas');
            
            if (loadingEl) loadingEl.remove();
            
            conversasList.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Erro ao carregar conversas</h3>
                    <p>${mensagem}</p>
                    <button onclick="carregarConversasIniciais()" class="btn-chat btn-tentar-novamente">
                        <i class="fas fa-redo"></i> Tentar novamente
                    </button>
                </div>
            `;
        }

        function mostrarNotificacao(mensagem) {
            const notif = document.createElement('div');
            notif.className = 'notificacao-flutuante';
            notif.innerHTML = `
                <i class="fas fa-comment-alt"></i>
                <span>${mensagem}</span>
                <button onclick="this.parentElement.remove()">&times;</button>
            `;
            
            notif.style.cssText = `
                position: fixed;
                top: 100px;
                right: 20px;
                background: #2E7D32;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 10px;
                z-index: 9999;
                animation: slideInRight 0.3s ease-out;
            `;
            
            document.body.appendChild(notif);
            
            setTimeout(() => {
                if (notif.parentElement) {
                    notif.style.animation = 'slideOutRight 0.3s ease-in';
                    setTimeout(() => notif.remove(), 300);
                }
            }, 4000);
        }

        function mostrarNotificacaoNovasMensagens(quantidade) {
            const notif = document.createElement('div');
            notif.className = 'notificacao-flutuante';
            notif.innerHTML = `
                <i class="fas fa-comment-dots"></i>
                <span>${quantidade} nova${quantidade > 1 ? 's' : ''} mensagem${quantidade > 1 ? 's' : ''} de transportador</span>
                <button onclick="this.parentElement.remove()">&times;</button>
            `;
            
            notif.style.cssText = `
                position: fixed;
                top: 100px;
                right: 20px;
                background: #2E7D32;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 10px;
                z-index: 9999;
                animation: slideInRight 0.3s ease-out;
            `;
            
            document.body.appendChild(notif);
            
            setTimeout(() => {
                if (notif.parentElement) {
                    notif.style.animation = 'slideOutRight 0.3s ease-in';
                    setTimeout(() => notif.remove(), 300);
                }
            }, 5000);
        }

        function gerenciarEventosJanela() {
            window.addEventListener('focus', function() {
                if (!estaVerificando) {
                    verificarAtualizacoes();
                }
            });
            
            window.addEventListener('blur', function() {
                if (intervaloAtualizacao) {
                    clearInterval(intervaloAtualizacao);
                    intervaloAtualizacao = null;
                }
            });
            
            window.addEventListener('focus', function() {
                if (!intervaloAtualizacao) {
                    iniciarPolling();
                }
            });
        }

        // Função para filtrar conversas (opcional)
        function filtrarConversas(tipo) {
            const cards = document.querySelectorAll('.conversa-card');
            cards.forEach(card => {
                if (tipo === 'todas') {
                    card.style.display = 'flex';
                } else if (tipo === 'nao-lidas') {
                    card.style.display = (card.dataset.tipo === 'nao-lida') ? 'flex' : 'none';
                }
            });
        }

        // Iniciar sistema quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(iniciarSistemaDinamico, 500);
        });
    </script>
</body>
</html>
