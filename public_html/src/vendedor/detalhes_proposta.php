<?php
// src/vendedor/detalhes_proposta.php - ATUALIZADO

require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

// 2. OBTENÇÃO DO ID DA PROPOSTA
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: propostas?erro=" . urlencode("Proposta não especificada ou inválida."));
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

    // Buscar detalhes da proposta do comprador - ATUALIZADA para incluir estoque
    $sql = "SELECT 
            pc.*,
            pn.id AS negociacao_id,
            pn.status AS negociacao_status,
            pn.produto_id,
            p.nome AS produto_nome,
            p.preco AS preco_anuncio_original,
            p.estoque AS estoque_kg,
            p.estoque_unidades,
            p.modo_precificacao,
            p.embalagem_peso_kg,
            p.embalagem_unidades,
            p.unidade_medida,
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
        header("Location: propostas?erro=" . urlencode("Proposta não encontrada ou acesso negado."));
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

// Ajustar exibição de estoque e unidade conforme modo de precificação
$modo = $proposta['modo_precificacao'] ?? 'por_quilo';
if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
    $proposta['quantidade_disponivel'] = $proposta['estoque_unidades'] ?? 0;
} else {
    $proposta['quantidade_disponivel'] = $proposta['estoque_kg'] ?? 0;
}
switch ($modo) {
    case 'por_unidade': $proposta['unidade_medida'] = 'unidade'; break;
    case 'por_quilo': $proposta['unidade_medida'] = 'kg'; break;
    case 'caixa_unidades': $proposta['unidade_medida'] = 'caixa' . (!empty($proposta['embalagem_unidades']) ? " ({$proposta['embalagem_unidades']} unid)" : ''); break;
    case 'caixa_quilos': $proposta['unidade_medida'] = 'caixa' . (!empty($proposta['embalagem_peso_kg']) ? " ({$proposta['embalagem_peso_kg']} kg)" : ''); break;
    case 'saco_unidades': $proposta['unidade_medida'] = 'saco' . (!empty($proposta['embalagem_unidades']) ? " ({$proposta['embalagem_unidades']} unid)" : ''); break;
    case 'saco_quilos': $proposta['unidade_medida'] = 'saco' . (!empty($proposta['embalagem_peso_kg']) ? " ({$proposta['embalagem_peso_kg']} kg)" : ''); break;
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
// SEMPRE mostrar os dados da proposta do comprador
$valor_atual_negociacao = $proposta['preco_proposto'];
$quantidade_atual_negociacao = $proposta['quantidade_proposta'];
$condicoes_comprador_atual = $proposta['condicoes_comprador'];

// Se houver proposta do vendedor, ela será exibida na seção separada
if ($ultima_proposta_vendedor) {
    $condicoes_vendedor = $ultima_proposta_vendedor['condicoes_venda'];
} else {
    $condicoes_vendedor = null;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Proposta #<?php echo $proposta_comprador_id; ?></title>
    <link rel="stylesheet" href="../../index.css"> 
    <link rel="stylesheet" href="../css/vendedor/vendedor.css"> 
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../css/vendedor/navbar.css">
</head>
<body>
    <?php
    $active_nav = 'propostas';
    $nav_items = [
        ['key' => 'painel',        'label' => 'Painel',          'href' => 'dashboard'],
        ['key' => 'meus_anuncios', 'label' => 'Meus Anúncios',   'href' => 'anuncios'],
        ['key' => 'propostas',     'label' => 'Propostas',       'href' => 'propostas'],
        ['key' => 'precos',        'label' => 'Médias de Preços', 'href' => 'precos'],
        ['key' => 'perfil',        'label' => 'Meu Perfil',      'href' => 'perfil'],
    ];
    require __DIR__ . '/includes/navbar.php';
    ?>
    <br>

    <main class="container details-container">
        
        <?php if (isset($_GET['sucesso'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['sucesso']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>
            <div class="alert alert-danger">
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
                    <p><strong>Estoque Disponível:</strong> <?php echo htmlspecialchars($proposta['estoque_disponivel']); ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                    <p><strong>Comprador:</strong> <?php echo htmlspecialchars($proposta['nome_comprador']); ?> (<?php echo htmlspecialchars($proposta['loja_comprador']); ?>)</p>
                </div>
                
                <!-- NOVA SEÇÃO: PROPOSTA DO CLIENTE -->
                    <div class="info-card">
                        <h4><i class="fas fa-user-tag"></i> Proposta do Cliente</h4>
                        <p><strong>Preço:</strong> <span class="proposta-valor">R$ <?php echo number_format($valor_atual_negociacao, 2, ',', '.') . ' / ' . htmlspecialchars($proposta['unidade_medida']); ?></span></p>
                        <p><strong>Quantidade:</strong> <?php echo htmlspecialchars($quantidade_atual_negociacao) . ' ' . htmlspecialchars($proposta['unidade_medida']); ?></p>
                        <p><strong>Enviada em:</strong> <?php echo date('d/m/Y H:i', strtotime($proposta['data_proposta'])); ?></p>
                    </div>
                </div>

                <?php if (!empty($condicoes_comprador_atual)): ?>
                <div class="condicoes-section">
                    <h3>Condições do Comprador (Proposta Inicial)</h3>
                    <p>
                        <?php echo nl2br(htmlspecialchars($condicoes_comprador_atual)); ?>
                    </p>
                </div>
                <?php endif; ?>
            
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
                    <p class="proposta-observacao">
                        Esta negociação está encerrada com o status "<?php echo $status_info['text']; ?>".
                        <br><small>Data da conclusão: <?php echo date('d/m/Y H:i', strtotime($proposta['data_atualizacao'] ?? $proposta['data_proposta'])); ?></small>
                    </p>
                    
                <?php elseif ($status_negociacao === 'negociacao' && $status_comprador === 'enviada'): ?>
                    <!-- Comprador enviou proposta inicial, vendedor deve responder -->
                    <p>O Comprador enviou uma <strong>nova proposta</strong>. Escolha uma opção:</p>
                    
                    <div class="action-buttons">
                        <a href="processar_decisao?id=<?php echo $proposta_comprador_id; ?>&action=aceitar" 
                        class="btn btn-success" 
                        onclick="return confirm('ATENÇÃO: Você está prestes a ACEITAR a proposta e concluir a negociação. Confirma?')">
                            <i class="fas fa-check"></i> 
                            Aceitar Proposta
                        </a>
                        
                        <a href="processar_decisao?id=<?php echo $proposta_comprador_id; ?>&action=recusar" 
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
                    
                    <div id="contraproposta-form-initial" class="contraproposta-form">
                        <h3>Sua Contraproposta (Condições de Venda)</h3>
                        <form action="processar_decisao?id=<?php echo $proposta_comprador_id; ?>&action=contraproposta" method="POST">
                        <input type="hidden" name="proposta_id" value="<?php echo $proposta_comprador_id; ?>">
                            
                            <div class="info-section">
                                <div class="form-group">
                                    <label for="novo_preco_initial">Novo Preço (R$ <?php echo htmlspecialchars($proposta['unidade_medida']); ?>)</label>
                                    <input type="number" step="0.01" id="novo_preco_initial" name="novo_preco" 
                                        value="<?php echo htmlspecialchars($valor_atual_negociacao); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="nova_quantidade_initial">Nova Quantidade (<?php echo htmlspecialchars($proposta['unidade_medida']); ?>)</label>
                                    <input type="number" step="0.01" id="nova_quantidade_initial" name="nova_quantidade" 
                                        value="<?php echo htmlspecialchars($quantidade_atual_negociacao); ?>" 
                                        min="1" 
                                        max="<?php echo htmlspecialchars($proposta['estoque_disponivel']); ?>" 
                                        required>
                                    <small class="estoque-info">Estoque disponível: <?php echo htmlspecialchars($proposta['estoque_disponivel']); ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="novas_condicoes_initial">Novas Condições de Pagamento/Entrega (Opcional)</label>
                                <textarea id="novas_condicoes_initial" name="novas_condicoes" rows="3" 
                                        placeholder="Ex: Novo preço, frete por conta do comprador, etc."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-reply"></i> 
                                Enviar Contraproposta
                            </button>
                        </form>
                        
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const quantidadeInput = document.getElementById('nova_quantidade_initial');
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
                    </div>
                    
                <?php elseif ($status_negociacao === 'negociacao' && $status_comprador === 'pendente'): ?>
                    <!-- Vendedor já fez contraproposta, aguardando resposta do comprador -->
                    <p>Você enviou uma <strong>contraproposta</strong> e aguarda a resposta do comprador.</p>
                    
                    <?php if ($ultima_proposta_vendedor): ?>
                        <div class="condicoes-section vendedor compact">
                            <h3>Sua Última Contraproposta (Enviada em: <?php echo date('d/m/Y H:i', strtotime($ultima_proposta_vendedor['data_contra_proposta'])); ?>)</h3>
                            <p><strong>Preço:</strong> R$ <?php echo number_format($ultima_proposta_vendedor['preco_proposto'], 2, ',', '.'); ?> / <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                            <p><strong>Quantidade:</strong> <?php echo $ultima_proposta_vendedor['quantidade_proposta']; ?> <?php echo htmlspecialchars($proposta['unidade_medida']); ?></p>
                            <?php if (!empty($ultima_proposta_vendedor['condicoes_venda'])): ?>
                                <p><strong>Condições:</strong> <?php echo nl2br(htmlspecialchars($ultima_proposta_vendedor['condicoes_venda'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <a href="editar_contraproposta?id=<?php echo $proposta_comprador_id; ?>" class="btn btn-edit">
                            <i class="fas fa-edit"></i> 
                            Editar Contraproposta
                        </a>
                        
                        <a href="desfazer_contraproposta?id=<?php echo $proposta_comprador_id; ?>" 
                        class="btn btn-warning"
                        onclick="return confirm('ATENÇÃO: Você está prestes a DESFAZER sua contraproposta.\n\n• A contraproposta será removida\n• A proposta voltará ao estado inicial\n• O comprador verá que você ainda não respondeu\n\nConfirma esta ação?')">
                        <i class="fas fa-undo"></i> 
                            Desfazer Contraproposta
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- Status não identificado -->
                    <p class="proposta-observacao">
                        Status da negociação não identificado. Entre em contato com o suporte.
                        <br><small>Negociação: <?php echo $status_negociacao; ?> | Comprador: <?php echo $status_comprador; ?></small>
                    </p>
                <?php endif; ?>
                
                <a href="propostas" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> 
                    Voltar para a Lista
                </a>
            </div>
        </div>
    </main>
</body>
</html>