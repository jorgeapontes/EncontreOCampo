<?php
// src/vendedor/anuncio_novo.php (VERSÃO CORRIGIDA - CRIA ANÚNCIO ATIVO POR PADRÃO)
require_once 'auth.php';

$mensagem_sucesso = '';
$mensagem_erro = '';
$vendedor_id_fk = $vendedor['id'];

// Dados do formulário (mantém valores em caso de erro)
$nome = '';
$descricao = '';
$preco = '';
$categoria = 'Frutas Cítricas';
$estoque = '';
$status = 'ativo'; // CORREÇÃO: Já definido como ativo por padrão
$modo_precificacao = 'por_quilo';
$quantidade_embalagem = '';
$paletizado = 0; // 0 = não, 1 = sim

// Categorias disponíveis
$categorias_disponiveis = [
    'Frutas Cítricas', 'Frutas Tropicais', 'Frutas de Caroço', 'Frutas Vermelhas', 'Frutas Secas', 'Frutas Exóticas',
    'Legumes Frutíferos', 'Legumes de Raiz', 'Legumes de Folha', 'Legumes de Bulbo',
    'Verduras', 'Folhosas', 'Temperos Frescos',
    'Grãos', 'Cereais', 'Leguminosas',
    'Raízes', 'Tubérculos',
    'Oleaginosas', 'Castanhas e Nozes',
    'Polpas de Fruta', 'Geleias e Doces', 'Conservas',
    'Produtos Orgânicos',
    'Plantas e Mudas', 'Flores Comestíveis', 'Ervas Medicinais', 'Outros'
];

