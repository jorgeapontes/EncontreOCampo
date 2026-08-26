<?php

require_once __DIR__ . '/../src/conexao.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "Erro: não foi possível conectar ao banco. Verifique as credenciais em src/conexao.php\n";
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/migrations/002_add_embalagem_columns.sql');
if ($sql === false) {
    echo "Erro ao ler arquivo de migração (002).\n";
    exit(1);
}

try {
    $db->exec($sql);
    echo "Migração 002 executada com sucesso: colunas de embalagem adicionadas.\n";
} catch (PDOException $e) {
    echo "Erro ao executar migração 002: " . $e->getMessage() . "\n";
    exit(1);
}

?>
