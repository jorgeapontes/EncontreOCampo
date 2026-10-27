<?php
/**
 * Checklist de configuração: confere sozinho o que costuma quebrar depois de um
 * deploy ou de uma mudança na hospedagem (ambiente, segurança, Stripe, e-mail,
 * banco, crons e pastas). Não mostra nenhum segredo: só se ele existe e o formato.
 * Os arquivos sensíveis são testados de verdade, pedindo-os ao próprio site pela web.
 */
require_once __DIR__ . '/includes/comum.php';

$raiz = dirname(__DIR__, 2); // public_html
$local = preg_match('/^localhost(:\d+)?$/', $_SERVER['HTTP_HOST'] ?? '') === 1;
$itens = []; // grupo => [[situação (ok|atencao|problema|neutro), item, detalhe], ...]
$marcar = function (string $grupo, string $situacao, string $item, string $detalhe) use (&$itens) {
    $itens[$grupo][] = [$situacao, $item, $detalhe];
};
$env = fn (string $chave) => trim((string) ($_ENV[$chave] ?? getenv($chave) ?: ''));

/** Pede um endereço do próprio site e devolve o código HTTP (0 se não respondeu). */
function codigoHttp(string $url): int
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 5,
        CURLOPT_RANGE => '0-200', CURLOPT_USERAGENT => 'EncontreOCampo-Checklist']);
    curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $codigo;
}

// ===== Ambiente =====
$g = 'Ambiente';
if ($local) {
    $marcar($g, 'neutro', 'Ambiente', 'Rodando no computador local (XAMPP): itens de produção podem aparecer como atenção.');
} else {
    $marcar($g, $env('APP_ENV') === 'production' ? 'ok' : 'problema', 'APP_ENV no .env', 'Valor: "' . ($env('APP_ENV') ?: 'vazio') . '" (deve ser production).');
    $debug = in_array(strtolower($env('APP_DEBUG')), ['1', 'true', 'yes', 'on'], true);
    $marcar($g, $debug ? 'problema' : 'ok', 'APP_DEBUG desligado', $debug ? 'Está ligado: erros podem aparecer para os visitantes.' : 'Desligado.');
}
$mostraErros = in_array(strtolower((string) ini_get('display_errors')), ['1', 'on', 'true', 'stdout'], true);
$marcar($g, $mostraErros && !$local ? 'problema' : 'ok', 'Erros do PHP escondidos dos visitantes',
    $mostraErros ? 'display_errors está ligado' . ($local ? ' (normal no XAMPP).' : ': desligue nas opções de PHP da Hostinger.') : 'display_errors desligado; os erros vão só para o log.');
$marcar($g, version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'atencao', 'Versão do PHP', PHP_VERSION . (version_compare(PHP_VERSION, '8.1', '>=') ? '' : ' (antiga: atualize para 8.1 ou mais nova na Hostinger).'));
$faltando = array_filter(['pdo_mysql', 'curl', 'mbstring', 'openssl', 'gd', 'fileinfo', 'zlib', 'json'], fn ($ext) => !extension_loaded($ext));
$marcar($g, $faltando ? 'problema' : 'ok', 'Extensões do PHP', $faltando ? 'Faltando: ' . implode(', ', $faltando) . '.' : 'Todas as necessárias estão ativas.');
$upload = ini_get('upload_max_filesize');
$marcar($g, (int) $upload >= 8 ? 'ok' : 'atencao', 'Tamanho máximo de upload', "upload_max_filesize = {$upload}, post_max_size = " . ini_get('post_max_size') . ', memória = ' . ini_get('memory_limit') . '.');
$fusoBanco = (int) $pdo->query("SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())")->fetchColumn();
$fusoOk = date_default_timezone_get() === 'America/Sao_Paulo' && $fusoBanco === (int) (date('Z') / 60);
$marcar($g, $fusoOk ? 'ok' : 'problema', 'Fuso horário (PHP e banco)', 'PHP: ' . date_default_timezone_get() . ' · banco: UTC' . sprintf('%+d', $fusoBanco / 60) . ($fusoOk ? '' : ' (devem bater com o horário de Brasília).'));

