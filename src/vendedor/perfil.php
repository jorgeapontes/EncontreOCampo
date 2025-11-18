<?php
// src/vendedor/perfil.php
require_once 'auth.php'; // Inclui a proteção de acesso e carrega $vendedor, $db, $usuario

$mensagem_sucesso = '';
$mensagem_erro = '';
$vendedor_id_fk = $vendedor['id'];

// Define o caminho onde as fotos de perfil serão salvas
$upload_dir = '../uploads/vendedores/'; 

// Garante que o diretório de uploads exista
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ----------------------------------------------------
// Lógica de Atualização (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitizeInput($_POST['nome'] ?? $usuario['nome']);
    $telefone1 = sanitizeInput($_POST['telefone1'] ?? $vendedor['telefone1']);
    // Outros dados do vendedor, se você tiver campos de edição para eles
    $razao_social = sanitizeInput($_POST['razao_social'] ?? $vendedor['razao_social']);

    $foto_perfil_antiga = $vendedor['foto_perfil_url'];
    $foto_perfil_nova = $foto_perfil_antiga; // Mantém a antiga por padrão
    
    // 1. Processamento da Foto de Perfil (Upload)
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['foto_perfil']['name'];
        $file_tmp = $_FILES['foto_perfil']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        $max_file_size = 1048576; // 1MB

        if (!in_array($file_ext, $allowed_extensions)) {
            $mensagem_erro = "Formato de arquivo inválido. Apenas JPG, JPEG e PNG são permitidos para a foto de perfil.";
        } elseif ($_FILES['foto_perfil']['size'] > $max_file_size) {
            $mensagem_erro = "A foto de perfil é muito grande. O tamanho máximo é 1MB.";
        } else {
            // Gera um nome de arquivo único (ex: vend_5_hash.jpg)
            $novo_nome = 'vend_' . $vendedor_id_fk . '_' . uniqid() . '.' . $file_ext;
            $destino_servidor = $upload_dir . $novo_nome;

            if (move_uploaded_file($file_tmp, $destino_servidor)) {
                $foto_perfil_nova = $destino_servidor; 

                // Deleta a foto antiga, se existir e não for a imagem padrão (se houver)
                if (!empty($foto_perfil_antiga) && file_exists($foto_perfil_antiga)) {
                    unlink($foto_perfil_antiga);
                }
            } else {
                $mensagem_erro = "Erro ao mover o novo arquivo para o destino.";
            }
        }
    }

    // 2. Validação e Atualização no Banco de Dados
    if (empty($mensagem_erro)) {
        try {
            $db->beginTransaction();

            // A. Atualiza tabela USUARIOS (Nome)
            $query_user = "UPDATE usuarios SET nome = :nome WHERE id = :usuario_id";
            $stmt_user = $db->prepare($query_user);
            $stmt_user->bindParam(':nome', $nome);
            $stmt_user->bindParam(':usuario_id', $usuario['id']);
            $stmt_user->execute();
            
            // B. Atualiza tabela VENDEDORES (Foto de Perfil, Telefone, Razão Social, etc.)
            $query_vend = "UPDATE vendedores SET 
                                telefone1 = :telefone1,
                                razao_social = :razao_social,
                                foto_perfil_url = :foto_perfil_nova
                            WHERE id = :vendedor_id";
            $stmt_vend = $db->prepare($query_vend);
            $stmt_vend->bindParam(':telefone1', $telefone1);
            $stmt_vend->bindParam(':razao_social', $razao_social);
            $stmt_vend->bindParam(':foto_perfil_nova', $foto_perfil_nova);
            $stmt_vend->bindParam(':vendedor_id', $vendedor_id_fk);
            $stmt_vend->execute();

            $db->commit();
            
            // Recarrega os dados para exibir na tela
            $usuario['nome'] = $nome;
            $vendedor['telefone1'] = $telefone1;
            $vendedor['razao_social'] = $razao_social;
            $vendedor['foto_perfil_url'] = $foto_perfil_nova; 

            $mensagem_sucesso = "Seu perfil foi atualizado com sucesso!";
        } catch (PDOException $e) {
            $db->rollBack();
            $mensagem_erro = "Erro de banco de dados ao atualizar: " . $e->getMessage();
        }
    }
}

// Garante que a URL da foto de perfil seja carregada para exibição
$foto_perfil_url = $vendedor['foto_perfil_url'] ?? '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/perfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Nova Navbar no estilo do index.php -->
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
                        <a href="#" class="nav-link active">Meu Perfil</a>
                    </li>
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
                <h1>Meu Perfil</h1>
            </header>
        </center>

        <section class="section-perfil">
            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="alert success-alert"><i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            <form method="POST" action="perfil.php" class="perfil-form" enctype="multipart/form-data">
                <div class="perfil-header-info">
                    <center>
                        <div class="foto-perfil-display">
                            <img id="profile-img-preview" 
                                src="<?php 
                                    echo (!empty($foto_perfil_url) && file_exists($foto_perfil_url)) 
                                        ? htmlspecialchars($foto_perfil_url) 
                                        : 'caminho/para/imagem/padrao.png'; // Substitua pelo caminho da sua imagem padrão
                                ?>" 
                            alt="Foto de Perfil">
                            
                            <div class="form-group">
                                <label for="foto_perfil" class="required">Alterar Foto de Perfil</label>
                                <input type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg, image/png">
                                <small class="help-text">Máximo 1MB. Formatos: JPG, PNG.</small>
                            </div>
                        </div>
                    </center>
                </div>

                <div class="forms-area">
                    <h3 style="margin-top: 20px;">Dados do Usuário (Conta)</h3>
                    <div class="form-group">
                        <label for="nome" class="required">Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email (Não Editável)</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" disabled>
                    </div>

                    <h3 style="margin-top: 30px;">Dados do Vendedor (Empresa)</h3>
                    
                    <div class="form-group">
                        <label for="razao_social">Razão Social / Nome da Loja</label>
                        <input type="text" id="razao_social" name="razao_social" value="<?php echo htmlspecialchars($vendedor['razao_social'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>CPF/CNPJ</label>
                            <input type="text" value="<?php echo htmlspecialchars($vendedor['cpf_cnpj'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="telefone1" class="required">Telefone Principal</label>
                            <input type="text" id="telefone1" name="telefone1" value="<?php echo htmlspecialchars($vendedor['telefone1'] ?? ''); ?>" required>
                        </div>
                    </div>
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

        // Preview da imagem
        document.getElementById('foto_perfil').addEventListener('change', function(event) {
            const [file] = event.target.files;
            if (file) {
                document.getElementById('profile-img-preview').src = URL.createObjectURL(file);
            }
        });
    </script>
</body>
</html>