<?php
// src/vendedor/detalhes_proposta.php - ATUALIZADO

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

// 2. OBTENÇÃO DO ID DA PROPOSTA
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: propostas.php?erro=" . urlencode("Proposta não especificada ou inválida."));
    exit();
}

$proposta_comprador_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();
$proposta = null;
$vendedor_id = null;
$ultima_proposta_vendedor = null;

// 3. OBTENDO ID DO VENDEDOR E DETALHES DA PROPOSTA - ATUALIZADO
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

    // Buscar detalhes da proposta do comprador - ATUALIZADA para trazer status da negociação
    $sql = "SELECT 
                pc.*,
                pn.id AS negociacao_id,
                pn.status AS negociacao_status,  -- Status da tabela propostas_negociacao
                pn.produto_id,
                p.nome AS produto_nome,
                p.unidade_medida,
                p.preco AS preco_anuncio_original,
                u.nome AS nome_comprador,
                c.nome_comercial AS loja_comprador,
                pc.condicoes_compra AS condicoes_comprador
            FROM propostas_comprador pc
            JOIN propostas_negociacao pn ON pc.id = pn.proposta_comprador_id
            JOIN produtos p ON pn.produto_id = p.id
            JOIN compradores c ON pc.comprador_id = c.id
            JOIN usuarios u ON c.usuario_id = u.id
            WHERE pc.id = :proposta_comprador_id AND p.vendedor_id = :vendedor_id";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
    $stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt->execute();
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposta) {
        header("Location: propostas.php?erro=" . urlencode("Proposta não encontrada ou acesso negado."));
        exit();
    }

    // Buscar a última proposta do vendedor (se existir)
    $sql_vendedor_proposta = "SELECT * FROM propostas_vendedor 
                             WHERE proposta_comprador_id = :proposta_comprador_id 
                             ORDER BY data_contra_proposta DESC LIMIT 1";
    $stmt_vendedor_proposta = $conn->prepare($sql_vendedor_proposta);
    $stmt_vendedor_proposta->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
    $stmt_vendedor_proposta->execute();
    $ultima_proposta_vendedor = $stmt_vendedor_proposta->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar detalhes: " . $e->getMessage()); 
}

// Função para traduzir o status - ATUALIZADA para vendedor
function formatarStatusVendedor($status_negociacao, $status_comprador = null) {
    // Se o status da negociação for 'aceita' ou 'recusada', usa esses status
    if (in_array($status_negociacao, ['aceita', 'recusada'])) {
        $map = [
            'aceita' => ['text' => 'Aceita', 'class' => 'status-accepted', 'icon' => 'fas fa-check-circle'],
            'recusada' => ['text' => 'Recusada', 'class' => 'status-rejected', 'icon' => 'fas fa-times-circle']
        ];
        return $map[$status_negociacao] ?? ['text' => ucfirst($status_negociacao), 'class' => 'status-default', 'icon' => 'fas fa-question-circle'];
    }
    
    // Se o status da negociação for 'negociacao', verifica o status do comprador
    if ($status_negociacao === 'negociacao') {
        if ($status_comprador === 'enviada') {
            return ['text' => 'Aguardando Resposta', 'class' => 'status-pending', 'icon' => 'fas fa-clock']; // Laranja
        } elseif ($status_comprador === 'pendente') {
            return ['text' => 'Aguardando Cliente', 'class' => 'status-negotiation', 'icon' => 'fas fa-exchange-alt']; // Azul
        }
    }
    
    // Fallback para outros status
    return ['text' => ucfirst($status_negociacao), 'class' => 'status-default', 'icon' => 'fas fa-question-circle'];
}

$status_negociacao = $proposta['negociacao_status'];
$status_comprador = $proposta['status'];
$status_info = formatarStatusVendedor($status_negociacao, $status_comprador);

