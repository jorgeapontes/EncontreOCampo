<?php
// src/processar_solicitacao.php
header('Content-Type: application/json');

// Incluir a conexão
require_once __DIR__ . '/conexao.php';

require_once 'funcoes_notificacoes.php';

// Funções de sanitização e validação (assumindo que estão definidas em outro lugar ou você usará filter_input)
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        return htmlspecialchars(stripslashes(trim($data)));
    }
}

// Iniciar conexão
$database = new Database();
$conn = $database->getConnection();

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falha grave na obtenção da conexão com o Banco de Dados.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// 2. Coleta e sanitiza os dados básicos
$nome = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$senha = $_POST['senha'] ?? ''; // Coleta a senha
$confirma_senha = $_POST['confirma_senha'] ?? ''; // Coleta a confirmação
$tipo_usuario = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS);
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);

// 3. Validação da Senha
if (empty($senha) || $senha !== $confirma_senha) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
    exit;
}
if (strlen($senha) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 8 caracteres.']);
    exit;
}

// 4. Criptografa a senha para salvar no JSON
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

// 5. Validação básica (campos obrigatórios)
if (empty($nome) || empty($email) || empty($tipo_usuario)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome, Email e Tipo de Usuário são obrigatórios.']);
    exit;
}

// 6. Processa e armazena todos os dados em um array para JSON
$dados_especificos = [];
$telefone_principal = '';
$endereco_principal = '';

foreach ($_POST as $key => $value) {
    // Ignora campos básicos e a senha (que será tratada separadamente)
    if ($key !== 'name' && $key !== 'email' && $key !== 'subject' && $key !== 'senha' && $key !== 'confirma_senha') {
        $dados_especificos[$key] = filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS);
    }
}

// Adiciona a SENHA CRIPTOGRAFADA ao JSON para ser usada na aprovação
$dados_especificos['senha_hash'] = $senha_hash; 

// 7. Tenta extrair o telefone/endereço mais relevante (código de extração existente)
if ($tipo_usuario === 'comprador') {
    $telefone_principal = $_POST['telefone1Comprador'] ?? '';
    $endereco_principal = implode(', ', array_filter([
        $_POST['ruaComprador'] ?? '', 
        $_POST['numeroComprador'] ?? '', 
        $_POST['complementoComprador'] ?? '', 
        $_POST['cidadeComprador'] ?? '', 
        $_POST['estadoComprador'] ?? ''
    ]));
} elseif ($tipo_usuario === 'vendedor') {
    $telefone_principal = $_POST['telefone1Vendedor'] ?? '';
     $endereco_principal = implode(', ', array_filter([
        $_POST['ruaVendedor'] ?? '', 
        $_POST['numeroVendedor'] ?? '', 
        $_POST['complementoVendedor'] ?? '', 
        $_POST['cidadeVendedor'] ?? '', 
        $_POST['estadoVendedor'] ?? ''
    ]));
} elseif ($tipo_usuario === 'transportador') {
    $telefone_principal = $_POST['telefoneTransportador'] ?? '';
    $endereco_principal = implode(', ', array_filter([$_POST['cidadeTransportador'] ?? '', $_POST['estadoTransportador'] ?? '']));
} elseif ($tipo_usuario === 'outro') {
    $dados_especificos = ['message' => $message];
}

$telefone_limpo = preg_replace('/\D/', '', $telefone_principal);
$dados_json = json_encode($dados_especificos);


// 8. Prepara a inserção no banco de dados
try {
    // Utilizando a query corrigida (tipo_solicitacao)
    $sql = "INSERT INTO solicitacoes_cadastro (nome, email, telefone, endereco, tipo_solicitacao, dados_json, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pendente')";
            
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$nome, $email, $telefone_limpo, $endereco_principal, $tipo_usuario, $dados_json])) {
        echo json_encode(['success' => true, 'message' => 'Solicitação de cadastro enviada com sucesso. Aguarde a aprovação.']);
        notificarAprovacaoCadastro($usuario_id, $tipo_solicitacao);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao registrar a solicitação no banco de dados.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor. Detalhes: ' . $e->getMessage()]);
}

if (isset($stmt)) $stmt->closeCursor(); 

?>