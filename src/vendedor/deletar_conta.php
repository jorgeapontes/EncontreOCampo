<?php
// src/vendedor/deletar_conta.php
require_once 'auth.php'; // Inclui a proteção de acesso e carrega $vendedor, $db, $usuario
require_once '../../config/StripeConfig.php'; // Inclui a configuração do Stripe

// Verificar se é vendedor
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=Acesso restrito");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: perfil.php?erro=Método inválido");
    exit();
}

$usuario_id = $usuario['id'];
$vendedor_id = $_POST['vendedor_id'] ?? null;

if (!$vendedor_id) {
    header("Location: perfil.php?erro=ID do vendedor não especificado");
    exit();
}

try {
    // Iniciar transação
    $db->beginTransaction();
    
    // 0. Cancelar assinatura no Stripe (antes de deletar)
    // Buscar dados da assinatura do Stripe
    $sqlGetStripeData = "SELECT stripe_subscription_id, stripe_customer_id FROM vendedores WHERE id = :vendedor_id";
    $stmtGetStripeData = $db->prepare($sqlGetStripeData);
    $stmtGetStripeData->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmtGetStripeData->execute();
    $stripeData = $stmtGetStripeData->fetch(PDO::FETCH_ASSOC);
    
    // Cancelar a assinatura no Stripe se existir
    if ($stripeData && !empty($stripeData['stripe_subscription_id'])) {
        try {
            \Config\StripeConfig::init();
            \Stripe\Subscription::retrieve($stripeData['stripe_subscription_id'])->cancel();
            error_log("Assinatura Stripe {$stripeData['stripe_subscription_id']} cancelada com sucesso para vendedor {$vendedor_id}");
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // A assinatura já pode estar cancelada
            error_log("Aviso ao cancelar assinatura Stripe: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Erro ao cancelar assinatura Stripe: " . $e->getMessage());
            // Continua mesmo se houver erro no Stripe
        }
    }
    
    // 1. Desativar usuário
    $sqlUpdateUser = "UPDATE usuarios SET status = 'inativo' WHERE id = :usuario_id";
    $stmtUpdateUser = $db->prepare($sqlUpdateUser);
    $stmtUpdateUser->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtUpdateUser->execute();
    
    // 2. Remover anúncios do vendedor
    // Primeiro, deletar as imagens dos produtos
    $sqlSelectProdutos = "SELECT id FROM produtos WHERE vendedor_id = :vendedor_id";
    $stmtSelectProdutos = $db->prepare($sqlSelectProdutos);
    $stmtSelectProdutos->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmtSelectProdutos->execute();
    $produtos_ids = $stmtSelectProdutos->fetchAll(PDO::FETCH_COLUMN);
    
    // Deletar imagens dos produtos
    if (!empty($produtos_ids)) {
        // Deletar da tabela produto_imagens
        $placeholders = implode(',', array_fill(0, count($produtos_ids), '?'));
        $sqlDeleteImagens = "DELETE FROM produto_imagens WHERE produto_id IN ($placeholders)";
        $stmtDeleteImagens = $db->prepare($sqlDeleteImagens);
        $stmtDeleteImagens->execute($produtos_ids);
        
        // Deletar os produtos
        $sqlDeleteProdutos = "DELETE FROM produtos WHERE vendedor_id = :vendedor_id";
        $stmtDeleteProdutos = $db->prepare($sqlDeleteProdutos);
        $stmtDeleteProdutos->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
        $stmtDeleteProdutos->execute();
    }
    
    // 3. Remover dados do vendedor
    $sqlDeleteVendedor = "DELETE FROM vendedores WHERE id = :vendedor_id";
    $stmtDeleteVendedor = $db->prepare($sqlDeleteVendedor);
    $stmtDeleteVendedor->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmtDeleteVendedor->execute();
    
    // 4. Remover dados pessoais relacionados
    // Notificações do usuário
    $sqlDeleteNotificacoes = "DELETE FROM notificacoes WHERE usuario_id = :usuario_id";
    $stmtDeleteNotificacoes = $db->prepare($sqlDeleteNotificacoes);
    $stmtDeleteNotificacoes->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtDeleteNotificacoes->execute();
    
    // Favoritos do usuário
    $sqlDeleteFavoritos = "DELETE FROM favoritos WHERE usuario_id = :usuario_id";
    $stmtDeleteFavoritos = $db->prepare($sqlDeleteFavoritos);
    $stmtDeleteFavoritos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmtDeleteFavoritos->execute();
    
    // Propostas do vendedor (mantém os dados de negociação, mas remove a referência pessoal)
    // Primeiro, encontrar as propostas de vendedor associadas
    $sqlSelectPropostasVendedor = "SELECT id FROM propostas_vendedor WHERE vendedor_id = :vendedor_id";
    $stmtSelectPropostasVendedor = $db->prepare($sqlSelectPropostasVendedor);
    $stmtSelectPropostasVendedor->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmtSelectPropostasVendedor->execute();
    $propostas_vendedor_ids = $stmtSelectPropostasVendedor->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($propostas_vendedor_ids)) {
        // Atualizar propostas_negociacao para remover referência às propostas do vendedor
        $placeholders = implode(',', array_fill(0, count($propostas_vendedor_ids), '?'));
        $sqlUpdateNegociacao = "UPDATE propostas_negociacao SET proposta_vendedor_id = NULL WHERE proposta_vendedor_id IN ($placeholders)";
        $stmtUpdateNegociacao = $db->prepare($sqlUpdateNegociacao);
        $stmtUpdateNegociacao->execute($propostas_vendedor_ids);
        
        // Deletar propostas do vendedor
        $sqlDeletePropostasVendedor = "DELETE FROM propostas_vendedor WHERE vendedor_id = :vendedor_id";
        $stmtDeletePropostasVendedor = $db->prepare($sqlDeletePropostasVendedor);
        $stmtDeletePropostasVendedor->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
        $stmtDeletePropostasVendedor->execute();
    }
    
    $db->commit();
    
    // Encerrar sessão
    session_destroy();
    
    // Redirecionar para home com mensagem
    header("Location: ../../index.php?sucesso=Conta de vendedor apagada com sucesso. Seus anúncios foram removidos, mas os chats permanecem disponíveis.");
    exit();
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erro ao apagar conta de vendedor: " . $e->getMessage());
    header("Location: perfil.php?erro=Erro ao apagar conta. Tente novamente.");
    exit();
}