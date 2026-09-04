<?php
// src/transportador/disponiveis.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
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

// Estados disponíveis para filtros (mesmo conjunto usado em anuncios.php)
$estados_disponiveis = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 
    'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 
    'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
];

// Filtros vindos da querystring
$filtro_estado_origem = $_GET['estado_origem'] ?? '';
$filtro_estado_destino = $_GET['estado_destino'] ?? '';

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

// Buscar favoritos do transportador (se a tabela existir)
$favoritos_propostas = [];
try {
    $sql_favs = "SELECT proposta_id FROM transportador_favoritos WHERE transportador_id = :transportador_id";
    $stmt_favs = $db->prepare($sql_favs);
    $stmt_favs->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
    $stmt_favs->execute();
    $rows = $stmt_favs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $favoritos_propostas[] = (int)$r['proposta_id'];
    }
} catch (PDOException $e) {
    // tabela pode não existir ainda -> OK
}

// Buscar total de notificações não lidas
$total_nao_lidas = 0;
if (isset($_SESSION['usuario_id'])) {
    try {
        $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
        $stmt_nao_lidas = $db->prepare($sql_nao_lidas);
        $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
        $stmt_nao_lidas->execute();
        $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        error_log("Erro ao buscar notificações: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Transportador - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/transportador/navbar.css">
    <link rel="stylesheet" href="../css/transportador/dashboard.css">
    <link rel="stylesheet" href="../css/transportador/disponiveis.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <?php if (isset($_GET['erro']) && $_GET['erro']): ?>
    <div id="popup-erro" class="popup-aviso-overlay">
        <div class="popup-aviso-box erro">
            <div class="popup-aviso-title erro">Erro ao enviar proposta</div>
            <div class="popup-aviso-message"><?php echo htmlspecialchars($_GET['erro']); ?></div>
            <div class="popup-aviso-timer erro">Este aviso será fechado automaticamente em <span id='popup-timer-erro'>10</span>s.</div>
            <div class="popup-aviso-progress-track erro">
                <div id="popup-bar-erro" class="popup-aviso-progress-bar erro"></div>
            </div>
            <button onclick="fecharPopupErro()" class="popup-aviso-btn erro">Fechar agora</button>
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
        }, 1000);
        function fecharPopupErro(){
            let popup = document.getElementById('popup-erro');
            if(popup) popup.style.display = 'none';
            clearInterval(intervalErro);
        }
    </script>
    <?php endif; ?>

    <?php if (isset($_GET['sucesso']) && $_GET['sucesso']): ?>
    <div id="popup-sucesso" class="popup-aviso-overlay">
        <div class="popup-aviso-box sucesso">
            <div class="popup-aviso-title sucesso">Proposta de frete enviada!</div>
            <div class="popup-aviso-message">Para ver, editar ou cancelar sua proposta, acesse <a href='entregas' class="popup-aviso-link">Minhas Entregas</a>.</div>
            <div class="popup-aviso-timer sucesso">Este aviso será fechado automaticamente em <span id='popup-timer'>10</span>s.</div>
            <div class="popup-aviso-progress-track sucesso">
                <div id="popup-bar" class="popup-aviso-progress-bar sucesso"></div>
            </div>
            <button onclick="fecharPopup()" class="popup-aviso-btn sucesso">Fechar agora</button>
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
        }, 1000);
        function fecharPopup(){
            let popup = document.getElementById('popup-sucesso');
            if(popup) popup.style.display = 'none';
            clearInterval(interval);
        }
    </script>
    <?php endif; ?>

    <?php $active_nav = ''; require __DIR__ . '/includes/navbar.php'; ?>

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
            <div class="acordos-header">
                <h2>Entregas disponíveis</h2>
                <div class="filters-inline">
                    <!-- Retirada (origem) dropdown -->
                    <div class="dropdown" id="filtro-origem">
                        <button type="button" class="filtro-btn">
                            <i class="fas fa-truck"></i>
                            <?= !empty($filtro_estado_origem) ? htmlspecialchars($filtro_estado_origem) : 'Retirada' ?>
                            <?php if (!empty($filtro_estado_origem)): ?><span class="filtro-ativo-indicator"></span><?php endif; ?>
                            <span class="caret">▾</span>
                        </button>
                        <div class="dropdown-content" role="dialog" aria-label="Estados de retirada">
                            <form method="GET" action="disponiveis" class="filtro-form">
                                <?php if (!empty($filtro_estado_destino)): ?><input type="hidden" name="estado_destino" value="<?= htmlspecialchars($filtro_estado_destino) ?>"><?php endif; ?>
                                <div>
                                    <div class="filtro-header"><span>Escolha o estado de retirada</span><?php if (!empty($filtro_estado_origem)): ?><a class="remove-filtro" href="disponiveis">Limpar</a><?php endif; ?></div>
                                    <div class="estados-grid">
                                        <?php foreach ($estados_disponiveis as $est): ?>
                                            <label class="estado-option">
                                                <input type="radio" name="estado_origem" value="<?= htmlspecialchars($est) ?>" <?= ($filtro_estado_origem === $est) ? 'checked' : '' ?> onchange="this.form.submit()">
                                                <span><?= htmlspecialchars($est) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Entrega (destino) dropdown -->
                    <div class="dropdown" id="filtro-destino">
                        <button type="button" class="filtro-btn">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= !empty($filtro_estado_destino) ? htmlspecialchars($filtro_estado_destino) : 'Entrega' ?>
                            <?php if (!empty($filtro_estado_destino)): ?><span class="filtro-ativo-indicator"></span><?php endif; ?>
                            <span class="caret">▾</span>
                        </button>
                        <div class="dropdown-content" role="dialog" aria-label="Estados de entrega">
                            <form method="GET" action="disponiveis" class="filtro-form">
                                <?php if (!empty($filtro_estado_origem)): ?><input type="hidden" name="estado_origem" value="<?= htmlspecialchars($filtro_estado_origem) ?>"><?php endif; ?>
                                <div>
                                    <div class="filtro-header"><span>Escolha o estado de entrega</span><?php if (!empty($filtro_estado_destino)): ?><a class="remove-filtro" href="disponiveis">Limpar</a><?php endif; ?></div>
                                    <div class="estados-grid">
                                        <?php foreach ($estados_disponiveis as $est): ?>
                                            <label class="estado-option">
                                                <input type="radio" name="estado_destino" value="<?= htmlspecialchars($est) ?>" <?= ($filtro_estado_destino === $est) ? 'checked' : '' ?> onchange="this.form.submit()">
                                                <span><?= htmlspecialchars($est) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            // Buscar acordos de compra com tipo_frete = 'plataforma' e status = 'aceita' e sem transportador definido
            // E que NÃO TENHAM uma proposta de transporte ACEITA
                $sql_acordos = "SELECT p.ID as proposta_id, p.*, 
                comp.nome_comercial as comprador_nome,
                comp.cep as comprador_cep,
                comp.rua as comprador_rua,
                comp.numero as comprador_numero,
                comp.cidade as comprador_cidade,
                comp.estado as comprador_estado,
                vend.nome_comercial as vendedor_nome,
                vend.cep as vendedor_cep,
                vend.rua as vendedor_rua,
                vend.numero as vendedor_numero,
                vend.cidade as vendedor_cidade,
                vend.estado as vendedor_estado,
                pr.nome as produto_nome, 
                pr.imagem_url as produto_imagem, 
                p.quantidade_proposta as quantidade
            FROM propostas p
            INNER JOIN produtos pr ON p.produto_id = pr.id
            LEFT JOIN compradores comp ON comp.usuario_id = p.comprador_id
            LEFT JOIN vendedores vend ON vend.usuario_id = p.vendedor_id
                WHERE p.opcao_frete = 'entregador' 
                AND p.status = 'aceita' 
                AND p.transportador_id IS NULL
                AND COALESCE(p.frete_resolvido,0) = 0
                ";

            // Aplicar filtros de estado
            if (!empty($filtro_estado_origem)) {
                $sql_acordos .= " AND vend.estado = :estado_origem";
            }
            if (!empty($filtro_estado_destino)) {
                $sql_acordos .= " AND comp.estado = :estado_destino";
            }

            $sql_acordos .= " ORDER BY p.data_inicio DESC";

            $stmt_acordos = $db->prepare($sql_acordos);
            if (!empty($filtro_estado_origem)) {
                $stmt_acordos->bindValue(':estado_origem', $filtro_estado_origem);
            }
            if (!empty($filtro_estado_destino)) {
                $stmt_acordos->bindValue(':estado_destino', $filtro_estado_destino);
            }
            $stmt_acordos->execute();
            $acordos = $stmt_acordos->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($acordos) === 0) {
                echo '<p>Nenhum acordo disponível no momento.</p>';
            } else {
                echo '<div class="acordos-lista">';
                foreach ($acordos as $acordo) {
                    // Preferir endereço salvo na própria proposta (endereco_vendedor / endereco_comprador),
                    // caso contrário montar a partir dos dados atuais do vendedor/comprador
                    if (!empty($acordo['endereco_vendedor'])) {
                        $origem = $acordo['endereco_vendedor'];
                    } else {
                        $origem = ($acordo['vendedor_rua'] ?? '') . 
                                ', ' . ($acordo['vendedor_numero'] ?? '') . 
                                ' - ' . ($acordo['vendedor_cidade'] ?? '') . 
                                '/' . ($acordo['vendedor_estado'] ?? '') . 
                                ' - CEP: ' . ($acordo['vendedor_cep'] ?? '');
                    }

                    if (!empty($acordo['endereco_comprador'])) {
                        $destino = $acordo['endereco_comprador'];
                    } else {
                        $destino = ($acordo['comprador_rua'] ?? '') . 
                                ', ' . ($acordo['comprador_numero'] ?? '') . 
                                ' - ' . ($acordo['comprador_cidade'] ?? '') . 
                                '/' . ($acordo['comprador_estado'] ?? '') . 
                                ' - CEP: ' . ($acordo['comprador_cep'] ?? '');
                    }
                        
                    $google_maps_url = 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($origem) . '&destination=' . urlencode($destino) . '&travelmode=driving';
            ?>
                    <div class="acordo-card">
                        <button class="fav-btn <?php echo in_array((int)$acordo['proposta_id'], $favoritos_propostas) ? 'favorited' : ''; ?>" data-proposta-id="<?php echo (int)$acordo['proposta_id']; ?>" title="Favoritar entrega">
                            <svg class="heart" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                <path d="M12.1 21.35l-1.1-1.02C5.14 15.36 2 12.28 2 8.5 2 6 4 4 6.5 4c1.74 0 3.41.81 4.5 2.09C12.09 4.81 13.76 4 15.5 4 18 4 20 6 20 8.5c0 3.78-3.14 6.86-8.9 11.83l-1 .02z"/>
                            </svg>
                        </button>
                        <div class="acordo-header">Pedido #<?php echo $acordo['proposta_id']; ?> &bull; <?php echo htmlspecialchars($acordo['produto_nome']); ?></div>
                        <div class="acordo-info">
                            <strong>Vendedor:</strong> <?php echo htmlspecialchars($acordo['vendedor_nome']); ?><br>
                            <strong>Comprador:</strong> <?php echo htmlspecialchars($acordo['comprador_nome']); ?><br>
                            <strong>Quantidade:</strong> <?php echo htmlspecialchars($acordo['quantidade']); ?><br>
                        </div>
                        <?php $img = $acordo['produto_imagem'] ? htmlspecialchars($acordo['produto_imagem']) : '../../img/placeholder.png'; ?>
                        <div class="acordo-img-wrap">
                            <a href="../visualizar_anuncio?anuncio_id=<?php echo intval($acordo['produto_id']); ?>">
                                <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($acordo['produto_nome']); ?>" class="acordo-img">
                            </a>
                        </div>
                        <div class="acordo-info">
                            Retirada: <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($origem); ?>" target="_blank"><?php echo htmlspecialchars($origem); ?></a><br>
                            Entrega: <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($destino); ?>" target="_blank"><?php echo htmlspecialchars($destino); ?></a><br>
                            <a href="<?php echo $google_maps_url; ?>" target="_blank" class="rota-link">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" class="rota-icon" aria-hidden="true">
                                    <path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/>
                                </svg>
                                Ver rota no Google Maps
                            </a>
                        </div>
                        <div class="acordo-actions">
                            <button type="button" class="acordo-btn chat" onclick="startChat(<?php echo $acordo['proposta_id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="chat-icon">
                                    <path d="M20 2H4a2 2 0 0 0-2 2v14l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
                                </svg>
                                Iniciar chat com <?php echo htmlspecialchars($acordo['comprador_nome']); ?>
                            </button>
                            <a href="../visualizar_anuncio?anuncio_id=<?php echo intval($acordo['produto_id']); ?>" class="acordo-btn ver-anuncio">Ver anúncio</a>
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
        // Toggle favorito via fetch
        document.querySelectorAll('.fav-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const propostaId = btn.getAttribute('data-proposta-id');
                try {
                    const res = await fetch('toggle_favorito', {
                        method: 'POST',
                        headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({proposta_id: propostaId})
                    });
                    const data = await res.json();
                    if (data.success) {
                        if (data.favorited) {
                            btn.classList.add('favorited');
                        } else {
                            btn.classList.remove('favorited');
                        }
                    } else {
                        alert(data.erro || 'Erro ao favoritar');
                    }
                } catch (err) {
                    console.error(err);
                    alert('Erro de conexão');
                }
            });
        });

        // Iniciar chat
        async function startChat(propostaId) {
            try {
                const form = new URLSearchParams();
                form.append('proposta_id', propostaId);
                const res = await fetch('../chat/create_conversa_transportador', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: form
                });
                const data = await res.json();
                if (data.success && data.conversa_id) {
                    window.location.href = '../chat_transportador/chat_interface?conversa_id=' + data.conversa_id;
                } else {
                    alert(data.erro || 'Erro ao iniciar chat');
                }
            } catch (e) {
                console.error(e);
                alert('Erro de conexão ao iniciar chat');
            }
        }
        // Dropdown toggle for filtros (works on any dropdown element)
        (function(){
            document.querySelectorAll('.dropdown').forEach(dropdown=>{
                const btn = dropdown.querySelector('.filtro-btn');
                if(!btn) return;
                btn.addEventListener('click', (e)=>{
                    e.stopPropagation();
                    // close others
                    document.querySelectorAll('.dropdown').forEach(d=>{ if(d!==dropdown) d.classList.remove('open'); });
                    dropdown.classList.toggle('open');
                });
            });
            // fechar ao clicar fora
            document.addEventListener('click', (ev)=>{
                document.querySelectorAll('.dropdown').forEach(d=>{ if(!d.contains(ev.target)) d.classList.remove('open'); });
            });
            // fechar com ESC
            document.addEventListener('keydown', (ev)=>{ if(ev.key === 'Escape') document.querySelectorAll('.dropdown').forEach(d=>d.classList.remove('open')); });
        })();
    </script>
</body>
</html>