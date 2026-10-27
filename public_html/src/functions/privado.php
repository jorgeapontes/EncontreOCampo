<?php
// src/functions/privado.php
// Pasta privada, FORA do public_html (o navegador não alcança): backups do banco
// e log de erros do PHP. Incluído pelo conexao.php, então vale para o site todo
// e para os crons.
//   Produção: /home/u569225384/domains/encontreocampo.com.br/privado
//   XAMPP:    C:/xampp/htdocs/EncontreOCampo/privado (fora do git)

if (!defined('PASTA_PRIVADA')) {
    define('PASTA_PRIVADA', dirname(__DIR__, 3) . '/privado');

    if (!is_dir(PASTA_PRIVADA . '/logs') && @mkdir(PASTA_PRIVADA . '/logs', 0750, true)) {
        // Segunda proteção, caso a pasta um dia fique dentro do alcance do servidor web
        @file_put_contents(PASTA_PRIVADA . '/.htaccess', "Require all denied\n");
    }

    // Erros do PHP vão para um arquivo só (antes o PHP espalhava arquivos error_log
    // pelas pastas do site). A aba Erros do monitoramento mostra este arquivo e o
    // cron_limpar_banco faz a rotação.
    if (is_dir(PASTA_PRIVADA . '/logs')) {
        ini_set('log_errors', '1');
        ini_set('error_log', PASTA_PRIVADA . '/logs/erros_site.log');
        // Contexto (página e usuário) nos erros e coletor de erros de JavaScript
        require_once __DIR__ . '/monitor_erros.php';
    }
}
