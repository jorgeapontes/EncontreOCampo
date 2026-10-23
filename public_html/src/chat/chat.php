<?php
// src/chat/chat.php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/chat_config.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];
$produto_id = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
$conversa_id_get = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;

// Capturar de onde o usuário veio
$referrer = isset($_GET['ref']) ? $_GET['ref'] : '';

if ($produto_id <= 0) {
    header("Location: ../anuncios?erro=" . urlencode("Produto não encontrado"));
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
    header("Location: ../anuncios?erro=" . urlencode("Produto não encontrado"));
    exit();
}

$vendedor_usuario_id = $produto['vendedor_usuario_id'];
$eh_vendedor_produto = ($vendedor_usuario_id == $usuario_id);

// Ajustar campos de exibição conforme modo de precificação
$modo = $produto['modo_precificacao'] ?? 'por_quilo';
if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
    $quantidade_disponivel = $produto['estoque_unidades'] ?? 0;
} else {
    $quantidade_disponivel = $produto['estoque_kg'] ?? 0;
}
switch ($modo) {
    case 'por_unidade': $unidade_medida = 'unidade'; break;
    case 'por_quilo': $unidade_medida = 'kg'; break;
    case 'caixa_unidades': $unidade_medida = 'caixa' . (!empty($produto['embalagem_unidades']) ? " ({$produto['embalagem_unidades']} unid)" : ''); break;
    case 'caixa_quilos': $unidade_medida = 'caixa' . (!empty($produto['embalagem_peso_kg']) ? " ({$produto['embalagem_peso_kg']} kg)" : ''); break;
    case 'saco_unidades': $unidade_medida = 'saco' . (!empty($produto['embalagem_unidades']) ? " ({$produto['embalagem_unidades']} unid)" : ''); break;
    case 'saco_quilos': $unidade_medida = 'saco' . (!empty($produto['embalagem_peso_kg']) ? " ({$produto['embalagem_peso_kg']} kg)" : ''); break;
    default: $unidade_medida = 'unidade';
}

// Lógica para VENDEDOR
if ($eh_vendedor_produto) {
    if ($conversa_id_get > 0) {
        $conversa_id = $conversa_id_get;
        
        $sql_conversa = "SELECT c.comprador_id, u.nome AS comprador_nome
                        FROM chat_conversas c
                        JOIN usuarios u ON c.comprador_id = u.id
                        WHERE c.id = :conversa_id
                        AND c.produto_id = :produto_id
                        AND c.vendedor_id = :vendedor_usuario_id";
        
        $stmt_conv = $conn->prepare($sql_conversa);
        $stmt_conv->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt_conv->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_conv->bindParam(':vendedor_usuario_id', $vendedor_usuario_id, PDO::PARAM_INT);
        $stmt_conv->execute();
        $conversa_info = $stmt_conv->fetch(PDO::FETCH_ASSOC);
        
        if ($conversa_info) {
            $outro_usuario_id = $conversa_info['comprador_id'];
            $outro_usuario_nome = $conversa_info['comprador_nome'];
        } else {
            $conversa_id = null;
            $outro_usuario_id = null;
            $outro_usuario_nome = null;
        }
    } else {
        $conversa_id = null;
        $outro_usuario_id = null;
        $outro_usuario_nome = null;
    }
    
    $conversas = null;
    
} else {
    // COMPRADOR
    $conversa_id = obterOuCriarConversa($conn, $produto_id, $usuario_id, $vendedor_usuario_id);
    $outro_usuario_id = $vendedor_usuario_id;
    $outro_usuario_nome = $produto['nome_vendedor'] ?: $produto['vendedor_nome'];
    $conversas = null;
}

// Determinar URL de volta
if ($eh_vendedor_produto) {
    $url_voltar = "../../src/vendedor/chats.php";
} else {
    if ($referrer === 'meus_chats') {
        $url_voltar = "../comprador/meus_chats.php";
    } else {
        $url_voltar = "../comprador/view_ad.php?anuncio_id=" . $produto_id;
    }
}

// BUSCAR ENDEREÇO E TELEFONE DO OUTRO USUÁRIO PARA EXIBIR NA SIDEBAR
$outro_usuario_endereco = null;
$outro_usuario_endereco_maps = null;
$outro_usuario_telefone = null;

