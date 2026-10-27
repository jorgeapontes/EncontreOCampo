<?php
// src/functions/monitor_erros.php
// Deixa o log de erros (PASTA_PRIVADA/logs/erros_site.log) útil para investigar bugs.
// Incluído pelo privado.php, logo vale para o site todo.
//
// 1. Contexto: se a requisição gravou algo no log, acrescenta no fim uma linha
//    "↳ GET /vendedor/dashboard · usuário #12 (vendedor)". Sem data no começo, ela
//    fica junto do erro na aba Erros do master. Parâmetros sensíveis (token, senha...)
//    aparecem como ***.
// 2. Erros de JavaScript: nas páginas HTML, injeta antes do </head> um script
//    pequeno que manda os erros do navegador para src/registrar_erro_js.php
//    (que grava neste mesmo log, com "JS:" no começo).

if (defined('MONITOR_ERROS_ATIVO') || !defined('PASTA_PRIVADA')) {
    return;
}
define('MONITOR_ERROS_ATIVO', true);

const MONITOR_ERROS_ARQUIVO = PASTA_PRIVADA . '/logs/erros_site.log';

/** Caminho + query com valores sensíveis mascarados: /reset_password?token=*** */
function monitorErrosUrl(string $url): string
{
    $url = mb_substr($url, 0, 300);
    return preg_replace('/([?&](?:token|senha|password|pass|key|code|secret|session_id)[^=&]*=)[^&#]*/i', '$1***', $url);
}

/** Onde o erro aconteceu: página (ou script do cron) e quem estava logado. */
function monitorErrosContexto(): string
{
    if (PHP_SAPI === 'cli') {
        return 'cron/linha de comando: ' . basename($_SERVER['SCRIPT_FILENAME'] ?? '?');
    }
    $pagina = $GLOBALS['MONITOR_ERROS_PAGINA'] ?? (($_SERVER['REQUEST_METHOD'] ?? '?') . ' ' . monitorErrosUrl($_SERVER['REQUEST_URI'] ?? '?'));
    $usuario = isset($_SESSION['usuario_id'])
        ? 'usuário #' . (int) $_SESSION['usuario_id'] . ' (' . ($_SESSION['usuario_tipo'] ?? '?') . ')'
        : 'visitante sem login';
    return $pagina . ' · ' . $usuario;
}

// ----- 1. Linha de contexto no fim da requisição, se ela gravou no log -----
clearstatcache(true, MONITOR_ERROS_ARQUIVO);
$GLOBALS['MONITOR_ERROS_TAMANHO_INICIAL'] = is_file(MONITOR_ERROS_ARQUIVO) ? filesize(MONITOR_ERROS_ARQUIVO) : 0;
register_shutdown_function(function () {
    clearstatcache(true, MONITOR_ERROS_ARQUIVO);
    if (is_file(MONITOR_ERROS_ARQUIVO) && filesize(MONITOR_ERROS_ARQUIVO) > $GLOBALS['MONITOR_ERROS_TAMANHO_INICIAL']) {
        @file_put_contents(MONITOR_ERROS_ARQUIVO, '    ↳ ' . monitorErrosContexto() . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
});

// ----- 2. Coletor de erros de JavaScript nas páginas HTML -----
// Só em navegação de página (GET que aceita HTML): chamadas AJAX, POSTs e
// downloads não passam pelo buffer.
if (PHP_SAPI !== 'cli'
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false
) {
    // Endereço do site (raiz em produção, subpasta no XAMPP): SCRIPT_NAME menos o caminho do arquivo dentro do public_html
    $relativo = str_replace('\\', '/', substr(realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '', strlen(realpath(dirname(__DIR__, 2)))));
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = ($relativo !== '' && substr($script, -strlen($relativo)) === $relativo) ? substr($script, 0, -strlen($relativo)) : '';
    $endpoint = json_encode(rtrim($base, '/') . '/registrar_erro_js');

    $tag = '<script>(function(){var u=' . $endpoint . ',n=0,p=location.pathname+location.search;'
        . 'function m(d){if(n++>=5)return;d.pagina=p;try{var b=JSON.stringify(d);'
        . 'if(navigator.sendBeacon){navigator.sendBeacon(u,new Blob([b],{type:"application/json"}))}'
        . 'else{fetch(u,{method:"POST",body:b,keepalive:true,headers:{"Content-Type":"application/json"}})}}catch(x){}}'
        . 'window.addEventListener("error",function(e){var t=e.target;'
        . 'if(t&&t!==window){if(t.tagName==="SCRIPT"||t.tagName==="LINK")m({tipo:"recurso",mensagem:"Falha ao carregar "+t.tagName.toLowerCase(),fonte:t.src||t.href});return}'
        . 'm({tipo:"erro",mensagem:e.message,fonte:e.filename,linha:e.lineno,coluna:e.colno,pilha:e.error&&e.error.stack})},true);'
        . 'window.addEventListener("unhandledrejection",function(e){var r=e.reason;'
        . 'm({tipo:"promessa",mensagem:r&&r.message?r.message:String(r),pilha:r&&r.stack})})})();</script>';

    $GLOBALS['MONITOR_ERROS_INJETADO'] = false;
    // Buffer em pedaços de 8 KB (não guarda a página inteira na memória); depois
    // de achar o </head>, ou ver que a resposta não é HTML, só repassa.
    ob_start(function ($pedaco) use ($tag) {
        if ($GLOBALS['MONITOR_ERROS_INJETADO']) {
            return $pedaco;
        }
        foreach (headers_list() as $cabecalho) {
            if (stripos($cabecalho, 'content-type:') === 0 && stripos($cabecalho, 'text/html') === false) {
                $GLOBALS['MONITOR_ERROS_INJETADO'] = true; // JSON, imagem, PDF, gz...
                return $pedaco;
            }
        }
        $pos = stripos($pedaco, '</head>');
        if ($pos === false) {
            return $pedaco;
        }
        $GLOBALS['MONITOR_ERROS_INJETADO'] = true;
        return substr_replace($pedaco, $tag, $pos, 0);
    }, 8192);
}
