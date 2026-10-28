<?php
// cron_limpar_documentos_orfaos.php
// Apaga fotos de rosto/documento em uploads/documentos/ que não pertencem a
// nenhum usuário (tabela usuarios, colunas foto_*). Elas surgiam quando o
// cadastro falhava depois do primeiro upload: dado biométrico sem finalidade
// (LGPD art. 15 e 16). O cadastro agora apaga as fotos na hora quando falha;
// este cron limpa o que já existia e qualquer caso que escape.
// Desde 2026-10-28: os órfãos vão para PASTA_PRIVADA/quarentena_documentos/AAAA-MM-DD
// e só são apagados depois de 30 dias; e se aparecerem muitos de uma vez (trava de
// segurança) nada é movido até alguém conferir (--forcar libera).
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador.
//
// Cron (Hostinger) - 1x por dia, 04:45 UTC (01:45 de Brasília):
// 45 4 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/src/scripts/cron_limpar_documentos_orfaos.php

require_once __DIR__ . '/cron_utils.php';
cronIniciar('cron_documentos_orfaos');

// Só apaga arquivos com mais de X horas, para não pegar um cadastro que
// está sendo gravado neste exato momento.
const HORAS_MINIMAS = 24;
// Mais órfãos que isso de uma vez (5 arquivos e 30% da pasta) para tudo e pede revisão
const ORFAOS_MAXIMO_SEM_REVISAO = 5;
const ORFAOS_FRACAO_MAXIMA = 0.3;
// Órfãos ficam na pasta privada por esse tempo antes de serem apagados de vez
const QUARENTENA_DIAS = 30;

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

    // 1) Junta os órfãos antes de mexer em qualquer coisa
    $orfaos = []; // caminho => data
    foreach (new DirectoryIterator($pasta) as $item) {
        if (!$item->isFile() || !preg_match('/\.(jpe?g|png|webp)$/i', $item->getFilename())) {
            continue;
        }
        $total++;
        if (isset($referenciados[$item->getFilename()])) {
            continue;
        }
        $modificado = @filemtime($item->getPathname());
        if ($modificado === false) {
            $falhas++;
            cronLog("   ❌ Não foi possível ler a data de: {$item->getFilename()}");
            continue;
        }
        if ($modificado > $limiteIdade) {
            $recentes++;
            continue;
        }
        $orfaos[$item->getPathname()] = $modificado;
    }

    // 2) TRAVA DE SEGURANÇA: órfãos de verdade são poucos (cadastro que falhou no meio).
    //    Muitos de uma vez indicam problema no banco (registros apagados à mão,
    //    banco errado...). Nesse caso não mexe em nada e avisa para alguém olhar.
    if (count($orfaos) > max(ORFAOS_MAXIMO_SEM_REVISAO, (int) floor($total * ORFAOS_FRACAO_MAXIMA))) {
        cronLog('   ❌ TRAVA DE SEGURANÇA: ' . count($orfaos) . " de {$total} documentos parecem órfãos — acima do limite."
            . ' Nada foi movido. Confira se os cadastros desses arquivos foram apagados de propósito; para liberar, rode com --forcar.');
        foreach ($orfaos as $caminho => $modificado) {
            cronLog('      · ' . basename($caminho) . ' (de ' . date('d/m/Y', $modificado) . ')');
        }
        if (!in_array('--forcar', $argv ?? [], true)) {
            $orfaos = [];
            $falhas++;
        }
    }

    // 3) Quarentena: o órfão vai para a pasta privada e só é apagado de vez depois de
    //    QUARENTENA_DIAS dias (dá para desfazer um engano copiando-o de volta).
    $pastaQuarentena = PASTA_PRIVADA . '/quarentena_documentos/' . date('Y-m-d');
    foreach ($orfaos as $caminho => $modificado) {
        $nome = basename($caminho);
        $data = date('d/m/Y', $modificado);
        if ($simular) {
            $apagados++;
            cronLog("   🔎 Iria para a quarentena: $nome (de $data)");
            continue;
        }
        if (!is_dir($pastaQuarentena) && !@mkdir($pastaQuarentena, 0750, true)) {
            $falhas++;
            cronLog("   ❌ Não foi possível criar a pasta de quarentena: $pastaQuarentena");
            break;
        }
        if (@rename($caminho, $pastaQuarentena . '/' . $nome)) {
            $apagados++;
            cronLog("   📦 Para a quarentena: $nome (de $data)");
        } else {
            $falhas++;
            cronLog("   ❌ Falha ao mover: $nome");
        }
    }

    // 4) Esvazia as quarentenas antigas
    foreach (glob(PASTA_PRIVADA . '/quarentena_documentos/*', GLOB_ONLYDIR) ?: [] as $dia) {
        if (strtotime(basename($dia)) < time() - QUARENTENA_DIAS * 86400 && !$simular) {
            $arquivosDia = glob($dia . '/*') ?: [];
            array_map('unlink', $arquivosDia);
            @rmdir($dia);
            cronLog('   🗑️ Quarentena de ' . date('d/m/Y', strtotime(basename($dia))) . ' apagada de vez (' . count($arquivosDia) . ' arquivo(s)).');
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
    cronLog("RESUMO: $total arquivo(s) na pasta | $apagados órfão(s) " . ($simular ? "que iriam para a quarentena" : "na quarentena (" . QUARENTENA_DIAS . " dias)") . " | $recentes órfão(s) com menos de " . HORAS_MINIMAS . "h mantido(s) | $falhas falha(s)");
    cronLog('FINALIZADO ' . ($falhas > 0 ? 'COM ERROS' : 'COM SUCESSO'));
    cronLog('========================================');

    exit($falhas > 0 ? 1 : 0);

} catch (Throwable $e) {
    cronLog('❌ ERRO CRÍTICO: ' . $e->getMessage());
    cronLog('FINALIZADO COM ERRO CRÍTICO');
    error_log('CRON DOCUMENTOS ÓRFÃOS - ERRO CRÍTICO: ' . $e->getMessage());
    exit(1);
}
