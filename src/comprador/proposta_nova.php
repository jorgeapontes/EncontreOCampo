<?php
// src/comprador/proposta_nova.php (Versão Corrigida - Carrossel Funcional)

session_start();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../permissions.php'; // ADICIONE ESTA LINHA

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
}

// 2. OBTENÇÃO DO ID DO ANÚNCIO
if (!isset($_GET['anuncio_id']) || !is_numeric($_GET['anuncio_id'])) {
    header("Location: dashboard.php?erro=" . urlencode("Anúncio não especificado ou inválido."));
    exit();
}

$anuncio_id = (int)$_GET['anuncio_id'];
$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];

$database = new Database();
$conn = $database->getConnection();
$anuncio = null;

// Função auxiliar para calcular desconto (Reutilizável)
function calcularDesconto($preco, $preco_desconto, $data_expiracao) {
    if ($preco_desconto && $preco_desconto > 0 && $preco_desconto < $preco) {
        $agora = date('Y-m-d H:i:s');
        if (empty($data_expiracao) || $data_expiracao > $agora) {
            return [
                'ativo' => true,
                'preco_original' => $preco,
                'preco_final' => $preco_desconto,
                'porcentagem' => round((($preco - $preco_desconto) / $preco) * 100)
            ];
        }
    }
    return [
        'ativo' => false,
        'preco_final' => $preco
    ];
}

