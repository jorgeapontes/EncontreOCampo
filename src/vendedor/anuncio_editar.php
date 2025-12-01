<?php
// src/vendedor/anuncio_editar.php (CÓDIGO CORRIGIDO - SALVA preco_desconto)
require_once 'auth.php'; 

$mensagem_sucesso = '';
$mensagem_erro = '';
$vendedor_id_fk = $vendedor['id'];
$anuncio = null;

// O ID pode vir da URL (GET) ou do formulário (POST)
$anuncio_id = sanitizeInput($_REQUEST['id'] ?? $_POST['anuncio_id'] ?? null);

// --------------------------------------------------------
// 1. Lógica de Carregamento (GET) / Pré-processamento
// --------------------------------------------------------
if (!$anuncio_id) {
    $_SESSION['mensagem_anuncio_erro'] = "ID do anúncio não fornecido para edição.";
    header("Location: anuncios.php");
    exit();
}

try {
    // Busca o anúncio para verificar se pertence ao vendedor logado
    $query_anuncio = "SELECT * FROM produtos WHERE id = :anuncio_id AND vendedor_id = :vendedor_id";
    $stmt_anuncio = $db->prepare($query_anuncio);
    $stmt_anuncio->bindParam(':anuncio_id', $anuncio_id);
    $stmt_anuncio->bindParam(':vendedor_id', $vendedor_id_fk);
    $stmt_anuncio->execute();
    $anuncio = $stmt_anuncio->fetch(PDO::FETCH_ASSOC);

    if (!$anuncio) {
        $_SESSION['mensagem_anuncio_erro'] = "Anúncio não encontrado ou você não tem permissão para editá-lo.";
        header("Location: anuncios.php");
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['mensagem_anuncio_erro'] = "Erro de banco de dados ao carregar anúncio: " . $e->getMessage();
    header("Location: anuncios.php");
    exit();
}

// --------------------------------------------------------
// 2. Lógica de Atualização (POST)
// --------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitização dos dados básicos
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $preco = sanitizeInput($_POST['preco']);
    $categoria = sanitizeInput($_POST['categoria']);
    $estoque = sanitizeInput($_POST['estoque']);
    $status = sanitizeInput($_POST['status']);
    
    // Dados de desconto
    $desconto_ativo = isset($_POST['desconto_ativo']) ? 1 : 0;
    $tipo_desconto = sanitizeInput($_POST['tipo_desconto'] ?? 'percentual');
    $desconto_valor = sanitizeInput($_POST['desconto_valor'] ?? 0);
    $desconto_data_inicio = sanitizeInput($_POST['desconto_data_inicio'] ?? null);
    $desconto_data_fim = sanitizeInput($_POST['desconto_data_fim'] ?? null);
    
    // A variável $anuncio já foi carregada acima, então usamos $anuncio['imagem_url'] como backup
    $imagem_url_antiga = $anuncio['imagem_url']; 
    $imagem_url_nova = $imagem_url_antiga;

    // Conversão de tipos
    $preco_db = str_replace(',', '.', $preco);
    $estoque_db = (int)$estoque;
    $desconto_valor_db = str_replace(',', '.', $desconto_valor);

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

    // ----------------------------------------------------
    // Lógica de Upload/Substituição de Imagem
    // ----------------------------------------------------
    $upload_dir = '../uploads/produtos/'; 
    
    if (isset($_FILES['imagem_upload']) && $_FILES['imagem_upload']['error'] === UPLOAD_ERR_OK && empty($mensagem_erro)) {
        $file_name = $_FILES['imagem_upload']['name'];
        $file_tmp = $_FILES['imagem_upload']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        $max_file_size = 2097152;

        if (!in_array($file_ext, $allowed_extensions)) {
            $mensagem_erro = "Formato de arquivo inválido. Apenas JPG, JPEG e PNG são permitidos.";
        } elseif ($_FILES['imagem_upload']['size'] > $max_file_size) {
            $mensagem_erro = "O arquivo é muito grande. O tamanho máximo é 2MB.";
        } else {
            $novo_nome = uniqid('prod_', true) . '.' . $file_ext;
            $destino_servidor = $upload_dir . $novo_nome;

            if (move_uploaded_file($file_tmp, $destino_servidor)) {
                $imagem_url_nova = $destino_servidor; 

                if (!empty($imagem_url_antiga) && file_exists($imagem_url_antiga) && strpos($imagem_url_antiga, 'default_image') === false) {
                    @unlink($imagem_url_antiga);
                }
            } else {
                $mensagem_erro = "Erro ao mover o novo arquivo para o destino.";
            }
        }
    }
    // ----------------------------------------------------

    // Validação básica
    if (empty($mensagem_erro)) {
        if (empty($nome) || empty($preco) || empty($estoque)) {
            $mensagem_erro = "Por favor, preencha os campos obrigatórios: Nome, Preço e Estoque.";
        } elseif (!is_numeric($preco_db) || $preco_db <= 0) {
            $mensagem_erro = "O preço deve ser um valor numérico positivo.";
        } elseif ($estoque_db <= 0 && $status === 'ativo') {
            $mensagem_erro = "Anúncios ativos devem ter estoque maior que zero, ou mude o status para 'inativo'.";
        }
    }

    if (empty($mensagem_erro)) {
        try {
            $db->beginTransaction();
            
            // CORREÇÃO PRINCIPAL: Calcular preco_desconto
            $desconto_percentual = 0;
            $desconto_ativo_db = $desconto_ativo;
            $preco_desconto_db = null; // Inicialmente null
            
            // Se desconto está ativo, calcular o preco_desconto
            if ($desconto_ativo && $desconto_valor_db > 0) {
                if ($tipo_desconto === 'percentual') {
                    $desconto_percentual = $desconto_valor_db;
                    $preco_desconto_db = $preco_db * (1 - ($desconto_percentual / 100));
                } else {
                    // Para desconto em valor, calcular percentual equivalente
                    $desconto_percentual = ($desconto_valor_db / $preco_db) * 100;
                    $preco_desconto_db = $preco_db - $desconto_valor_db;
                }
            } else {
                // Se desconto não está ativo, zerar tudo
                $desconto_ativo_db = 0;
                $desconto_percentual = 0;
                $preco_desconto_db = null;
            }
            
            // Converter datas para formato MySQL
            $desconto_data_inicio_db = $desconto_data_inicio ? $desconto_data_inicio . ' 00:00:00' : null;
            $desconto_data_fim_db = $desconto_data_fim ? $desconto_data_fim . ' 23:59:59' : null;
            
            // Se desconto não está ativo, limpar as datas
            if (!$desconto_ativo_db) {
                $desconto_data_inicio_db = null;
                $desconto_data_fim_db = null;
                $preco_desconto_db = null;
            }
            
            $query = "UPDATE produtos SET 
                        nome = :nome, 
                        descricao = :descricao, 
                        preco = :preco, 
                        preco_desconto = :preco_desconto,
                        categoria = :categoria, 
                        estoque = :estoque, 
                        status = :status,
                        imagem_url = :imagem_url_nova,
                        desconto_ativo = :desconto_ativo,
                        desconto_percentual = :desconto_percentual,
                        desconto_data_inicio = :desconto_data_inicio,
                        desconto_data_fim = :desconto_data_fim,
                        data_atualizacao = NOW()
                      WHERE id = :anuncio_id AND vendedor_id = :vendedor_id";
                      
            $stmt = $db->prepare($query);
            
            // Binding dos parâmetros
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':preco', $preco_db);
            $stmt->bindParam(':preco_desconto', $preco_desconto_db);
            $stmt->bindParam(':categoria', $categoria);
            $stmt->bindParam(':estoque', $estoque_db, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':imagem_url_nova', $imagem_url_nova);
            $stmt->bindParam(':desconto_ativo', $desconto_ativo_db, PDO::PARAM_INT);
            $stmt->bindParam(':desconto_percentual', $desconto_percentual);
            $stmt->bindParam(':desconto_data_inicio', $desconto_data_inicio_db);
            $stmt->bindParam(':desconto_data_fim', $desconto_data_fim_db);
            $stmt->bindParam(':anuncio_id', $anuncio_id);
            $stmt->bindParam(':vendedor_id', $vendedor_id_fk);
            
            if ($stmt->execute()) {
                $db->commit();
                
                // Atualiza a variável $anuncio com os NOVOS DADOS
                $anuncio['nome'] = $nome;
                $anuncio['descricao'] = $descricao;
                $anuncio['preco'] = $preco_db;
                $anuncio['preco_desconto'] = $preco_desconto_db;
                $anuncio['categoria'] = $categoria;
                $anuncio['estoque'] = $estoque_db;
                $anuncio['status'] = $status;
                $anuncio['imagem_url'] = $imagem_url_nova;
                $anuncio['desconto_ativo'] = $desconto_ativo_db;
                $anuncio['desconto_percentual'] = $desconto_percentual;
                $anuncio['desconto_data_inicio'] = $desconto_data_inicio_db;
                $anuncio['desconto_data_fim'] = $desconto_data_fim_db;
                
                $mensagem_sucesso = "Anúncio **{$nome}** atualizado com sucesso!";
            } else {
                $db->rollBack();
                $mensagem_erro = "Erro ao atualizar o anúncio. Tente novamente.";
            }

        } catch (PDOException $e) {
            $db->rollBack();
            $mensagem_erro = "Erro de banco de dados: " . $e->getMessage();
        }
    }
}
// --------------------------------------------------------
// 3. Formatação Final e Variáveis de Exibição
// --------------------------------------------------------
// Garante que o preço formatado exista, mesmo se o POST falhar.
$preco_formatado = number_format($anuncio['preco'], 2, ',', ''); 

