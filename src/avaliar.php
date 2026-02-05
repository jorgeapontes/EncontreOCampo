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

                // Buscar nome do produto
                $sql_produto = "SELECT nome FROM produtos WHERE id = :produto_id";
                $stmt_produto = $conn->prepare($sql_produto);
                $stmt_produto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                $stmt_produto->execute();
                $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

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

                $sql_vendedor = "SELECT u.nome 
                                FROM vendedores v 
                                INNER JOIN usuarios u ON v.usuario_id = u.id 
                                WHERE v.id = :vendedor_id";
                $stmt_vendedor = $conn->prepare($sql_vendedor);
                $stmt_vendedor->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
                $stmt_vendedor->execute();
                $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

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
        $erro = 'Por favor, selecione uma nota entre 1 e 5 estrelas.';
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
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <title>Avaliar</title>
    <link rel="stylesheet" href="../index.css">
    <style>
        /* Estilos para o sistema de estrelas */
        .rating-container {
            margin: 15px 0 25px 0;
        }
        
        .stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            font-size: 2.5rem;
            line-height: 1;
            cursor: pointer;
            margin-bottom: 10px;
        }
        
        .star {
            color: #ddd;
            transition: color 0.2s, transform 0.2s;
            margin-right: 5px;
        }
        
        .star:hover,
        .star:hover ~ .star {
            color: #ffc107;
        }
        
        .star.selected {
            color: #ffc107;
        }
        
        .star-label {
            font-size: 1.8rem;
            margin-left: 10px;
            color: #666;
            font-weight: bold;
        }
        
        .star-label-text {
            font-size: 1rem;
            color: #666;
            margin-top: 5px;
            text-align: center;
        }
        
        .star-rating-input {
            display: none;
        }
        
        .rating-error {
            color: #d32f2f;
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        }
        
        .stars-label {
            font-weight: bold;
            margin-bottom: 10px;
            display: block;
        }
        
        .cancel-btn{
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
            margin-top: 15px;
            margin-right: 15px;
            width: 160px;
        }

        .cancel-btn:hover {
            background-color: #f72424;
        }

        .submit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
            margin-top: 15px;
            margin-left: 15px;
        }
        
        .submit-btn:hover {
            background-color: #45a049;
        }
        
        .submit-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        .back-btn{
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
            margin-top: 15px;
        }

        .back-btn:hover {
            background-color: #4d5256;
        }
    </style>
