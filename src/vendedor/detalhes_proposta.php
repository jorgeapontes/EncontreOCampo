<?php
// src/vendedor/detalhes_proposta.php - CORRIGIDO

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

// 2. OBTENÇÃO DO ID DA PROPOSTA
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // CORRIGIDO: Redireciona para propostas.php
    header("Location: propostas.php?erro=" . urlencode("Proposta não especificada ou inválida."));
    exit();
}

$proposta_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id']; // ID do vendedor logado
$database = new Database();
$conn = $database->getConnection();
$proposta = null;
$vendedor_id = null;


// 3. OBTENDO ID DO VENDEDOR E DETALHES DA PROPOSTA
try {
    // Primeiro, obtém o ID do vendedor
    $sql_vendedor = "SELECT id FROM vendedores WHERE usuario_id = :usuario_id";
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $resultado_vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

    if (!$resultado_vendedor) {
        die("Erro: ID de vendedor não encontrado.");
    }
    $vendedor_id = $resultado_vendedor['id'];

    // Agora, busca os detalhes da proposta, verificando se o produto pertence a ESTE vendedor
    $sql = "SELECT 
                pn.*,
                p.nome AS produto_nome,
                p.unidade_medida,
                p.preco AS preco_anuncio_original,
                u.nome AS nome_comprador,
                c.nome_comercial AS loja_comprador
            FROM propostas_negociacao pn
            JOIN produtos p ON pn.produto_id = p.id
            JOIN vendedores v ON p.vendedor_id = v.id
            JOIN compradores c ON pn.comprador_id = c.id
            JOIN usuarios u ON c.usuario_id = u.id
            WHERE pn.id = :proposta_id AND v.id = :vendedor_id"; // CLÁUSULA DE SEGURANÇA!
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposta) {
        header("Location: propostas.php?erro=" . urlencode("Proposta não encontrada ou acesso negado."));
        exit();
    }

} catch (PDOException $e) {
    die("Erro ao carregar detalhes: " . $e->getMessage()); 
}

// Função para traduzir o status
function formatarStatus($status) {
    $map = [
        'pendente' => ['text' => 'Pendente', 'class' => 'status-pending', 'icon' => 'fas fa-clock'],
        'aceita' => ['text' => 'Aceita', 'class' => 'status-accepted', 'icon' => 'fas fa-check-circle'],
        'recusada' => ['text' => 'Recusada', 'class' => 'status-rejected', 'icon' => 'fas fa-times-circle'],
        'negociacao' => ['text' => 'Em Negociação', 'class' => 'status-negotiation', 'icon' => 'fas fa-exchange-alt'],
    ];
    return $map[$status] ?? ['text' => ucfirst($status), 'class' => 'status-default', 'icon' => 'fas fa-question-circle'];
}

$status_info = formatarStatus($proposta['status']);

