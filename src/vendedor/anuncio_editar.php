<?php
// src/vendedor/anuncio_editar.php (VERSÃO CORRIGIDA: Loading e Top Image + Desconto + Estrela para definir capa)
require_once 'auth.php'; 

$mensagem_sucesso = '';
$mensagem_erro = '';
$vendedor_id_fk = $vendedor['id'];
$anuncio = null;

$anuncio_id = sanitizeInput($_REQUEST['id'] ?? $_POST['anuncio_id'] ?? null);

if (!$anuncio_id) {
    header("Location: anuncios.php");
    exit();
}

// 1. Carregamento
try {
    $query_anuncio = "SELECT * FROM produtos WHERE id = :anuncio_id AND vendedor_id = :vendedor_id";
    $stmt_anuncio = $db->prepare($query_anuncio);
    $stmt_anuncio->bindParam(':anuncio_id', $anuncio_id);
    $stmt_anuncio->bindParam(':vendedor_id', $vendedor_id_fk);
    $stmt_anuncio->execute();
    $anuncio = $stmt_anuncio->fetch(PDO::FETCH_ASSOC);

    if (!$anuncio) {
        header("Location: anuncios.php");
        exit();
    }
    
    // Buscar imagens do produto ordenadas
    $query_imagens = "SELECT * FROM produto_imagens WHERE produto_id = :produto_id ORDER BY ordem ASC";
    $stmt_imagens = $db->prepare($query_imagens);
    $stmt_imagens->bindParam(':produto_id', $anuncio_id);
    $stmt_imagens->execute();
    $imagens = $stmt_imagens->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header("Location: anuncios.php");
    exit();
}

