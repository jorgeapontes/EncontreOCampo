[file name]: minhas_propostas.php
[file content begin]
<?php
// src/comprador/minhas_propostas.php - ATUALIZADO

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();
$propostas = [];
$comprador_id = null;
$mensagem_sucesso = isset($_GET['sucesso']) ? htmlspecialchars($_GET['sucesso']) : null;
$mensagem_erro = isset($_GET['erro']) ? htmlspecialchars($_GET['erro']) : null;

// 2. OBTENDO O ID DO COMPRADOR
try {
    $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $resultado_comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if ($resultado_comprador) {
        $comprador_id = $resultado_comprador['id'];
    } else {
        die("Erro: ID de comprador não encontrado.");
    }
} catch (PDOException $e) {
    die("Erro ao buscar ID do comprador: " . $e->getMessage());
}

// 3. BUSCA DAS PROPOSTAS - ATUALIZADA
try {
    $sql = "SELECT 
                pn.id AS negociacao_id,
                pn.status AS negociacao_status,  -- Status da tabela propostas_negociacao
                pn.data_criacao,
                pn.data_atualizacao,
                pc.id AS proposta_comprador_id,
                pc.preco_proposto,
                pc.quantidade_proposta,
                pc.condicoes_compra AS condicoes_comprador,
                pc.data_proposta,
                pc.status AS status_comprador,  -- Status da tabela propostas_comprador (IMPORTANTE!)
                pv.id AS proposta_vendedor_id,
                pv.preco_proposto AS preco_vendedor,
                pv.quantidade_proposta AS quantidade_vendedor,
                pv.condicoes_venda AS condicoes_vendedor,
                pv.observacao AS observacoes_vendedor,
                pv.data_contra_proposta,
                p.nome AS produto_nome,
                p.unidade_medida,
                p.preco AS preco_anuncio_original,
                u.nome AS nome_vendedor,
                p.id AS produto_id
            FROM propostas_negociacao pn
            JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
            JOIN produtos p ON pn.produto_id = p.id
            JOIN vendedores v ON p.vendedor_id = v.id
            JOIN usuarios u ON v.usuario_id = u.id
            LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.id
            WHERE pc.comprador_id = :comprador_id
            ORDER BY 
                CASE 
                    WHEN pc.status = 'pendente' THEN 1  -- Prioridade para propostas com resposta do vendedor
                    WHEN pc.status = 'enviada' THEN 2   -- Depois propostas aguardando resposta
                    ELSE 3                             -- Por último as finalizadas
                END,
                pn.data_criacao DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt->execute();
    $propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // DEBUG: Verificar dados retornados
    error_log("DEBUG minhas_propostas.php - Total de propostas: " . count($propostas));
    foreach ($propostas as $index => $proposta) {
        error_log("DEBUG Proposta {$index}: Negociação=" . $proposta['negociacao_status'] . 
                 ", Comprador=" . $proposta['status_comprador'] . 
                 ", Tem contraproposta=" . (!empty($proposta['proposta_vendedor_id']) ? 'SIM' : 'NÃO'));
    }
    
} catch (PDOException $e) {
    die("Erro ao carregar propostas: " . $e->getMessage()); 
}

// Função para traduzir o status - ATUALIZADA
function formatarStatusComprador($status_negociacao, $status_comprador = null) {
    // Se o status da negociação for 'aceita' ou 'recusada', usa esses status
    if (in_array($status_negociacao, ['aceita', 'recusada'])) {
        $map = [
            'aceita' => ['text' => 'Aceita', 'class' => 'status-accepted'],
            'recusada' => ['text' => 'Recusada', 'class' => 'status-rejected'],
        ];
        return $map[$status_negociacao] ?? ['text' => ucfirst($status_negociacao), 'class' => 'status-default'];
    }
    
    // Se o status da negociação for 'negociacao', verifica o status do comprador
    if ($status_negociacao === 'negociacao') {
        if ($status_comprador === 'enviada') {
            return ['text' => 'Enviada', 'class' => 'status-negotiation']; // Azul - aguardando vendedor
        } elseif ($status_comprador === 'pendente') {
            return ['text' => 'Pendente', 'class' => 'status-pending']; // Laranja - vendedor respondeu
        } elseif (in_array($status_comprador, ['aceita', 'recusada'])) {
            // Fallback
            $map = [
                'aceita' => ['text' => 'Aceita', 'class' => 'status-accepted'],
                'recusada' => ['text' => 'Recusada', 'class' => 'status-rejected'],
            ];
            return $map[$status_comprador] ?? ['text' => ucfirst($status_comprador), 'class' => 'status-default'];
        }
    }
    
    // Fallback
    return ['text' => ucfirst($status_negociacao), 'class' => 'status-default'];
}

// Verificar se há contraproposta do vendedor - ATUALIZADA
function temContrapropostaVendedor($proposta) {
    // Verifica se há proposta do vendedor OU condições do vendedor
    return !empty($proposta['proposta_vendedor_id']) || 
           !empty($proposta['condicoes_vendedor']) || 
           !empty($proposta['observacoes_vendedor']);
}
?>

<script>
function confirmarExclusao(negociacaoId) {
    if (confirm('Tem certeza que deseja excluir esta proposta?\n\nEsta ação não pode ser desfeita.')) {
        window.location.href = 'excluir_proposta.php?id=' + negociacaoId;
    }
}

