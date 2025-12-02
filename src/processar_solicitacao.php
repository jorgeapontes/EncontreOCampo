<?php
// src/processar_solicitacao.php

require_once 'conexao.php';

session_start();
header('Content-Type: application/json');

function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se é uma sequência de números repetidos
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Cálculo para validar CPF
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

function validarCNPJ($cnpj) {
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Verifica se tem 14 dígitos
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se é uma sequência de números repetidos
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Cálculo para validar CNPJ
    $tamanho = strlen($cnpj) - 2;
    $numeros = substr($cnpj, 0, $tamanho);
    $digitos = substr($cnpj, $tamanho);
    $soma = 0;
    $pos = $tamanho - 7;
    
    for ($i = $tamanho; $i >= 1; $i--) {
        $soma += $numeros[$tamanho - $i] * $pos--;
        if ($pos < 2) {
            $pos = 9;
        }
    }
    
    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $digitos[0]) {
        return false;
    }
    
    $tamanho++;
    $numeros = substr($cnpj, 0, $tamanho);
    $soma = 0;
    $pos = $tamanho - 7;
    
    for ($i = $tamanho; $i >= 1; $i--) {
        $soma += $numeros[$tamanho - $i] * $pos--;
        if ($pos < 2) {
            $pos = 9;
        }
    }
    
    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $digitos[1]) {
        return false;
    }
    
    return true;
}

function validarCPFouCNPJ($documento, $tipo) {
    $documento = preg_replace('/[^0-9]/', '', $documento);
    
    if ($tipo === 'cpf') {
        return validarCPF($documento);
    } elseif ($tipo === 'cnpj') {
        return validarCNPJ($documento);
    }
    
    return false;
}

// Validação básica
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados do formulário
$dados = $_POST;

// Validações obrigatórias
$camposObrigatorios = ['name', 'email', 'senha', 'confirma_senha', 'subject'];
foreach ($camposObrigatorios as $campo) {
    if (empty($dados[$campo])) {
        echo json_encode(['success' => false, 'message' => "O campo '{$campo}' é obrigatório."]);
        exit;
    }
}

// Validar email
if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido.']);
    exit;
}

// Validar senha
if ($dados['senha'] !== $dados['confirma_senha']) {
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
    exit;
}

if (strlen($dados['senha']) < 8) {
    echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 8 caracteres.']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Verificar se email já existe
$sqlCheckEmail = "SELECT id FROM usuarios WHERE email = :email";
$stmtCheckEmail = $conn->prepare($sqlCheckEmail);
$stmtCheckEmail->bindParam(':email', $dados['email']);
$stmtCheckEmail->execute();

if ($stmtCheckEmail->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Este email já está cadastrado.']);
    exit;
}

// Preparar dados comuns
$nome = $dados['name'];
$email = $dados['email'];
$senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);
$tipoUsuario = $dados['subject']; // comprador, vendedor ou transportador