$imagens_temp = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitização dos dados
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $preco = sanitizeInput($_POST['preco']);
    $categoria = sanitizeInput($_POST['categoria']);
    $estoque = sanitizeInput($_POST['estoque']);
    $modo_precificacao = sanitizeInput($_POST['modo_precificacao'] ?? 'por_quilo');
    $quantidade_embalagem = sanitizeInput($_POST['quantidade_embalagem'] ?? '');
    $paletizado = isset($_POST['paletizado']) ? 1 : 0;
    // CORREÇÃO: Obtém o status do POST, se não enviado, mantém 'ativo'
    $status = sanitizeInput($_POST['status'] ?? 'ativo');

    // Conversão de tipos
    $preco_db = str_replace(',', '.', $preco);
    $estoque_db = $estoque; // leave as string, will convert depending on modo
    // quantidade por embalagem (unidades ou kg)
    $quantidade_embalagem_db = str_replace(',', '.', $quantidade_embalagem);

    // Validação básica
    if (empty($nome) || empty($preco) || empty($estoque)) {
        $mensagem_erro = "Por favor, preencha os campos obrigatórios.";
    } elseif (!is_numeric($preco_db) || $preco_db <= 0) {
        $mensagem_erro = "O preço deve ser um valor numérico positivo.";
    } elseif ($estoque_db <= 0 && $status === 'ativo') {
        $mensagem_erro = "Anúncios ativos devem ter estoque maior que zero.";
    }

    // Upload de múltiplas imagens
    $upload_dir = '../uploads/produtos/';
    
    if (!is_dir($upload_dir) && empty($mensagem_erro)) {
        if (!mkdir($upload_dir, 0777, true)) {
            $mensagem_erro = "Erro interno: Não foi possível criar o diretório de uploads.";
        }
    }

    if (empty($mensagem_erro) && isset($_FILES['imagens'])) {
        $total_imagens = count($_FILES['imagens']['name']);
        
        if ($total_imagens === 0 || ($total_imagens === 1 && $_FILES['imagens']['error'][0] === UPLOAD_ERR_NO_FILE)) {
            $mensagem_erro = "Por favor, selecione pelo menos uma imagem para o anúncio.";
        } else {
            // A ordem do array $_FILES respeita a ordem do DataTransfer enviado pelo JS
            for ($i = 0; $i < $total_imagens; $i++) {
                if ($_FILES['imagens']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['imagens']['name'][$i];
                    $file_tmp = $_FILES['imagens']['tmp_name'][$i];
                    $file_size = $_FILES['imagens']['size'][$i];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                    $max_file_size = 5 * 1024 * 1024; // Aumentado para 5MB
                    
                    if (in_array($file_ext, $allowed_extensions) && $file_size <= $max_file_size) {
                        $novo_nome = uniqid('prod_', true) . '.' . $file_ext;
                        $destino_servidor = $upload_dir . $novo_nome;
                        
                        if (move_uploaded_file($file_tmp, $destino_servidor)) {
                            $imagens_temp[] = $destino_servidor;
                        }
                    }
                }
            }
        }
    }

    if (empty($mensagem_erro) && count($imagens_temp) === 0) {
        $mensagem_erro = "Nenhuma imagem válida foi enviada.";
    }

    // Inserção no banco
    if (empty($mensagem_erro) && count($imagens_temp) > 0) {
        try {
            // Garantir que a coluna `paletizado` existe na tabela produtos
            try {
                $col = $db->query("SHOW COLUMNS FROM produtos LIKE 'paletizado'")->fetch(PDO::FETCH_ASSOC);
                if (!$col) {
                    $db->exec("ALTER TABLE produtos ADD COLUMN paletizado TINYINT(1) NOT NULL DEFAULT 0");
                }
            } catch (Exception $e) {
                // Não interrompe o fluxo; coluna pode já existir ou permissões não permitirem alteração automatizada
            }

            $db->beginTransaction();

            // A primeira imagem do array (que foi ordenada pelo JS) é a principal
            $imagem_principal = $imagens_temp[0];
            
            // Define campos adicionais para o novo modo de precificação
            // unidade_medida será usada para exibir '/kg', '/unid', '/caixa' etc.
            $unidade_medida = 'kg';
            $embalagem_peso_kg = null;
            $embalagem_unidades = null;
            $estoque_unidades = null;

            switch ($modo_precificacao) {
                case 'por_unidade':
                    $unidade_medida = 'unidade';
                    $estoque_unidades = (int)$estoque_db;
                    $embalagem_unidades = $quantidade_embalagem_db ? (int)$quantidade_embalagem_db : null;
                    break;
                case 'por_quilo':
                    $unidade_medida = 'kg';
                    $estoque_db = (float)str_replace(',', '.', $estoque_db);
                    break;
                case 'caixa_unidades':
                    $unidade_medida = 'caixa';
                    $embalagem_unidades = $quantidade_embalagem_db ? (int)$quantidade_embalagem_db : null;
                    $estoque_unidades = (int)$estoque_db; // number of boxes
                    break;
                case 'caixa_quilos':
                    $unidade_medida = 'caixa';
                    $embalagem_peso_kg = $quantidade_embalagem_db ? (float)$quantidade_embalagem_db : null;
                    $estoque_db = (float)str_replace(',', '.', $estoque_db); // total kg available
                    break;
                case 'saco_unidades':
                    $unidade_medida = 'saco';
                    $embalagem_unidades = $quantidade_embalagem_db ? (int)$quantidade_embalagem_db : null;
                    $estoque_unidades = (int)$estoque_db;
                    break;
                case 'saco_quilos':
                    $unidade_medida = 'saco';
                    $embalagem_peso_kg = $quantidade_embalagem_db ? (float)$quantidade_embalagem_db : null;
                    $estoque_db = (float)str_replace(',', '.', $estoque_db);
                    break;
                default:
                    $unidade_medida = 'kg';
                    $estoque_db = (float)str_replace(',', '.', $estoque_db);
            }

            $query = "INSERT INTO produtos (vendedor_id, nome, descricao, preco, categoria, estoque, estoque_unidades, status, imagem_url, data_criacao, modo_precificacao, embalagem_peso_kg, embalagem_unidades, unidade_medida, paletizado)
                      VALUES (:vendedor_id, :nome, :descricao, :preco, :categoria, :estoque, :estoque_unidades, :status, :imagem_url, NOW(), :modo_precificacao, :embalagem_peso_kg, :embalagem_unidades, :unidade_medida, :paletizado)";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':vendedor_id', $vendedor_id_fk);
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':preco', $preco_db);
            $stmt->bindParam(':categoria', $categoria);
            // Bind estoque (kg) and estoque_unidades depending on mode
            $stmt->bindValue(':estoque', isset($estoque_db) ? $estoque_db : null);
            $stmt->bindValue(':estoque_unidades', isset($estoque_unidades) ? $estoque_unidades : null, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':imagem_url', $imagem_principal);
            $stmt->bindParam(':modo_precificacao', $modo_precificacao);
            $stmt->bindValue(':embalagem_peso_kg', $embalagem_peso_kg);
            $stmt->bindValue(':embalagem_unidades', $embalagem_unidades, PDO::PARAM_INT);
            $stmt->bindParam(':unidade_medida', $unidade_medida);
            $stmt->bindValue(':paletizado', $paletizado, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $produto_id = $db->lastInsertId();
                
                // Salvar todas as imagens na tabela auxiliar
                $ordem = 0;
                foreach ($imagens_temp as $imagem_url) {
                    $query_imagem = "INSERT INTO produto_imagens (produto_id, imagem_url, ordem) 
                                     VALUES (:produto_id, :imagem_url, :ordem)";
                    $stmt_imagem = $db->prepare($query_imagem);
                    $stmt_imagem->bindParam(':produto_id', $produto_id);
                    $stmt_imagem->bindParam(':imagem_url', $imagem_url);
                    $stmt_imagem->bindParam(':ordem', $ordem);
                    $stmt_imagem->execute();
                    $ordem++;
                }
                
                $db->commit();
                $_SESSION['mensagem_anuncio_sucesso'] = "Anúncio criado com sucesso!";
                header("Location: anuncios.php");
                exit();
            } else {
                throw new Exception("Erro ao inserir produto.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            // Limpar imagens
            foreach ($imagens_temp as $img) { if (file_exists($img)) unlink($img); }
            $mensagem_erro = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

$preco_formatado = number_format((float)$preco, 2, ',', '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo Anúncio</title>
    <link rel="stylesheet" href="../css/vendedor/anuncio_novo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&family=Zalando+Sans+SemiExpanded:wght@200..900&display=swap" rel="stylesheet">
    <style>
        /* CSS FIXES */
        .foto-produto-display {
            width: 100%;
            height: 300px; /* Altura fixa */
            border-radius: 8px;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #ddd;
        }
        
        .foto-produto-display img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Garante que a imagem preencha sem distorcer */
            display: block;
        }

        .galeria-imagens {
            margin-top: 20px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #f9f9f9;
        }
        
        .imagens-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .imagem-item {
            position: relative;
            width: 140px;
            height: 140px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #ddd;
            cursor: grab;
            background: #fff;
        }
        
        .imagem-item.dragging {
            opacity: 0.5;
            border-color: #4CAF50;
        }
        
        .imagem-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            pointer-events: none; /* Importante para drag n drop */
        }

        .btn-acao {
            position: absolute;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: 0.2s;
        }

        .btn-acao.principal { top: 5px; right: 40px; background: rgba(0,0,0,0.6); }
        .btn-acao.principal.active { background: #FFD700; color: #000; }
        .btn-acao.remover { top: 5px; right: 5px; background: rgba(220, 53, 69, 0.8); }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #4CAF50;
            border-radius: 50%;
            width: 50px; height: 50px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* Estilos para o campo status (oculto) */
        .status-hidden-field {
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
                    <div><h1>ENCONTRE</h1><h2>O CAMPO</h2></div>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Voltar ao Painel</a></li>
                </ul>
            </div>
        </nav>
    </header>
    <br>

    <div class="main-content">
        <center>
            <header class="header"><h1>Criar Novo Anúncio</h1></header>
        </center>

        <section class="form-section">
            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensagem_erro; ?></div>
            <?php endif; ?>
            
            <div class="loading-overlay" id="loadingOverlay">
                <div class="spinner"></div>
                <p>Publicando anúncio...</p>
            </div>
            
            <form method="POST" action="anuncio_novo.php" class="anuncio-form" enctype="multipart/form-data" id="anuncioForm">
                <div class="forms-area">
                    <div class="top-info">
                        <div class="form-group">
                            <div class="foto-produto-container">
                                <div class="foto-produto-display" id="imagemPrincipalPreview">
                                    <div class="default-image">
                                        <i class="fas fa-image"></i>
                                        <p>Capa</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="prod-info">
                            <h2>Detalhes do Produto</h2>
                            <div class="form-group">
                                <label for="nome" class="required">Nome do Produto</label>
                                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" list="produtos-sugestoes" required>
                                <datalist id="produtos-sugestoes">
                                    <option value="Banana"><option value="Maçã"><option value="Laranja"><option value="Tomate"><option value="Alface">
                                </datalist>
                            </div>

                            <div class="form-group">
                                <label for="categoria">Categoria</label>
                                <select id="categoria" name="categoria">
                                    <?php foreach ($categorias_disponiveis as $cat): ?>
                                        <option value="<?php echo $cat; ?>" <?php echo ($categoria === $cat) ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                
                    <div class="form-group">
                        <label for="imagens" class="required">Imagens do Produto</label>
                        <div class="galeria-imagens">
                            <div class="galeria-header">
                                <h3>Gerenciar Imagens</h3>
                                <span class="contador-imagens" id="contadorImagens">0 imagens</span>
                            </div>
                            
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h4>Arraste fotos aqui ou clique para selecionar</h4>
                                <p>JPG, PNG, WEBP (Max 5MB)</p>
                            </div>
                            
                            <div class="imagens-preview" id="imagensPreview"></div>
                            
                            <input type="file" id="imagens" name="imagens[]" accept="image/*" multiple style="display: none;" required>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="modo_precificacao" class="required">Modo de Precificação</label>
                            <select id="modo_precificacao" name="modo_precificacao">
                                <option value="por_quilo" <?php echo ($modo_precificacao === 'por_quilo') ? 'selected' : ''; ?>>Por quilo</option>
                                <option value="por_unidade" <?php echo ($modo_precificacao === 'por_unidade') ? 'selected' : ''; ?>>Por unidade</option>
                                <option value="caixa_unidades" <?php echo ($modo_precificacao === 'caixa_unidades') ? 'selected' : ''; ?>>Por caixa com X unidades</option>
                                <option value="caixa_quilos" <?php echo ($modo_precificacao === 'caixa_quilos') ? 'selected' : ''; ?>>Por caixa com X quilos</option>
                                <option value="saco_unidades" <?php echo ($modo_precificacao === 'saco_unidades') ? 'selected' : ''; ?>>Por saco com X unidades</option>
                                <option value="saco_quilos" <?php echo ($modo_precificacao === 'saco_quilos') ? 'selected' : ''; ?>>Por saco com X quilos</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="preco" class="required" id="labelPreco">Preço por Kg (R$)</label>
                            <input type="text" id="preco" name="preco" value="<?php echo htmlspecialchars($preco_formatado); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="estoque" class="required" id="labelEstoque">Estoque em Kg</label>
                            <input type="number" id="estoque" name="estoque" value="<?php echo htmlspecialchars($estoque); ?>" min="0" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="quantidade_embalagem" id="labelQuantidadeEmb">Quantidade por embalagem (se aplicável)</label>
                        <input type="number" id="quantidade_embalagem" name="quantidade_embalagem" value="<?php echo htmlspecialchars($quantidade_embalagem); ?>">
                    </div>

                                    <div class="form-group">
                                        <label class="checkbox-inline">
                                            <input type="checkbox" id="paletizado" name="paletizado" value="1" <?php echo ($paletizado) ? 'checked' : ''; ?>> Produto será paletizado
                                        </label>
                                        <div class="help-text">Marque se este produto será entregue em paletes.</div>
                                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <textarea id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($descricao); ?></textarea>
                    </div>

                    <!-- CORREÇÃO: Campo status oculto, sempre ativo -->
                    <input type="hidden" name="status" value="ativo">

                    <button type="submit" class="cta-button big-button"><i class="fas fa-check"></i> Publicar Anúncio</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('imagens');
            const uploadArea = document.getElementById('uploadArea');
            const imagensPreview = document.getElementById('imagensPreview');
            const contadorImagens = document.getElementById('contadorImagens');
            const imagemPrincipalPreview = document.getElementById('imagemPrincipalPreview');
            const anuncioForm = document.getElementById('anuncioForm');
            const loadingOverlay = document.getElementById('loadingOverlay');

            // Array global para manter os arquivos em memória
            let filesArray = [];
            let primaryIndex = 0; // Índice do array que é a principal

            // --- Máscara de Preço ---
            document.getElementById('preco').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value) {
                    value = (parseInt(value) / 100).toFixed(2).replace('.', ',');
                }
                e.target.value = value;
            });

            const modoSelect = document.getElementById('modo_precificacao');
            const labelPreco = document.getElementById('labelPreco');
            const labelEstoque = document.getElementById('labelEstoque');
            const quantidadeEmbInput = document.getElementById('quantidade_embalagem');
            const labelQuantidadeEmb = document.getElementById('labelQuantidadeEmb');

            function updatePrecificacaoUI() {
                const modo = modoSelect.value;
                if (modo === 'por_quilo') {
                    labelPreco.textContent = 'Preço por Kg (R$)';
                    labelEstoque.textContent = 'Estoque em Kg';
                    labelQuantidadeEmb.style.display = 'none';
                    quantidadeEmbInput.style.display = 'none';
                } else if (modo === 'por_unidade') {
                    labelPreco.textContent = 'Preço por Unidade (R$)';
                    labelEstoque.textContent = 'Estoque em Unidades';
                    labelQuantidadeEmb.style.display = 'none';
                    quantidadeEmbInput.style.display = 'none';
                } else if (modo === 'caixa_unidades') {
                    labelPreco.textContent = 'Preço por Caixa (R$)';
                    labelEstoque.textContent = 'Estoque em Caixas';
                    labelQuantidadeEmb.style.display = 'block';
                    quantidadeEmbInput.style.display = 'block';
                    labelQuantidadeEmb.textContent = 'Unidades por Caixa (ex: 10)';
                } else if (modo === 'caixa_quilos') {
                    labelPreco.textContent = 'Preço por Caixa (R$)';
                    labelEstoque.textContent = 'Estoque em Caixas';
                    labelQuantidadeEmb.style.display = 'block';
                    quantidadeEmbInput.style.display = 'block';
                    labelQuantidadeEmb.textContent = 'Kg por Caixa (ex: 5,5)';
                } else if (modo === 'saco_unidades') {
                    labelPreco.textContent = 'Preço por Saco (R$)';
                    labelEstoque.textContent = 'Estoque em Sacos';
                    labelQuantidadeEmb.style.display = 'block';
                    quantidadeEmbInput.style.display = 'block';
                    labelQuantidadeEmb.textContent = 'Unidades por Saco (ex: 10)';
                } else if (modo === 'saco_quilos') {
                    labelPreco.textContent = 'Preço por Saco (R$)';
                    labelEstoque.textContent = 'Estoque em Sacos';
                    labelQuantidadeEmb.style.display = 'block';
                    quantidadeEmbInput.style.display = 'block';
                    labelQuantidadeEmb.textContent = 'Kg por Saco (ex: 5,5)';
                }
            }

            modoSelect.addEventListener('change', updatePrecificacaoUI);
            updatePrecificacaoUI();

            // --- Upload Click ---
            uploadArea.addEventListener('click', () => fileInput.click());

            // --- Upload Change ---
            fileInput.addEventListener('change', function(e) {
                handleFiles(Array.from(this.files));
            });

            // --- Drag & Drop no Upload Area ---
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); });
            });
            uploadArea.addEventListener('drop', (e) => handleFiles(Array.from(e.dataTransfer.files)));

            function handleFiles(newFiles) {
                const validFiles = newFiles.filter(file => file.type.startsWith('image/'));
                if (validFiles.length === 0) return;

                // Adiciona novos arquivos ao array existente
                filesArray = filesArray.concat(validFiles);
                updateUI();
            }

            // --- Função Principal de UI ---
            function updateUI() {
                imagensPreview.innerHTML = '';
                contadorImagens.textContent = `${filesArray.length} imagens selecionadas`;

                // Garante que o índice principal é válido
                if (primaryIndex >= filesArray.length) primaryIndex = 0;

                filesArray.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const div = document.createElement('div');
                        div.className = 'imagem-item';
                        div.draggable = true;
                        div.dataset.index = index;

                        div.innerHTML = `
                            <img src="${e.target.result}">
                            <button type="button" class="btn-acao principal ${index === primaryIndex ? 'active' : ''}" onclick="setPrimary(${index})">
                                <i class="fas fa-star"></i>
                            </button>
                            <button type="button" class="btn-acao remover" onclick="removeImage(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        `;

                        // Eventos de Drag & Drop para Ordenação
                        div.addEventListener('dragstart', dragStart);
                        div.addEventListener('dragover', dragOver);
                        div.addEventListener('drop', drop);
                        div.addEventListener('dragend', dragEnd);

                        imagensPreview.appendChild(div);

                        // Se for a imagem principal, atualiza o topo
                        if (index === primaryIndex) {
                            imagemPrincipalPreview.innerHTML = `<img src="${e.target.result}" alt="Principal">`;
                        }
                    };
                    reader.readAsDataURL(file);
                });

                if (filesArray.length === 0) {
                    imagemPrincipalPreview.innerHTML = `
                        <div class="default-image">
                            <i class="fas fa-image" style="font-size: 4rem; color: #ccc;"></i>
                            <p style="color: #999;">Imagem Principal</p>
                        </div>`;
                }

                // Sincroniza o Input File com o Array Ordenado
                syncInputFiles();
            }

            // --- Funções Globais (acessíveis pelo HTML) ---
            window.setPrimary = (index) => {
                primaryIndex = index;
                updateUI(); // Re-renderiza para atualizar as estrelas e a foto do topo
            };

            window.removeImage = (index) => {
                filesArray.splice(index, 1);
                if (primaryIndex === index) primaryIndex = 0; // Reseta se apagar a principal
                else if (primaryIndex > index) primaryIndex--; // Ajusta o índice
                updateUI();
            };

            // --- Lógica de Ordenação (Drag & Drop) ---
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
                
                if (dragSrcEl !== this) {
                    // Troca a posição no array
                    const oldIndex = parseInt(dragSrcEl.dataset.index);
                    const newIndex = parseInt(this.dataset.index);

                    // Move o item no array
                    const itemMoved = filesArray.splice(oldIndex, 1)[0];
                    filesArray.splice(newIndex, 0, itemMoved);

                    // Ajusta o primaryIndex
                    if (primaryIndex === oldIndex) primaryIndex = newIndex;
                    else if (primaryIndex > oldIndex && primaryIndex <= newIndex) primaryIndex--;
                    else if (primaryIndex < oldIndex && primaryIndex >= newIndex) primaryIndex++;
                    
                    updateUI();
                }
                return false;
            }

            function dragEnd() {
                this.classList.remove('dragging');
            }

            // --- Sincroniza o DataTransfer para o PHP receber a ordem correta ---
            function syncInputFiles() {
                const dataTransfer = new DataTransfer();
                
                const filesToSend = [...filesArray];
                if (filesToSend.length > 0 && primaryIndex > 0) {
                    const primaryFile = filesToSend.splice(primaryIndex, 1)[0];
                    filesToSend.unshift(primaryFile); // Coloca a principal em primeiro
                }

                filesToSend.forEach(file => dataTransfer.items.add(file));
                fileInput.files = dataTransfer.files;
            }

            // --- Submit ---
            anuncioForm.addEventListener('submit', function(e) {
                if (filesArray.length === 0) {
                    e.preventDefault();
                    alert('Selecione pelo menos uma imagem.');
                    return;
                }
                loadingOverlay.style.display = 'flex';
            });
        });
    </script>
</body>
</html>