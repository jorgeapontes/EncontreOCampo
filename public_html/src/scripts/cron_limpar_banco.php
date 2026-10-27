<?php
// cron_limpar_banco.php
// Expurga registros antigos das tabelas de auditoria/segurança do banco.
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador.
//
// Cron (Hostinger) - 1x por dia, 04:00 UTC (01:00 de Brasília):
// 0 4 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/src/scripts/cron_limpar_banco.php
//
// Além da limpeza das tabelas, é a manutenção do site (vista na aba Saúde do master):
//   - tokens de "esqueci a senha" vencidos são apagados;
//   - backup semanal do banco (.sql.gz) em PASTA_PRIVADA/backups, fora do public_html:
//     4 mais recentes + 1 por mês dos últimos 6 meses, teto de 50 MB
//     (--backup-agora força um backup mesmo que o último tenha menos de 7 dias);
//   - log de erros do PHP (PASTA_PRIVADA/logs/erros_site.log): rotação acima de 5 MB
//     e contagem dos erros graves desde a última execução;
//   - tamanho do banco, uploads e backups, e alerta de disco (limite em configuracoes_site);
//   - UM e-mail de alerta (configuracoes_site.alerta_email) se esta ou qualquer outra
//     tarefa agendada teve problema nas últimas 24 h ou deixou de rodar.

require_once __DIR__ . '/cron_utils.php';
cronIniciar('cron_limpar_banco');

const BACKUP_INTERVALO_DIAS  = 7;
const BACKUP_MANTER_RECENTES = 4;
const BACKUP_MANTER_MESES    = 6;
const BACKUP_TETO_BYTES      = 50 * 1024 * 1024;
const ERROS_LOG_MAX_BYTES    = 5 * 1024 * 1024;
const DISCO_ALERTA_PERCENTUAL = 80;
const CRON_ATRASO_HORAS      = 30;
// Tarefas agendadas na Hostinger (o nome usado em cronIniciar), conferidas no e-mail de alerta
const CRONS_AGENDADOS = ['cron_vencimentos', 'limpar_logs', 'cron_limpar_banco', 'cron_rejeitados', 'cron_documentos_orfaos'];

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
    // Trilha de auditoria (IP já ofuscado) e histórico dos crons: 1 ano.
    'logs_auditoria' => [
        'dias'         => 365,
        'colunas_data' => ['data_hora'],
    ],
    'cron_execucoes' => [
        'dias'         => 365,
        'colunas_data' => ['data_hora'],
    ],
    // Eventos do Stripe: registro financeiro, 5 anos. As chamadas recusadas
    // (não vieram do Stripe) saem antes, no bloco "Chamadas recusadas" abaixo.
    'pagamentos_stripe' => [
        'dias'         => 1825,
        'colunas_data' => ['data_hora'],
    ],
];

$LOTE     = 2000;   // linhas por DELETE (evita lock longo)
$PAUSA_MS = 100;    // pausa entre lotes (ms)

// =============================================
// FUNÇÕES
// =============================================
function tamanhoLegivel(int $bytes): string {
    if ($bytes === 0) {
        return '0 KB';
    }
    return $bytes < 1048576
        ? max(1, round($bytes / 1024)) . ' KB'
        : number_format($bytes / 1048576, 1, ',', '.') . ' MB';
}

/** Tamanho total (bytes) de uma pasta, incluindo subpastas. */
function tamanhoPasta(string $pasta): int {
    if (!is_dir($pasta)) {
        return 0;
    }
    $total = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS)) as $arquivo) {
        if ($arquivo->isFile()) {
            $total += $arquivo->getSize();
        }
    }
    return $total;
}

