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
                p.estoque AS estoque_kg,
                p.estoque_unidades,
                p.modo_precificacao,
                p.embalagem_peso_kg,
                p.embalagem_unidades,
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

// Ajustar estoque e unidade para exibição conforme modo de precificação
$modo = $proposta['modo_precificacao'] ?? 'por_quilo';
if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
    $proposta['estoque_disponivel'] = $proposta['estoque_unidades'] ?? 0;
} else {
    $proposta['estoque_disponivel'] = $proposta['estoque_kg'] ?? 0;
}

switch ($modo) {
    case 'por_unidade': $proposta['unidade_medida'] = 'unidade'; break;
    case 'por_quilo': $proposta['unidade_medida'] = 'kg'; break;
    case 'caixa_unidades': $proposta['unidade_medida'] = 'caixa' . (!empty($proposta['embalagem_unidades']) ? " ({$proposta['embalagem_unidades']} unid)" : ''); break;
    case 'caixa_quilos': $proposta['unidade_medida'] = 'caixa' . (!empty($proposta['embalagem_peso_kg']) ? " ({$proposta['embalagem_peso_kg']} kg)" : ''); break;
    case 'saco_unidades': $proposta['unidade_medida'] = 'saco' . (!empty($proposta['embalagem_unidades']) ? " ({$proposta['embalagem_unidades']} unid)" : ''); break;
    case 'saco_quilos': $proposta['unidade_medida'] = 'saco' . (!empty($proposta['embalagem_peso_kg']) ? " ({$proposta['embalagem_peso_kg']} kg)" : ''); break;
}

// Processar o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preco_proposto = $_POST['preco_proposto'];
    $quantidade = $_POST['quantidade'];
    $condicoes = $_POST['condicoes'];
    
    // VALIDAÇÃO: Verificar se quantidade não excede estoque
    if ($quantidade > $proposta['estoque_disponivel']) {
        $erro = "Quantidade excede o estoque disponível. Máximo: " . $proposta['estoque_disponivel'];
    } elseif ($quantidade <= 0) {
        $erro = "Quantidade deve ser maior que zero.";
    } else {
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

    <main class="container contrapropostas-container">
        <h1>Editar Minha Proposta</h1>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <div class="contraproposta-card">
            <div class="proposta-info">
                <div class="contra-info-group">
                    <p><strong>Produto:</strong> <?php echo htmlspecialchars($proposta['produto_nome']); ?></p>
                    <p><strong>Preço Original:</strong> R$ <?php echo number_format($proposta['preco_original'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                    <p><strong>Estoque Disponível:</strong> <?php echo htmlspecialchars($proposta['estoque_disponivel']); ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
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

            <form method="POST" class="contraproposta-form">
                <div class="contraproposta-form-group">
                    <label for="preco_proposto">Preço Proposto (R$ <?php echo htmlspecialchars($proposta['unidade_medida']); ?>):</label>
                    <input type="number" step="0.01" id="preco_proposto" name="preco_proposto" 
                           value="<?php echo htmlspecialchars($proposta['preco_proposto']); ?>" required>
                </div>

                <div class="contraproposta-form-group">
                    <label for="quantidade">Quantidade (<?php echo htmlspecialchars($proposta['unidade_medida']); ?>):</label>
                    <input type="number" id="quantidade" name="quantidade" 
                           value="<?php echo htmlspecialchars($proposta['quantidade_proposta']); ?>" 
                           min="1" 
                           max="<?php echo htmlspecialchars($proposta['estoque_disponivel']); ?>" 
                           required>
                    <small class="estoque-info">Máximo disponível: <?php echo htmlspecialchars($proposta['estoque_disponivel']); ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></small>
                </div>

                <div class="contraproposta-form-group">
                    <label for="condicoes">Condições de Compra (opcional):</label>
                    <textarea id="condicoes" name="condicoes" rows="4"><?php echo htmlspecialchars($proposta['condicoes_compra']); ?></textarea>
                </div>

                <div class="contraproposta-form-actions">
                    <button type="submit" class="btn btn-atualizar-contraproposta">
                        <i class="fas fa-check"></i>
                        Atualizar Proposta
                    </button>
                    <a href="minhas_propostas.php" class="btn btn-cancelar">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quantidadeInput = document.getElementById('quantidade');
            const maxQuantidade = <?php echo $proposta['estoque_disponivel']; ?>;
            
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