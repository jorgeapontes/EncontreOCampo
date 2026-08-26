<?php
// cron_verificar_vencimentos.php
// Script para verificar assinaturas vencidas há mais de 2 dias e desativar vendedores
// ATENÇÃO: Este script DEVE ser executado via CRON JOB, não pelo navegador

// Configurações
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
// LOG E DIRETÓRIO
// =============================================
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/cron_vencimentos_' . date('Y-m-d') . '.log';

/**
 * Função para registrar logs
 */
function logMessage($msg) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $full_msg = "[$timestamp] $msg\n";
    
    // Mostrar na tela (para saída do cron)
    echo $full_msg;
    
    // Salvar em arquivo de log
    file_put_contents($log_file, $full_msg, FILE_APPEND);
}

// =============================================
// INICIAR PROCESSO
// =============================================
logMessage("========================================");
logMessage("INICIANDO VERIFICAÇÃO DE VENCIMENTOS");
logMessage("========================================");

// Carregar conexão com banco de dados
require_once __DIR__ . '/../conexao.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Falha na conexão com o banco de dados");
    }
    
    logMessage("✅ Conexão com banco estabelecida com sucesso.");

    // =============================================
    // 1. BUSCAR VENDEDORES COM ASSINATURA VENCIDA HÁ MAIS DE 2 DIAS
    // =============================================
    // Busca vendedores com status 'ativo' ou 'atrasado' que já venceram há mais de 2 dias
    $sql = "SELECT 
                id,
                nome_comercial,
                usuario_id,
                plano_id,
                status_assinatura,
                data_vencimento_assinatura
            FROM vendedores 
            WHERE status_assinatura IN ('ativo', 'atrasado')
            AND data_vencimento_assinatura IS NOT NULL
            AND data_vencimento_assinatura < DATE_SUB(NOW(), INTERVAL 2 DAY)";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $vencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($vencidos);
    logMessage("📊 Encontrados $total vendedores com assinatura vencida há mais de 2 dias.");

    if ($total === 0) {
        logMessage("✅ Nenhuma assinatura vencida para processar.");
        logMessage("========================================");
        logMessage("FINALIZADO COM SUCESSO");
        logMessage("========================================");
        exit(0);
    }

    // =============================================
    // 2. PROCESSAR CADA VENDEDOR VENCIDO
    // =============================================
    $processados = 0;
    $erros = 0;
    $detalhes = [];

    foreach ($vencidos as $vendedor) {
        $vendedor_id = $vendedor['id'];
        $nome = $vendedor['nome_comercial'] ?? 'ID ' . $vendedor_id;
        $data_vencimento = $vendedor['data_vencimento_assinatura'];
        
        logMessage("🔄 Processando vendedor: $nome (ID: $vendedor_id) - Vencido em: $data_vencimento");

        try {
            // Iniciar transação
            $db->beginTransaction();

            // 2.1 Atualizar status da assinatura para 'expirado'
            $sql1 = "UPDATE vendedores 
                     SET status_assinatura = 'expirado' 
                     WHERE id = ? 
                     AND status_assinatura IN ('ativo', 'atrasado')";
            
            $stmt1 = $db->prepare($sql1);
            $stmt1->execute([$vendedor_id]);
            $linhas_afetadas = $stmt1->rowCount();

            if ($linhas_afetadas === 0) {
                // Se não afetou nenhuma linha, o status já mudou
                logMessage("   ⚠️ Nenhuma linha afetada (status já pode ter sido alterado)");
            }

            // 2.2 Opcional: Se o vendedor estava em um plano pago (plano_id > 1), 
            // podemos manter o plano_id mas marcar como expirado.
            // Se quiser voltar para o plano free (ID 1), descomente a linha abaixo:
            // $sql2 = "UPDATE vendedores SET plano_id = 1 WHERE id = ?";
            // $stmt2 = $db->prepare($sql2);
            // $stmt2->execute([$vendedor_id]);

            // Commit da transação
            $db->commit();
            
            $processados++;
            $detalhes[] = "✅ Vendedor $nome (ID: $vendedor_id) - Status alterado para 'expirado'";
            logMessage("   ✅ Processado com sucesso");

        } catch (Exception $e) {
            // Rollback em caso de erro
            $db->rollBack();
            $erros++;
            $detalhes[] = "❌ ERRO no vendedor $nome (ID: $vendedor_id): " . $e->getMessage();
            logMessage("   ❌ ERRO: " . $e->getMessage());
            
            // Registrar erro no log do servidor
            error_log("CRON VENCIMENTOS: Erro ao processar vendedor ID $vendedor_id: " . $e->getMessage());
        }
    }

    // =============================================
    // 3. RESUMO FINAL
    // =============================================
    logMessage("========================================");
    logMessage("📊 RESUMO FINAL");
    logMessage("========================================");
    logMessage("Total de vendedores vencidos: " . $total);
    logMessage("Processados com sucesso: " . $processados);
    logMessage("Erros: " . $erros);
    
    if (!empty($detalhes)) {
        logMessage("========================================");
        logMessage("📋 DETALHES:");
        foreach ($detalhes as $detalhe) {
            logMessage("  " . $detalhe);
        }
    }
    
    logMessage("========================================");
    logMessage("FINALIZADO " . ($erros > 0 ? "COM ERROS" : "COM SUCESSO"));
    logMessage("========================================");

    // Se houve erros, sair com código de erro
    if ($erros > 0) {
        exit(1);
    }
    
    exit(0);

} catch (Exception $e) {
    logMessage("❌ ERRO CRÍTICO: " . $e->getMessage());
    logMessage("❌ Arquivo: " . $e->getFile() . " - Linha: " . $e->getLine());
    logMessage("========================================");
    logMessage("FINALIZADO COM ERRO CRÍTICO");
    logMessage("========================================");
    
    error_log("CRON VENCIMENTOS - ERRO CRÍTICO: " . $e->getMessage());
    exit(1);
}