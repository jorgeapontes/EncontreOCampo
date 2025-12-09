<?php
// src/comprador/perfil.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Comprador');

$database = new Database();
$conn = $database->getConnection();
$comprador_data = null;
$mensagem_sucesso = isset($_GET['sucesso']) ? htmlspecialchars($_GET['sucesso']) : null;
$mensagem_erro = isset($_GET['erro']) ? htmlspecialchars($_GET['erro']) : null;

// 2. BUSCAR DADOS DO COMPRADOR
try {
    $sql = "SELECT 
                c.*,
                u.email,
                u.nome as nome_usuario
            FROM compradores c
            JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.usuario_id = :usuario_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $comprador_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comprador_data) {
        die("Perfil de comprador não encontrado.");
    }

} catch (PDOException $e) {
    die("Erro ao carregar perfil: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Comprador</title>
    <link rel="stylesheet" href="../css/comprador/perfil.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                        <a href="dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link active">Meu Perfil</a>
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

            <form method="POST" action="atualizar_perfil.php" class="perfil-form" enctype="multipart/form-data">
                <div class="perfil-header-info">
                    <center>
                        <div class="foto-perfil-container">
                            <div class="foto-perfil-display">
                                <img id="profile-img-preview" 
                                    src="<?php 
                                        echo (!empty($comprador_data['foto_perfil_url']) && file_exists($comprador_data['foto_perfil_url'])) 
                                            ? htmlspecialchars($comprador_data['foto_perfil_url']) 
                                            : '../../img/no-user-image.png';
                                    ?>" 
                                alt="Foto de Perfil">
                                <div class="foto-overlay">
                                    <i class="fas fa-pencil-alt"></i>
                                </div>
                            </div>
                        </div>
                        <input type="file" id="foto_perfil" name="foto_perfil" accept="image/jpeg,image/png,image/gif" style="display: none;">
                    </center>
                </div>

                <div class="forms-area">
                    <h2>Dados do usuário</h2>
                    
                    <div class="form-group">
                        <label for="nome" class="required">Nome Completo</label>
                        <input type="text" id="nome" name="nome" 
                            value="<?php echo htmlspecialchars($comprador_data['nome_usuario'] ?? ''); ?>" 
                            required>
                    </div>

                    <div class="form-group">
                        <label for="nome_comercial">Nome Comercial (Opcional)</label>
                        <input type="text" id="nome_comercial" name="nome_comercial" 
                            value="<?php echo htmlspecialchars($comprador_data['nome_comercial'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email (Não Editável)</label>
                        <input type="email" id="email" name="email" 
                            value="<?php echo htmlspecialchars($comprador_data['email'] ?? ''); ?>" 
                            disabled>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="cpf_cnpj">CPF/CNPJ (Não Editável)</label>
                            <input type="text" id="cpf_cnpj" name="cpf_cnpj" 
                                value="<?php echo htmlspecialchars($comprador_data['cpf_cnpj'] ?? ''); ?>" 
                                disabled>
                        </div>
                        <div class="form-group">
                            <label for="tipo_pessoa" class="required">Tipo de Pessoa</label>
                            <select id="tipo_pessoa" name="tipo_pessoa" required>
                                <option value="cpf" <?php echo ($comprador_data['tipo_pessoa'] ?? '') == 'cpf' ? 'selected' : ''; ?>>CPF (Pessoa Física)</option>
                                <option value="cnpj" <?php echo ($comprador_data['tipo_pessoa'] ?? '') == 'cnpj' ? 'selected' : ''; ?>>CNPJ (Pessoa Jurídica)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="telefone1" class="required">Telefone Principal</label>
                            <input type="tel" id="telefone1" name="telefone1" 
                                value="<?php echo htmlspecialchars($comprador_data['telefone1'] ?? ''); ?>" 
                                required>
                        </div>
                        <div class="form-group">
                            <label for="telefone2">Telefone Secundário (Opcional)</label>
                            <input type="tel" id="telefone2" name="telefone2" 
                                value="<?php echo htmlspecialchars($comprador_data['telefone2'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="cip">CIP (Cadastro de Inadimplente do Produtor)</label>
                        <input type="text" id="cip" name="cip" 
                            value="<?php echo htmlspecialchars($comprador_data['cip'] ?? ''); ?>">
                    </div>

                    <h2>Endereço</h2>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="cep" class="required">CEP</label>
                            <input type="text" id="cep" name="cep" 
                                value="<?php echo htmlspecialchars($comprador_data['cep'] ?? ''); ?>" 
                                required>
                        </div>

                        <div class="form-group">
                            <label for="estado" class="required">Estado</label>
                            <input type="text" id="estado" name="estado" 
                                value="<?php echo htmlspecialchars($comprador_data['estado'] ?? ''); ?>" 
                                required maxlength="2">
                        </div>

                        <div class="form-group">
                            <label for="cidade" class="required">Cidade</label>
                            <input type="text" id="cidade" name="cidade" 
                                value="<?php echo htmlspecialchars($comprador_data['cidade'] ?? ''); ?>" 
                                required>
                        </div>
                    </div>

                    

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="rua" class="required">Rua</label>
                            <input type="text" id="rua" name="rua" 
                                value="<?php echo htmlspecialchars($comprador_data['rua'] ?? ''); ?>" 
                                required>
                        </div>
                        <div class="form-group">
                            <label for="numero" class="required">Número</label>
                            <input type="text" id="numero" name="numero" 
                                value="<?php echo htmlspecialchars($comprador_data['numero'] ?? ''); ?>" 
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="complemento">Complemento (Opcional)</label>
                        <input type="text" id="complemento" name="complemento" 
                            value="<?php echo htmlspecialchars($comprador_data['complemento'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="plano">Plano Atual</label>
                        <input type="text" id="plano" name="plano" 
                            value="<?php echo htmlspecialchars($comprador_data['plano'] ?? 'free'); ?>" 
                            disabled>
                        <small>Altere aqui seu plano.</small>
                    </div>
                    
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario_id; ?>">
                    <input type="hidden" name="comprador_id" value="<?php echo $comprador_data['id'] ?? ''; ?>">
                    
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

        // Script para foto de perfil
        const fotoPerfilDisplay = document.querySelector('.foto-perfil-display');
        const fotoPerfilInput = document.getElementById('foto_perfil');
        const profileImgPreview = document.getElementById('profile-img-preview');

        fotoPerfilDisplay.addEventListener('click', () => {
            fotoPerfilInput.click();
        });

        fotoPerfilInput.addEventListener('change', function(event) {
            const [file] = event.target.files;
            if (file) {
                // Verificar tamanho (máximo 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('A imagem deve ter no máximo 5MB.');
                    this.value = '';
                    return;
                }

                // Verificar tipo
                const tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
                if (!tiposPermitidos.includes(file.type)) {
                    alert('Formato inválido. Use JPG, PNG ou GIF.');
                    this.value = '';
                    return;
                }

                // Pré-visualização
                const reader = new FileReader();
                reader.onload = function(e) {
                    profileImgPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Validação de CEP (opcional)
        const cepInput = document.getElementById('cep');
        if (cepInput) {
            cepInput.addEventListener('blur', function() {
                let cep = this.value.replace(/\D/g, '');
                if (cep.length === 8) {
                    // Formatar CEP
                    this.value = cep.substring(0, 5) + '-' + cep.substring(5);
                }
            });
        }
    </script>
</body>
</html>