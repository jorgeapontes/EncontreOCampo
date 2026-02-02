#!/usr/bin/env php
<?php
/**
 * Script de teste rápido para verificar sistema de emails
 * Use via CLI: php testar_emails_cli.php
 */

// Determinar diretório base
$dir_base = dirname(__FILE__);
chdir($dir_base);

// Cores para output no terminal
class Color {
    const GREEN = "\033[92m";
    const RED = "\033[91m";
    const YELLOW = "\033[93m";
    const BLUE = "\033[94m";
    const RESET = "\033[0m";
    
    public static function success($msg) {
        echo self::GREEN . "✓ " . $msg . self::RESET . "\n";
    }
    
    public static function error($msg) {
        echo self::RED . "✗ " . $msg . self::RESET . "\n";
    }
    
    public static function warning($msg) {
        echo self::YELLOW . "⚠ " . $msg . self::RESET . "\n";
    }
    
    public static function info($msg) {
        echo self::BLUE . "ℹ " . $msg . self::RESET . "\n";
    }
}

// Header
echo "\n";
echo Color::BLUE . "╔════════════════════════════════════════════════════════╗\n";
echo Color::BLUE . "║     TESTE DE EMAIL ENCONTRE O CAMPO - CLI             ║\n";
echo Color::BLUE . "║     Sistema de Diagnóstico Rápido                     ║\n";
echo Color::BLUE . "╚════════════════════════════════════════════════════════╝\n" . Color::RESET;
echo "\n";

// 1. Verificar arquivos
echo Color::BLUE . "1. VERIFICAÇÃO DE ARQUIVOS\n" . Color::RESET;
echo str_repeat("─", 50) . "\n";

$files_check = [
    'vendor/autoload.php' => 'Autoload do Composer',
    '.env' => 'Arquivo de configuração',
    'includes/send_notification.php' => 'Função de notificações',
    'includes/email_config.php' => 'Configuração de email',
    'includes/PHPMailer-master/src/PHPMailer.php' => 'PHPMailer',
    'includes/PHPMailer-master/src/SMTP.php' => 'SMTP do PHPMailer',
];

$all_files_ok = true;
foreach ($files_check as $file => $desc) {
    if (file_exists($file)) {
        Color::success("$desc ($file)");
    } else {
        Color::error("$desc ($file) - FALTANDO!");
        $all_files_ok = false;
    }
}

if (!$all_files_ok) {
    Color::error("Alguns arquivos estão faltando. Abortando.");
    exit(1);
}

// 2. Carregar configurações
echo "\n" . Color::BLUE . "2. VERIFICAÇÃO DE CONFIGURAÇÕES\n" . Color::RESET;
echo str_repeat("─", 50) . "\n";

require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$config = [
    'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'NÃO DEFINIDO',
    'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? 'NÃO DEFINIDO',
    'SMTP_USERNAME' => $_ENV['SMTP_USERNAME'] ?? 'NÃO DEFINIDO',
    'SMTP_ENCRYPTION' => $_ENV['SMTP_ENCRYPTION'] ?? 'NÃO DEFINIDO',
];

$config_ok = true;
foreach ($config as $key => $value) {
    if ($value === 'NÃO DEFINIDO' || empty($value)) {
        Color::error("$key = $value");
        $config_ok = false;
    } else {
        if ($key === 'SMTP_PASSWORD') {
            Color::success("$key = ••••••••••");
        } else {
            Color::success("$key = $value");
        }
    }
}

if (isset($_ENV['SMTP_PASSWORD']) && !empty($_ENV['SMTP_PASSWORD'])) {
    Color::success("SMTP_PASSWORD = ••••••••••");
} else {
    Color::error("SMTP_PASSWORD não definida!");
    $config_ok = false;
}

if (!$config_ok) {
    Color::error("Configurações incompletas no .env");
    exit(1);
}

// 3. Teste de PHP
echo "\n" . Color::BLUE . "3. VERIFICAÇÃO DO AMBIENTE PHP\n" . Color::RESET;
echo str_repeat("─", 50) . "\n";

Color::success("PHP Version: " . phpversion());
Color::info("Extensions necessárias:");
echo "  ";
echo extension_loaded('openssl') ? Color::GREEN . "✓ OpenSSL" . Color::RESET : Color::RED . "✗ OpenSSL" . Color::RESET;
echo "  ";
echo extension_loaded('sockets') ? Color::GREEN . "✓ Sockets" . Color::RESET : Color::RED . "✗ Sockets" . Color::RESET;
echo "\n";

if (function_exists('fsockopen')) {
    Color::success("fsockopen disponível");
} else {
    Color::warning("fsockopen não disponível (pode causar problemas)");
}

// 4. Teste de Conectividade
echo "\n" . Color::BLUE . "4. TESTE DE CONECTIVIDADE SMTP\n" . Color::RESET;
echo str_repeat("─", 50) . "\n";

$host = $_ENV['SMTP_HOST'];
$port = (int)$_ENV['SMTP_PORT'];

// DNS Resolution
Color::info("Resolvendo DNS para $host...");
$ip = gethostbyname($host);
if ($ip && $ip !== $host) {
    Color::success("DNS resolvido: $host = $ip");
} else {
    Color::error("Não foi possível resolver DNS para $host");
}

// Port Connection
Color::info("Testando conexão em $host:$port...");
$connection = @fsockopen($host, $port, $errno, $errstr, 5);
if ($connection) {
    Color::success("Conexão bem-sucedida em $host:$port");
    fclose($connection);
} else {
    Color::error("Falha ao conectar: $errstr (Código: $errno)");
}

// 5. Teste de Email
echo "\n" . Color::BLUE . "5. TESTE DE ENVIO DE EMAIL\n" . Color::RESET;
echo str_repeat("─", 50) . "\n";

// Pedir email do usuário
echo "Email para teste (pressione Enter para pular): ";
$email = trim(fgets(STDIN));

if (!empty($email)) {
    // Carregar a função
    require_once 'includes/send_notification.php';
    
    Color::info("Enviando email de teste para: $email");
    $resultado = enviarEmailNotificacao(
        $email,
        'Usuário Teste',
        'Teste de Sistema - Encontre o Campo',
        'Se você recebeu este email, o sistema está funcionando corretamente!'
    );
    
    if ($resultado) {
        Color::success("Email enviado com sucesso!");
        Color::warning("Verifique sua caixa de entrada (incluindo SPAM)");
    } else {
        Color::error("Falha ao enviar email");
        Color::warning("Verifique C:\\xampp\\apache\\logs\\error.log para mais detalhes");
    }
} else {
    Color::warning("Teste de envio pulado");
}

// 6. Resumo Final
echo "\n" . Color::BLUE . "6. RESUMO\n" . Color::RESET;
echo str_repeat("─", 50) . "\n";

if ($all_files_ok && $config_ok) {
    Color::success("Todas as verificações preliminares passaram!");
    Color::info("Use a ferramenta web para testes mais completos:");
    Color::info("http://localhost/EncontreOCampo/includes/teste_diagnostico_email.php");
} else {
    Color::error("Existem problemas a corrigir");
}

echo "\n";
Color::BLUE . "═══════════════════════════════════════════════════════\n" . Color::RESET;
?>
