<?php
// src/comprador/editar_proposta.php

session_start();
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: minhas_propostas.php?erro=" . urlencode("ID da proposta não informado."));
    exit();
}

$negociacao_id = $_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

// Buscar dados da proposta
try {
    $sql = "SELECT 
                pc.*,
                pc.status AS status_comprador,
                pn.id AS negociacao_id,
                pn.status AS negociacao_status,
                pn.produto_id,
                p.nome AS produto_nome, 
                p.preco AS preco_original, 
                p.unidade_medida,
                pv.condicoes_venda
            FROM propostas_negociacao pn
            JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
            LEFT JOIN propostas_vendedor pv ON pn.proposta_vendedor_id = pv.id
            JOIN produtos p ON pn.produto_id = p.id
            JOIN compradores c ON pc.comprador_id = c.id
            WHERE pn.id = :negociacao_id AND c.usuario_id = :usuario_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':negociacao_id', $negociacao_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        header("Location: minhas_propostas.php?erro=" . urlencode("Proposta não encontrada."));
        exit();
    }
    
    // Verificar se a proposta pode ser editada
    // 1. Status na negociação deve ser 'negociacao'
    // 2. Status na proposta do comprador deve ser 'enviada'
    if ($proposta['negociacao_status'] !== 'negociacao' || $proposta['status_comprador'] !== 'enviada') {
        $status_msg = "Negociação: {$proposta['negociacao_status']}, Proposta: {$proposta['status_comprador']}";
        header("Location: minhas_propostas.php?erro=" . urlencode("Esta proposta não pode ser editada. " . $status_msg));
        exit();
    }
    
} catch (PDOException $e) {
    die("Erro ao carregar proposta: " . $e->getMessage());
}

// Processar o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preco_proposto = $_POST['preco_proposto'];
    $quantidade = $_POST['quantidade'];
    $condicoes = $_POST['condicoes'];
    
    try {
        // Atualizar a proposta do comprador
        $sql_update = "UPDATE propostas_comprador 
                      SET preco_proposto = :preco, 
                          quantidade_proposta = :quantidade, 
                          condicoes_compra = :condicoes
                      WHERE id = :proposta_comprador_id";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bindParam(':preco', $preco_proposto);
        $stmt_update->bindParam(':quantidade', $quantidade);
        $stmt_update->bindParam(':condicoes', $condicoes);
        $stmt_update->bindParam(':proposta_comprador_id', $proposta['id']);
        $stmt_update->execute();
        
        header("Location: minhas_propostas.php?sucesso=" . urlencode("Proposta atualizada com sucesso!"));
        exit();
        
    } catch (PDOException $e) {
        $erro = "Erro ao atualizar proposta: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Proposta - Comprador</title>
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
        <h1>Editar Minha Proposta</h1>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <div class="proposta-card">
            <div class="proposta-info">
                <div class="info-group">
                    <p><strong>Produto:</strong> <?php echo htmlspecialchars($proposta['produto_nome']); ?></p>
                    <p><strong>Preço Original:</strong> R$ <?php echo number_format($proposta['preco_original'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                </div>
            </div>

            <?php if (!empty($proposta['condicoes_venda'])): ?>
                <div class="contraproposta-section">
                    <strong>Contraproposta do Vendedor:</strong>
                    <div class="contraproposta-content">
                        <?php echo nl2br(htmlspecialchars($proposta['condicoes_venda'])); ?>
                    </div>
                    <p><em>Ao editar sua proposta, você está respondendo à contraproposta do vendedor.</em></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="proposta-form">
                <div class="form-group">
                    <label for="preco_proposto">Preço Proposto (R$ <?php echo htmlspecialchars($proposta['unidade_medida']); ?>):</label>
                    <input type="number" step="0.01" id="preco_proposto" name="preco_proposto" 
                           value="<?php echo htmlspecialchars($proposta['preco_proposto']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="quantidade">Quantidade (<?php echo htmlspecialchars($proposta['unidade_medida']); ?>):</label>
                    <input type="number" id="quantidade" name="quantidade" 
                           value="<?php echo htmlspecialchars($proposta['quantidade_proposta']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="condicoes">Condições de Compra (opcional):</label>
                    <textarea id="condicoes" name="condicoes" rows="4"><?php echo htmlspecialchars($proposta['condicoes_compra']); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i>
                        Atualizar Proposta
                    </button>
                    <a href="minhas_propostas.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>