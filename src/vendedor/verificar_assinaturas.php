<?php
// src/vendedor/verificar_assinaturas.php

function verificarValidadeAssinaturas($db) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $hoje = date('Y-m-d H:i:s');
    $ID_PLANO_FREE = 1; // ID do seu plano gratuito no banco de dados

    try {
        // 1. Identificamos se existem vendedores que expiraram
        // Selecionamos primeiro para saber se vamos disparar o alerta
        $check = $db->prepare("SELECT id FROM vendedores WHERE status_assinatura = 'ativo' AND plano_id > :free_id AND data_vencimento_assinatura < :hoje");
        $check->execute([':free_id' => $ID_PLANO_FREE, ':hoje' => $hoje]);
        
        if ($check->rowCount() > 0) {
            // 2. Executamos a atualização para o Plano Free
            $sql = "UPDATE vendedores SET 
                    plano_id = :free_id, 
                    status_assinatura = 'ativo', 
                    data_vencimento_assinatura = NULL,
                    Data_inicio_assinatura = NULL,
                    Data_assinatura = NULL
                    WHERE status_assinatura = 'ativo' 
                    AND plano_id > :free_id
                    AND data_vencimento_assinatura IS NOT NULL 
                    AND data_vencimento_assinatura < :hoje";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':free_id' => $ID_PLANO_FREE,
                ':hoje'    => $hoje
            ]);

            // 3. Ativamos o alerta de sessão para o usuário ver no painel
            $_SESSION['aviso_expiracao'] = true;
            
            return $stmt->rowCount();
        }
        
        return 0;
    } catch (Exception $e) {
        error_log("Erro na verificação: " . $e->getMessage());
        return 0;
    }
}