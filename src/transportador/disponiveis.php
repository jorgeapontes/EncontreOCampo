<?php
// src/transportador/dashboard.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

// Verificar se o usuário tem permissão para ver dashboard completo
$usuario_status = $_SESSION['usuario_status'] ?? 'pendente';
$is_pendente = ($usuario_status === 'pendente');

$usuario_nome = htmlspecialchars($_SESSION['transportador_nome'] ?? 'Transportador');
$usuario_id = $_SESSION['usuario_id'];

// Conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Buscar dados do transportador
$transportador_id = null;
$transportador_nome_comercial = '';

try {
    $sql_transportador = "SELECT id, nome_comercial 
                         FROM transportadores 
                         WHERE usuario_id = :usuario_id";
                     
    $stmt_transportador = $db->prepare($sql_transportador);
    $stmt_transportador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_transportador->execute();
    $transportador = $stmt_transportador->fetch(PDO::FETCH_ASSOC);
    
    if ($transportador) {
        $transportador_id = $transportador['id'];
        $transportador_nome_comercial = $transportador['nome_comercial'] ?? $usuario_nome;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do transportador: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Transportador - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/transportador/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
                <?php if (isset($_GET['erro']) && $_GET['erro']): ?>
                <div id="popup-erro" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.18);">
                    <div style="background:#fff;border-radius:14px;box-shadow:0 4px 32px rgba(220,60,60,0.13);padding:38px 32px 28px 32px;max-width:95vw;width:400px;text-align:center;position:relative;">
                        <div style="font-size:1.18rem;font-weight:600;color:#b3261e;margin-bottom:10px;">Erro ao enviar proposta</div>
                        <div style="color:#444;font-size:1.05rem;margin-bottom:18px;"><?php echo htmlspecialchars($_GET['erro']); ?></div>
                        <div style="margin-bottom:10px;color:#b3261e;font-size:0.98rem;">Este aviso será fechado automaticamente em <span id='popup-timer-erro'>10</span>s.</div>
                        <div style="width:100%;height:7px;background:#fbeaea;border-radius:4px;overflow:hidden;margin-bottom:0;">
                            <div id="popup-bar-erro" style="height:100%;background:#b3261e;width:100%;transition:width 0.2s;"></div>
                        </div>
                        <button onclick="fecharPopupErro()" style="margin-top:18px;background:#b3261e;color:#fff;border:none;border-radius:6px;padding:8px 22px;font-size:1rem;font-weight:500;cursor:pointer;">Fechar agora</button>
                    </div>
                </div>
                <script>
                    let tempoErro = 10;
                    let barErro = document.getElementById('popup-bar-erro');
                    let timerErro = document.getElementById('popup-timer-erro');
                    let intervalErro = setInterval(function(){
                        tempoErro--;
                        if(timerErro) timerErro.textContent = tempoErro;
                        if(barErro) barErro.style.width = (tempoErro*10) + '%';
                        if(tempoErro <= 0) fecharPopupErro();
                    },1000);
                    function fecharPopupErro(){
                        let popup = document.getElementById('popup-erro');
                        if(popup) popup.style.display = 'none';
                        clearInterval(intervalErro);
                    }
                </script>
                <?php endif; ?>
        <?php if (isset($_GET['sucesso']) && $_GET['sucesso']): ?>
        <div id="popup-sucesso" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.18);">
            <div style="background:#fff;border-radius:14px;box-shadow:0 4px 32px rgba(60,180,120,0.13);padding:38px 32px 28px 32px;max-width:95vw;width:400px;text-align:center;position:relative;">
                <div style="font-size:1.25rem;font-weight:600;color:#256c3b;margin-bottom:10px;">Proposta de frete enviada!</div>
                <div style="color:#444;font-size:1.05rem;margin-bottom:18px;">Para ver, editar ou cancelar sua proposta, acesse <a href='entregas.php' style='color:#2566d6;text-decoration:underline;'>Minhas Entregas</a>.</div>
                <div style="margin-bottom:10px;color:#256c3b;font-size:0.98rem;">Este aviso será fechado automaticamente em <span id='popup-timer'>10</span>s.</div>
                <div style="width:100%;height:7px;background:#e6f9ed;border-radius:4px;overflow:hidden;margin-bottom:0;">
                    <div id="popup-bar" style="height:100%;background:#3a7a4d;width:100%;transition:width 0.2s;"></div>
                </div>
                <button onclick="fecharPopup()" style="margin-top:18px;background:#256c3b;color:#fff;border:none;border-radius:6px;padding:8px 22px;font-size:1rem;font-weight:500;cursor:pointer;">Fechar agora</button>
            </div>
        </div>
        <script>
            let tempo = 10;
            let bar = document.getElementById('popup-bar');
            let timer = document.getElementById('popup-timer');
            let interval = setInterval(function(){
                tempo--;
                if(timer) timer.textContent = tempo;
                if(bar) bar.style.width = (tempo*10) + '%';
                if(tempo <= 0) fecharPopup();
            },1000);
            function fecharPopup(){
                let popup = document.getElementById('popup-sucesso');
                if(popup) popup.style.display = 'none';
                clearInterval(interval);
            }
        </script>
        <?php endif; ?>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="entregas.php" class="nav-link">Entregas</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            if (isset($_SESSION['usuario_id'])) {
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $db->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <br>
    <div class="main-content">

        <section class="header">
            <center>
                <h1>Bem-vindo(a), <?php echo htmlspecialchars($transportador_nome_comercial); ?>!</h1>
                <?php if ($is_pendente): ?>
                    <p class="subtitulo">(Cadastro aguardando aprovação)</p>
                <?php endif; ?>
            </center>
        </section>

        <?php if ($is_pendente): ?>
            <div class="aviso-status">
                <i class="fas fa-info-circle"></i>
                <strong>Seu cadastro está aguardando aprovação.</strong>
            </div>
        <?php endif; ?>

        <section class="acordos-disponiveis">
        <style>
            .acordos-disponiveis {
                max-width: 1200px;
                margin: 40px auto;
                background: #f9f9f9;
                border-radius: 16px;
                box-shadow: 0 2px 18px rgba(0,0,0,0.06);
                padding: 32px 24px 24px 24px;
            }
            .acordos-lista {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 28px;
            }
            .acordo-card {
                border-radius: 12px;
                background: #fff;
                box-shadow: 0 1px 6px rgba(0,0,0,0.04);
                padding: 20px 16px 16px 16px;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                min-height: 240px;
                transition: box-shadow 0.18s, border 0.18s;
                border: 1.5px solid #e6eaf0;
            }
            .acordo-card:hover {
                box-shadow: 0 6px 18px rgba(60,180,120,0.10);
                border-color: #b6e2c6;
            }
            .acordo-header {
                font-size: 1.08rem;
                font-weight: 700;
                color: #222;
                margin-bottom: 8px;
                letter-spacing: 0.01em;
            }
            .acordo-info {
                font-size: 0.97rem;
                color: #444;
                margin-bottom: 10px;
                width: 100%;
            }
            .acordo-info strong {
                color: #3a7a4d;
                font-weight: 600;
            }
            .acordo-actions {
                margin-top: auto;
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .acordo-btn {
                background: #3a7a4d;
                color: #fff;
                border: none;
                border-radius: 7px;
                padding: 10px 0;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                width: 100%;
                transition: background 0.18s;
                box-shadow: 0 1px 4px rgba(60,180,120,0.07);
            }
            .acordo-btn:hover {
                background: #256c3b;
            }
            .acordo-card a {
                color: #2566d6;
                text-decoration: none;
                font-size: 0.97rem;
                margin-bottom: 2px;
                word-break: break-all;
                transition: color 0.15s;
            }
            .acordo-card a:hover {
                text-decoration: underline;
                color: #1741a6;
            }
            .acordo-card form label {
                font-size: 0.97rem;
                color: #444;
                margin-bottom: 2px;
            }
            .acordo-card form input[type="number"] {
                width: 100%;
                padding: 7px 8px;
                border: 1px solid #dbe5ef;
                border-radius: 5px;
                margin-bottom: 8px;
                font-size: 1rem;
                background: #f9f9f9;
                transition: border 0.15s;
            }
            .acordo-card form input[type="number"]:focus {
                border-color: #3a7a4d;
                outline: none;
            }
            @media (max-width: 900px) {
                .acordos-lista {
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                }
            }
            @media (max-width: 600px) {
                .acordos-disponiveis { padding: 10px 2vw; }
                .acordos-lista { gap: 14px; }
                .acordo-card { padding: 10px 6px; min-height: 160px; }
            }
        </style>
            <h2>Entregas disponíveis</h2>
            <?php
            // Buscar acordos de compra com tipo_frete = 'plataforma' e status = 'aceita' e sem transportador definido
            $sql_acordos = "SELECT p.ID as proposta_id, p.*, 
                (SELECT nome_comercial FROM compradores WHERE id = p.comprador_id) as comprador_nome,
                (SELECT cep FROM compradores WHERE id = p.comprador_id) as comprador_cep,
                (SELECT rua FROM compradores WHERE id = p.comprador_id) as comprador_rua,
                (SELECT numero FROM compradores WHERE id = p.comprador_id) as comprador_numero,
                (SELECT cidade FROM compradores WHERE id = p.comprador_id) as comprador_cidade,
                (SELECT estado FROM compradores WHERE id = p.comprador_id) as comprador_estado,
                (SELECT nome_comercial FROM vendedores WHERE id = p.vendedor_id) as vendedor_nome,
                (SELECT cep FROM vendedores WHERE id = p.vendedor_id) as vendedor_cep,
                (SELECT rua FROM vendedores WHERE id = p.vendedor_id) as vendedor_rua,
                (SELECT numero FROM vendedores WHERE id = p.vendedor_id) as vendedor_numero,
                (SELECT cidade FROM vendedores WHERE id = p.vendedor_id) as vendedor_cidade,
                (SELECT estado FROM vendedores WHERE id = p.vendedor_id) as vendedor_estado,
                pr.nome as produto_nome, pr.imagem_url as produto_imagem, p.quantidade_proposta as quantidade
                FROM propostas p
                INNER JOIN produtos pr ON p.produto_id = pr.id
                WHERE p.opcao_frete = 'entregador' AND p.status = 'aceita'
                ORDER BY p.data_inicio DESC";
            $stmt_acordos = $db->prepare($sql_acordos);
            $stmt_acordos->execute();
            $acordos = $stmt_acordos->fetchAll(PDO::FETCH_ASSOC);
            if (count($acordos) === 0) {
                echo '<p>Nenhum acordo disponível no momento.</p>';
            } else {
                echo '<div class="acordos-lista">';
                foreach ($acordos as $acordo) {
                    // Preferir endereço atualizado da negociação, se existir
                    $origem = $acordo['vendedor_rua'] . ', ' . $acordo['vendedor_numero'] . ' - ' . $acordo['vendedor_cidade'] . '/' . $acordo['vendedor_estado'] . ' - CEP: ' . $acordo['vendedor_cep'];
                    $destino = $acordo['comprador_rua'] . ', ' . $acordo['comprador_numero'] . ' - ' . $acordo['comprador_cidade'] . '/' . $acordo['comprador_estado'] . ' - CEP: ' . $acordo['comprador_cep'];
                    $google_maps_url = 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($origem) . '&destination=' . urlencode($destino) . '&travelmode=driving';
            ?>
                    <div class="acordo-card">
                        <div class="acordo-header">Pedido #<?php echo $acordo['proposta_id']; ?> &bull; <?php echo htmlspecialchars($acordo['produto_nome']); ?></div>
                        <div class="acordo-info">
                            <strong>Vendedor:</strong> <?php echo htmlspecialchars($acordo['vendedor_nome']); ?><br>
                            <strong>Comprador:</strong> <?php echo htmlspecialchars($acordo['comprador_nome']); ?><br>
                            <strong>Quantidade:</strong> <?php echo htmlspecialchars($acordo['quantidade']); ?><br>
                        </div>
                            <?php $img = $acordo['produto_imagem'] ? htmlspecialchars($acordo['produto_imagem']) : '../../img/placeholder.png'; ?>
                            <div style="width:100%;height:160px;overflow:hidden;margin-bottom:12px;border-radius:8px;">
                                <a href="../visualizar_anuncio.php?anuncio_id=<?php echo intval($acordo['produto_id']); ?>">
                                    <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($acordo['produto_nome']); ?>" style="width:100%;height:160px;object-fit:cover;display:block;border-radius:8px;">
                                </a>
                            </div>
                        <div class="acordo-info">
                            Retirada: <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($origem); ?>" target="_blank"><?php echo htmlspecialchars($origem); ?></a><br>
                            Entrega:<a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($destino); ?>" target="_blank"> <?php echo htmlspecialchars($destino); ?></a><br>
                            <a href="<?php echo $google_maps_url; ?>" target="_blank" style="display:inline-flex;align-items:center;gap:8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="flex:0 0 16px;" aria-hidden="true">
                                    <path fill="#d23f31" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/>
                                </svg>
                                Ver rota no Google Maps
                            </a>
                        </div>
                        <div class="acordo-actions">
                            <a href="../visualizar_anuncio.php?anuncio_id=<?php echo intval($acordo['produto_id']); ?>" class="acordo-btn" style="background:#2566d6;color:#fff;text-align:center;text-decoration:none;">Ver anúncio</a>
                            <button type="button" class="acordo-btn" style="background:#2E7D32;color:#fff;margin-top:6px;" onclick="startChat(<?php echo $acordo['proposta_id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:8px;display:inline-block;">
                                    <path d="M20 2H4a2 2 0 0 0-2 2v14l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
                                </svg>
                                Iniciar chat com <?php echo htmlspecialchars($acordo['comprador_nome']); ?>
                            </button>
                        </div>
                    </div>
            <?php
                }
                echo '</div>';
            }
            ?>
        </section>
    </div>

    <script>
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        if (hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            }));
        }
        
        async function startChat(propostaId) {
            try {
                const form = new URLSearchParams();
                form.append('proposta_id', propostaId);
                const res = await fetch('../chat/create_conversa_transportador.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: form
                });
                const data = await res.json();
                if (data.success && data.conversa_id) {
                    window.location.href = '../chat_transportador/chat_interface.php?conversa_id=' + data.conversa_id;
                } else {
                    alert(data.erro || 'Erro ao iniciar chat');
                }
            } catch (e) {
                console.error(e);
                alert('Erro de conexão ao iniciar chat');
            }
        }
    </script>
</body>
</html>