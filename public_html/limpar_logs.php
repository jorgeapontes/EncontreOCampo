#!/usr/bin/php
<?php
// limpar_logs.php - Script para limpeza automática de logs

// ============================================================
// SEGURANÇA: BLOQUEAR ACESSO VIA NAVEGADOR
// ============================================================
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    die('❌ Acesso negado. Este script só pode ser executado via linha de comando.');
}

// Configurações
$logDir = __DIR__ . '/logs/';
$diasParaManter = 30; // Manter logs dos últimos 30 dias
$tamanhoMaximoArquivo = 50 * 1024 * 1024; // 50MB por arquivo

echo "========================================\n";
echo "  LIMPEZA AUTOMÁTICA DE LOGS\n";
echo "  Data: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Verificar se o diretório existe
if (!is_dir($logDir)) {
    echo "❌ Diretório de logs não encontrado: " . $logDir . "\n";
    exit(1);
}

$totalArquivos = 0;
$totalDeletados = 0;
$totalEspacoLiberado = 0;

// Escanear arquivos de log
$arquivos = glob($logDir . '*.log');

echo "📁 Encontrados " . count($arquivos) . " arquivos de log\n\n";

foreach ($arquivos as $arquivo) {
    $totalArquivos++;
    $nomeArquivo = basename($arquivo);
    $tamanho = filesize($arquivo);
    $dataModificacao = filemtime($arquivo);
    $diasDesdeModificacao = (time() - $dataModificacao) / 86400;
    
    echo "📄 Analisando: " . $nomeArquivo . "\n";
    echo "   - Tamanho: " . round($tamanho / 1024 / 1024, 2) . " MB\n";
    echo "   - Última modificação: " . date('Y-m-d H:i:s', $dataModificacao) . "\n";
    echo "   - Dias desde modificação: " . round($diasDesdeModificacao, 1) . " dias\n";
    
    if ($diasDesdeModificacao > $diasParaManter) {
        echo "   ⚠️  Arquivo com mais de " . $diasParaManter . " dias - DELETANDO...\n";
        
        $espacoLiberado = filesize($arquivo);
        if (unlink($arquivo)) {
            $totalDeletados++;
            $totalEspacoLiberado += $espacoLiberado;
            echo "   ✅ Deletado com sucesso!\n";
        } else {
            echo "   ❌ Falha ao deletar arquivo!\n";
        }
    } elseif ($tamanho > $tamanhoMaximoArquivo) {
        echo "   ⚠️  Arquivo muito grande (" . round($tamanho / 1024 / 1024, 2) . " MB) - ROTACIONANDO...\n";
        
        $arquivoOld = $arquivo . '.old';
        if (rename($arquivo, $arquivoOld)) {
            echo "   ✅ Arquivo rotacionado para: " . basename($arquivoOld) . "\n";
            touch($arquivo);
        } else {
            echo "   ❌ Falha ao rotacionar arquivo!\n";
        }
    } else {
        echo "   ✅ Arquivo OK (mantido)\n";
    }
    
    echo "\n";
}

echo "========================================\n";
echo "  RESUMO DA LIMPEZA\n";
echo "========================================\n";
echo "📊 Total de arquivos analisados: " . $totalArquivos . "\n";
echo "🗑️  Arquivos deletados: " . $totalDeletados . "\n";
echo "💾 Espaço liberado: " . round($totalEspacoLiberado / 1024 / 1024, 2) . " MB\n";
echo "✅ Limpeza concluída em: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

// ============================================================
// LIMPEZA DOS DIRETÓRIOS TEMPORÁRIOS
// ============================================================
echo "\n🧹 Limpando diretórios temporários...\n";

$tmpDirs = [
    __DIR__ . '/tmp/rate_limit/',
    __DIR__ . '/tmp/upload_limit/',
    __DIR__ . '/tmp/email_rate_limit/'  // <-- NOVO DIRETÓRIO ADICIONADO!
];

$totalTmpDeletados = 0;
$diasParaManterTmp = 2; // Manter apenas arquivos dos últimos 2 dias

foreach ($tmpDirs as $tmpDir) {
    if (is_dir($tmpDir)) {
        $arquivosTmp = glob($tmpDir . '*.json');
        $qtdArquivos = count($arquivosTmp);
        echo "📁 " . basename($tmpDir) . ": " . $qtdArquivos . " arquivos\n";
        
        foreach ($arquivosTmp as $arquivoTmp) {
            $dataModificacao = filemtime($arquivoTmp);
            $diasDesdeModificacao = (time() - $dataModificacao) / 86400;
            
            if ($diasDesdeModificacao > $diasParaManterTmp) {
                if (unlink($arquivoTmp)) {
                    $totalTmpDeletados++;
                }
            }
        }
    } else {
        echo "⚠️  Diretório não encontrado: " . $tmpDir . "\n";
    }
}

echo "\n🗑️  Arquivos temporários deletados: " . $totalTmpDeletados . "\n";
echo "✅ Limpeza completa!\n";