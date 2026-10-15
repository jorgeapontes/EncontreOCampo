<?php
// cron_limpar_rejeitados.php
// Apaga fotos de rosto/documento e dados pessoais de cadastros REJEITADOS
// há mais de DIAS_RETENCAO_REJEITADOS dias (LGPD art. 15 e 16: terminada a
// finalidade, que era analisar o cadastro, os dados devem ser eliminados).
// O prazo dá tempo para a pessoa contestar a rejeição antes da exclusão.
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador.
//
// Cron (Hostinger) - 1x por dia, 04:30:
// 30 4 * * * /usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/src/scripts/cron_limpar_rejeitados.php

require_once __DIR__ . '/cron_utils.php';
cronIniciar('cron_rejeitados');

const DIAS_RETENCAO_REJEITADOS = 30;

cronLog('========================================');
cronLog('INICIANDO LIMPEZA DE CADASTROS REJEITADOS');
cronLog('========================================');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../functions/conta_functions.php';

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados');
    }

    // data_analise nula (rejeições antigas sem data) usa a data da solicitação
    $stmt = $db->prepare(
        "SELECT id, usuario_id
         FROM solicitacoes_cadastro
         WHERE status = 'rejeitado'
           AND COALESCE(data_analise, data_solicitacao) < (NOW() - INTERVAL " . DIAS_RETENCAO_REJEITADOS . " DAY)
           AND dados_json <> '{\"anonimizado\":true}'"
    );
    $stmt->execute();
    $rejeitados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($rejeitados);
    cronLog("📊 $total cadastro(s) rejeitado(s) há mais de " . DIAS_RETENCAO_REJEITADOS . ' dias.');

    $processados = 0;
    $arquivos = 0;
    $erros = 0;

    foreach ($rejeitados as $solicitacao) {
        try {
            $arquivos += limparCadastroRejeitado($db, $solicitacao);
            $processados++;
            cronLog("   ✅ Solicitação ID {$solicitacao['id']} limpa.");
        } catch (Throwable $e) {
            $erros++;
            cronLog("   ❌ Solicitação ID {$solicitacao['id']} — ERRO: " . $e->getMessage());
            error_log("CRON REJEITADOS: erro na solicitação {$solicitacao['id']}: " . $e->getMessage());
        }
    }

    cronLog('========================================');
    cronLog("RESUMO: $total encontrado(s) | $processados limpo(s) | $arquivos arquivo(s) apagado(s) | $erros erro(s)");
    cronLog('FINALIZADO ' . ($erros > 0 ? 'COM ERROS' : 'COM SUCESSO'));
    cronLog('========================================');

    exit($erros > 0 ? 1 : 0);

} catch (Throwable $e) {
    cronLog('❌ ERRO CRÍTICO: ' . $e->getMessage());
    cronLog('FINALIZADO COM ERRO CRÍTICO');
    error_log('CRON REJEITADOS - ERRO CRÍTICO: ' . $e->getMessage());
    exit(1);
}
