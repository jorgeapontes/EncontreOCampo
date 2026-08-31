#!/usr/bin/php
<?php
// limpar_logs.php - Script para limpeza automática de logs
// Executado via CRON JOB (Hostinger): 0 0 * * *

// ============================================================
// SEGURANÇA: BLOQUEAR ACESSO VIA NAVEGADOR
// ============================================================
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    die('❌ Acesso negado. Este script só pode ser executado via linha de comando.');
}

date_default_timezone_set('America/Sao_Paulo');

// ============================================================
// CONFIGURAÇÕES
// ============================================================

// Todos os diretórios onde a aplicação grava arquivos de log.
// __DIR__ = .../public_html
$logDirs = [
    __DIR__ . '/logs/',               // logs de segurança
    __DIR__ . '/src/scripts/logs/',   // cron de verificação de vencimentos
    __DIR__ . '/src/vendedor/logs/',  // logs do Stripe
    __DIR__ . '/src/webhooks/',       // webhook_debug.log
];

$diasParaManter        = 30;                 // Manter logs dos últimos 30 dias
$tamanhoMaximoArquivo  = 50 * 1024 * 1024;   // 50MB por arquivo (acima disso, rotaciona)
$padroesLog            = ['*.log', '*.log.old'];

echo "========================================\n";
echo "  LIMPEZA AUTOMÁTICA DE LOGS\n";
echo "  Data: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// ============================================================
// COLETA DE ARQUIVOS
// ============================================================
$arquivos = [];
foreach ($logDirs as $dir) {
    if (!is_dir($dir)) {
        echo "⚠️  Diretório de log não encontrado (ignorando): " . $dir . "\n";
        continue;
    }

    foreach ($padroesLog as $padrao) {
        $encontrados = glob($dir . $padrao);
        if ($encontrados) {
            $arquivos = array_merge($arquivos, $encontrados);
        }
    }
}

$arquivos = array_values(array_unique($arquivos));

echo "\n📁 Encontrados " . count($arquivos) . " arquivos de log em " . count($logDirs) . " diretórios\n\n";

$totalArquivos       = 0;
$totalDeletados      = 0;
$totalRotacionados   = 0;
$totalEspacoLiberado = 0;
$agora               = time();

foreach ($arquivos as $arquivo) {
    if (!is_file($arquivo)) {
        continue;
    }

    $totalArquivos++;
    $nomeArquivo          = basename($arquivo);
    $tamanho              = filesize($arquivo);
    $dataModificacao      = filemtime($arquivo);
    $diasDesdeModificacao = ($agora - $dataModificacao) / 86400;

    echo "📄 " . $nomeArquivo . "  (" . dirname($arquivo) . ")\n";
    echo "   - Tamanho: " . round($tamanho / 1024 / 1024, 2) . " MB\n";
    echo "   - Última modificação: " . date('Y-m-d H:i:s', $dataModificacao)
        . " (" . round($diasDesdeModificacao, 1) . " dias)\n";

    if ($diasDesdeModificacao > $diasParaManter) {
        echo "   ⚠️  Mais de " . $diasParaManter . " dias - DELETANDO...\n";

        if (@unlink($arquivo)) {
            $totalDeletados++;
            $totalEspacoLiberado += $tamanho;
            echo "   ✅ Deletado!\n";
        } else {
            echo "   ❌ Falha ao deletar (verifique permissões).\n";
        }
    } elseif ($tamanho > $tamanhoMaximoArquivo) {
        echo "   ⚠️  Arquivo grande demais - ROTACIONANDO...\n";

        $arquivoOld = $arquivo . '.old';
        // Se já existe um .old antigo, remove antes de sobrescrever
        if (is_file($arquivoOld)) {
            @unlink($arquivoOld);
        }

        if (@rename($arquivo, $arquivoOld)) {
            $totalRotacionados++;
            touch($arquivo);
            @chmod($arquivo, 0644);
            echo "   ✅ Rotacionado para: " . basename($arquivoOld) . "\n";
        } else {
            echo "   ❌ Falha ao rotacionar (verifique permissões).\n";
        }
    } else {
        echo "   ✅ OK (mantido)\n";
    }

    echo "\n";
}

echo "========================================\n";
echo "  RESUMO DA LIMPEZA DE LOGS\n";
echo "========================================\n";
echo "📊 Arquivos analisados:   " . $totalArquivos . "\n";
echo "🗑️  Arquivos deletados:    " . $totalDeletados . "\n";
echo "🔄 Arquivos rotacionados: " . $totalRotacionados . "\n";
echo "💾 Espaço liberado:       " . round($totalEspacoLiberado / 1024 / 1024, 2) . " MB\n";
echo "========================================\n";

// ============================================================
// LIMPEZA DOS DIRETÓRIOS TEMPORÁRIOS
// ============================================================
echo "\n🧹 Limpando diretórios temporários...\n";

$tmpDirs = [
    __DIR__ . '/tmp/rate_limit/',
    __DIR__ . '/tmp/upload_limit/',
    __DIR__ . '/tmp/email_rate_limit/',
];

$totalTmpDeletados  = 0;
$diasParaManterTmp  = 2; // Manter apenas arquivos dos últimos 2 dias

foreach ($tmpDirs as $tmpDir) {
    if (!is_dir($tmpDir)) {
        echo "⚠️  Diretório não encontrado: " . $tmpDir . "\n";
        continue;
    }

    $arquivosTmp = glob($tmpDir . '*.json');
    $qtd         = $arquivosTmp ? count($arquivosTmp) : 0;
    $removidosDir = 0;

    foreach ($arquivosTmp as $arquivoTmp) {
        if (!is_file($arquivoTmp)) {
            continue;
        }
        $diasDesdeModificacao = ($agora - filemtime($arquivoTmp)) / 86400;
        if ($diasDesdeModificacao > $diasParaManterTmp && @unlink($arquivoTmp)) {
            $totalTmpDeletados++;
            $removidosDir++;
        }
    }

    echo "📁 " . basename($tmpDir) . ": " . $qtd . " arquivos, " . $removidosDir . " removidos\n";
}

echo "\n🗑️  Arquivos temporários deletados: " . $totalTmpDeletados . "\n";
echo "✅ Limpeza completa em: " . date('Y-m-d H:i:s') . "\n";
