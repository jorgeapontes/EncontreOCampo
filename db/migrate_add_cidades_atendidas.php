<?php
// Script de migração para adicionar coluna cidades_atendidas
// Uso: php db\migrate_add_cidades_atendidas.php

require_once __DIR__ . '/../src/conexao.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "Erro: não foi possível conectar ao banco. Verifique as credenciais em src/conexao.php\n";
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/migrations/004_add_cidades_atendidas.sql');
if ($sql === false) {
    echo "Erro ao ler arquivo de migração 004.\n";
    exit(1);
}

try {
    $db->exec($sql);
    echo "Migração 004 executada com sucesso: coluna cidades_atendidas adicionada na tabela vendedores.\n";
} catch (PDOException $e) {
    echo "Erro ao executar migração 004: " . $e->getMessage() . "\n";
    exit(1);
}

?>
