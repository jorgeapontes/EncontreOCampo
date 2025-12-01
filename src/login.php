<?php
require_once 'conexao.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT id, email, senha, tipo, nome, status FROM usuarios WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $usuario['senha'])) {

                if ($usuario['status'] === 'pendente') {
                    // Mensagem clara para o usuário
                    $_SESSION['erro_login'] = "Sua solicitação de cadastro está em análise. Por favor, aguarde a aprovação do administrador.";
                    header("Location: ../index.php#login");
                    exit();
                }


                elseif ($usuario['status'] === 'ativo') {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_email'] = $usuario['email'];
                    $_SESSION['usuario_tipo'] = $usuario['tipo'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];
                    
                    // Redirecionar baseado no tipo de usuário
                    switch ($usuario['tipo']) {
                        case 'admin':
                            header("Location: admin/dashboard.php");
                            break;
                        case 'comprador':
                            header("Location: anuncios.php");
                            break;
                        case 'vendedor':
                            header("Location: vendedor/dashboard.php");
                            break;
                        case 'transportador':
                            header("Location: transportador/dashboard.php");
                            break;
                        default:
                            header("Location: ../index.php");
                    }
                    exit();
                } else {
                    $erro = "Conta pendente de aprovação";
                }
            } else {
                $erro = "Senha incorreta";
            }
        } else {
            $erro = "Usuário não encontrado";
        }
    } else {
        $erro = "Por favor, preencha todos os campos";
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
                    <a href="#" class="forgot-password">Esqueceu a senha?</a>
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