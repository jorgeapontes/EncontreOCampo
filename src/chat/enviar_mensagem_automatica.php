<?php
// src/chat/enviar_mensagem_automatica.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

function enviarMensagemAutomatica($conversa_id, $remetente_id, $mensagem, $tipo = 'texto') {
    $database = new Database();
    $conn = $database->getConnection();
    
    try {
        // Inserir mensagem
        $sql = "INSERT INTO chat_mensagens (conversa_id, remetente_id, mensagem, tipo) 
                VALUES (:conversa_id, :remetente_id, :mensagem, :tipo)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt->bindParam(':remetente_id', $remetente_id, PDO::PARAM_INT);
        $stmt->bindParam(':mensagem', $mensagem);
        $stmt->bindParam(':tipo', $tipo);
        
        if ($stmt->execute()) {
            $mensagem_id = $conn->lastInsertId();
            
            // Atualizar conversa
            $sql_update = "UPDATE chat_conversas 
                          SET ultima_mensagem = :mensagem,
                              ultima_mensagem_data = NOW(),
                              comprador_lido = CASE WHEN comprador_id = :remetente_id THEN 1 ELSE 0 END,
                              vendedor_lido = CASE WHEN vendedor_id = :remetente_id THEN 1 ELSE 0 END
                          WHERE id = :conversa_id";
            
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bindParam(':mensagem', $mensagem);
            $stmt_update->bindParam(':remetente_id', $remetente_id, PDO::PARAM_INT);
            $stmt_update->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
            $stmt_update->execute();
            
            return ['success' => true, 'mensagem_id' => $mensagem_id];
        }
        
        return ['success' => false, 'error' => 'Erro ao inserir mensagem'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Função para obter conversa_id baseado em produto_id e usuários
function obterConversaId($produto_id, $comprador_id, $vendedor_id) {
    $database = new Database();
    $conn = $database->getConnection();
    
    $sql = "SELECT id FROM chat_conversas 
            WHERE produto_id = :produto_id 
            AND comprador_id = :comprador_id 
            AND vendedor_id = :vendedor_id
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
    $stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $resultado ? $resultado['id'] : null;
}

// Função principal para enviar notificação de ação
function enviarNotificacaoAcao($produto_id, $comprador_id, $vendedor_id, $acao, $detalhes = [], $remetente_id = null) {
    $conversa_id = obterConversaId($produto_id, $comprador_id, $vendedor_id);
    
    if (!$conversa_id) {
        return ['success' => false, 'error' => 'Conversa não encontrada'];
    }
    
    // Mapear ações para mensagens
    $mensagens_acoes = [
        'proposta_enviada' => [
            'titulo' => 'NOVA PROPOSTA DE COMPRA',
            'template' => "**NOVA PROPOSTA DE COMPRA**\n\n" .
                         "**Produto:** {produto_nome}\n" .
                         "**Quantidade:** {quantidade} {unidade_medida}\n" .
                         "**Valor unitário:** R$ {valor_unitario}\n" .
                         "**Forma de pagamento:** {forma_pagamento}\n" .
                         "**Opção de frete:** {opcao_frete}\n" .
                         "**Valor do frete:** R$ {valor_frete}\n" .
                         "**Valor total:** R$ {valor_total}"
        ],
        'proposta_editada' => [
            'titulo' => 'PROPOSTA ATUALIZADA',
            'template' => "**PROPOSTA ATUALIZADA**\n\n" .
                         "O comprador atualizou a proposta:\n\n" .
                         "**Quantidade:** {quantidade} {unidade_medida}\n" .
                         "**Valor unitário:** R$ {valor_unitario}\n" .
                         "**Valor total:** R$ {valor_total}"
        ],
        'proposta_aceita_assinatura' => [
            'titulo' => 'PROPOSTA ACEITA PARA ASSINATURA',
            'template' => "**PROPOSTA ACEITA PARA ASSINATURA DIGITAL**\n\n" .
                         "O vendedor aceitou a proposta!\n" .
                         "Agora ambas as partes precisam assinar digitalmente o acordo."
        ],
        'proposta_recusada' => [
            'titulo' => 'PROPOSTA RECUSADA',
            'template' => "**PROPOSTA RECUSADA**\n\n" .
                         "O vendedor recusou a proposta."
        ],
        'proposta_cancelada' => [
            'titulo' => 'PROPOSTA CANCELADA',
            'template' => "**PROPOSTA CANCELADA**\n\n" .
                         "O comprador cancelou a proposta."
        ],
        'assinatura_realizada' => [
            'titulo' => 'ASSINATURA REGISTRADA',
            'template' => "**ASSINATURA REGISTRADA**\n\n" .
                         "{usuario_nome} assinou digitalmente o acordo."
        ],
        'acordo_concluido' => [
            'titulo' => 'ACORDO CONCLUÍDO',
            'template' => "**✅ ACORDO CONCLUÍDO!**\n\n" .
                         "Ambas as partes assinaram o acordo digitalmente.\n" .
                         "A proposta foi oficialmente aceita e a compra está confirmada."
        ]
    ];
    
    if (!isset($mensagens_acoes[$acao])) {
        return ['success' => false, 'error' => 'Ação não reconhecida'];
    }
    
    $template = $mensagens_acoes[$acao]['template'];
    
    // Substituir placeholders
    foreach ($detalhes as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    
    // Se não especificado remetente, usar vendedor para notificações do sistema
    $remetente_final = $remetente_id ?: $vendedor_id;
    
    return enviarMensagemAutomatica($conversa_id, $remetente_final, $template);
}
?>