<?php
// src/funcoes_notificacoes.php

// Importar função de envio de email
require_once __DIR__ . '/../includes/send_notification.php';

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

// Função para buscar email do usuário
function buscarEmailUsuario($usuario_id) {
    require_once 'conexao.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    try {
        $sql = "SELECT email, nome FROM usuarios WHERE id = :usuario_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar email do usuário: " . $e->getMessage());
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
function notificarRespostaProposta($comprador_usuario_id, $produto_nome, $status, $proposta_comprador_id) {
    // Mapear ações para textos amigáveis
    $status_text = match($status) {
        'aceitar' => 'aceita',
        'recusar' => 'recusada', 
        'contraproposta' => 'recebeu uma contraproposta',
        default => 'atualizada'
    };
    
    $mensagem = "Sua proposta para '{$produto_nome}' foi {$status_text}";
    $url = "src/comprador/minhas_propostas.php";
    
    // Definir tipo de notificação baseado na ação
    $tipo = match($status) {
        'aceitar' => 'sucesso',
        'recusar' => 'alerta',
        'contraproposta' => 'info',
        default => 'info'
    };
    
    // Buscar email do comprador para enviar notificação
    $usuario = buscarEmailUsuario($comprador_usuario_id);
    
    if ($usuario && $usuario['email']) {
        // Enviar email de notificação
        $assunto = "Proposta {$status_text} - {$produto_nome}";
        $conteudo = "Sua proposta para o produto '{$produto_nome}' foi {$status_text} pelo vendedor.";
        
        enviarEmailNotificacao(
            $usuario['email'], // Email correto do destinatário
            $usuario['nome'],
            $assunto,
            $conteudo
        );
    }
    
    return criarNotificacao($comprador_usuario_id, $mensagem, $tipo, $url);
}

// Função para notificar sobre aprovação de cadastro
function notificarAprovacaoCadastro($usuario_id, $tipo_usuario) {
    $mensagem = "Seu cadastro como {$tipo_usuario} foi aprovado!";
    $url = "src/{$tipo_usuario}/dashboard.php";
    
    // Buscar dados do usuário para enviar email
    $usuario = buscarEmailUsuario($usuario_id);
    
    if ($usuario && $usuario['email']) {
        // Enviar email de notificação
        $assunto = "Cadastro Aprovado - Encontre o Campo";
        $conteudo = "Seu cadastro como {$tipo_usuario} na plataforma Encontre o Campo foi aprovado. Você já pode acessar todas as funcionalidades!";
        
        enviarEmailNotificacao(
            $usuario['email'], // Email correto do destinatário
            $usuario['nome'],
            $assunto,
            $conteudo
        );
    }
    
    return criarNotificacao($usuario_id, $mensagem, 'sucesso', $url);
}

// Função para notificar vendedor sobre nova proposta
function notificarNovaPropostaComEmail($vendedor_id, $produto_nome, $comprador_nome, $proposta_id) {
    $mensagem = "Nova proposta recebida para '{$produto_nome}' de {$comprador_nome}";
    $url = "src/vendedor/detalhes_proposta.php?id={$proposta_id}";
    
    // Buscar dados do vendedor para enviar email
    $vendedor = buscarEmailUsuario($vendedor_id);
    
    if ($vendedor && $vendedor['email']) {
        // Enviar email de notificação
        $assunto = "Nova Proposta Recebida - {$produto_nome}";
        $conteudo = "Você recebeu uma nova proposta para o produto '{$produto_nome}' do comprador {$comprador_nome}. Acesse sua conta para visualizar e responder.";
        
        enviarEmailNotificacao(
            $vendedor['email'], // Email correto do destinatário
            $vendedor['nome'],
            $assunto,
            $conteudo
        );
    }
    
    return criarNotificacao($vendedor_id, $mensagem, 'info', $url);
}

// Função para notificar transportador sobre resposta de proposta de frete
function notificarRespostaPropostaFrete($transportador_usuario_id, $produto_nome, $status, $novo_valor = null) {
    $status_text = match($status) {
        'aceitar' => 'aceita',
        'recusar' => 'recusada', 
        'contraproposta' => 'recebeu uma contraproposta',
        default => 'atualizada'
    };
    
    $mensagem = "Sua proposta de frete para '{$produto_nome}' foi {$status_text}";
    $url = "src/transportador/entregas.php";
    
    // Buscar dados do transportador para enviar email
    $transportador = buscarEmailUsuario($transportador_usuario_id);
    
    if ($transportador && $transportador['email']) {
        // Enviar email de notificação
        $assunto = "Proposta de Frete {$status_text} - {$produto_nome}";
        
        $conteudo = match($status) {
            'aceitar' => "Sua proposta de frete para o produto '{$produto_nome}' foi aceita pelo comprador. Entre em contato com o vendedor para combinar a coleta!",
            'recusar' => "Sua proposta de frete para o produto '{$produto_nome}' foi recusada pelo comprador.",
            'contraproposta' => "Você recebeu uma contraproposta de R$ " . number_format($novo_valor, 2, ',', '.') . " para o frete do produto '{$produto_nome}'. Acesse a plataforma para responder.",
            default => "Sua proposta de frete para '{$produto_nome}' foi {$status_text}."
        };
        
        enviarEmailNotificacao(
            $transportador['email'], // Email correto do destinatário
            $transportador['nome'],
            $assunto,
            $conteudo
        );
    }
    
    return criarNotificacao($transportador_usuario_id, $mensagem, 'info', $url);
}
?>