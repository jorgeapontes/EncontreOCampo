<?php
// src/vendedor/anuncio_novo.php (CÓDIGO ATUALIZADO)
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
$status = 'ativo';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitização dos dados
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $preco = sanitizeInput($_POST['preco']);
    $categoria = sanitizeInput($_POST['categoria']);
    $estoque = sanitizeInput($_POST['estoque']);
    $status = sanitizeInput($_POST['status']);

    // Conversão de tipos
    $preco_db = str_replace(',', '.', $preco);
    $estoque_db = (int)$estoque;

    // ----------------------------------------------------
    // Lógica de Upload de Imagem
    // ----------------------------------------------------
    $imagem_url = '';
    // Ajuste o caminho conforme a estrutura do seu projeto. 
    // Assumindo que a pasta 'uploads' está um nível acima de 'vendedor/'
    $upload_dir = '../uploads/produtos/';

    // Cria o diretório se ele não existir
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            $mensagem_erro = "Erro interno: Não foi possível criar o diretório de uploads. Verifique as permissões.";
        }
    }

    $upload_ok = false;
    
    if (isset($_FILES['imagem_upload']) && $_FILES['imagem_upload']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['imagem_upload']['name'];
        $file_tmp = $_FILES['imagem_upload']['tmp_name'];
        $file_size = $_FILES['imagem_upload']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        $max_file_size = 2097152; // 2MB

        // Validação do arquivo
        if (!in_array($file_ext, $allowed_extensions)) {
            $mensagem_erro = "Formato de arquivo inválido. Apenas JPG, JPEG e PNG são permitidos.";
        } elseif ($file_size > $max_file_size) {
            $mensagem_erro = "O arquivo é muito grande. O tamanho máximo é 2MB.";
        } else {
            // Gera um nome de arquivo único
            $novo_nome = uniqid('prod_', true) . '.' . $file_ext;
            $destino_servidor = $upload_dir . $novo_nome;

            if (move_uploaded_file($file_tmp, $destino_servidor)) {
                // Salva o caminho RELATIVO no banco de dados para fácil exibição
                $imagem_url = $destino_servidor;
                $upload_ok = true;
            } else {
                $mensagem_erro = "Erro ao mover o arquivo para o destino. Verifique as permissões.";
            }
        }
    } else {
        // Verifica se há erro específico no upload
        if (isset($_FILES['imagem_upload']) && $_FILES['imagem_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            switch ($_FILES['imagem_upload']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $mensagem_erro = "O arquivo é muito grande. O tamanho máximo é 2MB.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $mensagem_erro = "O upload do arquivo foi interrompido.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $mensagem_erro = "Erro de configuração do servidor.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $mensagem_erro = "Erro ao salvar o arquivo no servidor.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $mensagem_erro = "Extensão do arquivo não permitida.";
                    break;
                default:
                    $mensagem_erro = "Erro desconhecido no upload da imagem.";
                    break;
            }
        } else {
            $mensagem_erro = "Por favor, selecione uma imagem de capa para o anúncio.";
        }
    }
    // ----------------------------------------------------

    // 2. Validação final
    if (empty($nome) || empty($preco) || empty($estoque)) {
        $mensagem_erro = "Por favor, preencha os campos obrigatórios.";
    } elseif (!is_numeric($preco_db) || $preco_db <= 0) {
        $mensagem_erro = "O preço deve ser um valor numérico positivo.";
    } elseif ($estoque_db <= 0 && $status === 'ativo') {
        $mensagem_erro = "Anúncios ativos devem ter estoque maior que zero, ou mude o status para 'inativo'.";
    }

    // 3. Inserção no banco de dados
    if (empty($mensagem_erro) && $upload_ok && !empty($imagem_url)) {
        try {
            $db->beginTransaction();

            $query = "INSERT INTO produtos (vendedor_id, nome, descricao, preco, categoria, estoque, status, imagem_url, data_criacao)
                      VALUES (:vendedor_id, :nome, :descricao, :preco, :categoria, :estoque, :status, :imagem_url, NOW())";

            $stmt = $db->prepare($query);

            $stmt->bindParam(':vendedor_id', $vendedor_id_fk);
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':preco', $preco_db);
            $stmt->bindParam(':categoria', $categoria);
            $stmt->bindParam(':estoque', $estoque_db, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':imagem_url', $imagem_url); // O novo caminho da imagem

            if ($stmt->execute()) {
                $db->commit();
                $_SESSION['mensagem_anuncio_sucesso'] = "Anúncio {$nome} criado com sucesso! Ele já está ativo.";
                header("Location: anuncios.php");
                exit();
            } else {
                $db->rollBack();
                $mensagem_erro = "Erro ao salvar o anúncio. Tente novamente.";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $mensagem_erro = "Erro de banco de dados: " . $e->getMessage();
        }
    }
}

