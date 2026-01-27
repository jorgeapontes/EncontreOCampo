<?php
// src/transportador/concluir_entrega.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$entrega_id = intval($_GET['id'] ?? 0);

$database = new Database();
$db = $database->getConnection();

// Buscar id do transportador
$sql = "SELECT id FROM transportadores WHERE usuario_id = :usuario_id";
$stmt = $db->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$transportador = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$transportador) {
    die('Transportador não encontrado.');
}
$transportador_id = $transportador['id'];

// Verificar se entrega pertence ao transportador e está em transporte
$sql = "SELECT * FROM entregas WHERE id = :id AND transportador_id = :transportador_id AND status IN ('pendente','em_transporte')";
$stmt = $db->prepare($sql);
$stmt->bindParam(':id', $entrega_id, PDO::PARAM_INT);
$stmt->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
$stmt->execute();
$entrega = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$entrega) {
    die('Entrega não encontrada ou não disponível para conclusão.');
}

$erro = '';
$sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto_comprovante'])) {
    $foto = $_FILES['foto_comprovante'];
    if ($foto['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg','jpeg','png','gif'];
        if (in_array($ext, $permitidas)) {
            $nome_arquivo = 'entrega_' . $entrega_id . '_' . time() . '.' . $ext;
            $destino = '../../uploads/entregas/' . $nome_arquivo;
            if (!is_dir('../../uploads/entregas/')) {
                mkdir('../../uploads/entregas/', 0777, true);
            }
            if (move_uploaded_file($foto['tmp_name'], $destino)) {
                $sql_update = "UPDATE entregas SET status = 'entregue', status_detalhado = 'finalizada', foto_comprovante = :foto, data_entrega = NOW() WHERE id = :id";
                $stmt_update = $db->prepare($sql_update);
                $stmt_update->bindParam(':foto', $nome_arquivo);
                $stmt_update->bindParam(':id', $entrega_id, PDO::PARAM_INT);
                $stmt_update->execute();
                $sucesso = 'Entrega concluída com sucesso!';
            } else {
                $erro = 'Erro ao salvar o arquivo.';
            }
        } else {
            $erro = 'Formato de arquivo não permitido.';
        }
    } else {
        $erro = 'Erro no upload da foto.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Concluir Entrega</title>
    <link rel="stylesheet" href="../css/transportador/dashboard.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="entregas.php" class="nav-link active">Entregas</a></li>
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <main class="main-content">
        <section class="concluir-entrega-section">
            <h1>Concluir Entrega #<?php echo $entrega_id; ?></h1>
            <?php if ($erro): ?><div class="msg-erro"><?php echo $erro; ?></div><?php endif; ?>
            <?php if ($sucesso): ?><div class="msg-sucesso"><?php echo $sucesso; ?></div><?php endif; ?>
            <?php if (!$sucesso): ?>
                <form method="POST" enctype="multipart/form-data" class="form-concluir-entrega">
                    <label for="foto_comprovante">Foto do comprovante de entrega:</label>
                    <input type="file" name="foto_comprovante" id="foto_comprovante" accept="image/*" required>

                    <div id="preview-container" style="display:none;margin-top:8px;">
                        <strong>Pré-visualização:</strong>
                        <div style="margin-top:8px;"><img id="preview-image" src="" alt="Pré-visualização" style="max-width:100%;max-height:360px;border-radius:8px;border:1px solid #eee;display:block"></div>
                    </div>

                    <button type="submit" class="cta-button">Concluir Entrega</button>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <div style="display:flex;justify-content:center;margin-top:16px;">
        <button type="button" class="btn-voltar" onclick="window.location.href='entregas.php'"><i class="fas fa-arrow-left"></i> Voltar para Entregas</button>
    </div>
    <style>
    .main-content {
        max-width: 600px;
        margin: 80px auto 0 auto;
        padding: 24px;
    }
    .concluir-entrega-section {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        padding: 32px 24px;
        display: flex;
        flex-direction: column;
        gap: 24px;
        align-items: stretch;
    }
    .concluir-entrega-section h1 {
        color: var(--primary-color, #4CAF50);
        font-size: 2rem;
        margin-bottom: 8px;
    }
    .form-concluir-entrega {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .form-concluir-entrega label {
        font-weight: 600;
        color: var(--primary-color, #4CAF50);
        margin-bottom: 4px;
    }
    .form-concluir-entrega input[type="file"] {
        padding: 8px 0;
        font-size: 1rem;
    }
    .cta-button {
        background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 12px 28px;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s, box-shadow 0.2s;
        box-shadow: 0 2px 8px rgba(76,175,80,0.08);
        cursor: pointer;
        margin-top: 8px;
    }
    .cta-button:hover {
        background: linear-gradient(135deg, #388E3C 0%, #4CAF50 100%);
        color: #fff;
        box-shadow: 0 4px 16px rgba(76,175,80,0.15);
    }
    .btn-voltar {
        background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
        color: #333;
        border: none;
        border-radius: 8px;
        padding: 12px 28px;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s, box-shadow 0.2s;
        box-shadow: 0 2px 8px rgba(160,160,160,0.08);
        cursor: pointer;
        margin-top: 8px;
    }
    .btn-voltar:hover {
        background: linear-gradient(135deg, #bdbdbd 0%, #e0e0e0 100%);
        color: #111;
        box-shadow: 0 4px 16px rgba(160,160,160,0.15);
    }
    .msg-erro {
        color: #fff;
        background: #e57373;
        border-radius: 8px;
        padding: 10px 16px;
        margin-bottom: 8px;
        font-weight: 600;
    }
    .msg-sucesso {
        color: #fff;
        background: #4CAF50;
        border-radius: 8px;
        padding: 10px 16px;
        margin-bottom: 8px;
        font-weight: 600;
    }
    @media (max-width: 600px) {
        .main-content, .concluir-entrega-section {
            padding: 12px 2px;
        }
        .concluir-entrega-section h1 {
            font-size: 1.2rem;
        }
    }
    </style>
    <script>
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        if (hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            }));
        }
        // Preview da imagem selecionada
        (function(){
            const input = document.getElementById('foto_comprovante');
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('preview-image');
            if (!input) return;
            input.addEventListener('change', function(e){
                const file = input.files && input.files[0];
                if (!file) {
                    previewContainer.style.display = 'none';
                    previewImage.src = '';
                    return;
                }
                if (!file.type.match('image.*')) {
                    alert('Por favor selecione uma imagem.');
                    input.value = '';
                    previewContainer.style.display = 'none';
                    previewImage.src = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(evt){
                    previewImage.src = evt.target.result;
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
            });
        })();
    </script>
</body>
</html>
