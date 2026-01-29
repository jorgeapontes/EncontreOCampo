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

// BUSCAR ENDEREÇO DO OUTRO USUÁRIO PARA EXIBIR NA SIDEBAR
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
            
            <?php if ($eh_vendedor_produto): ?>
                <div class="conversas-lista">
                    <?php if ($conversa_id && $outro_usuario_id): ?>
                        <!-- Exibir apenas a conversa atual -->
                        <div class="conversa-item ativa">
                            <div style="flex: 1;">
                                <div class="nome">
                                    <i class="fas fa-user" style="margin-right: 8px;"></i>
                                    <?php echo htmlspecialchars($outro_usuario_nome); ?>
                                </div>
                                <div class="ultima-msg">Conversa ativa</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Quando não há conversa selecionada -->
                        <div style="padding: 40px 20px; text-align: center; color: #65676b;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 12px; opacity: 0.3;"></i>
                            <p style="font-size: 14px;">Nenhuma conversa selecionada</p>
                        </div>
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
            
            <!-- CARD DE ENDEREÇO DO OUTRO USUÁRIO -->
            <?php if ($conversa_id && $outro_usuario_id && $outro_usuario_endereco): ?>
                <div class="endereco-card">
                    <div class="endereco-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>Endereço do <?php echo $eh_vendedor_produto ? 'Comprador' : 'Vendedor'; ?></h4>
                    </div>
                    <div class="endereco-info">
                        <div class="endereco-texto">
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $outro_usuario_endereco_maps; ?>" 
                               target="_blank" 
                               class="endereco-link">
                                <p>
                                    <i class="fas fa-map-marked-alt" style="padding: 5px;"></i>
                                    <?php echo htmlspecialchars($outro_usuario_endereco);?>,
                                    <?php echo htmlspecialchars($outro_usuario_info['cep'] ?? ''); ?>
                                </p>
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
                
                $assinaturas_info = '<div class="assinaturas-info">';
                foreach ($assinaturas as $assinatura) {
                    $data = date('d/m/Y H:i', strtotime($assinatura['data_assinatura']));
                    $assinaturas_info .= '<small><i class="fas fa-check-circle" style="color: #28a745;"></i> ' .
                                       htmlspecialchars($assinatura['nome']) . 
                                       ' (' . $assinatura['tipo'] . ') assinou em ' . $data . '</small><br>';
                }
                $assinaturas_info .= '</div>';
            }
?>
<div class="proposta-card" id="proposta-card">
    <div class="proposta-header">
        <i class="fas fa-handshake"></i>
        <h4>Acordo de Compra</h4>
        <div class="proposta-status <?php echo htmlspecialchars($status_class); ?>" 
             id="proposta-status">
            <?php echo htmlspecialchars($status_exibir); ?>
        </div>
    </div>
    
    <div class="proposta-info" id="proposta-info">
        <div class="proposta-item" id="proposta-quantidade">
            <span><i class="fas fa-box"></i> Quantidade:</span>
            <strong><?php echo htmlspecialchars($ultima_proposta['quantidade_proposta']); ?> unidades</strong>
        </div>
        
        <div class="proposta-item" id="proposta-valor-unitario">
            <span><i class="fas fa-tag"></i> Valor Unitário:</span>
            <strong>R$ <?php echo htmlspecialchars($valor_unitario); ?></strong>
        </div>
        
        <div class="proposta-item" id="proposta-frete">
            <span><i class="fas fa-truck"></i> Frete:</span>
            <strong><?php echo htmlspecialchars($opcao_frete_exibir); ?> (R$ <?php echo htmlspecialchars($valor_frete); ?>)</strong>
        </div>
        
        <div class="proposta-item" id="proposta-pagamento">
            <span><i class="fas fa-credit-card"></i> Pagamento:</span>
            <strong><?php echo htmlspecialchars($forma_pagamento_exibir); ?></strong>
        </div>
        
        <div class="proposta-item total" id="proposta-total">
            <span><i class="fas fa-calculator"></i> Valor Total:</span>
            <strong>R$ <?php echo htmlspecialchars($valor_total); ?></strong>
        </div>
        
        <div class="proposta-item" id="proposta-data">
            <span><i class="fas fa-calendar"></i> Data:</span>
            <small><?php echo htmlspecialchars($data_formatada); ?></small>
        </div>
    </div>
    
    <div class="proposta-acoes" id="proposta-acoes">
        <?php if ($ultima_proposta['status'] === 'negociacao') { ?>
            <?php if ($eh_vendedor_produto) { ?>
                <!-- Botões para vendedor -->
                <button type="button" class="btn-accept-proposal" 
                        onclick="aceitarPropostaParaAssinatura(<?php echo htmlspecialchars($ultima_proposta['ID']); ?>)">
                    <i class="fas fa-check"></i> Aceitar e Enviar para Assinatura
                </button>
                <button type="button" class="btn-reject-proposal" 
                        onclick="responderProposta('recusar', <?php echo htmlspecialchars($ultima_proposta['ID']); ?>)">
                    <i class="fas fa-times"></i> Recusar
                </button>
            <?php } else { ?>
                <!-- Botões para comprador -->
                <button type="button" class="btn-cancel-proposal" 
                        onclick="cancelarProposta(<?php echo htmlspecialchars($ultima_proposta['ID']); ?>)">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            <?php } ?>
        <?php } elseif ($ultima_proposta['status'] === 'assinando') { ?>
            <?php if (!$usuario_assinou): ?>
                <button type="button" class="btn-assinar-acordo" 
                        onclick="abrirModalAssinatura(<?php echo htmlspecialchars($ultima_proposta['ID']); ?>)">
                    <i class="fas fa-signature"></i> Assinar Acordo
                </button>
            <?php else: ?>
                <div class="proposta-finalizada assinado">
                    <i class="fas fa-check-circle" style="color: #28a745;"></i> Você já assinou este acordo
                </div>
            <?php endif; ?>
        <?php } elseif ($ultima_proposta['status'] === 'cancelada') { ?>
            <div class="proposta-finalizada cancelada">
                <i class="fas fa-ban"></i> Proposta cancelada pelo comprador
            </div>
        <?php } ?>
    </div>
    
    <div class="proposta-footer" id="proposta-footer">
        <small>
            <i class="fas fa-info-circle"></i>
            <span id="proposta-footer-text">
                <?php 
                if ($ultima_proposta['status'] === 'negociacao') {
                    echo 'Esta proposta foi enviada.';
                } elseif ($ultima_proposta['status'] === 'assinando') {
                    echo 'Aguardando assinaturas para concluir o acordo.';
                } elseif ($ultima_proposta['status'] === 'cancelada') {
                    echo 'Esta proposta foi cancelada pelo comprador.';
                } else {
                    echo "Esta proposta foi {$ultima_proposta['status']}.";
                }
                ?>
            </span>
        </small>
    </div>
</div>

<!-- Indicador de atualização (hidden) -->
<div id="proposta-indicador" 
     data-proposta-id="<?php echo htmlspecialchars($ultima_proposta['ID']); ?>"
     data-status="<?php echo htmlspecialchars($ultima_proposta['status']); ?>"
     style="display: none;"></div>
