<?php
// src/avaliar.php
session_start();
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/funcoes_notificacoes.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php?erro=' . urlencode('Faça login para avaliar.'));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? null; // 'produto' ou 'vendedor'
$produto_id = isset($_GET['produto_id']) ? intval($_GET['produto_id']) : (isset($_POST['produto_id']) ? intval($_POST['produto_id']) : null);
$vendedor_id = isset($_GET['vendedor_id']) ? intval($_GET['vendedor_id']) : (isset($_POST['vendedor_id']) ? intval($_POST['vendedor_id']) : null);
$proposta_id = isset($_GET['proposta_id']) ? intval($_GET['proposta_id']) : (isset($_POST['proposta_id']) ? intval($_POST['proposta_id']) : null);
$entrega_id = isset($_GET['entrega_id']) ? intval($_GET['entrega_id']) : (isset($_POST['entrega_id']) ? intval($_POST['entrega_id']) : null);

$erro = '';
$sucesso = '';

function usuarioEhElegivel($conn, $usuario_id, $tipo, $produto_id = null, $vendedor_id = null, $proposta_id = null, $entrega_id = null) {
    try {
        if ($tipo === 'produto') {
                if ($produto_id) {
                $sql = "SELECT p.opcao_frete, p.comprador_id FROM propostas p LEFT JOIN compradores c ON p.comprador_id = c.id WHERE p.produto_id = :produto_id AND (p.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) AND p.status = 'aceita' ORDER BY p.data_inicio DESC LIMIT 1";
                $st = $conn->prepare($sql);
                $st->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                $st->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                $st->execute();
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $op = $row['opcao_frete'] ?? null;
                    if (in_array($op, ['vendedor','comprador'])) return true;
                    if ($op === 'entregador') {
                        $sql2 = "SELECT e.id FROM entregas e LEFT JOIN compradores c ON e.comprador_id = c.id WHERE e.produto_id = :produto_id AND (e.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') LIMIT 1";
                        $s2 = $conn->prepare($sql2);
                        $s2->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                        $s2->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                        $s2->execute();
                        if ($s2->fetch(PDO::FETCH_ASSOC)) return true;
                    }
                }
            }
            // if entrega_id provided, check delivery record (also consider compradores mapping)
            if ($entrega_id) {
                $sql_e = "SELECT e.id FROM entregas e LEFT JOIN compradores c ON e.comprador_id = c.id WHERE e.id = :entrega_id AND (e.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') LIMIT 1";
                $se = $conn->prepare($sql_e);
                $se->bindParam(':entrega_id', $entrega_id, PDO::PARAM_INT);
                $se->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                $se->execute();
                if ($se->fetch(PDO::FETCH_ASSOC)) return true;
            }
            return false;
        }

        if ($tipo === 'vendedor') {
            if ($vendedor_id) {
                $sql = "SELECT 1 FROM propostas WHERE vendedor_id = :vendedor_id AND comprador_id = :usuario_id AND status = 'aceita' LIMIT 1";
                $st = $conn->prepare($sql);
                $st->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
                $st->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
                $st->execute();
                if ($st->fetch(PDO::FETCH_ASSOC)) return true;
            }
            return false;
        }

    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nota = intval($_POST['nota'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');
    $tipo = $_POST['tipo'] ?? $tipo;
    $produto_id = !empty($_POST['produto_id']) ? intval($_POST['produto_id']) : $produto_id;
    $vendedor_id = !empty($_POST['vendedor_id']) ? intval($_POST['vendedor_id']) : $vendedor_id;
    $proposta_id = !empty($_POST['proposta_id']) ? intval($_POST['proposta_id']) : $proposta_id;
    $entrega_id = !empty($_POST['entrega_id']) ? intval($_POST['entrega_id']) : $entrega_id;

    if ($nota < 1 || $nota > 5) {
        $erro = 'A nota deve ser entre 1 e 5.';
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            // Validar se o usuário é elegível para avaliar este item
            if (!usuarioEhElegivel($conn, $usuario_id, $tipo, $produto_id, $vendedor_id, $proposta_id, $entrega_id)) {
                $erro = 'Você não tem permissão para avaliar este item.';
            } else {

            $sql = "INSERT INTO avaliacoes (avaliador_usuario_id, produto_id, vendedor_id, proposta_id, entrega_id, nota, comentario, tipo) VALUES (:avaliador, :produto, :vendedor, :proposta, :entrega, :nota, :comentario, :tipo)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':avaliador', $usuario_id, PDO::PARAM_INT);
            $prod_bind = $produto_id ?: null;
            $vend_bind = $vendedor_id ?: null;
            $prop_bind = $proposta_id ?: null;
            $ent_bind = $entrega_id ?: null;
            $stmt->bindParam(':produto', $prod_bind, PDO::PARAM_INT);
            $stmt->bindParam(':vendedor', $vend_bind, PDO::PARAM_INT);
            $stmt->bindParam(':proposta', $prop_bind, PDO::PARAM_INT);
            $stmt->bindParam(':entrega', $ent_bind, PDO::PARAM_INT);
            $stmt->bindParam(':nota', $nota, PDO::PARAM_INT);
            $stmt->bindParam(':comentario', $comentario);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->execute();

            // Notificar vendedor/usuario alvo
            if ($tipo === 'produto' && $produto_id) {
                $stmt_p = $conn->prepare("SELECT vendedor_id FROM produtos WHERE id = :pid");
                $stmt_p->bindParam(':pid', $produto_id, PDO::PARAM_INT);
                $stmt_p->execute();
                $prod = $stmt_p->fetch(PDO::FETCH_ASSOC);
                if ($prod && $prod['vendedor_id']) {
                    $v_id = $prod['vendedor_id'];
                    // buscar usuario do vendedor
                    $stmt_vu = $conn->prepare("SELECT usuario_id FROM vendedores WHERE id = :vid");
                    $stmt_vu->bindParam(':vid', $v_id, PDO::PARAM_INT);
                    $stmt_vu->execute();
                    $vu = $stmt_vu->fetch(PDO::FETCH_ASSOC);
                    $vendedor_usuario = $vu['usuario_id'] ?? null;

                    if ($vendedor_usuario) {
                        criarNotificacao($vendedor_usuario, "Você recebeu uma nova avaliação para seu produto.", 'info', "src/verperfil.php?usuario_id=" . $vendedor_usuario);
                        $usuario_email = buscarEmailUsuario($vendedor_usuario);
                        if ($usuario_email && $usuario_email['email']) {
                            enviarEmailNotificacao($usuario_email['email'], $usuario_email['nome'], 'Nova avaliação recebida', 'Você recebeu uma nova avaliação na plataforma. Acesse seu perfil para ver detalhes.');
                        }
                    }
                }
            } elseif ($tipo === 'vendedor' && $vendedor_id) {
                // buscar usuario do vendedor
                $stmt_vu = $conn->prepare("SELECT usuario_id FROM vendedores WHERE id = :vid");
                $stmt_vu->bindParam(':vid', $vendedor_id, PDO::PARAM_INT);
                $stmt_vu->execute();
                $vu = $stmt_vu->fetch(PDO::FETCH_ASSOC);
                $vendedor_usuario = $vu['usuario_id'] ?? null;
                if ($vendedor_usuario) {
                    criarNotificacao($vendedor_usuario, "Você recebeu uma nova avaliação de vendedor.", 'info', "src/verperfil.php?usuario_id=" . $vendedor_usuario);
                    $usuario_email = buscarEmailUsuario($vendedor_usuario);
                    if ($usuario_email && $usuario_email['email']) {
                        enviarEmailNotificacao($usuario_email['email'], $usuario_email['nome'], 'Nova avaliação recebida', 'Você recebeu uma nova avaliação na plataforma. Acesse seu perfil para ver detalhes.');
                    }
                }
            }

                $sucesso = 'Avaliação enviada. Obrigado!';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao gravar avaliação: ' . $e->getMessage();
        }
    }
}

// Se não for POST, também verificar elegibilidade antes de mostrar o formulário
if (empty($sucesso) && empty($erro)) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        if (!usuarioEhElegivel($conn, $usuario_id, $tipo, $produto_id, $vendedor_id, $proposta_id, $entrega_id)) {
            $erro = 'Você não tem permissão para avaliar este item.';
        }
    } catch (Exception $e) {
        // não bloquear exibição por erro de verificação
    }
}

