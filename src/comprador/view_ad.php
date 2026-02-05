<?php

session_start();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../permissions.php'; 

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

// Função auxiliar para calcular desconto
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

// 3. BUSCA DOS DETALHES DO ANÚNCIO
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
                v.nome_comercial AS nome_vendedor,
                v.estado AS estado_vendedor
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

// Ajustar campos de exibição
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
            header("Location: ../anuncios.php?erro=" . urlencode("Você não pode comprar ou fazer proposta para seu próprio anúncio."));
            exit();
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar vendedor: " . $e->getMessage());
    }
}

// 5. VERIFICAR LOGÍSTICA DE ENTREGA
$estado_comprador = '';
$estados_atendidos = [];
$entrega_disponivel = true; 

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
            if (!empty($estados_atendidos) && !empty($estado_comprador)) {
                $entrega_disponivel = in_array($estado_comprador, $estados_atendidos);
            }
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao verificar logística: " . $e->getMessage());
}

// 6. BUSCAR O COMPRADOR_ID
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
        if ($usuario_tipo === 'vendedor') {
            $sql_busca_vendedor = "SELECT v.*, u.email, u.nome 
                                   FROM vendedores v 
                                   JOIN usuarios u ON v.usuario_id = u.id 
                                   WHERE v.usuario_id = :usuario_id";
            $stmt_vendedor = $conn->prepare($sql_busca_vendedor);
            $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_vendedor->execute();
            $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);
            
            if ($vendedor) {
                $nome_comercial = !empty($vendedor['nome_comercial']) ? $vendedor['nome_comercial'] : $vendedor['nome'];
                $cpf_cnpj = !empty($vendedor['cpf_cnpj']) ? $vendedor['cpf_cnpj'] : '';
                $cep = !empty($vendedor['cep']) ? $vendedor['cep'] : '';
                $rua = !empty($vendedor['rua']) ? $vendedor['rua'] : '';
                $numero = !empty($vendedor['numero']) ? $vendedor['numero'] : '';
                $estado = !empty($vendedor['estado']) ? $vendedor['estado'] : '';
                $cidade = !empty($vendedor['cidade']) ? $vendedor['cidade'] : '';
                $telefone1 = !empty($vendedor['telefone1']) ? $vendedor['telefone1'] : '';
                
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
                } else {
                    header("Location: ../anuncios.php?erro=" . urlencode("Erro ao configurar perfil."));
                    exit();
                }
            } else {
                header("Location: ../anuncios.php?erro=" . urlencode("Perfil incompleto."));
                exit();
            }
        } else {
            header("Location: dashboard.php?erro=" . urlencode("Perfil de comprador incompleto."));
            exit();
        }
    }
} catch (PDOException $e) {
    header("Location: dashboard.php?erro=" . urlencode("Erro temporário no sistema."));
    exit();
}

$info_desconto = calcularDesconto($anuncio['preco'], $anuncio['preco_desconto'], $anuncio['desconto_data_fim']);

// BUSCAR IMAGENS DO PRODUTO
$imagens_produto = [];
$tabela_imagens_existe = false;
try {
    $sql_verifica_tabela = "SHOW TABLES LIKE 'produto_imagens'";
    $stmt_verifica = $conn->query($sql_verifica_tabela);
    $tabela_imagens_existe = $stmt_verifica->rowCount() > 0;
} catch (Exception $e) { $tabela_imagens_existe = false; }

if ($tabela_imagens_existe) {
    try {
        $sql_imagens = "SELECT imagem_url FROM produto_imagens WHERE produto_id = :anuncio_id ORDER BY ordem ASC";
        $stmt_imagens = $conn->prepare($sql_imagens);
        $stmt_imagens->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
        $stmt_imagens->execute();
        $imagens_temp = $stmt_imagens->fetchAll(PDO::FETCH_ASSOC);
        foreach ($imagens_temp as $imagem) {
            $imagens_produto[] = ['url' => $imagem['imagem_url'], 'principal' => false];
        }
    } catch (PDOException $e) {}
}

if (empty($imagens_produto) && !empty($anuncio['imagem_url'])) {
    $imagens_produto[] = ['url' => $anuncio['imagem_url'], 'principal' => true];
}
if (empty($imagens_produto)) {
    $imagens_produto[] = ['url' => '../../img/placeholder.png', 'principal' => true];
}

