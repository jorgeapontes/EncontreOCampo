<?php
// src/transportador/concluir_entrega.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/send_notification.php';
require_once __DIR__ . '/../funcoes_notificacoes.php';

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

// Verificar se entrega pertence ao transportador
$sql = "SELECT * FROM entregas WHERE id = :id AND transportador_id = :transportador_id AND status IN ('pendente','em_transporte')";
$stmt = $db->prepare($sql);
$stmt->bindParam(':id', $entrega_id, PDO::PARAM_INT);
$stmt->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
$stmt->execute();
$entrega = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entrega) {
    header("Location: entregas.php?erro=" . urlencode("Entrega não encontrada ou já concluída."));
    exit();
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $foto = $_FILES['foto_comprovante'] ?? null;
    $assinatura_base64 = $_POST['assinatura_data'] ?? '';

    if (!$foto || $foto['error'] !== UPLOAD_ERR_OK) {
        $erro = "A foto do comprovante é obrigatória.";
    } elseif (empty($assinatura_base64)) {
        $erro = "A assinatura do recebedor é obrigatória.";
    } else {
        try {
            // 1. Processar Foto
            $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nome_foto = "entrega_" . $entrega_id . "_" . time() . "." . $ext;
            $caminho_foto = "../../uploads/entregas/" . $nome_foto;

            if (!is_dir("../../uploads/entregas/")) {
                mkdir("../../uploads/entregas/", 0777, true);
            }

            // 2. Processar Assinatura (Base64 para Imagem)
            $assinatura_img = str_replace('data:image/png;base64,', '', $assinatura_base64);
            $assinatura_img = str_replace(' ', '+', $assinatura_img);
            $data_assinatura = base64_decode($assinatura_img);
            $nome_assinatura = "assinatura_" . $entrega_id . "_" . time() . ".png";
            $caminho_assinatura = "../../uploads/entregas/" . $nome_assinatura;

            if (move_uploaded_file($foto['tmp_name'], $caminho_foto)) {
                file_put_contents($caminho_assinatura, $data_assinatura);

                // 3. Atualizar Banco de Dados
                $sql_update = "UPDATE entregas 
                               SET status = 'entregue', 
                                   status_detalhado = 'finalizada',
                                   foto_comprovante = :foto, 
                                   assinatura_comprovante = :assinatura,
                                   data_entrega = NOW() 
                               WHERE id = :id";
                
                $stmt_up = $db->prepare($sql_update);
                $stmt_up->execute([
                    ':foto' => $nome_foto,
                    ':assinatura' => $nome_assinatura,
                    ':id' => $entrega_id
                ]);

                // NOTIFICAÇÃO: Buscar informações para notificar comprador e vendedor
                $sql_info = "SELECT 
                    e.*,
                    p.nome as produto_nome,
                    c.usuario_id as comprador_usuario_id,
                    uc.email as comprador_email,
                    uc.nome as comprador_nome,
                    v.usuario_id as vendedor_usuario_id,
                    uv.email as vendedor_email,
                    uv.nome as vendedor_nome,
                    t.nome_comercial as transportador_nome
                FROM entregas e
                INNER JOIN produtos p ON e.produto_id = p.id
                LEFT JOIN compradores c ON e.comprador_id = c.usuario_id
                LEFT JOIN usuarios uc ON c.usuario_id = uc.id
                INNER JOIN vendedores v ON v.id = p.vendedor_id
                INNER JOIN usuarios uv ON v.usuario_id = uv.id
                INNER JOIN transportadores t ON e.transportador_id = t.id
                WHERE e.id = :id";
                
                $stmt_info = $db->prepare($sql_info);
                $stmt_info->bindParam(':id', $entrega_id, PDO::PARAM_INT);
                $stmt_info->execute();
                $info_entrega = $stmt_info->fetch(PDO::FETCH_ASSOC);

                if ($info_entrega) {
                    // Notificar COMPRADOR
                    if (!empty($info_entrega['comprador_email'])) {
                        $assunto_comprador = "✅ Entrega Concluída - Pedido #" . $entrega_id;
                        $conteudo_comprador = "Sua entrega do produto '{$info_entrega['produto_nome']}' foi concluída pelo transportador {$info_entrega['transportador_nome']}. A entrega foi registrada em " . date('d/m/Y H:i') . " com assinatura digital e foto do comprovante.";
                        
                        enviarEmailNotificacao(
                            $info_entrega['comprador_email'],
                            $info_entrega['comprador_nome'],
                            $assunto_comprador,
                            $conteudo_comprador
                        );
                    }

                    // Criar notificação na plataforma e enviar email pedindo avaliação
                    if (!empty($info_entrega['comprador_usuario_id'])) {
                        $url_avaliacao = "src/avaliar.php?tipo=produto&produto_id=" . urlencode($info_entrega['produto_id']) . "&entrega_id=" . urlencode($entrega_id);
                        criarNotificacao($info_entrega['comprador_usuario_id'], "Avalie seu produto: {$info_entrega['produto_nome']}", 'info', $url_avaliacao);

                        $assunto_avaliacao = "Avalie seu produto - Encontre o Campo";
                        $conteudo_avaliacao = "Olá {$info_entrega['comprador_nome']},\n\nSua entrega do produto '{$info_entrega['produto_nome']}' foi concluída. Agradecemos se puder avaliar sua experiência. Acesse: " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/' : '') . $url_avaliacao;

                        enviarEmailNotificacao(
                            $info_entrega['comprador_email'],
                            $info_entrega['comprador_nome'],
                            $assunto_avaliacao,
                            $conteudo_avaliacao
                        );
                    }

                    // Notificar VENDEDOR
                    if (!empty($info_entrega['vendedor_email'])) {
                        $assunto_vendedor = "📦 Entrega Finalizada - Pedido #" . $entrega_id;
                        $conteudo_vendedor = "A entrega do seu produto '{$info_entrega['produto_nome']}' foi concluída pelo transportador {$info_entrega['transportador_nome']}. Pagamento será processado conforme combinado.";
                        
                        enviarEmailNotificacao(
                            $info_entrega['vendedor_email'],
                            $info_entrega['vendedor_nome'],
                            $assunto_vendedor,
                            $conteudo_vendedor
                        );
                    }
                }

                header("Location: historico.php?sucesso=" . urlencode("Entrega concluída com sucesso!"));
                exit();
            } else {
                $erro = "Erro ao salvar a foto no servidor.";
            }
        } catch (Exception $e) {
            $erro = "Erro técnico: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concluir Entrega - Encontre o Campo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #2E7D32; --dark: #1b5e20; --light: #f1f8e9; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { color: var(--primary); text-align: center; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
        
        /* Estilo da área de Assinatura */
        .signature-wrapper {
            border: 2px dashed #ccc;
            background: #fafafa;
            border-radius: 8px;
            position: relative;
            margin-bottom: 10px;
            touch-action: none; /* Importante para mobile */
        }
        #signature-pad { width: 100%; height: 200px; cursor: crosshair; }
        .signature-actions { display: flex; justify-content: flex-end; margin-top: 5px; }
        .btn-clear { background: #f44336; color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }

        input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 15px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: var(--dark); }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        #preview-container { margin-top: 10px; display: none; text-align: center; }
        #preview-image { max-width: 100%; border-radius: 8px; max-height: 200px; }
    </style>
</head>
<body>

    <div class="container">
        <h2><i class="fas fa-check-circle"></i> Finalizar Entrega</h2>

        <?php if ($erro): ?>
            <div class="alert alert-danger"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" id="form-concluir">
            
            <div class="form-group">
                <label for="foto_comprovante">1. Foto do Comprovante (Obrigatório)</label>
                <input type="file" name="foto_comprovante" id="foto_comprovante" accept="image/*" capture="camera" required>
                <div id="preview-container">
                    <img id="preview-image" src="" alt="Preview">
                </div>
            </div>

            <div class="form-group">
                <label>2. Assinatura do Recebedor (Na tela)</label>
                <div class="signature-wrapper">
                    <canvas id="signature-pad"></canvas>
                </div>
                <div class="signature-actions">
                    <button type="button" class="btn-clear" id="clear-signature">Limpar Assinatura</button>
                </div>
                <input type="hidden" name="assinatura_data" id="assinatura_data">
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-truck-loading"></i> CONCLUIR ENTREGA
            </button>
            
            <p style="text-align:center; margin-top:15px;">
                <a href="entregas.php" style="color: #666; text-decoration:none;">Cancelar</a>
            </p>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>

    <script>
        // 1. Configuração da Assinatura
        const canvas = document.getElementById('signature-pad');
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)'
        });

        // Ajustar tamanho do canvas dinamicamente
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear(); 
        }
        window.onresize = resizeCanvas;
        resizeCanvas();

        // Botão Limpar
        document.getElementById('clear-signature').addEventListener('click', () => {
            signaturePad.clear();
        });

        // 2. Preview da Foto
        document.getElementById('foto_comprovante').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('preview-image');
            const file = e.target.files[0];

            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    previewImage.src = evt.target.result;
                    previewContainer.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // 3. Validação antes de enviar
        document.getElementById('form-concluir').addEventListener('submit', function(e) {
            if (signaturePad.isEmpty()) {
                e.preventDefault();
                alert("Por favor, peça ao recebedor para assinar.");
                return;
            }

            // Exporta a assinatura do canvas para o campo oculto em Base64
            const data = signaturePad.toDataURL('image/png');
            document.getElementById('assinatura_data').value = data;
        });
    </script>
</body>
</html>