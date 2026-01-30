<?php
// cron_verificar_vencimentos.php
set_time_limit(0); 

require_once __DIR__ . '/../conexao.php'; 

$database = new Database();
$db = $database->getConnection();

echo "Iniciando verificação de vencimentos: " . date('Y-m-d H:i:s') . "<br>";

// 1. Buscamos as assinaturas que venceram há mais de 2 dias e ainda estão 'active'
// Na sua tabela a coluna é 'vendedor_id' e 'status'
$sql = "SELECT vendedor_id 
        FROM vendedor_assinaturas 
        WHERE status = 'active' 
        AND data_vencimento < DATE_SUB(NOW(), INTERVAL 2 DAY)";

$stmt = $db->prepare($sql);
$stmt->execute();
$vencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($vencidos) === 0) {
    echo "Nenhuma assinatura vencida encontrada hoje.<br>";
    exit();
}

echo "Encontrados " . count($vencidos) . " registros vencidos. Processando...<br>";

foreach ($vencidos as $assinatura) {
    $vendedor_id = $assinatura['vendedor_id'];
    
    // Iniciar transação para garantir que ou atualiza tudo ou nada
    $db->beginTransaction();

    try {
        // 2. Atualiza o status na tabela de assinaturas para 'expired'
        $up1 = $db->prepare("UPDATE vendedor_assinaturas SET status = 'expired' WHERE vendedor_id = ? AND status = 'active'");
        $up1->execute([$vendedor_id]);

        // 3. Atualiza o status na tabela de vendedores para 'inativo'
        // (Isso corta o acesso dele às funcionalidades do site)
        $up2 = $db->prepare("UPDATE vendedores SET status_assinatura = 'inativo' WHERE id = ?");
        $up2->execute([$vendedor_id]);

        $db->commit();
        echo "- Vendedor ID $vendedor_id: Acesso bloqueado por falta de pagamento.<br>";
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "- Erro ao processar Vendedor ID $vendedor_id: " . $e->getMessage() . "<br>";
    }
}

echo "Processo finalizado.";