</head>
<body style="background-color:#f5f5f5">
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link">Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="vendedor/dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="vendedor/perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link exit-button no-underline">Sair</a>
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
<div class="container" style="max-width:700px;margin:120px auto auto;padding:30px;background:#fff;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
    <?php
        // Buscar dados ANTES do HTML
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            $produto = [];
            $vendedor = [];
            $usuario = [];
            
            if ($tipo === 'produto' && $produto_id) {
                $sql_produto = "SELECT nome FROM produtos WHERE id = :produto_id";
                $stmt_produto = $conn->prepare($sql_produto);
                $stmt_produto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                $stmt_produto->execute();
                $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC) ?: [];
            } 
            elseif ($tipo === 'vendedor' && $vendedor_id) {
                $sql_vendedor = "SELECT u.nome 
                                FROM vendedores v 
                                INNER JOIN usuarios u ON v.usuario_id = u.id 
                                WHERE v.id = :vendedor_id";
                $stmt_vendedor = $conn->prepare($sql_vendedor);
                $stmt_vendedor->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
                $stmt_vendedor->execute();
                $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC) ?: [];
                
                if (empty($vendedor)) {
                    $sql_usuario = "SELECT nome FROM usuarios WHERE id = :vendedor_id";
                    $stmt_usuario = $conn->prepare($sql_usuario);
                    $stmt_usuario->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
                    $stmt_usuario->execute();
                    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC) ?: [];
                }
            }
        } catch (Exception $e) {
            // Erro silencioso
        }
        ?>
    <h2 style="margin-bottom:25px;color:#333;border-bottom:2px solid #f0f0f0;padding-bottom:10px;">
        Avaliar - 
        <?php if ($tipo === 'produto' && isset($produto['nome'])): ?>
            <?php echo htmlspecialchars($produto['nome']); ?>
        <?php elseif ($tipo === 'vendedor' && isset($usuario['nome'])): ?>
            <?php echo htmlspecialchars($usuario['nome']); ?>
        <?php endif; ?>
    </h2>
    <?php if ($erro): ?>
        <div style="background:#ffeded;padding:12px 15px;border-radius:6px;color:#a00;border-left:4px solid #d32f2f;margin-bottom:20px;"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    <?php if ($sucesso): ?>
        <div style="background:#e6ffed;padding:12px 15px;border-radius:6px;color:#080;border-left:4px solid #4CAF50;margin-bottom:20px;">
            <?php echo htmlspecialchars($sucesso); ?>
        </div>
            <center><button type="button" class="back-btn" onclick="history.back(history.back())">Voltar</button></center>
        <?php else: ?>
    <form method="POST" id="avaliacaoForm">
        <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">
        <input type="hidden" name="produto_id" value="<?php echo htmlspecialchars($produto_id); ?>">
        <input type="hidden" name="vendedor_id" value="<?php echo htmlspecialchars($vendedor_id); ?>">
        <input type="hidden" name="proposta_id" value="<?php echo htmlspecialchars($proposta_id); ?>">
        <input type="hidden" name="entrega_id" value="<?php echo htmlspecialchars($entrega_id); ?>">
        <input type="hidden" name="nota" id="notaInput" value="0" required>

        <div class="rating-container">
            <span class="stars-label">Avaliação:</span>
            <div class="stars" id="starsContainer">
                <input type="radio" id="star5" name="rating" value="5" class="star-rating-input">
                <label for="star5" class="star" data-value="5" title="Excelente">★</label>
                
                <input type="radio" id="star4" name="rating" value="4" class="star-rating-input">
                <label for="star4" class="star" data-value="4" title="Bom">★</label>
                
                <input type="radio" id="star3" name="rating" value="3" class="star-rating-input">
                <label for="star3" class="star" data-value="3" title="Regular">★</label>
                
                <input type="radio" id="star2" name="rating" value="2" class="star-rating-input">
                <label for="star2" class="star" data-value="2" title="Ruim">★</label>
                
                <input type="radio" id="star1" name="rating" value="1" class="star-rating-input">
                <label for="star1" class="star" data-value="1" title="Péssimo">★</label>
            </div>
            <div class="star-label-text" id="ratingText">Clique nas estrelas para avaliar</div>
            <div class="rating-error" id="ratingError">Por favor, selecione uma nota</div>
        </div>
        
        <div style="margin-top:20px;">
            <label style="display:block;font-weight:bold;margin-bottom:8px;">Comentário (opcional)</label>
            <textarea name="comentario" rows="4" style="width:100%;padding:12px;border:1px solid #ddd;border-radius:6px;font-size:1rem;resize:vertical;" placeholder="Compartilhe sua experiência..."></textarea>
        </div>
        
        <div style="margin-top:25px;text-align:center;">
            <button type="button" class="cancel-btn" onclick="history.back()">Cancelar</button>
            <button type="submit" class="submit-btn" id="submitBtn" disabled>Enviar avaliação</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star');
    const notaInput = document.getElementById('notaInput');
    const ratingText = document.getElementById('ratingText');
    const ratingError = document.getElementById('ratingError');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('avaliacaoForm');
    
    const ratingLabels = {
        1: 'Péssimo',
        2: 'Ruim', 
        3: 'Regular',
        4: 'Bom',
        5: 'Excelente'
    };
    
    // Adicionar evento de clique às estrelas
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            notaInput.value = value;
            
            // Atualizar visual das estrelas
            stars.forEach(s => {
                const sValue = s.getAttribute('data-value');
                if (sValue <= value) {
                    s.classList.add('selected');
                } else {
                    s.classList.remove('selected');
                }
            });
            
            // Atualizar texto
            ratingText.textContent = ratingLabels[value] + ' (' + value + ' estrelas)';
            ratingText.style.color = '#4CAF50';
            ratingError.style.display = 'none';
            
            // Habilitar botão de enviar
            submitBtn.disabled = false;
            
            // Marcar o radio button correspondente
            const radio = document.getElementById('star' + value);
            if (radio) radio.checked = true;
        });
        
        // Efeito hover
        star.addEventListener('mouseover', function() {
            const value = this.getAttribute('data-value');
            stars.forEach(s => {
                const sValue = s.getAttribute('data-value');
                if (sValue <= value) {
                    s.style.color = '#ffc107';
                }
            });
        });
        
        star.addEventListener('mouseout', function() {
            const currentRating = notaInput.value;
            stars.forEach(s => {
                const sValue = s.getAttribute('data-value');
                if (currentRating === '0') {
                    s.style.color = '#ddd';
                } else {
                    if (sValue <= currentRating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '#ddd';
                    }
                }
            });
        });
    });
    
    // Validação do formulário
    form.addEventListener('submit', function(e) {
        if (notaInput.value === '0') {
            e.preventDefault();
            ratingError.textContent = 'Por favor, selecione uma nota clicando nas estrelas';
            ratingError.style.display = 'block';
            return false;
        }
        
        // Desabilitar botão durante envio
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';
        return true;
    });
});
</script>
</body>
</html>