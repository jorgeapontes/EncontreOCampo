<?php
/**
 * SCRIPT DE DIAGNÓSTICO COMPLETO PARA EMAILS
 * Este script ajuda a identificar exatamente o que está acontecendo com o envio de emails
 */

session_start();

// Verificar se é POST para enviar teste
$enviando = $_SERVER['REQUEST_METHOD'] === 'POST';
$resultado = null;
$mensagem = '';

if ($enviando) {
    try {
        // Carregar o PHPMailer corrigido
        require_once __DIR__ . '/send_notification.php';
        
        $email_teste = $_POST['email'] ?? 'rafaeltonetti.cardoso@gmail.com';
        $nome_teste = $_POST['nome'] ?? 'Usuário Teste';
        
        // Tentar enviar
        $resultado = enviarEmailNotificacao(
            $email_teste,
            $nome_teste,
            'Teste de Diagnóstico - Encontre o Campo',
            'Este é um teste completo do sistema de notificações. Se você recebeu este email, o problema foi resolvido!'
        );
        
        if ($resultado) {
            $mensagem = "✅ EMAIL ENVIADO COM SUCESSO!\n\nVerifique a caixa de entrada e pasta de spam de: $email_teste";
        } else {
            $mensagem = "❌ FALHA AO ENVIAR\n\nVerifique os logs do PHP para detalhes. Veja o final desta página.";
        }
    } catch (Exception $e) {
        $mensagem = "❌ EXCEÇÃO: " . $e->getMessage();
        error_log("Teste diagnóstico exception: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Email - Encontre o Campo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 2em; margin-bottom: 10px; }
        .content { padding: 30px; }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .section h2 { 
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .info-box {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 3px solid #764ba2;
            font-family: monospace;
            font-size: 0.9em;
            overflow-x: auto;
        }
        .status {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin: 5px 0;
        }
        .ok { background: #4CAF50; color: white; }
        .warning { background: #FF9800; color: white; }
        .error { background: #f44336; color: white; }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="email"], input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }
        button:hover { transform: translateY(-2px); }
        .message {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            white-space: pre-wrap;
            font-weight: bold;
        }
        .message.success { 
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            color: #2e7d32;
        }
        .message.error { 
            background: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
        }
        .log-section {
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        .log-line { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Diagnóstico de Email</h1>
            <p>Sistema de Notificações - Encontre o Campo</p>
        </div>

        <div class="content">
            <!-- Resultado da Última Tentativa -->
            <?php if ($enviando && $mensagem): ?>
                <div class="message <?php echo $resultado ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>

            <!-- VERIFICAÇÕES DO SISTEMA -->
            <div class="section">
                <h2>✓ Verificações do Sistema</h2>

                <!-- PHP Version -->
                <div class="info-box">
                    <strong>PHP Version:</strong> 
                    <span class="status ok"><?php echo phpversion(); ?></span>
                </div>

                <!-- Extensions -->
                <div class="info-box">
                    <strong>OpenSSL:</strong>
                    <?php echo extension_loaded('openssl') ? '<span class="status ok">✓ Carregado</span>' : '<span class="status error">✗ Não disponível</span>'; ?>
                </div>

                <!-- Mail Function -->
                <div class="info-box">
                    <strong>mail() nativa:</strong>
                    <?php echo function_exists('mail') ? '<span class="status ok">✓ Disponível</span>' : '<span class="status error">✗ Desabilitada</span>'; ?>
                </div>

                <!-- Autoload -->
                <div class="info-box">
                    <strong>Composer Autoload:</strong>
                    <?php echo file_exists(__DIR__ . '/../vendor/autoload.php') ? '<span class="status ok">✓ Existe</span>' : '<span class="status error">✗ Não encontrado</span>'; ?>
                </div>

                <!-- PHPMailer -->
                <div class="info-box">
                    <strong>PHPMailer Files:</strong>
                    <?php 
                    $files = [
                        'PHPMailer.php' => __DIR__ . '/PHPMailer-master/src/PHPMailer.php',
                        'SMTP.php' => __DIR__ . '/PHPMailer-master/src/SMTP.php',
                        'Exception.php' => __DIR__ . '/PHPMailer-master/src/Exception.php'
                    ];
                    $all_ok = true;
                    foreach ($files as $name => $path) {
                        $ok = file_exists($path);
                        $all_ok = $all_ok && $ok;
                        echo ($ok ? '✓' : '✗') . " $name | ";
                    }
                    echo $all_ok ? '<span class="status ok">Tudo OK</span>' : '<span class="status error">Algum arquivo faltando</span>';
                    ?>
                </div>

                <!-- .env File -->
                <div class="info-box">
                    <strong>.env File:</strong>
                    <?php echo file_exists(__DIR__ . '/../.env') ? '<span class="status ok">✓ Existe</span>' : '<span class="status error">✗ Não encontrado</span>'; ?>
                </div>
            </div>

            <!-- CONFIGURAÇÕES SMTP -->
            <div class="section">
                <h2>📧 Configurações SMTP Carregadas</h2>
                
                <?php
                require __DIR__ . '/../vendor/autoload.php';
                $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
                $dotenv->load();
                ?>

                <div class="info-box">
                    <strong>Host:</strong> <?php echo htmlspecialchars($_ENV['SMTP_HOST'] ?? 'NÃO DEFINIDO'); ?>
                </div>

                <div class="info-box">
                    <strong>Port:</strong> <?php echo htmlspecialchars($_ENV['SMTP_PORT'] ?? 'NÃO DEFINIDO'); ?>
                </div>

                <div class="info-box">
                    <strong>Username:</strong> <?php echo htmlspecialchars($_ENV['SMTP_USERNAME'] ?? 'NÃO DEFINIDO'); ?>
                </div>

                <div class="info-box">
                    <strong>Encryption:</strong> 
                    <?php 
                    $encryption = $_ENV['SMTP_ENCRYPTION'] ?? 'NÃO DEFINIDO';
                    $status = $encryption === 'tls' ? 'STARTTLS' : ($encryption === 'ssl' ? 'SMTPS' : $encryption);
                    echo htmlspecialchars($status);
                    ?>
                </div>

                <div class="info-box">
                    <strong>Password:</strong> 
                    <?php echo isset($_ENV['SMTP_PASSWORD']) && !empty($_ENV['SMTP_PASSWORD']) ? '<span class="status ok">✓ Definida</span>' : '<span class="status error">✗ Não definida</span>'; ?>
                </div>
            </div>

            <!-- TESTE DE ENVIO -->
            <div class="section">
                <h2>📤 Teste de Envio de Email</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="email">Email de Teste:</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? 'seu-email@example.com'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" name="nome" id="nome" value="<?php echo htmlspecialchars($_POST['nome'] ?? 'Usuário Teste'); ?>" required>
                    </div>

                    <button type="submit">🚀 Enviar Email de Teste</button>
                </form>
            </div>

            <!-- SOLUÇÃO DE PROBLEMAS -->
            <div class="section">
                <h2>🔧 Checklist de Solução de Problemas</h2>
                
                <div class="info-box">
                    ✓ Arquivo <strong>send_notification.php</strong> foi atualizado com:<br>
                    - Conversão de PORT para (int)<br>
                    - Charset UTF8 configurado<br>
                    - SMTPDebug e Debugoutput configurados<br>
                    - Timeout aumentado para 10s<br>
                    - Melhor captura de exceções<br>
                    - Logs mais detalhados
                </div>

                <div class="info-box">
                    ✓ Arquivo <strong>email_config.php</strong> também foi atualizado<br>
                    com as mesmas correções para recuperação de senha.
                </div>

                <div class="info-box">
                    <strong>Próximos passos:</strong><br>
                    1. Execute o teste acima com seu email real<br>
                    2. Verifique a caixa de SPAM/Lixo<br>
                    3. Veja os logs de erro do PHP:<br>
                    📁 C:\xampp\apache\logs\error.log
                </div>
            </div>

            <!-- LOG DISPLAY -->
            <div class="section">
                <h2>📋 Últimas Linhas do Error Log</h2>
                <div class="log-section">
                    <?php
                    $error_log = 'C:\\xampp\\apache\\logs\\error.log';
                    if (file_exists($error_log)) {
                        $lines = array_slice(file($error_log), -50); // Últimas 50 linhas
                        foreach ($lines as $line) {
                            if (strpos($line, 'smtp') !== false || 
                                strpos($line, 'email') !== false || 
                                strpos($line, 'mail') !== false ||
                                strpos($line, 'notif') !== false) {
                                echo '<div class="log-line">' . htmlspecialchars($line) . '</div>';
                            }
                        }
                        if (empty(array_filter($lines))) {
                            echo '<div class="log-line">Nenhum erro relacionado a email encontrado.</div>';
                        }
                    } else {
                        echo '<div class="log-line">Não foi possível acessar o arquivo de log.</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
