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

// Categorias disponíveis
$categorias_disponiveis = [
    'Frutas Cítricas', 
    'Frutas Tropicais', 
    'Frutas de Caroço', 
    'Outras'
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

    if (empty($mensagem_erro) && isset($_FILES['imagem_upload']) && $_FILES['imagem_upload']['error'] === UPLOAD_ERR_OK) {
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
            } else {
                $mensagem_erro = "Erro ao mover o arquivo para o destino. Verifique as permissões.";
            }
        }
    } elseif (empty($mensagem_erro)) {
        $mensagem_erro = "Por favor, selecione uma imagem de capa para o anúncio.";
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
    if (empty($mensagem_erro) && !empty($imagem_url)) {
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
                $_SESSION['mensagem_anuncio_sucesso'] = "Anúncio **{$nome}** criado com sucesso! Ele já está ativo.";
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
    <link rel="stylesheet" href="../css/vendedor/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    </head>
<body>
    <!-- Nova Navbar no estilo do index.php -->
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link active">Meus Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="propostas.php" class="nav-link">Propostas</a>
                    </li>
                    <li class="nav-item">
                        <a href="precos.php" class="nav-link">Médias de Preços</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link login-button no-underline">Sair</a>
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
        <header class="header">
            <h1>Criar Novo Anúncio</h1>
        </header>

        <section class="form-section">
            <a href="anuncios.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para Meus Anúncios</a>

            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensagem_erro; ?></div>
            <?php endif; ?>
            <form method="POST" action="anuncio_novo.php" class="anuncio-form" enctype="multipart/form-data">
                
                <h3 style="color: var(--dark-color); margin-bottom: 20px;">Detalhes do Produto</h3>

                <div class="form-group">
                    <label for="nome" class="required">Nome da Fruta/Produto</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <select id="categoria" name="categoria">
                        <?php foreach ($categorias_disponiveis as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo ($categoria === $cat) ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="imagem_upload" class="required">Imagem de Capa (Upload)</label>
                    <input type="file" id="imagem_upload" name="imagem_upload" accept="image/jpeg, image/png" required>
                    <small class="help-text">Máximo 2MB. Formatos: JPG, PNG.</small>
                </div>
                <div class="form-row">
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
        });
    </script>
</body>
</html>