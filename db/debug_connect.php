<?php
$host = 'localhost';
$db_name = 'encontre_ocampo';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "OK: Conectado ao MySQL com sucesso.\n";
} catch (PDOException $e) {
    echo "ERRO PDO: " . $e->getMessage() . "\n";
    echo "DSN usada: mysql:host={$host};dbname={$db_name};charset=utf8\n";
    exit(1);
}

?>
