<?php
require_once 'conexao.php'; // inicia a sessão certa (EOC_SESSID) com os parâmetros corretos

// Limpa todos os dados da sessão
$_SESSION = [];

// Remove o cookie de sessão do navegador (não basta destruir no servidor)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destrói a sessão no servidor
session_destroy();

header("Location: ../index.php");
exit();
?>