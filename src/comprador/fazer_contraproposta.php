<?php
// src/comprador/fazer_contraproposta.php

session_start();
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
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
                p.preco AS preco_original,
                p.estoque AS estoque_kg,
                p.estoque_unidades,
                p.modo_precificacao,
                p.embalagem_peso_kg,
                p.embalagem_unidades,
                p.unidade_medida 
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

// Ajustar estoque e unidade para exibição conforme modo de precificação
$modo = $negociacao['modo_precificacao'] ?? 'por_quilo';
if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
    $negociacao['estoque_disponivel'] = $negociacao['estoque_unidades'] ?? 0;
} else {
    $negociacao['estoque_disponivel'] = $negociacao['estoque_kg'] ?? 0;
}

switch ($modo) {
    case 'por_unidade': $negociacao['unidade_medida'] = 'unidade'; break;
    case 'por_quilo': $negociacao['unidade_medida'] = 'kg'; break;
    case 'caixa_unidades': $negociacao['unidade_medida'] = 'caixa' . (!empty($negociacao['embalagem_unidades']) ? " ({$negociacao['embalagem_unidades']} unid)" : ''); break;
    case 'caixa_quilos': $negociacao['unidade_medida'] = 'caixa' . (!empty($negociacao['embalagem_peso_kg']) ? " ({$negociacao['embalagem_peso_kg']} kg)" : ''); break;
    case 'saco_unidades': $negociacao['unidade_medida'] = 'saco' . (!empty($negociacao['embalagem_unidades']) ? " ({$negociacao['embalagem_unidades']} unid)" : ''); break;
    case 'saco_quilos': $negociacao['unidade_medida'] = 'saco' . (!empty($negociacao['embalagem_peso_kg']) ? " ({$negociacao['embalagem_peso_kg']} kg)" : ''); break;
}

// Processar o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preco_proposto = $_POST['preco_proposto'];
    $quantidade = $_POST['quantidade'];
    $condicoes = $_POST['condicoes'] ?? '';
    
    // VALIDAÇÃO: Verificar se quantidade não excede estoque
    if ($quantidade > $negociacao['estoque_disponivel']) {
        $erro = "Quantidade excede o estoque disponível. Máximo: " . $negociacao['estoque_disponivel'];
    } elseif ($quantidade <= 0) {
        $erro = "Quantidade deve ser maior que zero.";
    } else {
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

    <main class="container contrapropostas-container">
        <h1>Fazer Contraproposta</h1>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <div class="contraproposta-card">
            <div class="proposta-info">
                <div class="contra-info-group">
                    <p><strong>Produto:</strong> <?php echo htmlspecialchars($negociacao['produto_nome']); ?></p>
                    <p><strong>Preço Original:</strong> R$ <?php echo number_format($negociacao['preco_original'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($negociacao['unidade_medida']); ?></p>
                    <p><strong>Estoque Disponível:</strong> <?php echo htmlspecialchars($negociacao['estoque_disponivel']); ?> <?php echo htmlspecialchars($negociacao['unidade_medida']); ?></p>
                </div>
            </div>

            <?php if (!empty($negociacao['condicoes_vendedor'])): ?>
                <div class="contra-info-group">
                    <strong><div class="contraproposta-titulo">Contraproposta do Vendedor:</div></strong>
                    <div class="contraproposta-content">
                        <?php if ($negociacao['preco_vendedor']): ?>
                            <p><strong>Preço Proposto:</strong> R$ <?php echo number_format($negociacao['preco_vendedor'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($negociacao['unidade_medida']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($negociacao['quantidade_vendedor']): ?>
                            <p><strong>Quantidade:</strong> <?php echo $negociacao['quantidade_vendedor']; ?> <?php echo htmlspecialchars($negociacao['unidade_medida']); ?></p>
                        <?php endif; ?>
                        
                        <p><strong>Condições:</strong></p>
                        <?php echo nl2br(htmlspecialchars($negociacao['condicoes_vendedor'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="contraproposta-form">
                <div class="contraproposta-form-group">
                    <label for="preco_proposto">Seu Novo Preço (por <?php echo htmlspecialchars($negociacao['unidade_medida']); ?>):</label>
                    <input type="number" step="0.01" id="preco_proposto" name="preco_proposto" 
                           value="<?php echo htmlspecialchars($negociacao['preco_proposto']); ?>" required>
                </div>

                <div class="contraproposta-form-group">
                    <label for="quantidade">Nova Quantidade (<?php echo htmlspecialchars($negociacao['unidade_medida']); ?>):</label>
                    <input type="number" id="quantidade" name="quantidade" 
                           value="<?php echo htmlspecialchars($negociacao['quantidade_proposta']); ?>" 
                           min="1" 
                           max="<?php echo htmlspecialchars($negociacao['estoque_disponivel']); ?>" 
                           required>
                    <small class="estoque-info">Máximo disponível: <?php echo htmlspecialchars($negociacao['estoque_disponivel']); ?> <?php echo htmlspecialchars($negociacao['unidade_medida']); ?></small>
                </div>

                <div class="contraproposta-form-group">
                    <label for="condicoes">Suas Novas Condições (opcional):</label>
                    <textarea id="condicoes" name="condicoes" rows="4"><?php echo htmlspecialchars($negociacao['condicoes_compra']); ?></textarea>
                </div>

                <div class="contraproposta-form-actions">
                    <button type="submit" class="btn btn-atualizar-contraproposta">
                        <i class="fas fa-reply"></i>
                        Enviar Contraproposta
                    </button>
                    <a href="minhas_propostas.php" class="btn btn-cancelar">Cancelar</a>
                </div>
            </form>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quantidadeInput = document.getElementById('quantidade');
            const maxQuantidade = <?php echo $negociacao['estoque_disponivel']; ?>;
            
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