// -------------------------------------------------------------------------
// BUSCAR PRODUTOS RELACIONADOS (COM FILTRO DE LIMITE DO PLANO) - ATUALIZADO
// -------------------------------------------------------------------------
$produtos_relacionados = [];
try {
    $sql_relacionados = "SELECT 
                            final.id, 
                            final.nome, 
                            final.preco, 
                            final.preco_desconto,
                            final.desconto_data_fim,
                            final.imagem_url,
                            final.unidade_medida,
                            v.nome_comercial AS nome_vendedor
                         FROM (
                            -- Subquery para numerar os anúncios de cada vendedor (mais antigos primeiro)
                            SELECT 
                                p.id, p.vendedor_id, p.nome, p.preco, p.preco_desconto, 
                                p.desconto_data_fim, p.imagem_url, p.unidade_medida,
                                ROW_NUMBER() OVER (PARTITION BY p.vendedor_id ORDER BY p.id ASC) as ranking
                            FROM produtos p
                            WHERE p.status = 'ativo' AND p.estoque > 0
                         ) final
                         JOIN vendedores v ON final.vendedor_id = v.id 
                         JOIN planos pl ON v.plano_id = pl.id
                         WHERE final.ranking <= pl.limite_total_anuncios -- Filtra apenas os permitidos pelo plano
                         AND final.id != :anuncio_id 
                         ORDER BY RAND() 
                         LIMIT 4";
    
    $stmt_relacionados = $conn->prepare($sql_relacionados);
    $stmt_relacionados->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_relacionados->execute();
    $produtos_relacionados = $stmt_relacionados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se der erro na query complexa (ex: MySQL antigo), falha silenciosamente
    error_log("Erro produtos relacionados: " . $e->getMessage());
}

// Verificar favorito
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
} catch (PDOException $e) {}

// =========================================================================
// BUSCAR AVALIAÇÕES DO PRODUTO (ADICIONADO DO visualizar_anuncio.php)
// =========================================================================
$avaliacoes = [];
$avaliacoes_limitadas = [];
$media_avaliacao = 0;
$total_avaliacoes = 0;
try {
    // Buscar todas as avaliações para calcular média
    $sql_aval_todas = "SELECT a.*, u.nome FROM avaliacoes a LEFT JOIN usuarios u ON a.avaliador_usuario_id = u.id WHERE a.produto_id = :produto_id AND a.tipo = 'produto' ORDER BY a.data_criacao DESC";
    $stmt_aval_todas = $conn->prepare($sql_aval_todas);
    $stmt_aval_todas->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_aval_todas->execute();
    $avaliacoes = $stmt_aval_todas->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular média
    if (!empty($avaliacoes)) {
        $soma_notas = 0;
        foreach ($avaliacoes as $av) {
            $soma_notas += (int)$av['nota'];
        }
        $media_avaliacao = round($soma_notas / count($avaliacoes), 1);
        $total_avaliacoes = count($avaliacoes);
        
        // Limitar a 3 avaliações mais recentes para exibição
        $avaliacoes_limitadas = array_slice($avaliacoes, 0, 3);
    }
} catch (Exception $e) {
    // Se der erro, continua sem avaliações
    error_log("Erro ao buscar avaliações: " . $e->getMessage());
}

