<?php
// src/comprador/minhas_propostas.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id']; // ID do usuário logado

$database = new Database();
$conn = $database->getConnection();
$propostas = [];
$comprador_id = null;
$mensagem_sucesso = isset($_GET['sucesso']) ? htmlspecialchars($_GET['sucesso']) : null;
$mensagem_erro = isset($_GET['erro']) ? htmlspecialchars($_GET['erro']) : null;


// 2. OBTENDO O ID DO COMPRADOR (ID da tabela 'compradores')
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

// 3. BUSCA DAS PROPOSTAS
try {
    // Busca todas as propostas do comprador, unindo com a tabela produtos para obter o nome
    $sql = "SELECT 
                pn.id AS proposta_id,
                pn.data_proposta,
                pn.preco_proposto,
                pn.quantidade_proposta,
                pn.condicoes_comprador,
                pn.status,
                p.nome AS produto_nome,
                p.unidade_medida,
                p.preco AS preco_anuncio_original,
                u.nome AS nome_vendedor
            FROM propostas_negociacao pn
            JOIN produtos p ON pn.produto_id = p.id
            JOIN vendedores v ON p.vendedor_id = v.id
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE pn.comprador_id = :comprador_id
            ORDER BY pn.data_proposta DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt->execute();
    $propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar propostas: " . $e->getMessage()); 
}

// Função para traduzir o status para um texto amigável e classe CSS
function formatarStatus($status) {
    $map = [
        'pendente' => ['text' => 'Pendente', 'class' => 'status-pending'],
        'aceita' => ['text' => 'Aceita', 'class' => 'status-accepted'],
        'recusada' => ['text' => 'Recusada', 'class' => 'status-rejected'],
        'negociacao' => ['text' => 'Em Negociação', 'class' => 'status-negotiation'],
    ];
    return $map[$status] ?? ['text' => ucfirst($status), 'class' => 'status-default'];
}
?>

<script>
function confirmarExclusao(propostaId) {
    if (confirm('Tem certeza que deseja excluir esta proposta?\n\nEsta ação não pode ser desfeita.')) {
        window.location.href = 'excluir_proposta.php?id=' + propostaId;
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
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
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
                <li class="nav-item"><a href="../logout.php" class="nav-link logout">Sair</a></li>
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
                    $status_info = formatarStatus($proposta['status']);
                ?>
                    <div class="proposta-card">
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
                            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #f0f0f0;">
                                <strong>Condições:</strong> <span style="font-style: italic; color: var(--text-light);"><?php echo nl2br(htmlspecialchars($proposta['condicoes_comprador'])); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="proposta-actions" style="text-align: right; margin-top: 15px;">
                            <?php if ($proposta['status'] === 'pendente' || $proposta['status'] === 'negociacao'): ?>
                                <a href="editar_proposta.php?id=<?php echo $proposta['proposta_id']; ?>" class="btn btn-secondary" style="background-color: #FF9800; color: white;">
                                    <i class="fas fa-edit"></i>
                                    Alterar Detalhes
                                </a>
                                <button onclick="confirmarExclusao(<?php echo $proposta['proposta_id']; ?>)" class="btn btn-danger" style="background-color: #dc3545; color: white; border: none; cursor: pointer;">
                                    <i class="fas fa-trash"></i>
                                    Excluir Proposta
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>