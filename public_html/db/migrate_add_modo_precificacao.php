<?php
// Script de migração rápido para adicionar a coluna modo_precificacao
// Uso: php db\migrate_add_modo_precificacao.php

require_once __DIR__ . '/../src/conexao.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo "Erro: não foi possível conectar ao banco. Verifique as credenciais em src/conexao.php\n";
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/migrations/001_add_modo_precificacao.sql');
if ($sql === false) {
    echo "Erro ao ler arquivo de migração.\n";
    exit(1);
}

try {
    // As instruções SQL podem conter múltiplos comandos; usamos exec para executar o batch
    $db->exec($sql);
    echo "Migração executada com sucesso: modo_precificacao adicionada na tabela produtos.\n";
} catch (PDOException $e) {
    echo "Erro ao executar migração: " . $e->getMessage() . "\n";
    exit(1);
}

?>
