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
        .produto-thumb { width: 80px; height: 80px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: #e0e0e0; display: flex; align-items: center; justify-content: center; position: relative; }
        .produto-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .produto-thumb .placeholder-icon { font-size: 32px; color: #999; }
        .debug-info { font-size: 10px; color: red; background: #fff; padding: 2px 4px; position: absolute; bottom: 0; left: 0; right: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .conversa-info { flex: 1; min-width: 0; }
        .produto-nome-principal { font-weight: 700; color: #333; font-size: 16px; }
        .conversa-data { font-size: 13px; color: #999; }
        .ultima-mensagem { font-size: 14px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 500px; }
        .conversa-actions { display: flex; gap: 8px; align-items: center; }
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

.nav-link.active {
    color: #fff;
}

.nav-link.active::after {
    width: 100%;
}

.nav-link:hover {
    color: var(--primary-color);
}

.nav-link:hover::after {
    width: 100%;
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

            <div class="conversas-list">
                <?php if (count($conversas) > 0): ?>
                    <?php foreach ($conversas as $conversa):
                        // DEBUG: Informações sobre o caminho da imagem
                        $imagem_db = $conversa['produto_imagem'];
                        
                        // Testa diferentes possibilidades de caminho
                        $opcoes_caminho = [
                            'original' => $imagem_db,
                            'sem_barra' => str_replace('../', '', $imagem_db),
                            'com_barra' => '../' . str_replace('../', '', $imagem_db),
                            'raiz' => '/' . str_replace('../', '', $imagem_db),
                        ];
                        
                        // Encontra qual caminho existe
                        $imagem_final = '../img/placeholder.png';
                        $caminho_usado = 'nenhum';
                        
                        foreach ($opcoes_caminho as $tipo => $caminho) {
                            if ($caminho && file_exists(__DIR__ . '/' . $caminho)) {
                                $imagem_final = $caminho;
                                $caminho_usado = $tipo;
                                break;
                            }
                        }
                        
                        $data_formatada = $conversa['ultima_mensagem_data'] ? date('d/m/Y H:i', strtotime($conversa['ultima_mensagem_data'])) : '';
                    ?>
                    <div class="conversa-card" id="conversa-<?php echo $conversa['conversa_id']; ?>">
                        <div class="produto-thumb">
                            <img src="<?php echo htmlspecialchars($imagem_final); ?>" 
                                 alt="<?php echo htmlspecialchars($conversa['produto_nome']); ?>"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <i class="fas fa-image placeholder-icon" style="display: none;"></i>

                        </div>
                        <div class="conversa-info">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div class="produto-nome-principal"><?php echo htmlspecialchars($conversa['produto_nome']); ?></div>
                                <div class="conversa-data"><?php echo $data_formatada; ?></div>
                            </div>
                            <div style="margin-top:8px;color:#666;">
                                <strong>Transportador:</strong> <?php echo htmlspecialchars($conversa['transportador_nome'] ?? 'Transportador'); ?>
                            </div>
                            <?php if ($conversa['ultima_mensagem']): ?>
                                <div class="ultima-mensagem" style="margin-top:8px;">
                                    <i class="fas fa-comment"></i> <?php echo htmlspecialchars($conversa['ultima_mensagem']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="conversa-actions">
                            <a href="chat_transportador/chat_interface.php?conversa_id=<?php echo $conversa['conversa_id']; ?>" class="btn-chat">
                                <i class="fas fa-comments"></i> Abrir Chat
                            </a>
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