// ===== Segurança =====
$g = 'Segurança';
$https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
$marcar($g, $https ? 'ok' : ($local ? 'neutro' : 'problema'), 'HTTPS', $https ? 'A página foi aberta em HTTPS.' : ($local ? 'XAMPP roda sem HTTPS (normal).' : 'O site respondeu sem HTTPS.'));
$cookie = session_get_cookie_params();
$marcar($g, ($cookie['httponly'] && ($cookie['secure'] || $local)) ? 'ok' : 'problema', 'Cookie de sessão protegido',
    'HttpOnly: ' . ($cookie['httponly'] ? 'sim' : 'não') . ' · Secure: ' . ($cookie['secure'] ? 'sim' : 'não') . ' · SameSite: ' . ($cookie['samesite'] ?: '—') . '.');
$csrf = $env('CSRF_SECRET_KEY');
$marcar($g, strlen($csrf) >= 32 ? 'ok' : 'atencao', 'Chave CSRF_SECRET_KEY', strlen($csrf) >= 32 ? 'Definida (' . strlen($csrf) . ' caracteres).' : 'Vazia ou curta (use 32+ caracteres aleatórios).');
$privadaReal = realpath(PASTA_PRIVADA);
$foraDoSite = $privadaReal && strpos($privadaReal, realpath($raiz)) !== 0;
$marcar($g, $foraDoSite && is_writable(PASTA_PRIVADA) ? 'ok' : 'problema', 'Pasta privada (backups e log de erros)',
    $foraDoSite ? (is_writable(PASTA_PRIVADA) ? 'Fora do public_html e aceita escrita.' : 'Existe, mas não aceita escrita.') : 'Não existe ou está dentro do public_html.');

// Arquivos que o navegador NÃO pode baixar (pedidos de verdade ao próprio site)
$site = urlDoSite();
$protegidos = ['.env', 'composer.json', 'composer.lock', 'logs/', 'db/migrations/010_pagamentos_stripe.sql'];
// Um arquivo de log de verdade de cada pasta. Os de src/ são pedidos sem o "src/",
// que é o endereço que a web atende (o com "src/" só redireciona).
foreach (['logs', 'src/scripts/logs', 'src/vendedor/logs'] as $pastaLog) {
    foreach (glob("$raiz/$pastaLog/*.log") ?: [] as $arquivoLog) {
        $protegidos[] = preg_replace('#^src/#', '', $pastaLog) . '/' . basename($arquivoLog);
        break;
    }
}
foreach (glob($raiz . '/*.sql') ?: [] as $dump) {
    $protegidos[] = basename($dump);
}
$expostos = [];
foreach ($protegidos as $caminho) {
    $codigo = codigoHttp($site . '/' . $caminho);
    if ($codigo === 200 || $codigo === 206) {
        $expostos[] = $caminho;
    }
}
$marcar($g, $expostos ? 'problema' : 'ok', 'Arquivos sensíveis bloqueados na web',
    $expostos ? 'Podem ser baixados por qualquer um: ' . implode(', ', $expostos) . '. Confira o .htaccess.' : 'Testados ' . count($protegidos) . ' endereços (.env, logs, dumps .sql, composer): todos bloqueados.');
$sobras = array_values(array_filter(['composer.phar', '.env.production', 'phpinfo.php', 'info.php', 'teste.php', 'test.php', '.git'], fn ($f) => file_exists($raiz . '/' . $f)));
$sobras = array_merge($sobras, array_map('basename', glob($raiz . '/*.sql') ?: []));
$marcar($g, $sobras ? 'atencao' : 'ok', 'Arquivos que não precisam estar no servidor', $sobras ? 'Encontrados: ' . implode(', ', $sobras) . ' (apague pelo Gerenciador de Arquivos da Hostinger).' : 'Nenhum.');

// ===== Stripe =====
$g = 'Pagamentos (Stripe)';
$secreta = $env('STRIPE_SECRET_KEY');
$publica = $env('STRIPE_PUBLISHABLE_KEY');
$modo = str_starts_with($secreta, 'sk_live_') ? 'real' : (str_starts_with($secreta, 'sk_test_') ? 'teste' : '');
$marcar($g, $modo === 'real' ? 'ok' : ($modo === 'teste' ? ($local ? 'neutro' : 'problema') : 'problema'), 'Chave secreta',
    $modo === '' ? 'STRIPE_SECRET_KEY vazia ou em formato desconhecido.' : "Modo {$modo}" . ($modo === 'teste' && !$local ? ': as cobranças NÃO são de verdade.' : '.'));
