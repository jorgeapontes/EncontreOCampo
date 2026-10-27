<?php
// src/functions/config_functions.php
// Configurações do site guardadas no banco (tabela configuracoes_site, chave/valor).
// Usado pelo monitoramento do master e pelos crons (e-mail de alerta, limite de disco).

require_once __DIR__ . '/../conexao.php';

function configConexao(): ?PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = (new Database())->getConnection();
    }
    return $pdo;
}

/** Valor da configuração, ou $padrao se ela não existir (ou estiver vazia). */
function obterConfig(string $chave, string $padrao = ''): string
{
    try {
        $stmt = configConexao()->prepare("SELECT valor FROM configuracoes_site WHERE chave = ?");
        $stmt->execute([$chave]);
        $valor = $stmt->fetchColumn();
        return ($valor === false || $valor === null || $valor === '') ? $padrao : (string) $valor;
    } catch (Throwable $e) {
        error_log("Configuração '{$chave}' indisponível: " . $e->getMessage());
        return $padrao;
    }
}

function salvarConfig(string $chave, string $valor): bool
{
    try {
        $stmt = configConexao()->prepare(
            "INSERT INTO configuracoes_site (chave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        );
        return $stmt->execute([$chave, $valor]);
    } catch (Throwable $e) {
        error_log("Não foi possível salvar a configuração '{$chave}': " . $e->getMessage());
        return false;
    }
}
