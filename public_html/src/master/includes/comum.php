<?php
/**
 * Base de todas as páginas do master: só deixa passar o usuário master, abre a
 * conexão ($pdo), prepara o token CSRF das ações e traz as funções de formatação.
 * Incluir no topo da página, antes de qualquer saída.
 */
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../functions/auditoria_functions.php';
require_once __DIR__ . '/../../functions/config_functions.php';

if (($_SESSION['usuario_tipo'] ?? '') !== 'master') {
    if (isset($_SESSION['usuario_id'])) {
        auditoriaAcessoNegado('master');
    }
    header("Location: ../login");
    exit;
}

$pdo = (new Database())->getConnection();
if (!$pdo) {
    error_log('Master: sem conexão com o banco');
    die('Ocorreu um erro ao carregar a página. Tente novamente mais tarde.');
}

const CRON_ATRASO_HORAS = 30; // todos rodam 1x por dia; passou disso, algo está errado

/** Crons agendados na Hostinger (nome usado em cronIniciar => descrição e horário de Brasília). */
const CRONS = [
    'cron_limpar_banco'      => ['Manutenção do banco e backup', '01:00'],
    'cron_vencimentos'       => ['Vencimento das assinaturas', '21:00'],
    'limpar_logs'            => ['Limpeza de logs e temporários', '21:00'],
    'cron_rejeitados'        => ['Cadastros rejeitados', '01:30'],
    'cron_documentos_orfaos' => ['Documentos órfãos', '01:45'],
];

if (empty($_SESSION['csrf_monitoramento'])) {
    $_SESSION['csrf_monitoramento'] = bin2hex(random_bytes(32));
}

/** O formulário enviado veio desta área (token CSRF confere)? */
function csrfOk(): bool
{
    return hash_equals($_SESSION['csrf_monitoramento'], (string) ($_POST['csrf'] ?? ''));
}

/** Campo oculto com o token, para colocar em todo formulário POST. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf_monitoramento']) . '">';
}

/** Mensagem mostrada no topo da próxima página (depois do redirecionamento). */
function avisar(string $tipo, string $texto): void
{
    $_SESSION['monitoramento_msg'] = [$tipo, $texto];
}

/** Lê (e apaga) a mensagem guardada por avisar(). */
function mensagemPendente(): ?array
{
    $mensagem = $_SESSION['monitoramento_msg'] ?? null;
    unset($_SESSION['monitoramento_msg']);
    return $mensagem;
}

function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** "1,2 MB" / "350 KB" */
function tamanho_legivel(?int $bytes): string
{
    if ($bytes === null) {
        return '—';
    }
    if ($bytes === 0) {
        return '0 KB';
    }
    return $bytes < 1048576
        ? max(1, round($bytes / 1024)) . ' KB'
        : number_format($bytes / 1048576, 1, ',', '.') . ' MB';
}

/** "há 3 h" / "há 2 dias" */
function tempo_atras(int $timestamp): string
{
    $segundos = time() - $timestamp;
    if ($segundos < 3600) {
        return 'há ' . max(1, floor($segundos / 60)) . ' min';
    }
    if ($segundos < 86400 * 2) {
        return 'há ' . floor($segundos / 3600) . ' h';
    }
    return 'há ' . floor($segundos / 86400) . ' dias';
}

/** "R$ 49,90" */
function reais(?float $valor): string
{
    return $valor === null ? '—' : 'R$ ' . number_format($valor, 2, ',', '.');
}

/** Endereço do site, para links mandados por e-mail (https://encontreocampo.com.br, ou a subpasta no XAMPP). */
function urlDoSite(): string
{
    $relativo = str_replace('\\', '/', substr(realpath($_SERVER['SCRIPT_FILENAME']) ?: '', strlen(realpath(dirname(__DIR__, 3)))));
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = substr($script, -strlen($relativo)) === $relativo ? substr($script, 0, -strlen($relativo)) : '';
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($base, '/');
}
