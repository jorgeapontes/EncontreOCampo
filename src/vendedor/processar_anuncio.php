<?php
// src/vendedor/processar_anuncio.php
require_once 'auth.php'; 

// Somente processa requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: anuncios.php");
    exit();
}

$anuncio_id = sanitizeInput($_POST['anuncio_id']);
$acao = sanitizeInput($_POST['acao']);
$vendedor_id_fk = $vendedor['id']; // ID do vendedor logado na tabela 'vendedores'

// 1. Validação básica
if (empty($anuncio_id) || empty($acao)) {
    $_SESSION['mensagem_anuncio_erro'] = "Dados inválidos para processar a ação.";
    header("Location: anuncios.php");
    exit();
}

// TRECHO A SER CONFIRMADO EM src/vendedor/anuncios.php

// ...
// LOGO ABAIXO da linha onde a mensagem de sucesso é exibida

// Mensagem de Erro (adicionar este bloco)
if (isset($_SESSION['mensagem_anuncio_erro'])): ?>
    <div class="alert error-alert" style="float: none; margin-bottom: 20px;"><?php echo $_SESSION['mensagem_anuncio_erro']; unset($_SESSION['mensagem_anuncio_erro']); ?></div>
<?php endif; 

// ...


try {
    $db->beginTransaction();
    $mensagem_sucesso = "";

    // 2. Garante que o anúncio pertence ao vendedor logado (Segurança!)
    $query_verifica = "SELECT nome FROM produtos WHERE id = :anuncio_id AND vendedor_id = :vendedor_id";
    $stmt_verifica = $db->prepare($query_verifica);
    $stmt_verifica->bindParam(':anuncio_id', $anuncio_id);
    $stmt_verifica->bindParam(':vendedor_id', $vendedor_id_fk);
    $stmt_verifica->execute();
    $anuncio_encontrado = $stmt_verifica->fetch(PDO::FETCH_ASSOC);

    if (!$anuncio_encontrado) {
        throw new Exception("Anúncio não encontrado ou você não tem permissão para esta ação.");
    }
    
    $nome_anuncio = $anuncio_encontrado['nome'];

    // 3. Execução da Ação
    switch ($acao) {
        case 'ativar':
            $query = "UPDATE produtos SET status = 'ativo', data_atualizacao = NOW() WHERE id = :anuncio_id AND vendedor_id = :vendedor_id";
            $mensagem_sucesso = "O anúncio '**{$nome_anuncio}**' foi ativado com sucesso!";
            break;
            
        case 'inativar':
            $query = "UPDATE produtos SET status = 'inativo', data_atualizacao = NOW() WHERE id = :anuncio_id AND vendedor_id = :vendedor_id";
            $mensagem_sucesso = "O anúncio '**{$nome_anuncio}**' foi inativado (pausado) com sucesso.";
            break;

        case 'deletar':
            // Excluir o registro permanentemente
            $query = "DELETE FROM produtos WHERE id = :anuncio_id AND vendedor_id = :vendedor_id";
            $mensagem_sucesso = "O anúncio '**{$nome_anuncio}**' foi DELETADO permanentemente.";
            break;

        default:
            throw new Exception("Ação inválida.");
    }

    // 4. Executa a query de UPDATE/DELETE
    $stmt = $db->prepare($query);
    $stmt->bindParam(':anuncio_id', $anuncio_id);
    $stmt->bindParam(':vendedor_id', $vendedor_id_fk);
    $stmt->execute();

    $db->commit();
    $_SESSION['mensagem_anuncio_sucesso'] = $mensagem_sucesso;

} catch (Exception $e) {
    // Em caso de erro, desfaz a transação
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['mensagem_anuncio_erro'] = $e->getMessage();

}

// 5. Redireciona de volta para a tela de anúncios
header("Location: anuncios.php");
exit();
?>