// 3. BUSCA DOS DETALHES DO ANÚNCIO (Incluindo campos de desconto)
try {
    $sql = "SELECT 
                p.id, 
                p.nome AS produto, 
                p.descricao,
                p.preco,
                p.preco_desconto,
                p.desconto_data_fim,
                p.estoque AS estoque_kg,
                p.estoque_unidades,
                p.modo_precificacao,
                p.embalagem_peso_kg,
                p.embalagem_unidades,
                p.paletizado,
                p.unidade_medida,
                p.imagem_url, 
                v.id AS vendedor_sistema_id, 
                u.id AS vendedor_usuario_id, 
                v.nome_comercial AS nome_vendedor
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

// Ajustar campos de exibição conforme modo de precificação
$modo = $anuncio['modo_precificacao'] ?? 'por_quilo';
if (in_array($modo, ['por_unidade', 'caixa_unidades', 'saco_unidades'])) {
    $anuncio['quantidade_disponivel'] = $anuncio['estoque_unidades'] ?? 0;
} else {
    $anuncio['quantidade_disponivel'] = $anuncio['estoque_kg'] ?? 0;
}
switch ($modo) {
    case 'por_unidade': $anuncio['unidade_medida'] = 'unidade'; break;
    case 'por_quilo': $anuncio['unidade_medida'] = 'kg'; break;
    case 'caixa_unidades': $anuncio['unidade_medida'] = 'caixa' . (!empty($anuncio['embalagem_unidades']) ? " ({$anuncio['embalagem_unidades']} unid)" : ''); break;
    case 'caixa_quilos': $anuncio['unidade_medida'] = 'caixa' . (!empty($anuncio['embalagem_peso_kg']) ? " ({$anuncio['embalagem_peso_kg']} kg)" : ''); break;
    case 'saco_unidades': $anuncio['unidade_medida'] = 'saco' . (!empty($anuncio['embalagem_unidades']) ? " ({$anuncio['embalagem_unidades']} unid)" : ''); break;
    case 'saco_quilos': $anuncio['unidade_medida'] = 'saco' . (!empty($anuncio['embalagem_peso_kg']) ? " ({$anuncio['embalagem_peso_kg']} kg)" : ''); break;
}

// 4. VERIFICAR SE O VENDEDOR ESTÁ TENTANDO COMPRAR DE SI MESMO
if ($usuario_tipo === 'vendedor') {
    // Precisamos verificar se este vendedor é o dono do anúncio
    try {
        $sql_verifica_vendedor = "SELECT v.id 
                                  FROM vendedores v 
                                  JOIN produtos p ON p.vendedor_id = v.id 
                                  WHERE p.id = :anuncio_id AND v.usuario_id = :usuario_id";
        
        $stmt_verifica = $conn->prepare($sql_verifica_vendedor);
        $stmt_verifica->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
        $stmt_verifica->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_verifica->execute();
        
        if ($stmt_verifica->rowCount() > 0) {
            // O vendedor é o dono do anúncio, não pode comprar de si mesmo
            header("Location: ../anuncios.php?erro=" . urlencode("Você não pode comprar ou fazer proposta para seu próprio anúncio."));
            exit();
        }
    } catch (PDOException $e) {
        // Em caso de erro, continua (não bloqueia o acesso)
        error_log("Erro ao verificar vendedor: " . $e->getMessage());
    }
}

// 4. VERIFICAR LOGÍSTICA DE ENTREGA - NOVO CÓDIGO
$estado_comprador = '';
$estados_atendidos = [];
$entrega_disponivel = true; // Assume true por padrão

try {
    // Buscar estado do comprador
    $sql_estado_comprador = "SELECT estado FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_estado = $conn->prepare($sql_estado_comprador);
    $stmt_estado->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_estado->execute();
    
    if ($comprador_estado = $stmt_estado->fetch(PDO::FETCH_ASSOC)) {
        $estado_comprador = strtoupper(trim($comprador_estado['estado']));
    }
    
    // Buscar estados atendidos pelo vendedor
    $sql_estados_vendedor = "SELECT estados_atendidos FROM vendedores WHERE id = :vendedor_id";
    $stmt_estados = $conn->prepare($sql_estados_vendedor);
    $stmt_estados->bindParam(':vendedor_id', $anuncio['vendedor_sistema_id'], PDO::PARAM_INT);
    $stmt_estados->execute();
    
    if ($vendedor_estados = $stmt_estados->fetch(PDO::FETCH_ASSOC)) {
        $estados_json = $vendedor_estados['estados_atendidos'];
        
        if (!empty($estados_json) && $estados_json !== 'null') {
            $estados_atendidos = json_decode($estados_json, true) ?: [];
            
            // Verificar se o vendedor definiu uma lista branca
            if (!empty($estados_atendidos) && !empty($estado_comprador)) {
                // Verificar se o estado do comprador está na lista
                $entrega_disponivel = in_array($estado_comprador, $estados_atendidos);
            }
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao verificar logística: " . $e->getMessage());
    // Em caso de erro, mantém a entrega como disponível
}

// 5. BUSCAR O COMPRADOR_ID CORRETAMENTE (COM CRIAÇÃO AUTOMÁTICA)
$comprador_id = null;
try {
    $sql_busca_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    
    $stmt_comprador = $conn->prepare($sql_busca_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    
    if ($stmt_comprador->rowCount() > 0) {
        $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);
        $comprador_id = $comprador['id'];
    } else {
        // Se não encontrar registro como comprador
        if ($usuario_tipo === 'vendedor') {
            // Buscar dados do vendedor para criar perfil de comprador
            $sql_busca_vendedor = "SELECT v.*, u.email, u.nome 
                                   FROM vendedores v 
                                   JOIN usuarios u ON v.usuario_id = u.id 
                                   WHERE v.usuario_id = :usuario_id";
            
            $stmt_vendedor = $conn->prepare($sql_busca_vendedor);
            $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_vendedor->execute();
            $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);
            
            if ($vendedor) {
                // Dados padrão caso algum campo esteja vazio
                $nome_comercial = !empty($vendedor['nome_comercial']) ? $vendedor['nome_comercial'] : $vendedor['nome'];
                $cpf_cnpj = !empty($vendedor['cpf_cnpj']) ? $vendedor['cpf_cnpj'] : '';
                $cep = !empty($vendedor['cep']) ? $vendedor['cep'] : '';
                $rua = !empty($vendedor['rua']) ? $vendedor['rua'] : '';
                $numero = !empty($vendedor['numero']) ? $vendedor['numero'] : '';
                $estado = !empty($vendedor['estado']) ? $vendedor['estado'] : '';
                $cidade = !empty($vendedor['cidade']) ? $vendedor['cidade'] : '';
                $telefone1 = !empty($vendedor['telefone1']) ? $vendedor['telefone1'] : '';
                
                // Criar registro na tabela compradores
                $sql_criar_comprador = "INSERT INTO compradores 
                                        (usuario_id, tipo_pessoa, nome_comercial, cpf_cnpj, cep, rua, numero, complemento, estado, cidade, telefone1, plano)
                                        VALUES 
                                        (:usuario_id, 'cpf', :nome_comercial, :cpf_cnpj, :cep, :rua, :numero, '', :estado, :cidade, :telefone1, 'free')";
                
                $stmt_criar = $conn->prepare($sql_criar_comprador);
                $stmt_criar->bindParam(':usuario_id', $usuario_id);
                $stmt_criar->bindParam(':nome_comercial', $nome_comercial);
                $stmt_criar->bindParam(':cpf_cnpj', $cpf_cnpj);
                $stmt_criar->bindParam(':cep', $cep);
                $stmt_criar->bindParam(':rua', $rua);
                $stmt_criar->bindParam(':numero', $numero);
                $stmt_criar->bindParam(':estado', $estado);
                $stmt_criar->bindParam(':cidade', $cidade);
                $stmt_criar->bindParam(':telefone1', $telefone1);
                
                if ($stmt_criar->execute()) {
                    $comprador_id = $conn->lastInsertId();
                    error_log("Perfil de comprador criado automaticamente para vendedor ID: $usuario_id");
                } else {
                    error_log("Erro ao criar perfil de comprador para vendedor ID: $usuario_id");
                    header("Location: ../anuncios.php?erro=" . urlencode("Erro ao configurar perfil de comprador. Tente novamente."));
                    exit();
                }
            } else {
                error_log("Vendedor não encontrado ao tentar criar perfil de comprador. Usuario ID: $usuario_id");
                header("Location: ../anuncios.php?erro=" . urlencode("Perfil de vendedor incompleto. Atualize seus dados primeiro."));
                exit();
            }
        } else {
            error_log("Comprador sem registro na tabela compradores. Usuario ID: $usuario_id");
            header("Location: dashboard.php?erro=" . urlencode("Perfil de comprador incompleto. Entre em contato com o suporte."));
            exit();
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar/criar comprador_id: " . $e->getMessage());
    header("Location: dashboard.php?erro=" . urlencode("Erro temporário no sistema. Tente novamente em alguns minutos."));
    exit();
}

// Calcular desconto do produto principal
$info_desconto = calcularDesconto($anuncio['preco'], $anuncio['preco_desconto'], $anuncio['desconto_data_fim']);

// BUSCAR IMAGENS DO PRODUTO - CORRIGIDO: Buscar TODAS as imagens relacionadas
$imagens_produto = [];

// Primeiro, verificar se existe uma tabela de imagens múltiplas
$tabela_imagens_existe = false;
try {
    // Verificar se a tabela produto_imagens existe
    $sql_verifica_tabela = "SHOW TABLES LIKE 'produto_imagens'";
    $stmt_verifica = $conn->query($sql_verifica_tabela);
    $tabela_imagens_existe = $stmt_verifica->rowCount() > 0;
} catch (Exception $e) {
    $tabela_imagens_existe = false;
}

if ($tabela_imagens_existe) {
    // Se a tabela existe, buscar todas as imagens
    try {
        $sql_imagens = "SELECT imagem_url FROM produto_imagens WHERE produto_id = :anuncio_id ORDER BY ordem ASC";
        $stmt_imagens = $conn->prepare($sql_imagens);
        $stmt_imagens->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
        $stmt_imagens->execute();
        $imagens_temp = $stmt_imagens->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($imagens_temp as $imagem) {
            $imagens_produto[] = [
                'url' => $imagem['imagem_url'],
                'principal' => false
            ];
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar imagens do produto: " . $e->getMessage());
    }
}

// Se não encontrou imagens múltiplas, usar a imagem principal do produto
if (empty($imagens_produto) && !empty($anuncio['imagem_url'])) {
    $imagens_produto[] = [
        'url' => $anuncio['imagem_url'],
        'principal' => true
    ];
    
    // Para demonstração, adicionar mais algumas imagens de exemplo (remova em produção)
    // Estas são apenas para testar o carrossel
    $imagens_exemplo = [
        '../../img/placeholder.png',
        '../../img/logo-nova.png',
        'https://via.placeholder.com/600x400/4CAF50/FFFFFF?text=Produto+Agrícola',
        'https://via.placeholder.com/600x400/388E3C/FFFFFF?text=Detalhe+do+Produto'
    ];
    
    foreach ($imagens_exemplo as $exemplo) {
        $imagens_produto[] = [
            'url' => $exemplo,
            'principal' => false
        ];
    }
}

// Se ainda não tem imagens, usar um placeholder
if (empty($imagens_produto)) {
    $imagens_produto[] = [
        'url' => '../../img/placeholder.png',
        'principal' => true
    ];
}

// Buscar produtos relacionados
$produtos_relacionados = [];
try {
    $sql_relacionados = "SELECT 
                            p.id, 
                            p.nome, 
                            p.preco, 
                            p.preco_desconto,
                            p.desconto_data_fim,
                            p.imagem_url,
                            p.unidade_medida,
                            v.nome_comercial AS nome_vendedor
                         FROM produtos p
                         JOIN vendedores v ON p.vendedor_id = v.id 
                         JOIN usuarios u ON v.usuario_id = u.id
                         WHERE p.id != :anuncio_id 
                         AND p.status = 'ativo' 
                         AND p.estoque > 0
                         ORDER BY RAND() 
                         LIMIT 4";
    
    $stmt_relacionados = $conn->prepare($sql_relacionados);
    $stmt_relacionados->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_relacionados->execute();
    $produtos_relacionados = $stmt_relacionados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se der erro, continua sem produtos relacionados
}

// Verificar se o produto já está nos favoritos do usuário
$is_favorito = false;
$favorito_id = null;
try {
    $sql_favorito = "SELECT id FROM favoritos WHERE usuario_id = :usuario_id AND produto_id = :produto_id";
    $stmt_favorito = $conn->prepare($sql_favorito);
    $stmt_favorito->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_favorito->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_favorito->execute();
    if ($stmt_favorito->rowCount() > 0) {
        $is_favorito = true;
        $favorito = $stmt_favorito->fetch(PDO::FETCH_ASSOC);
        $favorito_id = $favorito['id'];
    }
} catch (PDOException $e) {
    // Se a tabela não existir, ignora o erro
}

$preco_unitario = $info_desconto['preco_final'];
$preco_display = number_format($preco_unitario, 2, ',', '.');
$unidade = htmlspecialchars($anuncio['unidade_medida']);
$imagePath = !empty($imagens_produto[0]['url']) ? htmlspecialchars($imagens_produto[0]['url']) : '../../img/placeholder.png';
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anuncio['produto']); ?> - Encontre o Campo</title>
    <link rel="stylesheet" href="../css/comprador/proposta_nova.css?v=1.5">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        .preco-dinamico .valor-unitario {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        
        .preco-dinamico .valor-total-label {
            font-size: 14px;
            color: #2E7D32;
            margin-top: 5px;
            font-weight: 600;
        }

        .btn-chat {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }

        .btn-chat:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }

        .btn-chat i {
            font-size: 20px;
        }

        .btn-disabled {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            filter: grayscale(30%) !important;
        }

        .btn-disabled:hover {
            transform: none !important;
            box-shadow: none !important;
        }

        .status-info, .status-alert {
            padding: 8px 12px;
            margin-top: 10px;
        }

        .status-info i, .status-alert i {
            margin-right: 5px;
        }

        /* Badge de status pendente */
        .status-badge-pendente {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffc107;
            color: #856404;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            z-index: 100;
        }

        .aviso-pendente {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
            text-align: center;
        }

        .aviso-pendente i {
            margin-right: 10px;
            color: #ffc107;
        }

        .aviso-pendente a {
            color: #007bff;
            text-decoration: underline;
            font-weight: bold;
        }
        
        /* Estilos para o carrossel de imagens */
        .carrossel-container {
            position: relative;
            width: 100%;
            height: 400px;
            border-radius: var(--radius);
            overflow: hidden;
            background: var(--gray);
        }
        
        .carrossel-slides {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.5s ease-in-out;
        }
        
        .carrossel-slide {
            min-width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .carrossel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .carrossel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(76, 175, 80, 0.9);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s ease;
            z-index: 10;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .carrossel-btn:hover {
            background: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .carrossel-btn.prev {
            left: 15px;
        }
        
        .carrossel-btn.next {
            right: 15px;
        }
        
        .carrossel-btn:disabled {
            background: rgba(76, 175, 80, 0.5);
            cursor: not-allowed;
            transform: translateY(-50%);
        }
        
        .carrossel-btn:disabled:hover {
            transform: translateY(-50%);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .carrossel-indicators {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        
        .carrossel-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .carrossel-indicator.active {
            background: var(--primary-color);
            transform: scale(1.2);
        }
        
        .carrossel-indicator:hover {
            background: rgba(255, 255, 255, 0.8);
        }
        
        .carrossel-counter {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 10;
        }
        
        .badge-desconto {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--primary-color);
            color: var(--white);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1rem;
            z-index: 11;
        }
        
        /* Miniaturas das imagens */
        .carrossel-miniaturas {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            overflow-x: auto;
            padding: 5px 0;
        }
        
        .miniatura {
            width: 80px;
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .miniatura:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .miniatura.active {
            border-color: var(--primary-color);
            box-shadow: 0 3px 8px rgba(76, 175, 80, 0.3);
        }
        
        .miniatura img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        @media (max-width: 768px) {
            .carrossel-container {
                height: 300px;
            }
            
            .carrossel-btn {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .miniatura {
                width: 60px;
                height: 45px;
            }
        }
        
        @media (max-width: 576px) {
            .carrossel-container {
                height: 250px;
            }
            
            .carrossel-indicators {
                bottom: 10px;
            }
            
            .carrossel-indicator {
                width: 10px;
                height: 10px;
            }
        }
        
        /* Quando só tem uma imagem, esconder controles */
        .single-image .carrossel-btn,
        .single-image .carrossel-indicators,
        .single-image .carrossel-counter {
            display: none;
        }
    </style>
</head>
<body>
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
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            // Contar notificações não lidas
                            if (isset($_SESSION['usuario_id'])) {
                                $database = new Database();
                                $conn = $database->getConnection();
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) {
                                    echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                                }
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link exit-button no-underline">Sair</a>
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

    <main class="main-content">
        <?php if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'pendente'): ?>
            <div class="aviso-pendente">
                <i class="fas fa-clock"></i>
                <strong>Seu cadastro está aguardando aprovação.</strong> 
                Enquanto isso, você pode visualizar os anúncios, mas ainda não pode fazer negócios. 
            </div>
        <?php endif; ?>
        <div class="produto-container">
            <div class="produto-content">
                <!-- Seção de Imagem do Produto - CARROSSEL FUNCIONAL -->
                <div class="produto-imagem">
                    <div class="carrossel-container <?php echo count($imagens_produto) <= 1 ? 'single-image' : ''; ?>" id="carrossel-container">
                        <?php if ($info_desconto['ativo']): ?>
                            <div class="badge-desconto">-<?php echo $info_desconto['porcentagem']; ?>%</div>
                        <?php endif; ?>
                        
                        <div class="carrossel-slides" id="carrossel-slides">
                            <?php foreach ($imagens_produto as $index => $imagem): ?>
                                <div class="carrossel-slide">
                                    <img src="<?php echo htmlspecialchars($imagem['url']); ?>" 
                                         alt="<?php echo htmlspecialchars($anuncio['produto']); ?> - Imagem <?php echo $index + 1; ?>"
                                         onerror="this.src='../../img/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Botões de navegação -->
                        <?php if (count($imagens_produto) > 1): ?>
                            <button class="carrossel-btn prev" id="carrossel-prev">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="carrossel-btn next" id="carrossel-next">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            
                            <!-- Contador de imagens -->
                            <div class="carrossel-counter" id="carrossel-counter">
                                1/<?php echo count($imagens_produto); ?>
                            </div>
                            
                            <!-- Indicadores -->
                            <div class="carrossel-indicators" id="carrossel-indicators">
                                <?php for ($i = 0; $i < count($imagens_produto); $i++): ?>
                                    <div class="carrossel-indicator <?php echo $i === 0 ? 'active' : ''; ?>" 
                                         data-index="<?php echo $i; ?>"></div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Miniaturas -->
                    <?php if (count($imagens_produto) > 1): ?>
                        <div class="carrossel-miniaturas" id="carrossel-miniaturas">
                            <?php foreach ($imagens_produto as $index => $imagem): ?>
                                <div class="miniatura <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     data-index="<?php echo $index; ?>">
                                    <img src="<?php echo htmlspecialchars($imagem['url']); ?>" 
                                         alt="Miniatura <?php echo $index + 1; ?>"
                                         onerror="this.src='../../img/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="produto-actions">
                        <?php if ($is_favorito && $favorito_id): ?>
                            <a href="remover_favorito.php?favorito_id=<?php echo $favorito_id; ?>&redirect=proposta_nova.php?anuncio_id=<?php echo $anuncio_id; ?>" 
                               class="btn-action favoritado">
                                <i class="fas fa-heart"></i>
                                <span>Remover dos Favoritos</span>
                            </a>
                        <?php else: ?>
                            <a href="adicionar_favorito.php?produto_id=<?php echo $anuncio_id; ?>&redirect=proposta_nova.php?anuncio_id=<?php echo $anuncio_id; ?>" 
                               class="btn-action">
                                <i class="far fa-heart"></i>
                                <span>Adicionar aos Favoritos</span>
                            </a>
                        <?php endif; ?>

                        <button class="btn-action" id="btn-compartilhar">
                            <i class="fas fa-share-alt"></i>
                            <span>Compartilhar</span>
                        </button>
                    </div>
                </div>

                <!-- Formulário de Compra -->
                <div class="compra-section">
                    <div class="produto-info">
                        <div class="info-header">
                            <h2><?php echo htmlspecialchars($anuncio['produto']); ?></h2>
                            <div class="vendedor-info">
                                <span class="vendedor-label">Vendido por:</span>
                                <a href="../perfil_vendedor.php?vendedor_id=<?php echo $anuncio['vendedor_usuario_id']; ?>" class="vendedor-nome">
                                    <?php echo htmlspecialchars($anuncio['nome_vendedor']); ?>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>

                        <div class="preco-section preco-dinamico">
                            <?php if ($info_desconto['ativo']): ?>
                                <div class="preco-container">
                                    <span class="preco-antigo">R$ <?php echo number_format($info_desconto['preco_original'], 2, ',', '.'); ?></span>
                                    <span class="preco destaque-oferta" id="preco-atual">R$ <?php echo $preco_display; ?></span>
                                </div>
                                <div class="economia-info">
                                    <i class="fas fa-tag"></i> Economia de R$ <?php echo number_format($info_desconto['preco_original'] - $info_desconto['preco_final'], 2, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                <span class="preco" id="preco-atual">R$ <?php echo $preco_display; ?></span>
                            <?php endif; ?>
                            
                            <div class="unidade-info">
                                <span class="unidade">por <?php echo $unidade; ?></span>
                            </div>
                            
                            <div class="valor-info">
                                <div class="valor-unitario" id="valor-unitario">
                                    Preço unitário: R$ <?php echo $preco_display; ?>
                                </div>
                                <div class="valor-total-label" id="valor-total-label">
                                    Total: <span id="valor-total">R$ <?php echo $preco_display; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="estoque-info">
                            <i class="fas fa-box"></i>
                            <span><?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?> disponíveis</span>
                        </div>

                        <div class="quantidade-selector">
                            <label for="quantidade">Quantidade:</label>
                            <div class="quantidade-control">
                                <button type="button" class="qty-btn" id="decrease-qty">-</button>
                                <input type="number" id="quantidade" name="quantidade" value="1" min="1" max="<?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?>">
                                <button type="button" class="qty-btn" id="increase-qty">+</button>
                            </div>
                            <span class="unidade-info"><?php echo $unidade; ?></span>
                        </div>
                        

                        <div class="botoes-compra">
                            <?php if (!empty($anuncio['paletizado']) && $anuncio['paletizado'] == 1): ?><div class=""><i class="fas fa-cube"></i> Paletizado</div><?php endif; ?>
                            <?php if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                                <button type="button" class="btn-comprar" id="btn-comprar">
                                    <i class="fas fa-shopping-cart"></i>
                                    Comprar Agora
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-comprar btn-disabled" disabled title="Aguarde a aprovação da sua conta">
                                    <i class="fas fa-shopping-cart"></i>
                                    Comprar Agora
                                </button>
                            <?php endif; ?>
                            
                            <div class="proposta-option">
                                <?php if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                                    <a href="../chat/chat.php?produto_id=<?php echo $anuncio_id; ?>" class="btn-chat">
                                        <i class="fas fa-comments"></i>
                                        Conversar com o Vendedor
                                    </a>
                                <?php else: ?>
                                    <a type="button" class="btn-chat btn-disabled" disabled title="Aguarde a aprovação da sua conta">
                                        <i class="fas fa-comments"></i>
                                        Conversar com o Vendedor
                                    </a>
                                    <div class="status-info" style="color: #ff6b6b; font-size: 0.9em; margin-top: 5px;">
                                        <i class="fas fa-info-circle"></i> Aguarde a aprovação da sua conta para fazer negócios
                                    </div>
                                <?php endif; ?>
                                <p class="proposta-text">Negocie diretamente com o vendedor</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                    <!-- ALERTA DE LOGÍSTICA - NOVA SEÇÃO -->
        <?php if (!$entrega_disponivel && !empty($estado_comprador) && !empty($estados_atendidos)): ?>
        <div class="alerta-logistica alerta-aviso">
            <div class="alerta-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h4>Atenção: Verifique a disponibilidade de entrega</h4>
            </div>
            <div class="alerta-body">
                <p>
                    O vendedor <strong><?php echo htmlspecialchars($anuncio['nome_vendedor']); ?></strong> 
                    informou que <strong>não realiza entregas para o estado de <?php echo $estado_comprador; ?></strong>.
                </p>
                <p class="alerta-detalhes">
                    <i class="fas fa-info-circle"></i>
                    Estados atendidos: 
                    <?php echo implode(', ', $estados_atendidos); ?>
                </p>
                <p class="alerta-aviso-importante">
                    <strong>⚠️ Prossiga com a proposta por sua conta e risco</strong> 
                    ou entre em contato com o vendedor antes de negociar.
                </p>
            </div>
        </div>
        <?php elseif ($entrega_disponivel && !empty($estado_comprador) && !empty($estados_atendidos)): ?>
        <div class="alerta-logistica alerta-sucesso">
            <div class="alerta-header">
                <i class="fas fa-check-circle"></i>
                <h4>Entrega disponível para sua região</h4>
            </div>
            <div class="alerta-body">
                <p>
                    Este vendedor atende ao seu estado (<strong><?php echo $estado_comprador; ?></strong>).
                </p>
            </div>
        </div>
        <?php endif; ?>

            <!-- Descrição do Produto -->
            <?php if ($anuncio['descricao']): ?>
            <div class="descricao-completa">
                <div class="descricao-header">
                    <h3><i class="fas fa-file-alt"></i> Descrição do Produto</h3>
                </div>
                <div class="descricao-content">
                    <p><?php echo nl2br(htmlspecialchars($anuncio['descricao'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulário de Proposta -->
            <div class="proposta-section" id="proposta-section">
                <div class="section-header">
                    <h3><i class="fas fa-handshake"></i> Fazer Proposta</h3>
                    <p>Preencha os detalhes da sua proposta</p>
                </div>

                <form action="processar_proposta.php" method="POST" class="proposta-form">
                    <input type="hidden" name="produto_id" value="<?php echo $anuncio_id; ?>">
                    <input type="hidden" name="comprador_id" value="<?php echo $comprador_id; ?>">
                    <input type="hidden" name="usuario_tipo" value="<?php echo $usuario_tipo; ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="preco_proposto">
                                <i class="fas fa-tag"></i>
                                Preço Proposto (por <?php echo $unidade; ?>)
                            </label>
                            <div class="input-with-symbol">
                                <span class="currency-symbol">R$</span>
                                <input type="number" id="preco_proposto" name="preco_proposto" 
                                       step="0.01" min="0.01" required
                                       value="<?php echo number_format($info_desconto['preco_final'], 2, '.', ''); ?>"
                                       placeholder="0.00">
                            </div>
                            <small>Preço atual: R$ <?php echo $preco_display; ?> por <?php echo $unidade; ?></small>
                        </div>

                        <div class="form-group">
                            <label for="quantidade_proposta">
                                <i class="fas fa-box"></i>
                                Quantidade (<?php echo $unidade; ?>)
                            </label>
                            <input type="number" id="quantidade_proposta" name="quantidade_proposta" 
                                   min="1" max="<?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?>" 
                                   required value="1">
                            <small>Máximo: <?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="condicoes">
                            <i class="fas fa-file-alt"></i>
                            Condições/Detalhes (Opcional)
                        </label>
                        <textarea id="condicoes" name="condicoes" rows="4" 
                                  placeholder="Adicione detalhes para a negociação, como condições de entrega, pagamento, etc..."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Enviar Proposta
                        </button>
                        <button type="button" class="btn btn-secondary" id="btn-cancelar-proposta">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Produtos Relacionados -->
            <?php if (!empty($produtos_relacionados)): ?>
            <div class="produtos-relacionados">
                <div class="section-header">
                    <h3><i class="fas fa-star"></i> Outros Anúncios</h3>
                    <p>Produtos que podem te interessar</p>
                </div>
                <div class="anuncios-grid">
                    <?php foreach ($produtos_relacionados as $produto): 
                        $desc_rel = calcularDesconto($produto['preco'], $produto['preco_desconto'], $produto['desconto_data_fim']);
                        $imagem_produto = $produto['imagem_url'] ? htmlspecialchars($produto['imagem_url']) : '../../img/placeholder.png';
                    ?>
                        <div class="anuncio-card <?php echo $desc_rel['ativo'] ? 'card-desconto' : ''; ?>">
                            <a href="proposta_nova.php?anuncio_id=<?php echo $produto['id']; ?>" class="produto-link">
                                <div class="card-image">
                                    <?php if ($desc_rel['ativo']): ?>
                                        <div class="badge-desconto">-<?php echo $desc_rel['porcentagem']; ?>%</div>
                                    <?php endif; ?>
                                    <img src="<?php echo $imagem_produto; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                </div>
                                <div class="card-content">
                                    <div class="card-header">
                                        <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
                                        <span class="vendedor">por <?php echo htmlspecialchars($produto['nome_vendedor']); ?></span>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="price-container">
                                            <?php if ($desc_rel['ativo']): ?>
                                                <div class="preco-original">R$ <?php echo number_format($desc_rel['preco_original'], 2, ',', '.'); ?></div>
                                                <div class="price price-desconto">
                                                    R$ <?php echo number_format($desc_rel['preco_final'], 2, ',', '.'); ?>
                                                    <span>/<?php echo htmlspecialchars($produto['unidade_medida']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <p class="price">
                                                    R$ <?php echo number_format($desc_rel['preco_final'], 2, ',', '.'); ?>
                                                    <span>/<?php echo htmlspecialchars($produto['unidade_medida']); ?></span>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Encontre o Campo</h4>
                    <ul>
                        <li><a href="../../index.php">Página Inicial</a></li>
                        <li><a href="../anuncios.php">Ver Anúncios</a></li>
                        <li><a href="favoritos.php">Meus Favoritos</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="../ajuda.php">Central de Ajuda</a></li>
                        <li><a href="../contato.php">Fale Conosco</a></li>
                        <li><a href="../sobre.php">Sobre Nós</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="../faq.php">FAQ</a></li>
                        <li><a href="../termos.php">Termos de Uso</a></li>
                        <li><a href="../privacidade.php">Política de Privacidade</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contato</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> contato@encontreocampo.com.br</p>
                        <p><i class="fas fa-phone"></i> (11) 99999-9999</p>
                        <div class="social-links">
                            <a href="#"><i class="fab fa-facebook"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu Hamburguer
            const hamburger = document.querySelector(".hamburger");
            const navMenu = document.querySelector(".nav-menu");

            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });

            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            }));

            // Scripts originais mantidos e funcionais
            const quantidadeInput = document.getElementById('quantidade');
            const decreaseBtn = document.getElementById('decrease-qty');
            const increaseBtn = document.getElementById('increase-qty');
            const btnCancelarProposta = document.getElementById('btn-cancelar-proposta');
            const propostaSection = document.getElementById('proposta-section');
            const quantidadeProposta = document.getElementById('quantidade_proposta');
            const btnCompartilhar = document.getElementById('btn-compartilhar');
            const precoAtualElement = document.getElementById('preco-atual');
            const valorTotalElement = document.getElementById('valor-total');
            const valorUnitarioElement = document.getElementById('valor-unitario');
            const valorTotalLabel = document.getElementById('valor-total-label');
            const precoPropostoInput = document.getElementById('preco_proposto');
            
            // Preço unitário do produto
            const precoUnitario = <?php echo $preco_unitario; ?>;

            propostaSection.style.display = 'none';
            
            // Função para formatar valor
            function formatarValor(valor) {
                return valor.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
            
            // Função para calcular e exibir o valor total
            function atualizarValorTotal() {
                const quantidade = parseInt(quantidadeInput.value);
                
                if (quantidade && quantidade > 0) {
                    const valorTotal = precoUnitario * quantidade;
                    const valorUnitarioFormatado = formatarValor(precoUnitario);
                    const valorTotalFormatado = formatarValor(valorTotal);
                    
                    // Atualizar o preço grande na tela
                    if (quantidade === 1) {
                        precoAtualElement.textContent = `R$ ${valorUnitarioFormatado}`;
                        valorTotalLabel.style.display = 'none';
                    } else {
                        precoAtualElement.textContent = `R$ ${valorTotalFormatado}`;
                        valorTotalLabel.style.display = 'block';
                        valorTotalElement.textContent = `R$ ${valorTotalFormatado}`;
                    }
                    
                    // Atualizar também o campo de quantidade na proposta
                    if (quantidadeProposta) {
                        quantidadeProposta.value = quantidade;
                    }
                    
                    // Atualizar o valor unitário informativo
                    valorUnitarioElement.textContent = `Preço unitário: R$ ${valorUnitarioFormatado} por ${'<?php echo $unidade; ?>'}`;
                    
                    // Atualizar o campo de preço proposto
                    if (precoPropostoInput.value == <?php echo $preco_unitario; ?>) {
                        precoPropostoInput.value = precoUnitario.toFixed(2);
                    }
                }
            }
            
            // Atualizar valor total inicial
            atualizarValorTotal();

            decreaseBtn.addEventListener('click', () => {
                if (quantidadeInput.value > 1) {
                    quantidadeInput.value = parseInt(quantidadeInput.value) - 1;
                    atualizarValorTotal();
                }
            });

            increaseBtn.addEventListener('click', () => {
                const max = parseInt(quantidadeInput.max);
                if (quantidadeInput.value < max) {
                    quantidadeInput.value = parseInt(quantidadeInput.value) + 1;
                    atualizarValorTotal();
                }
            });

            quantidadeInput.addEventListener('change', () => {
                let value = parseInt(quantidadeInput.value);
                const max = parseInt(quantidadeInput.max);
                const min = parseInt(quantidadeInput.min);
                if (value < min) value = min;
                if (value > max) value = max;
                quantidadeInput.value = value;
                atualizarValorTotal();
            });
            
            quantidadeInput.addEventListener('input', () => {
                atualizarValorTotal();
            });
            
            if (quantidadeProposta) {
                quantidadeProposta.addEventListener('change', () => {
                    let value = parseInt(quantidadeProposta.value);
                    const max = parseInt(quantidadeProposta.max);
                    const min = parseInt(quantidadeProposta.min);
                    if (value < min) value = min;
                    if (value > max) value = max;
                    quantidadeProposta.value = value;
                    quantidadeInput.value = value;
                    atualizarValorTotal();
                });
                
                quantidadeProposta.addEventListener('input', () => {
                    quantidadeInput.value = quantidadeProposta.value;
                    atualizarValorTotal();
                });
            }

            btnCancelarProposta.addEventListener('click', () => {
                propostaSection.classList.remove('show');
                setTimeout(() => { propostaSection.style.display = 'none'; }, 300);
                propostaAberta = false;
            });

            // Logica de compartilhar
            if (btnCompartilhar) {
                btnCompartilhar.addEventListener('click', function() {
                    const url = window.location.href;
                    navigator.clipboard.writeText(url).then(() => {
                        alert('Link copiado para a área de transferência!');
                    });
                });
            }
            
            // Botão Comprar Agora
            const btnComprar = document.getElementById('btn-comprar');
            if (btnComprar) {
                btnComprar.addEventListener('click', function() {
                    const quantidade = quantidadeInput.value;
                    const valorTotal = precoUnitario * quantidade;
                    
                    const valorTotalFormatado = formatarValor(valorTotal);
                    const precoUnitarioFormatado = formatarValor(precoUnitario);
                    
                    const confirmar = confirm(`Você está comprando ${quantidade} ${'<?php echo $unidade; ?>'} de ${'<?php echo htmlspecialchars($anuncio['produto']); ?>'}\n\n` +
                                             `Preço unitário: R$ ${precoUnitarioFormatado}\n` +
                                             `Valor total: R$ ${valorTotalFormatado}\n\n` +
                                             `Deseja prosseguir com a compra?`);
                    
                    if (confirmar) {
                        alert('Funcionalidade de compra direta em desenvolvimento. Para comprar, use a opção "Fazer Proposta".');
                    }
                });
            }
            
            // SCRIPT DO CARROSSEL - CORRIGIDO E FUNCIONAL
            const carrosselSlides = document.getElementById('carrossel-slides');
            const carrosselPrev = document.getElementById('carrossel-prev');
            const carrosselNext = document.getElementById('carrossel-next');
            const carrosselCounter = document.getElementById('carrossel-counter');
            const carrosselIndicators = document.querySelectorAll('.carrossel-indicator');
            const carrosselMiniaturas = document.querySelectorAll('.miniatura');
            const totalSlides = <?php echo count($imagens_produto); ?>;
            
            let currentSlide = 0;
            
            // Atualizar carrossel
            function updateCarrossel() {
                // Mover slides
                carrosselSlides.style.transform = `translateX(-${currentSlide * 100}%)`;
                
                // Atualizar contador
                if (carrosselCounter) {
                    carrosselCounter.textContent = `${currentSlide + 1}/${totalSlides}`;
                }
                
                // Atualizar indicadores
                carrosselIndicators.forEach((indicator, index) => {
                    if (index === currentSlide) {
                        indicator.classList.add('active');
                    } else {
                        indicator.classList.remove('active');
                    }
                });
                
                // Atualizar miniaturas
                carrosselMiniaturas.forEach((miniatura, index) => {
                    if (index === currentSlide) {
                        miniatura.classList.add('active');
                        // Rolar para a miniatura ativa
                        miniatura.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    } else {
                        miniatura.classList.remove('active');
                    }
                });
                
                // Atualizar botões de navegação
                if (carrosselPrev) {
                    carrosselPrev.disabled = currentSlide === 0;
                }
                if (carrosselNext) {
                    carrosselNext.disabled = currentSlide === totalSlides - 1;
                }
            }
            
            // Só inicializar o carrossel se tiver mais de uma imagem
            if (totalSlides > 1 && carrosselPrev && carrosselNext) {
                // Event listeners para botões
                carrosselPrev.addEventListener('click', () => {
                    if (currentSlide > 0) {
                        currentSlide--;
                        updateCarrossel();
                    }
                });
                
                carrosselNext.addEventListener('click', () => {
                    if (currentSlide < totalSlides - 1) {
                        currentSlide++;
                        updateCarrossel();
                    }
                });
                
                // Event listeners para indicadores
                carrosselIndicators.forEach(indicator => {
                    indicator.addEventListener('click', () => {
                        const index = parseInt(indicator.getAttribute('data-index'));
                        if (index !== currentSlide) {
                            currentSlide = index;
                            updateCarrossel();
                        }
                    });
                });
                
                // Event listeners para miniaturas
                carrosselMiniaturas.forEach(miniatura => {
                    miniatura.addEventListener('click', () => {
                        const index = parseInt(miniatura.getAttribute('data-index'));
                        if (index !== currentSlide) {
                            currentSlide = index;
                            updateCarrossel();
                        }
                    });
                });
                
                // Navegação por teclado
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') {
                        if (currentSlide > 0) {
                            currentSlide--;
                            updateCarrossel();
                        }
                    } else if (e.key === 'ArrowRight') {
                        if (currentSlide < totalSlides - 1) {
                            currentSlide++;
                            updateCarrossel();
                        }
                    }
                });
                
                // Auto-play (opcional - descomente se quiser)
                /*
                let autoPlay = setInterval(() => {
                    if (currentSlide < totalSlides - 1) {
                        currentSlide++;
                    } else {
                        currentSlide = 0;
                    }
                    updateCarrossel();
                }, 4000);
                
                // Pausar auto-play ao interagir
                const carrosselContainer = document.getElementById('carrossel-container');
                carrosselContainer.addEventListener('mouseenter', () => {
                    clearInterval(autoPlay);
                });
                
                carrosselContainer.addEventListener('mouseleave', () => {
                    autoPlay = setInterval(() => {
                        if (currentSlide < totalSlides - 1) {
                            currentSlide++;
                        } else {
                            currentSlide = 0;
                        }
                        updateCarrossel();
                    }, 4000);
                });
                */
                
                // Inicializar estado dos botões
                updateCarrossel();
            }
        });
    </script>
</body>
</html>