// Validações específicas por tipo
if ($tipoUsuario === 'comprador') {
    // Verificar tipo de pessoa
    if (empty($dados['tipo_pessoa_comprador'])) {
        echo json_encode(['success' => false, 'message' => 'Selecione o tipo de pessoa (CPF ou CNPJ).']);
        exit;
    }
    
    $tipoPessoa = $dados['tipo_pessoa_comprador'];
    $cpfCnpj = preg_replace('/[^0-9]/', '', $dados['cpfCnpjComprador']);
    
    // Validar CPF/CNPJ
    if (!validarCPFouCNPJ($cpfCnpj, $tipoPessoa)) {
        echo json_encode(['success' => false, 'message' => ($tipoPessoa === 'cpf' ? 'CPF' : 'CNPJ') . ' inválido.']);
        exit;
    }
    
    // Verificar se CPF/CNPJ já existe para comprador
    $sqlCheckDoc = "SELECT id FROM compradores WHERE cpf_cnpj = :cpf_cnpj";
    $stmtCheckDoc = $conn->prepare($sqlCheckDoc);
    $stmtCheckDoc->bindParam(':cpf_cnpj', $cpfCnpj);
    $stmtCheckDoc->execute();
    
    if ($stmtCheckDoc->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => ($tipoPessoa === 'cpf' ? 'CPF' : 'CNPJ') . ' já cadastrado.']);
        exit;
    }
    
    // Verificar nome comercial
    if (empty($dados['nomeComercialComprador'])) {
        echo json_encode(['success' => false, 'message' => 'Nome de exibição/empresa é obrigatório.']);
        exit;
    }
    
} elseif ($tipoUsuario === 'vendedor') {
    // Para vendedor, obrigatório CNPJ
    $cpfCnpj = preg_replace('/[^0-9]/', '', $dados['cpfCnpjVendedor']);
    
    // Validar CNPJ (14 dígitos)
    if (strlen($cpfCnpj) !== 14) {
        echo json_encode(['success' => false, 'message' => 'CNPJ deve ter 14 dígitos.']);
        exit;
    }
    
    if (!validarCNPJ($cpfCnpj)) {
        echo json_encode(['success' => false, 'message' => 'CNPJ inválido.']);
        exit;
    }
    
    // Verificar se CNPJ já existe para vendedor
    $sqlCheckDoc = "SELECT id FROM vendedores WHERE cpf_cnpj = :cpf_cnpj";
    $stmtCheckDoc = $conn->prepare($sqlCheckDoc);
    $stmtCheckDoc->bindParam(':cpf_cnpj', $cpfCnpj);
    $stmtCheckDoc->execute();
    
    if ($stmtCheckDoc->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'CNPJ já cadastrado.']);
        exit;
    }
    
    // Verificar nome comercial
    if (empty($dados['nomeComercialVendedor'])) {
        echo json_encode(['success' => false, 'message' => 'Nome comercial é obrigatório.']);
        exit;
    }
    
} elseif ($tipoUsuario === 'transportador') {
    // Para transportador, validar número ANTT
    if (empty($dados['numeroANTT'])) {
        echo json_encode(['success' => false, 'message' => 'Número ANTT é obrigatório.']);
        exit;
    }
    
    // Validar placa do veículo (formato brasileiro)
    if (empty($dados['placaVeiculo'])) {
        echo json_encode(['success' => false, 'message' => 'Placa do veículo é obrigatória.']);
        exit;
    }
    
    // Validar modelo do veículo
    if (empty($dados['modeloVeiculo'])) {
        echo json_encode(['success' => false, 'message' => 'Modelo do veículo é obrigatório.']);
        exit;
    }
}

// Iniciar transação
$conn->beginTransaction();