$preco_unitario = $info_desconto['preco_final'];
$preco_display = number_format($preco_unitario, 2, ',', '.');
$unidade = htmlspecialchars($anuncio['unidade_medida']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anuncio['produto']); ?> - Encontre o Campo</title>
    <link rel="stylesheet" href="../css/comprador/view_ad.css?v=1.5">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        .preco-dinamico .valor-unitario { font-size: 14px; color: #666; margin-top: 5px; font-style: italic; }
        .preco-dinamico .valor-total-label { font-size: 14px; color: #2E7D32; margin-top: 5px; font-weight: 600; }
        .btn-disabled { opacity: 0.6 !important; cursor: not-allowed !important; pointer-events: none !important; filter: grayscale(30%) !important; }
        .status-info, .status-alert { padding: 8px 12px; margin-top: 10px; }
        .aviso-pendente { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0; color: #856404; text-align: center; }
        .aviso-estoque { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border: 1px solid #f5a6af; border-radius: 8px; padding: 15px; margin: 20px 0; color: #721c24; text-align: center; font-weight: 700; }
        
        /* Carrossel */
        .carrossel-container { position: relative; width: 100%; height: 400px; border-radius: var(--radius); overflow: hidden; background: var(--gray); }
        .carrossel-slides { display: flex; width: 100%; height: 100%; transition: transform 0.5s ease-in-out; }
        .carrossel-slide { min-width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        .carrossel-slide img { width: 100%; height: 100%; object-fit: cover; }
        .carrossel-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(76, 175, 80, 0.9); color: white; border: none; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; transition: all 0.3s ease; z-index: 10; box-shadow: 0 3px 10px rgba(0,0,0,0.2); }
        .carrossel-btn:hover { background: var(--primary-color); transform: translateY(-50%) scale(1.1); }
        .carrossel-btn.prev { left: 15px; }
        .carrossel-btn.next { right: 15px; }
        .carrossel-btn:disabled { background: rgba(76, 175, 80, 0.5); cursor: not-allowed; }
        .carrossel-indicators { position: absolute; bottom: 15px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10; }
        .carrossel-indicator { width: 12px; height: 12px; border-radius: 50%; background: rgba(255, 255, 255, 0.5); cursor: pointer; transition: all 0.3s ease; }
        .carrossel-indicator.active { background: var(--primary-color); transform: scale(1.2); }
        .carrossel-counter { position: absolute; top: 15px; right: 15px; background: rgba(0, 0, 0, 0.6); color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.9rem; font-weight: 600; z-index: 10; }
        .badge-desconto { position: absolute; top: 15px; left: 15px; background: var(--primary-color); color: var(--white); padding: 8px 15px; border-radius: 20px; font-weight: 700; font-size: 1rem; z-index: 11; }
        .carrossel-miniaturas { display: flex; gap: 10px; margin-top: 15px; overflow-x: auto; padding: 5px 0; }
        .miniatura { width: 80px; height: 60px; border-radius: 6px; overflow: hidden; cursor: pointer; border: 2px solid transparent; transition: all 0.3s ease; flex-shrink: 0; }
        .miniatura.active { border-color: var(--primary-color); box-shadow: 0 3px 8px rgba(76, 175, 80, 0.3); }
        .miniatura img { width: 100%; height: 100%; object-fit: cover; }
        .single-image .carrossel-btn, .single-image .carrossel-indicators, .single-image .carrossel-counter { display: none; }
        
        /* Estilos para seção de avaliações (ADICIONADO) */
        .avaliacoes-section {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 30px;
            margin: 30px 0;
            border-left: 5px solid #4CAF50;
        }
        
        .avaliacoes-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .media-avaliacao {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .numero-media {
            font-size: 3em;
            font-weight: 700;
            color: #4CAF50;
        }
        
        .estrelas-media {
            display: flex;
            gap: 5px;
            font-size: 1.2em;
        }
        
        .estrela-cheia {
            color: #ffc107;
        }
        
        .estrela-vazia {
            color: #ddd;
        }
        
        .total-avaliacoes {
            color: #666;
            font-size: 0.95em;
        }
        
        .avaliacoes-info {
            flex: 1;
        }
        
        .avaliacoes-info h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.3em;
        }
        
        .avaliacoes-info p {
            color: #666;
            margin: 5px 0;
        }
        
        .avaliacoes-lista {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .avaliacao-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .avaliacao-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .avaliador-nome {
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }
        
        .avaliacao-data {
            color: #999;
            font-size: 0.85em;
        }
        
        .avaliacao-nota {
            display: flex;
            gap: 3px;
            font-size: 1.1em;
        }
        
        .avaliacao-comentario {
            color: #555;
            line-height: 1.6;
            margin-top: 10px;
            font-size: 0.95em;
        }
        
        .sem-avaliacoes {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .sem-avaliacoes i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .botao-avaliar {
            margin-top: 15px;
            text-align: center;
        }
        
        .botao-avaliar .btn-avaliar {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .botao-avaliar .btn-avaliar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 152, 0, 0.3);
        }
        
        @media (max-width: 768px) { 
            .carrossel-container { height: 300px; } 
            .carrossel-btn { width: 40px; height: 40px; font-size: 16px; } 
            .social-links { align-items: center; gap: 10px; margin-bottom: 10px; padding-left: 25px; }
            .avaliacoes-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../../img/logo-nova.png" alt="Logo">
                        <div><h1>ENCONTRE</h1><h2>O CAMPO</h2></div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="../anuncios.php" class="nav-link">Anúncios</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            if (isset($_SESSION['usuario_id'])) {
                                $database = new Database();
                                $conn_notif = $database->getConnection();
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $conn_notif->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
                <div class="hamburger"><span class="bar"></span><span class="bar"></span><span class="bar"></span></div>
            </div>
        </nav>
    </header>
    <br>

    <main class="main-content">
        <?php if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'pendente'): ?>
            <div class="aviso-pendente">
                <i class="fas fa-clock"></i> <strong>Seu cadastro está aguardando aprovação.</strong> Enquanto isso, você pode visualizar os anúncios, mas ainda não pode fazer negócios.
            </div>
        <?php endif; ?>
        
        <div class="produto-container">
            <div class="produto-content">
                <div class="produto-imagem">
                    <div class="carrossel-container <?php echo count($imagens_produto) <= 1 ? 'single-image' : ''; ?>" id="carrossel-container">
                        <?php if ($info_desconto['ativo']): ?><div class="badge-desconto">-<?php echo $info_desconto['porcentagem']; ?>%</div><?php endif; ?>
                        
                        <div class="carrossel-slides" id="carrossel-slides">
                            <?php foreach ($imagens_produto as $index => $imagem): ?>
                                <div class="carrossel-slide">
                                    <img src="<?php echo htmlspecialchars($imagem['url']); ?>" onerror="this.src='../../img/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($imagens_produto) > 1): ?>
                            <button class="carrossel-btn prev" id="carrossel-prev"><i class="fas fa-chevron-left"></i></button>
                            <button class="carrossel-btn next" id="carrossel-next"><i class="fas fa-chevron-right"></i></button>
                            <div class="carrossel-counter" id="carrossel-counter">1/<?php echo count($imagens_produto); ?></div>
                            <div class="carrossel-indicators" id="carrossel-indicators">
                                <?php for ($i = 0; $i < count($imagens_produto); $i++): ?>
                                    <div class="carrossel-indicator <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($imagens_produto) > 1): ?>
                        <div class="carrossel-miniaturas" id="carrossel-miniaturas">
                            <?php foreach ($imagens_produto as $index => $imagem): ?>
                                <div class="miniatura <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
                                    <img src="<?php echo htmlspecialchars($imagem['url']); ?>" onerror="this.src='../../img/placeholder.png'">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="produto-actions">
                        <?php if ($is_favorito && $favorito_id): ?>
                            <a href="remover_favorito.php?favorito_id=<?php echo $favorito_id; ?>&redirect=view_ad.php?anuncio_id=<?php echo $anuncio_id; ?>" class="btn-action favoritado">
                                <i class="fas fa-heart"></i><span>Remover dos Favoritos</span>
                            </a>
                        <?php else: ?>
                            <a href="adicionar_favorito.php?produto_id=<?php echo $anuncio_id; ?>&redirect=view_ad.php?anuncio_id=<?php echo $anuncio_id; ?>" class="btn-action">
                                <i class="far fa-heart"></i><span>Adicionar aos Favoritos</span>
                            </a>
                        <?php endif; ?>
                        <button class="btn-action" id="btn-compartilhar"><i class="fas fa-share-alt"></i><span>Compartilhar</span></button>
                    </div>
                </div>

                <div class="compra-section">
                    <div class="produto-info">
                        <div class="info-header">
                            <h2><?php echo htmlspecialchars($anuncio['produto']); ?></h2>
                            <div class="vendedor-info">
                                <span class="vendedor-label">Vendido por:</span>
                                <a href="../perfil_vendedor.php?vendedor_id=<?php echo $anuncio['vendedor_usuario_id']; ?>" class="vendedor-nome">
                                    <?php echo htmlspecialchars($anuncio['nome_vendedor']); ?> <i class="fas fa-external-link-alt"></i>
                                </a>
                                <span class="vendedor-local">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($anuncio['estado_vendedor']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="preco-section preco-dinamico">
                            <?php if ($info_desconto['ativo']): ?>
                                <div class="preco-container">
                                    <span class="preco-antigo">R$ <?php echo number_format($info_desconto['preco_original'], 2, ',', '.'); ?></span>
                                    <span class="preco destaque-oferta" id="preco-atual">R$ <?php echo $preco_display; ?></span>
                                </div>
                                <div class="economia-info"><i class="fas fa-tag"></i> Economia de R$ <?php echo number_format($info_desconto['preco_original'] - $info_desconto['preco_final'], 2, ',', '.'); ?></div>
                            <?php else: ?>
                                <span class="preco" id="preco-atual">R$ <?php echo $preco_display; ?></span>
                            <?php endif; ?>
                            
                            <div class="unidade-info"><span class="unidade">por <?php echo $unidade; ?></span></div>
                            <div class="valor-info">
                                <div class="valor-unitario" id="valor-unitario">Preço unitário: R$ <?php echo $preco_display; ?></div>
                                <div class="valor-total-label" id="valor-total-label">Total: <span id="valor-total">R$ <?php echo $preco_display; ?></span></div>
                            </div>
                        </div>
                        
                        <div class="estoque-info">
                            <i class="fas fa-box"></i> <span><?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?> disponíveis</span>
                        </div>

                        <?php if ((int)$anuncio['quantidade_disponivel'] <= 0): ?>
                            <div class="aviso-estoque">
                                <i class="fas fa-exclamation-circle"></i>
                                Este anúncio está com o estoque zerado no momento. Você ainda pode visualizar, conversar com o vendedor e enviar propostas — peça informação sobre reposição ou prazo de disponibilidade.
                            </div>
                        <?php endif; ?>

                        <div class="quantidade-selector">
                            <label for="quantidade">Quantidade:</label>
                            <div class="quantidade-control">
                                <button type="button" class="qty-btn" id="decrease-qty">-</button>
                                <input type="number" id="quantidade" name="quantidade" value="1" min="1" max="<?php echo max(1, (int)$anuncio['quantidade_disponivel']); ?>">
                                <button type="button" class="qty-btn" id="increase-qty">+</button>
                            </div>
                            <span class="unidade-info"><?php echo $unidade; ?></span>
                        </div>

                        <div class="botoes-compra">
                            <?php if (!empty($anuncio['paletizado']) && $anuncio['paletizado'] == 1): ?><div><i class="fas fa-cube"></i> Paletizado</div><?php endif; ?>
                            <?php if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                                <!-- BOTÃO DE COMPRA COMENTADO PARA USO FUTURO -->
                                <!-- <button type="button" class="btn-comprar" id="btn-comprar"><i class="fas fa-shopping-cart"></i> Comprar Agora</button> -->
                            <?php else: ?>
                                <!-- BOTÃO DE COMPRA COMENTADO PARA USO FUTURO -->
                                <!-- <button type="button" class="btn-comprar btn-disabled" disabled><i class="fas fa-shopping-cart"></i> Comprar Agora</button> -->
                            <?php endif; ?>
                            
                            <div class="proposta-option">
                                <?php if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                                    <a href="../chat/chat.php?produto_id=<?php echo $anuncio_id; ?>" class="btn-chat"><i class="fas fa-comments"></i> Conversar com o Vendedor</a>
                                <?php else: ?>
                                    <a type="button" class="btn-chat btn-disabled" disabled><i class="fas fa-comments"></i> Conversar com o Vendedor</a>
                                    <div class="status-info" style="color: #ff6b6b; font-size: 0.9em; margin-top: 5px;"><i class="fas fa-info-circle"></i> Aguarde a aprovação</div>
                                <?php endif; ?>
                                <p class="proposta-text">Negocie diretamente com o vendedor</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($estado_comprador) && !empty($estados_atendidos)): ?>
                <?php if ($entrega_disponivel): ?>
                <div class="alerta-logistica alerta-sucesso">
                    <div class="alerta-header"><i class="fas fa-check-circle"></i><h4>Entrega disponível para sua região</h4></div>
                    <div class="alerta-body"><p><i class="fas fa-truck"></i> O vendedor <strong><?php echo htmlspecialchars($anuncio['nome_vendedor']); ?></strong> realiza entregas para o estado de <strong><?php echo $estado_comprador; ?></strong>.</p></div>
                </div>
                <?php else: ?>
                <div class="alerta-logistica alerta-atencao">
                    <div class="alerta-header"><i class="fas fa-map-marker-alt"></i><h4>Verifique a disponibilidade de entrega</h4></div>
                    <div class="alerta-body">
                        <p>O vendedor <strong><?php echo htmlspecialchars($anuncio['nome_vendedor']); ?></strong> não realiza entregas diretas para <strong><?php echo $estado_comprador; ?></strong>.</p>
                        <p class="alerta-detalhes"><i class="fas fa-map"></i> <strong>Estados onde este vendedor entrega:</strong> <?php echo implode(', ', $estados_atendidos); ?></p>
                        <p class="alerta-recomendacao"><i class="fas fa-lightbulb"></i> <strong>Sugestões:</strong> Entre em contato via chat ou transportadora.</p>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($anuncio['descricao']): ?>
            <div class="descricao-completa">
                <div class="descricao-header"><h3><i class="fas fa-file-alt"></i> Descrição do Produto</h3></div>
                <div class="descricao-content"><p><?php echo nl2br(htmlspecialchars($anuncio['descricao'])); ?></p></div>
            </div>
            <?php endif; ?>

            <!-- Seção de Avaliações (ADICIONADA) -->
            <div class="avaliacoes-section">
                <div class="avaliacoes-header">
                    <?php if ($total_avaliacoes > 0): ?>
                        <div class="media-avaliacao">
                            <div class="numero-media"><?php echo $media_avaliacao; ?></div>
                            <div class="estrelas-media">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= floor($media_avaliacao)) {
                                        echo '<i class="fas fa-star estrela-cheia"></i>';
                                    } elseif ($i - 0.5 <= $media_avaliacao) {
                                        echo '<i class="fas fa-star-half-alt estrela-cheia"></i>';
                                    } else {
                                        echo '<i class="far fa-star estrela-vazia"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <div class="total-avaliacoes"><?php echo $total_avaliacoes; ?> <?php echo $total_avaliacoes === 1 ? 'avaliação' : 'avaliações'; ?></div>
                        </div>
                        <div class="avaliacoes-info">
                            <h3><i class="fas fa-comments"></i> Avaliações dos Clientes</h3>
                            <p>Veja o que os compradores acham deste produto</p>
                        </div>
                            <?php
                            // Mostrar link para avaliar produto apenas para usuários logados que compraram e são elegíveis
                            if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo') {
                                try {
                                    $usuario_logado = $_SESSION['usuario_id'];
                                    $sql_check = "SELECT p.opcao_frete, p.comprador_id FROM propostas p LEFT JOIN compradores c ON p.comprador_id = c.id WHERE p.produto_id = :produto_id AND (p.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) AND p.status = 'aceita' ORDER BY p.data_inicio DESC LIMIT 1";
                                    $stc = $conn->prepare($sql_check);
                                    $stc->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
                                    $stc->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                                    $stc->execute();
                                    $rowc = $stc->fetch(PDO::FETCH_ASSOC);
                                    $mostrar_avaliar = false;
                                    if ($rowc) {
                                        $op = $rowc['opcao_frete'] ?? null;
                                        if (in_array($op, ['vendedor','comprador'])) {
                                            $mostrar_avaliar = true;
                                        } elseif ($op === 'entregador') {
                                            // permitir somente se houver entrega concluída
                                            $sql_ent = "SELECT e.id FROM entregas e LEFT JOIN compradores c ON e.comprador_id = c.id WHERE e.produto_id = :produto_id AND (e.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') LIMIT 1";
                                            $ste = $conn->prepare($sql_ent);
                                            $ste->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
                                            $ste->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                                            $ste->execute();
                                            if ($ste->fetch(PDO::FETCH_ASSOC)) {
                                                $mostrar_avaliar = true;
                                            }
                                        }
                                    }

                                    if ($mostrar_avaliar) {
                                        echo '<div class="botao-avaliar">';
                                        echo '<a href="../avaliar.php?tipo=produto&produto_id='.urlencode($anuncio_id).'" class="btn-avaliar">';
                                        echo '<i class="fas fa-star"></i>Avaliar este produto';
                                        echo '</a>';
                                        echo '</div>';
                                    }
                                } catch (Exception $e) {
                                    // não bloquear a exibição do anúncio em caso de erro na verificação
                                    error_log("Erro verificação avaliação: " . $e->getMessage());
                                }
                            }
                            ?>
                    <?php else: ?>
                        <div class="avaliacoes-info">
                            <h3><i class="fas fa-comments"></i> Avaliações dos Clientes</h3>
                            <p>Este produto ainda não tem avaliações. Seja o primeiro a avaliar!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($avaliacoes_limitadas)): ?>
                    <div class="avaliacoes-lista">
                        <?php foreach ($avaliacoes_limitadas as $av): ?>
                            <div class="avaliacao-item">
                                <div class="avaliacao-header">
                                    <div>
                                        <div class="avaliador-nome">
                                            <?php echo htmlspecialchars($av['nome'] ?? 'Comprador Anônimo'); ?>
                                        </div>
                                        <div class="avaliacao-data">
                                            <?php 
                                            $data = new DateTime($av['data_criacao']);
                                            echo $data->format('d/m/Y');
                                            ?>
                                        </div>
                                    </div>
                                    <div class="avaliacao-nota">
                                        <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= (int)$av['nota']) {
                                                echo '<i class="fas fa-star estrela-cheia"></i>';
                                            } else {
                                                echo '<i class="far fa-star estrela-vazia"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php if (!empty($av['comentario'])): ?>
                                    <div class="avaliacao-comentario">
                                        <?php echo nl2br(htmlspecialchars($av['comentario'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($total_avaliacoes > 3): ?>
                        <div class="ver-mais-avaliacoes">
                            <a href="../avaliacoes.php?tipo=produto&id=<?php echo $anuncio_id; ?>" class="btn-ver-mais">
                                <i class="fas fa-eye"></i> Ver todas as <?php echo $total_avaliacoes; ?> avaliações
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sem-avaliacoes">
                        <div><i class="fas fa-star"></i></div>
                        <p>Nenhuma avaliação ainda. Compre este produto e deixe sua avaliação!</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="proposta-section" id="proposta-section">
                <div class="section-header"><h3><i class="fas fa-handshake"></i> Fazer Proposta</h3><p>Preencha os detalhes</p></div>
                <form action="processar_proposta.php" method="POST" class="proposta-form">
                    <input type="hidden" name="produto_id" value="<?php echo $anuncio_id; ?>">
                    <input type="hidden" name="comprador_id" value="<?php echo $comprador_id; ?>">
                    <input type="hidden" name="usuario_tipo" value="<?php echo $usuario_tipo; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="preco_proposto"><i class="fas fa-tag"></i> Preço Proposto</label>
                            <div class="input-with-symbol">
                                <span class="currency-symbol">R$</span>
                                <input type="number" id="preco_proposto" name="preco_proposto" step="0.01" min="0.01" required value="<?php echo number_format($info_desconto['preco_final'], 2, '.', ''); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="quantidade_proposta"><i class="fas fa-box"></i> Quantidade</label>
                            <input type="number" id="quantidade_proposta" name="quantidade_proposta" min="1" max="<?php echo max(1, (int)$anuncio['quantidade_disponivel']); ?>" required value="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="condicoes"><i class="fas fa-file-alt"></i> Condições (Opcional)</label>
                        <textarea id="condicoes" name="condicoes" rows="4"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Enviar Proposta</button>
                        <button type="button" class="btn btn-secondary" id="btn-cancelar-proposta"><i class="fas fa-times"></i> Cancelar</button>
                    </div>
                </form>
            </div>

            <?php if (!empty($produtos_relacionados)): ?>
            <div class="produtos-relacionados">
                <div class="section-header"><h3><i class="fas fa-star"></i> Outros Anúncios</h3><p>Produtos que podem te interessar</p></div>
                <div class="anuncios-grid">
                    <?php foreach ($produtos_relacionados as $produto): 
                        $desc_rel = calcularDesconto($produto['preco'], $produto['preco_desconto'], $produto['desconto_data_fim']);
                        $imagem_produto = $produto['imagem_url'] ? htmlspecialchars($produto['imagem_url']) : '../../img/placeholder.png';
                    ?>
                        <div class="anuncio-card <?php echo $desc_rel['ativo'] ? 'card-desconto' : ''; ?>">
                            <a href="view_ad.php?anuncio_id=<?php echo $produto['id']; ?>" class="produto-link">
                                <div class="card-image">
                                    <?php if ($desc_rel['ativo']): ?><div class="badge-desconto">-<?php echo $desc_rel['porcentagem']; ?>%</div><?php endif; ?>
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
                                                <div class="price price-desconto">R$ <?php echo number_format($desc_rel['preco_final'], 2, ',', '.'); ?><span>/<?php echo htmlspecialchars($produto['unidade_medida']); ?></span></div>
                                            <?php else: ?>
                                                <p class="price">R$ <?php echo number_format($desc_rel['preco_final'], 2, ',', '.'); ?><span>/<?php echo htmlspecialchars($produto['unidade_medida']); ?></span></p>
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
                        <div class="social-links">
                            <a href="#">Instagram</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-bottom"><p>&copy; Encontre o Campo. Todos os direitos reservados.</p></div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector(".hamburger");
            const navMenu = document.querySelector(".nav-menu");
            hamburger.addEventListener("click", () => { hamburger.classList.toggle("active"); navMenu.classList.toggle("active"); });
            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => { hamburger.classList.remove("active"); navMenu.classList.remove("active"); }));

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
            const precoUnitario = <?php echo $preco_unitario; ?>;

            propostaSection.style.display = 'none';
            function formatarValor(valor) { return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
            function atualizarValorTotal() {
                const quantidade = parseInt(quantidadeInput.value);
                if (quantidade && quantidade > 0) {
                    const valorTotal = precoUnitario * quantidade;
                    const valorUnitarioFormatado = formatarValor(precoUnitario);
                    const valorTotalFormatado = formatarValor(valorTotal);
                    if (quantidade === 1) {
                        precoAtualElement.textContent = `R$ ${valorUnitarioFormatado}`;
                        valorTotalLabel.style.display = 'none';
                    } else {
                        precoAtualElement.textContent = `R$ ${valorTotalFormatado}`;
                        valorTotalLabel.style.display = 'block';
                        valorTotalElement.textContent = `R$ ${valorTotalFormatado}`;
                    }
                    if (quantidadeProposta) quantidadeProposta.value = quantidade;
                    valorUnitarioElement.textContent = `Preço unitário: R$ ${valorUnitarioFormatado} por ${'<?php echo $unidade; ?>'}`;
                    if (precoPropostoInput.value == <?php echo $preco_unitario; ?>) precoPropostoInput.value = precoUnitario.toFixed(2);
                }
            }
            atualizarValorTotal();

            decreaseBtn.addEventListener('click', () => { if (quantidadeInput.value > 1) { quantidadeInput.value = parseInt(quantidadeInput.value) - 1; atualizarValorTotal(); } });
            increaseBtn.addEventListener('click', () => { const max = parseInt(quantidadeInput.max); if (quantidadeInput.value < max) { quantidadeInput.value = parseInt(quantidadeInput.value) + 1; atualizarValorTotal(); } });
            quantidadeInput.addEventListener('change', () => { let value = parseInt(quantidadeInput.value); const max = parseInt(quantidadeInput.max); const min = parseInt(quantidadeInput.min); if (value < min) value = min; if (value > max) value = max; quantidadeInput.value = value; atualizarValorTotal(); });
            quantidadeInput.addEventListener('input', () => { atualizarValorTotal(); });
            
            if (quantidadeProposta) {
                quantidadeProposta.addEventListener('change', () => { let value = parseInt(quantidadeProposta.value); const max = parseInt(quantidadeProposta.max); const min = parseInt(quantidadeProposta.min); if (value < min) value = min; if (value > max) value = max; quantidadeProposta.value = value; quantidadeInput.value = value; atualizarValorTotal(); });
                quantidadeProposta.addEventListener('input', () => { quantidadeInput.value = quantidadeProposta.value; atualizarValorTotal(); });
            }

            btnCancelarProposta.addEventListener('click', () => { propostaSection.classList.remove('show'); setTimeout(() => { propostaSection.style.display = 'none'; }, 300); });
            if (btnCompartilhar) { btnCompartilhar.addEventListener('click', function() { navigator.clipboard.writeText(window.location.href).then(() => { alert('Link copiado!'); }); }); }
            
            // Carrossel
            const carrosselSlides = document.getElementById('carrossel-slides');
            const carrosselPrev = document.getElementById('carrossel-prev');
            const carrosselNext = document.getElementById('carrossel-next');
            const carrosselCounter = document.getElementById('carrossel-counter');
            const carrosselIndicators = document.querySelectorAll('.carrossel-indicator');
            const carrosselMiniaturas = document.querySelectorAll('.miniatura');
            const totalSlides = <?php echo count($imagens_produto); ?>;
            let currentSlide = 0;
            
            function updateCarrossel() {
                carrosselSlides.style.transform = `translateX(-${currentSlide * 100}%)`;
                if (carrosselCounter) carrosselCounter.textContent = `${currentSlide + 1}/${totalSlides}`;
                carrosselIndicators.forEach((ind, i) => { if (i === currentSlide) ind.classList.add('active'); else ind.classList.remove('active'); });
                carrosselMiniaturas.forEach((min, i) => { if (i === currentSlide) { min.classList.add('active'); min.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }); } else min.classList.remove('active'); });
                if (carrosselPrev) carrosselPrev.disabled = currentSlide === 0;
                if (carrosselNext) carrosselNext.disabled = currentSlide === totalSlides - 1;
            }
            
            if (totalSlides > 1 && carrosselPrev && carrosselNext) {
                carrosselPrev.addEventListener('click', () => { if (currentSlide > 0) { currentSlide--; updateCarrossel(); } });
                carrosselNext.addEventListener('click', () => { if (currentSlide < totalSlides - 1) { currentSlide++; updateCarrossel(); } });
                carrosselIndicators.forEach(ind => { ind.addEventListener('click', () => { const i = parseInt(ind.getAttribute('data-index')); if (i !== currentSlide) { currentSlide = i; updateCarrossel(); } }); });
                carrosselMiniaturas.forEach(min => { min.addEventListener('click', () => { const i = parseInt(min.getAttribute('data-index')); if (i !== currentSlide) { currentSlide = i; updateCarrossel(); } }); });
                document.addEventListener('keydown', (e) => { if (e.key === 'ArrowLeft') { if (currentSlide > 0) { currentSlide--; updateCarrossel(); } } else if (e.key === 'ArrowRight') { if (currentSlide < totalSlides - 1) { currentSlide++; updateCarrossel(); } } });
                updateCarrossel();
            }
        });
    </script>
</body>
</html>