<?php
// src/chat/carregar_proposta.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || !isset($_GET['produto_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$produto_id = (int)$_GET['produto_id'];
$usuario_tipo = $_SESSION['usuario_tipo']; // 'comprador' ou 'vendedor'

$database = new Database();
$conn = $database->getConnection();

$sql_produto = "SELECT v.usuario_id as vendedor_usuario_id 
                FROM produtos p
                JOIN vendedores v ON p.vendedor_id = v.id
                WHERE p.id = :produto_id";
                
$stmt_produto = $conn->prepare($sql_produto);
$stmt_produto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
$stmt_produto->execute();
$produto_info = $stmt_produto->fetch(PDO::FETCH_ASSOC);

if (!$produto_info) {
    echo json_encode(['success' => false, 'error' => 'Produto não encontrado']);
    exit();
}

$vendedor_id = $produto_info['vendedor_usuario_id'];

$comprador_id = $usuario_id;

if ($usuario_tipo === 'vendedor') {
    
    if (isset($_GET['conversa_id']) && $_GET['conversa_id'] > 0) {
        $conversa_id = (int)$_GET['conversa_id'];
        
        $sql_conversa = "SELECT comprador_id FROM chat_conversas WHERE id = :conversa_id";
        $stmt_conv = $conn->prepare($sql_conversa);
        $stmt_conv->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt_conv->execute();
        $conversa_info = $stmt_conv->fetch(PDO::FETCH_ASSOC);
        
        if ($conversa_info) {
            $comprador_id = $conversa_info['comprador_id'];
            $vendedor_id = $usuario_id; 
        }
    }
}

$sql = "SELECT p.*,
        prod.nome as produto_nome,
        prod.modo_precificacao,
        prod.embalagem_unidades,
        prod.embalagem_peso_kg,
        u_comp.nome as comprador_nome,
        u_vend.nome as vendedor_nome
        FROM propostas p
        JOIN produtos prod ON p.produto_id = prod.id
        JOIN usuarios u_comp ON p.comprador_id = u_comp.id
        JOIN usuarios u_vend ON p.vendedor_id = u_vend.id
        WHERE p.produto_id = :produto_id 
        AND (
            (p.comprador_id = :comprador_id AND p.vendedor_id = :vendedor_id)
            OR
            (p.comprador_id = :vendedor_id AND p.vendedor_id = :comprador_id)
        )
        ORDER BY p.data_atualizacao DESC 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
$stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
$stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
$stmt->execute();

$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

// Status com ícones
$status_icones = [
    'negociacao' => '🔄',
    'assinando' => '📝',
    'aceita' => '✅',
    'recusada' => '❌',
    'cancelada' => '⏹️'
];

$status_texto = [
    'negociacao' => 'Negociando',
    'assinando' => 'Assinando',
    'aceita' => 'Aceita',
    'recusada' => 'Recusada',
    'cancelada' => 'Cancelada'
];

if (!$proposta) {
    // SEM NENHUMA PROPOSTA
    $icone = '📄';
    $texto_status = 'Sem proposta';
    $status_class = 'vazio';
    
    echo json_encode([
        'success' => true,
        'tem_proposta' => false,
        'status_finalizado' => false,
        'proposta_finalizada' => false,
        'html_status' => '<span class="status-text ' . $status_class . '">' . 
                        $icone . ' ' . $texto_status . '</span>',
        'html_acoes' => gerarBotaoFazerProposta($usuario_tipo, $produto_id, null),
        'html_dados' => '' // Vazio sem dados
    ]);
    exit();
}

// TEM PROPOSTA - Processar dados
$icone = $status_icones[$proposta['status']] ?? '📄';
$texto_status = $status_texto[$proposta['status']] ?? $proposta['status'];
$status_class = $proposta['status'];

// Determinar se é proposta finalizada
$proposta_finalizada = in_array($proposta['status'], ['aceita', 'recusada', 'cancelada']);
$proposta_ativa = in_array($proposta['status'], ['negociacao', 'assinando']);

// Formatar dados da proposta
$valor_unitario = number_format($proposta['preco_proposto'], 2, ',', '.');
$valor_frete = number_format($proposta['valor_frete'], 2, ',', '.');
$valor_total = number_format($proposta['valor_total'], 2, ',', '.');

// Determinar unidade de medida
$modo = $proposta['modo_precificacao'] ?? 'por_quilo';
$unidade_medida = '';
switch ($modo) {
    case 'por_unidade': $unidade_medida = 'unidade'; break;
    case 'por_quilo': $unidade_medida = 'kg'; break;
    case 'caixa_unidades': 
        $unidade_medida = 'caixa' . (!empty($proposta['embalagem_unidades']) ? 
                        " ({$proposta['embalagem_unidades']} unid)" : '');
        break;
    case 'caixa_quilos': 
        $unidade_medida = 'caixa' . (!empty($proposta['embalagem_peso_kg']) ? 
                        " ({$proposta['embalagem_peso_kg']} kg)" : '');
        break;
    default: $unidade_medida = 'unidade';
}

