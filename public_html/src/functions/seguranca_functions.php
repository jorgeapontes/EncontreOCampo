<?php
// src/functions/seguranca_functions.php
// Ações de emergência do master (aba Segurança) e a checagem que as faz valer
// em toda requisição. Incluído pelo privado.php; o conexao.php chama
// verificarSessaoEncerrada() logo depois do session_start().
//
//   - IPs bloqueados no site inteiro: PASTA_PRIVADA/ips_bloqueados.json
//     {ip: {ate, motivo}}. Arquivo em vez do banco para não custar uma consulta
//     por página. Sempre com prazo (o bloqueio vence sozinho).
//   - Sessões encerradas: PASTA_PRIVADA/sessoes_encerradas.json {usuario_id: hora}.
//     Toda sessão desse usuário iniciada antes dessa hora é derrubada na próxima
//     página que ele abrir (o login grava $_SESSION['login_em']).

if (defined('SEGURANCA_FUNCOES') || !defined('PASTA_PRIVADA')) {
    return;
}
define('SEGURANCA_FUNCOES', true);

const SEG_ARQUIVO_IPS = PASTA_PRIVADA . '/ips_bloqueados.json';
const SEG_ARQUIVO_SESSOES = PASTA_PRIVADA . '/sessoes_encerradas.json';

function segLerJson(string $arquivo): array
{
    if (!is_file($arquivo)) {
        return [];
    }
    $dados = json_decode((string) @file_get_contents($arquivo), true);
    return is_array($dados) ? $dados : [];
}

function segGravarJson(string $arquivo, array $dados): bool
{
    return @file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

/** IPs bloqueados que ainda estão no prazo: [ip => ['ate' => timestamp, 'motivo' => ...]]. */
function ipsBloqueadosNoSite(): array
{
    return array_filter(segLerJson(SEG_ARQUIVO_IPS), fn ($b) => ($b['ate'] ?? 0) > time());
}

function bloquearIpNoSite(string $ip, int $dias, string $motivo): bool
{
    $lista = ipsBloqueadosNoSite(); // já descarta os vencidos
    $lista[$ip] = ['ate' => time() + $dias * 86400, 'motivo' => mb_substr($motivo, 0, 150), 'desde' => time()];
    return segGravarJson(SEG_ARQUIVO_IPS, $lista);
}

function desbloquearIpNoSite(string $ip): bool
{
    $lista = ipsBloqueadosNoSite();
    unset($lista[$ip]);
    return segGravarJson(SEG_ARQUIVO_IPS, $lista);
}

/** Derruba todas as sessões abertas do usuário (vale na próxima página que ele abrir). */
function encerrarSessoesUsuario(int $usuarioId): bool
{
    // Guarda 30 dias: nenhuma sessão do site dura tanto
    $lista = array_filter(segLerJson(SEG_ARQUIVO_SESSOES), fn ($hora) => $hora > time() - 30 * 86400);
    $lista[(string) $usuarioId] = time();
    return segGravarJson(SEG_ARQUIVO_SESSOES, $lista);
}

/** Chamado pelo conexao.php depois do session_start(): derruba a sessão se ela foi encerrada pelo master. */
function verificarSessaoEncerrada(): void
{
    if (PHP_SAPI === 'cli' || empty($_SESSION['usuario_id']) || !is_file(SEG_ARQUIVO_SESSOES)) {
        return;
    }
    $encerradaEm = segLerJson(SEG_ARQUIVO_SESSOES)[(string) $_SESSION['usuario_id']] ?? null;
    if ($encerradaEm !== null && (int) ($_SESSION['login_em'] ?? 0) < $encerradaEm) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}

// ----- Bloqueio de IP: vale para todas as páginas -----
if (PHP_SAPI !== 'cli' && is_file(SEG_ARQUIVO_IPS)) {
    $ipVisitante = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ipVisitante !== '' && isset(ipsBloqueadosNoSite()[$ipVisitante])) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><title>Acesso bloqueado</title>'
            . '<body style="font-family:sans-serif;text-align:center;padding:60px 16px;color:#333">'
            . '<h1>Acesso temporariamente bloqueado</h1>'
            . '<p>O acesso a partir da sua rede foi bloqueado por segurança.</p>'
            . '<p>Se acha que é um engano, escreva para privacidade@encontreocampo.com.br.</p></body></html>');
    }
}
