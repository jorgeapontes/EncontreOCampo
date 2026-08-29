<?php
// src/vendedor/perfil.php
require_once 'auth.php'; 
require_once 'verificar_assinaturas.php';

$database = new Database();
$db = $database->getConnection();
verificarValidadeAssinaturas($db); 

// Garante que as variáveis existam como arrays vazios caso auth.php não as crie
if (!isset($usuario)) $usuario = [];
if (!isset($vendedor)) $vendedor = [];

// --- 1. BUSCAR DADOS FRESCOS DO USUÁRIO ---
$stmt_user = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
if (isset($_SESSION['usuario_id'])) {
    $stmt_user->execute([$_SESSION['usuario_id']]);
    $usuario_real = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($usuario_real) {
        $usuario = is_array($usuario) ? array_merge($usuario, $usuario_real) : $usuario_real;
    }
}

// --- 2. BUSCAR DADOS COMPLETOS DO VENDEDOR ---
if (!isset($vendedor['id']) && isset($_SESSION['usuario_id'])) {
    $stmt_busca_vend = $db->prepare("SELECT id FROM vendedores WHERE usuario_id = ?");
    $stmt_busca_vend->execute([$_SESSION['usuario_id']]);
    $res_vend = $stmt_busca_vend->fetch(PDO::FETCH_ASSOC);
    if ($res_vend) {
        $vendedor['id'] = $res_vend['id'];
    }
}

if (isset($vendedor['id'])) {
    $stmt_plano = $db->prepare("
        SELECT v.*, p.nome as nome_plano_real, p.preco_mensal 
        FROM vendedores v 
        LEFT JOIN planos p ON v.plano_id = p.id 
        WHERE v.id = ?
    ");
    $stmt_plano->execute([$vendedor['id']]);
    $dados_completos = $stmt_plano->fetch(PDO::FETCH_ASSOC);

    if ($dados_completos) {
        $vendedor = is_array($vendedor) ? array_merge($vendedor, $dados_completos) : $dados_completos;
    }
}

$nome_exibicao_plano = $dados_completos['nome_plano_real'] ?? 'Sem Plano';
$mensagem_sucesso = '';
$mensagem_erro = '';

$vendedor_id_fk = $vendedor['id'] ?? 0;

// Exemplo de alerta no topo do painel do vendedor
if (isset($_SESSION['status_assinatura']) && $_SESSION['status_assinatura'] === 'atrasado') {
    echo '<div class="alert-atraso">
            <strong>Atenção:</strong> Identificamos um problema no pagamento da sua mensalidade.
            Para evitar o bloqueio dos seus anúncios, <a href="escolher_plano">clique aqui e atualize seu pagamento</a>.
          </div>';
}

// Diretório de upload relativo ao arquivo atual
$upload_dir = '../uploads/vendedores/'; 
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// --- LÓGICA DE SALVAMENTO ---
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
                    $sql = "UPDATE vendedores SET 
                            cep = :cep,
                            rua = :rua,
                            cidade = :cidade,
                            estado = :estado,
                            complemento = :complemento
                            WHERE id = :id";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':cep' => $cep,
                        ':rua' => $dados['logradouro'] ?? '',
                        ':cidade' => $dados['localidade'] ?? '',
                        ':estado' => $dados['uf'] ?? '',
                        ':complemento' => $dados['complemento'] ?? '',
                        ':id' => $vendedor_id_fk
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
    $nome = $_POST['nome'] ?? ($usuario['nome'] ?? '');
    $email = $_POST['email'] ?? ($usuario['email'] ?? '');
    $telefone1 = $_POST['telefone1'] ?? ($vendedor['telefone1'] ?? '');
    $razao_social = $_POST['razao_social'] ?? ($vendedor['razao_social'] ?? '');
    $cep = $_POST['cep'] ?? ($vendedor['cep'] ?? '');
    $rua = $_POST['rua'] ?? ($vendedor['rua'] ?? '');
    $numero = $_POST['numero'] ?? ($vendedor['numero'] ?? '');
    $complemento = $_POST['complemento'] ?? ($vendedor['complemento'] ?? '');
    $estado = $_POST['estado'] ?? ($vendedor['estado'] ?? '');
    $cidade = $_POST['cidade'] ?? ($vendedor['cidade'] ?? '');

    $foto_perfil_antiga = $vendedor['foto_perfil_url'] ?? '';
    $foto_perfil_nova = $foto_perfil_antiga;
    
    // Upload Imagem
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = 'perfil_' . $vendedor_id_fk . '_' . time() . '.' . $file_extension;
            $dest_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $dest_path)) {
                // CORREÇÃO: Salvar o caminho relativo correto no banco
                $foto_perfil_nova = '../uploads/vendedores/' . $new_file_name;
                
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

        // 2. Atualiza VENDEDOR
        $query_vendedor = "UPDATE vendedores SET 
            telefone1 = ?, 
            razao_social = ?, 
            foto_perfil_url = ?,
            cep = ?,
            rua = ?,
            numero = ?,
            complemento = ?,
            estado = ?,
            cidade = ?
            WHERE id = ?";
            
        $stmt_v = $db->prepare($query_vendedor);
        $stmt_v->execute([
            $telefone1, 
            $razao_social, 
            $foto_perfil_nova,
            $cep,
            $rua,
            $numero,
            $complemento,
            $estado,
            $cidade,
            $vendedor_id_fk
        ]);

        $db->commit();
        $mensagem_sucesso = "Perfil atualizado com sucesso!";
        
        // Atualiza variáveis visuais
        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
        $vendedor['telefone1'] = $telefone1;
        $vendedor['razao_social'] = $razao_social;
        $vendedor['cep'] = $cep;
        $vendedor['rua'] = $rua;
        $vendedor['numero'] = $numero;
        $vendedor['complemento'] = $complemento;
        $vendedor['estado'] = $estado;
        $vendedor['cidade'] = $cidade;
        $vendedor['foto_perfil_url'] = $foto_perfil_nova;
        
        // Refresh após 2 segundos
        header("Refresh: 2");

    } catch (Exception $e) {
        $db->rollBack();
        $mensagem_erro = "Erro ao atualizar: " . $e->getMessage();
    }
}

