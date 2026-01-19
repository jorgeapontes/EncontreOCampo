<?php
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    // Coletar dados básicos
    $nome = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $subject = sanitizeInput($_POST['subject']);

    // Validar dados básicos
    if (empty($nome) || empty($email) || empty($subject)) {
        $_SESSION['erro'] = "Por favor, preencha todos os campos obrigatórios.";
        header("Location: index.php#contato");
        exit();
    }

    // Verificar se email já existe
    $query = "SELECT id FROM usuarios WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['erro'] = "Este email já está cadastrado em nosso sistema.";
        header("Location: index.php#contato");
        exit();
    }

    try {
        $db->beginTransaction();

        // Criar senha temporária
        $senha_temporaria = bin2hex(random_bytes(8));
        $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO usuarios (email, senha, tipo, nome, status) 
                  VALUES (:email, :senha, :tipo, :nome, 'pendente')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':senha', $senha_hash);
        $stmt->bindParam(':tipo', $subject);
        $stmt->bindParam(':nome', $nome);
        $stmt->execute();
        
        $usuario_id = $db->lastInsertId();

        // Coletar dados específicos baseado no tipo
        $dados_especificos = [];

        if ($subject === 'comprador') {
            $dados_especificos = [
                'nome_comercial' => sanitizeInput($_POST['nomeComercialComprador'] ?? ''),
                'cpf_cnpj' => sanitizeInput($_POST['cpfCnpjComprador'] ?? ''),
                'cip' => sanitizeInput($_POST['cipComprador'] ?? ''),
                'cep' => sanitizeInput($_POST['cepComprador'] ?? ''),
                'rua' => sanitizeInput($_POST['ruaComprador'] ?? ''),
                'numero' => sanitizeInput($_POST['numeroComprador'] ?? ''),
                'complemento' => sanitizeInput($_POST['complementoComprador'] ?? ''),
                'estado' => sanitizeInput($_POST['estadoComprador'] ?? ''),
                'cidade' => sanitizeInput($_POST['cidadeComprador'] ?? ''),
                'telefone1' => sanitizeInput($_POST['telefone1Comprador'] ?? ''),
                'telefone2' => sanitizeInput($_POST['telefone2Comprador'] ?? ''),
                'plano' => sanitizeInput($_POST['planoComprador'] ?? '')
            ];
        } elseif ($subject === 'vendedor') {
            $dados_especificos = [
                'nome_comercial' => sanitizeInput($_POST['nomeComercialVendedor'] ?? ''),
                'cpf_cnpj' => sanitizeInput($_POST['cpfCnpjVendedor'] ?? ''),
                'cip' => sanitizeInput($_POST['cipVendedor'] ?? ''),
                'cep' => sanitizeInput($_POST['cepVendedor'] ?? ''),
                'rua' => sanitizeInput($_POST['ruaVendedor'] ?? ''),
                'numero' => sanitizeInput($_POST['numeroVendedor'] ?? ''),
                'complemento' => sanitizeInput($_POST['complementoVendedor'] ?? ''),
                'estado' => sanitizeInput($_POST['estadoVendedor'] ?? ''),
                'cidade' => sanitizeInput($_POST['cidadeVendedor'] ?? ''),
                'telefone1' => sanitizeInput($_POST['telefone1Vendedor'] ?? ''),
                'telefone2' => sanitizeInput($_POST['telefone2Vendedor'] ?? ''),
                'plano' => sanitizeInput($_POST['planoVendedor'] ?? '')
            ];
        } elseif ($subject === 'transportador') {
            $dados_especificos = [
                'telefone' => sanitizeInput($_POST['telefoneTransportador'] ?? ''),
                'antt' => sanitizeInput($_POST['ANTT'] ?? ''),
                'numero_antt' => sanitizeInput($_POST['numeroANTT'] ?? ''),
                'placa_veiculo' => sanitizeInput($_POST['placaVeiculo'] ?? ''),
                'modelo_veiculo' => sanitizeInput($_POST['modeloVeiculo'] ?? ''),
                'descricao_veiculo' => sanitizeInput($_POST['descricaoVeiculo'] ?? ''),
                'cep' => sanitizeInput($_POST['cepTransportador'] ?? ''),
                'rua' => sanitizeInput($_POST['ruaTransportador'] ?? ''),
                'numero' => sanitizeInput($_POST['numeroTransportador'] ?? ''),
                'complemento' => sanitizeInput($_POST['complementoTransportador'] ?? ''),
                'estado' => sanitizeInput($_POST['estadoTransportador'] ?? ''),
                'cidade' => sanitizeInput($_POST['cidadeTransportador'] ?? '')
            ];
        }

        // Salvar solicitação de cadastro
        $dados_json = json_encode($dados_especificos);
        
        $query = "INSERT INTO solicitacoes_cadastro (usuario_id, tipo_solicitacao, dados_json) 
                  VALUES (:usuario_id, :tipo, :dados_json)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':usuario_id', $usuario_id);
        $stmt->bindParam(':tipo', $subject);
        $stmt->bindParam(':dados_json', $dados_json);
        $stmt->execute();

        $db->commit();

        // Enviar email com senha temporária (implementar função de email)
        $_SESSION['sucesso'] = "Solicitação de cadastro enviada com sucesso! Aguarde a aprovação do administrador. Sua senha temporária é: " . $senha_temporaria;
        header("Location: index.php#contato");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['erro'] = "Erro ao processar cadastro: " . $e->getMessage();
        header("Location: index.php#contato");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>