<?php
// src/processar_solicitacao.php

// Iniciar output buffering para capturar qualquer output antes do JSON
ob_start();

require_once 'conexao.php';

// Limpar qualquer output anterior
ob_end_clean();

// Configurar cabeçalhos para AJAX/JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configurar tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ============================================================
// 1. FUNÇÕES DE LOGGING DETALHADO
// ============================================================

function logSecurityEvent($eventType, $message, $data = []) {
    $logDir = __DIR__ . '/../logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . 'security_' . date('Y-m-d') . '.log';
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'event_type' => $eventType,
        'message' => $message,
        'data' => $data
    ];
    
    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

function logError($message, $data = []) {
    $logDir = __DIR__ . '/../logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . 'errors_' . date('Y-m-d') . '.log';
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'message' => $message,
        'data' => $data
    ];
    
    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// ============================================================
// 2. RATE LIMITING - Versão Melhorada com Arquivo
// ============================================================

function checkRateLimit($ip, $action = 'cadastro', $limit = 5, $timeWindow = 3600) {
    $rateLimitDir = __DIR__ . '/../tmp/rate_limit/';
    if (!is_dir($rateLimitDir)) {
        if (!mkdir($rateLimitDir, 0755, true)) {
            logError("Falha ao criar diretório de rate limit", ['dir' => $rateLimitDir]);
            return true; // Fallback: permitir se não conseguir criar
        }
    }
    
    $key = md5($action . '_' . $ip);
    $filePath = $rateLimitDir . $key . '.json';
    
    $now = time();
    $data = [];
    
    // Tentar ler o arquivo com lock
    if (file_exists($filePath)) {
        $fp = fopen($filePath, 'r+');
        if (flock($fp, LOCK_EX)) {
            $content = fread($fp, filesize($filePath));
            $data = json_decode($content, true) ?: [];
            fclose($fp);
        } else {
            fclose($fp);
            logError("Falha ao obter lock no rate limit", ['ip' => $ip]);
            return true; // Fallback: permitir se não conseguir ler
        }
        
        // Limpar tentativas antigas
        if (isset($data['attempts'])) {
            $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            });
        }
    }
    
    if (!isset($data['attempts'])) {
        $data['attempts'] = [];
    }
    
    // Verificar limite
    if (count($data['attempts']) >= $limit) {
        logSecurityEvent('rate_limit_exceeded', "IP {$ip} excedeu limite de {$limit} tentativas em {$timeWindow}s", [
            'attempts' => count($data['attempts']),
            'limit' => $limit
        ]);
        return false;
    }
    
    // Adicionar nova tentativa
    $data['attempts'][] = $now;
    
    // Salvar com lock
    $fp = fopen($filePath, 'w+');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($data));
        fclose($fp);
    } else {
        fclose($fp);
        logError("Falha ao salvar rate limit", ['ip' => $ip]);
    }
    
    return true;
}

// ============================================================
// 3. LIMITE DE UPLOADS POR USUÁRIO
// ============================================================

function checkUploadLimit($ip, $maxUploads = 10, $timeWindow = 86400) {
    $uploadLimitDir = __DIR__ . '/../tmp/upload_limit/';
    if (!is_dir($uploadLimitDir)) {
        if (!mkdir($uploadLimitDir, 0755, true)) {
            logError("Falha ao criar diretório de upload limit", ['dir' => $uploadLimitDir]);
            return true; // Fallback: permitir se não conseguir criar
        }
    }
    
    $key = md5('uploads_' . $ip);
    $filePath = $uploadLimitDir . $key . '.json';
    
    $now = time();
    $data = [];
    
    // Tentar ler o arquivo com lock
    if (file_exists($filePath)) {
        $fp = fopen($filePath, 'r+');
        if (flock($fp, LOCK_EX)) {
            $content = fread($fp, filesize($filePath));
            $data = json_decode($content, true) ?: [];
            fclose($fp);
        } else {
            fclose($fp);
            logError("Falha ao obter lock no upload limit", ['ip' => $ip]);
            return true; // Fallback: permitir se não conseguir ler
        }
        
        // Limpar tentativas antigas
        if (isset($data['uploads'])) {
            $data['uploads'] = array_filter($data['uploads'], function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            });
        }
    }
    
    if (!isset($data['uploads'])) {
        $data['uploads'] = [];
    }
    
    // Verificar limite
    if (count($data['uploads']) >= $maxUploads) {
        logSecurityEvent('upload_limit_exceeded', "IP {$ip} excedeu limite de {$maxUploads} uploads em {$timeWindow}s", [
            'uploads' => count($data['uploads']),
            'limit' => $maxUploads
        ]);
        return false;
    }
    
    return true;
}