// Definir valores atuais da negociação
if ($ultima_proposta_vendedor) {
    // Se há proposta do vendedor, usar esses valores
    $valor_atual_negociacao = $ultima_proposta_vendedor['preco_proposto'];
    $quantidade_atual_negociacao = $ultima_proposta_vendedor['quantidade_proposta'];
    $condicoes_vendedor = $ultima_proposta_vendedor['condicoes_venda'];
} else {
    // Senão, usar os valores da proposta original do comprador
    $valor_atual_negociacao = $proposta['preco_proposto'];
    $quantidade_atual_negociacao = $proposta['quantidade_proposta'];
    $condicoes_vendedor = null;
}
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
        /* ... outros estilos ... */
        
        .btn-warning { 
            background-color: #dc3545; 
            color: white; 
        }
        
        .btn-warning:hover { 
            background-color: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-edit { 
            background-color: #FF9800; 
            color: white; 
        }
        
        .btn-edit:hover { 
            background-color: #e68900; 
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Nova Navbar no estilo do index.php -->
    <header>
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
            </div><br>
            
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
            <?php if ($ultima_proposta_vendedor && !empty($ultima_proposta_vendedor['condicoes_venda'])): ?>
                <div class="condicoes-section vendedor">
                    <h3>Sua Última Contraproposta (Condições de Venda)</h3>
                    <p><?php echo nl2br(htmlspecialchars($ultima_proposta_vendedor['condicoes_venda'])); ?></p>
                    <p><small>Enviada em: <?php echo date('d/m/Y H:i', strtotime($ultima_proposta_vendedor['data_contra_proposta'])); ?></small></p>
                </div>
            <?php endif; ?>

            <div class="actions-section">
                <h2>Ações do Vendedor</h2>
                
                <?php 
                // Lógica de exibição dos botões conforme as regras
                ?>
                
                <?php if (in_array($status_negociacao, ['aceita', 'recusada'])): ?>
                    <!-- Status finalizado - apenas visualização -->
                    <p style="font-size: 1.1em; font-style: italic; color: var(--text-light);">
                        Esta negociação está encerrada com o status "<?php echo $status_info['text']; ?>".
                        <br><small>Data da conclusão: <?php echo date('d/m/Y H:i', strtotime($proposta['data_atualizacao'] ?? $proposta['data_proposta'])); ?></small>
                    </p>
                    
                <?php elseif ($status_negociacao === 'negociacao' && $status_comprador === 'enviada'): ?>
                    <!-- Comprador enviou proposta inicial, vendedor deve responder -->
                    <p>O Comprador enviou uma <strong>nova proposta</strong>. Escolha uma opção:</p>
                    
                    <div class="action-buttons">
                        <a href="processar_decisao.php?id=<?php echo $proposta_comprador_id; ?>&action=aceitar" 
                        class="btn btn-success" 
                        onclick="return confirm('ATENÇÃO: Você está prestes a ACEITAR a proposta e concluir a negociação. Confirma?')">
                            <i class="fas fa-check"></i> 
                            Aceitar Proposta
                        </a>
                        
                        <a href="processar_decisao.php?id=<?php echo $proposta_comprador_id; ?>&action=recusar" 
                        class="btn btn-danger"
                        onclick="return confirm('Você está prestes a RECUSAR a proposta. Isso encerrará a negociação. Confirma?')">
                            <i class="fas fa-times"></i> 
                            Recusar Proposta
                        </a>
                        
                        <a href="#" class="btn btn-info" onclick="document.getElementById('contraproposta-form-initial').style.display='block'; this.style.display='none'; return false;">
                            <i class="fas fa-reply"></i> 
                            Fazer Contraproposta
                        </a>
                    </div>
                    
                    <div id="contraproposta-form-initial" class="contraproposta-form" style="display:none;">
                        <h3>Sua Contraproposta (Condições de Venda)</h3>
                        <form action="processar_decisao.php?id=<?php echo $proposta_comprador_id; ?>&action=contraproposta" method="POST">
                        <input type="hidden" name="proposta_id" value="<?php echo $proposta_comprador_id; ?>">
                            
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
                    
                <?php elseif ($status_negociacao === 'negociacao' && $status_comprador === 'pendente'): ?>
                    <!-- Vendedor já fez contraproposta, aguardando resposta do comprador -->
                    <p>Você enviou uma <strong>contraproposta</strong> e aguarda a resposta do comprador.</p>
                    
                    <?php if ($ultima_proposta_vendedor): ?>
                        <div class="condicoes-section vendedor" style="margin-bottom: 20px;">
                            <h3>Sua Última Contraproposta (Enviada em: <?php echo date('d/m/Y H:i', strtotime($ultima_proposta_vendedor['data_contra_proposta'])); ?>)</h3>
                            <p><strong>Preço:</strong> R$ <?php echo number_format($ultima_proposta_vendedor['preco_proposto'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                            <p><strong>Quantidade:</strong> <?php echo $ultima_proposta_vendedor['quantidade_proposta']; ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                            <?php if (!empty($ultima_proposta_vendedor['condicoes_venda'])): ?>
                                <p><strong>Condições:</strong> <?php echo nl2br(htmlspecialchars($ultima_proposta_vendedor['condicoes_venda'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <a href="editar_contraproposta.php?id=<?php echo $proposta_comprador_id; ?>" class="btn btn-edit">
                            <i class="fas fa-edit"></i> 
                            Editar Contraproposta
                        </a>
                        
                        <a href="desfazer_contraproposta.php?id=<?php echo $proposta_comprador_id; ?>" 
                        class="btn btn-warning"
                        onclick="return confirm('ATENÇÃO: Você está prestes a DESFAZER sua contraproposta.\n\n• A contraproposta será removida\n• A proposta voltará ao estado inicial\n• O comprador verá que você ainda não respondeu\n\nConfirma esta ação?')">
                        <i class="fas fa-undo"></i> 
                            Desfazer Contraproposta
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- Status não identificado -->
                    <p style="font-size: 1.1em; font-style: italic; color: var(--text-light);">
                        Status da negociação não identificado. Entre em contato com o suporte.
                        <br><small>Negociação: <?php echo $status_negociacao; ?> | Comprador: <?php echo $status_comprador; ?></small>
                    </p>
                <?php endif; ?>
                
                <a href="propostas.php" class="btn btn-back" style="margin-top: 20px;">
                    <i class="fas fa-arrow-left"></i> 
                    Voltar para a Lista
                </a>
            </div>
        </div>
    </main>
</body>
</html>