/** Gera o dump completo do banco em .sql.gz (PHP puro, sem depender do mysqldump). */
function gerarBackup(PDO $db, string $destino): void {
    $temporario = $destino . '.parcial';
    $gz = gzopen($temporario, 'wb6');
    if (!$gz) {
        throw new RuntimeException('não foi possível criar o arquivo de backup');
    }
    $db->exec("SET NAMES utf8mb4");
    gzwrite($gz, "-- Backup do banco Encontre o Campo — " . date('d/m/Y H:i:s') . "\n"
        . "-- Para restaurar: importe este arquivo no phpMyAdmin (aba Importar) ou com o comando mysql.\n\n"
        . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

    $tabelas = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tabelas as $tabela) {
        $criacao = $db->query("SHOW CREATE TABLE `{$tabela}`")->fetch(PDO::FETCH_NUM)[1];
        gzwrite($gz, "DROP TABLE IF EXISTS `{$tabela}`;\n{$criacao};\n\n");

        // Lê sem carregar a tabela inteira na memória
        $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $linhas = $db->query("SELECT * FROM `{$tabela}`");
        $lote = [];
        $colunas = null;
        while ($linha = $linhas->fetch(PDO::FETCH_ASSOC)) {
            $colunas = $colunas ?? '`' . implode('`, `', array_keys($linha)) . '`';
            $valores = array_map(fn ($v) => $v === null ? 'NULL' : $db->quote((string) $v), $linha);
            $lote[] = '(' . implode(', ', $valores) . ')';
            if (count($lote) === 200) {
                gzwrite($gz, "INSERT INTO `{$tabela}` ({$colunas}) VALUES\n" . implode(",\n", $lote) . ";\n");
                $lote = [];
            }
        }
        $linhas->closeCursor();
        $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        if ($lote) {
            gzwrite($gz, "INSERT INTO `{$tabela}` ({$colunas}) VALUES\n" . implode(",\n", $lote) . ";\n");
        }
        gzwrite($gz, "\n");
    }
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n-- fim do backup (" . count($tabelas) . " tabelas)\n");
    gzclose($gz);

    // Confere se o arquivo abre e chega até o fim antes de considerá-lo válido
    $leitura = gzopen($temporario, 'rb');
    $final = '';
    while (!gzeof($leitura)) {
        $final = substr($final . gzread($leitura, 65536), -200);
    }
    gzclose($leitura);
    if (strpos($final, '-- fim do backup') === false) {
        @unlink($temporario);
        throw new RuntimeException('o arquivo gerado está incompleto');
    }
    rename($temporario, $destino);
}

/** Rotação: 4 mais recentes + o último de cada mês dos últimos 6 meses; depois o teto de tamanho. */
function rotacionarBackups(string $pasta): int {
    $arquivos = glob($pasta . '/backup_*.sql.gz') ?: [];
    rsort($arquivos); // nome tem a data (AAAA-MM-DD): ordem alfabética = cronológica
    $manter = array_slice($arquivos, 0, BACKUP_MANTER_RECENTES);
    $limiteMes = date('Y-m', strtotime('first day of -' . (BACKUP_MANTER_MESES - 1) . ' months'));
    $mesesVistos = [];
    foreach ($arquivos as $arquivo) {
        $mes = substr(basename($arquivo), 7, 7); // backup_AAAA-MM-DD
        if ($mes >= $limiteMes && !isset($mesesVistos[$mes])) {
            $mesesVistos[$mes] = true;
            $manter[] = $arquivo;
        }
    }
    $apagados = 0;
    foreach (array_diff($arquivos, $manter) as $arquivo) {
        @unlink($arquivo) && $apagados++;
    }

    // Teto de segurança: se a pasta passar do limite, apaga os mais antigos (sempre fica pelo menos 1)
    $restantes = array_values(array_intersect($arquivos, $manter));
    $total = array_sum(array_map('filesize', $restantes));
    if ($total > BACKUP_TETO_BYTES) {
        cronLog('   ❌ A pasta de backups passou de ' . tamanhoLegivel(BACKUP_TETO_BYTES) . ' (' . tamanhoLegivel($total) . '); os mais antigos foram apagados.');
        while ($total > BACKUP_TETO_BYTES && count($restantes) > 1) {
            $maisAntigo = array_pop($restantes);
            $total -= filesize($maisAntigo);
            @unlink($maisAntigo) && $apagados++;
        }
    }
    return $apagados;
}

