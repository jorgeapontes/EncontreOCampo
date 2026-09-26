<?php
// cron_utils.php
// Base comum dos cron jobs: bloqueio de acesso via navegador, log em arquivo
// e trava contra execução simultânea. Incluir no topo de cada script de cron,
// antes de qualquer saída:
//
//   require_once __DIR__ . '/cron_utils.php';
//   cronIniciar('nome_do_cron');

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

/**
 * Prepara o log do dia e garante que só uma instância do cron rode por vez.
 * Se outra execução ainda estiver em andamento (ex.: travou e o próximo
 * agendamento chegou), esta sai sem fazer nada.
 */
function cronIniciar($nome) {
    global $CRON_LOG_FILE, $CRON_LOCK;

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $CRON_LOG_FILE = $logDir . '/' . $nome . '_' . date('Y-m-d') . '.log';

    // flock é liberado automaticamente pelo sistema quando o processo termina,
    // mesmo em caso de erro fatal, então não fica trava "presa".
    $CRON_LOCK = @fopen($logDir . '/' . $nome . '.lock', 'c');
    if ($CRON_LOCK && !flock($CRON_LOCK, LOCK_EX | LOCK_NB)) {
        cronLog('⚠️ Outra execução deste cron ainda está em andamento. Saindo.');
        exit(0);
    }
}

/**
 * Escreve na saída (que o cron da Hostinger captura) e no arquivo de log do dia.
 */
function cronLog($msg) {
    global $CRON_LOG_FILE;
    $linha = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    echo $linha;
    if ($CRON_LOG_FILE) {
        @file_put_contents($CRON_LOG_FILE, $linha, FILE_APPEND | LOCK_EX);
    }
}
