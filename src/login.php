<?php
<<<<<<< HEAD
// Garante que a sessão seja iniciada caso o conexao.php não faça isso
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

=======
// A sessão é iniciada dentro de conexao.php (nome customizado EOC_SESSID,
// cookie Secure/HttpOnly/SameSite). NÃO chame session_start() aqui antes
// disso — isso faria o PHP abrir uma sessão padrão (PHPSESSID) e o bloco
// de configuração de conexao.php seria pulado, gerando duas sessões
// diferentes (um dos sintomas: login funciona mas páginas internas não
// reconhecem o usuário logado).
>>>>>>> b6d3c10850f74ecd3c1cdc77ec76a3e05271cb5c
require_once 'conexao.php';

// Define o fuso horário para garantir que o tempo de bloqueio seja preciso
date_default_timezone_set('America/Sao_Paulo');

<<<<<<< HEAD
// Variáveis de Segurança (Configuráveis)
$MAX_TENTATIVAS = 5;          // Número de erros permitidos
$TEMPO_BLOQUEIO_MINUTOS = 15; // Tempo de castigo/esquecimento em minutos
=======
// =====================================================================
// Variáveis de Segurança (Configuráveis)
// =====================================================================
$MAX_TENTATIVAS_CONTA  = 5;   // Erros permitidos por CONTA antes de bloquear
$MAX_TENTATIVAS_IP     = 15;  // Erros permitidos por IP antes de bloquear (cobre credential stuffing)
$TEMPO_BLOQUEIO_MINUTOS = 15; // Tempo de bloqueio em minutos (conta e IP)

// Hash fictício usado para igualar o tempo de resposta quando o e-mail
// não existe no banco (mitiga timing attack / user enumeration)
$HASH_FICTICIO = '$2y$10$wH8z8K5JZ0qjA1L1G5cF1u3p5gk0F2qzN1y8b2v6mQk9b3hYV8e1S';
>>>>>>> b6d3c10850f74ecd3c1cdc77ec76a3e05271cb5c

$erro = '';
$ip_atual = getClientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =================================================================
    // VALIDAÇÃO DO TOKEN CSRF
    // =================================================================
    $csrf_valido = isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

