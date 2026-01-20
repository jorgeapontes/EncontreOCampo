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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se é uma busca de CEP via AJAX
    if (isset($_POST['buscar_cep']) && $_POST['buscar_cep'] == 'true') {
        // Buscar CEP e retornar dados em JSON
        $cep = preg_replace('/[^0-9]/', '', $_POST['cep']);
        
        if (strlen($cep) == 8) {
            // Fazer requisição à API ViaCEP
            $url = "https://viacep.com.br/ws/{$cep}/json/";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $dados = json_decode($response, true);
            
            if (!isset($dados['erro'])) {
                // Atualizar endereço no banco de dados
                try {
                    $sql = "UPDATE compradores SET 
                            cep = :cep,
                            rua = :rua,
                            cidade = :cidade,
                            estado = :estado,
                            complemento = :complemento
                            WHERE usuario_id = :id";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':cep' => $cep,
                        ':rua' => $dados['logradouro'] ?? '',
                        ':cidade' => $dados['localidade'] ?? '',
                        ':estado' => $dados['uf'] ?? '',
                        ':complemento' => $dados['complemento'] ?? '',
                        ':id' => $usuario_id
                    ]);
                    
                    // Retornar dados em JSON
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'data' => $dados,
                        'cep_formatado' => substr($cep, 0, 5) . '-' . substr($cep, 5, 3),
                        'message' => 'CEP encontrado e salvo com sucesso!'
                    ]);
                    exit;
                    
                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erro ao salvar no banco: ' . $e->getMessage()
                    ]);
                    exit;
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'CEP não encontrado'
                ]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'CEP inválido'
            ]);
            exit;
        }
    }
    // Lógica de atualização normal do perfil
    elseif (isset($_POST['atualizar_perfil'])) {
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

            // 2. Processar upload de foto se enviada
            $foto_perfil_url = $comprador_data['foto_perfil_url'] ?? '';
            
            if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/compradores/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_file_name = 'perfil_' . $usuario_id . '_' . time() . '.' . $file_extension;
                    $dest_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $dest_path)) {
                        $foto_perfil_url = '../../uploads/compradores/' . $new_file_name;
                        
                        // Deletar foto antiga se existir
                        if (!empty($comprador_data['foto_perfil_url']) && file_exists($comprador_data['foto_perfil_url']) && strpos($comprador_data['foto_perfil_url'], 'no-user-image') === false) {
                            @unlink($comprador_data['foto_perfil_url']);
                        }
                    } else {
                        throw new Exception("Erro ao fazer upload da foto. Verifique as permissões da pasta uploads.");
                    }
                } else {
                    throw new Exception("Formato de imagem não permitido. Use JPG, PNG ou GIF.");
                }
            }
            
            // 3. Atualizar tabela COMPRADORES
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
                        complemento = :complemento,
                        foto_perfil_url = :foto_perfil_url
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
                ':foto_perfil_url' => $foto_perfil_url,
                ':id'             => $usuario_id
            ]);

            $conn->commit();
            $_SESSION['usuario_nome'] = $_POST['nome']; // Atualiza nome na sessão
            $mensagem_sucesso = "Perfil atualizado com sucesso!";
            
            // Atualizar dados locais para exibição imediata
            $comprador_data['foto_perfil_url'] = $foto_perfil_url;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $mensagem_erro = "Erro ao atualizar: " . $e->getMessage();
        }
    }
}
// --- FIM DA LÓGICA DE ATUALIZAÇÃO ---

