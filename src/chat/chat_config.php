<?php
// src/chat/chat_config.php
require_once __DIR__ . '/../conexao.php';

// Verificar se o usuário está logado
function verificarLoginChat() {
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Usuário não autenticado']);
        exit();
    }
}

// Função para criar ou obter conversa existente
function obterOuCriarConversa($conn, $produto_id, $comprador_id, $vendedor_id) {
    try {
        // Verificar se já existe uma conversa
        $sql = "SELECT id FROM chat_conversas 
                WHERE produto_id = :produto_id 
                AND comprador_id = :comprador_id 
                AND vendedor_id = :vendedor_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
        $stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $conversa = $stmt->fetch(PDO::FETCH_ASSOC);
            return $conversa['id'];
        } else {
            // Criar nova conversa
            $sql_insert = "INSERT INTO chat_conversas (produto_id, comprador_id, vendedor_id) 
                          VALUES (:produto_id, :comprador_id, :vendedor_id)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
            $stmt_insert->execute();
            
            return $conn->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log("Erro ao obter/criar conversa: " . $e->getMessage());
        return false;
    }
}

// Função para marcar mensagens como lidas
function marcarMensagensComoLidas($conn, $conversa_id, $usuario_id) {
    try {
        $sql = "UPDATE chat_mensagens 
                SET lida = 1 
                WHERE conversa_id = :conversa_id 
                AND remetente_id != :usuario_id 
                AND lida = 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao marcar mensagens como lidas: " . $e->getMessage());
        return false;
    }
}
?>