<<<<<<< HEAD
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
=======
    if (!$csrf_valido) {
        $erro = "Sua sessão expirou ou a requisição é inválida. Atualize a página e tente novamente.";
    } else {

        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];

        if (!empty($email) && !empty($password)) {
            $database = new Database();
            $db = $database->getConnection();
            $agora = new DateTime();

            // =============================================================
            // 1) VERIFICA BLOQUEIO POR IP (credential stuffing / força bruta)
            // =============================================================
            $ip_bloqueado = false;

            $queryIp = "SELECT id, tentativas, bloqueado_ate FROM tentativas_ip WHERE ip = :ip";
            $stmtIp = $db->prepare($queryIp);
            $stmtIp->bindParam(':ip', $ip_atual);
            $stmtIp->execute();
            $regIp = $stmtIp->fetch(PDO::FETCH_ASSOC);

            if ($regIp) {
                // Janela de esquecimento do IP: se passou o tempo e não atingiu o limite, zera
                if (!empty($regIp['bloqueado_ate'])) {
                    $validadeIp = new DateTime($regIp['bloqueado_ate']);

                    if ($regIp['tentativas'] < $MAX_TENTATIVAS_IP && $agora > $validadeIp) {
                        $sqlResetIp = "UPDATE tentativas_ip SET tentativas = 0, bloqueado_ate = NULL WHERE ip = :ip";
                        $stmtResetIp = $db->prepare($sqlResetIp);
                        $stmtResetIp->bindParam(':ip', $ip_atual);
                        $stmtResetIp->execute();
                        $regIp['tentativas'] = 0;
                        $regIp['bloqueado_ate'] = null;
                    }

                    // Verifica se o IP está efetivamente bloqueado agora
                    if ($regIp['tentativas'] >= $MAX_TENTATIVAS_IP && !empty($regIp['bloqueado_ate'])) {
                        $bloqueioIp = new DateTime($regIp['bloqueado_ate']);
                        if ($agora < $bloqueioIp) {
                            $ip_bloqueado = true;
                            $diff = $agora->diff($bloqueioIp);
                            $minutos_restantes = $diff->i + ($diff->h * 60) + ($diff->days * 24 * 60) + 1;
                            $erro = "Muitas tentativas detectadas a partir do seu endereço. Tente novamente em {$minutos_restantes} minuto(s).";
                        }
                    }
                }
            }

            if (!$ip_bloqueado) {

                // =========================================================
                // 2) BUSCA O USUÁRIO PELO EMAIL
                // =========================================================
                $query = "SELECT id, email, senha, tipo, nome, status, tentativas_falhas, bloqueado_ate FROM usuarios WHERE email = :email";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                $usuarioEncontrado = ($usuario !== false);

                $bloqueado = false;

                if ($usuarioEncontrado) {
                    // =========================================================
                    // JANELA DE ESQUECIMENTO (ZERAR ERROS POR TEMPO) - CONTA
                    // =========================================================
                    if ($usuario['tentativas_falhas'] > 0 && !empty($usuario['bloqueado_ate'])) {
                        $validade_erro = new DateTime($usuario['bloqueado_ate']);

                        if ($usuario['tentativas_falhas'] < $MAX_TENTATIVAS_CONTA && $agora > $validade_erro) {
                            $sqlResetTempo = "UPDATE usuarios SET tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = :id";
                            $stmtResetTempo = $db->prepare($sqlResetTempo);
                            $stmtResetTempo->bindParam(':id', $usuario['id']);
                            $stmtResetTempo->execute();

                            $usuario['tentativas_falhas'] = 0;
                            $usuario['bloqueado_ate'] = null;
                        }
                    }

                    // Verifica se a conta está atualmente bloqueada (estouro de limite)
                    if ($usuario['tentativas_falhas'] >= $MAX_TENTATIVAS_CONTA && !empty($usuario['bloqueado_ate'])) {
                        $bloqueio = new DateTime($usuario['bloqueado_ate']);

                        if ($agora < $bloqueio) {
                            $bloqueado = true;
                            $diff = $agora->diff($bloqueio);
                            $minutos_restantes = $diff->i + ($diff->h * 60) + ($diff->days * 24 * 60) + 1;
                            $erro = "Por motivos de segurança, sua conta foi temporariamente bloqueada. Tente novamente em {$minutos_restantes} minuto(s).";
                        }
                    }
                }

                // =========================================================
                // 3) VALIDAÇÃO DA SENHA
                //    Sempre executamos password_verify(), mesmo se o e-mail
                //    não existir, para igualar o tempo de resposta entre os
                //    dois casos (mitigação de timing attack).
                // =========================================================
                if (!$bloqueado) {

                    $hashParaComparar = $usuarioEncontrado ? $usuario['senha'] : $HASH_FICTICIO;
                    $senhaCorreta = password_verify($password, $hashParaComparar);

                    if ($usuarioEncontrado && $senhaCorreta) {
                        // ---> SENHA CORRETA <---
                        if ($usuario['status'] === 'ativo' || $usuario['status'] === 'pendente') {

                            // Reseta contadores de erro da CONTA
                            $sqlReset = "UPDATE usuarios SET tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = :id";
                            $stmtReset = $db->prepare($sqlReset);
                            $stmtReset->bindParam(':id', $usuario['id']);
                            $stmtReset->execute();

                            // Reseta contador de erro do IP também
                            $sqlResetIpOk = "UPDATE tentativas_ip SET tentativas = 0, bloqueado_ate = NULL WHERE ip = :ip";
                            $stmtResetIpOk = $db->prepare($sqlResetIpOk);
                            $stmtResetIpOk->bindParam(':ip', $ip_atual);
                            $stmtResetIpOk->execute();

                            // Log de acesso bem-sucedido
                            registrarLogAcesso($db, $usuario['id'], $email, $ip_atual, true, null);

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

                            // Token CSRF não é mais necessário após o login bem-sucedido nesta página
                            unset($_SESSION['csrf_token']);

                            // Redirecionar baseado no tipo de usuário
                            switch ($usuario['tipo']) {
                                case 'admin':
                                    header("Location: admin/dashboard.php");
                                    break;
                                case 'comprador':
                                case 'vendedor':
                                case 'transportador':
                                    if ($usuario['status'] === 'pendente') {
                                        header("Location: ../index.php");
                                    } else {
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
                            registrarLogAcesso($db, $usuario['id'], $email, $ip_atual, false, 'conta_inativa');
                        }
                    } else {
                        // ---> SENHA INCORRETA (ou e-mail não encontrado) <---

                        // --- Atualiza contador da CONTA (somente se o e-mail existir) ---
                        if ($usuarioEncontrado) {
                            $tentativas = $usuario['tentativas_falhas'] + 1;

                            $futuro = new DateTime();
                            $futuro->modify("+{$TEMPO_BLOQUEIO_MINUTOS} minutes");
                            $bloqueado_ate = $futuro->format('Y-m-d H:i:s');

                            if ($tentativas >= $MAX_TENTATIVAS_CONTA) {
                                $erro = "Muitas tentativas incorretas. Sua conta foi bloqueada por {$TEMPO_BLOQUEIO_MINUTOS} minutos.";
                            } else {
                                $tentativas_restantes = $MAX_TENTATIVAS_CONTA - $tentativas;
                                $erro = "Email ou senha incorretos. Restam {$tentativas_restantes} tentativa(s).";
                            }

                            $sqlUpdate = "UPDATE usuarios SET tentativas_falhas = :tentativas, bloqueado_ate = :bloqueado_ate WHERE id = :id";
                            $stmtUpdate = $db->prepare($sqlUpdate);
                            $stmtUpdate->bindParam(':tentativas', $tentativas, PDO::PARAM_INT);
                            $stmtUpdate->bindParam(':bloqueado_ate', $bloqueado_ate);
                            $stmtUpdate->bindParam(':id', $usuario['id']);
                            $stmtUpdate->execute();

                            registrarLogAcesso($db, $usuario['id'], $email, $ip_atual, false, 'senha_incorreta');
                        } else {
                            // Mensagem genérica para não revelar se o e-mail existe
                            $erro = "Email ou senha incorretos.";
                            registrarLogAcesso($db, null, $email, $ip_atual, false, 'email_nao_encontrado');
                        }

                        // --- Atualiza contador do IP (sempre, exista o e-mail ou não) ---
                        $futuroIp = new DateTime();
                        $futuroIp->modify("+{$TEMPO_BLOQUEIO_MINUTOS} minutes");
                        $bloqueadoAteIp = $futuroIp->format('Y-m-d H:i:s');

                        $tentativasIpAtual = $regIp ? $regIp['tentativas'] + 1 : 1;

                        $sqlUpsertIp = "INSERT INTO tentativas_ip (ip, tentativas, bloqueado_ate, ultima_tentativa)
                                         VALUES (:ip, :tentativas, :bloqueado_ate, NOW())
                                         ON DUPLICATE KEY UPDATE
                                            tentativas = :tentativas2,
                                            bloqueado_ate = :bloqueado_ate2,
                                            ultima_tentativa = NOW()";
                        $stmtUpsertIp = $db->prepare($sqlUpsertIp);
                        $stmtUpsertIp->bindParam(':ip', $ip_atual);
                        $stmtUpsertIp->bindParam(':tentativas', $tentativasIpAtual, PDO::PARAM_INT);
                        $stmtUpsertIp->bindParam(':bloqueado_ate', $bloqueadoAteIp);
                        $stmtUpsertIp->bindParam(':tentativas2', $tentativasIpAtual, PDO::PARAM_INT);
                        $stmtUpsertIp->bindParam(':bloqueado_ate2', $bloqueadoAteIp);
                        $stmtUpsertIp->execute();
                    }
                }
            }
        } else {
            $erro = "Por favor, preencha todos os campos.";
        }
    }
}