if ($outro_usuario_id) {
    if ($eh_vendedor_produto) {
        // Se é vendedor, buscar endereço do comprador
        $sql_outro_usuario = "SELECT c.rua, c.numero, c.complemento, c.cidade, c.estado, c.cep, c.telefone1
                             FROM compradores c
                             WHERE c.usuario_id = :outro_usuario_id";
    } else {
        // Se é comprador, buscar endereço do vendedor
        $sql_outro_usuario = "SELECT v.rua, v.numero, v.complemento, v.cidade, v.estado, v.cep, v.telefone1
                             FROM vendedores v
                             WHERE v.usuario_id = :outro_usuario_id";
    }
    
    $stmt_outro = $conn->prepare($sql_outro_usuario);
    $stmt_outro->bindParam(':outro_usuario_id', $outro_usuario_id, PDO::PARAM_INT);
    $stmt_outro->execute();
    $outro_usuario_info = $stmt_outro->fetch(PDO::FETCH_ASSOC);
    
    if ($outro_usuario_info) {
        // Montar endereço completo
        $endereco_completo = "{$outro_usuario_info['rua']}, {$outro_usuario_info['numero']}";
        if (!empty($outro_usuario_info['complemento'])) {
            $endereco_completo .= " - {$outro_usuario_info['complemento']}";
        }
        $endereco_completo .= ", {$outro_usuario_info['cidade']} - {$outro_usuario_info['estado']}";
        
        $outro_usuario_endereco = $endereco_completo;
        $outro_usuario_endereco_maps = urlencode($endereco_completo);
        $outro_usuario_telefone = $outro_usuario_info['telefone1'];

        // Formatar telefone para WhatsApp (remover caracteres não numéricos)
        if ($outro_usuario_telefone) {
            $telefone_whatsapp = preg_replace('/[^0-9]/', '', $outro_usuario_telefone);
            // Remover o zero inicial se houver e adicionar código do Brasil (55)
            if (strlen($telefone_whatsapp) > 0) {
                if (substr($telefone_whatsapp, 0, 1) == '0') {
                    $telefone_whatsapp = substr($telefone_whatsapp, 1);
                }
                // Adicionar código do Brasil se não tiver
                if (strlen($telefone_whatsapp) <= 11) {
                    $telefone_whatsapp = '55' . $telefone_whatsapp;
                }
            }
        }
    }
}

if ($conversa_id && $outro_usuario_id) {
    // Buscar informações do outro usuário para pegar a foto
    $sql_outro_usuario_foto = "SELECT u.*, 
        IF(u.tipo = 'comprador', c.foto_perfil_url, 
           IF(u.tipo = 'vendedor', v.foto_perfil_url,
              IF(u.tipo = 'transportador', t.foto_perfil_url, NULL))) as foto_perfil
        FROM usuarios u
        LEFT JOIN compradores c ON u.tipo = 'comprador' AND u.id = c.usuario_id
        LEFT JOIN vendedores v ON u.tipo = 'vendedor' AND u.id = v.usuario_id
        LEFT JOIN transportadores t ON u.tipo = 'transportador' AND u.id = t.usuario_id
        WHERE u.id = :outro_usuario_id";
    
    $stmt_foto = $conn->prepare($sql_outro_usuario_foto);
    $stmt_foto->bindParam(':outro_usuario_id', $outro_usuario_id, PDO::PARAM_INT);
    $stmt_foto->execute();
    $outro_usuario_foto = $stmt_foto->fetch(PDO::FETCH_ASSOC);
    
    // Definir a URL da foto do perfil ou usar o ícone padrão
    $foto_perfil = null;
    if ($outro_usuario_foto && !empty($outro_usuario_foto['foto_perfil'])) {
        $foto_perfil = $outro_usuario_foto['foto_perfil'];
    }
}

// array com opções de pagamento após a definição das variáveis
$opcoes_pagamento = [
    'pagamento_ato' => 'Pagamento no Ato',
    'pagamento_entrega' => 'Pagamento na Entrega'
];

$usuario_assinou = false;
if (isset($ultima_proposta) && $ultima_proposta['status'] === 'assinando') {
    $sql_assinatura = "SELECT * FROM propostas_assinaturas 
                      WHERE proposta_id = :proposta_id AND usuario_id = :usuario_id";
    $stmt_assinatura = $conn->prepare($sql_assinatura);
    $stmt_assinatura->bindParam(':proposta_id', $ultima_proposta['ID'], PDO::PARAM_INT);
    $stmt_assinatura->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_assinatura->execute();
    $usuario_assinou = $stmt_assinatura->fetch(PDO::FETCH_ASSOC) !== false;
}