function responderContraproposta(negociacaoId, acao) {
    if (confirm('Tem certeza que deseja ' + acao + ' esta contraproposta?')) {
        window.location.href = 'processar_resposta.php?id=' + negociacaoId + '&action=' + acao;
    }
}
</script>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Propostas - Comprador</title>
    <link rel="stylesheet" href="../../index.css"> 
    <link rel="stylesheet" href="../css/comprador/comprador.css"> 
    <link rel="stylesheet" href="../css/comprador/minhas_propostas.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <img src="../../img/logo-nova.png" alt="Logo">
                <div>
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="../anuncios.php" class="nav-link">Comprar</a></li>
                <li class="nav-item"><a href="minhas_propostas.php" class="nav-link active">Minhas Propostas</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
            </ul>
        </div>
    </nav>

    <main class="container propostas-container">
        <h1>Minhas Propostas de Negociação</h1>
        <p>Acompanhe o status das propostas que você enviou aos vendedores.</p>

        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($propostas)): ?>
            <div class="empty-state">
                <h3>Você ainda não enviou nenhuma proposta.</h3>
                <p>Navegue em <a href="../anuncios.php">Anúncios Ativos</a> para começar a negociar!</p>
            </div>
        <?php else: ?>
            <div class="propostas-list">
                <?php foreach ($propostas as $proposta): 
                    $status_negociacao = $proposta['negociacao_status'];
                    $status_comprador = $proposta['status_comprador'];
                    $status_info = formatarStatusComprador($status_negociacao, $status_comprador);
                    $tem_contraproposta = temContrapropostaVendedor($proposta);
                    
                    // DEBUG no card (pode remover depois)
                    $debug_info = "N: {$status_negociacao}, C: {$status_comprador}";
                ?>
                    <div class="proposta-card">
                        <!-- DEBUG - remover depois -->
                        <div class="debug-info" style="display: none;">
                            <?php echo $debug_info; ?>
                        </div>
                        
                        <div class="proposta-header">
                            <h3>
                                Proposta para: <?php echo htmlspecialchars($proposta['produto_nome']); ?>
                            </h3>
                            <span class="status-badge <?php echo $status_info['class']; ?>">
                                <?php echo $status_info['text']; ?>
                            </span>
                        </div>
                        
                        <div class="proposta-info">
                            <div class="info-group">
                                <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($proposta['nome_vendedor']); ?></p>
                                <p><strong>Data da Proposta:</strong> <?php echo date('d/m/Y H:i', strtotime($proposta['data_proposta'])); ?></p>
                                <?php if ($proposta['data_atualizacao']): ?>
                                    <p><strong>Última Atualização:</strong> <?php echo date('d/m/Y H:i', strtotime($proposta['data_atualizacao'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="info-group">
                                <p><strong>Preço Proposto:</strong> <span><?php echo 'R$ ' . number_format($proposta['preco_proposto'], 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></span></p>
                                <p><strong>Preço Original:</strong> <?php echo 'R$ ' . number_format($proposta['preco_anuncio_original'], 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                            </div>
                            <div class="info-group">
                                <p><strong>Quantidade:</strong> <?php echo htmlspecialchars($proposta['quantidade_proposta']) . ' ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($proposta['condicoes_comprador'])): ?>
                            <div class="condicoes-section">
                                <strong>Suas Condições:</strong> 
                                <span class="condicoes-texto"><?php echo nl2br(htmlspecialchars($proposta['condicoes_comprador'])); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($tem_contraproposta): ?>
                            <div class="contraproposta-section">
                                <strong>Contraproposta do Vendedor (Enviada em: <?php echo date('d/m/Y H:i', strtotime($proposta['data_contra_proposta'])); ?>):</strong>
                                <div class="contraproposta-content">
                                    <?php if (!empty($proposta['observacoes_vendedor'])): ?>
                                        <p><strong>Observações:</strong> <?php echo nl2br(htmlspecialchars($proposta['observacoes_vendedor'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($proposta['preco_vendedor']): ?>
                                        <p><strong>Preço Proposto pelo Vendedor:</strong> R$ <?php echo number_format($proposta['preco_vendedor'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($proposta['quantidade_vendedor']): ?>
                                        <p><strong>Quantidade Proposta:</strong> <?php echo $proposta['quantidade_vendedor']; ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($proposta['condicoes_vendedor'])): ?>
                                        <p><strong>Condições do Vendedor:</strong> <?php echo nl2br(htmlspecialchars($proposta['condicoes_vendedor'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                </div>
                        <?php endif; ?>

                        <!-- BOTÕES DE AÇÃO PRINCIPAIS -->
                        <div class="proposta-actions">
                            <?php if ($status_negociacao === 'negociacao'): ?>
                                <?php if ($status_comprador === 'enviada'): ?>
                                    <!-- Comprador aguardando resposta do vendedor -->
                                    <a href="editar_proposta.php?id=<?php echo $proposta['negociacao_id']; ?>" class="btn btn-edit">
                                        <i class="fas fa-edit"></i>
                                        Alterar Detalhes
                                    </a>
                                    <button onclick="confirmarExclusao(<?php echo $proposta['negociacao_id']; ?>)" class="btn btn-delete">
                                        <i class="fas fa-trash"></i>
                                        Excluir Proposta
                                    </button>
                                <?php elseif ($status_comprador === 'pendente' && $tem_contraproposta): ?>
                                    <!-- Comprador precisa responder à contraproposta do vendedor -->
                                    <div class="contraproposta-actions">
                                        <button onclick="responderContraproposta(<?php echo $proposta['negociacao_id']; ?>, 'aceitar')" class="btn btn-success">
                                            <i class="fas fa-check"></i>
                                            Aceitar Contraproposta
                                        </button>
                                        <button onclick="responderContraproposta(<?php echo $proposta['negociacao_id']; ?>, 'recusar')" class="btn btn-danger">
                                            <i class="fas fa-times"></i>
                                            Recusar Contraproposta
                                        </button>
                                        <a href="fazer_contraproposta.php?id=<?php echo $proposta['negociacao_id']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i>
                                            Fazer Contraproposta
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
[file content end]