<?php
// src/chat/chat.php
session_start();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/chat_config.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
$conversa_id_get = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;

// Capturar de onde o usuário veio
$referrer = isset($_GET['ref']) ? $_GET['ref'] : '';

if ($produto_id <= 0) {
    header("Location: ../anuncios.php?erro=" . urlencode("Produto não encontrado"));
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Buscar informações do produto
$sql_produto = "SELECT p.*, v.id AS vendedor_sistema_id, u.id AS vendedor_usuario_id, 
                v.nome_comercial AS nome_vendedor, u.nome AS vendedor_nome
                FROM produtos p
                JOIN vendedores v ON p.vendedor_id = v.id
                JOIN usuarios u ON v.usuario_id = u.id
                WHERE p.id = :produto_id";

$stmt = $conn->prepare($sql_produto);
$stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
$stmt->execute();
$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    header("Location: ../anuncios.php?erro=" . urlencode("Produto não encontrado"));
    exit();
}

$vendedor_usuario_id = $produto['vendedor_usuario_id'];
$eh_vendedor_produto = ($vendedor_usuario_id == $usuario_id);

// Lógica para VENDEDOR
if ($eh_vendedor_produto) {
    if ($conversa_id_get > 0) {
        $conversa_id = $conversa_id_get;
        
        $sql_conversa = "SELECT c.comprador_id, u.nome AS comprador_nome
                        FROM chat_conversas c
                        JOIN usuarios u ON c.comprador_id = u.id
                        WHERE c.id = :conversa_id";
        
        $stmt_conv = $conn->prepare($sql_conversa);
        $stmt_conv->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt_conv->execute();
        $conversa_info = $stmt_conv->fetch(PDO::FETCH_ASSOC);
        
        if ($conversa_info) {
            $outro_usuario_id = $conversa_info['comprador_id'];
            $outro_usuario_nome = $conversa_info['comprador_nome'];
            
            // Buscar apenas esta conversa específica para exibir na sidebar
            $sql_conversas = "SELECT c.*, 
                             u.nome AS comprador_nome,
                             (SELECT COUNT(*) FROM chat_mensagens 
                              WHERE conversa_id = c.id AND lida = 0 AND remetente_id != :usuario_id) as nao_lidas
                             FROM chat_conversas c
                             JOIN usuarios u ON c.comprador_id = u.id
                             WHERE c.id = :conversa_id
                             ORDER BY c.ultima_mensagem_data DESC";
            
            $stmt_conversas = $conn->prepare($sql_conversas);
            $stmt_conversas->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
            $stmt_conversas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_conversas->execute();
            $conversas = $stmt_conversas->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $conversa_id = null;
            $outro_usuario_id = null;
            $outro_usuario_nome = null;
            // Buscar todas as conversas se a conversa específica não for encontrada
            $sql_conversas = "SELECT DISTINCT c.*, 
                              u.nome AS comprador_nome,
                              (SELECT COUNT(*) FROM chat_mensagens 
                               WHERE conversa_id = c.id AND lida = 0 AND remetente_id != :usuario_id) as nao_lidas
                              FROM chat_conversas c
                              JOIN usuarios u ON c.comprador_id = u.id
                              WHERE c.produto_id = :produto_id 
                              AND c.vendedor_id = :usuario_id
                              ORDER BY c.ultima_mensagem_data DESC";
            
            $stmt_conversas = $conn->prepare($sql_conversas);
            $stmt_conversas->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_conversas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_conversas->execute();
            $conversas = $stmt_conversas->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $conversa_id = null;
        $outro_usuario_id = null;
        $outro_usuario_nome = null;
        // Buscar todas as conversas se não houver conversa específica selecionada
        $sql_conversas = "SELECT DISTINCT c.*, 
                          u.nome AS comprador_nome,
                          (SELECT COUNT(*) FROM chat_mensagens 
                           WHERE conversa_id = c.id AND lida = 0 AND remetente_id != :usuario_id) as nao_lidas
                          FROM chat_conversas c
                          JOIN usuarios u ON c.comprador_id = u.id
                          WHERE c.produto_id = :produto_id 
                          AND c.vendedor_id = :usuario_id
                          ORDER BY c.ultima_mensagem_data DESC";
        
        $stmt_conversas = $conn->prepare($sql_conversas);
        $stmt_conversas->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_conversas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_conversas->execute();
        $conversas = $stmt_conversas->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // COMPRADOR (mantém o mesmo)
    $conversa_id = obterOuCriarConversa($conn, $produto_id, $usuario_id, $vendedor_usuario_id);
    $outro_usuario_id = $vendedor_usuario_id;
    $outro_usuario_nome = $produto['nome_vendedor'] ?: $produto['vendedor_nome'];
    $conversas = null;
}

// Buscar endereço do outro usuário (vendedor para comprador, comprador para vendedor)
if ($eh_vendedor_produto) {
    // Se é vendedor, buscar endereço do comprador
    $sql_endereco_outro = "SELECT 
        c.nome_comercial, c.cep, c.rua, c.numero, c.complemento, 
        c.cidade, c.estado, c.telefone1, c.telefone2
        FROM compradores c
        WHERE c.usuario_id = :outro_id";
    
    $outro_tipo = 'comprador';
} else {
    // Se é comprador, buscar endereço do vendedor
    $sql_endereco_outro = "SELECT 
        v.nome_comercial, v.cep, v.rua, v.numero, v.complemento, 
        v.cidade, v.estado, v.telefone1, v.telefone2,
        u.nome as nome_usuario
        FROM vendedores v
        JOIN usuarios u ON v.usuario_id = u.id
        WHERE v.usuario_id = :outro_id";
    
    $outro_tipo = 'vendedor';
}

// Executar query para buscar endereço
$endereco_outro = null;
if (isset($outro_usuario_id) && $outro_usuario_id > 0) {
    $stmt_end_outro = $conn->prepare($sql_endereco_outro);
    $stmt_end_outro->bindParam(':outro_id', $outro_usuario_id, PDO::PARAM_INT);
    $stmt_end_outro->execute();
    $endereco_outro = $stmt_end_outro->fetch(PDO::FETCH_ASSOC);
}

// Preparar endereço formatado
$endereco_formatado = '';
$endereco_maps = '';
if ($endereco_outro) {
    $nome_display = $endereco_outro['nome_comercial'] ?? 
                   ($endereco_outro['nome_usuario'] ?? $outro_usuario_nome);
    
    $endereco_formatado = "{$endereco_outro['rua']}, {$endereco_outro['numero']}";
    if (!empty($endereco_outro['complemento'])) {
        $endereco_formatado .= " - {$endereco_outro['complemento']}";
    }
    $endereco_formatado .= ", {$endereco_outro['cidade']} - {$endereco_outro['estado']}";
    
    if (!empty($endereco_outro['cep'])) {
        $endereco_formatado .= "\nCEP: {$endereco_outro['cep']}";
    }
    
    if (!empty($endereco_outro['telefone1'])) {
        $telefone_display = $endereco_outro['telefone1'];
        if (!empty($endereco_outro['telefone2'])) {
            $telefone_display .= " / {$endereco_outro['telefone2']}";
        }
        $endereco_formatado .= "\nTelefone: {$telefone_display}";
    }
    
    // Preparar endereço para Google Maps
    $endereco_maps = urlencode("{$endereco_outro['rua']} {$endereco_outro['numero']}, {$endereco_outro['cidade']}, {$endereco_outro['estado']}");
}

// Determinar URL de volta
if ($eh_vendedor_produto) {
    $url_voltar = "../../src/vendedor/chats.php";
} else {
    if ($referrer === 'meus_chats') {
        $url_voltar = "../comprador/meus_chats.php";
    } else {
        $url_voltar = "../comprador/proposta_nova.php?anuncio_id=" . $produto_id;
    }
}

// array com opções de pagamento após a definição das variáveis
$opcoes_pagamento = [
    'à vista' => 'Pagamento à Vista',
    'entrega' => 'Pagamento na Entrega'
];

$opcoes_frete = [
    'vendedor' => 'Frete por Conta do Vendedor (Entrega)',
    'comprador' => 'Frete por Conta do Comprador (Retirada)',
    'entregador' => 'A plataforma busca entregador'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - <?php echo htmlspecialchars($produto['nome']); ?></title>
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="css/chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @media (max-width: 768px) {
            .chat-sidebar {
                width: 100%;
                display: <?php echo $conversa_id ? 'none' : 'flex'; ?>;
            }
            
            .chat-area {
                display: <?php echo $conversa_id ? 'flex' : 'none'; ?>;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-sidebar">
        <div class="sidebar-main-content">
            <div class="sidebar-header">
                <h2>
                    <i class="fas fa-comments"></i>
                    Chat
                </h2>
                <small><?php echo htmlspecialchars($produto['nome']); ?></small>
            </div>
            
            <div class="produto-info-sidebar">
                <img src="<?php echo htmlspecialchars($produto['imagem_url'] ?: '../../img/placeholder.png'); ?>" alt="Produto">
                <div class="info">
                    <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                    <div class="preco">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                </div>
            </div>

            <?php if ($eh_vendedor_produto && $conversas): ?>
                <div class="conversas-lista">
                    <?php if (count($conversas) > 0): ?>
                        <?php foreach ($conversas as $conv): ?>
                            <div class="conversa-item <?php echo ($conversa_id && $conv['id'] == $conversa_id) ? 'ativa' : ''; ?>" 
                                onclick="location.href='chat.php?produto_id=<?php echo $produto_id; ?>&conversa_id=<?php echo $conv['id']; ?>'">
                                <div style="flex: 1;">
                                    <div class="nome"><?php echo htmlspecialchars($conv['comprador_nome']); ?></div>
                                    <?php if ($conv['ultima_mensagem']): ?>
                                        <div class="ultima-msg"><?php 
                                            $msg = htmlspecialchars($conv['ultima_mensagem']);
                                            // Truncar mensagem muito longa
                                            echo strlen($msg) > 30 ? substr($msg, 0, 30) . '...' : $msg;
                                        ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($conv['nao_lidas'] > 0): ?>
                                    <div class="badge-nao-lidas"><?php echo $conv['nao_lidas']; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php elseif (!$eh_vendedor_produto): ?>
                <div class="conversas-lista">
                    <div class="conversa-item ativa">
                        <div>
                            <div class="nome">
                                <i class="fas fa-store" style="margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($outro_usuario_nome); ?>
                            </div>
                            <div class="ultima-msg">Conversa com o vendedor</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($conversa_id && $outro_usuario_id && $endereco_outro): ?>
            <div class="endereco-usuario-sidebar">
                <div class="endereco-usuario-header">
                    <h4>
                        <i class="fas fa-map-marker-alt"></i>
                        Endereço de <?php echo htmlspecialchars($nome_display ?? $outro_usuario_nome); ?>
                    </h4>
                </div>
                
                <span class="endereco-texto">
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $endereco_maps; ?>" 
                        target="_blank" 
                        class="btn-ver-maps">
                            <i class="fas fa-map-marked-alt" style="padding: 5px;"></i>
                            <?php 
                                $endereco_linha1 = htmlspecialchars($endereco_outro['rua'] . ', ' . $endereco_outro['numero']);
                                if (!empty($endereco_outro['complemento'])) {
                                    $endereco_linha1 .= ' - ' . htmlspecialchars($endereco_outro['complemento']);
                                }
                                $endereco_linha1 .= ', ' . htmlspecialchars($endereco_outro['cidade'] . ' - ' . $endereco_outro['estado']);
                                $endereco_linha1 .= ', ' . htmlspecialchars($endereco_outro['cep']);
                                echo $endereco_linha1;
                            ?>
                        </a>
                </span>
                
                <div class="endereco-usuario-footer">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        Este endereço é fornecido para fins de negociação.
                    </small>
                </div>
            </div>

            <?php elseif ($conversa_id && $outro_usuario_id): ?>
            <div class="endereco-usuario-sidebar endereco-indisponivel">
                <div class="endereco-usuario-header">
                    <h4>
                        <i class="fas fa-map-marker-alt"></i>
                        Endereço do <?php echo $eh_vendedor_produto ? 'Comprador' : 'Vendedor'; ?>
                    </h4>
                </div>
                
                <div class="endereco-usuario-content">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <p>O <?php echo $eh_vendedor_produto ? 'comprador' : 'vendedor'; ?> ainda não cadastrou um endereço completo.</p>
                        <small>Solicite as informações de endereço durante a negociação.</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- NEGOCIAÇÃO NA SIDEBAR -->
            <div class="negociacao-absolute-bottom" id="negociacao-absolute-bottom">
            <div class="negociacao-wrapper">
                <div class="negociacao-content" id="negociacao-content">
                    <div class="negociacao-content-header">
                        <center>
                            <i class="fas fa-handshake"></i>
                            Enviar Acordo de Compra
                        </center>
                    </div>
                    
                    <!-- Barra de progresso -->
                    <div class="progress-bar">
                        <div class="progress-bar-fill" id="progress-bar-fill"></div>
                    </div>
                    
                    <!-- Etapas -->
                    <div class="etapas-negociacao-sidebar">
                        <div class="etapa-sidebar active" data-etapa-sidebar="1">1. Dados da Negociação</div>
                        <div class="etapa-sidebar" data-etapa-sidebar="2">2. Logística e Frete</div>
                    </div>
                    
                    <!-- Conteúdo das Etapas -->
                    <form id="form-negociacao-sidebar">
                        <!-- ETAPA 1: Dados da Negociação -->
                        <div class="etapa-conteudo-sidebar active" id="etapa1-conteudo" data-etapa-sidebar="1">
                            <div class="form-group">
                                <label for="quantidade-sidebar">Quantidade *</label>
                                <input type="number" 
                                    id="quantidade-sidebar" 
                                    name="quantidade" 
                                    min="1" 
                                    max="<?php echo $produto['estoque']; ?>"
                                    value="1"
                                    required>
                                <small>Máximo: <?php echo $produto['estoque']; ?> unidades disponíveis</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="valor_unitario-sidebar">Valor Unitário (R$) *</label>
                                <?php 
                                $preco_exibir = $produto['desconto_ativo'] && $produto['preco_desconto'] 
                                    ? $produto['preco_desconto'] 
                                    : $produto['preco'];
                                ?>
                                <input type="number" 
                                    id="valor_unitario-sidebar" 
                                    name="valor_unitario" 
                                    step="0.01"
                                    min="0.01"
                                    value="<?php echo number_format($preco_exibir, 2, '.', ''); ?>"
                                    required>
                            </div>
                            
                            <div class="form-group">
                                <label>Forma de Pagamento *</label>
                                <div class="radio-group">
                                    <?php foreach ($opcoes_pagamento as $valor => $label): ?>
                                        <label class="radio-option">
                                            <input type="radio" 
                                                name="forma_pagamento" 
                                                value="<?php echo $valor; ?>"
                                                <?php echo $valor == 'à vista' ? 'checked' : ''; ?>
                                                required>
                                            <span><?php echo $label; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ETAPA 2: Logística e Frete -->
                        <div class="etapa-conteudo-sidebar" id="etapa2-conteudo" data-etapa-sidebar="2">
                            <div class="form-group">
                                <label>Opção de Frete *</label>
                                <div class="radio-group">
                                    <?php foreach ($opcoes_frete as $valor => $label): ?>
                                        <label class="radio-option">
                                            <input type="radio" 
                                                name="opcao_frete" 
                                                value="<?php echo $valor; ?>"
                                                <?php echo $valor == 'vendedor' ? 'checked' : ''; ?>
                                                required>
                                            <span><?php echo $label; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Conteúdo dinâmico baseado na opção de frete -->
                            <div id="conteudo-frete-vendedor-sidebar" class="conteudo-frete-sidebar">
                                <div class="form-group">
                                    <label for="valor_frete-sidebar">Valor do Frete (R$)</label>
                                    <input type="number" 
                                        id="valor_frete-sidebar" 
                                        name="valor_frete" 
                                        step="0.01"
                                        min="0"
                                        value="0.00">
                                </div>
                            </div>
                            
                            <div id="conteudo-retirada-sidebar" class="conteudo-frete-sidebar">
                                <?php 
                                    // Buscar endereço do vendedor
                                    $sql_endereco_vendedor = "SELECT 
                                        v.rua, v.numero, v.complemento, v.cidade, v.estado,
                                        v.cep, v.nome_comercial
                                        FROM vendedores v
                                        WHERE v.usuario_id = :vendedor_id";
                                    
                                    $stmt_end = $conn->prepare($sql_endereco_vendedor);
                                    $stmt_end->bindParam(':vendedor_id', $vendedor_usuario_id, PDO::PARAM_INT);
                                    $stmt_end->execute();
                                    $endereco_vendedor = $stmt_end->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($endereco_vendedor):
                                        $endereco_completo = "{$endereco_vendedor['rua']}, {$endereco_vendedor['numero']}";
                                        if (!empty($endereco_vendedor['complemento'])) {
                                            $endereco_completo .= " - {$endereco_vendedor['complemento']}";
                                        }
                                        $endereco_completo .= ", {$endereco_vendedor['cidade']} - {$endereco_vendedor['estado']}";
                                        
                                        $endereco_maps = urlencode($endereco_completo);
                                    ?>
                                    <div class="form-group">
                                        <label>Endereço para Retirada</label>
                                        <div class="endereco-vendedor-info">
                                            <strong><?php echo htmlspecialchars($endereco_vendedor['nome_comercial']); ?></strong><br>
                                            <?php echo htmlspecialchars($endereco_completo); ?><br>
                                            CEP: <?php echo htmlspecialchars($endereco_vendedor['cep']); ?>
                                        </div>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $endereco_maps; ?>" 
                                        target="_blank" 
                                        style="color: #1877f2; text-decoration: none; font-weight: 500; font-size: 11px;">
                                            <i class="fas fa-map-marker-alt"></i> Ver no Google Maps
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div id="conteudo-buscar-transportador-sidebar" class="conteudo-frete-sidebar">
                                <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        A plataforma irá buscar um transportador disponível para a entrega.
                                    </div>
                                    
                                    <div id="aviso-pagamento-transportador-sidebar" style="display: none;" class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span><strong>Atenção:</strong> Para contratar transportador pela plataforma, o pagamento deve ser feito no ato.</span>
                                    </div>
                            </div>
                            
                            <!-- Resumo da Negociação -->
                            <div class="resumo-negociacao-sidebar">
                                <h5><i class="fas fa-receipt"></i> Resumo da Negociação</h5>
                                <div class="resumo-item-sidebar">
                                    <span>Quantidade:</span>
                                    <span id="resumo-quantidade-sidebar">1</span>
                                </div>
                                <div class="resumo-item-sidebar">
                                    <span>Valor Unitário:</span>
                                    <span id="resumo-valor-unitario-sidebar">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                                </div>
                                <div class="resumo-item-sidebar">
                                    <span>Subtotal:</span>
                                    <span id="resumo-subtotal-sidebar">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                                </div>
                                <div class="resumo-item-sidebar">
                                    <span>Frete:</span>
                                    <span id="resumo-frete-sidebar">R$ 0,00</span>
                                </div>
                                <div class="resumo-item-sidebar">
                                    <span>Forma de Pagamento:</span>
                                    <span id="resumo-pagamento-sidebar">Pagamento à Vista</span>
                                </div>
                                <div class="resumo-total-sidebar">
                                    <span>Total:</span>
                                    <span id="resumo-total-sidebar">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Botões -->
                    <div class="modal-footer-sidebar">
                        <button type="button" class="btn-prosseguir-acordo" id="btn-prosseguir-acordo-sidebar">
                            <i class="fas fa-arrow-right"></i> Prosseguir com Acordo
                        </button>
                        <button type="button" class="btn-voltar-etapa-sidebar" id="btn-voltar-etapa-sidebar" style="display: none;">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </button>
                        <button type="button" class="btn-finalizar-negociacao-sidebar" id="btn-finalizar-negociacao-sidebar" style="display: none;">
                            <i class="fas fa-check"></i> Finalizar Acordo
                        </button>
                    </div>
                </div>
                
                <button type="button" class="btn-toggle-negociacao" id="btn-abrir-negociacao-sidebar">
                    <i class="fas fa-handshake"></i> Enviar Acordo de Compra
                </button>
            </div>
            </div>
            <!-- FIM DA NEGOCIAÇÃO NA SIDEBAR -->
            </div>
        </div>
        
        <div class="chat-area">
            <?php if ($conversa_id && $outro_usuario_id): ?>
                <div class="chat-header">
                    <div class="usuario-info">
                        <div class="avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h3><?php echo htmlspecialchars($outro_usuario_nome); ?></h3>
                            <small><?php echo $eh_vendedor_produto ? 'Comprador' : 'Vendedor'; ?></small>
                        </div>
                    </div>
                    <a href="<?php echo $url_voltar; ?>" class="btn-voltar">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                </div>
                
                <div class="chat-messages" id="chat-messages"></div>
                
                <div class="chat-input">
                    <div class="chat-input-buttons">
                        <button type="button" class="btn-attach" id="btn-attach-image" title="Enviar Imagem">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    
                    <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">

                    <input type="text" id="message-input" placeholder="Digite sua mensagem..." autocomplete="off">
                    <button type="button" id="send-btn" class="btn-send">
                        <i class="fas fa-paper-plane"></i>
                        <span>Enviar</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="chat-placeholder">
                    <i class="fas fa-comments"></i>
                    <p><?php echo $eh_vendedor_produto ? 'Selecione uma conversa para começar' : 'Carregando chat...'; ?></p>
                    <?php if ($eh_vendedor_produto): ?>
                        <a href="<?php echo $url_voltar; ?>" class="btn-voltar" style="margin-top: 20px;">
                            <i class="fas fa-arrow-left"></i>
                            Voltar para Chats
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($conversa_id && $outro_usuario_id): ?>
    <script>
        const conversaId = <?php echo $conversa_id; ?>;
        const usuarioId = <?php echo $usuario_id; ?>;
        let ultimaMensagemId = 0;
        let carregandoMensagens = false;

        // Configuração do Lazy Loading usando Intersection Observer
        const placeholderImage = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 200'%3E%3Crect width='300' height='200' fill='%23f0f2f5'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='14' fill='%23a8abaf'%3ECarregando...%3C/text%3E%3C/svg%3E";

        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const src = img.getAttribute('data-src');
                    if (src) {
                        img.src = src;
                        img.onload = () => {
                            img.classList.remove('lazy-loading');
                            img.classList.add('lazy-loaded');
                        };
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        }, {
            root: document.getElementById('chat-messages'),
            rootMargin: '200px 0px',
            threshold: 0.01
        });

        function carregarMensagens() {
            if (carregandoMensagens) return;
            carregandoMensagens = true;
            
            fetch(`get_messages.php?conversa_id=${conversaId}&ultimo_id=${ultimaMensagemId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.mensagens.length > 0) {
                        const messagesDiv = document.getElementById('chat-messages');
                        const estavaNaBase = messagesDiv.scrollHeight - messagesDiv.scrollTop <= messagesDiv.clientHeight + 150;
                        let novasImagens = [];

                        data.mensagens.forEach(msg => {
                            if (msg.id > ultimaMensagemId) {
                                const div = document.createElement('div');
                                div.className = 'message ' + (msg.remetente_id == usuarioId ? 'sent' : 'received');
                                
                                let conteudoMensagem = '';
                                if (msg.tipo === 'imagem') {
                                    conteudoMensagem = `
                                        <div class="chat-image-container">
                                            <img src="${placeholderImage}" 
                                                 data-src="${msg.mensagem}" 
                                                 class="chat-image lazy-loading" 
                                                 alt="Imagem enviada"
                                                 loading="lazy">
                                        </div>`;
                                } else {
                                    conteudoMensagem = `<div>${escapeHtml(msg.mensagem)}</div>`;
                                }

                                div.innerHTML = `
                                    ${conteudoMensagem}
                                    <div class="time">${msg.data_formatada}</div>
                                `;
                                messagesDiv.appendChild(div);
                                ultimaMensagemId = msg.id;

                                if (msg.tipo === 'imagem') {
                                    novasImagens.push(div.querySelector('.chat-image'));
                                }
                            }
                        });
                        
                        novasImagens.forEach(img => imageObserver.observe(img));

                        if (estavaNaBase) {
                             setTimeout(() => {
                                messagesDiv.scrollTop = messagesDiv.scrollHeight;
                             }, 50);
                        }
                    }
                    carregandoMensagens = false;
                })
                .catch(err => {
                    console.error('Erro ao carregar mensagens:', err);
                    carregandoMensagens = false;
                });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        // --- Lógica de Envio de Texto ---
        function enviarMensagem() {
            const input = document.getElementById('message-input');
            const mensagem = input.value.trim();
            
            if (!mensagem) return;
            
            fetch('send_message.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `conversa_id=${conversaId}&mensagem=${encodeURIComponent(mensagem)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    carregarMensagens();
                }
            })
            .catch(err => console.error('Erro ao enviar mensagem:', err));
        }
        
        // --- Lógica de Envio de Imagem ---
        const btnAttach = document.getElementById('btn-attach-image');
        const fileInput = document.getElementById('image-input');
        const attachIcon = btnAttach.querySelector('i');

        btnAttach.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function() {
            if (this.files.length === 0) return;
            const file = this.files[0];
            uploadImagem(file);
        });

        function uploadImagem(file) {
            btnAttach.classList.add('loading');
            attachIcon.className = 'fas fa-spinner';
            btnAttach.disabled = true;

            const formData = new FormData();
            formData.append('imagem', file);
            formData.append('conversa_id', conversaId);

            fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    fileInput.value = '';
                    carregarMensagens();
                } else {
                    alert('Erro ao enviar imagem: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(err => {
                console.error('Erro no upload:', err);
                alert('Erro de conexão ao enviar imagem.');
            })
            .finally(() => {
                btnAttach.classList.remove('loading');
                attachIcon.className = 'fas fa-camera';
                btnAttach.disabled = false;
            });
        }

        // Event Listeners existentes
        document.getElementById('send-btn').addEventListener('click', enviarMensagem);
        document.getElementById('message-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                enviarMensagem();
            }
        });
        
        // Carregar mensagens iniciais
        carregarMensagens();
        
        // Atualizar a cada 2 segundos
        setInterval(carregarMensagens, 2000);

        // ==================== LÓGICA DA NEGOCIAÇÃO NA SIDEBAR ====================

        // Elementos da sidebar
        const negociacaoWrapper = document.querySelector('.negociacao-wrapper');
        const negociacaoContent = document.getElementById('negociacao-content');
        const btnAbrirNegociacaoSidebar = document.getElementById('btn-abrir-negociacao-sidebar');
        const progressBarFill = document.getElementById('progress-bar-fill');

        // Elementos das etapas
        const etapasSidebar = document.querySelectorAll('.etapa-sidebar');
        const conteudoEtapa1 = document.getElementById('etapa1-conteudo');
        const conteudoEtapa2 = document.getElementById('etapa2-conteudo');
        let etapaAtualSidebar = 1;

        // Elementos do formulário
        const quantidadeInputSidebar = document.getElementById('quantidade-sidebar');
        const valorUnitarioInputSidebar = document.getElementById('valor_unitario-sidebar');
        const formaPagamentoInputsSidebar = document.querySelectorAll('input[name="forma_pagamento"]');
        const opcaoFreteInputsSidebar = document.querySelectorAll('input[name="opcao_frete"]');
        const valorFreteInputSidebar = document.getElementById('valor_frete-sidebar');

        // Elementos de conteúdo dinâmico
        const conteudoFreteVendedorSidebar = document.getElementById('conteudo-frete-vendedor-sidebar');
        const conteudoRetiradaSidebar = document.getElementById('conteudo-retirada-sidebar');
        const conteudoBuscarTransportadorSidebar = document.getElementById('conteudo-buscar-transportador-sidebar');
        const avisoPagamentoTransportadorSidebar = document.getElementById('aviso-pagamento-transportador-sidebar');

        // Elementos do resumo
        const resumoQuantidadeSidebar = document.getElementById('resumo-quantidade-sidebar');
        const resumoValorUnitarioSidebar = document.getElementById('resumo-valor-unitario-sidebar');
        const resumoSubtotalSidebar = document.getElementById('resumo-subtotal-sidebar');
        const resumoFreteSidebar = document.getElementById('resumo-frete-sidebar');
        const resumoPagamentoSidebar = document.getElementById('resumo-pagamento-sidebar');
        const resumoTotalSidebar = document.getElementById('resumo-total-sidebar');

        // Botões
        const btnProsseguirAcordo = document.getElementById('btn-prosseguir-acordo-sidebar');
        const btnVoltarEtapaSidebar = document.getElementById('btn-voltar-etapa-sidebar');
        const btnFinalizarNegociacaoSidebar = document.getElementById('btn-finalizar-negociacao-sidebar');

        // Abrir/recolher conteúdo da negociação
        let negociacaoAberta = false;

        // Função para abrir/fechar negociação com animação suave
        btnAbrirNegociacaoSidebar.addEventListener('click', () => {
            if (!negociacaoAberta) {
                // Abrir negociação
                negociacaoContent.style.display = 'block';
                setTimeout(() => {
                    negociacaoContent.classList.add('active');
                    negociacaoWrapper.classList.add('open');
                }, 10);
                
                btnAbrirNegociacaoSidebar.innerHTML = '<i class="fas fa-times"></i> Fechar Negociação';
                btnAbrirNegociacaoSidebar.classList.add('active');
                negociacaoAberta = true;
                
                // Garantir que comece na etapa 1
                irParaEtapaSidebar(1);
                atualizarResumoSidebar();
                
                // Ajustar altura se necessário
                ajustarAlturaNegociacao();
            } else {
                // Fechar negociação
                negociacaoContent.classList.remove('active');
                negociacaoWrapper.classList.remove('open');
                
                // Aguardar animação para esconder conteúdo
                setTimeout(() => {
                    negociacaoContent.style.display = 'none';
                }, 300);
                
                btnAbrirNegociacaoSidebar.innerHTML = '<i class="fas fa-handshake"></i> Acordo de Compra';
                btnAbrirNegociacaoSidebar.classList.remove('active');
                negociacaoAberta = false;
                resetarFormularioSidebar();
            }
        });

        // Função para ajustar altura da negociação baseada no espaço disponível
        function ajustarAlturaNegociacao() {
            if (!negociacaoAberta) return;
            
            const windowHeight = window.innerHeight;
            const sidebar = document.querySelector('.sidebar-main-content');
            
            if (!sidebar) return;
            
            // Calcular altura disponível (altura da janela menos altura do header e outros elementos)
            const headerHeight = document.querySelector('.sidebar-header').offsetHeight;
            const produtoInfoHeight = document.querySelector('.produto-info-sidebar').offsetHeight;
            const conversasHeight = document.querySelector('.conversas-lista').offsetHeight;
            
            const alturaUsada = headerHeight + produtoInfoHeight + conversasHeight + 100; // +100 para margem
            let maxHeight = windowHeight - alturaUsada;
            
            // Limitar altura máxima e mínima
            maxHeight = Math.min(maxHeight, 600); // Máximo 600px
            maxHeight = Math.max(maxHeight, 400); // Mínimo 400px
            
            // Aplicar altura máxima
            negociacaoContent.style.maxHeight = maxHeight + 'px';
            negociacaoContent.style.overflowY = 'auto';
        }

        // Adicionar event listener para redimensionamento da janela
        window.addEventListener('resize', ajustarAlturaNegociacao);

        // Inicializar altura ao abrir
        if (negociacaoAberta) {
            setTimeout(ajustarAlturaNegociacao, 100);
        }

        // Navegação linear entre etapas
        btnProsseguirAcordo.addEventListener('click', () => {
            if (validarEtapa1()) {
                irParaEtapaSidebar(2);
                ajustarScrollResumo();
            }
        });

        btnVoltarEtapaSidebar.addEventListener('click', () => {
            irParaEtapaSidebar(1);
        });

        function irParaEtapaSidebar(numeroEtapa) {
            etapaAtualSidebar = numeroEtapa;
            
            // Atualizar visual das etapas
            etapasSidebar.forEach(etapa => {
                if (parseInt(etapa.dataset.etapaSidebar) === numeroEtapa) {
                    etapa.classList.add('active');
                } else {
                    etapa.classList.remove('active');
                }
            });
            
            // Mostrar/esconder conteúdo das etapas
            if (numeroEtapa === 1) {
                conteudoEtapa1.classList.add('active');
                conteudoEtapa2.classList.remove('active');
                
                // Atualizar botões
                btnProsseguirAcordo.style.display = 'block';
                btnVoltarEtapaSidebar.style.display = 'none';
                btnFinalizarNegociacaoSidebar.style.display = 'none';
                
                // Atualizar barra de progresso
                progressBarFill.style.width = '50%';
                
            } else if (numeroEtapa === 2) {
                conteudoEtapa1.classList.remove('active');
                conteudoEtapa2.classList.add('active');
                
                // Atualizar botões
                btnProsseguirAcordo.style.display = 'none';
                btnVoltarEtapaSidebar.style.display = 'block';
                btnFinalizarNegociacaoSidebar.style.display = 'block';
                
                // Atualizar barra de progresso
                progressBarFill.style.width = '100%';
                
                // Atualizar conteúdo dinâmico
                atualizarConteudoFreteSidebar();
                atualizarResumoSidebar();
                ajustarScrollResumo();
            }
        }

        function ajustarScrollResumo() {
            const resumoElement = document.querySelector('.resumo-negociacao-sidebar');
            if (resumoElement) {
                setTimeout(() => {
                    resumoElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }

        function validarEtapa1() {
            // Validar quantidade
            if (!quantidadeInputSidebar.value || parseInt(quantidadeInputSidebar.value) < 1) {
                alert('Por favor, insira uma quantidade válida.');
                quantidadeInputSidebar.focus();
                return false;
            }
            
            if (parseInt(quantidadeInputSidebar.value) > <?php echo $produto['estoque']; ?>) {
                alert('Quantidade excede o estoque disponível.');
                quantidadeInputSidebar.focus();
                return false;
            }
            
            // Validar valor unitário
            if (!valorUnitarioInputSidebar.value || parseFloat(valorUnitarioInputSidebar.value) <= 0) {
                alert('Por favor, insira um valor unitário válido.');
                valorUnitarioInputSidebar.focus();
                return false;
            }
            
            // Validar forma de pagamento
            let formaPagamentoSelecionada = false;
            formaPagamentoInputsSidebar.forEach(input => {
                if (input.checked) formaPagamentoSelecionada = true;
            });
            
            if (!formaPagamentoSelecionada) {
                alert('Por favor, selecione uma forma de pagamento.');
                return false;
            }
            
            return true;
        }

        // Atualizar conteúdo dinâmico baseado na opção de frete
        opcaoFreteInputsSidebar.forEach(input => {
            input.addEventListener('change', () => {
                atualizarConteudoFreteSidebar();
                atualizarResumoSidebar();
            });
        });

        function atualizarConteudoFreteSidebar() {
            // Esconder tudo primeiro
            conteudoFreteVendedorSidebar.style.display = 'none';
            conteudoRetiradaSidebar.style.display = 'none';
            conteudoBuscarTransportadorSidebar.style.display = 'none';
            avisoPagamentoTransportadorSidebar.style.display = 'none';
            
            // Mostrar conteúdo baseado na opção selecionada
            const opcaoSelecionada = document.querySelector('input[name="opcao_frete"]:checked');
            
            if (opcaoSelecionada) {
                switch (opcaoSelecionada.value) {
                    case 'vendedor':
                        conteudoFreteVendedorSidebar.style.display = 'block';
                        break;
                    case 'comprador':
                        conteudoRetiradaSidebar.style.display = 'block';
                        break;
                    case 'entregador':
                        conteudoBuscarTransportadorSidebar.style.display = 'block';
                        
                        // Verificar se pagamento é na entrega
                        const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
                        if (formaPagamentoSelecionada && formaPagamentoSelecionada.value === 'entrega') {
                            avisoPagamentoTransportadorSidebar.style.display = 'block';
                        }
                        break;
                }
            }
        }

        // Atualizar aviso de pagamento na entrega
        formaPagamentoInputsSidebar.forEach(input => {
            input.addEventListener('change', () => {
                atualizarConteudoFreteSidebar();
                atualizarResumoSidebar();
            });
        });

        // Atualizar resumo quando os valores mudam
        quantidadeInputSidebar.addEventListener('input', atualizarResumoSidebar);
        valorUnitarioInputSidebar.addEventListener('input', atualizarResumoSidebar);
        valorFreteInputSidebar.addEventListener('input', atualizarResumoSidebar);

        function atualizarResumoSidebar() {
            const quantidade = parseFloat(quantidadeInputSidebar.value) || 1;
            const valorUnitario = parseFloat(valorUnitarioInputSidebar.value) || <?php echo $preco_exibir; ?>;
            
            // Buscar valor do frete baseado na opção selecionada
            let valorFrete = 0;
            const opcaoFreteSelecionada = document.querySelector('input[name="opcao_frete"]:checked');
            
            if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'vendedor') {
                valorFrete = parseFloat(valorFreteInputSidebar.value) || 0;
            }
            
            const subtotal = quantidade * valorUnitario;
            const total = subtotal + valorFrete;
            
            // Atualizar elementos do resumo
            if (resumoQuantidadeSidebar) {
                resumoQuantidadeSidebar.textContent = quantidade.toLocaleString('pt-BR');
            }
            
            if (resumoValorUnitarioSidebar) {
                resumoValorUnitarioSidebar.textContent = 'R$ ' + valorUnitario.toFixed(2).replace('.', ',');
            }
            
            if (resumoSubtotalSidebar) {
                resumoSubtotalSidebar.textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
            }
            
            if (resumoFreteSidebar) {
                resumoFreteSidebar.textContent = 'R$ ' + valorFrete.toFixed(2).replace('.', ',');
            }
            
            if (resumoTotalSidebar) {
                resumoTotalSidebar.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
            }
            
            // Atualizar forma de pagamento no resumo
            const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
            if (formaPagamentoSelecionada && resumoPagamentoSidebar) {
                if (formaPagamentoSelecionada.value === 'à vista') {
                    resumoPagamentoSidebar.textContent = 'Pagamento à Vista';
                } else {
                    resumoPagamentoSidebar.textContent = 'Pagamento na Entrega';
                }
            }
            
            // Atualizar aviso de transportador se necessário
            atualizarConteudoFreteSidebar();
        }

        function validarEtapa2() {
            let opcaoFreteSelecionada = false;
            opcaoFreteInputsSidebar.forEach(input => {
                if (input.checked) opcaoFreteSelecionada = true;
            });
            
            if (!opcaoFreteSelecionada) {
                alert('Por favor, selecione uma opção de frete.');
                return false;
            }
            
            const opcaoSelecionada = document.querySelector('input[name="opcao_frete"]:checked').value;
            const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked').value;
            
            // Validação específica para buscar transportador
            if (opcaoSelecionada === 'entregador' && formaPagamentoSelecionada === 'entrega') {
                alert('Para contratar transportador pela plataforma, o pagamento deve ser feito no ato. Por favor, altere a forma de pagamento.');
                return false;
            }
            
            return true;
        }

        function calcularTotalSidebar() {
            const quantidade = parseFloat(quantidadeInputSidebar.value) || 1;
            const valorUnitario = parseFloat(valorUnitarioInputSidebar.value) || <?php echo $preco_exibir; ?>;
            
            // Buscar valor do frete
            let valorFrete = 0;
            const opcaoFreteSelecionada = document.querySelector('input[name="opcao_frete"]:checked');
            
            if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'vendedor') {
                valorFrete = parseFloat(valorFreteInputSidebar.value) || 0;
            }
            
            return (quantidade * valorUnitario) + valorFrete;
        }

        // Finalizar negociação
        btnFinalizarNegociacaoSidebar.addEventListener('click', () => {
            if (!validarEtapa2()) {
                return;
            }
            
            // Coletar dados da negociação
            const dadosNegociacao = {
                produto_id: <?php echo $produto_id; ?>,
                conversa_id: conversaId,
                quantidade: quantidadeInputSidebar.value,
                preco_proposto: valorUnitarioInputSidebar.value,
                forma_pagamento: document.querySelector('input[name="forma_pagamento"]:checked').value,
                opcao_frete: document.querySelector('input[name="opcao_frete"]:checked').value,
                valor_frete: '0', // Valor padrão
                total: calcularTotalSidebar(),
                usuario_tipo: '<?php echo $eh_vendedor_produto ? "vendedor" : "comprador"; ?>'
            };
            
            // Se frete for por conta do vendedor, pegar valor do input
            if (dadosNegociacao.opcao_frete === 'vendedor') {
                dadosNegociacao.valor_frete = valorFreteInputSidebar.value || '0';
            }
            
            // Enviar para o servidor
            enviarNegociacaoSidebar(dadosNegociacao);
        });

        function enviarNegociacaoSidebar(dados) {
            // Mostrar loading
            btnFinalizarNegociacaoSidebar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            btnFinalizarNegociacaoSidebar.disabled = true;
            btnVoltarEtapaSidebar.disabled = true;
            
            // Enviar para o servidor
            fetch('salvar_negociacao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dados)
            })
            .then(res => {
                // Verificar se a resposta é JSON válido
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                console.log("Resposta do servidor:", data);
                
                if (data.success) {
                    // Construir mensagem da negociação
                    let mensagemNegociacao = `*NOVA PROPOSTA DE COMPRA*\n\n` +
                        `Produto: <?php echo htmlspecialchars($produto['nome']); ?>\n` +
                        `Quantidade: ${dados.quantidade} unidades\n` +
                        `Valor unitário: R$ ${parseFloat(dados.preco_proposto).toFixed(2).replace('.', ',')}\n` +
                        `Forma de pagamento: ${dados.forma_pagamento === 'à vista' ? 'Pagamento à Vista' : 'Pagamento na Entrega'}\n` +
                        `Frete: ${obterDescricaoFreteSidebar(dados.opcao_frete)}\n`;
                    
                    // Adicionar valor do frete se aplicável
                    if (dados.opcao_frete === 'vendedor' && parseFloat(dados.valor_frete) > 0) {
                        mensagemNegociacao += `Valor do frete: R$ ${parseFloat(dados.valor_frete).toFixed(2).replace('.', ',')}\n`;
                    }
                    
                    mensagemNegociacao += `Total: R$ ${parseFloat(dados.total).toFixed(2).replace('.', ',')}\n\n` +
                        `ID da proposta: ${data.proposta_id}`;
                    
                    console.log("Mensagem a ser enviada:", mensagemNegociacao);
                    
                    // Enviar como mensagem no chat
                    return fetch('send_message.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `conversa_id=${conversaId}&mensagem=${encodeURIComponent(mensagemNegociacao)}`
                    }).then(resMsg => {
                        if (!resMsg.ok) {
                            throw new Error(`Erro ao enviar mensagem: ${resMsg.status}`);
                        }
                        return resMsg.json();
                    }).then(msgData => {
                        if (msgData.success) {
                            alert('✅ Negociação enviada com sucesso!');
                            
                            // Fechar a negociação
                            negociacaoContent.classList.remove('active');
                            negociacaoWrapper.classList.remove('open');
                            
                            setTimeout(() => {
                                negociacaoContent.style.display = 'none';
                            }, 300);
                            
                            btnAbrirNegociacaoSidebar.innerHTML = '<i class="fas fa-handshake"></i> Acordo de Compra';
                            btnAbrirNegociacaoSidebar.classList.remove('active');
                            negociacaoAberta = false;
                            resetarFormularioSidebar();
                            
                            // Recarregar mensagens para mostrar a nova proposta
                            setTimeout(() => carregarMensagens(), 1000);
                        } else {
                            alert('⚠️ Negociação salva, mas houve erro ao enviar mensagem no chat.');
                        }
                    });
                    
                } else {
                    throw new Error(data.error || 'Erro desconhecido ao salvar negociação');
                }
            })
            .catch(err => {
                console.error('Erro:', err);
                
                // Verificar se é erro de sintaxe no JSON
                if (err instanceof SyntaxError) {
                    alert('❌ Erro no formato da resposta do servidor. Verifique o console.');
                } else if (err.message && err.message.includes('Failed to fetch')) {
                    alert('❌ Erro de conexão. Verifique sua internet ou tente novamente.');
                } else {
                    alert('❌ Erro ao salvar negociação: ' + err.message);
                }
            })
            .finally(() => {
                btnFinalizarNegociacaoSidebar.innerHTML = '<i class="fas fa-check"></i> Finalizar Acordo';
                btnFinalizarNegociacaoSidebar.disabled = false;
                btnVoltarEtapaSidebar.disabled = false;
            });
        }

        function obterDescricaoFreteSidebar(opcao) {
            switch(opcao) {
                case 'vendedor': return 'Frete por conta do vendedor';
                case 'comprador': return 'Retirada pelo comprador';
                case 'entregador': return 'Buscar transportador na plataforma';
                default: return '';
            }
        }

        function resetarFormularioSidebar() {
            // Resetar para etapa 1
            etapaAtualSidebar = 1;
            irParaEtapaSidebar(1);
            
            // Resetar valores
            quantidadeInputSidebar.value = 1;
            valorUnitarioInputSidebar.value = <?php echo $preco_exibir; ?>;
            
            // Resetar radio buttons
            document.querySelector('input[name="forma_pagamento"][value="à vista"]').checked = true;
            document.querySelector('input[name="opcao_frete"][value="vendedor"]').checked = true;
            valorFreteInputSidebar.value = '0.00';
            
            // Esconder conteúdos dinâmicos
            conteudoFreteVendedorSidebar.style.display = 'none';
            conteudoRetiradaSidebar.style.display = 'none';
            conteudoBuscarTransportadorSidebar.style.display = 'none';
            avisoPagamentoTransportadorSidebar.style.display = 'none';
            
            // Atualizar resumo
            atualizarResumoSidebar();
        }

        // Inicializar
        atualizarConteudoFreteSidebar();
        atualizarResumoSidebar();
    </script>
    <?php endif; ?>
</body>
</html>