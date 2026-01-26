<?php
// src/transportador/favoritos.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$db = $database->getConnection();

// obter id do transportador
$transportador_id = null;
try {
    $sql_t = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id LIMIT 1";
    $stmt_t = $db->prepare($sql_t);
    $stmt_t->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_t->execute();
    $r = $stmt_t->fetch(PDO::FETCH_ASSOC);
    if ($r) $transportador_id = (int)$r['id'];
} catch (PDOException $e) {}

$favoritos = [];
if ($transportador_id) {
    try {
        $sql = "SELECT tf.id as fav_id, p.*, pr.nome as produto_nome, pr.imagem_url as produto_imagem,
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
               (SELECT estado FROM vendedores WHERE id = p.vendedor_id) as vendedor_estado
            FROM transportador_favoritos tf
                JOIN propostas p ON tf.proposta_id = p.ID
                JOIN produtos pr ON p.produto_id = pr.id
                WHERE tf.transportador_id = :transportador_id
                ORDER BY tf.data_criacao DESC";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
        $stmt->execute();
        $favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $favoritos = [];
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Favoritos - Transportador</title>
    <link rel="stylesheet" href="../css/transportador/dashboard.css">
</head>
<body>
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
                    <li class="nav-item"><a href="<?php echo ($_SESSION['usuario_tipo'] === 'vendedor') ? '../vendedor/dashboard.php' : 'dashboard.php'; ?>" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="favoritos.php" class="nav-link active">Favoritos</a></li>
                    <li class="nav-item"><a href="<?php echo ($_SESSION['usuario_tipo'] === 'vendedor') ? '../vendedor/perfil.php' : 'perfil.php'; ?>" class="nav-link">Meu Perfil</a></li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            if (isset($_SESSION['usuario_id'])) {
                                try {
                                    $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                    $stmt_nao_lidas = $db->prepare($sql_nao_lidas);
                                    $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                    $stmt_nao_lidas->execute();
                                    $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                    if ($total_nao_lidas > 0) echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                                } catch (PDOException $e) { }
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
    <div class="main-content">
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
                position: relative;
            }
            .acordo-card .thumb { width:100%;height:160px;overflow:hidden;margin-bottom:12px;border-radius:8px }
            .acordo-card .thumb img { width:100%;height:100%;object-fit:cover;display:block }
            .acordo-card h3 { margin:0 0 8px 0; font-size:1.08rem; font-weight:700 }
            .acordo-info { font-size:0.97rem; color:#444; margin-bottom:10px; width:100%; }
            .acordo-actions { margin-top: auto; width:100%; display:flex; flex-direction:column; gap:8px; }
            .acordo-btn { background: #3a7a4d; color:#fff; border:none; border-radius:7px; padding:10px 0; font-size:1rem; font-weight:600; cursor:pointer; width:100%; }
            .acordo-btn[style*="#2566d6"] { text-decoration:none; display:inline-block; text-align:center }
            .acordo-card:hover { box-shadow: 0 6px 18px rgba(60,180,120,0.10); border-color: #b6e2c6; }
            @media (max-width: 900px) {
                .acordos-lista { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
            }
            @media (max-width: 600px) {
                .acordos-disponiveis { padding: 10px 2vw; }
                .acordos-lista { gap: 14px; }
                .acordo-card { padding: 10px 6px; min-height: 160px; }
                .acordo-card .thumb { height:120px }
            }
            </style>

            <h2>Favoritos</h2>
            <?php if (empty($favoritos)): ?>
                <p>Você ainda não favoritou nenhuma entrega.</p>
            <?php else: ?>
                <div class="acordos-lista">
                    <?php foreach ($favoritos as $f):
                        $proposta_id = intval($f['ID']);
                        $produto_id = intval($f['produto_id']);
                        $img = $f['produto_imagem'] ? htmlspecialchars($f['produto_imagem']) : '../../img/placeholder.png';
                    ?>
                    <div class="acordo-card" data-proposta-id="<?php echo $proposta_id; ?>">
                        <div style="position:absolute;right:12px;top:12px;">
                            <button class="fav-btn favorited" data-proposta-id="<?php echo $proposta_id; ?>" title="Remover favorito" style="background:#fff;border:1px solid rgba(0,0,0,0.06);padding:6px;border-radius:8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#e74c3c" xmlns="http://www.w3.org/2000/svg"><path d="M12.1 21.35l-1.1-1.02C5.14 15.36 2 12.28 2 8.5 2 6 4 4 6.5 4c1.74 0 3.41.81 4.5 2.09C12.09 4.81 13.76 4 15.5 4 18 4 20 6 20 8.5c0 3.78-3.14 6.86-8.9 11.83l-1 .02z"/></svg>
                            </button>
                        </div>
                        <h3>Pedido #<?php echo $proposta_id; ?> &bull; <?php echo htmlspecialchars($f['produto_nome']); ?></h3>
                        <div class="thumb">
                            <a href="../visualizar_anuncio.php?anuncio_id=<?php echo $produto_id; ?>">
                                <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($f['produto_nome']); ?>">
                            </a>
                        </div>
                        <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($f['vendedor_nome']); ?></p>
                        <p><strong>Comprador:</strong> <?php echo htmlspecialchars($f['comprador_nome']); ?></p>
                        <?php
                            $origem = ($f['vendedor_rua'] ?? '') . ', ' . ($f['vendedor_numero'] ?? '') . ' - ' . ($f['vendedor_cidade'] ?? '') . '/' . ($f['vendedor_estado'] ?? '') . ' - CEP: ' . ($f['vendedor_cep'] ?? '');
                            $destino = ($f['comprador_rua'] ?? '') . ', ' . ($f['comprador_numero'] ?? '') . ' - ' . ($f['comprador_cidade'] ?? '') . '/' . ($f['comprador_estado'] ?? '') . ' - CEP: ' . ($f['comprador_cep'] ?? '');
                            $maps = 'https://www.google.com/maps/dir/?api=1&origin=' . urlencode($origem) . '&destination=' . urlencode($destino) . '&travelmode=driving';
                        ?>
                        <div style="margin-top:6px;font-size:0.95rem;">
                            Retirada: <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($origem); ?>" target="_blank"><?php echo htmlspecialchars($origem); ?></a><br>
                            Entrega: <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($destino); ?>" target="_blank"><?php echo htmlspecialchars($destino); ?></a><br>
                            <a href="<?php echo $maps; ?>" target="_blank" style="display:inline-flex;align-items:center;gap:8px;margin-top:6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" style="flex:0 0 16px;" aria-hidden="true"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>
                                Ver rota no Google Maps
                            </a>
                        </div>
                        <div class="acordo-actions">
                            <a href="../visualizar_anuncio.php?anuncio_id=<?php echo $produto_id; ?>" class="acordo-btn" style="background:#2566d6">Ver anúncio</a>
                            <button class="acordo-btn" onclick="startChat(<?php echo $proposta_id; ?>)" style="background:#2E7D32">Abrir chat</button>
                            <button class="acordo-btn remover-fav" data-proposta-id="<?php echo $proposta_id; ?>">Remover</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <script>
        // remover favorito a partir da lista
        document.querySelectorAll('.remover-fav').forEach(btn=>{
            btn.addEventListener('click', async (e)=>{
                const pid = btn.getAttribute('data-proposta-id');
                try{
                    const res = await fetch('toggle_favorito.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({proposta_id: pid}) });
                    const data = await res.json();
                    if(data.success){
                        // remover card da UI
                        const card = btn.closest('.acordo-card'); if(card) card.remove();
                    } else alert(data.erro||'Erro');
                }catch(err){ console.error(err); alert('Erro de conexão'); }
            });
        });

        // iniciar chat a partir dos favoritos (reutiliza endpoint de criação de conversa)
        async function startChat(propostaId){
            try{
                const form = new URLSearchParams();
                form.append('proposta_id', propostaId);
                const res = await fetch('../chat/create_conversa_transportador.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: form
                });
                const data = await res.json();
                if(data.success && data.conversa_id){
                    window.location.href = '../chat_transportador/chat_interface.php?conversa_id=' + data.conversa_id;
                } else {
                    alert(data.erro || 'Erro ao iniciar chat');
                }
            } catch(e){ console.error(e); alert('Erro de conexão ao iniciar chat'); }
        }
    </script>
</body>
</html>
