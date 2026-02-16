<?php
// Carregar autoload e variáveis de ambiente, se disponível
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();
    }
}

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'encontre_ocampo';
$username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'root';
$password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "OK: Conectado ao MySQL com sucesso.\n";
} catch (PDOException $e) {
    echo "ERRO PDO: " . $e->getMessage() . "\n";
    echo "DSN usada: mysql:host={$host};dbname={$db_name};charset=utf8\n";
    exit(1);
}

?>