try {
    // 1. Inserir na tabela usuarios
    $sqlUsuario = "INSERT INTO usuarios (email, senha, tipo, nome, status) 
                   VALUES (:email, :senha, :tipo, :nome, 'pendente')";
    $stmtUsuario = $conn->prepare($sqlUsuario);
    $stmtUsuario->bindParam(':email', $email);
    $stmtUsuario->bindParam(':senha', $senhaHash);
    $stmtUsuario->bindParam(':tipo', $tipoUsuario);
    $stmtUsuario->bindParam(':nome', $nome);
    $stmtUsuario->execute();
    
    $usuarioId = $conn->lastInsertId();
    
    // 2. Inserir dados específicos conforme o tipo
    $dadosSolicitacao = $dados;
    unset($dadosSolicitacao['senha']);
    unset($dadosSolicitacao['confirma_senha']);
    $dadosSolicitacao['senha_hash'] = $senhaHash;
    
    if ($tipoUsuario === 'comprador') {
        // Inserir na tabela compradores
        $sqlComprador = "INSERT INTO compradores (usuario_id, tipo_pessoa, nome_comercial, cpf_cnpj, cip, cep, rua, numero, complemento, estado, cidade, telefone1, telefone2, plano) 
                         VALUES (:usuario_id, :tipo_pessoa, :nome_comercial, :cpf_cnpj, :cip, :cep, :rua, :numero, :complemento, :estado, :cidade, :telefone1, :telefone2, :plano)";
        $stmtComprador = $conn->prepare($sqlComprador);
        
        // Formatar CPF/CNPJ
        $cpfCnpjFormatado = $dados['cpfCnpjComprador'];
        $cpfCnpjNumerico = preg_replace('/[^0-9]/', '', $cpfCnpjFormatado);
        
        // Aplicar máscara baseada no tipo
        if ($dados['tipo_pessoa_comprador'] === 'cpf') {
            $cpfCnpjFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfCnpjNumerico);
        } else {
            $cpfCnpjFormatado = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cpfCnpjNumerico);
        }
        
        $plano = 'free'; // Sempre free inicialmente
        
        $stmtComprador->execute([
            ':usuario_id' => $usuarioId,
            ':tipo_pessoa' => $dados['tipo_pessoa_comprador'],
            ':nome_comercial' => $dados['nomeComercialComprador'],
            ':cpf_cnpj' => $cpfCnpjFormatado,
            ':cip' => $dados['cipComprador'] ?? null,
            ':cep' => $dados['cepComprador'] ?? null,
            ':rua' => $dados['ruaComprador'] ?? null,
            ':numero' => $dados['numeroComprador'] ?? null,
            ':complemento' => $dados['complementoComprador'] ?? null,
            ':estado' => $dados['estadoComprador'] ?? null,
            ':cidade' => $dados['cidadeComprador'] ?? null,
            ':telefone1' => $dados['telefone1Comprador'] ?? null,
            ':telefone2' => $dados['telefone2Comprador'] ?? null,
            ':plano' => $plano
        ]);
        
    } elseif ($tipoUsuario === 'vendedor') {
        // Inserir na tabela vendedores
        $sqlVendedor = "INSERT INTO vendedores (usuario_id, tipo_pessoa, nome_comercial, cpf_cnpj, cip, cep, rua, numero, complemento, estado, cidade, telefone1, telefone2, plano) 
                        VALUES (:usuario_id, :tipo_pessoa, :nome_comercial, :cpf_cnpj, :cip, :cep, :rua, :numero, :complemento, :estado, :cidade, :telefone1, :telefone2, :plano)";
        $stmtVendedor = $conn->prepare($sqlVendedor);
        
        // Formatar CNPJ
        $cpfCnpjNumerico = preg_replace('/[^0-9]/', '', $dados['cpfCnpjVendedor']);
        $cpfCnpjFormatado = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cpfCnpjNumerico);
        
        $plano = 'free'; // Sempre free inicialmente
        
        $stmtVendedor->execute([
            ':usuario_id' => $usuarioId,
            ':tipo_pessoa' => 'cnpj', // Sempre CNPJ para vendedor
            ':nome_comercial' => $dados['nomeComercialVendedor'],
            ':cpf_cnpj' => $cpfCnpjFormatado,
            ':cip' => $dados['cipVendedor'] ?? null,
            ':cep' => $dados['cepVendedor'] ?? null,
            ':rua' => $dados['ruaVendedor'] ?? null,
            ':numero' => $dados['numeroVendedor'] ?? null,
            ':complemento' => $dados['complementoVendedor'] ?? null,
            ':estado' => $dados['estadoVendedor'] ?? null,
            ':cidade' => $dados['cidadeVendedor'] ?? null,
            ':telefone1' => $dados['telefone1Vendedor'] ?? null,
            ':telefone2' => $dados['telefone2Vendedor'] ?? null,
            ':plano' => $plano
        ]);
        
    } elseif ($tipoUsuario === 'transportador') {
        // Inserir na tabela transportadores
        $sqlTransportador = "INSERT INTO transportadores (usuario_id, nome_comercial, telefone, numero_antt, placa_veiculo, modelo_veiculo, descricao_veiculo, estado, cidade, plano) 
                             VALUES (:usuario_id, :nome_comercial, :telefone, :numero_antt, :placa_veiculo, :modelo_veiculo, :descricao_veiculo, :estado, :cidade, :plano)";
        $stmtTransportador = $conn->prepare($sqlTransportador);
        
        $plano = 'free'; // Sempre free inicialmente
        
        $stmtTransportador->execute([
            ':usuario_id' => $usuarioId,
            ':nome_comercial' => $nome, // Usar nome normal como nome_comercial para transportador
            ':telefone' => $dados['telefoneTransportador'] ?? null,
            ':numero_antt' => $dados['numeroANTT'] ?? null,
            ':placa_veiculo' => $dados['placaVeiculo'] ?? null,
            ':modelo_veiculo' => $dados['modeloVeiculo'] ?? null,
            ':descricao_veiculo' => $dados['descricaoVeiculo'] ?? null,
            ':estado' => $dados['estadoTransportador'] ?? null,
            ':cidade' => $dados['cidadeTransportador'] ?? null,
            ':plano' => $plano
        ]);
    }
    
    // 3. Inserir na tabela solicitacoes_cadastro
    $sqlSolicitacao = "INSERT INTO solicitacoes_cadastro (usuario_id, nome, email, telefone, endereco, tipo_solicitacao, dados_json, status) 
                       VALUES (:usuario_id, :nome, :email, :telefone, :endereco, :tipo_solicitacao, :dados_json, 'pendente')";
    $stmtSolicitacao = $conn->prepare($sqlSolicitacao);
    
    // Preparar endereço e telefone
    $telefone = '';
    $endereco = '';
    
    if ($tipoUsuario === 'comprador') {
        $telefone = $dados['telefone1Comprador'] ?? '';
        $endereco = ($dados['ruaComprador'] ?? '') . ', ' . 
                   ($dados['numeroComprador'] ?? '') . ', ' . 
                   ($dados['cidadeComprador'] ?? '') . ', ' . 
                   ($dados['estadoComprador'] ?? '');
    } elseif ($tipoUsuario === 'vendedor') {
        $telefone = $dados['telefone1Vendedor'] ?? '';
        $endereco = ($dados['ruaVendedor'] ?? '') . ', ' . 
                   ($dados['numeroVendedor'] ?? '') . ', ' . 
                   ($dados['cidadeVendedor'] ?? '') . ', ' . 
                   ($dados['estadoVendedor'] ?? '');
    } elseif ($tipoUsuario === 'transportador') {
        $telefone = $dados['telefoneTransportador'] ?? '';
        $endereco = ($dados['cidadeTransportador'] ?? '') . ', ' . 
                   ($dados['estadoTransportador'] ?? '');
    }
    
    $stmtSolicitacao->execute([
        ':usuario_id' => $usuarioId,
        ':nome' => $nome,
        ':email' => $email,
        ':telefone' => $telefone,
        ':endereco' => $endereco,
        ':tipo_solicitacao' => $tipoUsuario,
        ':dados_json' => json_encode($dadosSolicitacao, JSON_UNESCAPED_UNICODE)
    ]);
    
    // 4. Criar notificação para admin
    $sqlNotificacao = "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) 
                       VALUES (1, :mensagem, 'info', 'src/admin/solicitacoes.php')";
    $stmtNotificacao = $conn->prepare($sqlNotificacao);
    
    $mensagemNotificacao = "Nova solicitação de cadastro de {$tipoUsuario}: {$nome}";
    $stmtNotificacao->bindParam(':mensagem', $mensagemNotificacao);
    $stmtNotificacao->execute();
    
    // Confirmar transação
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Solicitação de cadastro enviada com sucesso! Em breve você receberá um email com as instruções. Sua conta será ativada após aprovação do administrador.'
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    
    error_log("Erro ao processar solicitação: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar solicitação: ' . $e->getMessage()
    ]);
}