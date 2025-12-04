<?php
// src/comprador/processar_proposta.php - VERSÃO COM DEBUG

session_start();

// DEBUG: Verificar sessão no início
error_log("=== DEBUG processar_proposta.php ===");
error_log("Session ID: " . session_id());
error_log("Usuario tipo na SESSION: " . (isset($_SESSION['usuario_tipo']) ? $_SESSION['usuario_tipo'] : 'NÃO DEFINIDO'));
error_log("Usuario ID na SESSION: " . (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'NÃO DEFINIDO'));
error_log("POST data: " . print_r($_POST, true));

require_once __DIR__ . '/../conexao.php'; 

// Função para redirecionar com mensagem
function redirecionar($tipo, $mensagem, $anuncio_id = null) {
    error_log("Redirecionando: tipo=$tipo, mensagem=$mensagem, anuncio_id=$anuncio_id");
    
    if ($tipo === 'sucesso') {
        // VERIFICA SE A SESSÃO AINDA EXISTE ANTES DE REDIRECIONAR
        if (!isset($_SESSION['usuario_tipo'])) {
            error_log("AVISO: Sessão perdida ao redirecionar para sucesso!");
        }
        header("Location: minhas_propostas.php?sucesso=" . urlencode($mensagem));
    } else {
        $url = $anuncio_id ? "proposta_nova.php?anuncio_id={$anuncio_id}&erro=" . urlencode($mensagem) 
                           : "../anuncios.php?erro=" . urlencode($mensagem);
        header("Location: {$url}");
    }
    exit();
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    error_log("ERRO: Acesso negado! Sessão não tem tipo válido.");
    error_log("Sessão dump: " . print_r($_SESSION, true));
    
    // Em vez de redirecionar para login, vamos redirecionar de volta com erro
    $produto_id = isset($_POST['produto_id']) ? $_POST['produto_id'] : null;
    if ($produto_id) {
        header("Location: proposta_nova.php?anuncio_id={$produto_id}&erro=" . urlencode("Sessão expirada. Faça login novamente."));
    } else {
        header("Location: ../login.php?erro=" . urlencode("Sessão expirada. Faça login novamente."));
    }
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];

error_log("Acesso permitido para usuario_id: $usuario_id, tipo: $usuario_tipo");