// Formata o preço para exibição no input (com vírgula)
$preco_formatado = number_format((float)$preco, 2, ',', '');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo Anúncio - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/anuncio_novo.css">
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
                <h1>Criar Novo Anúncio</h1>
            </header>
        </center>

        <section class="form-section">
            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensagem_erro; ?></div>
            <?php endif; ?>
            <form method="POST" action="anuncio_novo.php" class="anuncio-form" enctype="multipart/form-data">
                <div class="forms-area">
                    <div class="top-info">
                        <div class="form-group">
                            <div class="foto-produto-container">
                                <div class="foto-produto-display">
                                    <div class="default-image">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <div class="foto-overlay">
                                        <i class="fas fa-pencil-alt"></i>
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
                                    <optgroup label="Frutas">
                                        <option value="Frutas Cítricas" <?php echo ($categoria === 'Frutas Cítricas') ? 'selected' : ''; ?>>Frutas Cítricas</option>
                                        <option value="Frutas Tropicais" <?php echo ($categoria === 'Frutas Tropicais') ? 'selected' : ''; ?>>Frutas Tropicais</option>
                                        <option value="Frutas de Caroço" <?php echo ($categoria === 'Frutas de Caroço') ? 'selected' : ''; ?>>Frutas de Caroço</option>
                                        <option value="Frutas Vermelhas" <?php echo ($categoria === 'Frutas Vermelhas') ? 'selected' : ''; ?>>Frutas Vermelhas</option>
                                        <option value="Frutas Secas" <?php echo ($categoria === 'Frutas Secas') ? 'selected' : ''; ?>>Frutas Secas</option>
                                        <option value="Frutas Exóticas" <?php echo ($categoria === 'Frutas Exóticas') ? 'selected' : ''; ?>>Frutas Exóticas</option>
                                    </optgroup>

                                    <optgroup label="Legumes">
                                        <option value="Legumes Frutíferos" <?php echo ($categoria === 'Legumes Frutíferos') ? 'selected' : ''; ?>>Legumes Frutíferos</option>
                                        <option value="Legumes de Raiz" <?php echo ($categoria === 'Legumes de Raiz') ? 'selected' : ''; ?>>Legumes de Raiz</option>
                                        <option value="Legumes de Folha" <?php echo ($categoria === 'Legumes de Folha') ? 'selected' : ''; ?>>Legumes de Folha</option>
                                        <option value="Legumes de Bulbo" <?php echo ($categoria === 'Legumes de Bulbo') ? 'selected' : ''; ?>>Legumes de Bulbo</option>
                                    </optgroup>

                                    <optgroup label="Verduras">
                                        <option value="Verduras" <?php echo ($categoria === 'Verduras') ? 'selected' : ''; ?>>Verduras</option>
                                        <option value="Folhosas" <?php echo ($categoria === 'Folhosas') ? 'selected' : ''; ?>>Folhosas</option>
                                        <option value="Temperos Frescos" <?php echo ($categoria === 'Temperos Frescos') ? 'selected' : ''; ?>>Temperos Frescos</option>
                                    </optgroup>

                                    <optgroup label="Grãos e Cereais">
                                        <option value="Grãos" <?php echo ($categoria === 'Grãos') ? 'selected' : ''; ?>>Grãos</option>
                                        <option value="Cereais" <?php echo ($categoria === 'Cereais') ? 'selected' : ''; ?>>Cereais</option>
                                        <option value="Leguminosas" <?php echo ($categoria === 'Leguminosas') ? 'selected' : ''; ?>>Leguminosas</option>
                                    </optgroup>

                                    <optgroup label="Raízes e Tubérculos">
                                        <option value="Raízes" <?php echo ($categoria === 'Raízes') ? 'selected' : ''; ?>>Raízes</option>
                                        <option value="Tubérculos" <?php echo ($categoria === 'Tubérculos') ? 'selected' : ''; ?>>Tubérculos</option>
                                    </optgroup>

                                    <optgroup label="Oleaginosas">
                                        <option value="Oleaginosas" <?php echo ($categoria === 'Oleaginosas') ? 'selected' : ''; ?>>Oleaginosas</option>
                                        <option value="Castanhas e Nozes" <?php echo ($categoria === 'Castanhas e Nozes') ? 'selected' : ''; ?>>Castanhas e Nozes</option>
                                    </optgroup>

                                    <optgroup label="Produtos Processados">
                                        <option value="Polpas de Fruta" <?php echo ($categoria === 'Polpas de Fruta') ? 'selected' : ''; ?>>Polpas de Fruta</option>
                                        <option value="Geleias e Doces" <?php echo ($categoria === 'Geleias e Doces') ? 'selected' : ''; ?>>Geleias e Doces</option>
                                        <option value="Conservas" <?php echo ($categoria === 'Conservas') ? 'selected' : ''; ?>>Conservas</option>
                                    </optgroup>

                                    <optgroup label="Especiais">
                                        <option value="Produtos Orgânicos" <?php echo ($categoria === 'Produtos Orgânicos') ? 'selected' : ''; ?>>Produtos Orgânicos</option>
                                        <option value="Plantas e Mudas" <?php echo ($categoria === 'Plantas e Mudas') ? 'selected' : ''; ?>>Plantas e Mudas</option>
                                        <option value="Flores Comestíveis" <?php echo ($categoria === 'Flores Comestíveis') ? 'selected' : ''; ?>>Flores Comestíveis</option>
                                        <option value="Ervas Medicinais" <?php echo ($categoria === 'Ervas Medicinais') ? 'selected' : ''; ?>>Ervas Medicinais</option>
                                        <option value="Outros" <?php echo ($categoria === 'Outros') ? 'selected' : ''; ?>>Outros</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                
                    <div class="form-group" style="display: none;">
                        <label for="imagem_upload" class="required">Imagem de Capa do Produto</label>
                        <input type="file" id="imagem_upload" name="imagem_upload" accept="image/jpeg, image/png" required>
                        <small class="help-text">Formatos permitidos: JPG, JPEG, PNG. Tamanho máximo: 2MB. Clique na imagem acima para selecionar.</small>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="preco" class="required">Preço por Kg (R$)</label>
                            <input type="text" id="preco" name="preco" value="<?php echo htmlspecialchars($preco_formatado); ?>" placeholder="Ex: 5,50" required>
                        </div>

                        <div class="form-group">
                            <label for="estoque" class="required">Estoque em Kg</label>
                            <input type="number" id="estoque" name="estoque" value="<?php echo htmlspecialchars($estoque); ?>" min="0" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição Detalhada do Produto (Opcional)</label>
                        <textarea id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($descricao); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="status" class="required">Status do Anúncio</label>
                        <select id="status" name="status" required>
                            <option value="ativo" <?php echo ($status === 'ativo') ? 'selected' : ''; ?>>Ativo (Visível)</option>
                            <option value="inativo" <?php echo ($status === 'inativo') ? 'selected' : ''; ?>>Inativo (Pausado)</option>
                        </select>
                    </div>
                    <button type="submit" class="cta-button big-button"><i class="fas fa-bullhorn"></i> Anunciar Produto</button>
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
                    const defaultImage = document.querySelector('.default-image');
                    
                    reader.onload = function(e) {
                        if (defaultImage) {
                            // Substitui a imagem padrão por uma imagem real
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.alt = "Imagem do Produto";
                            newImg.style.width = '300px';
                            newImg.style.height = '250px';
                            newImg.style.objectFit = 'cover';
                            newImg.style.borderRadius = '5px';
                            newImg.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.1)';
                            
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