$modoPublica = str_starts_with($publica, 'pk_live_') ? 'real' : (str_starts_with($publica, 'pk_test_') ? 'teste' : '');
$marcar($g, $modoPublica !== '' && $modoPublica === $modo ? 'ok' : 'problema', 'Chave pública', $modoPublica === '' ? 'STRIPE_PUBLISHABLE_KEY vazia ou inválida.'
    : "Modo {$modoPublica}" . ($modoPublica === $modo ? ', igual à secreta.' : ', diferente da secreta (o checkout vai falhar).'));
$webhook = $env('STRIPE_WEBHOOK_SECRET');
$webhookOk = str_starts_with($webhook, 'whsec_') && $webhook !== 'whsec_test_replace_me';
$marcar($g, $webhookOk ? 'ok' : 'problema', 'Segredo do webhook', $webhookOk ? 'Definido.' : 'Vazio ou de exemplo: o site recusaria todos os avisos do Stripe.');
$semPreco = $pdo->query("SELECT nome FROM planos WHERE ativo = 1 AND preco_mensal > 0 AND (stripe_price_id IS NULL OR stripe_price_id = '')")->fetchAll(PDO::FETCH_COLUMN);
$marcar($g, $semPreco ? 'problema' : 'ok', 'Planos pagos ligados ao Stripe', $semPreco ? 'Sem preço do Stripe: ' . implode(', ', $semPreco) . '.' : 'Todos os planos pagos ativos têm o preço do Stripe.');
try {
    $ultimoEvento = $pdo->query("SELECT MAX(data_hora) FROM pagamentos_stripe WHERE status <> 'recusado'")->fetchColumn();
    $marcar($g, 'neutro', 'Último aviso recebido do Stripe', $ultimoEvento ? date('d/m/Y H:i', strtotime($ultimoEvento)) . ' (' . tempo_atras(strtotime($ultimoEvento)) . ').' : 'Nenhum ainda.');
} catch (Throwable $e) {
    $marcar($g, 'problema', 'Tabela pagamentos_stripe', 'Não existe: rode a migration 010.');
}