<?php 
    }
}
?>

        </div>
        
        <div class="chat-area">
            <?php if ($conversa_id && $outro_usuario_id): ?>
                <div class="chat-header">
                    <div class="usuario-info">
                        <div class="avatar-container">
                            <?php if ($foto_perfil): ?>
                                <div class="avatar" id="avatar-usuario" data-foto="<?php echo htmlspecialchars($foto_perfil); ?>" style="cursor: pointer;">
                                    <img src="<?php echo htmlspecialchars($foto_perfil); ?>" 
                                        alt="<?php echo htmlspecialchars($outro_usuario_nome); ?>"
                                        onerror="substituirPorIcone(this);">
                                </div>
                            <?php else: ?>
                                <div class="avatar" id="avatar-usuario" style="cursor: pointer;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
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
                        <button type="button" class="btn-attach" id="btn-attach-image" title="Enviar Imagem">
                            <i class="fas fa-camera"></i>
                        </button>
                        
                        <?php if (!$eh_vendedor_produto): ?>
                            <button type="button" class="btn-negociar-chat" id="btn-negociar" title="Acordo de Compra">
                                <i class="fas fa-handshake"></i>
                            </button>
                        <?php endif; ?>
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

<!-- MODAL DE NEGOCIAÇÃO -->
    <div class="modal-negociacao" id="modal-negociacao">
        <div class="modal-negociacao-content">
            <div class="modal-header">
                <h3><i class="fas fa-handshake" style="margin-right: 8px;"></i> Acordo de Compra</h3>
                <button class="btn-fechar-modal" id="fechar-modal">&times;</button>
            </div>
            
            <div class="modal-body">
                <!-- Dados do Produto -->
                <div class="produto-info-modal">
                    <img src="<?php echo htmlspecialchars($produto['imagem_url'] ?: '../../img/placeholder.png'); ?>" 
                         alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                    <div style="flex: 1;">
                        <h4><?php echo htmlspecialchars($produto['nome']); ?></h4>
                        <div class="preco" id="preco-produto-modal">
                            R$ <?php 
                            $preco_exibir = $produto['desconto_ativo'] && $produto['preco_desconto'] 
                                ? $produto['preco_desconto'] 
                                : $produto['preco'];
                            echo number_format($preco_exibir, 2, ',', '.'); 
                            ?>
                            <?php if ($produto['desconto_ativo'] && $produto['preco_desconto']): ?>
                                <small style="color: #999; text-decoration: line-through; margin-left: 8px;">
                                    R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                                </small>
                                <small style="color: #42b72a; margin-left: 8px;">
                                    -<?php echo number_format($produto['desconto_percentual'], 0); ?>%
                                </small>
                            <?php endif; ?>
                        </div>
                        <small>Estoque: <?php echo $produto['estoque']; ?> unidades</small>
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
                            <small>Máximo: <?php echo $produto['estoque']; ?> unidades disponíveis</small>
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
                        <div id="conteudo-frete-vendedor" style="display: none;">
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
                        
                        <div id="conteudo-retirada" style="display: none;">
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
                                <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                                    <strong><?php echo htmlspecialchars($endereco_vendedor['nome_comercial']); ?></strong><br>
                                    <?php echo htmlspecialchars($endereco_completo); ?><br>
                                    CEP: <?php echo htmlspecialchars($endereco_vendedor['cep']); ?>
                                </div>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $endereco_maps; ?>" 
                                   target="_blank" 
                                   style="color: #1877f2; text-decoration: none; font-weight: 500;">
                                    <i class="fas fa-map-marker-alt"></i> Ver no Google Maps
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div id="conteudo-buscar-transportador" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                A plataforma irá buscar um transportador disponível para a entrega.
                                Você receberá cotações de transportadores cadastrados.
                            </div>
                            
                            <div id="aviso-pagamento-transportador" style="display: none;" class="alert alert-warning">
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
                                <span id="resumo-quantidade">1</span>
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
                        <div id="aviso-pagamento-entrega" style="display: none;" class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>O Vendedor deverá cobrar o valor total de R$ <span id="valor-total-aviso">0,00</span> no ato da entrega.</strong>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-voltar-etapa" id="btn-voltar-etapa" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>
                
                <div style="flex: 1;"></div>
                
                <button type="button" class="btn-proximo-etapa" id="btn-proximo-etapa">
                    Próxima Etapa <i class="fas fa-arrow-right"></i>
                </button>
                
                <button type="button" class="btn-finalizar-negociacao" id="btn-finalizar-negociacao" style="display: none;">
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
        const conversaId = <?php echo $conversa_id; ?>;
        const usuarioId = <?php echo $usuario_id; ?>;
        let ultimaMensagemId = 0;
        let carregandoMensagens = false;

        // Configuração do Lazy Loading usando Intersection Observer
        // Usa uma imagem de placeholder leve enquanto a real não carrega
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
            root: document.getElementById('chat-messages'), // Observa em relação ao container do chat
            rootMargin: '200px 0px', // Começa a carregar 200px antes de aparecer
            threshold: 0.01
        });

        function carregarMensagens() {
            if (carregandoMensagens) return;
            carregandoMensagens = true;
            
            // IMPORTANTE: Seu get_messages.php deve retornar a coluna 'tipo' agora
            fetch(`get_messages.php?conversa_id=${conversaId}&ultimo_id=${ultimaMensagemId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.mensagens.length > 0) {
                        const messagesDiv = document.getElementById('chat-messages');
                        // Verifica se o usuário estava perto do fim antes de adicionar novas mensagens
                        const estavaNaBase = messagesDiv.scrollHeight - messagesDiv.scrollTop <= messagesDiv.clientHeight + 150;
                        let novasImagens = [];

                        data.mensagens.forEach(msg => {
                            if (msg.id > ultimaMensagemId) {
                                // Não exibir mensagens enviadas por usuários do tipo 'transportador' quando o usuário atual for vendedor
                                if (msg.remetente_tipo && msg.remetente_tipo === 'transportador' && <?php echo $eh_vendedor_produto ? 'true' : 'false'; ?>) {
                                    if (msg.id > ultimaMensagemId) ultimaMensagemId = msg.id;
                                    return;
                                }

                                const div = document.createElement('div');
                                div.className = 'message ' + (msg.remetente_id == usuarioId ? 'sent' : 'received');
                                
                                let conteudoMensagem = '';
                                // Verifica o tipo da mensagem
                                if (msg.tipo === 'imagem') {
                                    // Estrutura para Lazy Load
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

                                // Se for imagem, adiciona ao array para observar depois
                                if (msg.tipo === 'imagem') {
                                    novasImagens.push(div.querySelector('.chat-image'));
                                }
                            }
                        });
                        
                        // Inicia observação das novas imagens para lazy load
                        novasImagens.forEach(img => imageObserver.observe(img));

                        if (estavaNaBase) {
                             // Pequeno delay para garantir que o DOM atualizou antes de rolar
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
            // Feedback visual de carregamento no botão
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
                    // Limpa o input file e recarrega mensagens
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
                // Remove feedback visual
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
        
        // Atualizar a cada 2 segundos (aumentei um pouco para não sobrecarregar com imagens)
        setInterval(carregarMensagens, 2000);

        // ==================== LÓGICA DO MODAL DE NEGOCIAÇÃO ====================
        
        <?php if (!$eh_vendedor_produto): ?>
        // Elementos do modal
        const modalNegociacao = document.getElementById('modal-negociacao');
        const btnAbrirNegociacao = document.getElementById('btn-negociar');
        const btnFecharModal = document.getElementById('fechar-modal');
        const btnVoltarEtapa = document.getElementById('btn-voltar-etapa');
        const btnProximoEtapa = document.getElementById('btn-proximo-etapa');
        const btnFinalizarNegociacao = document.getElementById('btn-finalizar-negociacao');
        
        // Elementos das etapas
        const etapas = document.querySelectorAll('.etapa');
        const conteudosEtapas = document.querySelectorAll('.etapa-conteudo');
        let etapaAtual = 1;
        
        // Elementos do formulário
        const quantidadeInput = document.getElementById('quantidade');
        const valorUnitarioInput = document.getElementById('valor_unitario');
        const formaPagamentoInputs = document.querySelectorAll('input[name="forma_pagamento"]');
        const opcaoFreteInputs = document.querySelectorAll('input[name="opcao_frete"]');
        const valorFreteInput = document.getElementById('valor_frete');
        
        // Elementos de conteúdo dinâmico
        const conteudoFreteVendedor = document.getElementById('conteudo-frete-vendedor');
        const conteudoRetirada = document.getElementById('conteudo-retirada');
        const conteudoBuscarTransportador = document.getElementById('conteudo-buscar-transportador');
        const avisoPagamentoTransportador = document.getElementById('aviso-pagamento-transportador');
        const avisoPagamentoEntrega = document.getElementById('aviso-pagamento-entrega');
        const valorTotalAviso = document.getElementById('valor-total-aviso');
        
        // Elementos do resumo
        const resumoQuantidade = document.getElementById('resumo-quantidade');
        const resumoValorUnitario = document.getElementById('resumo-valor-unitario');
        const resumoSubtotal = document.getElementById('resumo-subtotal');
        const resumoFrete = document.getElementById('resumo-frete');
        const resumoPagamento = document.getElementById('resumo-pagamento');
        const resumoTotal = document.getElementById('resumo-total');
        
        // Abrir modal
        btnAbrirNegociacao.addEventListener('click', () => {
            modalNegociacao.classList.add('active');
            atualizarResumo();
        });
        
        // Fechar modal
        btnFecharModal.addEventListener('click', () => {
            modalNegociacao.classList.remove('active');
            resetarFormulario();
        });
        
        // Fechar modal ao clicar fora
        modalNegociacao.addEventListener('click', (e) => {
            if (e.target === modalNegociacao) {
                modalNegociacao.classList.remove('active');
                resetarFormulario();
            }
        });
        
        // Navegação entre etapas
        etapas.forEach(etapa => {
            etapa.addEventListener('click', () => {
                if (!etapa.classList.contains('active')) {
                    const novaEtapa = parseInt(etapa.dataset.etapa);
                    if (validarEtapaAtual() && novaEtapa >= etapaAtual) {
                        irParaEtapa(novaEtapa);
                    }
                }
            });
        });
        
        btnProximoEtapa.addEventListener('click', () => {
            if (validarEtapaAtual()) {
                if (etapaAtual < 2) {
                    irParaEtapa(etapaAtual + 1);
                }
            }
        });
        
        btnVoltarEtapa.addEventListener('click', () => {
            if (etapaAtual > 1) {
                irParaEtapa(etapaAtual - 1);
            }
        });
        
        function irParaEtapa(numeroEtapa) {
            // Atualizar visual das etapas
            etapas.forEach(etapa => {
                if (parseInt(etapa.dataset.etapa) === numeroEtapa) {
                    etapa.classList.add('active');
                } else {
                    etapa.classList.remove('active');
                }
            });
            
            // Mostrar conteúdo da etapa
            conteudosEtapas.forEach(conteudo => {
                if (parseInt(conteudo.dataset.etapa) === numeroEtapa) {
                    conteudo.classList.add('active');
                } else {
                    conteudo.classList.remove('active');
                }
            });
            
            // Atualizar botões
            etapaAtual = numeroEtapa;
            
            if (numeroEtapa === 1) {
                btnVoltarEtapa.style.display = 'none';
                btnProximoEtapa.style.display = 'block';
                btnFinalizarNegociacao.style.display = 'none';
            } else if (numeroEtapa === 2) {
                btnVoltarEtapa.style.display = 'block';
                btnProximoEtapa.style.display = 'none';
                btnFinalizarNegociacao.style.display = 'block';
            }
        }
        
        function validarEtapaAtual() {
            if (etapaAtual === 1) {
                // Validar etapa 1
                if (!quantidadeInput.value || parseInt(quantidadeInput.value) < 1) {
                    alert('Por favor, insira uma quantidade válida.');
                    quantidadeInput.focus();
                    return false;
                }
                
                if (parseInt(quantidadeInput.value) > <?php echo $produto['estoque']; ?>) {
                    alert('Quantidade excede o estoque disponível.');
                    quantidadeInput.focus();
                    return false;
                }
                
                if (!valorUnitarioInput.value || parseFloat(valorUnitarioInput.value) <= 0) {
                    alert('Por favor, insira um valor unitário válido.');
                    valorUnitarioInput.focus();
                    return false;
                }
                
                let formaPagamentoSelecionada = false;
                formaPagamentoInputs.forEach(input => {
                    if (input.checked) formaPagamentoSelecionada = true;
                });
                
                if (!formaPagamentoSelecionada) {
                    alert('Por favor, selecione uma forma de pagamento.');
                    return false;
                }
            }
            
            return true;
        }
        
        // Atualizar conteúdo dinâmico baseado na opção de frete
        opcaoFreteInputs.forEach(input => {
            input.addEventListener('change', () => {
                atualizarConteudoFrete();
                atualizarResumo();
            });
        });
        
        function atualizarConteudoFrete() {
            // Esconder tudo primeiro
            conteudoFreteVendedor.style.display = 'none';
            conteudoRetirada.style.display = 'none';
            conteudoBuscarTransportador.style.display = 'none';
            avisoPagamentoTransportador.style.display = 'none';
            
            // Mostrar conteúdo baseado na opção selecionada
            const opcaoSelecionada = document.querySelector('input[name="opcao_frete"]:checked');
            
            if (opcaoSelecionada) {
                switch (opcaoSelecionada.value) {
                    case 'frete_vendedor':
                        conteudoFreteVendedor.style.display = 'block';
                        break;
                    case 'retirada_comprador':
                        conteudoRetirada.style.display = 'block';
                        break;
                    case 'buscar_transportador':
                        conteudoBuscarTransportador.style.display = 'block';
                        
                        // Verificar se pagamento é na entrega
                        const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
                        if (formaPagamentoSelecionada && formaPagamentoSelecionada.value === 'pagamento_entrega') {
                            avisoPagamentoTransportador.style.display = 'block';
                        }
                        break;
                }
            }
        }
        
        // Atualizar aviso de pagamento na entrega
        formaPagamentoInputs.forEach(input => {
            input.addEventListener('change', () => {
                atualizarAvisosPagamento();
                atualizarResumo();
            });
        });
        
        function atualizarAvisosPagamento() {
            const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
            const opcaoFreteSelecionada = document.querySelector('input[name="opcao_frete"]:checked');
            
            if (formaPagamentoSelecionada && formaPagamentoSelecionada.value === 'pagamento_entrega') {
                // Mostrar aviso para frete do vendedor
                if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'frete_vendedor') {
                    avisoPagamentoEntrega.style.display = 'block';
                    atualizarValorAviso();
                } else {
                    avisoPagamentoEntrega.style.display = 'none';
                }
                
                // Mostrar aviso para buscar transportador
                if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'buscar_transportador') {
                    avisoPagamentoTransportador.style.display = 'block';
                }
            } else {
                avisoPagamentoEntrega.style.display = 'none';
                if (opcaoFreteSelecionada && opcaoFreteSelecionada.value === 'buscar_transportador') {
                    avisoPagamentoTransportador.style.display = 'none';
                }
            }
        }
        
        function atualizarValorAviso() {
            const quantidade = parseFloat(quantidadeInput.value) || 1;
            const valorUnitario = parseFloat(valorUnitarioInput.value) || <?php echo $preco_exibir; ?>;
            const valorFrete = parseFloat(valorFreteInput.value) || 0;
            
            const total = (quantidade * valorUnitario) + valorFrete;
            valorTotalAviso.textContent = total.toFixed(2).replace('.', ',');
        }
        
        // Atualizar resumo quando os valores mudam
        quantidadeInput.addEventListener('input', atualizarResumo);
        valorUnitarioInput.addEventListener('input', atualizarResumo);
        valorFreteInput.addEventListener('input', atualizarResumo);
        
        function atualizarResumo() {
            const quantidade = parseFloat(quantidadeInput.value) || 1;
            const valorUnitario = parseFloat(valorUnitarioInput.value) || <?php echo $preco_exibir; ?>;
            const valorFrete = parseFloat(valorFreteInput.value) || 0;
            
            const subtotal = quantidade * valorUnitario;
            const total = subtotal + valorFrete;
            
            // Atualizar elementos do resumo
            resumoQuantidade.textContent = quantidade.toLocaleString('pt-BR');
            resumoValorUnitario.textContent = 'R$ ' + valorUnitario.toFixed(2).replace('.', ',');
            resumoSubtotal.textContent = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
            resumoFrete.textContent = 'R$ ' + valorFrete.toFixed(2).replace('.', ',');
            resumoTotal.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
            
            // Atualizar forma de pagamento no resumo
            const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked');
            if (formaPagamentoSelecionada) {
                if (formaPagamentoSelecionada.value === 'pagamento_ato') {
                    resumoPagamento.textContent = 'Pagamento no Ato';
                } else {
                    resumoPagamento.textContent = 'Pagamento na Entrega';
                }
            }
            
            // Atualizar avisos
            atualizarAvisosPagamento();
        }
        
        // Finalizar negociação
        btnFinalizarNegociacao.addEventListener('click', () => {
            if (!validarEtapa2()) {
                return;
            }
            
            // Coletar dados da negociação
            const dadosNegociacao = {
                produto_id: <?php echo $produto_id; ?>,
                conversa_id: conversaId,
                quantidade: quantidadeInput.value,
                valor_unitario: valorUnitarioInput.value,
                forma_pagamento: document.querySelector('input[name="forma_pagamento"]:checked').value,
                opcao_frete: document.querySelector('input[name="opcao_frete"]:checked').value,
                valor_frete: valorFreteInput.value || '0',
                total: calcularTotal()
            };
            
            // Enviar para o servidor
            enviarNegociacao(dadosNegociacao);
        });
        
        function validarEtapa2() {
            let opcaoFreteSelecionada = false;
            opcaoFreteInputs.forEach(input => {
                if (input.checked) opcaoFreteSelecionada = true;
            });
            
            if (!opcaoFreteSelecionada) {
                alert('Por favor, selecione uma opção de frete.');
                return false;
            }
            
            const opcaoSelecionada = document.querySelector('input[name="opcao_frete"]:checked').value;
            const formaPagamentoSelecionada = document.querySelector('input[name="forma_pagamento"]:checked').value;
            
            // Validação específica para buscar transportador
            if (opcaoSelecionada === 'buscar_transportador' && formaPagamentoSelecionada === 'pagamento_entrega') {
                alert('Para contratar transportador pela plataforma, o pagamento deve ser feito no ato. Por favor, altere a forma de pagamento.');
                return false;
            }
            
            return true;
        }
        
        function calcularTotal() {
            const quantidade = parseFloat(quantidadeInput.value) || 1;
            const valorUnitario = parseFloat(valorUnitarioInput.value) || <?php echo $preco_exibir; ?>;
            const valorFrete = parseFloat(valorFreteInput.value) || 0;
            
            return (quantidade * valorUnitario) + valorFrete;
        }
        
        // No arquivo chat.php, dentro do script, atualize a função enviarNegociacao:
        function enviarNegociacao(dados) {
            // Mostrar loading
            btnFinalizarNegociacao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            btnFinalizarNegociacao.disabled = true;
            
            // Adicionar dados adicionais necessários
            dados.total = calcularTotal();
            dados.valor_frete = valorFreteInput.value || '0';
            
            // Enviar para o servidor
            fetch('salvar_negociacao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dados)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Enviar mensagem automática no chat com os detalhes da negociação
                    const mensagemNegociacao = `*NOVA PROPOSTA DE COMPRA*\n\n` +
                        `**Produto:** ${document.querySelector('.produto-info-modal h4').textContent}\n` +
                        `**Quantidade:** ${dados.quantidade} unidades\n` +
                        `**Valor unitário:** R$ ${parseFloat(dados.valor_unitario).toFixed(2).replace('.', ',')}\n` +
                        `**Forma de pagamento:** ${dados.forma_pagamento === 'pagamento_ato' ? 'Pagamento no Ato' : 'Pagamento na Entrega'}\n` +
                        `**Opção de frete:** ${obterDescricaoFrete(dados.opcao_frete)}\n` +
                        `**Valor do frete:** R$ ${parseFloat(dados.valor_frete).toFixed(2).replace('.', ',')}\n` +
                        `**Valor total:** R$ ${parseFloat(dados.total).toFixed(2).replace('.', ',')}\n\n` +
                        `**ID da proposta:** ${data.proposta_id}`;
                    
                    // Enviar como mensagem no chat
                    return fetch('send_message.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `conversa_id=${conversaId}&mensagem=${encodeURIComponent(mensagemNegociacao)}`
                    }).then(() => {
                        alert('✅ Proposta enviada com sucesso!');
                        modalNegociacao.classList.remove('active');
                        resetarFormulario();
                        
                        // Recarregar mensagens para mostrar a nova proposta
                        setTimeout(() => carregarMensagens(), 1000);
                    });
                    
                } else {
                    alert('❌ Erro ao enviar proposta: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(err => {
                console.error('Erro:', err);
                alert('❌ Erro de conexão ao enviar proposta.');
            })
            .finally(() => {
                btnFinalizarNegociacao.innerHTML = '<i class="fas fa-check"></i> Finalizar Negociação';
                btnFinalizarNegociacao.disabled = false;
            });
        }

        
        function obterDescricaoFrete(opcao) {
            switch(opcao) {
                case 'frete_vendedor': return 'Frete por conta do vendedor';
                case 'retirada_comprador': return 'Retirada pelo comprador';
                case 'buscar_transportador': return 'Buscar transportador na plataforma';
                default: return 'Não especificado';
            }
        }
        
        function resetarFormulario() {
            // Resetar para etapa 1
            etapaAtual = 1;
            irParaEtapa(1);
            
            // Resetar valores
            quantidadeInput.value = 1;
            valorUnitarioInput.value = <?php echo $preco_exibir; ?>;
            
            // Resetar radio buttons
            document.querySelector('input[name="forma_pagamento"][value="pagamento_ato"]').checked = true;
            opcaoFreteInputs[0].checked = false;
            opcaoFreteInputs[1].checked = false;
            opcaoFreteInputs[2].checked = false;
            valorFreteInput.value = '0.00';
            
            // Esconder conteúdos dinâmicos
            conteudoFreteVendedor.style.display = 'none';
            conteudoRetirada.style.display = 'none';
            conteudoBuscarTransportador.style.display = 'none';
            avisoPagamentoEntrega.style.display = 'none';
            avisoPagamentoTransportador.style.display = 'none';
            
            // Atualizar resumo
            atualizarResumo();
        }
        
        // Inicializar
        atualizarConteudoFrete();
        atualizarAvisosPagamento();
        <?php endif; // Fim da verificação se é comprador ?>

        // ==================== ATUALIZAÇÃO AUTOMÁTICA DA PROPOSTA ====================
<?php
// Buscar a última data de atualização da proposta
$ultima_data_atualizacao = null;
if (isset($ultima_proposta['data_atualizacao'])) {
    $ultima_data_atualizacao = strtotime($ultima_proposta['data_atualizacao']);
}
?>

let propostaAtualId = <?php echo $ultima_proposta['ID'] ?? 0; ?>;
let propostaAtualDataAtualizacao = '<?php echo $ultima_proposta['data_atualizacao'] ?? ''; ?>';
let propostaAtualStatus = '<?php echo $ultima_proposta['status'] ?? ''; ?>';
let verificarPropostaInterval;

// Elementos DOM que serão atualizados
let propostaStatusElement;
let propostaQuantidadeElement;
let propostaValorUnitarioElement;
let propostaFreteElement;
let propostaPagamentoElement;
let propostaTotalElement;
let propostaDataElement;
let propostaAcoesElement;
let propostaFooterTextElement;

// Mapeamentos
const statusTextMap = {
    'assinando': '📝 Assinando',
    'aceita': '✅ Aceita',
    'negociacao': '🔄 Em Negociação',
    'recusada': '❌ Recusada',
    'cancelada': '⏹️ Cancelada'
};

const pagamentoTextMap = {
    'à vista': 'À Vista',
    'entrega': 'Na Entrega'
};

const freteTextMap = {
    'vendedor': 'Vendedor',
    'comprador': 'Comprador',
    'entregador': 'Transportador'
};

// Inicializar elementos DOM após o carregamento
function inicializarElementosProposta() {
    propostaStatusElement = document.getElementById('proposta-status');
    propostaQuantidadeElement = document.getElementById('proposta-quantidade');
    propostaValorUnitarioElement = document.getElementById('proposta-valor-unitario');
    propostaFreteElement = document.getElementById('proposta-frete');
    propostaPagamentoElement = document.getElementById('proposta-pagamento');
    propostaTotalElement = document.getElementById('proposta-total');
    propostaDataElement = document.getElementById('proposta-data');
    propostaAcoesElement = document.getElementById('proposta-acoes');
    propostaFooterTextElement = document.getElementById('proposta-footer-text');
    
    // Capturar dados do elemento hidden
    const indicador = document.getElementById('proposta-indicador');
    if (indicador) {
        propostaAtualId = indicador.dataset.propostaId || 0;
        propostaAtualStatus = indicador.dataset.status || '';
        // Note: não inicializamos propostaAtualDataAtualizacao aqui
        // pois ela virá do servidor na primeira verificação
    }
}

function verificarNovaProposta() {
    if (!conversaId || !propostaAtualId) return;
    
    fetch(`verificar_proposta_v2.php?conversa_id=${conversaId}&produto_id=${<?php echo $produto_id; ?>}&ultima_data=${encodeURIComponent(propostaAtualDataAtualizacao)}`)
        .then(res => res.json())
        .then(data => {
            if (data.atualizacao && data.proposta) {
                // Se é a mesma proposta (mesmo ID)
                if (data.proposta.ID === propostaAtualId) {
                    // Atualizar normalmente
                    atualizarProposta(data.proposta);
                    
                    // Apenas recarregar se for uma NOVA proposta (diferente ID)
                } else {
                    // É uma nova proposta, recarregar página
                    location.reload();
                }
            }
        })
        .catch(err => console.error('Erro ao verificar proposta:', err));
}

function atualizarProposta(proposta) {
    if (!proposta) return;
    
    // Inicializar elementos se necessário
    if (!propostaStatusElement) {
        inicializarElementosProposta();
    }
    
    // Verificar se o status mudou
    const statusMudou = proposta.status !== propostaAtualStatus;
    
    // Formatando valores
    const valorUnitario = parseFloat(proposta.preco_proposto || 0).toFixed(2).replace('.', ',');
    const valorFrete = parseFloat(proposta.valor_frete || 0).toFixed(2).replace('.', ',');
    const valorTotal = parseFloat(proposta.valor_total || 0).toFixed(2).replace('.', ',');
    
    // 1. Atualizar status (sempre)
    if (propostaStatusElement) {
        const statusTextMap = {
            'aceita': '✅ Aceita',
            'negociacao': '🔄 Em Negociação',
            'recusada': '❌ Recusada',
            'cancelada': '⏹️ Cancelada'
        };
        
        const statusText = statusTextMap[proposta.status] || proposta.status;
        propostaStatusElement.textContent = statusText;
        propostaStatusElement.className = 'proposta-status ' + proposta.status;
    }
    
    // 2. Atualizar apenas valores numéricos e texto
    updateElementText(propostaQuantidadeElement, 'strong', `${proposta.quantidade_proposta} unidades`);
    updateElementText(propostaValorUnitarioElement, 'strong', `R$ ${valorUnitario}`);
    
    // Atualizar frete
    if (propostaFreteElement) {
        const strongElement = propostaFreteElement.querySelector('strong');
        if (strongElement) {
            const freteText = freteTextMap[proposta.opcao_frete] || proposta.opcao_frete;
            strongElement.textContent = `${freteText} (R$ ${valorFrete})`;
        }
    }
    
    // Atualizar pagamento
    if (propostaPagamentoElement) {
        const strongElement = propostaPagamentoElement.querySelector('strong');
        if (strongElement) {
            const pagamentoText = pagamentoTextMap[proposta.forma_pagamento] || proposta.forma_pagamento;
            strongElement.textContent = pagamentoText;
        }
    }
    
    // Atualizar total
    if (propostaTotalElement) {
        const strongElement = propostaTotalElement.querySelector('strong');
        if (strongElement) {
            strongElement.textContent = `R$ ${valorTotal}`;
        }
    }
    
    // 3. Atualizar data
    if (propostaDataElement && proposta.data_inicio) {
        const smallElement = propostaDataElement.querySelector('small');
        if (smallElement) {
            const dataObj = new Date(proposta.data_inicio);
            const dataFormatada = `${dataObj.getDate().toString().padStart(2, '0')}/` +
                                `${(dataObj.getMonth() + 1).toString().padStart(2, '0')}/` +
                                `${dataObj.getFullYear()} ` +
                                `${dataObj.getHours().toString().padStart(2, '0')}:` +
                                `${dataObj.getMinutes().toString().padStart(2, '0')}`;
            smallElement.textContent = dataFormatada;
        }
    }
    
    // 4. Atualizar footer
    if (propostaFooterTextElement) {
        if (proposta.status === 'negociacao') {
            propostaFooterTextElement.textContent = 'Esta proposta foi enviada.';
        } else if (proposta.status === 'cancelada') {
            propostaFooterTextElement.textContent = 'Esta proposta foi cancelada pelo comprador.';
        } else {
            propostaFooterTextElement.textContent = `Esta proposta foi ${proposta.status}.`;
        }
    }
    
    // 5. Apenas atualizar botões se o status mudou PARA FORA DE 'negociacao'
    if (statusMudou && (proposta.status === 'aceita' || proposta.status === 'recusada' || proposta.status === 'cancelada')) {
        // Quando proposta é finalizada, substituir botões por mensagem
        if (propostaAcoesElement) {
            propostaAcoesElement.innerHTML = '';
            const mensagemFinal = document.createElement('div');
            mensagemFinal.className = 'proposta-finalizada ' + proposta.status;
            
            if (proposta.status === 'aceita') {
                mensagemFinal.innerHTML = '✅ Proposta aceita';
            } else if (proposta.status === 'recusada') {
                mensagemFinal.innerHTML = '❌ Proposta recusada';
            } else if (proposta.status === 'cancelada') {
                mensagemFinal.innerHTML = '<i class="fas fa-ban"></i> Proposta cancelada pelo comprador';
            }
            
            propostaAcoesElement.appendChild(mensagemFinal);
        }
    }
    
    // 6. Adicionar/remover classe cancelada do card
    const propostaCard = document.getElementById('proposta-card');
    if (propostaCard) {
        if (proposta.status === 'cancelada') {
            propostaCard.classList.add('cancelada');
        } else {
            propostaCard.classList.remove('cancelada');
        }
    }
    
    // 7. Atualizar variáveis globais
    propostaAtualStatus = proposta.status;
    propostaAtualId = proposta.ID;
    propostaAtualDataAtualizacao = proposta.data_atualizacao;
    
    // Função auxiliar
    function updateElementText(element, childSelector, text) {
        if (element) {
            const child = element.querySelector(childSelector);
            if (child) child.textContent = text;
        }
    }
}

function responderProposta(acao, propostaId) {
    if (acao === 'aceitar') {
        // Usar a nova função para assinatura
        aceitarPropostaParaAssinatura(propostaId);
        return;
    }
    
    if (!confirm(`Tem certeza que deseja ${acao === 'recusar' ? 'recusar' : 'aceitar'} esta proposta?`)) {
        return;
    }

    // Encontrar o botão clicado
    const botao = event.target.closest('button');
    const textoOriginal = botao.innerHTML;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    botao.disabled = true;

    // Enviar requisição
    fetch('responder_proposta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: acao,
            proposta_id: propostaId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacao(data.message, 'success');
            
            // Buscar os dados atualizados da proposta
            return buscarPropostaAtualizada(propostaId);
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    })
    .then(propostaAtualizada => {
        // Atualizar a UI com os novos dados
        atualizarProposta(propostaAtualizada);
    })
    .catch(err => {
        console.error('Erro:', err);
        alert('Erro: ' + err.message);
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
    });
}

// Nova função para buscar proposta atualizada
function buscarPropostaAtualizada(propostaId) {
    return fetch(`buscar_proposta.php?id=${propostaId}`)
        .then(res => res.json());
}

// Função para cancelar proposta (apenas comprador)
function cancelarProposta(propostaId) {
    if (!confirm('Tem certeza que deseja cancelar esta proposta?')) {
        return;
    }

    const botao = event.target.closest('button');
    const textoOriginal = botao.innerHTML;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelando...';
    botao.disabled = true;

    // Enviar requisição para cancelar
    fetch('responder_proposta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'cancelar',
            proposta_id: propostaId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensagem de sucesso
            mostrarNotificacao('Proposta cancelada com sucesso!', 'success');
            
            // Atualizar o status da proposta para "cancelada"
            // Em vez de remover o card, apenas atualizar o status
            if (propostaAtualId === propostaId) {
                // Atualizar o status localmente
                propostaAtualStatus = 'cancelada';
                // Chamar função para atualizar a UI
                atualizarStatusProposta('cancelada');
            }
        } else {
            alert('Erro: ' + data.error);
            botao.innerHTML = textoOriginal;
            botao.disabled = false;
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        alert('Erro de conexão. Tente novamente.');
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
    });
}

function atualizarStatusProposta(novoStatus) {
    if (!propostaStatusElement) return;
    
    const statusTextMap = {
        'aceita': '✅ Aceita',
        'negociacao': '🔄 Em Negociação',
        'recusada': '❌ Recusada',
        'cancelada': '⏹️ Cancelada'
    };
    
    const statusText = statusTextMap[novoStatus] || novoStatus;
    propostaStatusElement.textContent = statusText;
    
    // Atualizar classes CSS
    propostaStatusElement.classList.remove('aceita', 'negociacao', 'recusada', 'cancelada');
    propostaStatusElement.classList.add(novoStatus);
    
    // Atualizar botões de ação
    atualizarBotoesAcaoUI(novoStatus);
    
    // Atualizar texto do footer
    if (propostaFooterTextElement) {
        if (novoStatus === 'negociacao') {
            propostaFooterTextElement.textContent = 'Esta proposta foi enviada.';
        } else if (novoStatus === 'cancelada') {
            propostaFooterTextElement.textContent = 'Esta proposta foi cancelada pelo comprador.';
        } else {
            propostaFooterTextElement.textContent = `Esta proposta foi ${novoStatus}.`;
        }
    }
    
    // Adicionar classe ao card se for cancelada
    const propostaCard = document.getElementById('proposta-card');
    if (propostaCard) {
        if (novoStatus === 'cancelada') {
            propostaCard.classList.add('cancelada');
        } else {
            propostaCard.classList.remove('cancelada');
        }
    }
}
// Atualizar botões de ação na UI
function atualizarBotoesAcaoUI(status) {
    if (!propostaAcoesElement) return;
    
    // Limpar botões existentes
    while (propostaAcoesElement.firstChild) {
        propostaAcoesElement.removeChild(propostaAcoesElement.firstChild);
    }
    
    // Não mostrar botões se a proposta já foi finalizada
    if (status !== 'negociacao') {
        const mensagemFinal = document.createElement('div');
        mensagemFinal.className = `proposta-finalizada ${status}`;
        
        if (status === 'aceita') {
            mensagemFinal.innerHTML = '✅ Proposta aceita';
        } else if (status === 'recusada') {
            mensagemFinal.innerHTML = '❌ Proposta recusada';
        } else if (status === 'cancelada') {
            mensagemFinal.innerHTML = '<i class="fas fa-ban"></i> Proposta cancelada pelo comprador';
        }
        
        propostaAcoesElement.appendChild(mensagemFinal);
    }
}

function mostrarNotificacao(mensagem, tipo) {
    // Criar elemento de notificação
    const notificacao = document.createElement('div');
    notificacao.className = `notificacao-chat notificacao-${tipo}`;
    notificacao.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${mensagem}</span>
    `;
    
    // Estilo da notificação
    notificacao.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${tipo === 'success' ? '#d4edda' : '#f8d7da'};
        color: ${tipo === 'success' ? '#155724' : '#721c24'};
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 10000;
        border: 1px solid ${tipo === 'success' ? '#c3e6cb' : '#f5c6cb'};
        animation: slideIn 0.3s ease forwards;
        max-width: 350px;
    `;
    
    // Adicionar animação
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    
    // Adicionar ao corpo
    document.body.appendChild(notificacao);
    
    // Remover após 3 segundos
    setTimeout(() => {
        notificacao.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => {
            notificacao.remove();
            style.remove();
        }, 300);
    }, 3000);
}
function adicionarBotao(classe, texto, acao, propostaId, iconeClasse) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = classe;
    button.dataset.action = acao;
    button.dataset.id = propostaId;
    
    const icon = document.createElement('i');
    icon.className = iconeClasse;
    
    button.appendChild(icon);
    button.appendChild(document.createTextNode(` ${texto}`));
    
    // Adicionar evento de clique
    button.addEventListener('click', function() {
        const acao = this.dataset.action;
        const id = this.dataset.id;
        
        switch(acao) {
            case 'aceitar':
                responderProposta('aceita', id);
                break;
            case 'recusar':
                responderProposta('recusada', id);
                break;
            case 'cancelar':
                cancelarProposta(id);
                break;
        }
    });
    
    propostaAcoesElement.appendChild(button);
}

const buttonStyles = document.createElement('style');
buttonStyles.textContent = `
    .btn-accept-proposal, .btn-reject-proposal, .btn-cancel-proposal {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0 4px;
        margin-top: 10px;
    }
    
    .btn-accept-proposal {
        background-color: #42b72a;
        color: white;
    }
    
    .btn-accept-proposal:hover {
        background-color: #36a420;
        transform: translateY(-1px);
    }
    
    .btn-accept-proposal:disabled {
        background-color: #ccc;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-reject-proposal {
        background-color: #ff4444;
        color: white;
    }
    
    .btn-reject-proposal:hover {
        background-color: #ff2222;
        transform: translateY(-1px);
    }
    
    .btn-reject-proposal:disabled {
        background-color: #ccc;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-cancel-proposal {
        background-color: #ff9500;
        color: white;
    }
    
    .btn-cancel-proposal:hover {
        background-color: #e68900;
        transform: translateY(-1px);
    }
    
    .btn-cancel-proposal:disabled {
        background-color: #ccc;
        cursor: not-allowed;
        transform: none;
    }
    
    .proposta-acoes {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    
    /* Animações para o card */
    .proposta-card {
        transition: all 0.3s ease;
    }
    
    .proposta-card.aceita {
        border: 2px solid #42b72a;
        background-color: #f0fff0;
    }
    
    .proposta-card.recusada {
        border: 2px solid #ff4444;
        background-color: #fff0f0;
    }
`;
document.head.appendChild(buttonStyles);

function atualizarFooterProposta(proposta) {
    if (!propostaFooterTextElement) return;
    
    if (proposta.status === 'negociacao') {
        propostaFooterTextElement.textContent = 'Esta proposta foi enviada.';
    } else {
        propostaFooterTextElement.textContent = `Esta proposta foi ${proposta.status}.`;
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    inicializarElementosProposta();
    
    // Iniciar verificação periódica se houver conversa
    if (conversaId) {
        verificarPropostaInterval = setInterval(verificarNovaProposta, 3000);
    }
});

// Parar verificação quando a página for fechada
window.addEventListener('beforeunload', function() {
    if (verificarPropostaInterval) {
        clearInterval(verificarPropostaInterval);
    }
});

// Lógica para visualização ampliada da foto de perfil
const avatarUsuario = document.getElementById('avatar-usuario');
const modalFotoPerfil = document.getElementById('modal-foto-perfil');
const btnFecharFoto = document.getElementById('fechar-foto');
const btnFecharModalFoto = document.getElementById('fechar-modal-foto');

if (avatarUsuario) {
    avatarUsuario.addEventListener('click', () => {
        modalFotoPerfil.classList.add('active');
    });
}

// Fechar modal ao clicar nos botões de fechar
if (btnFecharFoto) {
    btnFecharFoto.addEventListener('click', () => {
        modalFotoPerfil.classList.remove('active');
    });
}

if (btnFecharModalFoto) {
    btnFecharModalFoto.addEventListener('click', () => {
        modalFotoPerfil.classList.remove('active');
    });
}

// Fechar modal ao clicar fora da imagem
modalFotoPerfil.addEventListener('click', (e) => {
    if (e.target === modalFotoPerfil) {
        modalFotoPerfil.classList.remove('active');
    }
});

// Fechar modal com tecla ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalFotoPerfil.classList.contains('active')) {
        modalFotoPerfil.classList.remove('active');
    }
});

function substituirPorIcone(imgElement) {
    imgElement.style.display = 'none';
    imgElement.parentElement.innerHTML = '<i class="fas fa-user"></i>';
}

// ==================== LÓGICA DE ASSINATURA DIGITAL ====================

// Variáveis para o canvas de assinatura
let signatureCanvas;
let signatureCtx;
let isDrawing = false;
let lastX = 0;
let lastY = 0;
let propostaParaAssinar = null;

// Elementos do modal de assinatura
const modalAssinatura = document.getElementById('modal-assinatura');
const btnFecharAssinatura = document.getElementById('fechar-modal-assinatura');
const btnLimparAssinatura = document.getElementById('limpar-assinatura');
const btnConfirmarAssinatura = document.getElementById('confirmar-assinatura');
const canvas = document.getElementById('signature-canvas');
const assinaturaStatusDiv = document.getElementById('assinatura-status');

// Inicializar canvas quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    if (canvas) {
        signatureCanvas = canvas;
        signatureCtx = canvas.getContext('2d');
        inicializarCanvas(); // Chama a função que configura tudo
    }

    // Event listeners para o modal de assinatura
    if (btnFecharAssinatura) {
        btnFecharAssinatura.addEventListener('click', fecharModalAssinatura);
    }
    
    if (btnLimparAssinatura) {
        btnLimparAssinatura.addEventListener('click', limparAssinatura);
    }
    
    if (btnConfirmarAssinatura) {
        btnConfirmarAssinatura.addEventListener('click', confirmarAssinatura);
    }
    
    // Fechar modal ao clicar fora
    modalAssinatura.addEventListener('click', (e) => {
        if (e.target === modalAssinatura) {
            fecharModalAssinatura();
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalAssinatura.classList.contains('active')) {
            fecharModalAssinatura();
        }
    });
});

function inicializarCanvas() {
    if (!canvas || !signatureCtx) return;
    
    // Configurar canvas inicialmente
    ajustarCanvasParaDPI();
    
    // Limpar canvas
    signatureCtx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Eventos do mouse
    canvas.addEventListener('mousedown', (e) => {
        e.preventDefault();
        isDrawing = true;
        const pos = getMousePos(canvas, e);
        [lastX, lastY] = [pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1)];
    });
    
    canvas.addEventListener('mousemove', (e) => {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getMousePos(canvas, e);
        
        signatureCtx.beginPath();
        signatureCtx.moveTo(lastX, lastY);
        signatureCtx.lineTo(pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1));
        signatureCtx.stroke();
        
        [lastX, lastY] = [pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1)];
    });
    
    canvas.addEventListener('mouseup', () => {
        isDrawing = false;
    });
    
    canvas.addEventListener('mouseout', () => {
        isDrawing = false;
    });
    
    // Touch events para dispositivos móveis
    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        isDrawing = true;
        const touch = e.touches[0];
        const pos = getTouchPos(canvas, touch);
        [lastX, lastY] = [pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1)];
    });
    
    canvas.addEventListener('touchmove', (e) => {
        if (!isDrawing) return;
        e.preventDefault();
        const touch = e.touches[0];
        const pos = getTouchPos(canvas, touch);
        
        signatureCtx.beginPath();
        signatureCtx.moveTo(lastX, lastY);
        signatureCtx.lineTo(pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1));
        signatureCtx.stroke();
        
        [lastX, lastY] = [pos.x / (window.devicePixelRatio || 1), pos.y / (window.devicePixelRatio || 1)];
    });
    
    canvas.addEventListener('touchend', () => {
        isDrawing = false;
    });
}

// Função para obter posição do mouse
function getMousePos(canvas, evt) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: evt.clientX - rect.left,
        y: evt.clientY - rect.top
    };
}

// Função para obter posição do touch
function getTouchPos(canvas, touch) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: touch.clientX - rect.left,
        y: touch.clientY - rect.top
    };
}

function getCoordenadas(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY,
        preventDefault: () => e.preventDefault() // Adicionar método preventDefault
    };
}

// Função auxiliar para obter coordenadas do touch
function getCoordenadasTouch(touch) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    return {
        x: (touch.clientX - rect.left) * scaleX,
        y: (touch.clientY - rect.top) * scaleY,
        preventDefault: () => {} // Método vazio para touch
    };
}

function ajustarCanvasParaDPI() {
    if (!canvas || !signatureCtx) return;
    
    const dpi = window.devicePixelRatio || 1;
    const container = canvas.parentElement;
    const containerWidth = container.clientWidth;
    const containerHeight = container.clientHeight;
    
    // Definir dimensões físicas do canvas (considerando DPI)
    canvas.width = containerWidth * dpi;
    canvas.height = containerHeight * dpi;
    
    // Redefinir o contexto
    signatureCtx = canvas.getContext('2d');
    
    // Aplicar escala DPI
    signatureCtx.scale(dpi, dpi);
    
    // Definir dimensões CSS (em pixels de CSS)
    canvas.style.width = containerWidth + 'px';
    canvas.style.height = containerHeight + 'px';
    
    // Configurar estilo da linha
    signatureCtx.lineWidth = 2;
    signatureCtx.lineCap = 'round';
    signatureCtx.lineJoin = 'round';
    signatureCtx.strokeStyle = '#000000';
}

function iniciarDesenho(coords) {
    isDrawing = true;
    [lastX, lastY] = [coords.x, coords.y];
}

function desenhar(coords) {
    if (!isDrawing) return;
    
    // Chamar preventDefault se existir
    if (coords.preventDefault && typeof coords.preventDefault === 'function') {
        coords.preventDefault();
    }
    
    signatureCtx.beginPath();
    signatureCtx.moveTo(lastX, lastY);
    signatureCtx.lineTo(coords.x, coords.y);
    signatureCtx.stroke();
    
    [lastX, lastY] = [coords.x, coords.y];
}

function pararDesenho() {
    isDrawing = false;
}

function limparAssinatura() {
    if (!canvas || !signatureCtx) return;
    
    // Obter dimensões considerando DPI
    const dpi = window.devicePixelRatio || 1;
    const container = canvas.parentElement;
    const containerWidth = container.clientWidth;
    const containerHeight = container.clientHeight;
    
    // Limpar toda a área do canvas
    signatureCtx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Restaurar transformações e estilo
    signatureCtx.setTransform(dpi, 0, 0, dpi, 0, 0);
    signatureCtx.lineWidth = 2;
    signatureCtx.lineCap = 'round';
    signatureCtx.lineJoin = 'round';
    signatureCtx.strokeStyle = '#000000';
}

function abrirModalAssinatura(propostaId) {
    propostaParaAssinar = propostaId;
    
    // Atualizar ID da proposta no modal
    document.getElementById('assinatura-proposta-id').textContent = propostaId;
    
    // Abrir modal primeiro para que as dimensões estejam disponíveis
    modalAssinatura.classList.add('active');
    
    // Pequeno delay para garantir que o modal está renderizado
    setTimeout(() => {
        // Reinicializar canvas com as dimensões corretas
        if (canvas && signatureCtx) {
            ajustarCanvasParaDPI();
            limparAssinatura();
        }
        
        // Buscar informações das assinaturas
        buscarInformacoesAssinaturas(propostaId);
    }, 50);
}
function fecharModalAssinatura() {
    modalAssinatura.classList.remove('active');
    propostaParaAssinar = null;
}

function buscarInformacoesAssinaturas(propostaId) {
    fetch(`buscar_assinaturas.php?proposta_id=${propostaId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                atualizarStatusAssinaturas(data.assinaturas, data.outro_usuario_nome);
            }
        })
        .catch(err => console.error('Erro ao buscar assinaturas:', err));
}

