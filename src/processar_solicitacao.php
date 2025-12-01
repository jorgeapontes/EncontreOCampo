<?php
// src/processar_solicitacao.php
require_once 'conexao.php';

header('Content-Type: application/json');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Coletar dados básicos
    $nome = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';
    $tipo_solicitacao = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    
    // Validar dados básicos
    if (empty($nome) || empty($email) || empty($senha) || empty($tipo_solicitacao)) {
        throw new Exception('Preencha todos os campos obrigatórios.');
    }
    
    if ($senha !== $confirma_senha) {
        throw new Exception('As senhas não coincidem.');
    }
    
    if (strlen($senha) < 8) {
        throw new Exception('A senha deve ter no mínimo 8 caracteres.');
    }
    
    // Verificar se email já existe
    $sql_check = "SELECT id FROM usuarios WHERE email = :email";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bindParam(':email', $email);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() > 0) {
        throw new Exception('Este email já está cadastrado no sistema.');
    }
    
    // Preparar dados para JSON baseado no tipo de solicitação
    $dados_json = [];
    
    // Dados comuns a todos os tipos
    $dados_json['nome'] = $nome;
    $dados_json['email'] = $email;
    $dados_json['tipo_solicitacao'] = $tipo_solicitacao;
    
    // Hash da senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    $dados_json['senha_hash'] = $senha_hash;
    
    // Coletar dados específicos baseado no tipo
    if ($tipo_solicitacao === 'comprador') {
        $dados_json['nomeComercialComprador'] = $_POST['nomeComercialComprador'] ?? '';
        $dados_json['cpfCnpjComprador'] = preg_replace('/[^0-9]/', '', $_POST['cpfCnpjComprador'] ?? '');
        $dados_json['cipComprador'] = $_POST['cipComprador'] ?? '';
        $dados_json['cepComprador'] = preg_replace('/[^0-9]/', '', $_POST['cepComprador'] ?? '');
        $dados_json['ruaComprador'] = $_POST['ruaComprador'] ?? '';
        $dados_json['numeroComprador'] = $_POST['numeroComprador'] ?? '';
        $dados_json['complementoComprador'] = $_POST['complementoComprador'] ?? '';
        $dados_json['estadoComprador'] = $_POST['estadoComprador'] ?? '';
        $dados_json['cidadeComprador'] = $_POST['cidadeComprador'] ?? '';
        $dados_json['telefone1Comprador'] = preg_replace('/[^0-9]/', '', $_POST['telefone1Comprador'] ?? '');
        $dados_json['telefone2Comprador'] = preg_replace('/[^0-9]/', '', $_POST['telefone2Comprador'] ?? '');
        $dados_json['planoComprador'] = $_POST['planoComprador'] ?? 'basico';
        
    } elseif ($tipo_solicitacao === 'vendedor') {
        $dados_json['nomeComercialVendedor'] = $_POST['nomeComercialVendedor'] ?? '';
        $dados_json['cpfCnpjVendedor'] = preg_replace('/[^0-9]/', '', $_POST['cpfCnpjVendedor'] ?? '');
        $dados_json['cipVendedor'] = $_POST['cipVendedor'] ?? '';
        $dados_json['cepVendedor'] = preg_replace('/[^0-9]/', '', $_POST['cepVendedor'] ?? '');
        $dados_json['ruaVendedor'] = $_POST['ruaVendedor'] ?? '';
        $dados_json['numeroVendedor'] = $_POST['numeroVendedor'] ?? '';
        $dados_json['complementoVendedor'] = $_POST['complementoVendedor'] ?? '';
        $dados_json['estadoVendedor'] = $_POST['estadoVendedor'] ?? '';
        $dados_json['cidadeVendedor'] = $_POST['cidadeVendedor'] ?? '';
        $dados_json['telefone1Vendedor'] = preg_replace('/[^0-9]/', '', $_POST['telefone1Vendedor'] ?? '');
        $dados_json['telefone2Vendedor'] = preg_replace('/[^0-9]/', '', $_POST['telefone2Vendedor'] ?? '');
        $dados_json['planoVendedor'] = $_POST['planoVendedor'] ?? 'basico';
        
    } elseif ($tipo_solicitacao === 'transportador') {
        $dados_json['telefoneTransportador'] = preg_replace('/[^0-9]/', '', $_POST['telefoneTransportador'] ?? '');
        $dados_json['ANTT'] = $_POST['ANTT'] ?? '';
        $dados_json['numeroANTT'] = $_POST['numeroANTT'] ?? '';
        $dados_json['placaVeiculo'] = $_POST['placaVeiculo'] ?? '';
        $dados_json['modeloVeiculo'] = $_POST['modeloVeiculo'] ?? '';
        $dados_json['descricaoVeiculo'] = $_POST['descricaoVeiculo'] ?? '';
        $dados_json['estadoTransportador'] = $_POST['estadoTransportador'] ?? '';
        $dados_json['cidadeTransportador'] = $_POST['cidadeTransportador'] ?? '';
    }
    
    // Inserir na tabela de solicitações de cadastro
    $sql = "INSERT INTO solicitacoes_cadastro 
            (nome, email, telefone, endereco, tipo_solicitacao, dados_json, status, data_solicitacao) 
            VALUES 
            (:nome, :email, :telefone, :endereco, :tipo_solicitacao, :dados_json, 'pendente', NOW())";
    
    $stmt = $conn->prepare($sql);
    
    // Preparar telefone e endereco para inserção
    $telefone = '';
    $endereco = '';
    
    if ($tipo_solicitacao === 'comprador') {
        $telefone = $dados_json['telefone1Comprador'] ?? '';
        $endereco = $dados_json['ruaComprador'] . ', ' . $dados_json['numeroComprador'] . ', ' . 
                    $dados_json['cidadeComprador'] . ', ' . $dados_json['estadoComprador'];
    } elseif ($tipo_solicitacao === 'vendedor') {
        $telefone = $dados_json['telefone1Vendedor'] ?? '';
        $endereco = $dados_json['ruaVendedor'] . ', ' . $dados_json['numeroVendedor'] . ', ' . 
                    $dados_json['cidadeVendedor'] . ', ' . $dados_json['estadoVendedor'];
    } elseif ($tipo_solicitacao === 'transportador') {
        $telefone = $dados_json['telefoneTransportador'] ?? '';
        $endereco = $dados_json['cidadeTransportador'] . ', ' . $dados_json['estadoTransportador'];
    }
    
    // Converter dados para JSON
    $dados_json_string = json_encode($dados_json, JSON_UNESCAPED_UNICODE);
    
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':endereco', $endereco);
    $stmt->bindParam(':tipo_solicitacao', $tipo_solicitacao);
    $stmt->bindParam(':dados_json', $dados_json_string);
    
    if ($stmt->execute()) {
        // Criar notificação para o admin
        $solicitacao_id = $conn->lastInsertId();
        $mensagem_notificacao = "Nova solicitação de cadastro de $tipo_solicitacao: $nome";
        
        $sql_notificacao = "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) 
                            VALUES (1, :mensagem, 'info', 'src/admin/solicitacoes.php')";
        $stmt_notificacao = $conn->prepare($sql_notificacao);
        $stmt_notificacao->bindParam(':mensagem', $mensagem_notificacao);
        $stmt_notificacao->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Solicitação enviada com sucesso! Aguarde a aprovação do administrador.',
            'solicitacao_id' => $solicitacao_id
        ]);
    } else {
        throw new Exception('Erro ao salvar solicitação no banco de dados.');
    }
    
} catch (Exception $e) {
    error_log('Erro processar_solicitacao: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log('Erro PDO processar_solicitacao: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}