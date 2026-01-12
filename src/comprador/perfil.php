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
$database = new Database();
$conn = $database->getConnection();

$mensagem_sucesso = null;
$mensagem_erro = null;

// --- INÍCIO DA LÓGICA DE ATUALIZAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
    try {
        $conn->beginTransaction();

        // 1. Atualizar tabela USUARIOS (Nome e Email)
        $sql_u = "UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id";
        $stmt_u = $conn->prepare($sql_u);
        $stmt_u->execute([
            ':nome'  => $_POST['nome'],
            ':email' => $_POST['email'],
            ':id'    => $usuario_id
        ]);

        // 2. Atualizar tabela COMPRADORES
        $sql_c = "UPDATE compradores SET 
                    nome_comercial = :nome_comercial,
                    tipo_pessoa = :tipo_pessoa,
                    telefone1 = :tel1,
                    telefone2 = :tel2,
                    cip = :cip,
                    cep = :cep,
                    estado = :estado,
                    cidade = :cidade,
                    rua = :rua,
                    numero = :numero,
                    complemento = :complemento
                  WHERE usuario_id = :id";
        
        $stmt_c = $conn->prepare($sql_c);
        $stmt_c->execute([
            ':nome_comercial' => $_POST['nome_comercial'],
            ':tipo_pessoa'    => $_POST['tipo_pessoa'],
            ':tel1'           => $_POST['telefone1'],
            ':tel2'           => $_POST['telefone2'],
            ':cip'            => $_POST['cip'],
            ':cep'            => $_POST['cep'],
            ':estado'         => $_POST['estado'],
            ':cidade'         => $_POST['cidade'],
            ':rua'            => $_POST['rua'],
            ':numero'         => $_POST['numero'],
            ':complemento'    => $_POST['complemento'],
            ':id'             => $usuario_id
        ]);

        $conn->commit();
        $_SESSION['usuario_nome'] = $_POST['nome']; // Atualiza nome na sessão
        $mensagem_sucesso = "Perfil e e-mail atualizados com sucesso!";
    } catch (Exception $e) {
        $conn->rollBack();
        $mensagem_erro = "Erro ao atualizar: " . $e->getMessage();
    }
}
// --- FIM DA LÓGICA DE ATUALIZAÇÃO ---

// 2. BUSCAR DADOS ATUALIZADOS DO COMPRADOR
try {
    $sql = "SELECT c.*, u.email, u.nome as nome_usuario 
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
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
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="../anuncios.php" class="nav-link">Anúncios</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link active">Meu Perfil</a></li>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="main-content">
        <center><header class="header"><h1>Meu Perfil</h1></header></center>

        <section class="section-perfil">
            <?php if ($mensagem_sucesso): ?>
                <div class="alert success-alert"><i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?></div>
            <?php endif; ?>
            
            <?php if ($mensagem_erro): ?>
                <div class="alert error-alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="perfil-form" enctype="multipart/form-data">
                <input type="hidden" name="atualizar_perfil" value="1">
                
                <div class="perfil-header-info">
                    <center>
                        <div class="foto-perfil-container">
                            <div class="foto-perfil-display">
                                <img id="profile-img-preview" src="<?php echo (!empty($comprador_data['foto_perfil_url']) && file_exists($comprador_data['foto_perfil_url'])) ? htmlspecialchars($comprador_data['foto_perfil_url']) : '../../img/no-user-image.png'; ?>" alt="Foto de Perfil">
                                <div class="foto-overlay"><i class="fas fa-pencil-alt"></i></div>
                            </div>
                        </div>
                        <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display: none;">
                    </center>
                </div>

                <div class="forms-area">
                    <h2>Dados do usuário</h2>
                    
                    <div class="form-group">
                        <label for="nome" class="required">Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($comprador_data['nome_usuario']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="required">E-mail (Editável)</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($comprador_data['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nome_comercial">Nome Comercial</label>
                        <input type="text" id="nome_comercial" name="nome_comercial" value="<?php echo htmlspecialchars($comprador_data['nome_comercial'] ?? ''); ?>">
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label>CPF/CNPJ (Fixo)</label>
                            <input type="text" value="<?php echo htmlspecialchars($comprador_data['cpf_cnpj']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="tipo_pessoa" class="required">Tipo</label>
                            <select id="tipo_pessoa" name="tipo_pessoa" required>
                                <option value="cpf" <?php echo $comprador_data['tipo_pessoa'] == 'cpf' ? 'selected' : ''; ?>>CPF</option>
                                <option value="cnpj" <?php echo $comprador_data['tipo_pessoa'] == 'cnpj' ? 'selected' : ''; ?>>CNPJ</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group"><label class="required">Telefone 1</label><input type="tel" name="telefone1" value="<?php echo htmlspecialchars($comprador_data['telefone1']); ?>" required></div>
                        <div class="form-group"><label>Telefone 2</label><input type="tel" name="telefone2" value="<?php echo htmlspecialchars($comprador_data['telefone2']); ?>"></div>
                    </div>

                    <h2>Endereço</h2>
                    <div class="form-group-row">
                        <div class="form-group"><label class="required">CEP</label><input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($comprador_data['cep']); ?>" required></div>
                        <div class="form-group"><label class="required">Estado</label><input type="text" name="estado" value="<?php echo htmlspecialchars($comprador_data['estado']); ?>" required maxlength="2"></div>
                        <div class="form-group"><label class="required">Cidade</label><input type="text" name="cidade" value="<?php echo htmlspecialchars($comprador_data['cidade']); ?>" required></div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group"><label class="required">Rua</label><input type="text" name="rua" value="<?php echo htmlspecialchars($comprador_data['rua']); ?>" required></div>
                        <div class="form-group"><label class="required">Número</label><input type="text" name="numero" value="<?php echo htmlspecialchars($comprador_data['numero']); ?>" required></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cip">CIP</label>
                        <input type="text" name="cip" value="<?php echo htmlspecialchars($comprador_data['cip'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="complemento">Complemento</label>
                        <input type="text" name="complemento" value="<?php echo htmlspecialchars($comprador_data['complemento'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="big-button"><i class="fas fa-save"></i> Salvar Alterações</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        // Preview da foto
        const fotoPerfilDisplay = document.querySelector('.foto-perfil-display');
        const fotoPerfilInput = document.getElementById('foto_perfil');
        fotoPerfilDisplay.addEventListener('click', () => fotoPerfilInput.click());
        fotoPerfilInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => document.getElementById('profile-img-preview').src = e.target.result;
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>