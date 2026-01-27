<?php
// src/admin/chats_admin.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// PROCESSAR EXCLUSÃO DE CONVERSA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_conversa'])) {
    $conversa_id_excluir = (int)$_POST['conversa_id'];
    
    try {
        // Iniciar transação para garantir que tudo seja excluído corretamente
        $conn->beginTransaction();
        
        // 1. Excluir todas as mensagens da conversa
        $sql_delete_mensagens = "DELETE FROM chat_mensagens WHERE conversa_id = :conversa_id";
        $stmt_delete_mensagens = $conn->prepare($sql_delete_mensagens);
        $stmt_delete_mensagens->bindParam(':conversa_id', $conversa_id_excluir, PDO::PARAM_INT);
        $stmt_delete_mensagens->execute();
        
        // 2. Excluir registros de auditoria relacionados à conversa
        $sql_delete_auditoria = "DELETE FROM chat_auditoria WHERE conversa_id = :conversa_id";
        $stmt_delete_auditoria = $conn->prepare($sql_delete_auditoria);
        $stmt_delete_auditoria->bindParam(':conversa_id', $conversa_id_excluir, PDO::PARAM_INT);
        $stmt_delete_auditoria->execute();
        
        // 3. Excluir a conversa
        $sql_delete_conversa = "DELETE FROM chat_conversas WHERE id = :conversa_id";
        $stmt_delete_conversa = $conn->prepare($sql_delete_conversa);
        $stmt_delete_conversa->bindParam(':conversa_id', $conversa_id_excluir, PDO::PARAM_INT);
        $stmt_delete_conversa->execute();
        
        // Registrar no log de auditoria (se quiser manter um histórico)
        // Antes de excluir tudo, podemos salvar em uma tabela de backup se necessário
        
        $conn->commit();
        
        $mensagem_sucesso = "Conversa excluída permanentemente do banco de dados!";
        
        // Redirecionar para evitar reenvio do formulário
        header("Location: chats_admin.php?success=1&msg=" . urlencode($mensagem_sucesso));
        exit();
        
    } catch (PDOException $e) {
        // Reverter em caso de erro
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Erro ao excluir conversa: " . $e->getMessage());
        $mensagem_erro = "Erro ao excluir conversa. Tente novamente.";
    }
}

// Verificar se veio de um redirecionamento com sucesso
$success = isset($_GET['success']) && $_GET['success'] == 1;
$success_msg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';

$filtro_busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$ordenacao = isset($_GET['ordenacao']) ? $_GET['ordenacao'] : 'recente';