// CORREÇÃO: Função para verificar e retornar o caminho correto da imagem
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
    <title>Meu Perfil - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/perfil.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    
</head>
<body>
    <?php if (isset($_SESSION['aviso_expiracao'])): ?>
    <div id="alert-expirado" class="alert-expirado">
        <div class="alert-expirado-content">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><strong>Plano Expirado:</strong> A sua assinatura Profissional terminou e a sua conta foi movida para o <strong>Plano Gratuito</strong>.</span>
        </div>
        <button onclick="document.getElementById('alert-expirado').style.display='none'" class="alert-expirado-close">&times;</button>
    </div>
    <?php unset($_SESSION['aviso_expiracao']); endif; ?>
    
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../../index" class="logo-link">
                        <img src="../../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../../index" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="../anuncios" class="nav-link">Anúncios</a></li>
                    <li class="nav-item"><a href="dashboard" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="chats" class="nav-link">Chats</a></li>
                    <li class="nav-item"><a href="perfil" class="nav-link active">Meu Perfil</a></li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            if (isset($_SESSION['usuario_id'])) {
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $db->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item"><a href="../logout" class="nav-link exit-button no-underline">Sair</a></li>
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

            <form method="POST" action="perfil" class="perfil-form" enctype="multipart/form-data">
                <div class="perfil-header-info">
                    <center>
                        <div class="foto-perfil-container">
                            <div class="foto-perfil-display">
                                <img id="profile-img-preview" 
                                    src="<?php echo getImagePath($vendedor['foto_perfil_url'] ?? ''); ?>" 
                                    alt="Foto de Perfil">
                                <div class="foto-overlay"><i class="fas fa-pencil-alt"></i></div>
                            </div>
                            <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" class="file-input-hidden">
                        </div>
                    </center>
                </div>

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

                    <h2>Dados do Vendedor (Empresa)</h2>
                    
                    <div class="form-group">
                        <label for="razao_social">Razão Social / Nome da Loja</label>
                        <input type="text" id="razao_social" name="razao_social" value="<?php echo htmlspecialchars($vendedor['razao_social'] ?? ''); ?>">
                    </div>

                    <div class="form-group-row">
                        <div class="form-group">
                            <label>CPF/CNPJ (Não editável)</label>
                            <input type="text" value="<?php echo htmlspecialchars($vendedor['cpf_cnpj'] ?? ''); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="telefone1" class="required">Telefone Principal</label>
                            <input type="text" id="telefone1" name="telefone1" value="<?php echo htmlspecialchars($vendedor['telefone1'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <h3>Endereço</h3>
                    
                    <div class="form-group-row input-group-cep">
                        <div class="form-group form-group-cep">
                            <label for="cep">CEP</label>
                            <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($vendedor['cep'] ?? ''); ?>" maxlength="9" placeholder="00000-000">
                            <div id="cep-message" class="cep-message"></div>
                        </div>
                        <button type="button" class="btn-buscar-cep" id="btn-buscar-cep" onclick="buscarCep()">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado">
                                <option value="">UF</option>
                                <?php
                                $estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                                foreach ($estados as $uf) {
                                    $selected = (isset($vendedor['estado']) && $vendedor['estado'] == $uf) ? 'selected' : '';
                                    echo "<option value='$uf' $selected>$uf</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group flex-2">
                            <label for="cidade">Cidade</label>
                            <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($vendedor['cidade'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div class="form-group flex-3">
                            <label for="rua">Rua/Logradouro</label>
                            <input type="text" id="rua" name="rua" value="<?php echo htmlspecialchars($vendedor['rua'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="numero">Número</label>
                            <input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($vendedor['numero'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="complemento">Complemento</label>
                        <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($vendedor['complemento'] ?? ''); ?>">
                    </div>

                    <br>
                    <h2>Informações do Plano</h2>

                    <div class="form-group">
                        <label for="plano">Plano Atual</label>
                        <div class="plano-info">
                            <?php
                            $plano_atual = 'Free';
                            $plano_id_atual = 1;
                            if (isset($vendedor['plano_id'])) {
                                $query_plano = "SELECT * FROM planos WHERE id = :plano_id";
                                $stmt_plano = $db->prepare($query_plano);
                                $stmt_plano->bindParam(':plano_id', $vendedor['plano_id']);
                                $stmt_plano->execute();
                                $plano_dados = $stmt_plano->fetch(PDO::FETCH_ASSOC);
                                if ($plano_dados) {
                                    $plano_atual = $plano_dados['nome'];
                                    $plano_id_atual = $plano_dados['id'];
                                }
                            }
                            ?>
                            <input type="text" value="<?php echo htmlspecialchars($plano_atual); ?>" disabled>
                            <small>
                                <?php if ($plano_id_atual > 1): ?>
                                    <a href="escolher_plano" class="change-plan-link">Ver Planos</a> | 
                                    <a href="gerenciar_assinatura" class="manage-subscription-link">Gerenciar assinatura</a>
                                <?php else: ?>
                                    <a href="escolher_plano" class="upgrade-plan-link">Fazer upgrade do plano</a>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                
                    <button type="submit" class="big-button"><i class="fas fa-save"></i> Salvar Alterações</button>
                    
                    <center>
                        <a href="#" id="delete-account-link" class="delete-account-link">
                            <i class="fas fa-trash-alt"></i> Apagar minha conta
                        </a>
                    </center>

                    <!-- Campo hidden para guardar o número original -->
                    <input type="hidden" id="numero_original" value="<?php echo htmlspecialchars($vendedor['numero'] ?? ''); ?>">
                </div>
            </form>
        </section>
    </div>

    <div id="delete-account-modal" class="delete-account-modal">
        <div class="delete-account-modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirmar exclusão</h3>
            <p>Tem certeza? Esta ação não pode ser desfeita.</p>
            <div class="delete-account-modal-actions">
                <button id="cancel-delete" class="btn-cancel-delete">Cancelar</button>
                <form id="delete-account-form" method="POST" action="deletar_conta" class="delete-account-form">
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                    <input type="hidden" name="vendedor_id" value="<?php echo $vendedor['id']; ?>">
                    <button type="submit" class="btn-confirm-delete">Sim, apagar</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Menu Mobile
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        if(hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
        }

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

        // Formatar CEP enquanto digita
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
            }
            e.target.value = value;
        });

        // Função Buscar CEP com salvamento automático
        function buscarCep() {
            const cepInput = document.getElementById('cep');
            const ruaInput = document.getElementById('rua');
            const cidadeInput = document.getElementById('cidade');
            const estadoSelect = document.getElementById('estado');
            const complementoInput = document.getElementById('complemento');
            const cepMessage = document.getElementById('cep-message');
            const btnBuscar = document.getElementById('btn-buscar-cep');
            
            let cep = cepInput.value.replace(/\D/g, '');
            
            if (cep.length !== 8) {
                showMessage('Por favor, digite um CEP válido (8 dígitos).', 'error');
                cepInput.focus();
                return;
            }
            
            // Mostrar indicador de carregamento
            const originalHTML = btnBuscar.innerHTML;
            btnBuscar.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btnBuscar.disabled = true;
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
                    ruaInput.value = data.data.logradouro || '';
                    cidadeInput.value = data.data.localidade || '';
                    estadoSelect.value = data.data.uf || '';
                    complementoInput.value = data.data.complemento || '';
                    
                    // Atualizar o campo CEP com formatação
                    if (data.cep_formatado) {
                        cepInput.value = data.cep_formatado;
                    }
                    
                    // Mostrar mensagem de sucesso
                    showMessage(data.message, 'success');
                    
                    // Focar no campo número após buscar o CEP
                    setTimeout(() => {
                        document.getElementById('numero').focus();
                    }, 300);
                    
                    // Limpar campo número
                    document.getElementById('numero').value = '';

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
                btnBuscar.innerHTML = originalHTML;
                btnBuscar.disabled = false;
            });
        }

        // Buscar CEP ao pressionar Enter no campo CEP
        document.getElementById('cep').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarCep();
            }
        });

        // Remover o listener antigo de blur que podia conflitar
        document.getElementById('cep').removeEventListener('blur', buscarCep);

        function showMessage(message, type) {
            const cepMessage = document.getElementById('cep-message');
            cepMessage.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle') + '"></i> ' + message;
            cepMessage.className = 'cep-message ' + type;
        }

        // Validação no submit do formulário
        let precisaAlterarNumero = false;
        // Quando buscar CEP, ativa a flag
        function ativarFlagNumero() {
            precisaAlterarNumero = true;
        }
        // Adiciona chamada ao limpar número após buscar CEP
        const btnBuscar = document.getElementById('buscar-cep-btn');
        if (btnBuscar) {
            btnBuscar.addEventListener('click', ativarFlagNumero);
        }
        // Se buscar via Enter
        const cepInput = document.getElementById('cep');
        if (cepInput) {
            cepInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') ativarFlagNumero();
            });
        }
        const perfilForm = document.querySelector('.perfil-form');
        if (perfilForm) {
            perfilForm.addEventListener('submit', function(e) {
                const numeroInput = document.getElementById('numero');
                const numeroOriginal = document.getElementById('numero_original').value;
                if (!numeroInput.value) {
                    alert('Por favor, preencha o número do endereço.');
                    numeroInput.focus();
                    e.preventDefault();
                    return false;
                }
                if (precisaAlterarNumero && numeroInput.value === numeroOriginal) {
                    alert('Por favor, digite um número diferente do original após buscar o CEP.');
                    numeroInput.focus();
                    e.preventDefault();
                    return false;
                }
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