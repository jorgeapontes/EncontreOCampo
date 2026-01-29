<?php
// src/chat/salvar_assinatura.php
session_start();
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit();
}

// Ler dados JSON
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados || !isset($dados['proposta_id']) || !isset($dados['assinatura_imagem'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$proposta_id = (int)$dados['proposta_id'];
$assinatura_imagem = $dados['assinatura_imagem'];

$database = new Database();
$conn = $database->getConnection();

try {
    $conn->beginTransaction();
    
    // 1. Verificar se a proposta existe e está no status 'assinando'
    $sql_proposta = "SELECT p.*, 
                    u_comp.nome as comprador_nome,
                    u_vend.nome as vendedor_nome
                    FROM propostas p
                    JOIN usuarios u_comp ON p.comprador_id = u_comp.id
                    JOIN usuarios u_vend ON p.vendedor_id = u_vend.id
                    WHERE p.ID = :proposta_id 
                    AND p.status = 'assinando' 
                    AND (p.comprador_id = :usuario_id OR p.vendedor_id = :usuario_id)";
    
    $stmt_proposta = $conn->prepare($sql_proposta);
    $stmt_proposta->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_proposta->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_proposta->execute();
    $proposta = $stmt_proposta->fetch(PDO::FETCH_ASSOC);
    
    if (!$proposta) {
        throw new Exception('Proposta não encontrada ou não está disponível para assinatura');
    }
    
    // 2. Verificar se o usuário já assinou
    $sql_verificar = "SELECT id FROM propostas_assinaturas 
                     WHERE proposta_id = :proposta_id AND usuario_id = :usuario_id";
    
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_verificar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_verificar->execute();
    
    if ($stmt_verificar->fetch()) {
        throw new Exception('Você já assinou esta proposta');
    }
    
    // 3. Criar hash da assinatura (SHA256 da imagem + timestamp + proposta_id + usuario_id)
    $assinatura_hash_data = $assinatura_imagem . time() . $proposta_id . $usuario_id;
    $assinatura_hash = hash('sha256', $assinatura_hash_data);
    
    // 4. Obter informações do cliente
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // 5. Salvar a assinatura no banco de dados
    $sql_inserir = "INSERT INTO propostas_assinaturas 
                   (proposta_id, usuario_id, assinatura_hash, assinatura_imagem, ip_address, user_agent) 
                   VALUES (:proposta_id, :usuario_id, :assinatura_hash, :assinatura_imagem, :ip_address, :user_agent)";
    
    $stmt_inserir = $conn->prepare($sql_inserir);
    $stmt_inserir->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_inserir->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_inserir->bindParam(':assinatura_hash', $assinatura_hash);
    $stmt_inserir->bindParam(':assinatura_imagem', $assinatura_imagem);
    $stmt_inserir->bindParam(':ip_address', $ip_address);
    $stmt_inserir->bindParam(':user_agent', $user_agent);
    
    if (!$stmt_inserir->execute()) {
        throw new Exception('Erro ao salvar assinatura no banco de dados');
    }
    
    // 6. Verificar se ambas as partes já assinaram
    $sql_contar_assinaturas = "SELECT COUNT(*) as total FROM propostas_assinaturas 
                              WHERE proposta_id = :proposta_id";
    
    $stmt_contar = $conn->prepare($sql_contar_assinaturas);
    $stmt_contar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
    $stmt_contar->execute();
    $resultado = $stmt_contar->fetch(PDO::FETCH_ASSOC);
    
    $ambas_assinadas = false;
    if ($resultado['total'] >= 2) {
        // Ambas as partes assinaram, atualizar status para 'aceita'
        $sql_atualizar_status = "UPDATE propostas SET 
                                status = 'aceita',
                                data_atualizacao = NOW()
                                WHERE ID = :proposta_id";
        
        $stmt_atualizar = $conn->prepare($sql_atualizar_status);
        $stmt_atualizar->bindParam(':proposta_id', $proposta_id, PDO::PARAM_INT);
        
        if (!$stmt_atualizar->execute()) {
            throw new Exception('Erro ao atualizar status da proposta');
        }
        
        // Atualizar estoque do produto
        $sql_estoque = "SELECT estoque FROM produtos WHERE id = :produto_id";
        $stmt_estoque = $conn->prepare($sql_estoque);
        $stmt_estoque->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
        $stmt_estoque->execute();
        $produto_info = $stmt_estoque->fetch(PDO::FETCH_ASSOC);
        
        if ($produto_info['estoque'] < $proposta['quantidade_proposta']) {
            throw new Exception('Estoque insuficiente para concluir a proposta');
        }
        
        $sql_update_estoque = "UPDATE produtos SET 
                              estoque = estoque - :quantidade
                              WHERE id = :produto_id";
        
        $stmt_update = $conn->prepare($sql_update_estoque);
        $stmt_update->bindParam(':quantidade', $proposta['quantidade_proposta'], PDO::PARAM_INT);
        $stmt_update->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
        $stmt_update->execute();
        
        $ambas_assinadas = true;
        
        // Enviar notificação para o outro usuário
        $outro_usuario_id = ($usuario_id == $proposta['comprador_id']) ? 
                           $proposta['vendedor_id'] : $proposta['comprador_id'];
        
        $sql_notificacao = "INSERT INTO notificacoes 
                          (usuario_id, mensagem, tipo, url) 
                          VALUES (:usuario_id, :mensagem, 'sucesso', :url)";
        
        $stmt_not = $conn->prepare($sql_notificacao);
        $mensagem = "Acordo assinado por todas as partes! A proposta para '{$proposta['nome']}' foi concluída.";
        $url = "../../src/chat/chat.php?produto_id=" . $proposta['produto_id'] . "&conversa_id=" . $_GET['conversa_id'] ?? '';
        
        $stmt_not->bindParam(':usuario_id', $outro_usuario_id, PDO::PARAM_INT);
        $stmt_not->bindParam(':mensagem', $mensagem);
        $stmt_not->bindParam(':url', $url);
        $stmt_not->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $ambas_assinadas ? 
                    'Assinatura registrada! Acordo concluído com sucesso.' : 
                    'Assinatura registrada com sucesso!',
        'ambas_assinadas' => $ambas_assinadas,
        'proposta_id' => $proposta_id
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>