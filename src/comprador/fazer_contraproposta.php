<?php
// src/comprador/fazer_contraproposta.php

session_start();
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: minhas_propostas.php?erro=" . urlencode("ID da negociação inválido."));
    exit();
}

$negociacao_id = $_GET['id'];
$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

// Buscar dados da negociação atual
try {
    $sql = "SELECT 
                pn.*,
                pc.id AS proposta_comprador_id,
                pc.preco_proposto,
                pc.quantidade_proposta,
                pc.condicoes_compra,
                pc.status AS status_comprador,
                pv.preco_proposto AS preco_vendedor,
                pv.quantidade_proposta AS quantidade_vendedor,
                pv.condicoes_venda AS condicoes_vendedor,
                p.nome AS produto_nome,
                p.unidade_medida,
                p.preco AS preco_original
            FROM propostas_negociacao pn
            JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
            JOIN produtos p ON pn.produto_id = p.id
            LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.id
            JOIN compradores c ON pc.comprador_id = c.id
            WHERE pn.id = :negociacao_id AND c.usuario_id = :usuario_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $negociacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$negociacao) {
        header("Location: minhas_propostas.php?erro=" . urlencode("Negociação não encontrada."));
        exit();
    }
    
    // Verificar se pode fazer contraproposta
    if ($negociacao['status'] !== 'negociacao' || $negociacao['status_comprador'] !== 'pendente') {
        header("Location: minhas_propostas.php?erro=" . urlencode("Esta negociação não permite contraproposta no momento."));
        exit();
    }
    
} catch (PDOException $e) {
    die("Erro ao carregar negociação: " . $e->getMessage());
}

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preco_proposto = $_POST['preco_proposto'];
    $quantidade = $_POST['quantidade'];
    $condicoes = $_POST['condicoes'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // 1. Criar nova proposta do comprador (atualizar a existente)
        $sql_update_comprador = "UPDATE propostas_comprador 
                                SET preco_proposto = :preco,
                                    quantidade_proposta = :quantidade,
                                    condicoes_compra = :condicoes,
                                    status = 'enviada'
                                WHERE id = :proposta_comprador_id";
        
        $stmt_update = $conn->prepare($sql_update_comprador);
        $stmt_update->bindParam(':preco', $preco_proposto);
        $stmt_update->bindParam(':quantidade', $quantidade);
        $stmt_update->bindParam(':condicoes', $condicoes);
        $stmt_update->bindParam(':proposta_comprador_id', $negociacao['proposta_comprador_id']);
        $stmt_update->execute();
        
        // 2. Atualizar a negociação
        $sql_update_negociacao = "UPDATE propostas_negociacao 
                                 SET status = 'negociacao',
                                     data_atualizacao = NOW()
                                 WHERE id = :negociacao_id";
        
        $stmt_update_neg = $conn->prepare($sql_update_negociacao);
        $stmt_update_neg->bindParam(':negociacao_id', $negociacao_id);
        $stmt_update_neg->execute();
        
        $conn->commit();
        
        header("Location: minhas_propostas.php?sucesso=" . urlencode("Contraproposta enviada com sucesso! Aguarde a resposta do vendedor."));
        exit();
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $erro = "Erro ao enviar contraproposta: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fazer Contraproposta - Comprador</title>
    <link rel="stylesheet" href="../../index.css">
    <link rel="stylesheet" href="../css/comprador/comprador.css">
    <link rel="stylesheet" href="../css/comprador/minhas_propostas.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
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
        <h1>Fazer Contraproposta</h1>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <div class="proposta-card">
            <div class="proposta-info">
                <div class="info-group">
                    <p><strong>Produto:</strong> <?php echo htmlspecialchars($negociacao['produto_nome']); ?></p>
                    <p><strong>Preço Original:</strong> R$ <?php echo number_format($negociacao['preco_original'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($negociacao['unidade_medida']); ?></p>
                </div>
            </div>

            <?php if (!empty($negociacao['condicoes_vendedor'])): ?>
                <div class="contraproposta-section">
                    <strong>Contraproposta do Vendedor:</strong>
                    <div class="contraproposta-content">
                        <?php if ($negociacao['preco_vendedor']): ?>
                            <p><strong>Preço Proposto:</strong> R$ <?php echo number_format($negociacao['preco_vendedor'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($negociacao['unidade_medida']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($negociacao['quantidade_vendedor']): ?>
                            <p><strong>Quantidade:</strong> <?php echo $negociacao['quantidade_vendedor']; ?> <?php echo htmlspecialchars($negociacao['unidade_medida']); ?></p>
                        <?php endif; ?>
                        
                        <?php echo nl2br(htmlspecialchars($negociacao['condicoes_vendedor'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="proposta-form">
                <div class="form-group">
                    <label for="preco_proposto">Seu Novo Preço (por <?php echo htmlspecialchars($negociacao['unidade_medida']); ?>):</label>
                    <input type="number" step="0.01" id="preco_proposto" name="preco_proposto" 
                           value="<?php echo htmlspecialchars($negociacao['preco_proposto']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="quantidade">Nova Quantidade (<?php echo htmlspecialchars($negociacao['unidade_medida']); ?>):</label>
                    <input type="number" id="quantidade" name="quantidade" 
                           value="<?php echo htmlspecialchars($negociacao['quantidade_proposta']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="condicoes">Suas Novas Condições (opcional):</label>
                    <textarea id="condicoes" name="condicoes" rows="4"><?php echo htmlspecialchars($negociacao['condicoes_compra']); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-reply"></i>
                        Enviar Contraproposta
                    </button>
                    <a href="minhas_propostas.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>