// 2. VERIFICA SE É UMA NOVA PROPOSTA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obter dados do formulário
    $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $preco_proposto = filter_input(INPUT_POST, 'preco_proposto', FILTER_VALIDATE_FLOAT);
    $quantidade_proposta = filter_input(INPUT_POST, 'quantidade_proposta', FILTER_VALIDATE_INT);
    $condicoes_comprador = filter_input(INPUT_POST, 'condicoes');
    
    // Validações básicas
    if (!$produto_id || !$preco_proposto || !$quantidade_proposta || $preco_proposto <= 0 || $quantidade_proposta <= 0) {
        redirecionar('erro', "Dados inválidos. Verifique preço e quantidade.", $produto_id);
    }
    
    try {
        // Conexão com banco
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            redirecionar('erro', "Erro ao conectar com o banco de dados.", $produto_id);
        }
        
        // 3. BUSCAR OU CRIAR COMPRADOR_ID
        $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
        $stmt_comprador = $conn->prepare($sql_comprador);
        $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_comprador->execute();
        
        $comprador_id = null;
        
        if ($stmt_comprador->rowCount() > 0) {
            $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);
            $comprador_id = $comprador['id'];
            error_log("Comprador ID encontrado: $comprador_id");
        } else {
            error_log("Comprador não encontrado para usuario_id: $usuario_id, criando...");
            
            // Pega o nome do usuário
            $sql_usuario = "SELECT nome FROM usuarios WHERE id = :usuario_id";
            $stmt_usuario = $conn->prepare($sql_usuario);
            $stmt_usuario->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_usuario->execute();
            $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
            
            $nome_comercial = $usuario['nome'];
            
            // Insere um registro básico na tabela compradores
            $sql_criar_comprador = "INSERT INTO compradores 
                                    (usuario_id, tipo_pessoa, nome_comercial, cpf_cnpj, plano) 
                                    VALUES 
                                    (:usuario_id, 'cpf', :nome_comercial, '', 'free')";
            
            $stmt_criar = $conn->prepare($sql_criar_comprador);
            $stmt_criar->bindParam(':usuario_id', $usuario_id);
            $stmt_criar->bindParam(':nome_comercial', $nome_comercial);
            
            if ($stmt_criar->execute()) {
                $comprador_id = $conn->lastInsertId();
                error_log("Comprador criado com ID: $comprador_id");
            } else {
                error_log("Erro ao criar comprador");
                redirecionar('erro', "Erro ao criar perfil de comprador.", $produto_id);
            }
        }
        
        // 4. VERIFICAR PRODUTO
        $sql_produto = "SELECT id, estoque, vendedor_id FROM produtos WHERE id = :produto_id AND status = 'ativo'";
        $stmt_produto = $conn->prepare($sql_produto);
        $stmt_produto->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_produto->execute();
        $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);
        
        if (!$produto) {
            redirecionar('erro', "Produto não encontrado ou inativo.", $produto_id);
        }
        
        // Verificar estoque
        if ($produto['estoque'] < $quantidade_proposta) {
            redirecionar('erro', "Quantidade solicitada maior que estoque disponível.", $produto_id);
        }
        
        // 5. INSERIR PROPOSTA
        $sql_inserir_proposta = "INSERT INTO propostas_comprador 
                                (comprador_id, preco_proposto, quantidade_proposta, condicoes_compra, status) 
                                VALUES 
                                (:comprador_id, :preco_proposto, :quantidade_proposta, :condicoes_comprador, 'enviada')";
        
        $stmt_inserir = $conn->prepare($sql_inserir_proposta);
        $stmt_inserir->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
        $stmt_inserir->bindParam(':preco_proposto', $preco_proposto);
        $stmt_inserir->bindParam(':quantidade_proposta', $quantidade_proposta, PDO::PARAM_INT);
        
        if (empty($condicoes_comprador)) {
            $stmt_inserir->bindValue(':condicoes_comprador', null, PDO::PARAM_NULL);
        } else {
            $stmt_inserir->bindParam(':condicoes_comprador', $condicoes_comprador, PDO::PARAM_STR);
        }
        
        if ($stmt_inserir->execute()) {
            $proposta_comprador_id = $conn->lastInsertId();
            error_log("Proposta inserida com ID: $proposta_comprador_id");
        } else {
            error_log("Erro ao inserir proposta");
            redirecionar('erro', "Erro ao salvar proposta.", $produto_id);
        }
        
        // 6. CRIAR REGISTRO DE NEGOCIAÇÃO
        $sql_inserir_negociacao = "INSERT INTO propostas_negociacao 
                                  (produto_id, proposta_comprador_id, status) 
                                  VALUES 
                                  (:produto_id, :proposta_comprador_id, 'negociacao')";
        
        $stmt_negociacao = $conn->prepare($sql_inserir_negociacao);
        $stmt_negociacao->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
        
        if ($stmt_negociacao->execute()) {
            $negociacao_id = $conn->lastInsertId();
            error_log("Negociação criada com ID: $negociacao_id");
        } else {
            error_log("Erro ao criar negociação");
            redirecionar('erro', "Erro ao criar negociação.", $produto_id);
        }
        
        error_log("=== PROCESSAMENTO CONCLUÍDO COM SUCESSO ===");
        error_log("Redirecionando para minhas_propostas.php");
        
        // DEBUG: Verificar sessão antes do redirecionamento final
        error_log("Sessão antes do redirecionamento:");
        error_log("usuario_tipo: " . (isset($_SESSION['usuario_tipo']) ? $_SESSION['usuario_tipo'] : 'NÃO DEFINIDO'));
        error_log("usuario_id: " . (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'NÃO DEFINIDO'));
        
        // Redireciona com sucesso
        header("Location: minhas_propostas.php?sucesso=" . urlencode("Proposta enviada com sucesso! Aguarde a resposta do vendedor."));
        exit();
        
    } catch (PDOException $e) {
        error_log("Erro PDO: " . $e->getMessage());
        redirecionar('erro', "Erro: " . $e->getMessage(), $produto_id);
    }
    
} else {
    redirecionar('erro', "Método inválido.");
}
?>