function atualizarStatusAssinaturas(assinaturas, outroUsuarioNome) {
    // Corrigir: remover a verificação da variável não definida
    if (!assinaturaStatusDiv || !outroUsuarioNome) return;
    
    const usuarioAtualAssinou = assinaturas.some(a => a.usuario_id == usuarioId);
    const outroUsuarioAssinou = assinaturas.some(a => a.usuario_id != usuarioId);
    
    let html = `
        <div class="assinatura-item ${usuarioAtualAssinou ? 'assinada' : 'pendente'}">
            <i class="fas fa-${usuarioAtualAssinou ? 'check-circle' : 'clock'}"></i>
            <span>Você: ${usuarioAtualAssinou ? 'Assinou' : 'Pendente'}</span>
        </div>
        <div class="assinatura-item ${outroUsuarioAssinou ? 'assinada' : 'pendente'}">
            <i class="fas fa-${outroUsuarioAssinou ? 'check-circle' : 'clock'}"></i>
            <span>${outroUsuarioNome}: ${outroUsuarioAssinou ? 'Assinou' : 'Pendente'}</span>
        </div>
    `;
    
    assinaturaStatusDiv.innerHTML = html;
}

function confirmarAssinatura() {
    if (!propostaParaAssinar) {
        alert('Erro: Proposta não encontrada.');
        return;
    }
    
    // Verificação simples: verificar se houve algum desenho
    // Pegando uma amostra de pixels
    const ctx = canvas.getContext('2d');
    const imageData = ctx.getImageData(canvas.width/2 - 50, canvas.height/2 - 25, 100, 50).data;
    
    let hasSignature = false;
    for (let i = 0; i < imageData.length; i += 4) {
        // Se encontrar algum pixel não branco (R, G, ou B diferente de 255)
        // ou não totalmente transparente (A diferente de 0)
        if (imageData[i] < 250 || imageData[i + 1] < 250 || imageData[i + 2] < 250 || imageData[i + 3] > 10) {
            hasSignature = true;
            break;
        }
    }
    
    if (!hasSignature) {
        alert('Por favor, desenhe sua assinatura no quadro antes de confirmar.');
        return;
    }
    
    // Mostrar loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    btn.disabled = true;
    
    // Converter para base64
    const signatureData = canvas.toDataURL('image/png');
    const base64Image = signatureData.split(',')[1];
    
    // Enviar para o servidor
    fetch('salvar_assinatura.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            proposta_id: propostaParaAssinar,
            assinatura_imagem: base64Image
        })
    })
    .then(async response => {
        const text = await response.text();
        console.log('Resposta:', text);
        
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                // Sucesso
                if (data.ambas_assinadas) {
                    mostrarNotificacao('🎉 Acordo assinado por ambas as partes! Proposta aceita.', 'success');
                } else {
                    mostrarNotificacao('✅ Sua assinatura foi registrada! Aguarde a outra parte.', 'success');
                }
                
                fecharModalAssinatura();
                
                // Pequeno delay para o usuário ver a mensagem
                setTimeout(() => {
                    location.reload();
                }, 1500);
                
            } else {
                // Erro do servidor
                throw new Error(data.error || 'Erro desconhecido');
            }
        } catch (e) {
            if (text.includes('success') || text.includes('assinatura')) {
                // Se parece ser um JSON mas não foi parseado corretamente
                mostrarNotificacao('Assinatura salva!', 'success');
                fecharModalAssinatura();
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error('Resposta inválida do servidor: ' + text.substring(0, 50));
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        
        // Verificar se foi um erro de rede ou de servidor
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            alert('Erro de conexão. Verifique sua internet e tente novamente.');
        } else {
            alert('Erro: ' + error.message);
        }
        
        // Restaurar botão
        const btn = document.getElementById('confirmar-assinatura');
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
}

// Nova função para aceitar proposta e enviar para assinatura
function aceitarPropostaParaAssinatura(propostaId) {
    if (!confirm('Ao aceitar esta proposta, ela será enviada para assinatura digital de ambas as partes. Deseja continuar?')) {
        return;
    }

    // Encontrar o botão clicado
    const botao = event.target.closest('button');
    const textoOriginal = botao.innerHTML;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    botao.disabled = true;

    // Enviar requisição para mudar status para 'assinando'
    fetch('responder_proposta.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            acao: 'aceitar_para_assinatura',
            proposta_id: propostaId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacao('Proposta aceita! Aguarde as assinaturas para concluir.', 'success');
            
            // Recarregar a página para atualizar interface
            setTimeout(() => {
                location.reload();
            }, 1000);
            
        } else {
            alert('Erro: ' + data.error);
            botao.innerHTML = textoOriginal;
            botao.disabled = false;
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        alert('Erro de conexão. Tente novamente.');
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
    });
}
    </script>
    <?php endif; ?>
</body>
</html>