<?php
// cron_limpar_documentos_orfaos.php
// Apaga fotos de rosto/documento em uploads/documentos/ que não pertencem a
// nenhum usuário (tabela usuarios, colunas foto_*). Elas surgiam quando o
// cadastro falhava depois do primeiro upload: dado biométrico sem finalidade
// (LGPD art. 15 e 16). O cadastro agora apaga as fotos na hora quando falha;
// este cron limpa o que já existia e qualquer caso que escape.
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador.
//
// Cron (Hostinger) - 1x por dia, 04:45 UTC (01:45 de Brasília):
// 45 4 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/src/scripts/cron_limpar_documentos_orfaos.php

require_once __DIR__ . '/cron_utils.php';
cronIniciar('cron_documentos_orfaos');

// Só apaga arquivos com mais de X horas, para não pegar um cadastro que
// está sendo gravado neste exato momento.
const HORAS_MINIMAS = 24;

// --simular: só lista o que seria apagado, sem apagar nada
$simular = in_array('--simular', $argv ?? [], true);

cronLog('========================================');
cronLog('INICIANDO LIMPEZA DE DOCUMENTOS ÓRFÃOS' . ($simular ? ' (SIMULAÇÃO - nada será apagado)' : ''));
cronLog('========================================');

require_once __DIR__ . '/../conexao.php';

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados');
    }

    $pasta = realpath(__DIR__ . '/../../uploads/documentos');
    if (!$pasta) {
        cronLog('⚠️ Pasta uploads/documentos não existe. Nada a fazer.');
        exit(0);
    }

    // Arquivos referenciados por algum usuário (compara pelo nome do arquivo,
    // que é aleatório e único, independente do formato do caminho gravado)
    $referenciados = []; // nome do arquivo => "usuário ID (coluna)"
    $stmt = $db->query("SELECT id, foto_rosto, foto_documento_frente, foto_documento_verso FROM usuarios");
    while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {
        foreach (['foto_rosto', 'foto_documento_frente', 'foto_documento_verso'] as $coluna) {
            if (!empty($linha[$coluna])) {
                $referenciados[basename($linha[$coluna])] = "usuário ID {$linha['id']} ($coluna)";
            }
        }
    }
    cronLog('📎 ' . count($referenciados) . ' arquivo(s) referenciado(s) por usuários.');

    // Trava de segurança: nenhum arquivo referenciado indica problema na
    // consulta (banco errado, coluna renomeada...). Nesse caso não apaga nada.
    if (count($referenciados) === 0) {
        throw new Exception('Nenhum documento referenciado no banco. Abortando para não apagar a pasta inteira.');
    }

    $limiteIdade = time() - HORAS_MINIMAS * 3600;
    $total = 0;
    $apagados = 0;
    $recentes = 0;
    $falhas = 0;

    foreach (new DirectoryIterator($pasta) as $item) {
        if (!$item->isFile() || !preg_match('/\.(jpe?g|png|webp)$/i', $item->getFilename())) {
            continue;
        }
        $total++;
        if (isset($referenciados[$item->getFilename()])) {
            continue;
        }
        if ($item->getMTime() > $limiteIdade) {
            $recentes++;
            continue;
        }
        if ($simular) {
            $apagados++;
            cronLog('   🔎 Seria apagado: ' . $item->getFilename() . ' (de ' . date('d/m/Y', $item->getMTime()) . ')');
            continue;
        }
        if (@unlink($item->getPathname())) {
            $apagados++;
            cronLog('   🗑️ Apagado: ' . $item->getFilename() . ' (de ' . date('d/m/Y', $item->getMTime()) . ')');
        } else {
            $falhas++;
            cronLog('   ❌ Falha ao apagar: ' . $item->getFilename());
        }
    }

    // O contrário também importa: documento no banco sem arquivo no disco
    // (o admin não consegue analisar esse cadastro)
    $ausentes = 0;
    foreach ($referenciados as $nome => $dono) {
        if (!is_file($pasta . DIRECTORY_SEPARATOR . $nome)) {
            $ausentes++;
            cronLog("   ⚠️ Arquivo ausente no servidor: $nome — $dono");
        }
    }

    cronLog('========================================');
    cronLog("DOCUMENTOS AUSENTES: $ausentes");
    cronLog("RESUMO: $total arquivo(s) na pasta | $apagados órfão(s) " . ($simular ? "que seriam apagado(s)" : "apagado(s)") . " | $recentes órfão(s) com menos de " . HORAS_MINIMAS . "h mantido(s) | $falhas falha(s)");
    cronLog('FINALIZADO ' . ($falhas > 0 ? 'COM ERROS' : 'COM SUCESSO'));
    cronLog('========================================');

    exit($falhas > 0 ? 1 : 0);

} catch (Throwable $e) {
    cronLog('❌ ERRO CRÍTICO: ' . $e->getMessage());
    cronLog('FINALIZADO COM ERRO CRÍTICO');
    error_log('CRON DOCUMENTOS ÓRFÃOS - ERRO CRÍTICO: ' . $e->getMessage());
    exit(1);
}
