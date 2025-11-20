<?php
// src/comprador/editar_proposta.php

session_start();
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: minhas_propostas.php?erro=" . urlencode("ID da proposta não informado."));
    exit();
}

$proposta_id = $_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

// Buscar dados da proposta
try {
    $sql = "SELECT pn.*, p.nome AS produto_nome, p.preco AS preco_original, p.unidade_medida,
                   pn.observacoes_vendedor AS contraproposta_vendedor
            FROM propostas_negociacao pn
            JOIN produtos p ON pn.produto_id = p.id
            JOIN compradores c ON pn.comprador_id = c.id
            WHERE pn.id = :proposta_id AND c.usuario_id = :usuario_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        header("Location: minhas_propostas.php?erro=" . urlencode("Proposta não encontrada."));
        exit();
    }
    
    // Verificar se a proposta pode ser editada
    if ($proposta['status'] !== 'pendente' && $proposta['status'] !== 'negociacao') {
        header("Location: minhas_propostas.php?erro=" . urlencode("Esta proposta não pode ser editada."));
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
        $sql_update = "UPDATE propostas_negociacao 
                      SET preco_proposto = :preco, 
                          quantidade_proposta = :quantidade, 
                          condicoes_comprador = :condicoes,
                          status = 'pendente', // Volta para pendente quando o comprador edita
                          data_atualizacao = NOW()
                      WHERE id = :proposta_id";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bindParam(':preco', $preco_proposto);
        $stmt_update->bindParam(':quantidade', $quantidade);
        $stmt_update->bindParam(':condicoes', $condicoes);
        $stmt_update->bindParam(':proposta_id', $proposta_id);
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
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
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

            <?php if (!empty($proposta['contraproposta_vendedor'])): ?>
                <div class="contraproposta-section">
                    <strong>Contraproposta do Vendedor:</strong>
                    <div class="contraproposta-content">
                        <?php echo nl2br(htmlspecialchars($proposta['contraproposta_vendedor'])); ?>
                    </div>
                    <p><em>Ao editar sua proposta, você está respondendo à contraproposta do vendedor.</em></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="proposta-form">
                <div class="form-group">
                    <label for="preco_proposto">Preço Proposto (por <?php echo htmlspecialchars($proposta['unidade_medida']); ?>):</label>
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
                    <textarea id="condicoes" name="condicoes" rows="4"><?php echo htmlspecialchars($proposta['condicoes_comprador']); ?></textarea>
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