// Form HTML simples
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Avaliar</title>
    <link rel="stylesheet" href="../index.css">
</head>
<body>
<div class="container" style="max-width:700px;margin:40px auto;padding:20px;background:#fff;border-radius:8px;">
    <h2>Avaliar</h2>
    <?php if ($erro): ?>
        <div style="background:#ffeded;padding:10px;border-radius:6px;color:#a00"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    <?php if ($sucesso): ?>
        <div style="background:#e6ffed;padding:10px;border-radius:6px;color:#080"><?php echo htmlspecialchars($sucesso); ?></div>
        <p><a href="javascript:history.back()">Voltar</a></p>
    <?php else: ?>
    <form method="POST">
        <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">
        <input type="hidden" name="produto_id" value="<?php echo htmlspecialchars($produto_id); ?>">
        <input type="hidden" name="vendedor_id" value="<?php echo htmlspecialchars($vendedor_id); ?>">
        <input type="hidden" name="proposta_id" value="<?php echo htmlspecialchars($proposta_id); ?>">
        <input type="hidden" name="entrega_id" value="<?php echo htmlspecialchars($entrega_id); ?>">

        <div>
            <label>Nota (1-5)</label>
            <select name="nota" required>
                <option value="">Selecione</option>
                <option value="5">5 - Excelente</option>
                <option value="4">4 - Bom</option>
                <option value="3">3 - Regular</option>
                <option value="2">2 - Ruim</option>
                <option value="1">1 - Péssimo</option>
            </select>
        </div>
        <div style="margin-top:10px;">
            <label>Comentário (opcional)</label>
            <textarea name="comentario" rows="4" style="width:100%"></textarea>
        </div>
        <div style="margin-top:12px;">
            <button type="submit">Enviar avaliação</button>
        </div>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
