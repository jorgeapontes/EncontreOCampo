<?php
// src/procurando_transportador.php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissions.php';

// Permitir acesso a usuários logados como comprador ou vendedor (vendedores podem também comprar)
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_tipo'] ?? '', ['comprador', 'vendedor'])) {
    header("Location: login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
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
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                cc.transportador_id,
                (SELECT u.nome FROM usuarios u WHERE u.id = cc.transportador_id) AS transportador_nome,
                cc.favorito_comprador AS arquivado,
                (SELECT COUNT(*) FROM chat_mensagens cm WHERE cm.conversa_id = cc.id AND cm.remetente_id != :usuario_id AND cm.lida = 0) AS mensagens_nao_lidas
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --secondary-color: #FF9800;
            --dark-color: #2E7D32;
            --light-color: #F1F8E9;
            --text-color: #212121;
            --text-light: #757575;
            --white: #FFFFFF;
            --gray: #F5F5F5;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; min-height: 100vh; }
        /* Navbar */
        .navbar {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
        }       
        .logo h1 {
            font-size: 1.5rem;
            color: var(--primary-color);
            font-weight: 700;
            letter-spacing: 0px;
            line-height: 1.6;
            margin-top: 4px;
        }

        .logo h2 {
            font-size: 1.1rem;
            color: var(--dark-color);
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: -10px;
            line-height: 1.3;
        }

        .logo img {
            height: 60px;
            padding-right: 5px;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            align-items: center;
        }

        .nav-item {
            margin-left: 30px;
        }

        .nav-link {
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            padding: 10px 0;
            position: relative;
            transition: color 0.3s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .nav-link.login-button {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 8px 20px;
            border-radius: 20px;
            transition: background-color 0.3s ease;
            margin-left: 15px;
        }

        .nav-link.sino::after {
            display: none;
        }

        .nav-link.login-button:hover {
            background-color: var(--primary-dark);
            color: var(--white);
        }

        /* Remover sublinhado do botão login */
        .nav-link.no-underline::after {
            display: none;
        }
        .main-content { position: flex; max-width: 1400px; margin: 2rem auto; padding: 70px 2rem; top: 20px; }
        .conversas-container { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .conversas-header { padding: 1.5rem 2rem; background: #f9f9f9; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
        .conversas-header h2 { font-size: 20px; color: #333; }
        .conversas-list { max-height: 700px; overflow-y: auto; }
        .conversa-card { padding: 1.5rem 2rem; border-bottom: 1px solid #e0e0e0; display: flex; gap: 1.5rem; align-items: center; transition: background 0.2s; color: inherit; }
        .conversa-card:hover { background: #f9f9f9; }
        .produto-thumb { width: 80px; height: 80px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: #e0e0e0; display: flex; align-items: center; justify-content: center; position: relative; }
        .produto-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .produto-thumb .placeholder-icon { font-size: 32px; color: #999; }
        .debug-info { font-size: 10px; color: red; background: #fff; padding: 2px 4px; position: absolute; bottom: 0; left: 0; right: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .conversa-info { flex: 1; min-width: 0; }
        .produto-nome-principal { font-weight: 700; color: #333; font-size: 16px; }
        .conversa-data { font-size: 13px; color: #999; }
        .ultima-mensagem { font-size: 14px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 500px; }
        .conversa-actions { display: flex; gap: 8px; align-items: center; }
        .badge-novo { background: #dc3545; color: white; font-size: 12px; padding: 4px 8px; border-radius: 12px; font-weight: 700; }
        .btn-chat { background: #2E7D32; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; border: none; }
        .btn-chat:hover { background: #1B5E20; }
        .empty-state { padding: 4rem 2rem; text-align: center; color: #999; }
        .debug-alert { background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px; }
        .nav-menu {
    display: flex;
    list-style: none;
    align-items: center;
    text-decoration: none;
}



.nav-link.exit-button {
    background-color: rgb(230, 30, 30);
    color: #fff;
    padding: 8px 20px;
    border-radius: 20px;
    transition: background-color 0.3s ease;
    margin-left: 15px;
}

.nav-link.exit-button:hover {
    background-color: rgb(200, 30, 30);
    color: #fff;
}

/* Menu Hamburguer */
.hamburger {
    display: none;
    cursor: pointer;
}

.bar {
    display: block;
    width: 25px;
    height: 3px;
    margin: 5px auto;
    background-color: var(--text-color);
    transition: all 0.3s ease;
}

.hamburger.active .bar:nth-child(2) {
    opacity: 0;
}

.hamburger.active .bar:nth-child(1) {
    transform: translateY(8px) rotate(45deg);
}

.hamburger.active .bar:nth-child(3) {
    transform: translateY(-8px) rotate(-45deg);
}

/* Responsividade do menu */
@media (max-width: 768px) {
    .hamburger {
        display: block;
    }
    
    .nav-menu {
        position: fixed;
        left: -100%;
        top: 80px;
        gap: 0;
        flex-direction: column;
        background-color: white;
        width: 100%;
        text-align: center;
        transition: 0.3s;
        box-shadow: 0 10px 10px rgba(0,0,0,0.1);
        z-index: 999;
        padding: 20px 0;
    }
    
    .nav-item {
        margin: 15px 0;
    }
    
    .nav-menu.active {
        left: 0;
    }
    
    .nav-link.exit-button {
        margin-left: 0;
        margin-top: 10px;
    }
}

/* --- Ajustes para dispositivos móveis (até 480px) --- */
@media screen and (max-width: 480px) {
    /* Ajuste do layout geral */
    .main-content {
        padding: 10px;
        margin-top: 100px;
    }
    
    /* Ajuste do container das conversas */
    .conversas-container {
        border-radius: 8px;
        overflow: hidden;
        margin: 0;
    }
    
    /* Ajuste do cabeçalho */
    .conversas-header {
        padding: 15px;
    }
    
    .conversas-header h2 {
        font-size: 1.2rem;
        text-align: center;
        width: 100%;
    }
    
    /* Ajuste da lista de conversas */
    .conversas-list {
        max-height: none;
        overflow-y: visible;
    }
    
    /* Ajuste dos cards de conversa */
    .conversa-card {
        padding: 15px;
        flex-direction: column;
        gap: 12px;
        position: relative;
    }
    
    /* Ajuste da miniatura do produto */
    .produto-thumb {
        width: 60px;
        height: 60px;
        border-radius: 6px;
        align-self: flex-start;
    }
    
    .produto-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* Ajuste das informações da conversa */
    .conversa-info {
        width: 100%;
        order: 2;
    }
    
    /* Ajuste do nome do produto e data */
    .produto-nome-principal {
        font-size: 1rem;
        line-height: 1.3;
        margin-bottom: 8px;
    }
    
    .conversa-data {
        font-size: 0.8rem;
        color: #666;
        position: absolute;
        top: 15px;
        right: 15px;
    }
    
    /* Ajuste da linha do transportador */
    .conversa-info div[style*="margin-top:8px;color:#666;"] {
        font-size: 0.9rem;
        margin-top: 5px;
        line-height: 1.4;
    }
    
    /* Ajuste da última mensagem */
    .ultima-mensagem {
        font-size: 0.9rem;
        white-space: normal;
        max-width: 100%;
        line-height: 1.4;
        margin-top: 8px;
        color: #555;
    }
    
    /* Ajuste das ações (botão) */
    .conversa-actions {
        order: 3;
        width: 100%;
        margin-top: 10px;
    }
    
    .btn-chat {
        width: 100%;
        justify-content: center;
        padding: 12px;
        font-size: 0.95rem;
    }
    
    /* Ajuste do estado vazio */
    .empty-state {
        padding: 30px 15px;
    }
    
    .empty-state i {
        font-size: 36px;
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 1.2rem;
    }
    
    .empty-state p {
        font-size: 0.95rem;
    }
    
    /* Ajuste para o link de conversa (quando não arquivado) */
    .conversa-card > a[style*="display:flex"] {
        display: flex !important;
        flex-direction: row;
        align-items: flex-start;
        gap: 12px;
        width: 100%;
        text-decoration: none;
        color: inherit;
    }
    
    /* Ajuste para o div de conversa arquivada */
    .conversa-card > div[style*="display:flex"] {
        display: flex !important;
        flex-direction: row;
        align-items: flex-start;
        gap: 12px;
        width: 100%;
    }
    
    /* Ajuste do badge de mensagens não lidas */
    .badge-novo {
        position: absolute;
        top: 10px;
        left: 10px;
        font-size: 10px;
        padding: 3px 8px;
        z-index: 2;
    }
    
    /* Ajuste do badge arquivado */
    .badge-arquivado {
        display: inline-block;
        background: #6c757d;
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 8px;
    }
}

/* Ajustes para telas muito pequenas (até 360px) */
@media screen and (max-width: 360px) {
    .conversa-card {
        padding: 12px;
    }
    
    .produto-thumb {
        width: 50px;
        height: 50px;
    }
    
    .produto-nome-principal {
        font-size: 0.95rem;
    }
    
    .conversa-data {
        font-size: 0.75rem;
    }
    
    .btn-chat {
        padding: 10px;
        font-size: 0.9rem;
    }
}

.loading-conversas {
            padding: 3rem;
            text-align: center;
            color: #666;
            font-size: 16px;
        }
        
        .loading-conversas i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        .notificacao-flutuante {
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
        }
        
        .notificacao-flutuante button {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            margin-left: 10px;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            50% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        
        .conversa-card.nao-lida {
            background-color: #f0f9ff !important;
            border-left: 4px solid #2196F3 !important;
        }
        
        .badge-novo {
            animation: pulse 1s ease-in-out;
        }
        
        .conversa-card.nova {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Adicione abas para ativos/arquivados (opcional) */
        .abas-container {
            display: flex;
            gap: 1px;
            margin-bottom: 20px;
            background: #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .aba {
            flex: 1;
            padding: 12px 20px;
            background: #f8f9fa;
            border: none;
            border-right: 1px solid #e0e0e0;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .aba:last-child {
            border-right: none;
        }
        
        .aba.active {
            background: #2E7D32;
            color: white;
        }
        
        .aba:hover:not(.active) {
            background: #e9ecef;
        }
        
        .badge-aba {
            background: #dc3545;
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit;">
                        <img src="../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <!-- Menu Hamburguer (adicionado) -->
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="comprador/dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="comprador/perfil.php" class="nav-link">Meu Perfil</a></li>
                    <li class="nav-item"><a href="comprador/chats.php" class="nav-link">Chats</a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
            </div>
        </nav>
    </header>

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
        // Menu Hamburguer functionality
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        
        if (hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
            
            // Fechar menu ao clicar em um link
            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            }));
        }

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
            
            fetch(`carregar_conversas_transportador_ajax.php?aba=${abaAtiva}`, {
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
                <a href="${chatUrl}" style="display:flex;gap:1.5rem;align-items:center;text-decoration:none;color:inherit;flex:1;">
                    <div class="produto-thumb">
                        <img src="${escapeHtml(imagemProduto)}" 
                             alt="${escapeHtml(conversa.produto_nome)}"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <i class="fas fa-image placeholder-icon" style="display: none;"></i>
                    </div>
                    <div class="conversa-info">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div class="produto-nome-principal">
                                ${escapeHtml(conversa.produto_nome)}
                                ${conversa.mensagens_nao_lidas > 0 ? 
                                    `<span class="badge-novo">${conversa.mensagens_nao_lidas} nova${conversa.mensagens_nao_lidas > 1 ? 's' : ''}</span>` : 
                                    ''
                                }
                            </div>
                            <div class="conversa-data">${dataFormatada}</div>
                        </div>
                        <div style="margin-top:8px;color:#666;">
                            <strong>Transportador:</strong> ${escapeHtml(conversa.transportador_nome || 'Transportador')}
                        </div>
                        ${conversa.ultima_mensagem ? 
                            `<div class="ultima-mensagem" style="margin-top:8px;">
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
            
            fetch(`atualizar_transportador_ajax.php?aba=${abaAtiva}&ultima_verificacao=${ultimaVerificacao}`, {
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
                <i class="fas fa-comments" style="font-size:48px;margin-bottom:12px;"></i>
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
                    <button onclick="carregarConversasIniciais()" class="btn-chat" style="margin-top: 15px;">
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