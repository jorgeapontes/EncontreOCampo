<?php
/**
 * Função auxiliar para testar conectividade SMTP
 * Use isso quando quiser verificar se pode conectar ao servidor SMTP
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Testa a conectividade SMTP
 * 
 * @return array Com as chaves: 'success' (bool), 'mensagens' (array), 'erros' (array)
 */
function testarConexaoSMTP() {
    $resultado = [
        'success' => false,
        'mensagens' => [],
        'erros' => []
    ];

    try {
        $host = $_ENV['SMTP_HOST'] ?? null;
        $port = (int)($_ENV['SMTP_PORT'] ?? 587);
        $username = $_ENV['SMTP_USERNAME'] ?? null;
        $password = $_ENV['SMTP_PASSWORD'] ?? null;
        $encryption = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';

        // Validar configurações
        if (!$host || !$username || !$password) {
            $resultado['erros'][] = 'Configurações SMTP incompletas no .env';
            return $resultado;
        }

        $resultado['mensagens'][] = "Host: $host:$port";
        $resultado['mensagens'][] = "User: $username";
        $resultado['mensagens'][] = "Encryption: $encryption";

        // Tentar conectar
        $smtp = new SMTP();
        $smtp->Debugoutput = 'error_log';

        $resultado['mensagens'][] = "Conectando a $host:$port...";

        if (!$smtp->connect($host, $port)) {
            $resultado['erros'][] = "Falha ao conectar ao servidor SMTP";
            return $resultado;
        }

        $resultado['mensagens'][] = "✓ Conectado com sucesso";

        // Tentar autenticar
        $resultado['mensagens'][] = "Autenticando como $username...";

        if (!$smtp->authenticate($username, $password)) {
            $resultado['erros'][] = "Autenticação falhou - verifique username/password";
            $smtp->quit();
            return $resultado;
        }

        $resultado['mensagens'][] = "✓ Autenticação bem-sucedida";
        $resultado['success'] = true;

        $smtp->quit();

    } catch (Exception $e) {
        $resultado['erros'][] = "Exceção: " . $e->getMessage();
    }

    return $resultado;
}

// Se acessado diretamente, executa o teste
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "=== TESTE DE CONECTIVIDADE SMTP ===\n\n";

    $teste = testarConexaoSMTP();

    echo "MENSAGENS:\n";
    foreach ($teste['mensagens'] as $msg) {
        echo "  • $msg\n";
    }

    if (!empty($teste['erros'])) {
        echo "\nERROS:\n";
        foreach ($teste['erros'] as $erro) {
            echo "  ✗ $erro\n";
        }
    }

    echo "\nRESULTADO: " . ($teste['success'] ? "✓ SUCESSO" : "✗ FALHA") . "\n";
}

// Exportar função para uso em outros scripts
// Exemplo: require_once 'testar_smtp.php'; $resultado = testarConexaoSMTP();
?>