// Formatar dados de desconto para exibição
$desconto_valor_formatado = '';
if ($anuncio['desconto_ativo'] && $anuncio['desconto_percentual'] > 0) {
    $desconto_valor_formatado = number_format($anuncio['desconto_percentual'], 2, ',', '');
}

$desconto_data_inicio_formatada = $anuncio['desconto_data_inicio'] ? date('Y-m-d', strtotime($anuncio['desconto_data_inicio'])) : '';
$desconto_data_fim_formatada = $anuncio['desconto_data_fim'] ? date('Y-m-d', strtotime($anuncio['desconto_data_fim'])) : '';

// Determinar tipo de desconto atual para exibição
$tipo_desconto_atual = 'percentual'; // Padrão

// Usar preco_desconto se existir, caso contrário calcular
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

// ... O RESTANTE DO CÓDIGO HTML PERMANECE O MESMO ...
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Anúncio - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/anuncio_editar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
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
                        <a href="dashboard.php" class="nav-link active">Painel</a>
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
                        <a href="../logout.php" class="nav-link exit-button no-underline"> Sair </a>
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

    <div class="main-content">
        <center>
            <header class="header">
                <h1>Editar: <?php echo htmlspecialchars($anuncio['nome']); ?> (ID: <?php echo $anuncio['id']; ?>)</h1>
            </header>
        </center>

        <section class="form-section">
            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="alert success-alert"><i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            <form method="POST" action="anuncio_editar.php" class="anuncio-form" enctype="multipart/form-data">
                <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                
                <div class="forms-area">
                    <div class="top-info">
                        <div class="form-group">
                            <div class="foto-produto-container">
                                <div class="foto-produto-display">
                                    <?php if (!empty($anuncio['imagem_url']) && file_exists($anuncio['imagem_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($anuncio['imagem_url']); ?>" alt="Imagem do Anúncio">
                                    <?php else: ?>
                                        <div class="default-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="foto-overlay">
                                        <i class="fas fa-pencil-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="prod-info">
                            <h2>Informações do Produto</h2>
                            <div class="form-group">
                                <label for="nome" class="required">Nome da Fruta/Produto</label>
                                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($anuncio['nome']); ?>" list="produtos-sugestoes" required>
                                <datalist id="produtos-sugestoes">
                                    <!-- Frutas -->
                                    <option value="Abacate">
                                    <option value="Abacaxi">
                                    <option value="Açaí">
                                    <option value="Acerola">
                                    <option value="Amora">
                                    <option value="Banana">
                                    <option value="Caju">
                                    <option value="Coco">
                                    <option value="Figo">
                                    <option value="Framboesa">
                                    <option value="Goiaba">
                                    <option value="Jabuticaba">
                                    <option value="Jaca">
                                    <option value="Kiwi">
                                    <option value="Laranja">
                                    <option value="Limão">
                                    <option value="Maçã">
                                    <option value="Mamão">
                                    <option value="Manga">
                                    <option value="Maracujá">
                                    <option value="Melancia">
                                    <option value="Melão">
                                    <option value="Morango">
                                    <option value="Pêra">
                                    <option value="Pêssego">
                                    <option value="Uva">
                                    
                                    <!-- Legumes -->
                                    <option value="Abóbora">
                                    <option value="Berinjela">
                                    <option value="Beterraba">
                                    <option value="Cenoura">
                                    <option value="Chuchu">
                                    <option value="Ervilha">
                                    <option value="Milho">
                                    <option value="Pepino">
                                    <option value="Pimentão">
                                    <option value="Quiabo">
                                    <option value="Tomate">
                                    
                                    <!-- Verduras -->
                                    <option value="Alface">
                                    <option value="Couve">
                                    <option value="Espinafre">
                                    <option value="Rúcula">
                                    <option value="Agrião">
                                    <option value="Salsinha">
                                    <option value="Cebolinha">
                                    <option value="Manjericão">
                                    
                                    <!-- Grãos e Cereais -->
                                    <option value="Arroz">
                                    <option value="Feijão">
                                    <option value="Soja">
                                    <option value="Trigo">
                                    <option value="Milho Seco">
                                    
                                    <!-- Outros -->
                                    <option value="Batata">
                                    <option value="Cebola">
                                    <option value="Alho">
                                    <option value="Gengibre">
                                </datalist>
                            </div>
                            
                            <div class="form-group">
                                <label for="categoria">Categoria</label>
                                <select id="categoria" name="categoria">
                                    <?php foreach ($categorias_disponiveis as $cat): ?>
                                        <option value="<?php echo $cat; ?>" <?php echo ($anuncio['categoria'] === $cat) ? 'selected' : ''; ?>>
                                            <?php echo $cat; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="display: none;">
                        <input type="file" id="imagem_upload" name="imagem_upload" accept="image/jpeg, image/png">
                    </div>

                    <h2>Preço e Estoque</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="preco" class="required">Preço por Kg (R$)</label>
                            <input type="text" id="preco" name="preco" value="<?php echo htmlspecialchars($preco_formatado); ?>" placeholder="Ex: 5,50" required>
                            <?php if ($anuncio['desconto_ativo'] && $anuncio['desconto_percentual'] > 0): ?>
                                <div class="preco-com-desconto">
                                    Preço com desconto: R$ <?php echo number_format($preco_com_desconto, 2, ',', ''); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="estoque" class="required">Estoque em Kg</label>
                            <input type="number" id="estoque" name="estoque" value="<?php echo htmlspecialchars($anuncio['estoque']); ?>" min="0" required>
                        </div>
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
                        <label for="status" class="required">Status do Anúncio</label>
                        <select id="status" name="status" required>
                            <option value="ativo" <?php echo ($anuncio['status'] === 'ativo') ? 'selected' : ''; ?>>Ativo (Visível)</option>
                            <option value="inativo" <?php echo ($anuncio['status'] === 'inativo') ? 'selected' : ''; ?>>Inativo (Pausado)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição Detalhada do Produto (Opcional)</label>
                        <textarea id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($anuncio['descricao'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="big-button"><i class="fas fa-save"></i> Salvar Alterações</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        // Script para menu hamburger
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

        // Máscara de preço
        document.addEventListener('DOMContentLoaded', function() {
            const precoInput = document.getElementById('preco');
            const descontoValorInput = document.getElementById('desconto_valor');
            const descontoAtivoCheckbox = document.getElementById('desconto_ativo');
            const camposDesconto = document.getElementById('campos-desconto');
            const tipoDescontoRadios = document.querySelectorAll('input[name="tipo_desconto"]');
            const descontoHelp = document.getElementById('desconto-help');
            const descontoCalculado = document.getElementById('desconto-calculado');

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
                        // Para percentual, permite até 100,00
                        value = (parseInt(value) / 100).toFixed(2);
                    } else {
                        // Para valor, formata normalmente
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
                    // Limpar campo quando desativar
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
                    // Limpar campo ao mudar tipo
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

            // Script para clicar na imagem abrir o seletor de arquivos
            const fotoContainer = document.querySelector('.foto-produto-container');
            const fileInput = document.getElementById('imagem_upload');
            
            if (fotoContainer && fileInput) {
                fotoContainer.addEventListener('click', function() {
                    fileInput.click();
                });
            }
            
            // Mostrar preview da nova imagem selecionada
            fileInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    const imgElement = document.querySelector('.foto-produto-display img');
                    const defaultImage = document.querySelector('.default-image');
                    
                    reader.onload = function(e) {
                        if (imgElement) {
                            imgElement.src = e.target.result;
                        } else if (defaultImage) {
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.alt = "Imagem do Anúncio";
                            newImg.style.width = '300px';
                            newImg.style.height = '250px';
                            newImg.style.objectFit = 'cover';
                            newImg.style.borderRadius = '5px';
                            newImg.style.border = '2px solid #C8E6C9';
                            
                            defaultImage.parentNode.replaceChild(newImg, defaultImage);
                        }
                    }
                    
                    reader.readAsDataURL(e.target.files[0]);
                }
            });

            // Calcular desconto inicial
            calcularDesconto();
        });
    </script>
</body>
</html>