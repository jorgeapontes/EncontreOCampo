<?php
// src/comprador/editar_proposta.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: minhas_propostas.php?erro=" . urlencode("ID da proposta inválido."));
    exit();
}

$proposta_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();
$proposta = null;
$produto = null;

// 2. BUSCAR DADOS DA PROPOSTA E VERIFICAR PROPRIEDADE
try {
    // Buscar ID do comprador
    $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if (!$comprador) {
        header("Location: minhas_propostas.php?erro=" . urlencode("Perfil de comprador não encontrado."));
        exit();
    }

    $comprador_id = $comprador['id'];

    // Buscar dados da proposta
    $sql = "SELECT 
                pn.*,
                p.nome AS produto_nome,
                p.preco AS preco_original,
                p.estoque,
                p.unidade_medida,
                p.imagem_url,
                u.nome AS nome_vendedor
            FROM propostas_negociacao pn
            JOIN produtos p ON pn.produto_id = p.id
            JOIN vendedores v ON p.vendedor_id = v.id
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE pn.id = :proposta_id AND pn.comprador_id = :comprador_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposta) {
        header("Location: minhas_propostas.php?erro=" . urlencode("Proposta não encontrada ou você não tem permissão para editá-la."));
        exit();
    }

    // Verificar se a proposta pode ser editada (apenas pendente ou em negociação)
    if (!in_array($proposta['status'], ['pendente', 'negociacao'])) {
        header("Location: minhas_propostas.php?erro=" . urlencode("Esta proposta não pode mais ser editada."));
        exit();
    }

} catch (PDOException $e) {
    die("Erro ao carregar proposta: " . $e->getMessage());
}

// Caminho da imagem (com fallback)
$imagePath = $proposta['imagem_url'] ? htmlspecialchars($proposta['imagem_url']) : '../../img/placeholder.png';
$mensagem_erro = isset($_GET['erro']) ? htmlspecialchars($_GET['erro']) : null;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Proposta - <?php echo htmlspecialchars($proposta['produto_nome']); ?></title>
    <link rel="stylesheet" href="../../index.css"> 
    <link rel="stylesheet" href="../css/comprador/proposta_nova.css">
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <h1>ENCONTRE</h1>
                <h2>OCAMPO</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="../anuncios.php" class="nav-link">Ver Anúncios</a></li>
                <li class="nav-item"><a href="minhas_propostas.php" class="nav-link active">Minhas Propostas</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link logout">Sair</a></li>
            </ul>
        </div>
    </nav>

    <main class="container proposta-container">
        <div class="page-header">
            <h1>Alterar Proposta</h1>
            <p class="page-subtitle">Atualize os detalhes da sua proposta</p>
        </div>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <div class="proposta-content">
            <div class="anuncio-card">
                <div class="anuncio-header">
                    <h2>Anúncio Original</h2>
                </div>
                <div class="anuncio-info">
                    <div class="product-image">
                        <img src="<?php echo $imagePath; ?>" alt="Imagem de <?php echo htmlspecialchars($proposta['produto_nome']); ?>">
                    </div>
                    <div class="product-details">
                        <h3><?php echo htmlspecialchars($proposta['produto_nome']); ?></h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="label">Vendedor:</span>
                                <span class="value"><?php echo htmlspecialchars($proposta['nome_vendedor']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Preço Original:</span>
                                <span class="value price-original">
                                    R$ <?php echo number_format($proposta['preco_original'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($proposta['unidade_medida']); ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Estoque Disponível:</span>
                                <span class="value"><?php echo htmlspecialchars($proposta['estoque']); ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Status Atual:</span>
                                <span class="value status-<?php echo $proposta['status']; ?>">
                                    <?php echo ucfirst($proposta['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="proposta-form-container">
                <div class="form-header">
                    <h3><i class="fas fa-edit"></i> Editar Sua Proposta</h3>
                    <p>Atualize os valores e condições da sua proposta</p>
                </div>

                <form action="processar_edicao_proposta.php" method="POST" class="proposta-form">
                    <input type="hidden" name="proposta_id" value="<?php echo $proposta_id; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="preco_proposto">
                                <i class="fas fa-tag"></i>
                                Novo Preço Proposto
                                <span class="unit">(por <?php echo htmlspecialchars($proposta['unidade_medida']); ?>)</span>
                            </label>
                            <div class="input-with-symbol">
                                <span class="currency-symbol">R$</span>
                                <input type="number" id="preco_proposto" name="preco_proposto" 
                                       step="0.01" min="0.01" required 
                                       value="<?php echo number_format($proposta['preco_proposto'], 2, '.', ''); ?>"
                                       placeholder="0.00">
                            </div>
                            <small>Preço atual: R$ <?php echo number_format($proposta['preco_proposto'], 2, ',', '.'); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="quantidade_proposta">
                                <i class="fas fa-box"></i>
                                Nova Quantidade Desejada
                                <span class="unit">(em <?php echo htmlspecialchars($proposta['unidade_medida']); ?>)</span>
                            </label>
                            <input type="number" id="quantidade_proposta" name="quantidade_proposta" 
                                   min="1" max="<?php echo htmlspecialchars($proposta['estoque']); ?>" 
                                   required 
                                   value="<?php echo htmlspecialchars($proposta['quantidade_proposta']); ?>"
                                   placeholder="Quantidade">
                            <small>Máximo disponível: <?php echo htmlspecialchars($proposta['estoque']); ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></small>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="condicoes">
                            <i class="fas fa-file-alt"></i>
                            Condições de Pagamento/Entrega
                            <span class="optional">(Opcional)</span>
                        </label>
                        <textarea id="condicoes" name="condicoes" rows="4" 
                                  placeholder="Ex: Pagamento à vista na entrega, frete por minha conta, prazo de entrega desejado..."><?php echo htmlspecialchars($proposta['condicoes_comprador']); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Atualizar Proposta
                        </button>
                        <a href="minhas_propostas.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Voltar às Propostas
                        </a>
                        <?php if ($proposta['status'] === 'negociacao'): ?>
                        <a href="detalhes_proposta.php?id=<?php echo $proposta_id; ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i>
                            Ver Contraproposta
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        const precoInput = document.getElementById('preco_proposto');
        if (precoInput) {
            precoInput.addEventListener('blur', function() {
                this.value = parseFloat(this.value).toFixed(2);
            });
        }
    </script>
</body>
</html>