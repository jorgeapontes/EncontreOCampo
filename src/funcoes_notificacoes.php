<?php
// src/funcoes_notificacoes.php

function criarNotificacao($usuario_id, $mensagem, $tipo = 'info', $url = null) {
    require_once 'conexao.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    try {
        $sql = "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) 
                VALUES (:usuario_id, :mensagem, :tipo, :url)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':mensagem', $mensagem);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':url', $url);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erro ao criar notificação: " . $e->getMessage());
        return false;
    }
}

// Função para notificar vendedor sobre nova proposta
function notificarNovaProposta($vendedor_id, $produto_nome, $comprador_nome, $proposta_id) {
    $mensagem = "Nova proposta recebida para '{$produto_nome}' de {$comprador_nome}";
    $url = "src/vendedor/detalhes_proposta.php?id={$proposta_id}";
    return criarNotificacao($vendedor_id, $mensagem, 'info', $url);
}

// Função para notificar comprador sobre resposta da proposta
function notificarRespostaProposta($comprador_id, $produto_nome, $status, $proposta_id) {
    $status_text = $status == 'aceita' ? 'aceita' : ($status == 'recusada' ? 'recusada' : 'em negociação');
    $mensagem = "Sua proposta para '{$produto_nome}' foi {$status_text}";
    $url = "src/comprador/minhas_propostas.php";
    return criarNotificacao($comprador_id, $mensagem, $status == 'aceita' ? 'sucesso' : 'info', $url);
}

// Função para notificar sobre aprovação de cadastro
function notificarAprovacaoCadastro($usuario_id, $tipo_usuario) {
    $mensagem = "Seu cadastro como {$tipo_usuario} foi aprovado!";
    $url = "src/{$tipo_usuario}/dashboard.php";
    return criarNotificacao($usuario_id, $mensagem, 'sucesso', $url);
}
?>