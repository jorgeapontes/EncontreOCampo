#!/usr/bin/php
<?php
// limpar_logs.php - Limpeza automática de arquivos de log e temporários
// Cron (Hostinger) - 1x por dia, 00:00:
// 0 0 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/limpar_logs.php
//
// A saída só lista o que foi apagado/rotacionado/falhou e um resumo, para o
// e-mail do cron ficar curto. O mesmo conteúdo vai para
// src/scripts/logs/limpar_logs_AAAA-MM-DD.log.

require_once __DIR__ . '/src/scripts/cron_utils.php';
cronIniciar('limpar_logs');

// ============================================================
// CONFIGURAÇÕES
// ============================================================

// Todos os diretórios onde a aplicação grava arquivos de log.
$logDirs = [
    __DIR__ . '/logs/',               // security_*.log e errors_*.log (cadastro)
    __DIR__ . '/src/scripts/logs/',   // logs dos próprios crons
    __DIR__ . '/src/vendedor/logs/',  // stripe_*.log (webhook)
    __DIR__ . '/src/webhooks/',       // webhook_debug.log
];
$padroesLog = ['*.log', '*.log.old'];

// Retenção em dias por padrão de nome (o 1º que casar vale); o resto usa o padrão.
// security_*.log guarda IP + data/hora de cadastros: o Marco Civil da Internet
// (art. 15) exige no mínimo 6 meses para registros de acesso. Não reduzir abaixo de 183.
$retencaoPorPadrao = [
    'security_*' => 190,
];
$retencaoPadrao = 30;

$tamanhoMaximoArquivo = 50 * 1024 * 1024; // acima disso, rotaciona para .old

// Arquivos legados que devem ser removidos sempre que existirem.
// log_webhook.txt era uma cópia do stripe_*.log, sem limite de tamanho e
// acessível pela web (o .htaccess só bloqueia .log). Não é mais gravado.
$arquivosLegados = [
    __DIR__ . '/src/vendedor/log_webhook.txt',
];

// Arquivos de rate limit (JSON) que só precisam existir por pouco tempo.
$tmpDirs = [
    __DIR__ . '/tmp/rate_limit/',
    __DIR__ . '/tmp/upload_limit/',
    __DIR__ . '/tmp/email_rate_limit/',
];
$diasParaManterTmp = 2;

// ============================================================
// FUNÇÕES
// ============================================================
function retencaoDoArquivo($nomeArquivo, $retencaoPorPadrao, $retencaoPadrao) {
    foreach ($retencaoPorPadrao as $padrao => $dias) {
        if (fnmatch($padrao, $nomeArquivo)) {
            return $dias;
        }
    }
    return $retencaoPadrao;
}

function formatarMB($bytes) {
    return round($bytes / 1024 / 1024, 2) . ' MB';
}

// ============================================================
// INÍCIO
// ============================================================
cronLog('========================================');
cronLog('INICIANDO LIMPEZA DE LOGS');
cronLog('========================================');

$agora            = time();
$totalAnalisados  = 0;
$totalDeletados   = 0;
$totalRotacionados = 0;
$totalFalhas      = 0;
$espacoLiberado   = 0;

// ------------------------------------------------------------
// 1. ARQUIVOS DE LOG
// ------------------------------------------------------------
$arquivos = [];
foreach ($logDirs as $dir) {
    if (!is_dir($dir)) {
        continue; // diretório só é criado quando o primeiro log é gravado
    }
    foreach ($padroesLog as $padrao) {
        $arquivos = array_merge($arquivos, glob($dir . $padrao) ?: []);
    }
}
$arquivos = array_values(array_unique($arquivos));

foreach ($arquivos as $arquivo) {
    if (!is_file($arquivo)) {
        continue;
    }
    $totalAnalisados++;

    $nome     = basename($arquivo);
    $tamanho  = filesize($arquivo);
    $idadeDias = ($agora - filemtime($arquivo)) / 86400;
    $retencao = retencaoDoArquivo($nome, $retencaoPorPadrao, $retencaoPadrao);
    $relativo = substr($arquivo, strlen(__DIR__) + 1);

    if ($idadeDias > $retencao) {
        if (@unlink($arquivo)) {
            $totalDeletados++;
            $espacoLiberado += $tamanho;
            cronLog("🗑️  Apagado: $relativo (" . round($idadeDias) . " dias, limite $retencao)");
        } else {
            $totalFalhas++;
            cronLog("❌ Falha ao apagar: $relativo (verifique permissões)");
        }
    } elseif ($tamanho > $tamanhoMaximoArquivo) {
        $arquivoOld = $arquivo . '.old';
        if (is_file($arquivoOld)) {
            $espacoLiberado += filesize($arquivoOld);
            @unlink($arquivoOld);
        }
        if (@rename($arquivo, $arquivoOld)) {
            $totalRotacionados++;
            @touch($arquivo);
            @chmod($arquivo, 0644);
            cronLog("🔄 Rotacionado: $relativo (" . formatarMB($tamanho) . ')');
        } else {
            $totalFalhas++;
            cronLog("❌ Falha ao rotacionar: $relativo (verifique permissões)");
        }
    }
}

// ------------------------------------------------------------
// 2. ARQUIVOS LEGADOS
// ------------------------------------------------------------
foreach ($arquivosLegados as $arquivo) {
    if (!is_file($arquivo)) {
        continue;
    }
    $tamanho  = filesize($arquivo);
    $relativo = substr($arquivo, strlen(__DIR__) + 1);
    if (@unlink($arquivo)) {
        $totalDeletados++;
        $espacoLiberado += $tamanho;
        cronLog("🗑️  Apagado arquivo legado: $relativo (" . formatarMB($tamanho) . ')');
    } else {
        $totalFalhas++;
        cronLog("❌ Falha ao apagar arquivo legado: $relativo");
    }
}

// ------------------------------------------------------------
// 3. TEMPORÁRIOS DE RATE LIMIT
// ------------------------------------------------------------
$totalTmpDeletados = 0;
foreach ($tmpDirs as $tmpDir) {
    if (!is_dir($tmpDir)) {
        continue;
    }
    foreach (glob($tmpDir . '*.json') ?: [] as $arquivoTmp) {
        if (is_file($arquivoTmp)
            && ($agora - filemtime($arquivoTmp)) / 86400 > $diasParaManterTmp
            && @unlink($arquivoTmp)) {
            $totalTmpDeletados++;
        }
    }
}

// ------------------------------------------------------------
// RESUMO
// ------------------------------------------------------------
cronLog('========================================');
cronLog("RESUMO: $totalAnalisados log(s) analisado(s) | $totalDeletados apagado(s) | "
    . "$totalRotacionados rotacionado(s) | $totalTmpDeletados temporário(s) apagado(s) | "
    . formatarMB($espacoLiberado) . ' liberados');

if ($totalFalhas > 0) {
    cronLog("FINALIZADO COM $totalFalhas FALHA(S)");
    cronLog('========================================');
    exit(1);
}
cronLog('FINALIZADO COM SUCESSO');
cronLog('========================================');
exit(0);
