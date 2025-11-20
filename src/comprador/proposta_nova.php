<?php
// src/comprador/proposta_nova.php (Com Imagem do Produto e CSS Externo)

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    // Redireciona para o login com uma mensagem de erro
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

// 2. OBTENÇÃO DO ID DO ANÚNCIO
if (!isset($_GET['anuncio_id']) || !is_numeric($_GET['anuncio_id'])) {
    header("Location: dashboard.php?erro=" . urlencode("Anúncio não especificado ou inválido."));
    exit();
}

$anuncio_id = (int)$_GET['anuncio_id'];
$usuario_id = $_SESSION['usuario_id']; // ID do comprador logado

$database = new Database();
$conn = $database->getConnection();
$anuncio = null;

// 3. BUSCA DOS DETALHES DO ANÚNCIO NO BANCO DE DADOS
try {
    // Adicionado 'p.imagem_url' na seleção
    $sql = "SELECT 
                p.id, 
                p.nome AS produto, 
                p.estoque AS quantidade_disponivel, 
                p.preco, 
                p.unidade_medida,
                p.imagem_url, 
                v.id AS vendedor_sistema_id, 
                u.id AS vendedor_usuario_id, 
                u.nome AS nome_vendedor
            FROM produtos p
            JOIN vendedores v ON p.vendedor_id = v.id 
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE p.id = :anuncio_id AND p.status = 'ativo'";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt->execute();
    $anuncio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anuncio) {
        header("Location: dashboard.php?erro=" . urlencode("Anúncio não encontrado ou inativo."));
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao carregar anúncio: " . $e->getMessage()); 
}

// Formatação do preço para exibição
$preco_formatado = 'R$ ' . number_format($anuncio['preco'], 2, ',', '.');
$unidade = htmlspecialchars($anuncio['unidade_medida']);

// Caminho da imagem (com fallback para imagem padrão)
$imagePath = $anuncio['imagem_url'] ? htmlspecialchars($anuncio['imagem_url']) : '../../img/placeholder.png';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fazer Proposta - <?php echo htmlspecialchars($anuncio['produto']); ?></title>
    <link rel="stylesheet" href="../../index.css"> 
    <link rel="stylesheet" href="../css/comprador/proposta_nova.css">
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
                <li class="nav-item"><a href="../anuncios.php" class="nav-link">Ver Anúncios</a></li>
                <li class="nav-item"><a href="minhas_propostas.php" class="nav-link active">Minhas Propostas</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link logout">Sair</a></li>
            </ul>
        </div>
    </nav>

    <main class="container proposta-container">
        <div class="page-header">
            <h1>Fazer Proposta</h1>
            <p class="page-subtitle">Negocie diretamente com o vendedor</p>
        </div>

        <div class="proposta-content">
            <div class="anuncio-card">
                <div class="anuncio-header">
                    <h2>Anúncio Selecionado</h2>
                </div>
                <div class="anuncio-info">
                    <div class="product-image">
                        <img src="<?php echo $imagePath; ?>" alt="Imagem de <?php echo htmlspecialchars($anuncio['produto']); ?>">
                    </div>
                    <div class="product-details">
                        <h3><?php echo htmlspecialchars($anuncio['produto']); ?></h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="label">Vendedor:</span>
                                <span class="value"><?php echo htmlspecialchars($anuncio['nome_vendedor']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Preço Original:</span>
                                <span class="value price-original"><?php echo $preco_formatado; ?> / <?php echo $unidade; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="label">Estoque:</span>
                                <span class="value"><?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="proposta-form-container">
                <div class="form-header">
                    <h3><i class="fas fa-handshake"></i> Sua Proposta</h3>
                    <p>Preencha os detalhes da sua proposta de compra</p>
                </div>

                <form action="processar_proposta.php" method="POST" class="proposta-form">
                    <input type="hidden" name="produto_id" value="<?php echo $anuncio_id; ?>"> 
                    <input type="hidden" name="comprador_usuario_id" value="<?php echo $usuario_id; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="preco_proposto">
                                <i class="fas fa-tag"></i>
                                Seu Preço Proposto
                                <span class="unit">(por <?php echo $unidade; ?>)</span>
                            </label>
                            <div class="input-with-symbol">
                                <span class="currency-symbol">R$</span>
                                <input type="number" id="preco_proposto" name="preco_proposto" 
                                       step="0.01" min="0.01" required
                                        value="<?php echo htmlspecialchars($anuncio['preco']); ?>"
                                       placeholder="0.00">
                            </div>
                            <small>Digite o valor que você deseja pagar por unidade</small>
                        </div>

                        <div class="form-group">
                            <label for="quantidade_proposta">
                                <i class="fas fa-box"></i>
                                Quantidade Desejada
                                <span class="unit">(em <?php echo $unidade; ?>)</span>
                            </label>
                            <input type="number" id="quantidade_proposta" name="quantidade_proposta" 
                                   min="1" max="<?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?>" 
                                   required placeholder="Quantidade">
                            <small>Máximo disponível: <?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?></small>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="condicoes">
                            <i class="fas fa-file-alt"></i>
                            Condições de Pagamento/Entrega
                            <span class="optional">(Opcional)</span>
                        </label>
                        <textarea id="condicoes" name="condicoes" rows="4" 
                                  placeholder="Adicione aqui detalhes para a negociação."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Proposta
                        </button>
                        <a href="../anuncios.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Voltar aos Anúncios
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        const precoInput = document.getElementById('preco_proposto');
        if (precoInput) {
            precoInput.addEventListener('blur', function() {
                this.value = parseFloat(this.value.replace(',', '.')).toFixed(2);
            });
        }
    </script>
</body>
</html>