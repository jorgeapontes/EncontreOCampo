<?php
// src/processar_solicitacao_debug.php
// Versão debug do processar_solicitacao

require_once 'conexao.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_keys' => array_keys($_POST),
    'files_keys' => array_keys($_FILES),
    'post_sample' => []
];

// Coletar amostra dos dados POST (sem valores sensíveis)
foreach ($_POST as $key => $value) {
    if (is_array($value)) {
        $debug['post_sample'][$key] = 'array:' . count($value);
    } else {
        $debug['post_sample'][$key] = strlen($value) . ' chars';
    }
}

// Testar conexão
try {
    $database = new Database();
    $conn = $database->getConnection();
    if ($conn) {
        $debug['database_connection'] = 'OK';
    } else {
        $debug['database_connection'] = 'FALHA';
    }
} catch (Exception $e) {
    $debug['database_error'] = $e->getMessage();
}

echo json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
