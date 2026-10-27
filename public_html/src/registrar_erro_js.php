<?php
// src/registrar_erro_js.php
// Recebe os erros de JavaScript mandados pelo script que o functions/monitor_erros.php
// injeta nas páginas, e grava no log de erros (aba Erros do master) com "JS:" no começo.
// É público (visitantes também têm erro), então: só POST, corpo pequeno, no máximo
// 10 registros a cada 10 minutos por sessão, e ignora o ruído de extensões do navegador.

require_once __DIR__ . '/conexao.php';

http_response_code(204);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    exit;
}

$dados = json_decode((string) file_get_contents('php://input', false, null, 0, 8192), true);
if (!is_array($dados)) {
    exit;
}

/** Uma linha só, sem caracteres de controle, com limite de tamanho. */
function textoJs($valor, int $limite): string
{
    return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $valor)), 0, $limite);
}

$mensagem = preg_replace('/^Uncaught\s+/i', '', textoJs($dados['mensagem'] ?? '', 300));
$fonte = textoJs($dados['fonte'] ?? '', 300);
$pagina = monitorErrosUrl(textoJs($dados['pagina'] ?? '', 300));

// Ruído que não é do site: extensões do navegador e erros de scripts de outros domínios ("Script error.")
if ($mensagem === '' || preg_match('#^(chrome|moz|safari)-extension://#', $fonte)
    || ($mensagem === 'Script error.' && $fonte === '')) {
    exit;
}

// Limite por sessão: 10 registros a cada 10 minutos
$agora = time();
$_SESSION['erros_js'] = array_values(array_filter($_SESSION['erros_js'] ?? [], fn ($t) => $t > $agora - 600));
if (count($_SESSION['erros_js']) >= 10) {
    exit;
}
$_SESSION['erros_js'][] = $agora;

$linha = (int) ($dados['linha'] ?? 0);
$coluna = (int) ($dados['coluna'] ?? 0);
$origem = $fonte !== '' ? ' em ' . preg_replace('/\?.*$/', '', $fonte) . ($linha ? ":$linha" . ($coluna ? ":$coluna" : '') : '') : '';
$pilha = '';
if (!empty($dados['pilha'])) {
    // As 3 primeiras linhas da pilha bastam para achar a função
    $linhasPilha = array_slice(preg_split('/\R/', (string) $dados['pilha']), 1, 3);
    $pilha = $linhasPilha ? ' | pilha: ' . textoJs(implode(' ← ', array_map('trim', $linhasPilha)), 400) : '';
}
$navegador = textoJs($_SERVER['HTTP_USER_AGENT'] ?? '', 160);

// O contexto (linha "↳") mostra a página onde o erro aconteceu, não este endereço
$GLOBALS['MONITOR_ERROS_PAGINA'] = 'JS na página ' . ($pagina !== '' ? $pagina : '?');
error_log('JS: ' . ($dados['tipo'] ?? 'erro') . ': ' . $mensagem . $origem . $pilha . ' | navegador: ' . $navegador);