// 2. Atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $preco = sanitizeInput($_POST['preco']);
    $categoria = sanitizeInput($_POST['categoria']);
    $estoque = sanitizeInput($_POST['estoque']);
    $status = sanitizeInput($_POST['status']);
    $modo_precificacao = sanitizeInput($_POST['modo_precificacao'] ?? 'por_quilo');
    $quantidade_embalagem = sanitizeInput($_POST['quantidade_embalagem'] ?? '');
    $paletizado = isset($_POST['paletizado']) ? 1 : 0;
    
    // Dados de desconto
    $desconto_ativo = isset($_POST['desconto_ativo']) ? 1 : 0;
    $tipo_desconto = sanitizeInput($_POST['tipo_desconto'] ?? 'percentual');
    $desconto_valor = sanitizeInput($_POST['desconto_valor'] ?? 0);
    $desconto_data_inicio = sanitizeInput($_POST['desconto_data_inicio'] ?? null);
    $desconto_data_fim = sanitizeInput($_POST['desconto_data_fim'] ?? null);
    
    // Tratamento de preço e estoque
    $preco_db = str_replace(',', '.', $preco);
    $estoque_db = (int)$estoque;
    $desconto_valor_db = str_replace(',', '.', $desconto_valor);

    // Validação básica
    if (empty($nome) || empty($preco)) {
        $mensagem_erro = "Preencha os campos obrigatórios.";
    }

    // Validação do desconto
    if ($desconto_ativo) {
        if (empty($desconto_valor)) {
            $mensagem_erro = "Por favor, informe o valor do desconto.";
        } elseif ($tipo_desconto === 'percentual' && ($desconto_valor_db <= 0 || $desconto_valor_db > 100)) {
            $mensagem_erro = "O desconto percentual deve ser entre 0.01% e 100%.";
        } elseif ($tipo_desconto === 'valor' && ($desconto_valor_db <= 0 || $desconto_valor_db >= $preco_db)) {
            $mensagem_erro = "O desconto em valor deve ser maior que zero e menor que o preço original.";
        }
        
        // Validação das datas
        if ($desconto_data_inicio && $desconto_data_fim) {
            $inicio = DateTime::createFromFormat('Y-m-d', $desconto_data_inicio);
            $fim = DateTime::createFromFormat('Y-m-d', $desconto_data_fim);
            
            if ($inicio > $fim) {
                $mensagem_erro = "A data de início do desconto não pode ser posterior à data de fim.";
            }
        }
    }

    if (empty($mensagem_erro)) {
        try {
            $db->beginTransaction();
            
            // Cálculo do desconto
            $desconto_percentual = 0;
            $desconto_ativo_db = $desconto_ativo;
            $preco_desconto_db = null;
            
            if ($desconto_ativo && $desconto_valor_db > 0) {
                if ($tipo_desconto === 'percentual') {
                    $desconto_percentual = $desconto_valor_db;
                    $preco_desconto_db = $preco_db * (1 - ($desconto_percentual / 100));
                } else {
                    $desconto_percentual = ($desconto_valor_db / $preco_db) * 100;
                    $preco_desconto_db = $preco_db - $desconto_valor_db;
                }
            } else {
                $desconto_ativo_db = 0;
                $desconto_percentual = 0;
                $preco_desconto_db = null;
            }
            
            // Converter datas para formato MySQL
            $desconto_data_inicio_db = $desconto_data_inicio ? $desconto_data_inicio . ' 00:00:00' : null;
            $desconto_data_fim_db = $desconto_data_fim ? $desconto_data_fim . ' 23:59:59' : null;
            
            if (!$desconto_ativo_db) {
                $desconto_data_inicio_db = null;
                $desconto_data_fim_db = null;
                $preco_desconto_db = null;
            }
            
            // Definir campos relacionados à embalagem/estoque conforme modo de precificação
            $embalagem_peso_kg = null;
            $embalagem_unidades = null;
            $estoque_unidades = null;
            $unidade_medida = 'kg'; // padrão

            switch ($modo_precificacao) {
                case 'por_unidade':
                    $unidade_medida = 'unidade';
                    $estoque_unidades = (int)$estoque_db;
                    $embalagem_unidades = $quantidade_embalagem ? (int)$quantidade_embalagem : null;
                    break;
                case 'por_quilo':
                    $unidade_medida = 'kg';
                    $estoque_db = (float)str_replace(',', '.', $estoque_db);
                    break;
                case 'caixa_unidades':
                    $unidade_medida = 'caixa';
                    $embalagem_unidades = $quantidade_embalagem ? (int)$quantidade_embalagem : null;
                    $estoque_unidades = (int)$estoque_db;
                    break;
                case 'caixa_quilos':
                    $unidade_medida = 'caixa';
                    $embalagem_peso_kg = $quantidade_embalagem ? (float)str_replace(',', '.', $quantidade_embalagem) : null;
                    $estoque_db = (float)str_replace(',', '.', $estoque_db);
                    break;
                case 'saco_unidades':
                    $unidade_medida = 'saco';
                    $embalagem_unidades = $quantidade_embalagem ? (int)$quantidade_embalagem : null;
                    $estoque_unidades = (int)$estoque_db;
                    break;
                case 'saco_quilos':
                    $unidade_medida = 'saco';
                    $embalagem_peso_kg = $quantidade_embalagem ? (float)str_replace(',', '.', $quantidade_embalagem) : null;
                    $estoque_db = (float)str_replace(',', '.', $estoque_db);
                    break;
                default:
                    $unidade_medida = 'kg';
                    $estoque_db = (float)str_replace(',', '.', $estoque_db);
            }

            // Update Dados Básicos incluindo desconto e campos de embalagem
            // Garantir que a coluna `paletizado` existe
            try {
                $col = $db->query("SHOW COLUMNS FROM produtos LIKE 'paletizado'")->fetch(PDO::FETCH_ASSOC);
                if (!$col) {
                    $db->exec("ALTER TABLE produtos ADD COLUMN paletizado TINYINT(1) NOT NULL DEFAULT 0");
                }
            } catch (Exception $e) {
            }

            $query = "UPDATE produtos SET 
                        nome=:n, 
                        descricao=:d, 
                        preco=:p, 
                        preco_desconto=:pd,
                        categoria=:c, 
                        estoque=:e, 
                        estoque_unidades=:eu,
                        status=:s,
                        desconto_ativo=:da,
                        desconto_percentual=:dp,
                        desconto_data_inicio=:ddi,
                        desconto_data_fim=:ddf,
                        modo_precificacao=:mp,
                        embalagem_peso_kg=:epk,
                        embalagem_unidades=:euq,
                                                unidade_medida=:um,
                                                paletizado=:pal,
                                                data_atualizacao=NOW() 
                      WHERE id=:id AND vendedor_id=:vid";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':n' => $nome, 
                ':d' => $descricao, 
                ':p' => $preco_db, 
                ':pd' => $preco_desconto_db,
                ':c' => $categoria, 
                ':e' => $estoque_db, 
                ':eu' => $estoque_unidades,
                ':s' => $status,
                ':da' => $desconto_ativo_db,
                ':dp' => $desconto_percentual,
                ':ddi' => $desconto_data_inicio_db,
                ':ddf' => $desconto_data_fim_db,
                ':mp' => $modo_precificacao,
                ':epk' => $embalagem_peso_kg,
                ':euq' => $embalagem_unidades,
                ':um' => $unidade_medida,
                ':pal' => $paletizado,
                ':id' => $anuncio_id, 
                ':vid' => $vendedor_id_fk
            ]);

            // --- PROCESSAMENTO DE IMAGENS ---
            
            // 1. Remover Imagens Deletadas
            if (!empty($_POST['imagens_removidas'])) {
                $removidas = json_decode($_POST['imagens_removidas'], true);
                if (is_array($removidas)) {
                    foreach ($removidas as $img_id) {
                        $stmt_path = $db->prepare("SELECT imagem_url FROM produto_imagens WHERE id = :id AND produto_id = :pid");
                        $stmt_path->execute([':id' => $img_id, ':pid' => $anuncio_id]);
                        $path = $stmt_path->fetchColumn();
                        
                        if ($path) {
                            $db->prepare("DELETE FROM produto_imagens WHERE id = :id")->execute([':id' => $img_id]);
                            if (file_exists($path)) @unlink($path);
                        }
                    }
                }
            }

            // 2. Upload Novas Imagens
            $upload_dir = '../uploads/produtos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $mapa_novas_imagens = [];
            
            if (!empty($_FILES['novas_imagens'])) {
                $total = count($_FILES['novas_imagens']['name']);
                for ($i=0; $i < $total; $i++) {
                    if ($_FILES['novas_imagens']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp = $_FILES['novas_imagens']['tmp_name'][$i];
                        $ext = strtolower(pathinfo($_FILES['novas_imagens']['name'][$i], PATHINFO_EXTENSION));
                        $new_name = uniqid('prod_', true) . '.' . $ext;
                        
                        if (move_uploaded_file($tmp, $upload_dir . $new_name)) {
                            $stmt_ins = $db->prepare("INSERT INTO produto_imagens (produto_id, imagem_url, ordem) VALUES (:pid, :url, 999)");
                            $stmt_ins->execute([':pid' => $anuncio_id, ':url' => $upload_dir . $new_name]);
                            $new_db_id = $db->lastInsertId();
                            
                            $mapa_novas_imagens[] = ['id' => $new_db_id, 'url' => $upload_dir . $new_name];
                        }
                    }
                }
            }

            // 3. Reordenar TUDO e Definir Principal
            if (!empty($_POST['nova_ordem'])) {
                $ordem_json = json_decode($_POST['nova_ordem'], true);
                
                $contador_novas = 0;
                $primeira_img_url = null;
                
                foreach ($ordem_json as $index => $item) {
                    if ($item['type'] === 'existente') {
                        $db->prepare("UPDATE produto_imagens SET ordem = :o WHERE id = :id AND produto_id = :pid")
                           ->execute([':o' => $index, ':id' => $item['id'], ':pid' => $anuncio_id]);
                           
                        if ($index === 0) {
                            $stmt_url = $db->prepare("SELECT imagem_url FROM produto_imagens WHERE id = :id");
                            $stmt_url->execute([':id' => $item['id']]);
                            $primeira_img_url = $stmt_url->fetchColumn();
                        }
                    } 
                    elseif ($item['type'] === 'nova') {
                        if (isset($mapa_novas_imagens[$contador_novas])) {
                            $dados_nova = $mapa_novas_imagens[$contador_novas];
                            $db->prepare("UPDATE produto_imagens SET ordem = :o WHERE id = :id")
                               ->execute([':o' => $index, ':id' => $dados_nova['id']]);
                            
                            if ($index === 0) $primeira_img_url = $dados_nova['url'];
                            
                            $contador_novas++;
                        }
                    }
                }
                
                // Atualizar imagem principal no produto
                if ($primeira_img_url) {
                    $db->prepare("UPDATE produtos SET imagem_url = :url WHERE id = :id")->execute([':url' => $primeira_img_url, ':id' => $anuncio_id]);
                }
            }

            $db->commit();
            $mensagem_sucesso = "Anúncio atualizado!";
            
            // Recarregar dados
            $stmt_anuncio->execute();
            $anuncio = $stmt_anuncio->fetch(PDO::FETCH_ASSOC);
            $stmt_imagens->execute();
            $imagens = $stmt_imagens->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $mensagem_erro = "Erro: " . $e->getMessage();
        }
    }
}