// Verificar se veio redirecionamento com sucesso
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $mensagem_sucesso = "Perfil e e-mail atualizados com sucesso!";
}

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
                <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display: none;">
                
                <div class="perfil-header-info">
                    <center>
                        <div class="foto-perfil-container">
                            <div class="foto-perfil-display">
                                <img id="profile-img-preview" src="<?php echo (!empty($comprador_data['foto_perfil_url']) && file_exists($comprador_data['foto_perfil_url'])) ? htmlspecialchars($comprador_data['foto_perfil_url']) : '../../img/no-user-image.png'; ?>" alt="Foto de Perfil">
                                <div class="foto-overlay"><i class="fas fa-pencil-alt"></i></div>
                            </div>
                        </div>
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
                        <div class="form-group cep-group">
                            <label class="required">CEP</label>
                            <div class="cep-input-wrapper">
                                <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($comprador_data['cep']); ?>" required>
                                <button type="button" id="buscar-cep" class="cep-button">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                            <div id="cep-message" class="cep-message"></div>
                        </div>
                        <div class="form-group"><label class="required">Estado</label><input type="text" name="estado" id="estado" value="<?php echo htmlspecialchars($comprador_data['estado']); ?>" required maxlength="2"></div>
                        <div class="form-group"><label class="required">Cidade</label><input type="text" name="cidade" id="cidade" value="<?php echo htmlspecialchars($comprador_data['cidade']); ?>" required></div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group"><label class="required">Rua</label><input type="text" name="rua" id="rua" value="<?php echo htmlspecialchars($comprador_data['rua']); ?>" required></div>
                        <div class="form-group"><label class="required">Número</label><input type="text" name="numero" id="numero" value="<?php echo htmlspecialchars($comprador_data['numero']); ?>" required></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cip">CIP</label>
                        <input type="text" name="cip" value="<?php echo htmlspecialchars($comprador_data['cip'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="complemento">Complemento</label>
                        <input type="text" name="complemento" id="complemento" value="<?php echo htmlspecialchars($comprador_data['complemento'] ?? ''); ?>">
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
        const profileImgPreview = document.getElementById('profile-img-preview');
        
        if(fotoPerfilDisplay) {
            fotoPerfilDisplay.addEventListener('click', () => fotoPerfilInput.click());
        }
        if(fotoPerfilInput) {
            fotoPerfilInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        profileImgPreview.src = e.target.result;
                        console.log('Arquivo selecionado:', file.name);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Auto-fechar alerts
        window.addEventListener('load', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });

        // Busca de CEP com salvamento automático
        document.getElementById('buscar-cep').addEventListener('click', buscarCEP);
        document.getElementById('cep').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarCEP();
            }
        });

        // Formatar CEP enquanto digita
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
            }
            e.target.value = value;
        });

        function buscarCEP() {
            const cepInput = document.getElementById('cep');
            const cep = cepInput.value.replace(/\D/g, '');
            const cepMessage = document.getElementById('cep-message');
            
            if (!cep || cep.length !== 8) {
                showMessage('Por favor, digite um CEP válido (8 dígitos).', 'error');
                return;
            }
            
            // Mostrar indicador de carregamento
            const buscarBtn = document.getElementById('buscar-cep');
            const originalHTML = buscarBtn.innerHTML;
            buscarBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
            buscarBtn.disabled = true;
            cepMessage.innerHTML = '';
            
            // Enviar requisição AJAX para o servidor
            const formData = new FormData();
            formData.append('buscar_cep', 'true');
            formData.append('cep', cep);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Preencher os campos com os dados do CEP
                    document.getElementById('rua').value = data.data.logradouro || '';
                    document.getElementById('cidade').value = data.data.localidade || '';
                    document.getElementById('estado').value = data.data.uf || '';
                    document.getElementById('complemento').value = data.data.complemento || '';
                    
                    // Atualizar o campo CEP com formatação
                    if (data.cep_formatado) {
                        cepInput.value = data.cep_formatado;
                    } else {
                        cepInput.value = formatCEP(cep);
                    }
                    
                    // Mostrar mensagem de sucesso
                    showMessage(data.message, 'success');
                    
                    // Forçar um pequeno reload dos dados da página após 1 segundo
                    setTimeout(() => {
                        // Recarregar a página para mostrar os dados atualizados
                        window.location.reload();
                    }, 1500);
                    
                    // Focar no campo número após buscar o CEP
                    setTimeout(() => {
                        document.getElementById('numero').focus();
                    }, 300);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar CEP:', error);
                showMessage('Erro ao buscar CEP. Por favor, tente novamente.', 'error');
            })
            .finally(() => {
                // Restaurar o botão
                buscarBtn.innerHTML = originalHTML;
                buscarBtn.disabled = false;
            });
        }

        function formatCEP(cep) {
            // Formatar CEP: 00000-000
            return cep.replace(/(\d{5})(\d{3})/, '$1-$2');
        }

        function showMessage(message, type) {
            const cepMessage = document.getElementById('cep-message');
            cepMessage.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle') + '"></i> ' + message;
            cepMessage.className = 'cep-message ' + type;
        }
    </script>
</body>
</html>