function incrementUploadCount($ip) {
    $uploadLimitDir = __DIR__ . '/../tmp/upload_limit/';
    if (!is_dir($uploadLimitDir)) {
        return;
    }
    
    $key = md5('uploads_' . $ip);
    $filePath = $uploadLimitDir . $key . '.json';
    
    $now = time();
    $data = [];
    
    if (file_exists($filePath)) {
        $fp = fopen($filePath, 'r+');
        if (flock($fp, LOCK_EX)) {
            $content = fread($fp, filesize($filePath));
            $data = json_decode($content, true) ?: [];
            fclose($fp);
        } else {
            fclose($fp);
            return;
        }
    }
    
    if (!isset($data['uploads'])) {
        $data['uploads'] = [];
    }
    
    $data['uploads'][] = $now;
    
    $fp = fopen($filePath, 'w+');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($data));
        fclose($fp);
    } else {
        fclose($fp);
    }
}

// ============================================================
// 4. FUNÇÃO PARA SANITIZAR SAÍDA (XSS)
// ============================================================

function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// 5. HONEYPOT - Proteção contra bots
// ============================================================
if (!empty($_POST['honeypot'])) {
    logSecurityEvent('honeypot_triggered', 'Bot detectado', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    echo json_encode([
        'success' => true,
        'message' => 'Solicitação enviada com sucesso!'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 6. FUNÇÃO PARA ENVIAR RESPOSTA JSON
// ============================================================
function sendJsonResponse($success, $message, $additionalData = []) {
    // Sanitizar saída para prevenir XSS
    $message = sanitizeOutput($message);
    
    $response = array_merge([
        'success' => $success,
        'message' => $message
    ], $additionalData);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 7. FUNÇÕES DE VALIDAÇÃO DE DOCUMENTOS
// ============================================================

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
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

function validarCNPJNumerico($cnpj) {
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

function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/[^A-Za-z0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    if (ctype_digit($cnpj)) {
        return validarCNPJNumerico($cnpj);
    }
    
    if (!preg_match('/[A-Za-z]/', $cnpj) || !preg_match('/[0-9]/', $cnpj)) {
        return false;
    }
    
    if (preg_match('/(.)\1{13}/', $cnpj)) {
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

// ============================================================
// 8. FUNÇÃO DE UPLOAD COM VALIDAÇÃO COMPLETA
// ============================================================

function uploadFoto($file, $tipo_usuario, $tipo_foto) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $erro_code = $file['error'] ?? 'arquivo não encontrado';
        $mensagens_erro = [
            0 => 'Sem erro',
            1 => 'Arquivo excede upload_max_filesize',
            2 => 'Arquivo excede MAX_FILE_SIZE',
            3 => 'Arquivo foi parcialmente enviado',
            4 => 'Nenhum arquivo foi enviado',
            6 => 'Falta pasta temporária',
            7 => 'Erro ao escrever arquivo',
            8 => 'Extensão PHP bloqueou upload',
        ];
        $msg_erro = isset($mensagens_erro[$erro_code]) ? $mensagens_erro[$erro_code] : 'Erro desconhecido';
        logError("Erro no upload do arquivo {$tipo_foto}", ['error' => $msg_erro, 'code' => $erro_code]);
        throw new Exception("Erro ao fazer upload do arquivo {$tipo_foto}: {$msg_erro}");
    }
    
    // ==========================================================
    // VALIDAÇÃO REAL DA IMAGEM - getimagesize()
    // ==========================================================
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        logSecurityEvent('invalid_image_upload', 'Tentativa de upload de arquivo não-imagem', [
            'tipo_foto' => $tipo_foto,
            'filename' => $file['name'],
            'mime' => $file['type']
        ]);
        throw new Exception("Arquivo não é uma imagem válida para {$tipo_foto}");
    }
    
    // ==========================================================
    // VALIDAÇÃO DE DIMENSÕES MÁXIMAS
    // ==========================================================
    $maxWidth = 4096;
    $maxHeight = 4096;
    $minWidth = 100;
    $minHeight = 100;
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    if ($width > $maxWidth || $height > $maxHeight) {
        logSecurityEvent('image_size_exceeded', 'Tentativa de upload de imagem muito grande', [
            'width' => $width,
            'height' => $height,
            'max_width' => $maxWidth,
            'max_height' => $maxHeight
        ]);
        throw new Exception("Imagem muito grande. Dimensões máximas: {$maxWidth}x{$maxHeight} pixels.");
    }
    
    if ($width < $minWidth || $height < $minHeight) {
        throw new Exception("Imagem muito pequena. Dimensões mínimas: {$minWidth}x{$minHeight} pixels.");
    }
    
    $ratio = $width / $height;
    if ($ratio < 0.3 || $ratio > 3.0) {
        throw new Exception("Proporção da imagem inválida.");
    }
    
    // Verificar extensão real do arquivo
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (!in_array($extensao, $extensoesPermitidas)) {
        throw new Exception("Extensão de arquivo não permitida para {$tipo_foto}. Use JPG, PNG ou WebP.");
    }
    
    $mimePermitidos = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    ];
    
    $mimeTypeReal = $imageInfo['mime'];
    
    if ($mimePermitidos[$extensao] !== $mimeTypeReal) {
        logSecurityEvent('mime_mismatch', 'Tentativa de upload com MIME type incompatível', [
            'expected' => $mimePermitidos[$extensao],
            'actual' => $mimeTypeReal,
            'filename' => $file['name']
        ]);
        throw new Exception("Tipo de arquivo corrompido ou inválido para {$tipo_foto}");
    }
    
    // Validar tamanho (máximo 10MB)
    $tamanho_maximo = 10 * 1024 * 1024;
    if ($file['size'] > $tamanho_maximo) {
        throw new Exception("Arquivo {$tipo_foto} muito grande. Máximo: 10MB");
    }
    
    // Criar diretório se não existir
    $diretorio_base = __DIR__ . '/../uploads/documentos/';
    if (!is_dir($diretorio_base)) {
        if (!mkdir($diretorio_base, 0755, true)) {
            logError("Falha ao criar diretório: " . $diretorio_base);
            throw new Exception("Erro ao processar arquivo. Contate o suporte.");
        }
    }
    
    if (!is_writable($diretorio_base)) {
        logError("Diretório sem permissão de escrita: " . $diretorio_base);
        throw new Exception("Erro ao processar arquivo. Contate o suporte.");
    }
    
    // Nome seguro para o arquivo
    $nome_arquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
    $nome_arquivo = preg_replace('/[^a-zA-Z0-9_.-]/', '', $nome_arquivo);
    if (strpos($nome_arquivo, '..') !== false) {
        throw new Exception("Nome de arquivo inválido");
    }
    
    $caminho_arquivo = $diretorio_base . $nome_arquivo;
    
    if (!move_uploaded_file($file['tmp_name'], $caminho_arquivo)) {
        logError("Falha ao mover arquivo", [
            'source' => $file['tmp_name'],
            'dest' => $caminho_arquivo
        ]);
        throw new Exception("Erro ao salvar arquivo. Contate o suporte.");
    }
    
    // Log de sucesso do upload
    logSecurityEvent('upload_success', 'Upload de imagem realizado com sucesso', [
        'tipo_foto' => $tipo_foto,
        'nome_arquivo' => $nome_arquivo,
        'tamanho' => $file['size']
    ]);
    
    return 'uploads/documentos/' . $nome_arquivo;
}

// ============================================================
// 9. VALIDAÇÃO INICIAL
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logSecurityEvent('method_not_allowed', 'Tentativa de método não permitido', ['method' => $_SERVER['REQUEST_METHOD']]);
    sendJsonResponse(false, 'Método não permitido');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// Log de início de tentativa
logSecurityEvent('registration_attempt', 'Início de tentativa de cadastro', [
    'ip' => $ip,
    'user_agent' => $userAgent
]);

// Aplicar rate limiting
if (!checkRateLimit($ip)) {
    logSecurityEvent('rate_limit_blocked', 'Tentativa bloqueada por rate limit', ['ip' => $ip]);
    sendJsonResponse(false, 'Muitas tentativas de cadastro. Aguarde 1 hora e tente novamente.');
}

// Verificar limite de uploads
if (!checkUploadLimit($ip)) {
    logSecurityEvent('upload_limit_blocked', 'Tentativa bloqueada por limite de uploads', ['ip' => $ip]);
    sendJsonResponse(false, 'Limite de uploads excedido. Tente novamente mais tarde.');
}

$dados = $_POST;

// Sanitizar entrada (XSS Prevention)
$dadosSanitizados = [];
foreach ($dados as $key => $value) {
    if (is_string($value)) {
        $dadosSanitizados[$key] = trim(strip_tags($value));
    } else {
        $dadosSanitizados[$key] = $value;
    }
}
$dados = $dadosSanitizados;

// ============================================================
// 10. SANITIZAÇÃO E VALIDAÇÃO DOS DADOS
// ============================================================

$camposObrigatorios = ['name', 'email', 'senha', 'confirma_senha', 'subject'];
foreach ($camposObrigatorios as $campo) {
    if (empty($dados[$campo])) {
        logSecurityEvent('registration_failed', 'Campos obrigatórios faltando', ['campo' => $campo]);
        sendJsonResponse(false, "O campo '{$campo}' é obrigatório.");
    }
}

$dados['name'] = trim(strip_tags($dados['name']));
if (strlen($dados['name']) < 2 || strlen($dados['name']) > 100) {
    sendJsonResponse(false, 'Nome deve ter entre 2 e 100 caracteres.');
}
if (!preg_match('/^[a-zA-ZÀ-ÿ\s]+$/', $dados['name'])) {
    logSecurityEvent('registration_failed', 'Nome contém caracteres inválidos', ['nome' => $dados['name']]);
    sendJsonResponse(false, 'Nome contém caracteres inválidos.');
}
$nome = $dados['name'];

$email = filter_var(trim($dados['email']), FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logSecurityEvent('registration_failed', 'Email inválido', ['email' => $dados['email']]);
    sendJsonResponse(false, 'Email inválido.');
}

$domain = substr(strrchr($email, "@"), 1);
if (!checkdnsrr($domain, 'MX')) {
    logSecurityEvent('registration_failed', 'Domínio de email inválido', ['domain' => $domain]);
    sendJsonResponse(false, 'Domínio de email não existe ou não aceita emails.');
}

if ($dados['senha'] !== $dados['confirma_senha']) {
    logSecurityEvent('registration_failed', 'Senhas não coincidem', ['email' => $email]);
    sendJsonResponse(false, 'As senhas não coincidem.');
}

if (strlen($dados['senha']) < 8) {
    logSecurityEvent('registration_failed', 'Senha muito curta', ['email' => $email]);
    sendJsonResponse(false, 'A senha deve ter no mínimo 8 caracteres.');
}

$senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);

// ============================================================
// 11. VALIDAÇÃO DO ACEITE_TERMOS
// ============================================================
$aceite_termos_existe = isset($_POST['aceite_termos']);
$aceite_termos_valor = $aceite_termos_existe ? $_POST['aceite_termos'] : '';

$checkbox_marcado = false;

if ($aceite_termos_existe) {
    $checkbox_marcado = true;
}

if (!$checkbox_marcado && !empty($aceite_termos_valor)) {
    $checkbox_marcado = true;
}

if (!$checkbox_marcado && strtolower($aceite_termos_valor) === 'on') {
    $checkbox_marcado = true;
}

if (!$checkbox_marcado && $aceite_termos_valor === '1') {
    $checkbox_marcado = true;
}

if (!$checkbox_marcado) {
    logSecurityEvent('registration_failed', 'Termos não aceitos', ['email' => $email]);
    sendJsonResponse(false, 'Você precisa aceitar os termos e condições para criar a conta.');
}

$aceite_termos_db = $checkbox_marcado ? 1 : 0;

// Sanitizar campos de texto
$camposParaSanitizar = [
    'nomeComercialComprador', 'nomeComercialVendedor', 
    'ruaComprador', 'ruaVendedor', 'ruaTransportador',
    'cidadeComprador', 'cidadeVendedor', 'cidadeTransportador',
    'modeloVeiculo', 'descricaoVeiculo', 
    'complementoComprador', 'complementoVendedor', 'complementoTransportador',
    'cipComprador', 'cipVendedor'
];

foreach ($camposParaSanitizar as $campo) {
    if (isset($dados[$campo])) {
        $dados[$campo] = trim(strip_tags($dados[$campo]));
        if (strlen($dados[$campo]) > 255) {
            $dados[$campo] = substr($dados[$campo], 0, 255);
        }
    }
}

$camposNumericos = [
    'numeroComprador', 'numeroVendedor', 'numeroTransportador',
    'cepComprador', 'cepVendedor', 'cepTransportador',
    'telefone1Comprador', 'telefone2Comprador',
    'telefone1Vendedor', 'telefone2Vendedor',
    'telefoneTransportador', 'numeroANTT'
];

foreach ($camposNumericos as $campo) {
    if (isset($dados[$campo])) {
        $dados[$campo] = preg_replace('/[^0-9\-() ]/', '', $dados[$campo]);
    }
}

$tipoUsuario = $dados['subject'];

// ============================================================
// 12. CONEXÃO COM BANCO DE DADOS
// ============================================================

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    logError("Erro de conexão com BD", ['error' => $e->getMessage()]);
    sendJsonResponse(false, 'Erro interno no servidor. Tente novamente mais tarde.');
}

// ============================================================
// 13. VERIFICAÇÕES DE DUPLICIDADE
// ============================================================

try {
    $sqlCheckEmail = "SELECT id FROM usuarios WHERE email = :email";
    $stmtCheckEmail = $conn->prepare($sqlCheckEmail);
    $stmtCheckEmail->bindParam(':email', $email, PDO::PARAM_STR);
    $stmtCheckEmail->execute();
    
    if ($stmtCheckEmail->rowCount() > 0) {
        logSecurityEvent('registration_failed', 'Email já cadastrado', ['email' => $email]);
        sendJsonResponse(false, 'Este email já está cadastrado.');
    }
} catch (Exception $e) {
    logError("Erro ao verificar email", ['error' => $e->getMessage()]);
    sendJsonResponse(false, 'Erro ao processar solicitação. Contate o suporte.');
}

// ============================================================
// 14. VALIDAÇÕES ESPECÍFICAS POR TIPO DE USUÁRIO
// ============================================================

if ($tipoUsuario === 'comprador') {
    if (empty($dados['tipoPessoaComprador'])) {
        sendJsonResponse(false, 'Selecione o tipo de pessoa (CPF ou CNPJ).');
    }
    
    $tipoPessoa = $dados['tipoPessoaComprador'];
    $cpfCnpj = preg_replace('/[^0-9]/', '', $dados['cpfCnpjComprador']);
    
    if (!validarCPFouCNPJ($cpfCnpj, $tipoPessoa)) {
        logSecurityEvent('registration_failed', 'CPF/CNPJ inválido', ['tipo' => $tipoPessoa]);
        sendJsonResponse(false, ($tipoPessoa === 'cpf' ? 'CPF' : 'CNPJ') . ' inválido.');
    }
    
    try {
        $sqlCheckDoc = "SELECT id FROM compradores WHERE cpf_cnpj = :cpf_cnpj";
        $stmtCheckDoc = $conn->prepare($sqlCheckDoc);
        $stmtCheckDoc->bindParam(':cpf_cnpj', $cpfCnpj, PDO::PARAM_STR);
        $stmtCheckDoc->execute();
        
        if ($stmtCheckDoc->rowCount() > 0) {
            logSecurityEvent('registration_failed', 'CPF/CNPJ já cadastrado', ['tipo' => $tipoPessoa]);
            sendJsonResponse(false, ($tipoPessoa === 'cpf' ? 'CPF' : 'CNPJ') . ' já cadastrado.');
        }
    } catch (Exception $e) {
        logError("Erro ao verificar documento", ['error' => $e->getMessage()]);
        sendJsonResponse(false, 'Erro ao processar solicitação. Contate o suporte.');
    }
    
    if (empty($dados['nomeComercialComprador'])) {
        sendJsonResponse(false, 'Nome de exibição/empresa é obrigatório.');
    }
    
} elseif ($tipoUsuario === 'vendedor') {
    $cpfCnpj = preg_replace('/[^A-Za-z0-9]/', '', $dados['cpfCnpjVendedor']);
    
    if (strlen($cpfCnpj) !== 14) {
        sendJsonResponse(false, 'CNPJ deve ter 14 caracteres.');
    }
    
    if (!validarCNPJ($cpfCnpj)) {
        logSecurityEvent('registration_failed', 'CNPJ inválido', ['cnpj' => $cpfCnpj]);
        sendJsonResponse(false, 'CNPJ inválido.');
    }
    
    try {
        $sqlCheckDoc = "SELECT id FROM vendedores WHERE cpf_cnpj = :cpf_cnpj";
        $stmtCheckDoc = $conn->prepare($sqlCheckDoc);
        $stmtCheckDoc->bindParam(':cpf_cnpj', $cpfCnpj, PDO::PARAM_STR);
        $stmtCheckDoc->execute();
        
        if ($stmtCheckDoc->rowCount() > 0) {
            logSecurityEvent('registration_failed', 'CNPJ já cadastrado', ['cnpj' => $cpfCnpj]);
            sendJsonResponse(false, 'CNPJ já cadastrado.');
        }
    } catch (Exception $e) {
        logError("Erro ao verificar CNPJ", ['error' => $e->getMessage()]);
        sendJsonResponse(false, 'Erro ao processar solicitação. Contate o suporte.');
    }
    
    if (empty($dados['nomeComercialVendedor'])) {
        sendJsonResponse(false, 'Nome comercial é obrigatório.');
    }
    
} elseif ($tipoUsuario === 'transportador') {
    if (empty($dados['numeroANTT'])) {
        sendJsonResponse(false, 'Número ANTT é obrigatório.');
    }
    
    if (empty($dados['placaVeiculo'])) {
        sendJsonResponse(false, 'Placa do veículo é obrigatória.');
    }
    
    if (empty($dados['modeloVeiculo'])) {
        sendJsonResponse(false, 'Modelo do veículo é obrigatório.');
    }
}

// ============================================================
// 15. PROCESSAMENTO DE UPLOAD DAS FOTOS
// ============================================================

$fotos = [];
$uploadSuccess = false;

try {
    $campos_fotos = [
        'comprador' => [
            'fotoRostoComprador' => 'rosto',
            'fotoDocumentoFrenteComprador' => 'documento_frente',
            'fotoDocumentoVersoComprador' => 'documento_verso'
        ],
        'vendedor' => [
            'fotoRostoVendedor' => 'rosto',
            'fotoDocumentoFrenteVendedor' => 'documento_frente',
            'fotoDocumentoVersoVendedor' => 'documento_verso'
        ],
        'transportador' => [
            'fotoRostoTransportador' => 'rosto',
            'fotoDocumentoFrenteTransportador' => 'documento_frente',
            'fotoDocumentoVersoTransportador' => 'documento_verso'
        ]
    ];
    
    if (isset($campos_fotos[$tipoUsuario])) {
        foreach ($campos_fotos[$tipoUsuario] as $campo_form => $tipo_foto) {
            if (!isset($_FILES[$campo_form]) || $_FILES[$campo_form]['error'] === UPLOAD_ERR_NO_FILE) {
                sendJsonResponse(false, "Arquivo de {$tipo_foto} é obrigatório.");
            }
            
            if ($_FILES[$campo_form]['error'] !== UPLOAD_ERR_OK) {
                $erro = $_FILES[$campo_form]['error'];
                logError("Erro no upload do arquivo {$tipo_foto}", ['code' => $erro]);
                sendJsonResponse(false, "Erro ao enviar arquivo. Contate o suporte.");
            }
            
            $arquivo = $_FILES[$campo_form];
            try {
                $fotos[$tipo_foto] = uploadFoto($arquivo, $tipoUsuario, $tipo_foto);
                $uploadSuccess = true;
            } catch (Exception $e) {
                logError("Erro no upload: " . $e->getMessage());
                sendJsonResponse(false, $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    logError("Erro ao processar fotos: " . $e->getMessage());
    sendJsonResponse(false, 'Erro ao processar fotos. Contate o suporte.');
}

// ============================================================
// 16. INSERÇÃO NO BANCO DE DADOS (TRANSACTION)
// ============================================================

try {
    $conn->beginTransaction();
    
    // Verificação de colunas com prepared statement
    try {
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        $stmt = $conn->prepare($sql);
        $tableName = 'usuarios';
        $stmt->bindParam(1, $tableName, PDO::PARAM_STR);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        if (!in_array('foto_rosto', $columns)) {
            $conn->exec("ALTER TABLE usuarios ADD COLUMN foto_rosto varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do rosto do usuário'");
        }
        if (!in_array('foto_documento_frente', $columns)) {
            $conn->exec("ALTER TABLE usuarios ADD COLUMN foto_documento_frente varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do documento (frente)'");
        }
        if (!in_array('foto_documento_verso', $columns)) {
            $conn->exec("ALTER TABLE usuarios ADD COLUMN foto_documento_verso varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do documento (verso)'");
        }
    } catch (Exception $e) {
        logError("Erro ao verificar colunas: " . $e->getMessage());
    }
    
    $foto_rosto = $fotos['rosto'] ?? null;
    $foto_documento_frente = $fotos['documento_frente'] ?? null;
    $foto_documento_verso = $fotos['documento_verso'] ?? null;
    
    // 1. Inserir na tabela usuarios
    $sqlUsuario = "INSERT INTO usuarios (email, senha, tipo, nome, status, foto_rosto, foto_documento_frente, foto_documento_verso) 
                   VALUES (:email, :senha, :tipo, :nome, 'pendente', :foto_rosto, :foto_documento_frente, :foto_documento_verso)";
    $stmtUsuario = $conn->prepare($sqlUsuario);
    $stmtUsuario->bindParam(':email', $email, PDO::PARAM_STR);
    $stmtUsuario->bindParam(':senha', $senhaHash, PDO::PARAM_STR);
    $stmtUsuario->bindParam(':tipo', $tipoUsuario, PDO::PARAM_STR);
    $stmtUsuario->bindParam(':nome', $nome, PDO::PARAM_STR);
    $stmtUsuario->bindParam(':foto_rosto', $foto_rosto, PDO::PARAM_STR);
    $stmtUsuario->bindParam(':foto_documento_frente', $foto_documento_frente, PDO::PARAM_STR);
    $stmtUsuario->bindParam(':foto_documento_verso', $foto_documento_verso, PDO::PARAM_STR);
    $stmtUsuario->execute();
    
    $usuarioId = $conn->lastInsertId();
    
    // Incrementar contador de uploads
    if ($uploadSuccess) {
        incrementUploadCount($ip);
    }
    
    $dadosSolicitacao = $dados;
    unset($dadosSolicitacao['senha']);
    unset($dadosSolicitacao['confirma_senha']);
    $dadosSolicitacao['senha_hash'] = $senhaHash;
    
    if ($tipoUsuario === 'comprador') {
        $sqlComprador = "INSERT INTO compradores (usuario_id, tipo_pessoa, nome_comercial, cpf_cnpj, cip, cep, rua, numero, complemento, estado, cidade, telefone1, telefone2, plano) 
                         VALUES (:usuario_id, :tipo_pessoa, :nome_comercial, :cpf_cnpj, :cip, :cep, :rua, :numero, :complemento, :estado, :cidade, :telefone1, :telefone2, :plano)";
        $stmtComprador = $conn->prepare($sqlComprador);
        
        $cpfCnpjFormatado = $dados['cpfCnpjComprador'];
        $cpfCnpjNumerico = preg_replace('/[^0-9]/', '', $cpfCnpjFormatado);
        
        if ($dados['tipoPessoaComprador'] === 'cpf') {
            $cpfCnpjFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpfCnpjNumerico);
        } else {
            $cpfCnpjFormatado = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cpfCnpjNumerico);
        }
        
        $plano = 'free';
        
        $stmtComprador->execute([
            ':usuario_id' => $usuarioId,
            ':tipo_pessoa' => $dados['tipoPessoaComprador'],
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
        $sqlVendedor = "INSERT INTO vendedores (usuario_id, tipo_pessoa, nome_comercial, cpf_cnpj, cip, cep, rua, numero, complemento, estado, cidade, telefone1, telefone2, plano) 
                        VALUES (:usuario_id, :tipo_pessoa, :nome_comercial, :cpf_cnpj, :cip, :cep, :rua, :numero, :complemento, :estado, :cidade, :telefone1, :telefone2, :plano)";
        $stmtVendedor = $conn->prepare($sqlVendedor);
        
        $cpfCnpjNumerico = preg_replace('/[^A-Za-z0-9]/', '', $dados['cpfCnpjVendedor']);
        if (ctype_digit($cpfCnpjNumerico)) {
            $cpfCnpjFormatado = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cpfCnpjNumerico);
        } else {
            $cpfCnpjFormatado = $cpfCnpjNumerico;
        }
        
        $plano = 'free';
        
        $stmtVendedor->execute([
            ':usuario_id' => $usuarioId,
            ':tipo_pessoa' => 'cnpj',
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
        $sqlTransportador = "INSERT INTO transportadores (usuario_id, nome_comercial, telefone, numero_antt, placa_veiculo, modelo_veiculo, descricao_veiculo, cep, rua, numero, complemento, estado, cidade, plano) 
                             VALUES (:usuario_id, :nome_comercial, :telefone, :numero_antt, :placa_veiculo, :modelo_veiculo, :descricao_veiculo, :cep, :rua, :numero, :complemento, :estado, :cidade, :plano)";
        $stmtTransportador = $conn->prepare($sqlTransportador);
        
        $plano = 'free';
        
        $stmtTransportador->execute([
            ':usuario_id' => $usuarioId,
            ':nome_comercial' => $nome,
            ':telefone' => $dados['telefoneTransportador'] ?? null,
            ':numero_antt' => $dados['numeroANTT'] ?? null,
            ':placa_veiculo' => $dados['placaVeiculo'] ?? null,
            ':modelo_veiculo' => $dados['modeloVeiculo'] ?? null,
            ':descricao_veiculo' => $dados['descricaoVeiculo'] ?? null,
            ':cep' => $dados['cepTransportador'] ?? null,
            ':rua' => $dados['ruaTransportador'] ?? null,
            ':numero' => $dados['numeroTransportador'] ?? null,
            ':complemento' => $dados['complementoTransportador'] ?? null,
            ':estado' => $dados['estadoTransportador'] ?? null,
            ':cidade' => $dados['cidadeTransportador'] ?? null,
            ':plano' => $plano
        ]);
    }
    
    // 3. Inserir na tabela solicitacoes_cadastro
    $sqlSolicitacao = "INSERT INTO solicitacoes_cadastro (usuario_id, nome, email, telefone, endereco, tipo_solicitacao, dados_json, status, aceite_termos) 
                       VALUES (:usuario_id, :nome, :email, :telefone, :endereco, :tipo_solicitacao, :dados_json, 'pendente', :aceite_termos)";
    $stmtSolicitacao = $conn->prepare($sqlSolicitacao);
    
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
        $endereco = ($dados['ruaTransportador'] ?? '') . ', ' . 
                   ($dados['numeroTransportador'] ?? '') . ', ' . 
                   ($dados['cidadeTransportador'] ?? '') . ', ' . 
                   ($dados['estadoTransportador'] ?? '');
    }
    
    $stmtSolicitacao->execute([
        ':usuario_id' => $usuarioId,
        ':nome' => $nome,
        ':email' => $email,
        ':telefone' => $telefone,
        ':endereco' => $endereco,
        ':tipo_solicitacao' => $tipoUsuario,
        ':dados_json' => json_encode($dadosSolicitacao, JSON_UNESCAPED_UNICODE),
        ':aceite_termos' => $aceite_termos_db
    ]);
    
    // 4. Criar notificação para admin
    $sqlNotificacao = "INSERT INTO notificacoes (usuario_id, mensagem, tipo, url) 
                       VALUES (1, :mensagem, 'info', 'src/admin/solicitacoes.php')";
    $stmtNotificacao = $conn->prepare($sqlNotificacao);
    
    $mensagemNotificacao = "Nova solicitação de cadastro de {$tipoUsuario}: {$nome}";
    $stmtNotificacao->bindParam(':mensagem', $mensagemNotificacao, PDO::PARAM_STR);
    $stmtNotificacao->execute();
    
    $conn->commit();
    
    // Log de sucesso
    logSecurityEvent('registration_success', 'Cadastro realizado com sucesso', [
        'email' => $email,
        'tipo' => $tipoUsuario,
        'usuario_id' => $usuarioId
    ]);
    
    sendJsonResponse(
        true, 
        'Solicitação de cadastro enviada com sucesso! Em breve você receberá um email com as instruções. Sua conta será ativada após aprovação do administrador.'
    );
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    logError("Erro ao processar solicitação", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    sendJsonResponse(false, 'Erro ao processar solicitação. Tente novamente ou contate o suporte.');
}
?>