<?php
// src/vendedor/anuncio_editar.php (CÓDIGO CORRIGIDO E REFORÇADO)
require_once 'auth.php'; 

$mensagem_sucesso = '';
$mensagem_erro = '';
$vendedor_id_fk = $vendedor['id'];
$anuncio = null; // Inicializa a variável para evitar erro caso a busca falhe

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

    // Se o anúncio não for encontrado (ID inválido ou não pertence ao vendedor)
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
    // Coleta e sanitização dos dados
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $preco = sanitizeInput($_POST['preco']);
    $categoria = sanitizeInput($_POST['categoria']);
    $estoque = sanitizeInput($_POST['estoque']);
    $status = sanitizeInput($_POST['status']);
    
    // A variável $anuncio já foi carregada acima, então usamos $anuncio['imagem_url'] como backup
    $imagem_url_antiga = $anuncio['imagem_url']; 
    $imagem_url_nova = $imagem_url_antiga; // Mantém a antiga por padrão

    // Conversão de tipos
    $preco_db = str_replace(',', '.', $preco);
    $estoque_db = (int)$estoque;

    // ----------------------------------------------------
    // Lógica de Upload/Substituição de Imagem
    // ----------------------------------------------------
    $upload_dir = '../uploads/produtos/'; 
    
    if (isset($_FILES['imagem_upload']) && $_FILES['imagem_upload']['error'] === UPLOAD_ERR_OK) {
        // Lógica de validação e upload que fornecemos antes...
        $file_name = $_FILES['imagem_upload']['name'];
        $file_tmp = $_FILES['imagem_upload']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        $max_file_size = 2097152; // 2MB

        // Validação do arquivo
        if (!in_array($file_ext, $allowed_extensions)) {
            $mensagem_erro = "Formato de arquivo inválido. Apenas JPG, JPEG e PNG são permitidos.";
        } elseif ($_FILES['imagem_upload']['size'] > $max_file_size) {
            $mensagem_erro = "O arquivo é muito grande. O tamanho máximo é 2MB.";
        } else {
            // Gera um nome de arquivo único
            $novo_nome = uniqid('prod_', true) . '.' . $file_ext;
            $destino_servidor = $upload_dir . $novo_nome;

            if (move_uploaded_file($file_tmp, $destino_servidor)) {
                $imagem_url_nova = $destino_servidor; 

                // Deleta a imagem antiga se não for a imagem padrão e se a nova foi salva
                if (!empty($imagem_url_antiga) && file_exists($imagem_url_antiga) && strpos($imagem_url_antiga, 'default_image') === false) {
                    @unlink($imagem_url_antiga); // @ para suprimir warnings caso o arquivo não exista mais
                }
            } else {
                $mensagem_erro = "Erro ao mover o novo arquivo para o destino.";
            }
        }
    }
    // ----------------------------------------------------

    // Validação
    if (empty($nome) || empty($preco) || empty($estoque)) {
        $mensagem_erro = "Por favor, preencha os campos obrigatórios: Nome, Preço e Estoque.";
    } elseif (!is_numeric($preco_db) || $preco_db <= 0) {
        $mensagem_erro = "O preço deve ser um valor numérico positivo.";
    } elseif ($estoque_db <= 0 && $status === 'ativo') {
        $mensagem_erro = "Anúncios ativos devem ter estoque maior que zero, ou mude o status para 'inativo'.";
    } else {
        try {
            $db->beginTransaction();
            
            $query = "UPDATE produtos SET 
                        nome = :nome, 
                        descricao = :descricao, 
                        preco = :preco, 
                        categoria = :categoria, 
                        estoque = :estoque, 
                        status = :status,
                        imagem_url = :imagem_url_nova,
                        data_atualizacao = NOW()
                      WHERE id = :anuncio_id AND vendedor_id = :vendedor_id";
                      
            $stmt = $db->prepare($query);
            
            // Binding dos parâmetros
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':preco', $preco_db);
            $stmt->bindParam(':categoria', $categoria);
            $stmt->bindParam(':estoque', $estoque_db, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':imagem_url_nova', $imagem_url_nova);
            $stmt->bindParam(':anuncio_id', $anuncio_id);
            $stmt->bindParam(':vendedor_id', $vendedor_id_fk);
            
            if ($stmt->execute()) {
                $db->commit();
                
                // IMPORTANTE: Atualiza a variável $anuncio com os NOVOS DADOS
                $anuncio['nome'] = $nome;
                $anuncio['descricao'] = $descricao;
                $anuncio['preco'] = $preco_db;
                $anuncio['categoria'] = $categoria;
                $anuncio['estoque'] = $estoque_db;
                $anuncio['status'] = $status;
                $anuncio['imagem_url'] = $imagem_url_nova; 
                
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

// Categorias disponíveis - LISTA EXPANDIDA
$categorias_disponiveis = [
    // Frutas
    'Frutas Cítricas',
    'Frutas Tropicais',
    'Frutas de Caroço',
    'Frutas Vermelhas',
    'Frutas Secas',
    'Frutas Exóticas',
    
    // Legumes
    'Legumes Frutíferos',
    'Legumes de Raiz',
    'Legumes de Folha',
    'Legumes de Bulbo',
    
    // Verduras e Folhosas
    'Verduras',
    'Folhosas',
    'Temperos Frescos',
    
    // Grãos e Cereais
    'Grãos',
    'Cereais',
    'Leguminosas',
    
    // Raízes e Tubérculos
    'Raízes',
    'Tubérculos',
    
    // Oleaginosas
    'Oleaginosas',
    'Castanhas e Nozes',
    
    // Produtos Processados
    'Polpas de Fruta',
    'Geleias e Doces',
    'Conservas',
    
    // Orgânicos
    'Produtos Orgânicos',
    
    // Outros
    'Plantas e Mudas',
    'Flores Comestíveis',
    'Ervas Medicinais',
    'Outros'
];

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
                <div class="forms-area">
                    <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                    
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
                        </div>
                        
                        <div class="form-group">
                            <label for="estoque" class="required">Estoque em Kg</label>
                            <input type="number" id="estoque" name="estoque" value="<?php echo htmlspecialchars($anuncio['estoque']); ?>" min="0" required>
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

        // Fechar menu mobile ao clicar em um link
        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));

        // Máscara de preço
        document.addEventListener('DOMContentLoaded', function() {
            const precoInput = document.getElementById('preco');
            
            precoInput.addEventListener('input', function(e) {
                let value = e.target.value;
                value = value.replace(/\D/g, ''); // Remove tudo que não é dígito
                
                if (value) {
                    value = (parseInt(value) / 100).toFixed(2);
                    value = value.replace('.', ',');
                }
                e.target.value = value;
            });

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
                            // Substitui a imagem padrão por uma imagem real
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
        });
    </script>
</body>
</html>