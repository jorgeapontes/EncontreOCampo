<?php
// src/comprador/fazer_contraproposta.php

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

// Buscar dados da proposta atual
try {
    $sql = "SELECT pn.*, p.nome AS produto_nome, p.preco AS preco_original, 
                   p.unidade_medida, pn.observacoes_vendedor AS contraproposta_vendedor,
                   p.id AS produto_id
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
        header("Location: minhas_propostas.php?erro=" . urlencode("Proposta não encontrada ou você não tem permissão para fazer contraproposta."));
        exit();
    }
    
    // DEBUG: Mostrar informações da proposta
    echo "<!-- DEBUG: Status: " . $proposta['status'] . " -->";
    echo "<!-- DEBUG: Tem contraproposta: " . (!empty($proposta['contraproposta_vendedor']) ? 'Sim' : 'Não') . " -->";
    
    // Verificar se a proposta pode ser alterada - LÓGICA CORRIGIDA
    $pode_alterar = false;
    
    // Se há contraproposta do vendedor, permite fazer contraproposta independentemente do status
    if (!empty($proposta['contraproposta_vendedor'])) {
        $pode_alterar = true;
    }
    // Se não há contraproposta, só permite se o status for pendente ou negociação
    elseif (in_array($proposta['status'], ['pendente', 'negociacao'])) {
        $pode_alterar = true;
    }
    
    if (!$pode_alterar) {
        header("Location: minhas_propostas.php?erro=" . urlencode("Esta proposta não pode ser alterada. Status atual: " . $proposta['status']));
        exit();
    }
    
} catch (PDOException $e) {
    die("Erro ao carregar proposta: " . $e->getMessage());
}

// Processar o formulário de contraproposta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preco_proposto = $_POST['preco_proposto'];
    $quantidade = $_POST['quantidade'];
    $condicoes = $_POST['condicoes'];
    
    try {
        // Preparar dados para atualização
        $sql_update = "UPDATE propostas_negociacao 
                      SET preco_proposto = :preco, 
                          quantidade_proposta = :quantidade, 
                          condicoes_comprador = :condicoes,
                          status = 'negociacao', 
                          data_atualizacao = NOW()";
        
        // Se há uma contraproposta do vendedor, limpar ao fazer nova contraproposta
        if (!empty($proposta['contraproposta_vendedor'])) {
            $sql_update .= ", observacoes_vendedor = NULL";
        }
        
        $sql_update .= " WHERE id = :proposta_id";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bindParam(':preco', $preco_proposto);
        $stmt_update->bindParam(':quantidade', $quantidade);
        $stmt_update->bindParam(':condicoes', $condicoes);
        $stmt_update->bindParam(':proposta_id', $proposta_id);
        $stmt_update->execute();
        
        header("Location: minhas_propostas.php?sucesso=" . urlencode("Contraproposta enviada com sucesso! Aguarde a resposta do vendedor."));
        exit();
        
    } catch (PDOException $e) {
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
                    <p><strong>Produto:</strong> <?php echo htmlspecialchars($proposta['produto_nome']); ?></p>
                    <p><strong>Preço Original:</strong> R$ <?php echo number_format($proposta['preco_original'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                </div>
                <div class="info-group">
                    <p><strong>Sua Proposta Anterior:</strong> R$ <?php echo number_format($proposta['preco_proposto'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                    <p><strong>Quantidade Anterior:</strong> <?php echo htmlspecialchars($proposta['quantidade_proposta']); ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                </div>
            </div>

            <?php if (!empty($proposta['contraproposta_vendedor'])): ?>
                <div class="contraproposta-section">
                    <strong>Contraproposta do Vendedor:</strong>
                    <div class="contraproposta-content">
                        <?php echo nl2br(htmlspecialchars($proposta['contraproposta_vendedor'])); ?>
                    </div>
                    <p><em>Você está respondendo a esta contraproposta com uma nova oferta.</em></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="proposta-form">
                <div class="form-group">
                    <label for="preco_proposto">Novo Preço Proposto (por <?php echo htmlspecialchars($proposta['unidade_medida']); ?>):</label>
                    <input type="number" step="0.01" id="preco_proposto" name="preco_proposto" 
                           value="<?php echo htmlspecialchars($proposta['preco_proposto']); ?>" required
                           min="0.01" max="999999.99">
                </div>

                <div class="form-group">
                    <label for="quantidade">Nova Quantidade (<?php echo htmlspecialchars($proposta['unidade_medida']); ?>):</label>
                    <input type="number" id="quantidade" name="quantidade" 
                           value="<?php echo htmlspecialchars($proposta['quantidade_proposta']); ?>" required
                           min="1" max="999999">
                </div>

                <div class="form-group">
                    <label for="condicoes">Novas Condições de Compra (opcional):</label>
                    <textarea id="condicoes" name="condicoes" rows="4" placeholder="Descreva suas novas condições..."><?php echo htmlspecialchars($proposta['condicoes_comprador']); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i>
                        <?php echo !empty($proposta['contraproposta_vendedor']) ? 'Enviar Contraproposta' : 'Atualizar Proposta'; ?>
                    </button>
                    <a href="minhas_propostas.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>