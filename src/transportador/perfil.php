<?php
// src/transportador/perfil.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php'; 

$database = new Database();
$db = $database->getConnection();

// Garante que as variáveis existam como arrays vazios caso auth.php não as crie
if (!isset($usuario)) $usuario = [];
if (!isset($transportador)) $transportador = [];

// --- 1. BUSCAR DADOS FRESCOS DO USUÁRIO ---
$stmt_user = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
if (isset($_SESSION['usuario_id'])) {
    $stmt_user->execute([$_SESSION['usuario_id']]);
    $usuario_real = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($usuario_real) {
        $usuario = is_array($usuario) ? array_merge($usuario, $usuario_real) : $usuario_real;
    }
}

// --- 2. BUSCAR DADOS COMPLETOS DO TRANSPORTADOR ---
if (!isset($transportador['id']) && isset($_SESSION['usuario_id'])) {
    $stmt_busca_trans = $db->prepare("SELECT id FROM transportadores WHERE usuario_id = ?");
    $stmt_busca_trans->execute([$_SESSION['usuario_id']]);
    $res_trans = $stmt_busca_trans->fetch(PDO::FETCH_ASSOC);
    if ($res_trans) {
        $transportador['id'] = $res_trans['id'];
    }
}

if (isset($transportador['id'])) {
    $stmt_completo = $db->prepare("SELECT * FROM transportadores WHERE id = ?");
    $stmt_completo->execute([$transportador['id']]);
    $dados_completos = $stmt_completo->fetch(PDO::FETCH_ASSOC);

    if ($dados_completos) {
        $transportador = is_array($transportador) ? array_merge($transportador, $dados_completos) : $dados_completos;
    }
}

$mensagem_sucesso = '';
$mensagem_erro = '';

$transportador_id_fk = $transportador['id'] ?? 0;