// =====================================================================
// GERA UM NOVO TOKEN CSRF PARA O FORMULÁRIO (sempre, no fim do processamento)
// =====================================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Registra uma tentativa de login na tabela de auditoria log_acessos.
 * Falhas no log NUNCA devem interromper o fluxo de login, por isso
 * o erro é apenas registrado no error_log do servidor.
 */
function registrarLogAcesso($db, $usuarioId, $email, $ip, $sucesso, $motivoFalha) {
    try {
        $sql = "INSERT INTO log_acessos (usuario_id, email_tentado, ip, user_agent, sucesso, motivo_falha)
                VALUES (:usuario_id, :email, :ip, :user_agent, :sucesso, :motivo_falha)";
        $stmt = $db->prepare($sql);
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $sucessoInt = $sucesso ? 1 : 0;
        $stmt->bindParam(':usuario_id', $usuarioId, $usuarioId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':user_agent', $userAgent);
        $stmt->bindParam(':sucesso', $sucessoInt, PDO::PARAM_INT);
        $stmt->bindParam(':motivo_falha', $motivoFalha);
        $stmt->execute();
    } catch (Exception $e) {
        error_log('Falha ao registrar log_acessos: ' . $e->getMessage());
>>>>>>> b6d3c10850f74ecd3c1cdc77ec76a3e05271cb5c
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
                <div class="alert alert-danger"><?php echo escapeHtml($erro); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($_SESSION['csrf_token']); ?>">

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