// Formatação dos dados para exibição
$preco_formatado = number_format($anuncio['preco'], 2, ',', ''); 

// Formatar dados de desconto para exibição
$desconto_valor_formatado = '';
if ($anuncio['desconto_ativo'] && $anuncio['desconto_percentual'] > 0) {
    $desconto_valor_formatado = number_format($anuncio['desconto_percentual'], 2, ',', '');
}

$desconto_data_inicio_formatada = $anuncio['desconto_data_inicio'] ? date('Y-m-d', strtotime($anuncio['desconto_data_inicio'])) : '';
$desconto_data_fim_formatada = $anuncio['desconto_data_fim'] ? date('Y-m-d', strtotime($anuncio['desconto_data_fim'])) : '';

// Determinar tipo de desconto atual para exibição
$tipo_desconto_atual = 'percentual';
$preco_com_desconto = $anuncio['preco_desconto'] ?? $anuncio['preco'];
if ($anuncio['desconto_ativo'] && $anuncio['desconto_percentual'] > 0 && !$anuncio['preco_desconto']) {
    $preco_com_desconto = $anuncio['preco'] * (1 - ($anuncio['desconto_percentual'] / 100));
}

// Categorias disponíveis
$categorias_disponiveis = [
    'Frutas Cítricas',
    'Frutas Tropicais',
    'Frutas de Caroço',
    'Frutas Vermelhas',
    'Frutas Secas',
    'Frutas Exóticas',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Anúncio</title>
    <link rel="stylesheet" href="../css/vendedor/anuncio_editar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <style>
        /* CSS FIX: Loading deve ser none por padrão */
        .loading-overlay {
            display: none; 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.9); z-index: 9999;
            align-items: center; justify-content: center; flex-direction: column;
        }
        .spinner {
            border: 4px solid #f3f3f3; border-top: 4px solid #4CAF50;
            border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .foto-produto-display {
            width: 100%; height: 300px;
            background: #f5f5f5; border: 2px solid #ddd; border-radius: 8px;
            overflow: hidden; position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .foto-produto-display img { width: 100%; height: 100%; object-fit: cover; }
        
        /* Remover o hover antigo do lápis */
        .foto-overlay { display: none !important; }

        .galeria-imagens { margin-top: 20px; border: 2px dashed #ddd; padding: 20px; border-radius: 8px; }
        .imagens-preview { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 20px; }
        
        .imagem-item {
            position: relative; width: 120px; height: 120px;
            border: 2px solid #ddd; border-radius: 6px; overflow: hidden;
            cursor: move; background: #fff;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .imagem-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .imagem-item.dragging { opacity: 0.5; border-color: #2196F3; }
        .imagem-item img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
        
        .btn-acao {
            position: absolute; width: 25px; height: 25px; border-radius: 50%;
            border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;
            font-size: 10px; color: white;
            transition: all 0.2s ease;
        }
        .btn-acao:hover {
            transform: scale(1.1);
        }
        .btn-acao.principal { top: 5px; right: 35px; background: rgba(0,0,0,0.6); }
        .btn-acao.principal.active { background: #FFD700; color: #000; }
        .btn-acao.principal.active i { color: #000 !important; }
        .btn-acao.deleting { top: 5px; right: 5px; background: #f44336; }
        
        /* Estilos para seção de desconto */
        .desconto-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .desconto-toggle {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
            margin-right: 10px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #4CAF50;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .tipo-desconto {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .tipo-desconto label {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .desconto-fields {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .data-fields {
            display: flex;
            gap: 20px;
        }
        
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .preco-com-desconto {
            padding: 8px;
            background: #e8f5e9;
            border-radius: 4px;
            margin-top: 5px;
            font-weight: bold;
        }
        
        /* Botão flutuante para mobile */
        .floating-back-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #4CAF50;
            color: white;
            border: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        /* Estilo para feedback visual quando define como capa */
        @keyframes highlightStar {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }
        
        .star-highlight {
            animation: highlightStar 0.5s ease;
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
                    <li class="nav-item"><a href="anuncios.php" class="nav-link">Voltar</a></li>
                </ul>
            </div>
        </nav>
    </header>
    <br>

    <div class="main-content">
        <center><header class="header"><h1>Editar: <?php echo htmlspecialchars($anuncio['nome']); ?></h1></header></center>

        <section class="form-section">
            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="alert success-alert"><i class="fas fa-check"></i> <?php echo $mensagem_sucesso; ?></div>
            <?php endif; ?>
            
            <div class="loading-overlay" id="loadingOverlay">
                <div class="spinner"></div>
                <p>Salvando alterações...</p>
            </div>

            <form method="POST" action="anuncio_editar.php" class="anuncio-form" enctype="multipart/form-data" id="anuncioForm">
                <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                <input type="hidden" id="imagens_removidas" name="imagens_removidas" value="">
                <input type="hidden" id="nova_ordem" name="nova_ordem" value="">
                
                <div class="forms-area">
                    <div class="top-info">
                        <div class="form-group">
                            <div class="foto-produto-container">
                                <div class="foto-produto-display" id="imagemPrincipalPreview">
                                    <?php if (!empty($anuncio['imagem_url']) && file_exists($anuncio['imagem_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($anuncio['imagem_url']); ?>" alt="Imagem do Anúncio">
                                    <?php else: ?>
                                        <div class="default-image"><i class="fas fa-image" style="font-size: 3rem; color: #ccc;"></i></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="prod-info">
                            <h2>Informações</h2>
                            <div class="form-group">
                                <label for="nome" class="required">Nome</label>
                                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($anuncio['nome']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="categoria">Categoria</label>
                                <select id="categoria" name="categoria">
                                    <?php foreach ($categorias_disponiveis as $cat): ?>
                                        <option value="<?php echo $cat; ?>" <?php echo ($anuncio['categoria'] == $cat)?'selected':''; ?>>
                                            <?php echo $cat; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Imagens do Produto</label>
                        <div class="galeria-imagens">
                            <div class="galeria-header">
                                <span class="contador-imagens" id="contadorImagens"><?php echo count($imagens); ?> imagens</span>
                            </div>
                            
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-plus"></i> Adicionar Mais Fotos
                            </div>
                            
                            <div class="imagens-preview" id="imagensPreview">
                                </div>
                            
                            <input type="file" id="novas_imagens" name="novas_imagens[]" accept="image/*" multiple style="display: none;">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="modo_precificacao">Modo de Precificação</label>
                            <select id="modo_precificacao" name="modo_precificacao">
                                <option value="por_quilo" <?php echo ($anuncio['modo_precificacao'] === 'por_quilo' || empty($anuncio['modo_precificacao'])) ? 'selected' : ''; ?>>Por quilo</option>
                                <option value="por_unidade" <?php echo ($anuncio['modo_precificacao'] === 'por_unidade') ? 'selected' : ''; ?>>Por unidade</option>
                                <option value="caixa_unidades" <?php echo ($anuncio['modo_precificacao'] === 'caixa_unidades') ? 'selected' : ''; ?>>Por caixa com X unidades</option>
                                <option value="caixa_quilos" <?php echo ($anuncio['modo_precificacao'] === 'caixa_quilos') ? 'selected' : ''; ?>>Por caixa com X quilos</option>
                                <option value="saco_unidades" <?php echo ($anuncio['modo_precificacao'] === 'saco_unidades') ? 'selected' : ''; ?>>Por saco com X unidades</option>
                                <option value="saco_quilos" <?php echo ($anuncio['modo_precificacao'] === 'saco_quilos') ? 'selected' : ''; ?>>Por Saco com X quilos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="preco" class="required">Preço</label>
                            <input type="text" id="preco" name="preco" value="<?php echo htmlspecialchars($preco_formatado); ?>" required>
                            <?php if ($anuncio['desconto_ativo'] && $anuncio['desconto_percentual'] > 0): ?>
                                <div class="preco-com-desconto">
                                    Preço com desconto: R$ <?php echo number_format($preco_com_desconto, 2, ',', ''); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="estoque" class="required">Estoque</label>
                            <input type="number" id="estoque" name="estoque" value="<?php echo htmlspecialchars($anuncio['estoque']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="quantidade_embalagem" id="labelQuantidadeEmb">Quantidade por embalagem (se aplicável)</label>
                        <input type="text" id="quantidade_embalagem" name="quantidade_embalagem" value="<?php echo htmlspecialchars(!empty($anuncio['embalagem_unidades']) ? $anuncio['embalagem_unidades'] : (!empty($anuncio['embalagem_peso_kg']) ? $anuncio['embalagem_peso_kg'] : '')); ?>" placeholder="Ex: 10 ou 5,5">
                    </div>

                    <div class="form-group">
                        <label for="paletizado">Paletizado</label>
                        <label class="checkbox-inline">
                            <input type="checkbox" id="paletizado" name="paletizado" value="1" <?php echo !empty($anuncio['paletizado']) ? 'checked' : ''; ?>> Produto será paletizado
                        </label>
                        <div class="help-text">Marque se este produto é paletizado.</div>
                    </div>
                    
                    <!-- SEÇÃO DE DESCONTO -->
                    <div class="desconto-section">
                        <h2>Configuração de Desconto</h2>
                        
                        <div class="desconto-toggle">
                            <label class="toggle-switch">
                                <input type="checkbox" id="desconto_ativo" name="desconto_ativo" value="1" <?php echo $anuncio['desconto_ativo'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <label for="desconto_ativo">Ativar desconto para este produto</label>
                        </div>

                        <div id="campos-desconto" style="<?php echo $anuncio['desconto_ativo'] ? '' : 'display: none;'; ?>">
                            <div class="tipo-desconto">
                                <label>
                                    <input type="radio" name="tipo_desconto" value="percentual" <?php echo ($tipo_desconto_atual === 'percentual') ? 'checked' : ''; ?>>
                                    Desconto Percentual
                                </label>
                                <label>
                                    <input type="radio" name="tipo_desconto" value="valor" <?php echo ($tipo_desconto_atual === 'valor') ? 'checked' : ''; ?>>
                                    Desconto em Valor
                                </label>
                            </div>

                            <div class="desconto-fields">
                                <div class="form-group">
                                    <label for="desconto_valor" class="required">Valor do Desconto</label>
                                    <input type="text" id="desconto_valor" name="desconto_valor" value="<?php echo htmlspecialchars($desconto_valor_formatado); ?>" placeholder="<?php echo ($tipo_desconto_atual === 'percentual') ? 'Ex: 15,00' : 'Ex: 2,50'; ?>">
                                    <span class="help-text" id="desconto-help">
                                        <?php echo ($tipo_desconto_atual === 'percentual') ? 'Porcentagem (Ex: 15,00 = 15%)' : 'Valor em reais (Ex: 2,50 = R$ 2,50)'; ?>
                                    </span>
                                </div>
                                
                                <div class="form-group">
                                    <label>Desconto Calculado</label>
                                    <div id="desconto-calculado" class="preco-com-desconto">
                                        <?php if ($anuncio['desconto_ativo'] && $anuncio['desconto_percentual'] > 0): ?>
                                            Preço final: R$ <?php echo number_format($preco_com_desconto, 2, ',', ''); ?>
                                        <?php else: ?>
                                            Insira os valores para calcular
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="data-fields">
                                <div class="form-group">
                                    <label for="desconto_data_inicio">Data de Início</label>
                                    <input type="date" id="desconto_data_inicio" name="desconto_data_inicio" value="<?php echo htmlspecialchars($desconto_data_inicio_formatada); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="desconto_data_fim">Data de Fim</label>
                                    <input type="date" id="desconto_data_fim" name="desconto_data_fim" value="<?php echo htmlspecialchars($desconto_data_fim_formatada); ?>">
                                </div>
                            </div>
                            
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i> Deixe as datas em branco para desconto permanente.
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="ativo" <?php echo ($anuncio['status'] === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo ($anuncio['status'] === 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <textarea id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($anuncio['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="big-button"><i class="fas fa-save"></i> Salvar Alterações</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        const imagensIniciais = <?php 
            $imgs_json = array_map(function($img) {
                return [
                    'id' => $img['id'],
                    'url' => $img['imagem_url'],
                    'type' => 'existente'
                ];
            }, $imagens);
            echo json_encode($imgs_json); 
        ?>;
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // FIX: Garantir que loading está oculto ao carregar
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.display = 'none';

            const previewContainer = document.getElementById('imagensPreview');
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('novas_imagens');
            const removedInput = document.getElementById('imagens_removidas');
            const orderInput = document.getElementById('nova_ordem');
            const topImageContainer = document.getElementById('imagemPrincipalPreview');
            const anuncioForm = document.getElementById('anuncioForm');
            const contadorImagens = document.getElementById('contadorImagens');

            let allImages = [...imagensIniciais];
            let removedIds = [];

            // Renderizar inicial
            renderGallery();

            // Eventos Upload
            uploadArea.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function(e) {
                const files = Array.from(this.files);
                files.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = (evt) => {
                        allImages.push({
                            id: 'temp_' + Date.now() + '_' + index,
                            url: evt.target.result,
                            type: 'nova',
                            file: file
                        });
                        renderGallery();
                        updateFileInput();
                    };
                    reader.readAsDataURL(file);
                });
            });

            // Função Renderizar
            function renderGallery() {
                previewContainer.innerHTML = '';
                contadorImagens.textContent = `${allImages.length} imagens`;

                if(allImages.length > 0) {
                    topImageContainer.innerHTML = `<img src="${allImages[0].url}" alt="Principal">`;
                } else {
                    topImageContainer.innerHTML = '<div class="default-image"><i class="fas fa-image" style="font-size:3rem"></i></div>';
                }

                allImages.forEach((img, index) => {
                    const div = document.createElement('div');
                    div.className = 'imagem-item';
                    div.draggable = true;
                    div.dataset.id = img.id;
                    div.dataset.index = index;
                    
                    div.innerHTML = `
                        <img src="${img.url}">
                        <button type="button" class="btn-acao principal ${index === 0 ? 'active' : ''}" onclick="setAsCover(${index})" title="${index === 0 ? 'Esta é a foto principal' : 'Definir como foto principal'}">
                            <i class="fas fa-star"></i>
                        </button>
                        <button type="button" class="btn-acao deleting" onclick="removeImg(${index})" title="Remover imagem">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    div.addEventListener('dragstart', dragStart);
                    div.addEventListener('dragover', dragOver);
                    div.addEventListener('drop', drop);
                    
                    previewContainer.appendChild(div);
                });

                const orderData = allImages.map(img => ({
                    type: img.type,
                    id: img.type === 'existente' ? img.id : 'temp'
                }));
                orderInput.value = JSON.stringify(orderData);
            }

            // Global Remove
            window.removeImg = (index) => {
                const img = allImages[index];
                if (img.type === 'existente') {
                    removedIds.push(img.id);
                    removedInput.value = JSON.stringify(removedIds);
                }
                allImages.splice(index, 1);
                renderGallery();
                updateFileInput();
            };

            // Global Set as Cover (NOVA FUNÇÃO)
            window.setAsCover = (index) => {
                if (index === 0) return; // Já é a principal
                
                // Move a imagem para a primeira posição
                const img = allImages.splice(index, 1)[0];
                allImages.unshift(img);
                
                // Renderiza novamente
                renderGallery();
                updateFileInput();
                
                // Feedback visual na estrela
                const starButtons = document.querySelectorAll('.btn-acao.principal');
                if (starButtons[0]) {
                    starButtons[0].classList.add('star-highlight');
                    setTimeout(() => {
                        starButtons[0].classList.remove('star-highlight');
                    }, 500);
                }
                
                // Feedback visual na imagem principal
                const topImage = topImageContainer.querySelector('img');
                if (topImage) {
                    topImage.style.opacity = '0.7';
                    setTimeout(() => {
                        topImage.style.opacity = '1';
                    }, 300);
                }
            };

            // Drag Functions
            let dragSrcEl = null;

            function dragStart(e) {
                dragSrcEl = this;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);
                this.classList.add('dragging');
            }

            function dragOver(e) {
                if (e.preventDefault) e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                return false;
            }

            function drop(e) {
                if (e.stopPropagation) e.stopPropagation();
                
                const srcIndex = parseInt(dragSrcEl.dataset.index);
                const targetIndex = parseInt(this.dataset.index);

                if (srcIndex !== targetIndex) {
                    const item = allImages.splice(srcIndex, 1)[0];
                    allImages.splice(targetIndex, 0, item);
                    renderGallery();
                    updateFileInput();
                }
                return false;
            }

            function updateFileInput() {
                const dt = new DataTransfer();
                allImages.forEach(img => {
                    if (img.type === 'nova' && img.file) {
                        dt.items.add(img.file);
                    }
                });
                fileInput.files = dt.files;
            }

            // Submit
            anuncioForm.addEventListener('submit', function() {
                loadingOverlay.style.display = 'flex';
            });

            // Script para desconto
            const precoInput = document.getElementById('preco');
            const descontoValorInput = document.getElementById('desconto_valor');
            const descontoAtivoCheckbox = document.getElementById('desconto_ativo');
            const camposDesconto = document.getElementById('campos-desconto');
            const tipoDescontoRadios = document.querySelectorAll('input[name="tipo_desconto"]');
            const descontoHelp = document.getElementById('desconto-help');
            const descontoCalculado = document.getElementById('desconto-calculado');

            // Script para modo de precificação
            const modoSelect = document.getElementById('modo_precificacao');
            const quantidadeEmbInput = document.getElementById('quantidade_embalagem');
            const estoqueInput = document.getElementById('estoque');
            
            // Criar labels dinâmicos se não existirem
            let labelPreco = document.querySelector('label[for="preco"]');
            let labelEstoque = document.querySelector('label[for="estoque"]');
            let labelQuantidadeEmb = document.querySelector('label[for="quantidade_embalagem"]');

            function updatePrecificacaoUI() {
                const modo = modoSelect.value;
                if (modo === 'por_quilo') {
                    if (labelPreco) labelPreco.textContent = 'Preço por Kg (R$)';
                    if (labelEstoque) labelEstoque.textContent = 'Estoque em Kg';
                    if (labelQuantidadeEmb) labelQuantidadeEmb.style.display = 'none';
                    quantidadeEmbInput.style.display = 'none';
                } else if (modo === 'por_unidade') {
                    if (labelPreco) labelPreco.textContent = 'Preço por Unidade (R$)';
                    if (labelEstoque) labelEstoque.textContent = 'Estoque em Unidades';
                    if (labelQuantidadeEmb) labelQuantidadeEmb.style.display = 'none';
                    quantidadeEmbInput.style.display = 'none';
                } else if (modo === 'caixa_unidades') {
                    if (labelPreco) labelPreco.textContent = 'Preço por Caixa (R$)';
                    if (labelEstoque) labelEstoque.textContent = 'Estoque em Caixas';
                    if (labelQuantidadeEmb) {
                        labelQuantidadeEmb.style.display = 'block';
                        labelQuantidadeEmb.textContent = 'Unidades por Caixa (ex: 10)';
                    }
                    quantidadeEmbInput.style.display = 'block';
                } else if (modo === 'caixa_quilos') {
                    if (labelPreco) labelPreco.textContent = 'Preço por Caixa (R$)';
                    if (labelEstoque) labelEstoque.textContent = 'Estoque em Caixas';
                    if (labelQuantidadeEmb) {
                        labelQuantidadeEmb.style.display = 'block';
                        labelQuantidadeEmb.textContent = 'Kg por Caixa (ex: 5,5)';
                    }
                    quantidadeEmbInput.style.display = 'block';
                } else if (modo === 'saco_unidades') {
                    if (labelPreco) labelPreco.textContent = 'Preço por Saco (R$)';
                    if (labelEstoque) labelEstoque.textContent = 'Estoque em Sacos';
                    if (labelQuantidadeEmb) {
                        labelQuantidadeEmb.style.display = 'block';
                        labelQuantidadeEmb.textContent = 'Unidades por Saco (ex: 10)';
                    }
                    quantidadeEmbInput.style.display = 'block';
                } else if (modo === 'saco_quilos') {
                    if (labelPreco) labelPreco.textContent = 'Preço por Saco (R$)';
                    if (labelEstoque) labelEstoque.textContent = 'Estoque em Sacos';
                    if (labelQuantidadeEmb) {
                        labelQuantidadeEmb.style.display = 'block';
                        labelQuantidadeEmb.textContent = 'Kg por Saco (ex: 5,5)';
                    }
                    quantidadeEmbInput.style.display = 'block';
                }
            }

            // Listener para mudanças no modo de precificação
            modoSelect.addEventListener('change', updatePrecificacaoUI);

            // Inicializar UI ao carregar a página
            updatePrecificacaoUI();

            // Máscara para preço
            precoInput.addEventListener('input', function(e) {
                let value = e.target.value;
                value = value.replace(/\D/g, '');
                
                if (value) {
                    value = (parseInt(value) / 100).toFixed(2);
                    value = value.replace('.', ',');
                }
                e.target.value = value;
                calcularDesconto();
            });

            // Máscara para valor do desconto
            descontoValorInput.addEventListener('input', function(e) {
                let value = e.target.value;
                value = value.replace(/\D/g, '');
                
                if (value) {
                    const isPercentual = document.querySelector('input[name="tipo_desconto"]:checked').value === 'percentual';
                    if (isPercentual) {
                        value = (parseInt(value) / 100).toFixed(2);
                    } else {
                        value = (parseInt(value) / 100).toFixed(2);
                    }
                    value = value.replace('.', ',');
                }
                e.target.value = value;
                calcularDesconto();
            });

            // Mostrar/ocultar campos de desconto
            descontoAtivoCheckbox.addEventListener('change', function() {
                camposDesconto.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    descontoCalculado.innerHTML = 'Insira os valores para calcular';
                    descontoValorInput.value = '';
                } else {
                    calcularDesconto();
                }
            });

            // Alterar tipo de desconto
            tipoDescontoRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'percentual') {
                        descontoHelp.textContent = 'Porcentagem (Ex: 15,00 = 15%)';
                        descontoValorInput.placeholder = 'Ex: 15,00';
                    } else {
                        descontoHelp.textContent = 'Valor em reais (Ex: 2,50 = R$ 2,50)';
                        descontoValorInput.placeholder = 'Ex: 2,50';
                    }
                    descontoValorInput.value = '';
                    descontoCalculado.innerHTML = 'Insira os valores para calcular';
                });
            });

            // Função para calcular desconto em tempo real
            function calcularDesconto() {
                if (!descontoAtivoCheckbox.checked) return;

                const preco = parseFloat(precoInput.value.replace(',', '.')) || 0;
                const descontoValor = parseFloat(descontoValorInput.value.replace(',', '.')) || 0;
                const tipoDesconto = document.querySelector('input[name="tipo_desconto"]:checked').value;

                if (preco > 0 && descontoValor > 0) {
                    let precoFinal = preco;
                    
                    if (tipoDesconto === 'percentual') {
                        if (descontoValor <= 100) {
                            precoFinal = preco * (1 - (descontoValor / 100));
                            descontoCalculado.innerHTML = `Preço final: R$ ${precoFinal.toFixed(2).replace('.', ',')} (${descontoValor}% off)`;
                        } else {
                            descontoCalculado.innerHTML = 'Percentual máximo é 100%';
                        }
                    } else {
                        if (descontoValor < preco) {
                            precoFinal = preco - descontoValor;
                            const percentualCalculado = (descontoValor / preco) * 100;
                            descontoCalculado.innerHTML = `Preço final: R$ ${precoFinal.toFixed(2).replace('.', ',')} (${percentualCalculado.toFixed(1)}% off)`;
                        } else {
                            descontoCalculado.innerHTML = 'Desconto deve ser menor que o preço';
                        }
                    }
                } else {
                    descontoCalculado.innerHTML = 'Insira os valores para calcular';
                }
            }

            // Calcular desconto inicial
            calcularDesconto();
        });

        // Adiciona botão flutuante de voltar para dispositivos móveis
        document.addEventListener('DOMContentLoaded', function() {
            // Verifica se é um dispositivo móvel
            const isMobile = window.innerWidth <= 480;
            
            if (isMobile) {
                // Cria o botão flutuante de voltar
                const floatingBackButton = document.createElement('button');
                floatingBackButton.className = 'floating-back-button';
                floatingBackButton.innerHTML = '<i class="fas fa-arrow-left"></i>';
                floatingBackButton.title = 'Voltar ao Painel';
                
                // Adiciona evento de clique
                floatingBackButton.addEventListener('click', function() {
                    window.location.href = 'dashboard.php';
                });
                
                // Adiciona ao body
                document.body.appendChild(floatingBackButton);
                
                // Remove o menu de navegação original
                const navMenu = document.querySelector('.nav-menu');
                if (navMenu) {
                    navMenu.style.display = 'none';
                }
            }
            
            // Ajusta dinamicamente a altura da capa com base na largura da tela
            function adjustCapaHeight() {
                const capaElements = document.querySelectorAll('.foto-produto-display, .foto-overlay, .default-image');
                const screenWidth = window.innerWidth;
                
                if (screenWidth <= 360) {
                    // Telas muito pequenas
                    capaElements.forEach(el => {
                        el.style.height = '180px';
                        if (el.tagName === 'IMG') {
                            el.style.height = '180px';
                        }
                    });
                } else if (screenWidth <= 480) {
                    // Telas de 480px
                    capaElements.forEach(el => {
                        el.style.height = '200px';
                        if (el.tagName === 'IMG') {
                            el.style.height = '200px';
                        }
                    });
                } else {
                    // Telas maiores (reset)
                    capaElements.forEach(el => {
                        el.style.height = '';
                        if (el.tagName === 'IMG') {
                            el.style.height = '';
                        }
                    });
                }
            }
            
            // Executa ao carregar e ao redimensionar
            adjustCapaHeight();
            window.addEventListener('resize', adjustCapaHeight);
        });
    </script>
</body>
</html>