// Verifica qual é a última condição válida
$valor_atual_negociacao = $proposta['preco_proposto'];
$quantidade_atual_negociacao = $proposta['quantidade_proposta'];
// CORRIGIDO: condicoes_vendedor -> observacoes_vendedor
$condicoes_vendedor = empty($proposta['observacoes_vendedor']) ? 'Nenhuma condição especial informada.' : nl2br(htmlspecialchars($proposta['observacoes_vendedor']));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Proposta #<?php echo $proposta_id; ?></title>
    <link rel="stylesheet" href="../../index.css"> 
    <link rel="stylesheet" href="../css/vendedor/vendedor.css"> 
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* Estilos similares ao Comprador, mas adaptados ao Vendedor */
        .details-container {
            padding-top: 120px;
            max-width: 900px;
            margin: 0 auto;
        }

        .proposta-details {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
        }
        
        /* Cores de status */
        .status-pending { background-color: #FFF3E0; color: #FF9800; border: 1px solid #FF9800; }
        .status-accepted { background-color: #E8F5E9; color: #4CAF50; border: 1px solid #4CAF50; }
        .status-rejected { background-color: #FFEBEE; color: #F44336; border: 1px solid #F44336; }
        .status-negotiation { background-color: #E3F2FD; color: #2196F3; border: 1px solid #2196F3; }
        .status-badge {
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1em;
        }
        
        .info-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            padding: 15px;
            border-radius: 8px;
            background-color: var(--gray);
        }
        
        .proposta-valor {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        /* Seção de Condições */
        .condicoes-section {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 5px solid var(--secondary-color);
        }
        
        .condicoes-section.vendedor {
            border-left-color: var(--primary-color);
            background-color: #f0fff0; /* Verde bem claro para a última oferta */
        }
        
        /* Seção de Ações e Formulário */
        .actions-section {
            padding-top: 20px;
            border-top: 2px dashed #eee;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-success { background-color: var(--primary-color); color: white; }
        .btn-success:hover { background-color: var(--primary-dark); }
        .btn-danger { background-color: #F44336; color: white; }
        .btn-danger:hover { background-color: #D32F2F; }
        .btn-info { background-color: #2196F3; color: white; }
        .btn-info:hover { background-color: #1976D2; }
        .btn-back { background-color: #aaa; color: white; }
        .btn-back:hover { background-color: #888; }
        
        .contraproposta-form {
            background-color: #fcfcfc;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
        }
        
        .form-group { margin-bottom: 15px; }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <!-- Nova Navbar no estilo do index.php -->
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link">Meus Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="propostas.php" class="nav-link active">Propostas</a>
                    </li>
                    <li class="nav-item">
                        <a href="precos.php" class="nav-link">Médias de Preços</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link login-button no-underline">Sair</a>
                    </li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <br>

    <main class="container details-container">
        
        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success" style="margin-bottom: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 5px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['sucesso']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 5px;">
                <i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($_GET['erro']); ?>
            </div>
        <?php endif; ?>

        <div class="proposta-details">
            <div class="header-details">
                <h1>Proposta de <?php echo htmlspecialchars($proposta['nome_comprador']); ?></h1>
                <span class="status-badge <?php echo $status_info['class']; ?>">
                    <i class="<?php echo $status_info['icon']; ?>"></i> 
                    <?php echo $status_info['text']; ?>
                </span>
            </div>
            
            <div class="info-section">
                <div class="info-card">
                    <h4><i class="fas fa-box-open"></i> Produto Anunciado</h4>
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($proposta['produto_nome']); ?></p>
                    <p><strong>Preço Anunciado:</strong> R$ <?php echo number_format($proposta['preco_anuncio_original'], 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                    <p><strong>Comprador:</strong> <?php echo htmlspecialchars($proposta['nome_comprador']); ?> (<?php echo htmlspecialchars($proposta['loja_comprador']); ?>)</p>
                </div>
                
                <div class="info-card">
                    <h4><i class="fas fa-handshake"></i> Último Valor Negociado</h4>
                    <p><strong>Preço:</strong> <span class="proposta-valor">R$ <?php echo number_format($valor_atual_negociacao, 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></span></p>
                    <p><strong>Quantidade:</strong> <?php echo htmlspecialchars($quantidade_atual_negociacao) . ' ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                    <p><strong>Enviada/Atualizada em:</strong> <?php echo date('d/m/Y H:i', strtotime($proposta['data_proposta'])); ?></p>
                </div>
            </div>
            
            <div class="condicoes-section">
                <h3>Condições do Comprador (Proposta Inicial)</h3>
                <p>
                    <?php echo empty($proposta['condicoes_comprador']) ? 'Nenhuma condição especial informada.' : nl2br(htmlspecialchars($proposta['condicoes_comprador'])); ?>
                </p>
            </div>
            
            <?php // CORRIGIDO: condicoes_vendedor -> observacoes_vendedor ?>
            <?php if (!empty($proposta['observacoes_vendedor'])): ?>
                <div class="condicoes-section vendedor">
                    <h3>Sua Última Contraproposta (Condições de Venda)</h3>
                    <p>
                        <?php // CORRIGIDO: condicoes_vendedor -> observacoes_vendedor ?>
                        <?php echo nl2br(htmlspecialchars($proposta['observacoes_vendedor'])); ?>
                    </p>
                </div>
            <?php endif; ?>

            
            <div class="actions-section">
                <h2>Ações do Vendedor</h2>
                
                <?php 
                // CORRIGIDO: O if/elseif/else usará a sintaxe alternativa com ':'
                // O vendedor só pode agir se o status for 'pendente' (primeira proposta do comprador) ou 'negociacao' (contraproposta do comprador)
                ?>
                <?php if ($proposta['status'] === 'negociacao'): // Resposta a uma contraproposta do Comprador ?>
                    <p>O Comprador enviou uma **nova Contraproposta**. Você deve **Aceitar**, **Recusar** ou enviar uma **nova Contraproposta**.</p>
                    
                    <div class="action-buttons">
                        <a href="processar_decisao.php?id=<?php echo $proposta_id; ?>&action=aceitar" 
                           class="btn btn-success" 
                           onclick="return confirm('ATENÇÃO: Você está prestes a ACEITAR a Contraproposta do Comprador e concluir a negociação. Confirma?')">
                            <i class="fas fa-check"></i> 
                            Aceitar e Concluir
                        </a>
                        
                        <a href="processar_decisao.php?id=<?php echo $proposta_id; ?>&action=recusar" 
                           class="btn btn-danger"
                           onclick="return confirm('Você está prestes a RECUSAR a Contraproposta do Comprador. Isso encerrará a negociação. Confirma?')">
                            <i class="fas fa-times"></i> 
                            Recusar e Encerrar
                        </a>
                    </div>
                    
                    <h3>Ou Envie uma Nova Contraproposta:</h3>
                    
                    <div class="contraproposta-form">
                        <form action="processar_decisao.php?action=contraproposta" method="POST">
                            <input type="hidden" name="proposta_id" value="<?php echo $proposta_id; ?>">
                            <input type="hidden" name="action" value="contraproposta">
                            
                            <div class="info-section">
                                <div class="form-group">
                                    <label for="novo_preco">Novo Preço (por <?php echo htmlspecialchars($proposta['unidade_medida']); ?>)</label>
                                    <input type="number" step="0.01" id="novo_preco" name="novo_preco" 
                                           value="<?php echo htmlspecialchars($valor_atual_negociacao); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="nova_quantidade">Nova Quantidade (<?php echo htmlspecialchars($proposta['unidade_medida']); ?>)</label>
                                    <input type="number" step="0.01" id="nova_quantidade" name="nova_quantidade" 
                                           value="<?php echo htmlspecialchars($quantidade_atual_negociacao); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="novas_condicoes">Novas Condições de Pagamento/Entrega (Opcional)</label>
                                <textarea id="novas_condicoes" name="novas_condicoes" rows="3" 
                                          placeholder="Ex: Novo prazo de entrega, frete por conta do comprador, etc."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-reply"></i> 
                                Enviar Contraproposta
                            </button>
                        </form>
                    </div>

                <?php elseif ($proposta['status'] === 'pendente'): // Se for a PRIMEIRA proposta do Comprador ?>
                    <p>Esta é a proposta inicial do Comprador. Escolha uma opção:</p>
                    
                    <div class="action-buttons">
                        <a href="processar_decisao.php?id=<?php echo $proposta_id; ?>&action=aceitar" 
                           class="btn btn-success" 
                           onclick="return confirm('ATENÇÃO: Você está prestes a ACEITAR a proposta e concluir a negociação. Confirma?')">
                            <i class="fas fa-check"></i> 
                            Aceitar
                        </a>
                        
                        <a href="processar_decisao.php?id=<?php echo $proposta_id; ?>&action=recusar" 
                           class="btn btn-danger"
                           onclick="return confirm('Você está prestes a RECUSAR a proposta. Isso encerrará a negociação. Confirma?')">
                            <i class="fas fa-times"></i> 
                            Recusar
                        </a>
                        
                        <a href="#" class="btn btn-info" onclick="document.getElementById('contraproposta-form-initial').style.display='block'; this.style.display='none'; return false;">
                            <i class="fas fa-reply"></i> 
                            Fazer Contraproposta
                        </a>
                    </div>
                    
                    <div id="contraproposta-form-initial" class="contraproposta-form" style="display:none;">
                        <h3>Sua Contraproposta (Condições de Venda)</h3>
                        <form action="processar_decisao.php?action=contraproposta" method="POST">
                            <input type="hidden" name="proposta_id" value="<?php echo $proposta_id; ?>">
                            <input type="hidden" name="action" value="contraproposta">
                            
                            <div class="info-section">
                                <div class="form-group">
                                    <label for="novo_preco_initial">Novo Preço (por <?php echo htmlspecialchars($proposta['unidade_medida']); ?>)</label>
                                    <input type="number" step="0.01" id="novo_preco_initial" name="novo_preco" 
                                           value="<?php echo htmlspecialchars($valor_atual_negociacao); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="nova_quantidade_initial">Nova Quantidade (<?php echo htmlspecialchars($proposta['unidade_medida']); ?>)</label>
                                    <input type="number" step="0.01" id="nova_quantidade_initial" name="nova_quantidade" 
                                           value="<?php echo htmlspecialchars($quantidade_atual_negociacao); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="novas_condicoes_initial">Novas Condições de Pagamento/Entrega (Opcional)</label>
                                <textarea id="novas_condicoes_initial" name="novas_condicoes" rows="3" 
                                          placeholder="Ex: Novo prazo de entrega, frete por conta do comprador, etc."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-reply"></i> 
                                Enviar Contraproposta
                            </button>
                        </form>
                    </div>

                <?php else: // Status "aceita" ou "recusada" ?>
                    <p style="font-size: 1.1em; font-style: italic; color: var(--text-light);">
                        Esta negociação está encerrada com o status "<?php echo $status_info['text']; ?>".
                    </p>
                <?php endif; // Fecha o bloco condicional principal ?>
                
                <a href="propostas.php" class="btn btn-back" style="margin-top: 20px;">
                    <i class="fas fa-arrow-left"></i> 
                    Voltar para a Lista
                </a>
            </div>
        </div>
    </main>
</body>
</html>