// ===== E-mail =====
$g = 'E-mail';
$smtpFaltando = array_filter(['SMTP_HOST', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_PORT'], fn ($c) => $env($c) === '');
$marcar($g, $smtpFaltando ? 'problema' : 'ok', 'Configuração do SMTP', $smtpFaltando ? 'Faltando no .env: ' . implode(', ', $smtpFaltando) . '.'
    : 'Servidor ' . $env('SMTP_HOST') . ':' . $env('SMTP_PORT') . ' (' . ($env('SMTP_ENCRYPTION') ?: 'sem criptografia') . ').');
if (!$smtpFaltando) {
    $conexao = @fsockopen(($env('SMTP_ENCRYPTION') === 'ssl' ? 'ssl://' : '') . $env('SMTP_HOST'), (int) $env('SMTP_PORT'), $errno, $errstr, 5);
    $marcar($g, $conexao ? 'ok' : 'problema', 'Servidor de e-mail responde', $conexao ? 'Conectou.' : "Não conectou ({$errstr}). Os e-mails do site não vão sair.");
    if ($conexao) {
        fclose($conexao);
    }
}
$alerta = obterConfig('alerta_email', '');
$marcar($g, filter_var($alerta, FILTER_VALIDATE_EMAIL) ? 'ok' : 'atencao', 'E-mail que recebe os alertas', $alerta !== '' ? $alerta : 'Não cadastrado (Configurações): os alertas e o resumo semanal não saem.');

// ===== Banco, crons e arquivos =====
$g = 'Banco, tarefas agendadas e pastas';
$marcar($g, 'ok', 'Banco de dados', 'Conectado · ' . $pdo->query('SELECT VERSION()')->fetchColumn() . '.');
$esperadas = ['logs_auditoria', 'cron_execucoes', 'configuracoes_site', 'pagamentos_stripe', 'log_acessos', 'tentativas_ip'];
$existentes = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$semTabela = array_diff($esperadas, $existentes);
$marcar($g, $semTabela ? 'problema' : 'ok', 'Tabelas do monitoramento', $semTabela ? 'Faltando: ' . implode(', ', $semTabela) . ' (rode as migrations 009 e 010).' : 'Todas existem.');
$ultimas = $pdo->query("SELECT cron, MAX(data_hora) FROM cron_execucoes GROUP BY cron")->fetchAll(PDO::FETCH_KEY_PAIR);
$atrasados = array_keys(array_filter(CRONS, fn ($info, $nome) => !isset($ultimas[$nome]) || strtotime($ultimas[$nome]) < time() - CRON_ATRASO_HORAS * 3600, ARRAY_FILTER_USE_BOTH));
$marcar($g, $atrasados ? 'problema' : 'ok', 'Tarefas agendadas rodando', $atrasados ? 'Sem rodar há mais de ' . CRON_ATRASO_HORAS . ' h: ' . implode(', ', array_map(fn ($n) => CRONS[$n][0], $atrasados)) . ' (veja a Saúde).' : 'Todas rodaram nas últimas ' . CRON_ATRASO_HORAS . ' h.');
$backups = glob(PASTA_PRIVADA . '/backups/backup_*.sql.gz') ?: [];
rsort($backups);
$idadeBackup = $backups ? (time() - filemtime($backups[0])) / 86400 : null;
$marcar($g, $idadeBackup !== null && $idadeBackup <= 8 ? 'ok' : 'problema', 'Backup recente', $idadeBackup === null ? 'Nenhum backup ainda.' : 'Último ' . tempo_atras(filemtime($backups[0])) . '.');
$marcar($g, (int) obterConfig('limite_disco_mb', '0') > 0 ? 'ok' : 'atencao', 'Limite de disco configurado', (int) obterConfig('limite_disco_mb', '0') > 0 ? obterConfig('limite_disco_mb') . ' MB (alerta em 80%).' : 'Não configurado (Configurações): sem alerta de disco cheio.');
$pastasSemEscrita = array_filter(['uploads', 'uploads/documentos', 'src/uploads'], fn ($p) => is_dir("$raiz/$p") && !is_writable("$raiz/$p"));
$marcar($g, $pastasSemEscrita ? 'problema' : 'ok', 'Pastas de upload aceitam escrita', $pastasSemEscrita ? 'Sem permissão: ' . implode(', ', $pastasSemEscrita) . ' (fotos e documentos não vão salvar).' : 'uploads/, uploads/documentos e src/uploads OK.');

$contagem = ['ok' => 0, 'atencao' => 0, 'problema' => 0];
foreach ($itens as $lista) {
    foreach ($lista as [$situacao]) {
        if (isset($contagem[$situacao])) {
            $contagem[$situacao]++;
        }
    }
}
$selos = ['ok' => ['Certo', 'ok'], 'atencao' => ['Atenção', 'atencao'], 'problema' => ['Problema', 'problema'], 'neutro' => ['Informação', 'neutro']];

$mensagem = mensagemPendente();
$paginaAtiva = 'checklist';
$tituloPagina = 'Checklist';
require __DIR__ . '/includes/layout_inicio.php';
?>
            <div class="row g-3 mb-4">
                <div class="col-4"><div class="cartao-status ok"><div class="rotulo">Certo</div><div class="valor"><?= $contagem['ok'] ?></div></div></div>
                <div class="col-4"><div class="cartao-status <?= $contagem['atencao'] ? 'atencao' : 'neutro' ?>"><div class="rotulo">Atenção</div><div class="valor"><?= $contagem['atencao'] ?></div></div></div>
                <div class="col-4"><div class="cartao-status <?= $contagem['problema'] ? 'problema' : 'neutro' ?>"><div class="rotulo">Problemas</div><div class="valor"><?= $contagem['problema'] ?></div></div></div>
            </div>

            <?php foreach ($itens as $grupo => $lista): ?>
                <h2 class="titulo-secao"><?= e($grupo) ?></h2>
                <div class="table-responsive mb-4">
                    <table class="table tabela-acessos tabela-cartoes">
                        <thead><tr><th>Item</th><th>Situação</th><th>Detalhe</th></tr></thead>
                        <tbody>
                            <?php foreach ($lista as [$situacao, $item, $detalhe]): ?>
                                <tr>
                                    <td><?= e($item) ?></td>
                                    <td class="text-nowrap"><span class="selo <?= e($selos[$situacao][1]) ?>"><?= e($selos[$situacao][0]) ?></span></td>
                                    <td class="texto-secundario"><?= e($detalhe) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            <p class="texto-secundario small">
                Conferido agora (<?= date('d/m/Y H:i') ?>). Vale abrir esta tela depois de cada deploy ou mudança na hospedagem.
                Nenhum segredo é mostrado: só se ele existe e o formato.
            </p>
<?php require __DIR__ . '/includes/layout_fim.php'; ?>
