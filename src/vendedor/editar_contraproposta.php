<?php
// src/vendedor/editar_contraproposta.php

session_start();
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: propostas.php?erro=" . urlencode("ID da proposta inválido."));
    exit();
}

$proposta_comprador_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

// Buscar dados da contraproposta atual
try {
    // Primeiro, obtém o ID do vendedor
    $sql_vendedor = "SELECT id FROM vendedores WHERE usuario_id = :usuario_id";
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor) {
        die("Erro: Vendedor não encontrado.");
    }

    $vendedor_id = $vendedor['id'];

    // Buscar a contraproposta atual com estoque do produto
    $sql = "SELECT pv.*, pc.status AS status_comprador, pn.status AS negociacao_status, 
                   p.estoque AS estoque_disponivel, p.unidade_medida 
            FROM propostas_vendedor pv
            JOIN propostas_comprador pc ON pv.proposta_comprador_id = pc.id
            JOIN propostas_negociacao pn ON pv.proposta_comprador_id = pn.proposta_comprador_id
            JOIN produtos p ON pn.produto_id = p.id
            WHERE pv.proposta_comprador_id = :proposta_comprador_id 
            AND p.vendedor_id = :vendedor_id
            ORDER BY pv.data_contra_proposta DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
    $stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $contraproposta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contraproposta) {
        header("Location: propostas.php?erro=" . urlencode("Contraproposta não encontrada."));
        exit();
    }
    
    // Verificar se pode editar (status deve ser negociacao + pendente)
    if ($contraproposta['negociacao_status'] !== 'negociacao' || $contraproposta['status_comprador'] !== 'pendente') {
        header("Location: detalhes_proposta.php?id=" . $proposta_comprador_id . "&erro=" . urlencode("Esta contraproposta não pode ser editada."));
        exit();
    }
    
} catch (PDOException $e) {
    die("Erro ao carregar contraproposta: " . $e->getMessage());
}

// Processar o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preco_proposto = $_POST['preco_proposto'];
    $quantidade = $_POST['quantidade'];
    $condicoes = $_POST['condicoes'];
    
    // VALIDAÇÃO: Verificar se quantidade não excede estoque
    if ($quantidade > $contraproposta['estoque_disponivel']) {
        $erro = "Quantidade excede o estoque disponível. Máximo: " . $contraproposta['estoque_disponivel'];
    } elseif ($quantidade <= 0) {
        $erro = "Quantidade deve ser maior que zero.";
    } else {
        try {
            $sql_update = "UPDATE propostas_vendedor 
                          SET preco_proposto = :preco, 
                              quantidade_proposta = :quantidade, 
                              condicoes_venda = :condicoes,
                              data_contra_proposta = NOW()
                          WHERE id = :contraproposta_id";
            
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bindParam(':preco', $preco_proposto);
            $stmt_update->bindParam(':quantidade', $quantidade);
            $stmt_update->bindParam(':condicoes', $condicoes);
            $stmt_update->bindParam(':contraproposta_id', $contraproposta['id']);
            $stmt_update->execute();
            
            header("Location: detalhes_proposta.php?id=" . $proposta_comprador_id . "&sucesso=" . urlencode("Contraproposta atualizada com sucesso!"));
            exit();
            
        } catch (PDOException $e) {
            $erro = "Erro ao atualizar contraproposta: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Contraproposta</title>
    <link rel="stylesheet" href="../../index.css">
    <link rel="stylesheet" href="../css/vendedor/vendedor.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
</head>
<body>
    <!-- Navbar similar ao detalhes_proposta.php -->
    
    <main class="container">
        <h1>Editar Contraproposta</h1>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <div class="proposta-info">
            <p><strong>Estoque Disponível:</strong> <?php echo htmlspecialchars($contraproposta['estoque_disponivel']); ?> <?php echo htmlspecialchars($contraproposta['unidade_medida']); ?></p>
        </div>
        
        <form method="POST" class="proposta-form">
            <div class="form-group">
                <label for="preco_proposto">Preço Proposto:</label>
                <input type="number" step="0.01" id="preco_proposto" name="preco_proposto" 
                       value="<?php echo htmlspecialchars($contraproposta['preco_proposto']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="quantidade">Quantidade:</label>
                <input type="number" id="quantidade" name="quantidade" 
                       value="<?php echo htmlspecialchars($contraproposta['quantidade_proposta']); ?>" 
                       min="1" 
                       max="<?php echo htmlspecialchars($contraproposta['estoque_disponivel']); ?>" 
                       required>
                <small class="estoque-info">Máximo disponível: <?php echo htmlspecialchars($contraproposta['estoque_disponivel']); ?> <?php echo htmlspecialchars($contraproposta['unidade_medida']); ?></small>
            </div>
            
            <div class="form-group">
                <label for="condicoes">Condições de Venda (opcional):</label>
                <textarea id="condicoes" name="condicoes" rows="4"><?php echo htmlspecialchars($contraproposta['condicoes_venda']); ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Atualizar Contraproposta</button>
                <a href="detalhes_proposta.php?id=<?php echo $proposta_comprador_id; ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quantidadeInput = document.getElementById('quantidade');
            const maxQuantidade = <?php echo $contraproposta['estoque_disponivel']; ?>;
            
            // Impedir valores fora do intervalo
            quantidadeInput.addEventListener('change', function() {
                let value = parseInt(this.value);
                if (value < 1) {
                    this.value = 1;
                    alert('Quantidade deve ser pelo menos 1.');
                } else if (value > maxQuantidade) {
                    this.value = maxQuantidade;
                    alert('Quantidade não pode exceder o estoque disponível de ' + maxQuantidade);
                }
            });
            
            quantidadeInput.addEventListener('input', function() {
                let value = parseInt(this.value);
                if (value > maxQuantidade) {
                    this.value = maxQuantidade;
                }
            });
        });
    </script>
</body>
</html>