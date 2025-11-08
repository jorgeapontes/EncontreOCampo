<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Encontre o Campo</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <!-- Login Section -->
    <section class="login-section">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">
                    <h1>ENCONTRE</h1>
                    <h2>O CAMPO</h2>
                </div>
                <h3>Faça login na sua conta</h3>
            </div>

            <form id="loginForm">
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
                    Não tem uma conta? <a href="index.php#contato">Registre-se</a>
                </div>
            </form>
        </div>
    </section>

    <script>
        // Navbar toggle for mobile
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        hamburger.addEventListener("click", () => {
            hamburger.classList.toggle("active");
            navMenu.classList.toggle("active");
        });

        // Close mobile menu when clicking on a link
        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
            } else {
                navbar.style.backgroundColor = 'var(--white)';
                navbar.style.backdropFilter = 'none';
            }
        });

        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            // Simulação de login - em um sistema real, isso seria uma requisição AJAX
            if (email && password) {
                alert('Login realizado com sucesso! Redirecionando...');
                // Aqui você redirecionaria para a página principal ou dashboard
                // window.location.href = 'dashboard.php';
            } else {
                alert('Por favor, preencha todos os campos.');
            }
        });

        // Social login buttons
        document.querySelectorAll('.social-btn').forEach(button => {
            button.addEventListener('click', function() {
                const platform = this.textContent.trim();
                alert(`Login com ${platform} - Esta funcionalidade seria implementada com APIs específicas.`);
            });
        });
    </script>
</body>
</html>