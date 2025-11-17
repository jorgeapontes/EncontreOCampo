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

// Categorias disponíveis
$categorias_disponiveis = [
    'Frutas Cítricas', 
    'Frutas Tropicais', 
    'Frutas de Caroço', 
    'Outras'
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Anúncio - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
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
            <h1>Editar Anúncio: <?php echo htmlspecialchars($anuncio['nome']); ?> (ID: <?php echo $anuncio['id']; ?>)</h1>
        </header>

        <section class="form-section">
            <a href="anuncios.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar para Meus Anúncios</a>

            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="alert success-alert"><i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            <form method="POST" action="anuncio_editar.php" class="anuncio-form" enctype="multipart/form-data">
                <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                
                <h3 style="color: var(--dark-color); margin-bottom: 20px;">Informações Principais</h3>
                
                <div class="form-group">
                    <label for="nome" class="required">Nome da Fruta/Produto</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($anuncio['nome']); ?>" required>
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

                <div class="form-group">
                    <label>Imagem Atual de Capa</label>
                    <?php if (!empty($anuncio['imagem_url']) && file_exists($anuncio['imagem_url'])): ?>
                        <img src="<?php echo htmlspecialchars($anuncio['imagem_url']); ?>" alt="Imagem do Anúncio" style="max-width: 150px; height: auto; display: block; margin-bottom: 10px; border: 1px solid var(--gray);">
                    <?php else: ?>
                        <p>Nenhuma imagem cadastrada.</p>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="imagem_upload">Substituir Imagem de Capa (Opcional)</label>
                    <input type="file" id="imagem_upload" name="imagem_upload" accept="image/jpeg, image/png">
                    <small class="help-text">Máximo 2MB. Formatos: JPG, PNG. Se um arquivo for selecionado, ele substituirá o atual.</small>
                </div>
                <h3 style="color: var(--dark-color); margin-top: 30px; margin-bottom: 20px;">Preço e Estoque</h3>

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
                
                <button type="submit" class="cta-button big-button"><i class="fas fa-save"></i> Salvar Alterações</button>
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