// Diretório de upload relativo ao arquivo atual
$upload_dir = '../uploads/transportadores/'; 
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- LÓGICA DE SALVAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? ($usuario['nome'] ?? '');
    $email = $_POST['email'] ?? ($usuario['email'] ?? '');
    $telefone = $_POST['telefone'] ?? ($transportador['telefone'] ?? '');
    $nome_comercial = $_POST['nome_comercial'] ?? ($transportador['nome_comercial'] ?? '');
    $numero_antt = $_POST['numero_antt'] ?? ($transportador['numero_antt'] ?? '');
    $placa_veiculo = $_POST['placa_veiculo'] ?? ($transportador['placa_veiculo'] ?? '');
    $modelo_veiculo = $_POST['modelo_veiculo'] ?? ($transportador['modelo_veiculo'] ?? '');
    $descricao_veiculo = $_POST['descricao_veiculo'] ?? ($transportador['descricao_veiculo'] ?? '');
    $estado = $_POST['estado'] ?? ($transportador['estado'] ?? '');
    $cidade = $_POST['cidade'] ?? ($transportador['cidade'] ?? '');
    $foto_perfil_antiga = $transportador['foto_perfil_url'] ?? '';
    $foto_perfil_nova = $foto_perfil_antiga;
    
    // Upload Imagem
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = 'perfil_' . $transportador_id_fk . '_' . time() . '.' . $file_extension;
            $dest_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $dest_path)) {
                // Salvar o caminho relativo correto no banco
                $foto_perfil_nova = '../uploads/transportadores/' . $new_file_name;
                
                // Deletar foto antiga se existir e não for a padrão
                if ($foto_perfil_antiga && file_exists($foto_perfil_antiga) && strpos($foto_perfil_antiga, 'no-user-image') === false) {
                    @unlink($foto_perfil_antiga);
                }
            }
        }
    }

    try {
        $db->beginTransaction();
        
        // 1. Atualiza USUÁRIO
        $stmt_u = $db->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
        $stmt_u->execute([$nome, $email, $_SESSION['usuario_id']]);

        // 2. Atualiza TRANSPORTADOR - ADICIONE O CAMPO foto_perfil_url
        $query_transportador = "UPDATE transportadores SET 
            telefone = ?, 
            nome_comercial = ?, 
            numero_antt = ?,
            placa_veiculo = ?,
            modelo_veiculo = ?,
            descricao_veiculo = ?,
            estado = ?,
            cidade = ?,
            foto_perfil_url = ?
            WHERE id = ?";
            
        $stmt_t = $db->prepare($query_transportador);
        $stmt_t->execute([
            $telefone, 
            $nome_comercial, 
            $numero_antt,
            $placa_veiculo,
            $modelo_veiculo,
            $descricao_veiculo,
            $estado,
            $cidade,
            $foto_perfil_nova,  // NOVO CAMPO
            $transportador_id_fk
        ]);

        $db->commit();
        $mensagem_sucesso = "Perfil atualizado com sucesso!";
        
        // Atualiza variáveis visuais - ADICIONE A FOTO
        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
        $transportador['telefone'] = $telefone;
        $transportador['nome_comercial'] = $nome_comercial;
        $transportador['numero_antt'] = $numero_antt;
        $transportador['placa_veiculo'] = $placa_veiculo;
        $transportador['modelo_veiculo'] = $modelo_veiculo;
        $transportador['descricao_veiculo'] = $descricao_veiculo;
        $transportador['estado'] = $estado;
        $transportador['cidade'] = $cidade;
        $transportador['foto_perfil_url'] = $foto_perfil_nova;  // NOVO CAMPO
        
        // Refresh após 2 segundos
        header("Refresh: 2");

    } catch (Exception $e) {
        $db->rollBack();
        $mensagem_erro = "Erro ao atualizar: " . $e->getMessage();
    }

}
// Função para verificar e retornar o caminho correto da imagem
function getImagePath($path) {
    if (empty($path)) {
        return '../../img/no-user-image.png';
    }
    
    // Se o caminho já começa com ../, usa direto
    if (strpos($path, '../') === 0) {
        return file_exists($path) ? $path : '../../img/no-user-image.png';
    }
    
    // Se começa com src/, ajusta
    if (strpos($path, 'src/') === 0) {
        $adjusted_path = '../../' . $path;
        return file_exists($adjusted_path) ? $adjusted_path : '../../img/no-user-image.png';
    }
    
    return file_exists($path) ? $path : '../../img/no-user-image.png';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Transportador</title>
    <link rel="stylesheet" href="../css/transportador/perfil.css">
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
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="../anuncios.php" class="nav-link">Anúncios</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="" class="nav-link active">Meu Perfil</a></li>
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
            <header class="header"><h1>Meu Perfil</h1></header>
        </center>

        <section class="section-perfil">
            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="alert success-alert"><i class="fas fa-check-circle"></i> <?php echo $mensagem_sucesso; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert error-alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensagem_erro; ?></div>
            <?php endif; ?>

            <!-- ADICIONE ESTE BLOCO -->
            <div class="perfil-header-info">
                <center>
                    <div class="foto-perfil-container">
                        <div class="foto-perfil-display">
                            <img id="profile-img-preview" 
                                src="<?php echo getImagePath($transportador['foto_perfil_url'] ?? ''); ?>" 
                                alt="Foto de Perfil">
                            <div class="foto-overlay"><i class="fas fa-pencil-alt"></i></div>
                        </div>
                        <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display: none;">
                    </div>
                </center>
            </div>

    <form method="POST" action="perfil.php" class="perfil-form" enctype="multipart/form-data">
                <div class="forms-area">
                    <h2>Dados do usuário</h2>
                    
                    <div class="form-group">
                        <label for="nome" class="required">Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" required>
                    </div>

                    <h2>Dados do Transportador</h2>
                    
                    <div class="form-group">
                        <label for="nome_comercial">Nome Comercial / Nome da Empresa</label>
                        <input type="text" id="nome_comercial" name="nome_comercial" value="<?php echo htmlspecialchars($transportador['nome_comercial'] ?? ''); ?>">
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="telefone" class="required">Telefone Principal</label>
                            <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($transportador['telefone'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="numero_antt" class="required">Número ANTT</label>
                            <input type="text" id="numero_antt" name="numero_antt" value="<?php echo htmlspecialchars($transportador['numero_antt'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="placa_veiculo" class="required">Placa do Veículo</label>
                            <input type="text" id="placa_veiculo" name="placa_veiculo" value="<?php echo htmlspecialchars($transportador['placa_veiculo'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="modelo_veiculo" class="required">Modelo do Veículo</label>
                            <input type="text" id="modelo_veiculo" name="modelo_veiculo" value="<?php echo htmlspecialchars($transportador['modelo_veiculo'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descricao_veiculo">Descrição do Veículo</label>
                        <textarea id="descricao_veiculo" name="descricao_veiculo" rows="4"><?php echo htmlspecialchars($transportador['descricao_veiculo'] ?? ''); ?></textarea>
                    </div>

                    <h3>Endereço onde está instalado</h3>
                    
                    <div class="form-group-row">
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado">
                                <option value="">UF</option>
                                <?php
                                $estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                                foreach ($estados as $uf) {
                                    $selected = (isset($transportador['estado']) && $transportador['estado'] == $uf) ? 'selected' : '';
                                    echo "<option value='$uf' $selected>$uf</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 2;">
                            <label for="cidade">Cidade</label>
                            <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($transportador['cidade'] ?? ''); ?>">
                        </div>
                    </div>
                
                    <button type="submit" class="big-button"><i class="fas fa-save"></i> Salvar Alterações</button>
                    
                    <center>
                        <a href="#" id="delete-account-link" style="display: inline-block; margin-top: 20px; color: #666; text-decoration: none; font-size: 0.9rem;">
                            <i class="fas fa-trash-alt"></i> Apagar minha conta
                        </a>
                    </center>
                </div>
            </form>
        </section>
    </div>

    <div id="delete-account-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <h3 style="color: #c62828; margin-bottom: 15px;"><i class="fas fa-exclamation-triangle"></i> Confirmar exclusão</h3>
            <p>Tem certeza? Esta ação não pode ser desfeita.</p>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button id="cancel-delete" style="padding: 10px 20px; border: 1px solid #ddd; background: #f5f5f5; border-radius: 4px; cursor: pointer;">Cancelar</button>
                <form id="delete-account-form" method="POST" action="deletar_conta.php" style="margin: 0;">
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                    <input type="hidden" name="transportador_id" value="<?php echo $transportador['id']; ?>">
                    <button type="submit" style="padding: 10px 20px; background: #c62828; color: white; border: none; border-radius: 4px; cursor: pointer;">Sim, apagar</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Upload Imagem
        const fotoPerfilDisplay = document.querySelector('.foto-perfil-display');
        const fotoPerfilInput = document.getElementById('foto_perfil');
        const profileImgPreview = document.getElementById('profile-img-preview');
        if(fotoPerfilDisplay) {
            fotoPerfilDisplay.addEventListener('click', () => { fotoPerfilInput.click(); });
        }
        if(fotoPerfilInput) {
            fotoPerfilInput.addEventListener('change', function(event) {
                const [file] = event.target.files;
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) { profileImgPreview.src = e.target.result; };
                    reader.readAsDataURL(file);
                }
            });
        }
        // Menu Mobile
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        if(hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
        }

        // Modal
        const deleteAccountLink = document.getElementById('delete-account-link');
        const deleteAccountModal = document.getElementById('delete-account-modal');
        const cancelDeleteBtn = document.getElementById('cancel-delete');
        if (deleteAccountLink) {
            deleteAccountLink.addEventListener('click', (e) => { 
                e.preventDefault(); 
                deleteAccountModal.style.display = 'flex'; 
            });
            cancelDeleteBtn.addEventListener('click', () => { 
                deleteAccountModal.style.display = 'none'; 
            });
            window.onclick = (e) => { 
                if (e.target == deleteAccountModal) deleteAccountModal.style.display = 'none'; 
            };
        }
    </script>
</body>
</html>