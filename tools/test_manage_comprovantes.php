<?php
// Script de teste: simula sessão de admin e inclui a página de comprovantes
session_start();
// Credenciais de teste (não persistir)
$_SESSION['usuario_tipo'] = 'admin';
$_SESSION['usuario_id'] = 1;

// Forçar exibição de erros durante o teste
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir a página (usa caminhos relativos corretos a partir deste arquivo)
require_once __DIR__ . '/../src/admin/manage_comprovantes.php';

// Fim do teste