// Mapeamentos
$forma_pagamento_texto = [
    'à vista' => 'À Vista',
    'entrega' => 'Na Entrega'
];

$opcao_frete_texto = [
    'vendedor' => 'Vendedor',
    'comprador' => 'Comprador',
    'entregador' => 'Transportador'
];

// Data formatada
$data_formatada = date('d/m/Y H:i', strtotime($proposta['data_inicio']));

// Verificar assinaturas se status = 'assinando'
$assinaturas_html = '';
if ($proposta['status'] === 'assinando') {
    $sql_assinaturas = "SELECT u.nome, u.tipo 
                       FROM propostas_assinaturas pa
                       JOIN usuarios u ON pa.usuario_id = u.id
                       WHERE pa.proposta_id = :proposta_id";
    
    $stmt_assin = $conn->prepare($sql_assinaturas);
    $stmt_assin->bindParam(':proposta_id', $proposta['ID'], PDO::PARAM_INT);
    $stmt_assin->execute();
    $assinaturas = $stmt_assin->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($assinaturas as $assinatura) {
        $assinaturas_html .= "<small><i class='fas fa-check-circle text-success'></i> " .
                           htmlspecialchars($assinatura['nome']) . 
                           " ({$assinatura['tipo']}) já assinou</small><br>";
    }
}

// Gerar HTML das ações
$html_acoes = '';

// Botão "Fazer Proposta" apenas para comprador e apenas em estados finais ou sem proposta
if ($proposta_finalizada && $usuario_tipo === 'comprador') {
    $html_acoes .= gerarBotaoFazerProposta($usuario_tipo, $produto_id, $proposta['ID']);
}

// Botões específicos baseados no status
if ($proposta['status'] === 'assinando') {
    // Verificar se usuário atual já assinou
    $sql_verificar_assinatura = "SELECT id FROM propostas_assinaturas 
                                WHERE proposta_id = :proposta_id 
                                AND usuario_id = :usuario_id";
    
    $stmt_ver = $conn->prepare($sql_verificar_assinatura);
    $stmt_ver->bindParam(':proposta_id', $proposta['ID'], PDO::PARAM_INT);
    $stmt_ver->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_ver->execute();
    $usuario_assinou = $stmt_ver->fetch() !== false;
    
    if (!$usuario_assinou) {
        $html_acoes .= '<button type="button" class="btn-assinar-acordo" 
                       data-proposta-id="' . $proposta['ID'] . '">
                       <i class="fas fa-signature"></i> Assinar Acordo</button>';
    } else {
        $html_acoes .= '<div class="proposta-finalizada assinado">
                       <i class="fas fa-check-circle" style="color: #28a745;"></i> 
                       Você já assinou este acordo</div>';
    }
    
} elseif ($proposta['status'] === 'negociacao') {
    // Adicionar uma div para os botões específicos
    $html_acoes .= '<div class="botoes-especificos">';
    
    if ($proposta['comprador_id'] == $usuario_id) {
        // Comprador: pode editar ou cancelar
        $html_acoes .= '<button type="button" class="btn-editar-proposta" 
                       data-proposta-id="' . $proposta['ID'] . '">
                       <i class="fas fa-edit"></i> Editar Proposta</button>';
        
        $html_acoes .= '<button type="button" class="btn-cancelar-proposta" 
                       data-proposta-id="' . $proposta['ID'] . '">
                       <i class="fas fa-times"></i> Cancelar</button>';
        
    } elseif ($proposta['vendedor_id'] == $usuario_id) {
        // Vendedor: pode aceitar ou recusar
        $html_acoes .= '<button type="button" class="btn-aceitar-proposta" 
                       data-proposta-id="' . $proposta['ID'] . '">
                       <i class="fas fa-check"></i> Aceitar</button>';
        
        $html_acoes .= '<button type="button" class="btn-recusar-proposta" 
                       data-proposta-id="' . $proposta['ID'] . '">
                       <i class="fas fa-times"></i> Recusar</button>';
    }
    
    $html_acoes .= '</div>';
} elseif ($proposta_finalizada) {
    // Estado finalizado - mostrar mensagem apropriada
    $mensagem_final = '';
    switch ($proposta['status']) {
        case 'aceita':
            $mensagem_final = '<div class="proposta-finalizada aceita">
                              <i class="fas fa-check-circle"></i> Proposta aceita</div>';
            break;
        case 'recusada':
            $mensagem_final = '<div class="proposta-finalizada recusada">
                              <i class="fas fa-times-circle"></i> Proposta recusada</div>';
            break;
        case 'cancelada':
            $mensagem_final = '<div class="proposta-finalizada cancelada">
                              <i class="fas fa-ban"></i> Proposta cancelada</div>';
            break;
    }
    
    $html_acoes .= $mensagem_final;
}

