<?php
// src/verificar_email.php

require_once 'conexao.php';

// Iniciar sessão de forma segura
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurar cabeçalhos para AJAX/JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Função para enviar resposta JSON de forma consistente
function sendJsonResponse($success, $message, $additionalData = []) {
    $response = array_merge([
        'success' => $success,
        'message' => $message
    ], $additionalData);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Validação básica
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendJsonResponse(false, 'Método não permitido');
}

// Obter dados do formulário
$dados = $_POST;

// Validar email
if (empty($dados['email'])) {
    sendJsonResponse(false, 'Email não fornecido');
}

$email = filter_var($dados['email'], FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(false, 'Email inválido');
}

// Conectar ao banco de dados
try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    sendJsonResponse(false, 'Erro de conexão com o banco de dados: ' . $e->getMessage());
}

// Verificar se email já existe
try {
    $sqlCheckEmail = "SELECT id, email, tipo, status FROM usuarios WHERE email = :email";
    $stmtCheckEmail = $conn->prepare($sqlCheckEmail);
    $stmtCheckEmail->bindParam(':email', $email);
    $stmtCheckEmail->execute();
    
    if ($stmtCheckEmail->rowCount() > 0) {
        $usuario = $stmtCheckEmail->fetch(PDO::FETCH_ASSOC);
        
        $mensagem = 'Este email já está cadastrado. ';
        
        // Informações adicionais sobre o status
        if ($usuario['status'] === 'pendente') {
            $mensagem .= 'A conta está aguardando aprovação.';
        } elseif ($usuario['status'] === 'ativo') {
            $mensagem .= 'A conta já está ativa.';
        } elseif ($usuario['status'] === 'inativo') {
            $mensagem .= 'A conta está inativa.';
        } elseif ($usuario['status'] === 'suspenso') {
            $mensagem .= 'A conta está suspensa.';
        }
        
        sendJsonResponse(false, $mensagem, [
            'email_existe' => true,
            'status_conta' => $usuario['status'],
            'tipo_conta' => $usuario['tipo']
        ]);
    } else {
        sendJsonResponse(true, 'Email disponível para cadastro', [
            'email_existe' => false
        ]);
    }
} catch (Exception $e) {
    sendJsonResponse(false, 'Erro ao verificar email: ' . $e->getMessage());
}