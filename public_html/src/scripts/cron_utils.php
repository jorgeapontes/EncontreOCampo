<?php
// cron_utils.php
// Base comum dos cron jobs: bloqueio de acesso via navegador, log em arquivo,
// trava contra execução simultânea e histórico das execuções (tabela
// cron_execucoes, vista no monitoramento do master). Incluir no topo de cada
// script de cron, antes de qualquer saída:
//
//   require_once __DIR__ . '/cron_utils.php';
//   cronIniciar('nome_do_cron');
//
// No histórico, a execução fica como "alerta" se alguma linha do log começar
// com ❌ (ou se o script morrer com erro fatal); a linha "RESUMO: ..." vira o
// resumo. Linhas com ⚠️ são só informativas.

// =============================================
// SEGURANÇA: Só permite execução via CLI (cron)
// =============================================
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado. Este script só pode ser executado via linha de comando.');
}

set_time_limit(0);
date_default_timezone_set('America/Sao_Paulo');

$CRON_LOG_FILE = null;
$CRON_LOCK     = null;
$CRON_NOME     = null;
$CRON_INICIO   = null;
$CRON_ALERTAS  = [];   // linhas com ❌
$CRON_RESUMO   = null; // última linha "RESUMO: ..."
$CRON_EXTRA    = [];   // colunas extras de cron_execucoes (tamanhos, backup, email_enviado)

/**
 * Prepara o log do dia e garante que só uma instância do cron rode por vez.
 * Se outra execução ainda estiver em andamento (ex.: travou e o próximo
 * agendamento chegou), esta sai sem fazer nada.
 */
function cronIniciar($nome) {
    global $CRON_LOG_FILE, $CRON_LOCK, $CRON_NOME, $CRON_INICIO;

    $CRON_NOME   = $nome;
    $CRON_INICIO = microtime(true);
    register_shutdown_function('cronRegistrarExecucao');

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $CRON_LOG_FILE = $logDir . '/' . $nome . '_' . date('Y-m-d') . '.log';

    // flock é liberado automaticamente pelo sistema quando o processo termina,
    // mesmo em caso de erro fatal, então não fica trava "presa".
    $CRON_LOCK = @fopen($logDir . '/' . $nome . '.lock', 'c');
    if ($CRON_LOCK && !flock($CRON_LOCK, LOCK_EX | LOCK_NB)) {
        cronLog('❌ Outra execução deste cron ainda está em andamento (travou?). Saindo.');
        exit(0);
    }
}

/**
 * Escreve na saída (que o cron da Hostinger captura) e no arquivo de log do dia.
 */
function cronLog($msg) {
    global $CRON_LOG_FILE, $CRON_ALERTAS, $CRON_RESUMO;
    $linha = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    echo $linha;
    if ($CRON_LOG_FILE) {
        @file_put_contents($CRON_LOG_FILE, $linha, FILE_APPEND | LOCK_EX);
    }

    $texto = trim($msg);
    if (strpos($texto, '❌') === 0) {
        $CRON_ALERTAS[] = trim(substr($texto, strlen('❌')));
    } elseif (strpos($texto, 'RESUMO:') === 0) {
        $CRON_RESUMO = trim(substr($texto, strlen('RESUMO:')));
    }
}

/**
 * Grava a execução em cron_execucoes ao fim do script (registrada como shutdown
 * function, então roda também depois de exit() e de erro fatal). Se o banco
 * estiver fora, só registra no log de erros: o cron em si já terminou.
 */
function cronRegistrarExecucao() {
    global $CRON_NOME, $CRON_INICIO, $CRON_ALERTAS, $CRON_RESUMO, $CRON_EXTRA;

    $erro = error_get_last();
    if ($erro && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $CRON_ALERTAS[] = 'Erro fatal: ' . $erro['message'] . ' (' . basename($erro['file']) . ':' . $erro['line'] . ')';
    }

    try {
        require_once __DIR__ . '/../conexao.php';
        $db = (new Database())->getConnection();
        if (!$db) {
            throw new RuntimeException('sem conexão com o banco');
        }
        $stmt = $db->prepare(
            "INSERT INTO cron_execucoes (cron, data_hora, duracao_segundos, status, resumo, alertas, email_enviado,
                                         tamanho_banco, tamanho_uploads, tamanho_backups, ultimo_backup)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $CRON_NOME,
            date('Y-m-d H:i:s', (int) $CRON_INICIO),
            (int) round(microtime(true) - $CRON_INICIO),
            $CRON_ALERTAS ? 'alerta' : 'ok',
            $CRON_RESUMO !== null ? mb_substr($CRON_RESUMO, 0, 1000) : null,
            $CRON_ALERTAS ? mb_substr(implode("\n", $CRON_ALERTAS), 0, 5000) : null,
            !empty($CRON_EXTRA['email_enviado']) ? 1 : 0,
            $CRON_EXTRA['tamanho_banco'] ?? null,
            $CRON_EXTRA['tamanho_uploads'] ?? null,
            $CRON_EXTRA['tamanho_backups'] ?? null,
            $CRON_EXTRA['ultimo_backup'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log("CRON {$CRON_NOME}: não foi possível gravar em cron_execucoes: " . $e->getMessage());
    }
}
