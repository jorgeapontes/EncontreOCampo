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
    <main class="container" style="margin-top: 100px;">
        <h1 style="font-size:2rem; font-weight:700; margin-bottom: 10px;">Propostas de Frete Recebidas</h1>
        <p style="color:var(--text-light); margin-bottom: 30px;">Veja e gerencie as propostas de frete enviadas pelos transportadores.</p>
        
        <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_GET['sucesso']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_GET['erro']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (count($propostas) === 0): ?>
            <div class="empty-state" style="text-align:center; margin: 60px 0; color:var(--text-light);">
                <h3 style="font-weight:600;">Nenhuma proposta de frete recebida no momento.</h3>
            </div>
        <?php else: ?>
        <div class="propostas-list">
            <?php foreach ($propostas as $p):
                $img_url = isset($p['imagem_url']) && $p['imagem_url'] ? htmlspecialchars($p['imagem_url']) : '../img/no-user-image.png';
            ?>
            <div class="proposta-card">
                <div style="display:flex; gap:24px; align-items:center;">
                    <img src="<?php echo $img_url; ?>" alt="<?php echo htmlspecialchars($p['produto_nome']); ?>" style="width:90px; height:90px; object-fit:cover; border-radius:10px; background:#f5f5f5; border:1px solid #eee;">
                    <div class="proposta-info" style="flex:1;">
                        <div class="proposta-header">
                            <span class="proposta-pedido">#<?php echo $p['proposta_id']; ?> - <?php echo htmlspecialchars($p['produto_nome']); ?> (<?php echo $p['quantidade']; ?>)</span>
                            <span class="proposta-status status-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span>
                        </div>
                        <div class="proposta-dados">
                            <span class="proposta-transportador"><b>Transportador:</b> <?php echo isset($p['transportador_nome']) && $p['transportador_nome'] ? htmlspecialchars($p['transportador_nome']) : 'Não informado'; ?></span>
                            <span class="proposta-valor"><b>Frete:</b> R$ <?php echo number_format($p['valor_frete'], 2, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="proposta-acoes">
                    <form action="responder_proposta_frete.php" method="POST" style="display:inline;">
                        <input type="hidden" name="proposta_frete_id" value="<?php echo $p['proposta_frete_id']; ?>">
                        <button type="submit" name="acao" value="aceitar" class="btn-proposta aceitar">Aceitar</button>
                        <button type="submit" name="acao" value="recusar" class="btn-proposta recusar">Recusar</button>
                    </form>
                    <form action="responder_proposta_frete.php" method="POST" style="display:inline;">
                        <input type="hidden" name="proposta_frete_id" value="<?php echo $p['proposta_frete_id']; ?>">
                        <input type="number" step="0.01" min="0" name="novo_valor" placeholder="Contra-proposta" required class="input-contraproposta">
                        <button type="submit" name="acao" value="contraproposta" class="btn-proposta contraproposta">Enviar Contra-proposta</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
    
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>