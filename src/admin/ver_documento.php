<?php
// src/admin/ver_documento.php
// Serve as fotos de rosto/documento de um usuário, SOMENTE para admin logado.
// Nunca confia em caminho de arquivo vindo da URL — sempre busca o caminho
// real no banco, a partir do usuario_id.

require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    http_response_code(403);
    exit('Acesso negado.');
}

$usuario_id = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT);
$tipo = $_GET['tipo'] ?? '';

$colunas_permitidas = [
    'rosto'  => 'foto_rosto',
    'frente' => 'foto_documento_frente',
    'verso'  => 'foto_documento_verso',
];

if (!$usuario_id || !isset($colunas_permitidas[$tipo])) {
    http_response_code(400);
    exit('Parâmetros inválidos.');
}

$coluna = $colunas_permitidas[$tipo];

$database = new Database();
$db = $database->getConnection();

// Nome da coluna vem de uma lista fixa acima (nunca do usuário), então é seguro
// interpolar aqui dentro do SELECT.
$stmt = $db->prepare("SELECT {$coluna} AS caminho FROM usuarios WHERE id = :id");
$stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resultado || empty($resultado['caminho'])) {
    http_response_code(404);
    exit('Documento não encontrado.');
}

// Caminho salvo no banco é relativo à raiz do public_html, ex: "uploads/documentos/arquivo.jpg"
$caminho_relativo = $resultado['caminho'];
$caminho_absoluto = realpath(__DIR__ . '/../../' . $caminho_relativo);

// Garante que o caminho resolvido continua DENTRO da pasta de uploads esperada
// (proteção extra contra qualquer manipulação, mesmo o dado vindo do banco)
$pasta_uploads_permitida = realpath(__DIR__ . '/../../uploads/documentos');

if (
    !$caminho_absoluto ||
    !$pasta_uploads_permitida ||
    strpos($caminho_absoluto, $pasta_uploads_permitida) !== 0 ||
    !is_file($caminho_absoluto)
) {
    error_log("Tentativa de acesso a documento inválido/ausente: usuario_id={$usuario_id}, tipo={$tipo}, caminho={$caminho_relativo}");
    http_response_code(404);
    exit('Documento não encontrado.');
}

// Detecta o tipo real do arquivo pelo conteúdo (não confia na extensão do nome)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $caminho_absoluto);
finfo_close($finfo);

$mimes_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $mimes_permitidos, true)) {
    error_log("Arquivo com MIME inesperado bloqueado: {$caminho_absoluto} ({$mime})");
    http_response_code(415);
    exit('Tipo de arquivo não suportado.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminho_absoluto));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

readfile($caminho_absoluto);
exit();