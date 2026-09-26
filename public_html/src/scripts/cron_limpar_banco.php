<?php
// cron_limpar_banco.php
// Expurga registros antigos das tabelas de auditoria/segurança do banco.
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador.
//
// Cron (Hostinger) - 1x por dia, 04:00:
// 0 4 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/src/scripts/cron_limpar_banco.php

require_once __DIR__ . '/cron_utils.php';
cronIniciar('cron_limpar_banco');

// =============================================
// CONFIGURAÇÃO
// =============================================
// Para cada tabela:
//   dias           => retenção (registros mais antigos que isso são apagados)
//   colunas_data   => nomes possíveis da coluna de data/hora (usa a 1ª que existir)
//   extra_where    => condição adicional opcional
$TABELAS = [
    // Registros de acesso (IP + data/hora de cada login). O Marco Civil da
    // Internet (Lei 12.965/2014, art. 15) obriga a guardar por NO MÍNIMO 6 meses.
    // Não reduzir abaixo de 183 dias; 190 dá margem.
    'log_acessos' => [
        'dias'         => 190,
        'colunas_data' => ['data_tentativa', 'data_hora', 'criado_em', 'created_at', 'data', 'timestamp'],
    ],
    // Contadores de rate limit de login por IP. Só apaga IPs fora de bloqueio.
    'tentativas_ip' => [
        'dias'         => 7,
        'colunas_data' => ['ultima_tentativa', 'atualizado_em', 'updated_at', 'data'],
        'extra_where'  => '(bloqueado_ate IS NULL OR bloqueado_ate < NOW())',
    ],
    // Referenciada no código, mas ainda não existe em produção (é ignorada até ser criada).
    'log_alteracoes' => [
        'dias'         => 180,
        'colunas_data' => ['data', 'data_hora', 'criado_em', 'created_at'],
    ],
    // Notificações do painel já lidas há mais de 6 meses. As não lidas nunca são apagadas.
    'notificacoes' => [
        'dias'         => 180,
        'colunas_data' => ['data_criacao'],
        'extra_where'  => 'lida = 1',
    ],
];

$LOTE     = 2000;   // linhas por DELETE (evita lock longo)
$PAUSA_MS = 100;    // pausa entre lotes (ms)

// =============================================
// INÍCIO
// =============================================
cronLog('========================================');
cronLog('INICIANDO LIMPEZA DO BANCO');
cronLog('========================================');

require_once __DIR__ . '/../conexao.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
    cronLog('✅ Conexão estabelecida.');
} catch (Throwable $e) {
    cronLog('❌ ERRO CRÍTICO: ' . $e->getMessage());
    cronLog('FINALIZADO COM ERRO');
    error_log('CRON LIMPAR BANCO - ERRO: ' . $e->getMessage());
    exit(1);
}

$stmtTabelaExiste = $db->prepare(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = :t"
);
$stmtColunas = $db->prepare(
    "SELECT column_name FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = :t"
);

$totalGeral = 0;
$tabelasComErro = [];

foreach ($TABELAS as $tabela => $cfg) {
    cronLog('----------------------------------------');
    cronLog("Tabela: $tabela (retenção: {$cfg['dias']} dias)");

    // Cada tabela é independente: um erro aqui não impede a limpeza das demais.
    try {
        // 1) A tabela existe?
        $stmtTabelaExiste->execute([':t' => $tabela]);
        if ((int)$stmtTabelaExiste->fetchColumn() === 0) {
            cronLog('   ⚠️ Tabela não encontrada — ignorando.');
            continue;
        }

        // 2) Descobrir a coluna de data
        $stmtColunas->execute([':t' => $tabela]);
        $colunasExistentes = array_map('strtolower', $stmtColunas->fetchAll(PDO::FETCH_COLUMN));

        $colData = null;
        foreach ($cfg['colunas_data'] as $candidata) {
            if (in_array(strtolower($candidata), $colunasExistentes, true)) {
                $colData = $candidata;
                break;
            }
        }

        if ($colData === null) {
            cronLog('   ❌ Nenhuma coluna de data conhecida encontrada ('
                . implode(', ', $cfg['colunas_data']) . '). Pulando por segurança.');
            $tabelasComErro[] = $tabela;
            continue;
        }

        // 3) Montar WHERE
        $where = "`$colData` < (NOW() - INTERVAL " . (int)$cfg['dias'] . ' DAY)';
        if (!empty($cfg['extra_where'])) {
            $where .= ' AND ' . $cfg['extra_where'];
        }

        // 4) Quantos serão afetados
        $qtd = (int)$db->query("SELECT COUNT(*) FROM `$tabela` WHERE $where")->fetchColumn();
        if ($qtd === 0) {
            cronLog("   ✅ Nada a apagar (coluna `$colData`).");
            continue;
        }
        cronLog("   🔎 $qtd registro(s) a apagar (coluna `$colData`).");

        // 5) DELETE em lotes
        $apagadosTabela = 0;
        $sqlDelete = "DELETE FROM `$tabela` WHERE $where LIMIT $LOTE";
        do {
            $del = (int)$db->exec($sqlDelete);
            $apagadosTabela += $del;
            if ($del === $LOTE) {
                usleep($PAUSA_MS * 1000);
            }
        } while ($del === $LOTE);

        cronLog("   ✅ $apagadosTabela registro(s) removido(s).");
        $totalGeral += $apagadosTabela;

    } catch (Throwable $e) {
        cronLog('   ❌ ERRO: ' . $e->getMessage());
        error_log("CRON LIMPAR BANCO - erro na tabela $tabela: " . $e->getMessage());
        $tabelasComErro[] = $tabela;
    }
}

cronLog('========================================');
cronLog("RESUMO: $totalGeral registro(s) removido(s) no total.");
if ($tabelasComErro) {
    cronLog('FINALIZADO COM ERROS em: ' . implode(', ', $tabelasComErro));
    cronLog('========================================');
    exit(1);
}
cronLog('FINALIZADO COM SUCESSO');
cronLog('========================================');
exit(0);