// Gerar HTML dos dados da proposta
$html_dados = gerarHTMLDadosProposta([
    'quantidade' => $proposta['quantidade_proposta'] . ' ' . $unidade_medida,
    'valor_unitario' => 'R$ ' . $valor_unitario,
    'frete' => ($opcao_frete_texto[$proposta['opcao_frete']] ?? $proposta['opcao_frete']) . 
              ' (R$ ' . $valor_frete . ')',
    'pagamento' => $forma_pagamento_texto[$proposta['forma_pagamento']] ?? $proposta['forma_pagamento'],
    'total' => 'R$ ' . $valor_total,
    'data' => $data_formatada,
    'assinaturas_html' => $assinaturas_html
]);

echo json_encode([
    'success' => true,
    'tem_proposta' => true,
    'proposta_finalizada' => $proposta_finalizada,
    'proposta_ativa' => $proposta_ativa,
    'dados' => [
        'id' => $proposta['ID'],
        'status' => $proposta['status'],
        'status_texto' => $texto_status,
        'status_icone' => $icone,
        'comprador_id' => $proposta['comprador_id'],
        'vendedor_id' => $proposta['vendedor_id'],
        'comprador_nome' => $proposta['comprador_nome'],
        'vendedor_nome' => $proposta['vendedor_nome'],
        'quantidade' => $proposta['quantidade_proposta'] . ' ' . $unidade_medida,
        'valor_unitario' => 'R$ ' . $valor_unitario,
        'frete' => ($opcao_frete_texto[$proposta['opcao_frete']] ?? $proposta['opcao_frete']) . 
                  ' (R$ ' . $valor_frete . ')',
        'pagamento' => $forma_pagamento_texto[$proposta['forma_pagamento']] ?? $proposta['forma_pagamento'],
        'total' => 'R$ ' . $valor_total,
        'data' => $data_formatada,
        'assinaturas_html' => $assinaturas_html,
        'data_atualizacao' => $proposta['data_atualizacao']
    ],
    'html_status' => '<span class="status-text ' . $status_class . '">' . 
                    $icone . ' ' . $texto_status . '</span>',
    'html_acoes' => $html_acoes,
    'html_dados' => $html_dados,
    'usuario_tipo' => $usuario_tipo,
    'usuario_id' => $usuario_id
]);

// Função para gerar botão "Fazer Proposta"
function gerarBotaoFazerProposta($usuario_tipo, $produto_id, $proposta_id = null) {
    // Apenas comprador pode fazer proposta
    if ($usuario_tipo !== 'comprador') {
        return '';
    }
    
    $proposta_param = $proposta_id ? "&proposta_existente=" . $proposta_id : "";
    
    return '<button type="button" class="btn-nova-proposta" 
            data-produto-id="' . $produto_id . '" ' . $proposta_param . '>
            <i class="fas fa-handshake"></i> Fazer Proposta
            </button>';
}

// Função para gerar HTML dos dados da proposta
function gerarHTMLDadosProposta($dados) {
    $html = '
        <div class="proposta-item" id="proposta-quantidade">
            <span><i class="fas fa-box"></i> Quantidade:</span>
            <strong id="quantidade-valor">' . ($dados['quantidade'] ?? '-') . '</strong>
        </div>
        
        <div class="proposta-item" id="proposta-valor-unitario">
            <span><i class="fas fa-tag"></i> Valor Unitário:</span>
            <strong id="valor-unitario-valor">' . ($dados['valor_unitario'] ?? '-') . '</strong>
        </div>
        
        <div class="proposta-item" id="proposta-frete">
            <span><i class="fas fa-truck"></i> Frete:</span>
            <strong id="frete-valor">' . ($dados['frete'] ?? '-') . '</strong>
        </div>
        
        <div class="proposta-item" id="proposta-pagamento">
            <span><i class="fas fa-credit-card"></i> Pagamento:</span>
            <strong id="pagamento-valor">' . ($dados['pagamento'] ?? '-') . '</strong>
        </div>
        
        <div class="proposta-item total" id="proposta-total">
            <span><i class="fas fa-calculator"></i> Valor Total:</span>
            <strong id="total-valor">' . ($dados['total'] ?? '-') . '</strong>
        </div>
        
        <div class="proposta-item" id="proposta-data">
            <span><i class="fas fa-calendar"></i> Data:</span>
            <small id="data-valor">' . ($dados['data'] ?? '-') . '</small>
        </div>';
    
    return $html;
}
?>