// Verificar se o outro usuário já assinou (se houver)
$outro_usuario_assinou = false;
if (isset($ultima_proposta) && $ultima_proposta['status'] === 'assinando') {
    $sql_outro_assinatura = "SELECT * FROM propostas_assinaturas 
                            WHERE proposta_id = :proposta_id AND usuario_id = :outro_usuario_id";
    $stmt_outro_assinatura = $conn->prepare($sql_outro_assinatura);
    $stmt_outro_assinatura->bindParam(':proposta_id', $ultima_proposta['ID'], PDO::PARAM_INT);
    $stmt_outro_assinatura->bindParam(':outro_usuario_id', $outro_usuario_id, PDO::PARAM_INT);
    $stmt_outro_assinatura->execute();
    $outro_usuario_assinou = $stmt_outro_assinatura->fetch(PDO::FETCH_ASSOC) !== false;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(chatCsrfToken()); ?>">
    <title>Chat - <?php echo htmlspecialchars($produto['nome']); ?></title>
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="css/chat.css?v=<?php echo filemtime(__DIR__ . '/css/chat.css'); ?>">
    <link rel="stylesheet" href="css/chat_comum.css?v=<?php echo filemtime(__DIR__ . '/css/chat_comum.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="<?php echo $conversa_id ? 'tem-conversa' : ''; ?>">
    <div class="chat-container">
        <div class="chat-sidebar">
            <!-- Produto no topo, alinhado com o cabeçalho da conversa -->
            <div class="produto-info-sidebar">
                <img src="<?php echo htmlspecialchars($produto['imagem_url'] ?: '../../img/placeholder.png'); ?>" alt="Produto">
                <div class="info">
                    <small class="produto-contexto"><i class="fas fa-comments" aria-hidden="true"></i> Conversa sobre</small>
                    <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                    <div class="preco">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></div>
                </div>
            </div>
            
            <?php if ($eh_vendedor_produto): ?>
                <div class="conversas-lista">
                    <?php if ($conversa_id && $outro_usuario_id): ?>
                        <!-- Exibir apenas a conversa atual -->
                        <div class="conversa-item ativa">
                            <div class="flex-1">
                                <div class="nome">
                                    <i class="fas fa-user icon-spaced"></i>
                                    <?php echo htmlspecialchars($outro_usuario_nome); ?>
                                </div>
                                <div class="ultima-msg">Conversa com o comprador</div>
                            </div>
                            <?php if ($conversa_id && $outro_usuario_id && !empty($telefone_whatsapp)): ?>
                                <a href="https://wa.me/<?php echo $telefone_whatsapp; ?>?text=Olá%20<?php echo urlencode($outro_usuario_nome); ?>%2C%20estamos%20conversando%20sobre%20o%20produto%20<?php echo urlencode($produto['nome']); ?>%20no%20Encontre%20o%20Campo."
                                target="_blank"
                                class="whatsapp-button"
                                title="Converse no Whatsapp com <?php echo htmlspecialchars($outro_usuario_nome); ?>">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif (!$eh_vendedor_produto): ?>
                <div class="conversas-lista">
                    <div class="conversa-item ativa">
                        <div>
                            <div class="nome">
                                <i class="fas fa-store icon-spaced"></i>
                                <?php echo htmlspecialchars($outro_usuario_nome); ?>
                            </div>
                            <div class="ultima-msg">Conversa com o vendedor</div>
                        </div>
                        <?php if ($conversa_id && $outro_usuario_id && !empty($telefone_whatsapp)): ?>
                            <a href="https://wa.me/<?php echo $telefone_whatsapp; ?>?text=Olá%20<?php echo urlencode($outro_usuario_nome); ?>%2C%20estamos%20conversando%20sobre%20o%20produto%20<?php echo urlencode($produto['nome']); ?>%20no%20Encontre%20o%20Campo."
                            target="_blank"
                            class="whatsapp-button"
                            title="Converse no Whatsapp com <?php echo htmlspecialchars($outro_usuario_nome); ?>">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- CARD DE ENDEREÇO - VERSÃO SIMPLES -->
            <?php if ($conversa_id && $outro_usuario_id && $outro_usuario_endereco): ?>
                <div class="endereco-card">
                    <div class="endereco-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>Endereço do <?php echo $eh_vendedor_produto ? 'Comprador' : 'Vendedor'; ?></h4>
                    </div>
                    <div class="endereco-info">
                        <div class="endereco-conteudo">
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $outro_usuario_endereco_maps; ?>" 
                            target="_blank" 
                            class="endereco-link"
                            title="<?php echo htmlspecialchars($outro_usuario_endereco); ?>">
                                <div class="endereco-texto-compacto">
                                    <i class="fas fa-map-marked-alt"></i>
                                    <span>
                                        <?php 
                                        $endereco_display = $outro_usuario_endereco;
                                        if (strlen($endereco_display) > 50) {
                                            echo htmlspecialchars(substr($endereco_display, 0, 47)) . '...';
                                        } else {
                                            echo htmlspecialchars($endereco_display);
                                        }
                                        ?>
                                    </span>
                                </div>
                                <?php if (!empty($outro_usuario_info['cep'])): ?>
                                    <div class="endereco-cep">
                                        CEP: <?php echo htmlspecialchars($outro_usuario_info['cep']); ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                    <div class="endereco-usuario-footer">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Este endereço é fornecido para fins de negociação.
                        </small>
                    </div>
                </div>
            <?php endif; ?>

            

            <?php
            if ($conversa_id) {
                // Buscar a última proposta desta conversa
                $sql_ultima_proposta = "SELECT * FROM propostas 
                                    WHERE produto_id = :produto_id 
                                    AND comprador_id = :comprador_id 
                                    AND vendedor_id = :vendedor_id 
                                    ORDER BY data_inicio DESC LIMIT 1";
                
                $comprador_id_param = $eh_vendedor_produto ? $outro_usuario_id : $usuario_id;
                $vendedor_id_param = $eh_vendedor_produto ? $usuario_id : $outro_usuario_id;
                
                $stmt_proposta = $conn->prepare($sql_ultima_proposta);
                $stmt_proposta->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                $stmt_proposta->bindParam(':comprador_id', $comprador_id_param, PDO::PARAM_INT);
                $stmt_proposta->bindParam(':vendedor_id', $vendedor_id_param, PDO::PARAM_INT);
                $stmt_proposta->execute();
                $ultima_proposta = $stmt_proposta->fetch(PDO::FETCH_ASSOC);
                
                if ($ultima_proposta) {
                    // Formatando os valores
                    $valor_unitario = number_format($ultima_proposta['preco_proposto'], 2, ',', '.');
                    $valor_total = $ultima_proposta['valor_total'] ? number_format($ultima_proposta['valor_total'], 2, ',', '.') : '0,00';
                    $valor_frete = number_format($ultima_proposta['valor_frete'], 2, ',', '.');
                    
                    // Mapeamento de status
                    $status_texto = [
                        'assinando' => '📝 Assinando',
                        'aceita' => '✅ Aceita',
                        'negociacao' => '🔄 Em Negociação',
                        'recusada' => '❌ Recusada',
                        'cancelada' => '⏹️ Cancelada'
                    ];
                    
                    $status_exibir = isset($status_texto[$ultima_proposta['status']]) ? 
                                    $status_texto[$ultima_proposta['status']] : 
                                    $ultima_proposta['status'];
                    $status_class = $ultima_proposta['status'];
                    
                    // Mapeamento de forma de pagamento
                    $forma_pagamento_texto = [
                        'à vista' => 'À Vista',
                        'entrega' => 'Na Entrega'
                    ];
                    
                    $forma_pagamento_exibir = isset($forma_pagamento_texto[$ultima_proposta['forma_pagamento']]) ?
                                            $forma_pagamento_texto[$ultima_proposta['forma_pagamento']] :
                                            $ultima_proposta['forma_pagamento'];
                    
                    // Mapeamento de opção de frete
                    $opcao_frete_texto = [
                        'vendedor' => 'Vendedor',
                        'comprador' => 'Comprador',
                        'entregador' => 'Transportador'
                    ];
                    
                    $opcao_frete_exibir = isset($opcao_frete_texto[$ultima_proposta['opcao_frete']]) ?
                                        $opcao_frete_texto[$ultima_proposta['opcao_frete']] :
                                        $ultima_proposta['opcao_frete'];
                    
                    // Data formatada
                    $data_formatada = date('d/m/Y H:i', strtotime($ultima_proposta['data_inicio'])); 
                    
                    $assinaturas_info = '';
                        if ($ultima_proposta['status'] === 'assinando') {
                            $sql_assinaturas = "SELECT u.nome, u.tipo, pa.data_assinatura 
                                            FROM propostas_assinaturas pa
                                            JOIN usuarios u ON pa.usuario_id = u.id
                                            WHERE pa.proposta_id = :proposta_id";
                            $stmt_assinaturas = $conn->prepare($sql_assinaturas);
                            $stmt_assinaturas->bindParam(':proposta_id', $ultima_proposta['ID'], PDO::PARAM_INT);
                            $stmt_assinaturas->execute();
                            $assinaturas = $stmt_assinaturas->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($assinaturas as $assinatura) {
                                $data = date('d/m/Y H:i', strtotime($assinatura['data_assinatura']));
                                $assinaturas_info .= '<small><i class="fas fa-check-circle icon-success"></i> ' .
                                                htmlspecialchars($assinatura['nome']) . 
                                                ' (' . $assinatura['tipo'] . ') assinou em ' . $data . '</small><br>';
                            }
                            $assinaturas_info .= '</div>';
                        }
            ?>

            <!-- Indicador de atualização (hidden) -->
            <div id="proposta-indicador" 
                data-proposta-id="<?php echo htmlspecialchars($ultima_proposta['ID']); ?>"
                data-status="<?php echo htmlspecialchars($ultima_proposta['status']); ?>"
                class="hidden"></div>
            <?php 
                }
            }
            ?>

            <!-- CARD SEMPRE VISÍVEL -->
            <div class="proposta-card" id="proposta-card">
                <div class="proposta-header">
                    <i class="fas fa-handshake"></i>
                    <h4>Acordo de Compra</h4>
                    <div class="proposta-status vazio" id="proposta-status">
                        <!-- Carregado via AJAX -->
                    </div>
                </div>
                
                <!-- ÁREA DE INFORMAÇÕES (inicialmente vazia) -->
                <div class="proposta-info" id="proposta-info">
                    <div class="proposta-placeholder" id="proposta-placeholder">
                        <i class="fas fa-comments"></i>
                        <p>Nenhuma proposta ativa no momento</p>
                    </div>
                    
                    <!-- Template para quando houver proposta -->
                    <div class="proposta-conteudo hidden" id="proposta-conteudo">
                        <div class="proposta-item" id="proposta-quantidade">
                            <span><i class="fas fa-box"></i> Quantidade:</span>
                            <strong id="quantidade-valor">-</strong>
                        </div>
                        
                        <div class="proposta-item" id="proposta-valor-unitario">
                            <span><i class="fas fa-tag"></i> Valor Unitário:</span>
                            <strong id="valor-unitario-valor">-</strong>
                        </div>
                        
                        <div class="proposta-item" id="proposta-frete">
                            <span><i class="fas fa-truck"></i> Frete:</span>
                            <strong id="frete-valor">-</strong>
                        </div>
                        
                        <div class="proposta-item" id="proposta-pagamento">
                            <span><i class="fas fa-credit-card"></i> Pagamento:</span>
                            <strong id="pagamento-valor">-</strong>
                        </div>
                        
                        <div class="proposta-item total" id="proposta-total">
                            <span><i class="fas fa-calculator"></i> Valor Total:</span>
                            <strong id="total-valor">-</strong>
                        </div>
                        
                        <div class="proposta-item" id="proposta-data">
                            <span><i class="fas fa-calendar"></i> Data:</span>
                            <small id="data-valor">-</small>
                        </div>
                    </div>
                </div>
                
                <!-- ÁREA DE AÇÕES (sempre com botão inicial) -->
                <div class="proposta-acoes" id="proposta-acoes">
                    <?php if (!$eh_vendedor_produto): ?>
                    <button type="button" class="btn-nova-proposta" id="btn-nova-proposta">
                        <i class="fas fa-handshake"></i> Fazer Proposta
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="chat-area">
            <?php if ($conversa_id && $outro_usuario_id): ?>
                <div class="chat-header">
                    <div class="chat-header-top-row">
                        <div class="usuario-info">
                        <div class="avatar-container">
                            <?php if ($foto_perfil): ?>
                                <div class="avatar" id="avatar-usuario" data-foto="<?php echo htmlspecialchars($foto_perfil); ?>">
                                    <img src="<?php echo htmlspecialchars($foto_perfil); ?>" 
                                        alt="<?php echo htmlspecialchars($outro_usuario_nome); ?>"
                                        onerror="substituirPorIcone(this);">
                                </div>
                            <?php else: ?>
                                <div class="avatar" id="avatar-usuario">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="../verperfil?usuario_id=<?php echo $outro_usuario_id; ?>"
                            class="perfil-link-reset"
                            title="Ver perfil de <?php echo htmlspecialchars($outro_usuario_nome); ?>">
                            <div class="name-and-type">
                                <h3><?php echo htmlspecialchars($outro_usuario_nome); ?></h3>
                                <small><?php echo $eh_vendedor_produto ? 'Comprador' : 'Vendedor'; ?></small>
                            </div>
                        </a>
                    </div>
                    <!-- EXIBIÇÃO DA AVALIAÇÃO MÉDIA DO OUTRO USUÁRIO -->
                    <div class="avaliacao-usuario-chat" 
                        onclick="redirectToUserReviews(<?php echo $outro_usuario_id; ?>, '<?php echo $eh_vendedor_produto ? 'comprador' : 'vendedor'; ?>')"
                        title="Clique para ver todas as avaliações de <?php echo htmlspecialchars($outro_usuario_nome); ?>">
                        <div class="avaliacao-mini">
                            <div class="estrela-media-chat">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="nota-media-chat">
                                <?php 
                                // Buscar a avaliação média do outro usuário
                                $sql_avaliacao_outro = "SELECT AVG(nota) as media, COUNT(*) as total 
                                                    FROM avaliacoes 
                                                    WHERE tipo = :tipo_usuario 
                                                    AND ";
                                
                                // Definir o campo correto baseado no tipo de usuário
                                if ($eh_vendedor_produto) {
                                    // Se o usuário atual é vendedor, o outro é comprador
                                    $tipo_avaliacao_outro = 'comprador';
                                    $sql_avaliacao_outro .= "comprador_id = :usuario_id";
                                } else {
                                    // Se o usuário atual é comprador, o outro é vendedor
                                    $tipo_avaliacao_outro = 'vendedor';
                                    $sql_avaliacao_outro .= "vendedor_id = :usuario_id";
                                }
                                
                                $stmt_avaliacao_outro = $conn->prepare($sql_avaliacao_outro);
                                $stmt_avaliacao_outro->bindParam(':tipo_usuario', $tipo_avaliacao_outro);
                                $stmt_avaliacao_outro->bindParam(':usuario_id', $outro_usuario_id, PDO::PARAM_INT);
                                $stmt_avaliacao_outro->execute();
                                $avaliacao_outro = $stmt_avaliacao_outro->fetch(PDO::FETCH_ASSOC);
                                
                                $media_outro = $avaliacao_outro['media'] ? round($avaliacao_outro['media'], 1) : 0;
                                $total_avaliacoes_outro = $avaliacao_outro['total'] ?? 0;
                                
                                echo number_format($media_outro, 1, ',', '.');
                                ?>
                            </div>
                            <div class="total-avaliacoes-chat">
                                (<?php echo $total_avaliacoes_outro; ?>)
                            </div>
                        </div>
                    </div>
                    </div>
                    <a href="<?php echo $url_voltar; ?>" class="btn-voltar">
                        <i class="fas fa-arrow-left"></i>
                        Voltar
                    </a>
                </div>

                <!-- Modal para visualização ampliada da foto de perfil -->
                <div id="modal-foto-perfil" class="modal-foto-perfil">
                    <div class="modal-foto-content">
                        <div class="modal-foto-header">
                            <h3>Foto de perfil de <?php echo htmlspecialchars($outro_usuario_nome); ?></h3>
                            <button class="btn-fechar-foto" id="fechar-foto">&times;</button>
                        </div>
                        <div class="modal-foto-body">
                            <?php if ($foto_perfil): ?>
                                <img src="<?php echo htmlspecialchars($foto_perfil); ?>" 
                                    alt="Foto de perfil de <?php echo htmlspecialchars($outro_usuario_nome); ?>"
                                    id="foto-ampliada">
                            <?php else: ?>
                                <div class="sem-foto">
                                    <i class="fas fa-user-circle"></i>
                                    <p>Usuário não tem foto de perfil</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-foto-footer">
                            <button class="btn-fechar-modal-foto" id="fechar-modal-foto">Fechar</button>
                        </div>
                    </div>
                </div>

                <div class="chat-messages" id="chat-messages"></div>
                
                <div class="chat-input">
                    <div class="chat-input-buttons">
                        <button type="button" class="btn-attach" id="btn-attach-image" title="Enviar imagem" aria-label="Enviar imagem">
                            <i class="fas fa-camera" aria-hidden="true"></i>
                        </button>

                        <?php if (!$eh_vendedor_produto): ?>
                            <button type="button" class="btn-negociar-chat" id="btn-negociar" title="Fazer proposta" aria-label="Fazer proposta">
                                <i class="fas fa-handshake" aria-hidden="true"></i>
                            </button>
                        <?php endif; ?>
                    </div>

                    <input type="file" id="image-input" accept="image/jpeg,image/png,image/gif,image/webp" multiple class="hidden">

                    <textarea id="message-input" rows="1" maxlength="5000" placeholder="Digite sua mensagem..." autocomplete="off" aria-label="Mensagem"></textarea>
                    <button type="button" id="send-btn" class="btn-send" aria-label="Enviar mensagem">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i>
                        <span>Enviar</span>
                    </button>
                </div>
            <?php else: ?>
                <div class="chat-placeholder">
                    <i class="fas fa-comments"></i>
                    <p><?php echo $eh_vendedor_produto ? 'Selecione uma conversa para começar' : 'Carregando chat...'; ?></p>
                    <?php if ($eh_vendedor_produto): ?>
                        <a href="<?php echo $url_voltar; ?>" class="btn-voltar mt-20">
                            <i class="fas fa-arrow-left"></i>
                            Voltar para Chats
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div> 

<!-- MODAL DE NEGOCIAÇÃO -->
    <div class="modal-negociacao" id="modal-negociacao">
        <div class="modal-negociacao-content">
            <div class="modal-header">
                <h3><i class="fas fa-handshake icon-spaced"></i> Acordo de Compra</h3>
                <button class="btn-fechar-modal" id="fechar-modal">&times;</button>
            </div>
            
            <div class="modal-body">
                <!-- Dados do Produto -->
                <div class="produto-info-modal">
                    <img src="<?php echo htmlspecialchars($produto['imagem_url'] ?: '../../img/placeholder.png'); ?>" 
                         alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                    <div class="flex-1">
                        <h4><?php echo htmlspecialchars($produto['nome']); ?></h4>
                        <div class="preco" id="preco-produto-modal">
                            R$ <?php 
                            $preco_exibir = $produto['desconto_ativo'] && $produto['preco_desconto'] 
                                ? $produto['preco_desconto'] 
                                : $produto['preco'];
                            echo number_format($preco_exibir, 2, ',', '.'); 
                            ?>
                            <?php if ($produto['desconto_ativo'] && $produto['preco_desconto']): ?>
                                <small class="preco-riscado">
                                    R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                                </small>
                                <small class="preco-desconto-percentual">
                                    -<?php echo number_format($produto['desconto_percentual'], 0); ?>%
                                </small>
                            <?php endif; ?>
                        </div>
                        <small>Estoque: <?php echo $produto['estoque']; ?> <?php echo htmlspecialchars($unidade_medida); ?></small>
                    </div>
                </div>
                
                <!-- Etapas -->
                <div class="etapas-negociacao">
                    <div class="etapa active" data-etapa="1">1. Dados da Negociação</div>
                    <div class="etapa" data-etapa="2">2. Logística e Frete</div>
                </div>
                
                <!-- Conteúdo das Etapas -->
                <form id="form-negociacao">
                    <!-- ETAPA 1 -->
                    <div class="etapa-conteudo active" data-etapa="1">
                        <div class="form-group">
                            <label for="quantidade">Quantidade *</label>
                            <input type="number" 
                                   id="quantidade" 
                                   name="quantidade" 
                                   min="1" 
                                   max="<?php echo $produto['estoque']; ?>"
                                   value="1"
                                   required>
                            <small>Máximo: <?php echo $produto['estoque']; ?> <?php echo htmlspecialchars($unidade_medida); ?> disponíveis</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_unitario">Valor Unitário (R$)</label>
                            <input type="number" 
                                   id="valor_unitario" 
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
                                               <?php echo $valor == 'pagamento_ato' ? 'checked' : ''; ?>
                                               required>
                                        <span><?php echo $label; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="alert alert-info" id="info-etapa1">
                            Preencha os dados da negociação para prosseguir.
                        </div>
                    </div>
                    
                    <!-- ETAPA 2 -->
                    <div class="etapa-conteudo" data-etapa="2">
                        <div class="form-group">
                            <label>Opção de Frete *</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="opcao_frete" value="frete_vendedor" required>
                                    <span>Frete por Conta do Vendedor (Entrega)</span>
                                </label>
                                
                                <label class="radio-option">
                                    <input type="radio" name="opcao_frete" value="retirada_comprador" required>
                                    <span>Frete por Conta do Comprador (Retirada)</span>
                                </label>
                                
                                <label class="radio-option">
                                    <input type="radio" name="opcao_frete" value="buscar_transportador" required>
                                    <span>A plataforma busca entregador</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Conteúdo dinâmico baseado na opção de frete -->
                        <div id="conteudo-frete-vendedor" class="hidden">
                            <div class="form-group">
                                <label for="valor_frete">Valor do Frete (R$)</label>
                                <input type="number" 
                                       id="valor_frete" 
                                       name="valor_frete" 
                                       step="0.01"
                                       min="0"
                                       value="0.00">
                            </div>
                        </div>
                        
                        <div id="conteudo-retirada" class="hidden">
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
                                <div class="endereco-retirada-box">
                                    <strong><?php echo htmlspecialchars($endereco_vendedor['nome_comercial']); ?></strong><br>
                                    <?php echo htmlspecialchars($endereco_completo); ?><br>
                                    CEP: <?php echo htmlspecialchars($endereco_vendedor['cep']); ?>
                                </div>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $endereco_maps; ?>"
                                   target="_blank"
                                   class="link-maps">
                                    <i class="fas fa-map-marker-alt"></i> Ver no Google Maps
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div id="conteudo-buscar-transportador" class="hidden">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                A plataforma irá buscar um transportador disponível para a entrega.
                                Você receberá cotações de transportadores cadastrados.
                            </div>
                            
                            <div id="aviso-pagamento-transportador" class="alert alert-warning hidden">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Atenção:</strong> Para contratar transportador pela plataforma, 
                                o pagamento deve ser feito no ato. Transportadores não aceitam pagamento na entrega.
                            </div>
                        </div>
                        
                        <!-- Resumo da Negociação -->
                        <div class="resumo-negociacao">
                            <h5><i class="fas fa-receipt"></i> Resumo da Negociação</h5>
                            <div class="resumo-item">
                                <span>Quantidade:</span>
                                <span id="resumo-quantidade">1</span> <span id="resumo-unidade">unidade</span>
                            </div>
                            <div class="resumo-item">
                                <span>Valor Unitário:</span>
                                <span id="resumo-valor-unitario">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                            </div>
                            <div class="resumo-item">
                                <span>Subtotal:</span>
                                <span id="resumo-subtotal">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                            </div>
                            <div class="resumo-item">
                                <span>Frete:</span>
                                <span id="resumo-frete">R$ 0,00</span>
                            </div>
                            <div class="resumo-item">
                                <span>Forma de Pagamento:</span>
                                <span id="resumo-pagamento">Pagamento no Ato</span>
                            </div>
                            <div class="resumo-total">
                                <span>Total:</span>
                                <span id="resumo-total">R$ <?php echo number_format($preco_exibir, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Avisos dinâmicos -->
                        <div id="aviso-pagamento-entrega" class="alert alert-warning hidden">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>O Vendedor deverá cobrar o valor total de R$ <span id="valor-total-aviso">0,00</span> no ato da entrega.</strong>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-voltar-etapa hidden" id="btn-voltar-etapa">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>
                
                <div class="flex-1"></div>
                
                <button type="button" class="btn-proximo-etapa" id="btn-proximo-etapa">
                    Próxima Etapa <i class="fas fa-arrow-right"></i>
                </button>
                
                <button type="button" class="btn-finalizar-negociacao hidden" id="btn-finalizar-negociacao">
                    <i class="fas fa-check"></i> Finalizar Negociação
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DE ASSINATURA DIGITAL -->
    <div class="modal-assinatura" id="modal-assinatura">
        <div class="modal-assinatura-content">
            <div class="modal-assinatura-header">
                <h3>
                    <i class="fas fa-signature"></i>
                    Assinar Acordo
                </h3>
                <button class="btn-fechar-modal-assinatura" id="fechar-modal-assinatura">&times;</button>
            </div>
            
            <div class="modal-assinatura-body">
                <div class="assinatura-info">
                    <p><strong>Proposta ID:</strong> <span id="assinatura-proposta-id"></span></p>
                    <p><strong>Produto:</strong> <span id="assinatura-produto-nome"><?php echo htmlspecialchars($produto['nome']); ?></span></p>
                    <p>Desenhe sua assinatura no quadro abaixo:</p>
                </div>
                
                <div class="canvas-container">
                    <canvas id="signature-canvas"></canvas>
                </div>
                
                <div class="assinatura-status" id="assinatura-status">
                    <!-- Será preenchido via JavaScript -->
                </div>
                
                <div class="assinatura-botoes">
                    <button type="button" class="btn-limpar-assinatura" id="limpar-assinatura">
                        <i class="fas fa-eraser"></i> Limpar
                    </button>
                    <button type="button" class="btn-assinar" id="confirmar-assinatura">
                        <i class="fas fa-check"></i> Confirmar Assinatura
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($conversa_id && $outro_usuario_id): ?>
    <script>
        // Dados da página para js/chat.js
        window.CHAT_CONFIG = <?php echo json_encode([
            'conversaId' => (int)$conversa_id,
            'usuarioId' => (int)$usuario_id,
            'produtoId' => (int)$produto_id,
            'ehVendedor' => (bool)$eh_vendedor_produto,
            'unidadeMedida' => $unidade_medida,
            'precoExibir' => (float)$preco_exibir,
            'estoque' => (float)$produto['estoque'],
            'nomeOutro' => $outro_usuario_nome,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="js/chat_comum.js?v=<?php echo filemtime(__DIR__ . '/js/chat_comum.js'); ?>"></script>
    <script src="js/chat.js?v=<?php echo filemtime(__DIR__ . '/js/chat.js'); ?>"></script>
    <?php endif; ?>
</body>
</html>