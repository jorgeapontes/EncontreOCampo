<?php
/**
 * Script para verificar possíveis bloqueios de rede
 * Útil para diagnosticar problemas de conectividade com servidor SMTP
 */

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$host = $_ENV['SMTP_HOST'] ?? 'smtp.hostinger.com';
$port = (int)($_ENV['SMTP_PORT'] ?? 587);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Conectividade de Rede</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
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
        .content { padding: 30px; }
        .test-section {
            margin-bottom: 25px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .test-section h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 3px solid #4CAF50;
        }
        .warning {
            background: #fff3e0;
            color: #e65100;
            border-left: 3px solid #FF9800;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            border-left: 3px solid #f44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Teste de Conectividade de Rede</h1>
            <p>Verificação de bloqueios e conectividade SMTP</p>
        </div>

        <div class="content">
            <!-- DNS Resolution -->
            <div class="test-section">
                <h3>🌐 Resolução de DNS</h3>
                <?php
                echo "<strong>Host:</strong> $host<br><br>";
                
                $ip = gethostbyname($host);
                if ($ip && $ip !== $host) {
                    echo "<div class='result success'>";
                    echo "✓ DNS resolvido<br>";
                    echo "IP: $ip";
                    echo "</div>";
                } else {
                    echo "<div class='result error'>";
                    echo "✗ DNS não pode ser resolvido<br>";
                    echo "Possível causa: Firewall/DNS bloqueando<br>";
                    echo "Solução: Contate seu ISP ou admin de rede";
                    echo "</div>";
                }
                ?>
            </div>

            <!-- Port Connection Test -->
            <div class="test-section">
                <h3>🔗 Teste de Conexão na Porta</h3>
                <?php
                echo "<strong>Host:</strong> $host:$port<br><br>";
                
                $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                if ($connection) {
                    echo "<div class='result success'>";
                    echo "✓ Conexão bem-sucedida na porta $port<br>";
                    echo "O servidor SMTP está acessível";
                    fclose($connection);
                    echo "</div>";
                } else {
                    echo "<div class='result error'>";
                    echo "✗ Não foi possível conectar<br>";
                    echo "<strong>Erro:</strong> $errstr (Código: $errno)<br><br>";
                    echo "<strong>Possíveis causas:</strong><br>";
                    echo "• Firewall bloqueando a porta $port<br>";
                    echo "• Servidor SMTP offline<br>";
                    echo "• Host/Port incorretos<br>";
                    echo "• Provedor bloqueando conexões SMTP<br>";
                    echo "</div>";
                }
                ?>
            </div>

            <!-- fsockopen availability -->
            <div class="test-section">
                <h3>🛠️ Funções PHP Necessárias</h3>
                <?php
                $functions_needed = [
                    'fsockopen' => function_exists('fsockopen'),
                    'gethostbyname' => function_exists('gethostbyname'),
                    'stream_socket_client' => function_exists('stream_socket_client'),
                ];

                foreach ($functions_needed as $func => $available) {
                    $class = $available ? 'success' : 'error';
                    $status = $available ? '✓' : '✗';
                    echo "<div class='result $class'>$status $func</div>";
                }
                ?>
            </div>

            <!-- Network Settings -->
            <div class="test-section">
                <h3>⚙️ Configurações de Rede do PHP</h3>
                <?php
                $safe_mode = ini_get('safe_mode');
                $disable_functions = ini_get('disable_functions');
                
                echo "Safe Mode: " . ($safe_mode ? '<span style="color: #f44336;">Ativado</span>' : '<span style="color: #4CAF50;">Desativado</span>') . "<br>";
                echo "Allow URL Fopen: " . (ini_get('allow_url_fopen') ? '<span style="color: #4CAF50;">Sim</span>' : '<span style="color: #f44336;">Não</span>') . "<br>";
                echo "Display Errors: " . (ini_get('display_errors') ? '<span style="color: #4CAF50;">Sim</span>' : '<span style="color: #f44336;">Não</span>') . "<br><br>";
                
                echo "<strong>Funções Desabilitadas:</strong><br>";
                if ($disable_functions) {
                    echo "<div class='result warning'>$disable_functions</div>";
                } else {
                    echo "<div class='result success'>Nenhuma função crítica desabilitada</div>";
                }
                ?>
            </div>

            <!-- Recomendações -->
            <div class="test-section">
                <h3>📋 Recomendações</h3>
                <div style="background: white; padding: 15px; border-radius: 5px; border-left: 3px solid #667eea;">
                    <p style="margin: 10px 0;"><strong>✓ Passe em todos os testes?</strong></p>
                    <p style="margin: 10px 0;">Então o problema está na autenticação SMTP. Verifique o username/password no .env</p>
                    
                    <p style="margin: 15px 0 10px 0;"><strong>✗ Falhou no teste de porta?</strong></p>
                    <p style="margin: 10px 0;">1. Teste com outra porta (geralmente 465 para SMTPS ou 587 para STARTTLS)</p>
                    <p style="margin: 10px 0;">2. Contate seu ISP - pode estar bloqueando SMTP</p>
                    <p style="margin: 10px 0;">3. Contate o suporte do seu host - pode ser restrição do servidor</p>
                    
                    <p style="margin: 15px 0 10px 0;"><strong>✗ DNS falhou?</strong></p>
                    <p style="margin: 10px 0;">1. Ping o servidor: verificar conectividade básica</p>
                    <p style="margin: 10px 0;">2. Verifique SMTP_HOST no .env - deve estar correto</p>
                    <p style="margin: 10px 0;">3. Contate seu ISP sobre bloqueios de DNS</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