// Buscar TODAS as conversas (EXCLUÍDAS NÃO APARECEM MAIS)
try {
    $sql = "SELECT 
                cc.id AS conversa_id,
                cc.produto_id,
                cc.transportador_id,
                cc.ultima_mensagem,
                cc.ultima_mensagem_data,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                uc.id AS comprador_id,
                uc.nome AS comprador_nome,
                uc.email AS comprador_email,
                comp.cpf_cnpj AS comprador_cpf_cnpj,
                CASE WHEN cc.transportador_id IS NOT NULL THEN 'transportador' ELSE 'vendedor' END AS tipo_conversa,
                COALESCE(uv.id, ut.id) AS outro_participante_id,
                COALESCE(uv.nome, ut.nome) AS outro_participante_nome,
                COALESCE(uv.email, ut.email) AS outro_participante_email,
                COALESCE(vend.cpf_cnpj, NULL) AS outro_participante_cpf_cnpj,
                (SELECT COUNT(*) FROM chat_mensagens 
                 WHERE conversa_id = cc.id) AS total_mensagens
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN usuarios uc ON cc.comprador_id = uc.id
            LEFT JOIN compradores comp ON comp.usuario_id = uc.id
            LEFT JOIN vendedores vend ON p.vendedor_id = vend.id
            LEFT JOIN usuarios uv ON vend.usuario_id = uv.id
            LEFT JOIN transportadores trans ON cc.transportador_id = trans.id
            LEFT JOIN usuarios ut ON trans.usuario_id = ut.id
            WHERE 1=1";
    
    // Filtro de busca
    if ($filtro_busca) {
        // Remover pontuação para busca de CPF/CNPJ
        $busca_limpa = preg_replace('/[^0-9]/', '', $filtro_busca);
        
        // Busca por nome, email, CPF ou CNPJ
        $sql .= " AND (
            uc.nome LIKE :busca OR 
            COALESCE(uv.nome, ut.nome) LIKE :busca OR 
            p.nome LIKE :busca OR
            uc.email LIKE :busca OR
            COALESCE(uv.email, ut.email) LIKE :busca";
        
        // Se tem números, busca também por CPF/CNPJ
        if ($busca_limpa) {
            $sql .= " OR REPLACE(REPLACE(REPLACE(REPLACE(comp.cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') LIKE :busca_limpa
                     OR REPLACE(REPLACE(REPLACE(REPLACE(vend.cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') LIKE :busca_limpa";
        }
        
        $sql .= ")";
    }
    
    // Ordenação
    switch ($ordenacao) {
        case 'alfabetica':
            $sql .= " ORDER BY p.nome ASC";
            break;
        case 'antiga':
            $sql .= " ORDER BY cc.ultima_mensagem_data ASC";
            break;
        case 'recente':
        default:
            $sql .= " ORDER BY cc.ultima_mensagem_data DESC";
            break;
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($filtro_busca) {
        $busca_like = "%{$filtro_busca}%";
        $stmt->bindParam(':busca', $busca_like);
        
        // Se tem números, busca também por CPF/CNPJ
        $busca_limpa = preg_replace('/[^0-9]/', '', $filtro_busca);
        if ($busca_limpa) {
            $busca_limpa_like = "%{$busca_limpa}%";
            $stmt->bindParam(':busca_limpa', $busca_limpa_like);
        }
    }
    
    $stmt->execute();
    $conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar conversas: " . $e->getMessage());
    $conversas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria de Chats - Admin</title>
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --white: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }
        
        /* Navbar */
        .navbar {
            background-color: var(--white);
            color: #333;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
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

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-logo h1{
            font-size: 1.5rem;
            color: #4CAF50;
            font-weight: 700;
            letter-spacing: 0px;
            line-height: 1.6;
            margin-top: 4px;
        }

        .nav-logo h2{
            font-size: 1.1rem;
            color: #2E7D32;
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: -10px;
            line-height: 1.3;
        }

        .logo {
            height: 60px;
            width: auto;
            border-radius: 4px;
        }

        .brand-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .nav-link {
            color: #333;
            text-decoration: none;
            padding: 15px 0;
            font-weight: 500;
            transition: color 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .nav-link:hover {
            color: #2E7D32;
            border-bottom: 3px solid #2E7D32;
            transition: width 0.3s ease;
            
        }

        .nav-link.active {
            color: #2E7D32;
            border-bottom-color: #2E7D32;
        }

        .nav-link.logout {
            color: #fff;
            background-color: rgb(230, 30, 30);
            padding: 3px 20px;
            border-radius: 20px;
        }

        .nav-link.logout:hover {
            color: #ffffff;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #2E7D32;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: start;
        }
        
        .search-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filters input, .filters select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .filters input[type="text"] {
            width: 100%;
        }
        
        .filters select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 180px;
        }
        
        .filters select:hover {
            border-color: #007bff;
        }
        
        .filters button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .filters button:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,123,255,0.3);
        }
        
        .conversas-list {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .conversa-item {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        
        .produto-thumb {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .produto-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversa-info {
            flex: 1;
        }
        
        .conversa-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .produto-nome {
            font-weight: 700;
            font-size: 16px;
            color: #333;
        }
        
        .conversa-data {
            color: #999;
            font-size: 13px;
        }
        
        .usuarios-info {
            display: flex;
            gap: 30px;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .usuario-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .usuario-item i {
            color: #007bff;
            margin-right: 5px;
        }
        
        .usuario-item .cpf-cnpj {
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .badge-mensagens {
            background: #28a745;
            color: white;
        }
        
        .actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
            width: 100%;
        }
        
        .btn-view {
            background: #007bff;
            color: white;
        }
        
        .btn-view:hover {
            background: #0056b3;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-item i {
            color: #007bff;
            font-size: 20px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .search-hint {
            font-size: 11px;
            color: #999;
            font-style: italic;
            margin-top: 5px;
        }
        
        /* Estilo para o botão de limpar busca */
        .search-wrapper {
            position: relative;
        }
        
        .btn-clear-search {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            display: none;
            padding: 0;
        }
        
        .btn-clear-search.show {
            display: block;
        }
        
        .btn-clear-search:hover {
            color: #dc3545;
        }
        
        @media (max-width: 992px) {
            .filters {
                flex-wrap: wrap;
            }
            
            .search-group {
                flex: 1 1 100%;
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                flex-wrap: wrap;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="../../img/logo-nova.png" class="logo" alt="Logo">
                <div>
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="todos_usuarios.php" class="nav-link">Todos os Usuários</a>
                <a href="chats_admin.php" class="nav-link active">Chats</a>
                <a href="../../index.php" class="nav-link">Home</a>
                <a href="../logout.php" class="nav-link logout">Sair</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($mensagem_erro)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>
        
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Auditoria de Chats</h1>
            <p>Visualize todas as conversas do sistema para fins de auditoria e conformidade legal</p>
            
            <div class="stats">
                <div class="stat-item">
                    <i class="fas fa-comments"></i>
                    <div>
                        <div class="stat-value"><?php echo count($conversas); ?></div>
                        <div class="stat-label">Total de Conversas Ativas</div>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="GET" class="filters" id="formFiltros">
            <div class="search-group">
                <div class="search-wrapper">
                    <input type="text" name="busca" id="inputBusca" placeholder="Buscar por nome, email, CPF ou CNPJ..." 
                           value="<?php echo htmlspecialchars($filtro_busca); ?>">
                    <button type="button" class="btn-clear-search <?php echo $filtro_busca ? 'show' : ''; ?>" 
                            id="btnLimpar" title="Limpar busca">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
                <div class="search-hint">
                    <i class="fas fa-info-circle"></i> Digite CPF/CNPJ com ou sem pontuação
                </div>
            </div>
            
            <select name="ordenacao" id="selectOrdenacao">
                <option value="recente" <?php echo $ordenacao === 'recente' ? 'selected' : ''; ?>>
                    Mais Recentes
                </option>
                <option value="antiga" <?php echo $ordenacao === 'antiga' ? 'selected' : ''; ?>>
                    Mais Antigas
                </option>
                <option value="alfabetica" <?php echo $ordenacao === 'alfabetica' ? 'selected' : ''; ?>>
                    Ordem Alfabética
                </option>
            </select>
            
            <button type="submit">
                <i class="fas fa-search"></i> Filtrar
            </button>
        </form>
        
        <div class="conversas-list">
            <?php if (count($conversas) > 0): ?>
                <?php foreach ($conversas as $conversa): 
                    $imagem = $conversa['produto_imagem'] ?: '../../img/placeholder.png';
                ?>
                    <div class="conversa-item">
                        <div class="produto-thumb">
                            <img src="<?php echo htmlspecialchars($imagem); ?>" alt="Produto">
                        </div>
                        
                        <div class="conversa-info">
                            <div class="conversa-header">
                                <div>
                                    <span class="produto-nome">
                                        <?php echo htmlspecialchars($conversa['produto_nome']); ?>
                                    </span>
                                    <span class="badge badge-mensagens">
                                        <?php echo $conversa['total_mensagens']; ?> mensagens
                                    </span>
                                </div>
                                <div class="conversa-data">
                                    <?php echo date('d/m/Y H:i', strtotime($conversa['ultima_mensagem_data'])); ?>
                                </div>
                            </div>
                            
                            <div class="usuarios-info">
                                <div class="usuario-item">
                                    <div>
                                        <i class="fas fa-user"></i>
                                        <strong>Comprador:</strong>
                                        <?php echo htmlspecialchars($conversa['comprador_nome']); ?>
                                    </div>
                                    <div style="margin-left: 20px;">
                                        <?php echo htmlspecialchars($conversa['comprador_email']); ?>
                                    </div>
                                    <?php if ($conversa['comprador_cpf_cnpj']): ?>
                                        <div class="cpf-cnpj" style="margin-left: 20px;">
                                            <i class="fas fa-id-card"></i>
                                            <?php echo htmlspecialchars($conversa['comprador_cpf_cnpj']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="usuario-item">
                                    <div>
                                        <?php if ($conversa['tipo_conversa'] === 'transportador'): ?>
                                            <i class="fas fa-truck"></i>
                                            <strong>Transportador:</strong>
                                        <?php else: ?>
                                            <i class="fas fa-store"></i>
                                            <strong>Vendedor:</strong>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($conversa['outro_participante_nome']); ?>
                                    </div>
                                    <div style="margin-left: 20px;">
                                        <?php echo htmlspecialchars($conversa['outro_participante_email']); ?>
                                    </div>
                                    <?php if ($conversa['outro_participante_cpf_cnpj']): ?>
                                        <div class="cpf-cnpj" style="margin-left: 20px;">
                                            <i class="fas fa-id-card"></i>
                                            <?php echo htmlspecialchars($conversa['outro_participante_cpf_cnpj']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="actions">
                            <a href="visualizar_chat.php?conversa_id=<?php echo $conversa['conversa_id']; ?>" 
                               class="btn btn-view">
                                <i class="fas fa-eye"></i> Visualizar Completo
                            </a>
                            
                            <form method="POST" style="margin: 0;" onsubmit="return confirmarExclusaoPermanente();">
                                <input type="hidden" name="conversa_id" value="<?php echo $conversa['conversa_id']; ?>">
                                <button type="submit" name="excluir_conversa" class="btn btn-delete">
                                    <i class="fas fa-trash"></i> Excluir 
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Nenhuma conversa encontrada</h3>
                    <p>Tente ajustar os filtros de busca</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function confirmarExclusaoPermanente() {
            return confirm('🚨 ALERTA CRÍTICO!\n\nVocê está prestes a EXCLUIR PERMANENTEMENTE esta conversa!\n\n' +
                          '⚠️  ESTA AÇÃO É IRREVERSÍVEL!\n\n' +
                          '• Todas as mensagens serão EXCLUÍDAS DO BANCO DE DADOS\n' +
                          '• A conversa será REMOVIDA PERMANENTEMENTE\n' +
                          '• NÃO HAVERÁ BACKUP AUTOMÁTICO\n' +
                          '• Os usuários NÃO terão mais acesso ao histórico\n\n' +
                          'Tem absoluta certeza que deseja continuar?\n\n' +
                          'Digite "EXCLUIR" para confirmar:');
            
            // Para adicionar validação extra, você pode usar:
            // var confirmacao = prompt('Digite "EXCLUIR" para confirmar a exclusão permanente:');
            // return confirmacao === 'EXCLUIR';
        }
        
        // Limpar busca
        const inputBusca = document.getElementById('inputBusca');
        const btnLimpar = document.getElementById('btnLimpar');
        
        if (inputBusca && btnLimpar) {
            // Mostrar/ocultar botão X conforme há texto
            inputBusca.addEventListener('input', function() {
                if (this.value.trim()) {
                    btnLimpar.classList.add('show');
                } else {
                    btnLimpar.classList.remove('show');
                }
            });
            
            // Limpar busca ao clicar no X
            btnLimpar.addEventListener('click', function() {
                inputBusca.value = '';
                btnLimpar.classList.remove('show');
                // Recarregar sem busca
                const form = document.getElementById('formFiltros');
                const ordenacao = document.getElementById('selectOrdenacao').value;
                window.location.href = '?ordenacao=' + ordenacao;
            });
        }
        
        // Auto-submit ao mudar ordenação
        const selectOrdenacao = document.getElementById('selectOrdenacao');
        if (selectOrdenacao) {
            selectOrdenacao.addEventListener('change', function() {
                document.getElementById('formFiltros').submit();
            });
        }
        
        // Auto-fechar mensagem de sucesso após 5 segundos
        <?php if ($success): ?>
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>