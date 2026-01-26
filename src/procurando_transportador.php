<?php
// src/procurando_transportador.php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissions.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f5f5f5; min-height: 100vh; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        .nav-container { max-width: 1400px; margin: 0 auto; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: #2E7D32; }
        .logo img { width: 50px; height: 50px; }
        .main-content { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .conversas-container { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .conversas-header { padding: 1.5rem 2rem; background: #f9f9f9; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
        .conversas-header h2 { font-size: 20px; color: #333; }
        .conversas-list { max-height: 700px; overflow-y: auto; }
        .conversa-card { padding: 1.5rem 2rem; border-bottom: 1px solid #e0e0e0; display: flex; gap: 1.5rem; align-items: center; transition: background 0.2s; color: inherit; }
        .conversa-card:hover { background: #f9f9f9; }
        .produto-thumb { width: 80px; height: 80px; border-radius: 8px; overflow: hidden; flex-shrink: 0; }
        .produto-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .conversa-info { flex: 1; min-width: 0; }
        .produto-nome-principal { font-weight: 700; color: #333; font-size: 16px; }
        .conversa-data { font-size: 13px; color: #999; }
        .ultima-mensagem { font-size: 14px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 500px; }
        .conversa-actions { display: flex; gap: 8px; align-items: center; }
        .btn-chat { background: #2E7D32; color: white; padding: 10px 18px; border-radius: 5px; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; border: none; }
        .btn-chat:hover { background: #1B5E20; }
        .empty-state { padding: 4rem 2rem; text-align: center; color: #999; }
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
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="comprador/dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="comprador/perfil.php" class="nav-link">Meu Perfil</a></li>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <div class="conversas-container">
            <div class="conversas-header">
                <h2>Conversas com Transportadores</h2>
                <div><small>Conversas onde um transportador está associado ao chat.</small></div>
            </div>

            <div class="conversas-list">
                <?php if (count($conversas) > 0): ?>
                    <?php foreach ($conversas as $conversa):
                        $imagem = $conversa['produto_imagem'] ? htmlspecialchars($conversa['produto_imagem']) : '../img/placeholder.png';
                        $data_formatada = $conversa['ultima_mensagem_data'] ? date('d/m/Y H:i', strtotime($conversa['ultima_mensagem_data'])) : '';
                    ?>
                    <div class="conversa-card" id="conversa-<?php echo $conversa['conversa_id']; ?>">
                        <div class="produto-thumb">
                            <img src="<?php echo $imagem; ?>" alt="<?php echo htmlspecialchars($conversa['produto_nome']); ?>">
                        </div>
                        <div class="conversa-info">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div class="produto-nome-principal"><?php echo htmlspecialchars($conversa['produto_nome']); ?></div>
                                <div class="conversa-data"><?php echo $data_formatada; ?></div>
                            </div>
                            <div style="margin-top:8px;color:#666;"><strong>Transportador:</strong> <?php echo htmlspecialchars($conversa['transportador_nome'] ?? 'Transportador'); ?></div>
                            <?php if ($conversa['ultima_mensagem']): ?>
                                <div class="ultima-mensagem" style="margin-top:8px;"><i class="fas fa-comment"></i> <?php echo htmlspecialchars($conversa['ultima_mensagem']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="conversa-actions">
                            <a href="chat_transportador/chat_interface.php?conversa_id=<?php echo $conversa['conversa_id']; ?>" class="btn-chat"><i class="fas fa-comments"></i> Abrir Chat</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments" style="font-size:48px;margin-bottom:12px;"></i>
                        <h3>Nenhuma conversa com transportador encontrada</h3>
                        <p>Quando transportadores entrarem em contato, as conversas aparecerão aqui.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
<?php
// src/procurando_transportador.php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissions.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$db = $database->getConnection();

// Buscar propostas de frete recebidas para acordos do comprador
$sql = "SELECT pf.id as proposta_frete_id, pf.valor_frete, pf.status, pf.data_envio, 
               t.nome_comercial as transportador_nome, 
               p.id as proposta_id, pr.nome AS produto_nome, 
               p.quantidade_proposta as quantidade, p.valor_total, pr.imagem_url
        FROM propostas_frete_transportador pf
        INNER JOIN propostas p ON pf.proposta_id = p.id
        INNER JOIN produtos pr ON p.produto_id = pr.id
        INNER JOIN transportadores t ON pf.transportador_id = t.id
        WHERE p.comprador_id = :usuario_id AND pf.status IN ('pendente','contraproposta')
        ORDER BY pf.data_envio DESC";

$stmt = $db->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Propostas de Frete Recebidas</title>
    <link rel="stylesheet" href="../index.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<Style>
    
.propostas-list {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-bottom: 40px;
}

.proposta-card {
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(44, 62, 80, 0.07);
    padding: 28px 32px 20px 32px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    transition: box-shadow 0.2s;
    border: 1px solid var(--gray);
}
.proposta-card:hover {
    box-shadow: 0 6px 24px rgba(44, 62, 80, 0.13);
}
.proposta-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.proposta-pedido {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
}
.proposta-status {
    font-size: 0.95rem;
    font-weight: 600;
    padding: 4px 14px;
    border-radius: 16px;
    background: var(--gray);
    color: var(--text-light);
    text-transform: capitalize;
}
.status-pendente { background: #fffbe6; color: #bfa100; }
.status-contraproposta { background: #e3f2fd; color: #1976d2; }
.status-aceita { background: #e8f5e9; color: #388e3c; }
.status-recusada { background: #ffebee; color: #c62828; }
.proposta-dados {
    display: flex;
    gap: 32px;
    font-size: 1rem;
    color: var(--text-color);
    margin-bottom: 6px;
}
.proposta-acoes {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.btn-proposta {
    border: none;
    border-radius: 8px;
    padding: 8px 18px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    margin-right: 4px;
}
.btn-proposta.aceitar {
    background: var(--primary-color);
    color: var(--white);
}
.btn-proposta.aceitar:hover {
    background: var(--primary-dark);
}
.btn-proposta.recusar {
    background: #ffebee;
    color: #c62828;
}
.btn-proposta.recusar:hover {
    background: #ffcdd2;
}
.btn-proposta.contraproposta {
    background: #e0e0e0;
    color: var(--text-color);
    border: 1px solid #bdbdbd;
}
.btn-proposta.contraproposta:hover {
    background: #cccccc;
    color: var(--text-color);
}

.nav-link.exit-button {
    background: #e53935;
    color: #fff !important;
    border-radius: 18px;
    padding: 8px 20px;
    margin-left: 10px;
    transition: background 0.2s;
    font-weight: 600;
}
.nav-link.exit-button:hover {
    background: #b71c1c;
    color: #fff !important;
}
.input-contraproposta {
    border: 1px solid var(--gray);
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 1rem;
    width: 200px;
    margin-right: 6px;
    outline: none;
    transition: border 0.2s;
}
.input-contraproposta:focus {
    border: 1.5px solid var(--primary-color);
}

/* Mensagens de sucesso/erro */
.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 12px;
}
.alert-success {
    background: #e8f5e9;
    color: #2e7d32;
    border-left: 4px solid #4caf50;
}
.alert-error {
    background: #ffebee;
    color: #c62828;
    border-left: 4px solid #f44336;
}
.alert i {
    font-size: 1.3rem;
}
</Style>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="comprador/dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="comprador/perfil.php" class="nav-link">Meu Perfil</a></li>
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
    
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        async function abrirChat(propostaFreteId) {
            try {
                const form = new URLSearchParams();
                form.append('proposta_frete_id', propostaFreteId);
                const res = await fetch('chat/create_conversa_from_proposta_frete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: form
                });
                const data = await res.json();
                if (data.success && data.conversa_id) {
                    window.location.href = 'chat_transportador/chat_interface.php?conversa_id=' + data.conversa_id;
                } else {
                    alert(data.erro || 'Erro ao abrir chat');
                }
            } catch (e) {
                console.error(e);
                alert('Erro de conexão ao abrir chat');
            }
        }
    </script>
</body>
</html>