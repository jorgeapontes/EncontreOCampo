<?php
// cron_limpar_banco.php
// Expurga registros antigos das tabelas de auditoria/segurança do banco.
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador.
//
// Cron sugerido (Hostinger) - 1x por dia, 04:00:
// 0 4 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/src/scripts/cron_limpar_banco.php

set_time_limit(0);
date_default_timezone_set('America/Sao_Paulo');

// =============================================
// SEGURANÇA: Só permite execução via CLI (cron)
// =============================================
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado. Este script só pode ser executado via linha de comando.');
}

// =============================================
// CONFIGURAÇÃO
// =============================================
// Para cada tabela:
//   dias           => retenção (registros mais antigos que isso são apagados)
//   colunas_data   => nomes possíveis da coluna de data/hora (usa a 1ª que existir)
//   extra_where    => condição adicional opcional (ex.: não apagar registros ainda em bloqueio)
$TABELAS = [
    'log_acessos' => [
        'dias'         => 90,
        'colunas_data' => ['data_tentativa', 'data_hora', 'criado_em', 'created_at', 'data', 'timestamp'],
    ],
    'tentativas_ip' => [
        'dias'         => 7,
        'colunas_data' => ['ultima_tentativa', 'atualizado_em', 'updated_at', 'data'],
        // Só apaga IPs que não estão mais em janela de bloqueio.
        'extra_where'  => '(bloqueado_ate IS NULL OR bloqueado_ate < NOW())',
    ],
    'log_alteracoes' => [
        'dias'         => 180,
        'colunas_data' => ['data', 'data_hora', 'criado_em', 'created_at'],
    ],
];

$LOTE          = 2000;   // linhas por DELETE (evita lock longo)
$PAUSA_MS      = 100;    // pausa entre lotes (ms)

// =============================================
// LOG
// =============================================
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/cron_limpar_banco_' . date('Y-m-d') . '.log';

function logMessage($msg) {
    global $log_file;
    $full_msg = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    echo $full_msg;
    file_put_contents($log_file, $full_msg, FILE_APPEND);
}

// =============================================
// INÍCIO
// =============================================
logMessage('========================================');
logMessage('INICIANDO LIMPEZA DO BANCO');
logMessage('========================================');

require_once __DIR__ . '/../conexao.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
    logMessage('✅ Conexão estabelecida.');

    $totalGeral = 0;

    foreach ($TABELAS as $tabela => $cfg) {
        logMessage('----------------------------------------');
        logMessage("Tabela: $tabela (retenção: {$cfg['dias']} dias)");

        // 1) A tabela existe?
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :t"
        );
        $stmt->execute([':t' => $tabela]);
        if ((int) $stmt->fetchColumn() === 0) {
            logMessage("   ⚠️ Tabela não encontrada — ignorando.");
            continue;
        }

        // 2) Descobrir a coluna de data
        $stmt = $db->prepare(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :t"
        );
        $stmt->execute([':t' => $tabela]);
        $colunasExistentes = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $colData = null;
        foreach ($cfg['colunas_data'] as $candidata) {
            if (in_array(strtolower($candidata), $colunasExistentes, true)) {
                $colData = $candidata;
                break;
            }
        }

        if ($colData === null) {
            logMessage('   ❌ Nenhuma coluna de data conhecida encontrada ('
                . implode(', ', $cfg['colunas_data']) . '). Pulando por segurança.');
            continue;
        }
        logMessage("   Coluna de data: `$colData`");

        // 3) Montar WHERE
        $where = "`$colData` < (NOW() - INTERVAL {$cfg['dias']} DAY)";
        if (!empty($cfg['extra_where'])) {
            $where .= ' AND ' . $cfg['extra_where'];
        }

        // 4) Quantos serão afetados
        $qtd = (int) $db->query("SELECT COUNT(*) FROM `$tabela` WHERE $where")->fetchColumn();
        if ($qtd === 0) {
            logMessage('   ✅ Nada a apagar.');
            continue;
        }
        logMessage("   🔎 $qtd registro(s) a apagar.");

        // 5) DELETE em lotes
        $apagadosTabela = 0;
        $sqlDelete = "DELETE FROM `$tabela` WHERE $where LIMIT $LOTE";
        do {
            $del = $db->exec($sqlDelete);
            $apagadosTabela += $del;
            if ($del > 0) {
                logMessage("   … $apagadosTabela/$qtd");
                usleep($PAUSA_MS * 1000);
            }
        } while ($del > 0);

        logMessage("   ✅ $apagadosTabela registro(s) removido(s) de $tabela.");
        $totalGeral += $apagadosTabela;
    }

    logMessage('========================================');
    logMessage("RESUMO: $totalGeral registro(s) removido(s) no total.");
    logMessage('FINALIZADO COM SUCESSO');
    logMessage('========================================');
    exit(0);

} catch (Exception $e) {
    logMessage('❌ ERRO CRÍTICO: ' . $e->getMessage());
    logMessage('   Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine());
    logMessage('FINALIZADO COM ERRO');
    logMessage('========================================');
    error_log('CRON LIMPAR BANCO - ERRO: ' . $e->getMessage());
    exit(1);
}
