<?php
// Garante que a sessão seja iniciada caso o conexao.php não faça isso
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexao.php';

// Define o fuso horário para garantir que o tempo de bloqueio seja preciso
date_default_timezone_set('America/Sao_Paulo');

// Variáveis de Segurança (Configuráveis)
$MAX_TENTATIVAS = 5;          // Número de erros permitidos
$TEMPO_BLOQUEIO_MINUTOS = 15; // Tempo de castigo/esquecimento em minutos

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();

        // Busca o usuário pelo email, trazendo também os dados de bloqueio
        $query = "SELECT id, email, senha, tipo, nome, status, tentativas_falhas, bloqueado_ate FROM usuarios WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $agora = new DateTime();
            $bloqueado = false;

            // ==========================================================
            // JANELA DE ESQUECIMENTO (ZERAR ERROS POR TEMPO)
            // ==========================================================
            if ($usuario['tentativas_falhas'] > 0 && !empty($usuario['bloqueado_ate'])) {
                $validade_erro = new DateTime($usuario['bloqueado_ate']);
                
                // Se o usuário NÃO atingiu o limite máximo mas o tempo do último erro já passou, reseta os erros
                if ($usuario['tentativas_falhas'] < $MAX_TENTATIVAS && $agora > $validade_erro) {
                    $sqlResetTempo = "UPDATE usuarios SET tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = :id";
                    $stmtResetTempo = $db->prepare($sqlResetTempo);
                    $stmtResetTempo->bindParam(':id', $usuario['id']);
                    $stmtResetTempo->execute();
                    
                    // Atualiza as variáveis locais para o restante do script
                    $usuario['tentativas_falhas'] = 0;
                    $usuario['bloqueado_ate'] = null;
                }
            }

            // Verifica se a conta está atualmente bloqueada (estouro de limite)
            if ($usuario['tentativas_falhas'] >= $MAX_TENTATIVAS && !empty($usuario['bloqueado_ate'])) {
                $bloqueio = new DateTime($usuario['bloqueado_ate']);
                
                if ($agora < $bloqueio) {
                    $bloqueado = true;
                    $diff = $agora->diff($bloqueio);
                    $minutos_restantes = $diff->i + ($diff->h * 60) + ($diff->days * 24 * 60) + 1; // Arredonda para cima
                    $erro = "Por motivos de segurança, sua conta foi temporariamente bloqueada. Tente novamente em {$minutos_restantes} minuto(s).";
                }
            }

            // Se não estiver bloqueado, prossegue com a validação da senha
            if (!$bloqueado) {
                if (password_verify($password, $usuario['senha'])) {
                    // ---> SENHA CORRETA <---
                    if ($usuario['status'] === 'ativo' || $usuario['status'] === 'pendente') {
                        
                        // Reseta os contadores de erro no banco de dados
                        $sqlReset = "UPDATE usuarios SET tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = :id";
                        $stmtReset = $db->prepare($sqlReset);
                        $stmtReset->bindParam(':id', $usuario['id']);
                        $stmtReset->execute();

                        // ==========================================
                        // PROTEÇÃO CONTRA SESSION HIJACKING/FIXATION
                        // ==========================================
                        session_regenerate_id(true);

                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['usuario_email'] = $usuario['email'];
                        $_SESSION['usuario_tipo'] = $usuario['tipo'];
                        $_SESSION['usuario_nome'] = $usuario['nome'];
                        $_SESSION['usuario_status'] = $usuario['status'];
                        
                        // Sinalizar login recente para vendedores (para mostrar aviso nas páginas)
                        if ($usuario['tipo'] === 'vendedor') {
                            $_SESSION['login_recente_vendedor'] = true;
                        }
                        
                        // Redirecionar baseado no tipo de usuário
                        switch ($usuario['tipo']) {
                            case 'admin':
                                header("Location: admin/dashboard.php");
                                break;
                            case 'comprador':
                            case 'vendedor':
                            case 'transportador':
                                // Para usuários não-admin com status pendente
                                if ($usuario['status'] === 'pendente') {
                                    header("Location: ../index.php");
                                } else {
                                    // Para status ativo
                                    switch ($usuario['tipo']) {
                                        case 'comprador':
                                            header("Location: anuncios.php");
                                            break;
                                        case 'vendedor':
                                            header("Location: vendedor/dashboard.php");
                                            break;
                                        case 'transportador':
                                            header("Location: transportador/dashboard.php");
                                            break;
                                    }
                                }
                                break;
                            default:
                                header("Location: ../index.php");
                        }
                        exit();
                    } else {
                        $erro = "Conta inativa ou suspensa.";
                    }
                } else {
                    // ---> SENHA INCORRETA <---
                    $tentativas = $usuario['tentativas_falhas'] + 1;
                    
                    // Define a validade desse erro ou do bloqueio (Sempre +15 minutos do horário atual)
                    $futuro = new DateTime();
                    $futuro->modify("+{$TEMPO_BLOQUEIO_MINUTOS} minutes");
                    $bloqueado_ate = $futuro->format('Y-m-d H:i:s');

                    if ($tentativas >= $MAX_TENTATIVAS) {
                        $erro = "Muitas tentativas incorretas. Sua conta foi bloqueada por {$TEMPO_BLOQUEIO_MINUTOS} minutos.";
                    } else {
                        $tentativas_restantes = $MAX_TENTATIVAS - $tentativas;
                        $erro = "Email ou senha incorretos. Restam {$tentativas_restantes} tentativa(s).";
                    }

                    // Atualiza o registro de falhas no banco
                    $sqlUpdate = "UPDATE usuarios SET tentativas_falhas = :tentativas, bloqueado_ate = :bloqueado_ate WHERE id = :id";
                    $stmtUpdate = $db->prepare($sqlUpdate);
                    $stmtUpdate->bindParam(':tentativas', $tentativas, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(':bloqueado_ate', $bloqueado_ate);
                    $stmtUpdate->bindParam(':id', $usuario['id']);
                    $stmtUpdate->execute();
                }
            }
        } else {
            // Mudei a mensagem de "Usuário não encontrado" para uma genérica.
            // Isso evita que invasores descubram quais e-mails existem no seu banco.
            $erro = "Email ou senha incorretos.";
        }
    } else {
        $erro = "Por favor, preencha todos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Encontre o Campo</title>
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="css/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <section class="login-section">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
                <h3>Faça login na sua conta</h3>
            </div>

            <?php if (!empty($erro)): ?>
                <div class="alert alert-danger"><?php echo $erro; ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="Sua senha" required>
                </div>

                <div class="login-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Lembrar-me</label>
                    </div>
                    <a href="../includes/forgot_password.php" class="forgot-password">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="login-button">Entrar</button>

                <div class="register-link">
                    Não tem uma conta? <a href="../index.php#contato">Registre-se</a>
                </div>
                <div class="register-link">
                    <a href="../index.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </div>

            </form>
        </div>
    </section>

    <script>
        // Navbar toggle for mobile
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        if (hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
        }

        // Close mobile menu when clicking on a link
        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (navbar) {
                if (window.scrollY > 50) {
                    navbar.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
                    navbar.style.backdropFilter = 'blur(10px)';
                } else {
                    navbar.style.backgroundColor = 'var(--white)';
                    navbar.style.backdropFilter = 'none';
                }
            }
        });
    </script>
</body>
</html>