/** Envia o e-mail de alerta da manutenção. Retorna se conseguiu enviar. */
function enviarAlertaManutencao(array $alertas, string $destino): bool {
    if (!$alertas || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    try {
        require_once __DIR__ . '/../../includes/send_notification.php';
        $itens = '';
        foreach ($alertas as $alerta) {
            $itens .= '<li>' . htmlspecialchars($alerta, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        return (bool) enviarEmailNotificacao($destino, 'Master', '[Encontre o Campo] Alerta da manutenção diária',
            'A manutenção diária de ' . date('d/m/Y H:i') . " encontrou o seguinte:<ul>{$itens}</ul>"
            . 'Detalhes na área do master: entre com o usuário master e abra <strong>Saúde</strong>.');
    } catch (Throwable $e) {
        error_log('CRON LIMPAR BANCO - e-mail de alerta: ' . $e->getMessage());
        return false;
    }
}

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
    // Sem banco não dá para ler o e-mail de alerta: usa a cópia guardada na pasta privada
    $copiaEmail = PASTA_PRIVADA . '/alerta_email.txt';
    enviarAlertaManutencao(['A manutenção NÃO rodou: ' . $e->getMessage()],
        is_file($copiaEmail) ? trim((string) file_get_contents($copiaEmail)) : '');
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

// =============================================
// NOTIFICAÇÕES ÓRFÃS (lidas ou não)
// Links para anúncio ou proposta que não existe mais. A exclusão de anúncio já
// apaga essas notificações (functions/notificacao_functions.php); isto pega as
// que ficaram de antes disso e qualquer caminho de exclusão que não passe por lá.
// O id vem da própria URL (REGEXP_SUBSTR existe no MySQL 8 e no MariaDB).
// =============================================
$ORFAS = [
    'anúncio inexistente' => "url REGEXP '[?&](produto_id|anuncio_id)=[0-9]+'
        AND NOT EXISTS (SELECT 1 FROM produtos p WHERE p.id =
            CAST(REGEXP_SUBSTR(REGEXP_SUBSTR(url, '(produto_id|anuncio_id)=[0-9]+'), '[0-9]+') AS UNSIGNED))",
    'proposta inexistente' => "url REGEXP 'detalhes_proposta(\\\\.php)?\\\\?id=[0-9]+'
        AND NOT EXISTS (SELECT 1 FROM propostas pr WHERE pr.ID =
            CAST(REGEXP_SUBSTR(REGEXP_SUBSTR(url, 'detalhes_proposta(\\\\.php)?\\\\?id=[0-9]+'), '[0-9]+$') AS UNSIGNED))",
];

cronLog('----------------------------------------');
cronLog('Notificações órfãs (link para anúncio/proposta apagado)');
foreach ($ORFAS as $motivo => $whereOrfa) {
    try {
        $qtd = (int)$db->query("SELECT COUNT(*) FROM notificacoes WHERE $whereOrfa")->fetchColumn();
        if ($qtd === 0) {
            cronLog("   ✅ Nada a apagar ($motivo).");
            continue;
        }
        $apagadas = (int)$db->exec("DELETE FROM notificacoes WHERE $whereOrfa");
        cronLog("   ✅ $apagadas notificação(ões) removida(s) ($motivo).");
        $totalGeral += $apagadas;
    } catch (Throwable $e) {
        cronLog("   ❌ ERRO ($motivo): " . $e->getMessage());
        error_log("CRON LIMPAR BANCO - erro nas notificações órfãs ($motivo): " . $e->getMessage());
        $tabelasComErro[] = 'notificacoes (órfãs)';
    }
}

// =============================================
// TOKENS DE "ESQUECI A SENHA" VENCIDOS
// =============================================
cronLog('----------------------------------------');
cronLog('Tokens de redefinição de senha vencidos');
try {
    $stmt = $db->prepare("UPDATE usuarios SET reset_token = NULL, reset_token_expira = NULL
                          WHERE reset_token IS NOT NULL AND reset_token_expira < ?");
    $stmt->execute([date('Y-m-d H:i:s')]);
    cronLog('   ✅ ' . $stmt->rowCount() . ' token(s) apagado(s).');
} catch (Throwable $e) {
    cronLog('   ❌ ERRO (tokens): ' . $e->getMessage());
    $tabelasComErro[] = 'usuarios (tokens)';
}

// =============================================
// CHAMADAS RECUSADAS NO WEBHOOK DO STRIPE (robôs/testes): 90 dias
// =============================================
cronLog('----------------------------------------');
cronLog('Chamadas recusadas no webhook do Stripe (retenção: 90 dias)');
try {
    $stmt = $db->prepare("DELETE FROM pagamentos_stripe WHERE status = 'recusado' AND data_hora < ?");
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-90 days'))]);
    cronLog('   ✅ ' . $stmt->rowCount() . ' registro(s) removido(s).');
} catch (Throwable $e) {
    cronLog('   ❌ ERRO (pagamentos_stripe): ' . $e->getMessage());
    $tabelasComErro[] = 'pagamentos_stripe';
}

require_once __DIR__ . '/../functions/config_functions.php';

// O endereço dos alertas fica no banco e é copiado para a pasta privada, para
// ainda dar para avisar quando o banco estiver fora do ar.
$emailAlerta = obterConfig('alerta_email', '');
if ($emailAlerta !== '') {
    @file_put_contents(PASTA_PRIVADA . '/alerta_email.txt', $emailAlerta);
}

// =============================================
// BACKUP SEMANAL DO BANCO
// =============================================
cronLog('----------------------------------------');
cronLog('Backup do banco');
$pastaBackups = PASTA_PRIVADA . '/backups';
$ultimoBackup = null;
try {
    if (!is_dir($pastaBackups) && !@mkdir($pastaBackups, 0750, true)) {
        throw new RuntimeException('não foi possível criar a pasta ' . $pastaBackups);
    }
    $existentes = glob($pastaBackups . '/backup_*.sql.gz') ?: [];
    rsort($existentes);
    $idadeDias = $existentes ? (time() - filemtime($existentes[0])) / 86400 : INF;
    if (in_array('--backup-agora', $argv ?? [], true) || $idadeDias >= BACKUP_INTERVALO_DIAS - 0.1) { // folga para o horário do cron variar
        $novo = $pastaBackups . '/backup_' . date('Y-m-d') . '.sql.gz';
        gerarBackup($db, $novo);
        cronLog('   ✅ Backup novo: ' . basename($novo) . ' (' . tamanhoLegivel(filesize($novo)) . ').');
    } else {
        cronLog('   ✅ Último backup há ' . floor($idadeDias) . ' dia(s); o próximo sai com ' . BACKUP_INTERVALO_DIAS . '.');
    }
    $apagados = rotacionarBackups($pastaBackups);
    if ($apagados) {
        cronLog("   🗑️  $apagados backup(s) antigo(s) apagado(s) pela rotação.");
    }
    $existentes = glob($pastaBackups . '/backup_*.sql.gz') ?: [];
    rsort($existentes);
    $ultimoBackup = $existentes ? date('Y-m-d H:i:s', filemtime($existentes[0])) : null;
} catch (Throwable $e) {
    cronLog('   ❌ O backup do banco falhou: ' . $e->getMessage());
    error_log('CRON LIMPAR BANCO - backup: ' . $e->getMessage());
}

// =============================================
// LOG DE ERROS DO SITE: rotação e erros graves desde a última execução
// =============================================
cronLog('----------------------------------------');
cronLog('Log de erros do site');
$arquivoErros = PASTA_PRIVADA . '/logs/erros_site.log';
try {
    $posicao = (int) obterConfig('cron_erros_posicao', '0');
    $errosGraves = $errosJs = 0;
    if (is_file($arquivoErros)) {
        $tamanho = filesize($arquivoErros);
        if ($tamanho < $posicao) {
            $posicao = 0; // o arquivo foi trocado/rotacionado
        }
        $novos = (string) file_get_contents($arquivoErros, false, null, $posicao, 5 * 1024 * 1024);
        $errosGraves = preg_match_all('/PHP Fatal error|Uncaught|SQLSTATE/', $novos);
        $errosJs = preg_match_all('/^\[[^\]]+\] JS: /m', $novos);
        $posicao = $tamanho;
        if ($tamanho > ERROS_LOG_MAX_BYTES) {
            rename($arquivoErros, PASTA_PRIVADA . '/logs/erros_site.anterior.log'); // guarda só a versão anterior
            $posicao = 0;
            cronLog('   🔄 Rotacionado (' . tamanhoLegivel($tamanho) . ').');
        }
    }
    salvarConfig('cron_erros_posicao', (string) $posicao);
    if ($errosGraves > 0) {
        cronLog("   ❌ {$errosGraves} erro(s) grave(s) no site desde a última manutenção (veja a aba Erros do monitoramento).");
    } else {
        cronLog('   ✅ Nenhum erro grave desde a última manutenção.');
    }
    if (!empty($errosJs)) {
        // Informativo: erros no navegador dos usuários (aba Erros), sem disparar alerta
        cronLog("   ℹ️ {$errosJs} erro(s) de JavaScript registrado(s) desde a última manutenção.");
    }
} catch (Throwable $e) {
    cronLog('   ❌ ERRO (log de erros): ' . $e->getMessage());
}

// =============================================
// TAMANHOS E ESPAÇO EM DISCO
// =============================================
cronLog('----------------------------------------');
cronLog('Tamanhos');
$raizSite = dirname(__DIR__, 2); // public_html
try {
    $tamanhoBanco   = (int) $db->query("SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    $tamanhoUploads = tamanhoPasta($raizSite . '/uploads') + tamanhoPasta($raizSite . '/src/uploads');
    $tamanhoBackups = tamanhoPasta($pastaBackups);
    $CRON_EXTRA += [
        'tamanho_banco'   => $tamanhoBanco,
        'tamanho_uploads' => $tamanhoUploads,
        'tamanho_backups' => $tamanhoBackups,
        'ultimo_backup'   => $ultimoBackup,
    ];
    cronLog('   📦 Banco ' . tamanhoLegivel($tamanhoBanco) . ' | uploads ' . tamanhoLegivel($tamanhoUploads) . ' | backups ' . tamanhoLegivel($tamanhoBackups));

    $limiteDiscoMb = (int) obterConfig('limite_disco_mb', '0');
    if ($limiteDiscoMb > 0) {
        $usoSite = tamanhoPasta($raizSite) + tamanhoPasta(PASTA_PRIVADA) + $tamanhoBanco;
        $percentual = $usoSite / ($limiteDiscoMb * 1048576) * 100;
        $textoUso = 'O site usa ' . tamanhoLegivel($usoSite) . ' (' . round($percentual) . '% do limite de '
            . number_format($limiteDiscoMb, 0, ',', '.') . ' MB do plano).';
        cronLog(($percentual >= DISCO_ALERTA_PERCENTUAL ? '   ❌ ' : '   ✅ ') . $textoUso);
    }
} catch (Throwable $e) {
    cronLog('   ❌ ERRO (tamanhos): ' . $e->getMessage());
}

// =============================================
// E-MAIL DE ALERTA: problemas desta e das outras tarefas agendadas (no máximo um por noite)
// =============================================
$alertasEmail = $CRON_ALERTAS;
try {
    $stmt = $db->prepare("SELECT cron, data_hora, alertas FROM cron_execucoes
                          WHERE status <> 'ok' AND cron <> 'cron_limpar_banco' AND data_hora >= ? ORDER BY data_hora");
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $execucao) {
        foreach (explode("\n", (string) $execucao['alertas']) as $alerta) {
            $alertasEmail[] = "[{$execucao['cron']} em " . date('d/m H:i', strtotime($execucao['data_hora'])) . "] $alerta";
        }
    }
    $ultimas = $db->query("SELECT cron, MAX(data_hora) FROM cron_execucoes GROUP BY cron")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (CRONS_AGENDADOS as $cron) {
        if ($cron !== 'cron_limpar_banco' && (!isset($ultimas[$cron]) || strtotime($ultimas[$cron]) < time() - CRON_ATRASO_HORAS * 3600)) {
            $alertasEmail[] = "[{$cron}] não rodou nas últimas " . CRON_ATRASO_HORAS . ' h'
                . (isset($ultimas[$cron]) ? ' (última vez em ' . date('d/m H:i', strtotime($ultimas[$cron])) . ')' : ' (nunca registrou execução)');
        }
    }
} catch (Throwable $e) {
    $alertasEmail[] = 'Não foi possível ler o histórico das tarefas agendadas: ' . $e->getMessage();
}
// Pagamentos recusados e erros do site ao processar eventos do Stripe
try {
    $stmt = $db->prepare("SELECT status, COUNT(*) FROM pagamentos_stripe WHERE status IN ('falha', 'erro') AND data_hora >= ? GROUP BY status");
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($pagamentos['falha'])) {
        $alertasEmail[] = "[Stripe] {$pagamentos['falha']} cobrança(s) recusada(s) nas últimas 24 h (veja a seção Pagamentos).";
    }
    if (!empty($pagamentos['erro'])) {
        $alertasEmail[] = "[Stripe] {$pagamentos['erro']} evento(s) que o site não conseguiu processar nas últimas 24 h: confira se o plano do vendedor ficou certo (seção Pagamentos).";
    }
} catch (Throwable $e) {
    $alertasEmail[] = 'Não foi possível ler os eventos do Stripe (a migration 010 foi rodada?): ' . $e->getMessage();
}
if ($alertasEmail) {
    $CRON_EXTRA['email_enviado'] = enviarAlertaManutencao($alertasEmail, $emailAlerta);
    cronLog('----------------------------------------');
    cronLog(count($alertasEmail) . ' alerta(s) para o e-mail: ' . ($CRON_EXTRA['email_enviado'] ? 'enviado.' : 'NÃO enviado (confira o alerta_email e o SMTP).'));
}

cronLog('========================================');
cronLog("RESUMO: $totalGeral registro(s) removido(s) no total"
    . (isset($tamanhoBanco) ? ' | banco ' . tamanhoLegivel($tamanhoBanco) . ', uploads ' . tamanhoLegivel($tamanhoUploads) . ', backups ' . tamanhoLegivel($tamanhoBackups) : '')
    . ($ultimoBackup ? ' | último backup ' . date('d/m', strtotime($ultimoBackup)) : ' | sem backup'));
if ($tabelasComErro) {
    cronLog('FINALIZADO COM ERROS em: ' . implode(', ', $tabelasComErro));
    cronLog('========================================');
    exit(1);
}
cronLog('FINALIZADO COM SUCESSO');
cronLog('========================================');
exit(0);
