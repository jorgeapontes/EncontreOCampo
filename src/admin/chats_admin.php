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
                CASE 
                    WHEN cc.transportador_id IS NOT NULL THEN 'transportador' 
                    ELSE 'vendedor' 
                END AS tipo_conversa,
                -- Informações do outro participante (transportador OU vendedor)
                CASE 
                    WHEN cc.transportador_id IS NOT NULL THEN ut.id
                    ELSE uv.id
                END AS outro_participante_id,
                CASE 
                    WHEN cc.transportador_id IS NOT NULL THEN ut.nome
                    ELSE uv.nome
                END AS outro_participante_nome,
                CASE 
                    WHEN cc.transportador_id IS NOT NULL THEN ut.email
                    ELSE uv.email
                END AS outro_participante_email,
                -- CPF/CNPJ apenas para vendedor, transportador não tem
                CASE 
                    WHEN cc.transportador_id IS NOT NULL THEN NULL
                    ELSE vend.cpf_cnpj
                END AS outro_participante_cpf_cnpj,
                (SELECT COUNT(*) FROM chat_mensagens 
                 WHERE conversa_id = cc.id) AS total_mensagens
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN usuarios uc ON cc.comprador_id = uc.id
            LEFT JOIN compradores comp ON comp.usuario_id = uc.id
            LEFT JOIN vendedores vend ON p.vendedor_id = vend.id
            LEFT JOIN usuarios uv ON vend.usuario_id = uv.id
            LEFT JOIN transportadores trans ON cc.transportador_id = trans.usuario_id
            LEFT JOIN usuarios ut ON cc.transportador_id = ut.id
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
    <link rel="stylesheet" href="css/chats_admin.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                <a href="manage_comprovantes.php" class="nav-link">Comprovantes</a>
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