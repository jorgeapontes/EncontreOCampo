<?php
// src/comprador/deletar_conta.php
session_start();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/send_notification.php';

// Verificar se é comprador
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=Acesso restrito");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: perfil.php?erro=Método inválido");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$comprador_id = $_POST['comprador_id'] ?? null;

if (!$comprador_id) {
    header("Location: perfil.php?erro=ID do comprador não especificado");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Buscar informações do comprador para notificação
    $sqlInfo = "SELECT u.nome, u.email FROM usuarios u 
                JOIN compradores c ON u.id = c.usuario_id 
                WHERE c.id = :comprador_id";
    $stmtInfo = $conn->prepare($sqlInfo);
    $stmtInfo->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmtInfo->execute();
    $compradorInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    // Iniciar transação
    $conn->beginTransaction();
    
    // 1. Desativar usuário (em vez de deletar completamente)
    $sqlUpdateUser = "UPDATE usuarios SET status = 'inativo' WHERE id = :usuario_id";
    $stmtUpdateUser = $conn->prepare($sqlUpdateUser);
    $stmtUpdateUser->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtUpdateUser->execute();
    
    // 2. Remover dados do comprador (exceto chats/mensagens)
    $sqlDeleteComprador = "DELETE FROM compradores WHERE id = :comprador_id";
    $stmtDeleteComprador = $conn->prepare($sqlDeleteComprador);
    $stmtDeleteComprador->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmtDeleteComprador->execute();
    
    // 3. Remover dados pessoais relacionados
    // Notificações do usuário
    $sqlDeleteNotificacoes = "DELETE FROM notificacoes WHERE usuario_id = :usuario_id";
    $stmtDeleteNotificacoes = $conn->prepare($sqlDeleteNotificacoes);
    $stmtDeleteNotificacoes->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtDeleteNotificacoes->execute();
    
    // Favoritos do usuário
    $sqlDeleteFavoritos = "DELETE FROM favoritos WHERE usuario_id = :usuario_id";
    $stmtDeleteFavoritos = $conn->prepare($sqlDeleteFavoritos);
    $stmtDeleteFavoritos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtDeleteFavoritos->execute();
    
    // Propostas do comprador (mantém os dados de negociação, mas remove a referência pessoal)
    // Aqui apenas removemos as propostas não finalizadas
    $sqlDeletePropostas = "DELETE FROM propostas_comprador WHERE comprador_id = :comprador_id AND status != 'finalizada'";
    $stmtDeletePropostas = $conn->prepare($sqlDeletePropostas);
    $stmtDeletePropostas->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmtDeletePropostas->execute();
    
    $conn->commit();
    
    // Enviar notificação por email para o comprador
    if ($compradorInfo && isset($compradorInfo['email'])) {
        enviarEmailNotificacao(
            $compradorInfo['email'],
            $compradorInfo['nome'],
            'Conta Desativada - Encontre o Campo',
            'Sua conta foi desativada com sucesso. Seus chats permanecem disponíveis para futuras consultas.'
        );
    }
    
    // Encerrar sessão
    session_destroy();
    
    // Redirecionar para home com mensagem
    header("Location: ../../index.php?sucesso=Conta apagada com sucesso. Seus chats permanecem disponíveis.");
    exit();
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Erro ao apagar conta: " . $e->getMessage());
    header("Location: perfil.php?erro=Erro ao apagar conta. Tente novamente.");
    exit();
}