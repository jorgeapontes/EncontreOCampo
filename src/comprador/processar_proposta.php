<?php
// src/comprador/processar_proposta.php - CORRIGIDO

session_start();
require_once __DIR__ . '/../conexao.php'; 
require_once 'funcoes_notificacoes.php';

$database = new Database();
$conn = $database->getConnection();

// Função para redirecionar com mensagem
function redirecionar($tipo, $mensagem, $anuncio_id = null) {
    if ($tipo === 'sucesso') {
        header("Location: minhas_propostas.php?sucesso=" . urlencode($mensagem));
    } else {
        // Em caso de erro, volta para a página do anúncio
        $url = $anuncio_id ? "proposta_nova.php?anuncio_id={$anuncio_id}&erro=" . urlencode($mensagem) 
                           : "../anuncios.php?erro=" . urlencode($mensagem);
        header("Location: {$url}");
    }
    exit();
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    redirecionar('erro', "Acesso negado. Faça login como Comprador.");
}

$usuario_id = $_SESSION['usuario_id'];

// 2. VERIFICA SE É UMA NOVA PROPOSTA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obter dados do formulário
    $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $preco_proposto = filter_input(INPUT_POST, 'preco_proposto', FILTER_VALIDATE_FLOAT);
    $quantidade_proposta = filter_input(INPUT_POST, 'quantidade_proposta', FILTER_VALIDATE_INT);
    $condicoes_comprador = filter_input(INPUT_POST, 'condicoes', FILTER_SANITIZE_STRING);
    
    // Validações básicas
    if (!$produto_id || !$preco_proposto || !$quantidade_proposta || $preco_proposto <= 0 || $quantidade_proposta <= 0) {
        redirecionar('erro', "Dados inválidos. Verifique preço e quantidade.", $produto_id);
    }
    
    // 3. BUSCAR ID DO COMPRADOR
    try {
        $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
        $stmt_comprador = $conn->prepare($sql_comprador);
        $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_comprador->execute();
        $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);
        
        if (!$comprador) {
            redirecionar('erro', "Perfil de comprador não encontrado.", $produto_id);
        }
        
        $comprador_id = $comprador['id'];
        
        // 4. VERIFICAR SE O PRODUTO EXISTE E ESTÁ ATIVO
        $sql_produto = "SELECT id, estoque FROM produtos WHERE id = :produto_id AND status = 'ativo'";
        $stmt_produto = $conn->prepare($sql_produto);
        $stmt_produto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_produto->execute();
        $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);
        
        if (!$produto) {
            redirecionar('erro', "Produto não encontrado ou inativo.", $produto_id);
        }
        
        // Verificar se há estoque suficiente
        if ($produto['estoque'] < $quantidade_proposta) {
            redirecionar('erro', "Quantidade solicitada maior que estoque disponível.", $produto_id);
        }
        
        // 5. INSERIR NOVA PROPOSTA
        $sql_inserir = "INSERT INTO propostas_negociacao 
                        (produto_id, comprador_id, preco_proposto, quantidade_proposta, condicoes_comprador, status) 
                        VALUES 
                        (:produto_id, :comprador_id, :preco_proposto, :quantidade_proposta, :condicoes_comprador, 'pendente')";
        
        $stmt_inserir = $conn->prepare($sql_inserir);
        $stmt_inserir->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_inserir->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
        $stmt_inserir->bindParam(':preco_proposto', $preco_proposto);
        $stmt_inserir->bindParam(':quantidade_proposta', $quantidade_proposta, PDO::PARAM_INT);
        
        // Tratar condicoes vazias
        if (empty($condicoes_comprador)) {
            $stmt_inserir->bindValue(':condicoes_comprador', null, PDO::PARAM_NULL);
        } else {
            $stmt_inserir->bindParam(':condicoes_comprador', $condicoes_comprador, PDO::PARAM_STR);
        }
        
        if ($stmt_inserir->execute()) {
            redirecionar('sucesso', "Proposta enviada com sucesso! Aguarde a resposta do vendedor.");
            // Notificar o vendedor
            $sql_vendedor = "SELECT v.usuario_id, p.nome as produto_nome 
                            FROM produtos p 
                            JOIN vendedores v ON p.vendedor_id = v.id 
                            WHERE p.id = :produto_id";
            $stmt_vendedor = $conn->prepare($sql_vendedor);
            $stmt_vendedor->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_vendedor->execute();
            $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

            if ($vendedor) {
                $comprador_nome = $_SESSION['usuario_nome'];
                notificarNovaProposta($vendedor['usuario_id'], $vendedor['produto_nome'], $comprador_nome, $proposta_id);
            }
        } else {
            redirecionar('erro', "Erro ao enviar proposta. Tente novamente.", $produto_id);
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao processar nova proposta: " . $e->getMessage());
        redirecionar('erro', "Erro interno do sistema. Tente novamente.", $produto_id);
    }
    
} else {
    // Se não for POST, redireciona
    redirecionar('erro', "Método inválido.");
}
?>