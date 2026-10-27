<?php
/**
 * Monitoramento do site — SÓ para o usuário master (dono do sistema), que não usa as páginas do admin.
 * Seções (no menu lateral): Saúde, Auditoria, Segurança, Erros, Backups e Configurações.
 * Os dados vêm de cron_execucoes e logs_auditoria (gravados pelos crons e pelas telas)
 * e dos arquivos da pasta privada (backups e log de erros), fora do public_html.
 */
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../functions/auditoria_functions.php';
require_once __DIR__ . '/../functions/config_functions.php';

if (($_SESSION['usuario_tipo'] ?? '') !== 'master') {
    if (isset($_SESSION['usuario_id'])) {
        auditoriaAcessoNegado('master');
    }
    header("Location: ../login");
    exit;
}

$pdo = (new Database())->getConnection();
if (!$pdo) {
    error_log('Monitoramento: sem conexão com o banco');
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

function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// ===== Configurações (e-mail de alerta e limite de disco) =====
if (empty($_SESSION['csrf_monitoramento'])) {
    $_SESSION['csrf_monitoramento'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'configuracoes') {
    $ok = hash_equals($_SESSION['csrf_monitoramento'], (string) ($_POST['csrf'] ?? ''));
    $email = trim((string) ($_POST['alerta_email'] ?? ''));
    $limite = (int) ($_POST['limite_disco_mb'] ?? 0);
    if ($ok && filter_var($email, FILTER_VALIDATE_EMAIL) && $limite >= 0) {
        salvarConfig('alerta_email', $email);
        salvarConfig('limite_disco_mb', (string) $limite);
        $_SESSION['monitoramento_msg'] = ['success', 'Configurações salvas.'];
    } else {
        $_SESSION['monitoramento_msg'] = ['danger', 'Não foi possível salvar: confira o e-mail informado.'];
    }
    header('Location: monitoramento?aba=configuracoes');
    exit;
}

// ===== E-mail de teste (confirma que os alertas da manutenção chegam) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'email_teste') {
    $destino = obterConfig('alerta_email', '');
    if (!hash_equals($_SESSION['csrf_monitoramento'], (string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['monitoramento_msg'] = ['danger', 'Requisição inválida. Tente novamente.'];
    } elseif (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['monitoramento_msg'] = ['danger', 'Cadastre um e-mail de alerta válido antes de testar.'];
    } else {
        require_once __DIR__ . '/../../includes/send_notification.php';
        if (enviarEmailNotificacao($destino, 'Master', '[Encontre o Campo] Teste do e-mail de alertas',
            'Este é um teste enviado pela tela de Monitoramento em ' . date('d/m/Y H:i') . '.<br>'
            . 'Se você recebeu, os alertas da manutenção diária também vão chegar neste endereço.')) {
            $_SESSION['monitoramento_msg'] = ['success', "E-mail de teste enviado para {$destino}. Confira a caixa de entrada (e o spam)."];
        } else {
            $_SESSION['monitoramento_msg'] = ['danger', 'O envio falhou. O motivo foi registrado na seção Erros.'];
        }
    }
    header('Location: monitoramento?aba=configuracoes');
    exit;
}

// ===== Troca da própria senha (o master não tem página de perfil) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'senha') {
    $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
    $stmt->execute([(int) $_SESSION['usuario_id']]);
    $senhaAtualHash = (string) $stmt->fetchColumn();
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');

    if (!hash_equals($_SESSION['csrf_monitoramento'], (string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['monitoramento_msg'] = ['danger', 'Requisição inválida. Tente novamente.'];
    } elseif (!password_verify((string) ($_POST['senha_atual'] ?? ''), $senhaAtualHash)) {
        usleep(random_int(300000, 600000)); // dificulta tentativas em sequência
        $_SESSION['monitoramento_msg'] = ['danger', 'A senha atual está incorreta.'];
    } elseif (strlen($novaSenha) < 8) {
        $_SESSION['monitoramento_msg'] = ['danger', 'A nova senha deve ter pelo menos 8 caracteres.'];
    } elseif ($novaSenha !== (string) ($_POST['confirma_senha'] ?? '')) {
        $_SESSION['monitoramento_msg'] = ['danger', 'A confirmação não confere com a nova senha.'];
    } else {
        $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([password_hash($novaSenha, PASSWORD_DEFAULT), (int) $_SESSION['usuario_id']]);
        registrarAuditoria('senha_alterada', 'Trocou a própria senha (master)', 'usuario', $_SESSION['usuario_id']);
        session_regenerate_id(true);
        $_SESSION['monitoramento_msg'] = ['success', 'Senha alterada.'];
    }
    header('Location: monitoramento?aba=configuracoes');
    exit;
}
$mensagem = $_SESSION['monitoramento_msg'] ?? null;
unset($_SESSION['monitoramento_msg']);

$abas = ['saude' => 'Saúde', 'auditoria' => 'Auditoria', 'seguranca' => 'Segurança', 'erros' => 'Erros', 'backups' => 'Backups', 'configuracoes' => 'Configurações'];
$aba = isset($abas[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'saude';
$pastaBackups = PASTA_PRIVADA . '/backups';
$arquivoErros = PASTA_PRIVADA . '/logs/erros_site.log';

$backups = glob($pastaBackups . '/backup_*.sql.gz') ?: [];
rsort($backups);
$ultimoBackupTs = $backups ? filemtime($backups[0]) : null;
$backupAtrasado = !$ultimoBackupTs || (time() - $ultimoBackupTs) > 8 * 86400;

// ===== Aba Saúde =====
if ($aba === 'saude') {
    $execucoes = [];
    $ultimaPorCron = [];
    $ultimaManutencao = null;
    $haTrintaDias = null;
    try {
        $execucoes = $pdo->query("SELECT * FROM cron_execucoes ORDER BY data_hora DESC, id DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pdo->query(
            "SELECT c.* FROM cron_execucoes c
             JOIN (SELECT cron, MAX(id) AS id FROM cron_execucoes GROUP BY cron) u ON u.id = c.id"
        ) as $linha) {
            $ultimaPorCron[$linha['cron']] = $linha;
        }
        $ultimaManutencao = $ultimaPorCron['cron_limpar_banco'] ?? null;
        $stmt = $pdo->prepare("SELECT tamanho_banco, tamanho_uploads FROM cron_execucoes
                               WHERE cron = 'cron_limpar_banco' AND data_hora <= ? ORDER BY data_hora DESC LIMIT 1");
        $stmt->execute([date('Y-m-d H:i:s', strtotime('-30 days'))]);
        $haTrintaDias = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('Monitoramento: ' . $e->getMessage());
    }

    $cronsComProblema = 0;
    foreach (CRONS as $nome => $info) {
        $u = $ultimaPorCron[$nome] ?? null;
        if (!$u || (time() - strtotime($u['data_hora'])) > CRON_ATRASO_HORAS * 3600 || $u['status'] !== 'ok') {
            $cronsComProblema++;
        }
    }
}

// ===== Configurações =====
if ($aba === 'configuracoes') {
    $alertaEmail = obterConfig('alerta_email', '');
    $limiteDiscoMb = (int) obterConfig('limite_disco_mb', '0');
}

$rotulos = [];
foreach (AUDITORIA_ACOES as $acoes) {
    $rotulos += $acoes;
}

// ===== Aba Auditoria =====
if ($aba === 'auditoria') {
    $periodos = [7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 365 => '1 ano'];
    $periodo = isset($periodos[(int) ($_GET['periodo'] ?? 0)]) ? (int) $_GET['periodo'] : 30;
    $filtroAcao = (string) ($_GET['acao_filtro'] ?? '');
    $busca = trim((string) ($_GET['busca'] ?? ''));
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
    $porPagina = 50;

    $where = ["data_hora >= ?"];
    $params = [date('Y-m-d H:i:s', strtotime("-{$periodo} days"))];
    if (str_starts_with($filtroAcao, 'grupo:') && isset(AUDITORIA_ACOES[substr($filtroAcao, 6)])) {
        $chaves = array_keys(AUDITORIA_ACOES[substr($filtroAcao, 6)]);
        $where[] = 'acao IN (' . implode(',', array_fill(0, count($chaves), '?')) . ')';
        $params = array_merge($params, $chaves);
    } elseif (isset($rotulos[$filtroAcao])) {
        $where[] = 'acao = ?';
        $params[] = $filtroAcao;
    } else {
        $filtroAcao = '';
    }
    if ($busca !== '') {
        $where[] = '(usuario_nome LIKE ? OR descricao LIKE ?)';
        $params[] = "%{$busca}%";
        $params[] = "%{$busca}%";
    }
    $sqlWhere = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs_auditoria {$sqlWhere}");
    $stmt->execute($params);
    $totalAuditoria = (int) $stmt->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalAuditoria / $porPagina));
    $pagina = min($pagina, $totalPaginas);

    $stmt = $pdo->prepare("SELECT * FROM logs_auditoria {$sqlWhere} ORDER BY data_hora DESC, id DESC LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina));
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $urlFiltros = 'monitoramento?aba=auditoria&periodo=' . $periodo . '&acao_filtro=' . urlencode($filtroAcao) . '&busca=' . urlencode($busca);
}

// ===== Aba Segurança (últimos 7 dias) =====
if ($aba === 'seguranca') {
    $seteDias = "NOW() - INTERVAL 7 DAY";
    $loginsOk = (int) $pdo->query("SELECT COUNT(*) FROM log_acessos WHERE sucesso = 1 AND data_tentativa >= {$seteDias}")->fetchColumn();
    $loginsFalha = (int) $pdo->query("SELECT COUNT(*) FROM log_acessos WHERE motivo_falha = 'senha_incorreta' AND data_tentativa >= {$seteDias}")->fetchColumn();
    $emailsInexistentes = (int) $pdo->query("SELECT COUNT(*) FROM log_acessos WHERE motivo_falha = 'email_nao_encontrado' AND data_tentativa >= {$seteDias}")->fetchColumn();
    $falhasPorUsuario = $pdo->query(
        "SELECT u.nome, u.tipo, COUNT(*) AS falhas, MAX(l.data_tentativa) AS ultima
         FROM log_acessos l JOIN usuarios u ON u.id = l.usuario_id
         WHERE l.motivo_falha = 'senha_incorreta' AND l.data_tentativa >= {$seteDias}
         GROUP BY u.id, u.nome, u.tipo ORDER BY falhas DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
    // bloqueado_ate é gravado a cada senha errada; só conta como bloqueio a partir de 5 (login.php)
    $stmt = $pdo->prepare("SELECT nome, tipo, bloqueado_ate FROM usuarios WHERE tentativas_falhas >= 5 AND bloqueado_ate > ? ORDER BY bloqueado_ate");
    $stmt->execute([date('Y-m-d H:i:s')]);
    $contasBloqueadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tentativas_ip WHERE bloqueado_ate > ?");
    $stmt->execute([date('Y-m-d H:i:s')]);
    $ipsBloqueados = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT * FROM logs_auditoria WHERE acao IN ('acesso_negado', 'conta_bloqueada', 'senha_alterada', 'senha_redefinida', 'reset_solicitado', 'backup_baixado',
                                                                   'email_alterado', 'stripe_erro', 'stripe_assinatura_invalida')
                           AND data_hora >= ? ORDER BY data_hora DESC LIMIT 50");
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-30 days'))]);
    $eventosSeguranca = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== Aba Erros: fim do log de erros, mais recentes primeiro =====
if ($aba === 'erros') {
    $linhasErro = [];
    $tamanhoLogErros = is_file($arquivoErros) ? filesize($arquivoErros) : 0;
    if ($tamanhoLogErros > 0) {
        $inicio = max(0, $tamanhoLogErros - 128 * 1024);
        $trecho = (string) file_get_contents($arquivoErros, false, null, $inicio);
        $linhas = preg_split('/\R(?=\[\d{2}-\w{3}-\d{4} )/', trim($trecho)); // uma entrada por data (stack traces juntos)
        if ($inicio > 0) {
            array_shift($linhas); // a primeira pode estar cortada no meio
        }
        $linhasErro = array_slice(array_reverse($linhas), 0, 150);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Monitoramento - Encontre o Campo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="../css/master/base.css?v=<?= @filemtime(__DIR__ . '/../css/master/base.css') ?>">
    <link rel="stylesheet" href="../css/master/monitoramento.css?v=<?= @filemtime(__DIR__ . '/../css/master/monitoramento.css') ?>">
</head>
<body>

<div class="d-flex">
    <?php $paginaAtiva = $aba; $tituloMobile = $abas[$aba]; require __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="page-header">
            <div>
                <div class="sobretitulo">Monitoramento</div>
                <h1 class="page-title"><?= e($abas[$aba]) ?></h1>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= e($mensagem[0]) ?> alert-dismissible fade show" role="alert">
                <?= e($mensagem[1]) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>


        <?php if ($aba === 'saude'): ?>
            <div class="row g-3 mb-4">
                <!-- Crons -->
                <div class="col-md-6 col-xl-3">
                    <div class="cartao-status <?= $cronsComProblema ? 'problema' : 'ok' ?>">
                        <div class="rotulo"><i class="fas fa-clock"></i> Tarefas agendadas</div>
                        <div class="valor"><?= count(CRONS) - $cronsComProblema ?> de <?= count(CRONS) ?> ok</div>
                        <div class="detalhe">
                            <?= $cronsComProblema ? $cronsComProblema . ' com falha, alerta ou atraso — veja abaixo' : 'todas rodaram nas últimas ' . CRON_ATRASO_HORAS . ' h' ?>
                        </div>
                    </div>
                </div>
                <!-- Backup -->
                <div class="col-md-6 col-xl-3">
                    <div class="cartao-status <?= $backupAtrasado ? 'problema' : 'ok' ?>">
                        <div class="rotulo"><i class="fas fa-database"></i> Último backup</div>
                        <?php if ($ultimoBackupTs): ?>
                            <div class="valor"><?= tempo_atras($ultimoBackupTs) ?></div>
                            <div class="detalhe"><?= date('d/m/Y', $ultimoBackupTs) ?> · <?= tamanho_legivel(filesize($backups[0])) ?> · <?= count($backups) ?> guardado(s)</div>
                        <?php else: ?>
                            <div class="valor">Nenhum</div>
                            <div class="detalhe">O primeiro sai na próxima manutenção</div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Banco -->
                <div class="col-md-6 col-xl-3">
                    <div class="cartao-status neutro">
                        <div class="rotulo"><i class="fas fa-table"></i> Banco de dados</div>
                        <div class="valor"><?= tamanho_legivel(isset($ultimaManutencao['tamanho_banco']) ? (int) $ultimaManutencao['tamanho_banco'] : null) ?></div>
                        <div class="detalhe">
                            <?php if ($haTrintaDias && $ultimaManutencao): ?>
                                há 30 dias: <?= tamanho_legivel((int) $haTrintaDias['tamanho_banco']) ?>
                            <?php else: ?>
                                medido pela manutenção diária
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Uploads -->
                <div class="col-md-6 col-xl-3">
                    <div class="cartao-status neutro">
                        <div class="rotulo"><i class="fas fa-folder-open"></i> Arquivos enviados</div>
                        <div class="valor"><?= tamanho_legivel(isset($ultimaManutencao['tamanho_uploads']) ? (int) $ultimaManutencao['tamanho_uploads'] : null) ?></div>
                        <div class="detalhe">
                            <?php if ($haTrintaDias && $ultimaManutencao): ?>
                                há 30 dias: <?= tamanho_legivel((int) $haTrintaDias['tamanho_uploads']) ?>
                            <?php else: ?>
                                fotos de anúncios, documentos e chat
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($ultimaManutencao && $ultimaManutencao['alertas']): ?>
                <div class="alert alert-warning">
                    <strong>Alertas da última manutenção</strong>
                    <?= $ultimaManutencao['email_enviado'] ? '(enviados por e-mail)' : '(o e-mail NÃO foi enviado — confira a seção Erros ou o e-mail de alerta nas Configurações)' ?>
                    <ul class="mb-0 mt-2">
                        <?php foreach (explode("\n", $ultimaManutencao['alertas']) as $alerta): ?>
                            <li><?= e($alerta) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <h2 class="titulo-secao">Tarefas agendadas</h2>
            <div class="table-responsive mb-4">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead>
                        <tr><th>Tarefa</th><th>Horário</th><th>Última execução</th><th>Resultado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (CRONS as $nome => [$descricao, $horario]): ?>
                            <?php $u = $ultimaPorCron[$nome] ?? null; $atrasado = !$u || (time() - strtotime($u['data_hora'])) > CRON_ATRASO_HORAS * 3600; ?>
                            <tr>
                                <td><?= e($descricao) ?></td>
                                <td class="texto-secundario text-nowrap">todo dia, <?= e($horario) ?></td>
                                <td class="text-nowrap"><?= $u ? date('d/m/Y H:i', strtotime($u['data_hora'])) . ' <span class="texto-secundario small">· ' . tempo_atras(strtotime($u['data_hora'])) . '</span>' : '<span class="texto-secundario">nunca</span>' ?></td>
                                <td class="text-nowrap">
                                    <?php if ($atrasado): ?>
                                        <span class="selo problema">Não rodou<?= $u ? ' nas últimas ' . CRON_ATRASO_HORAS . ' h' : '' ?></span>
                                    <?php elseif ($u['status'] === 'ok'): ?>
                                        <span class="selo ok">Tudo certo</span>
                                    <?php else: ?>
                                        <span class="selo atencao" title="<?= e($u['alertas']) ?>">Alerta</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2 class="titulo-secao">Últimas execuções</h2>
            <div class="table-responsive mb-4">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead>
                        <tr><th>Data</th><th>Tarefa</th><th>Resultado</th><th>Duração</th><th>Resumo</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($execucoes as $execucao): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($execucao['data_hora'])) ?></td>
                                <td class="text-nowrap"><?= e(CRONS[$execucao['cron']][0] ?? $execucao['cron']) ?></td>
                                <td class="text-nowrap">
                                    <?php if ($execucao['status'] === 'ok'): ?>
                                        <span class="selo ok">Tudo certo</span>
                                    <?php else: ?>
                                        <span class="selo atencao" title="<?= e($execucao['alertas']) ?>">Alerta<?= $execucao['email_enviado'] ? ' · e-mail enviado' : '' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="texto-secundario"><?= (int) $execucao['duracao_segundos'] ?> s</td>
                                <td class="texto-secundario resumo-cron"><?= e($execucao['resumo']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$execucoes): ?>
                            <tr><td colspan="5" class="text-center py-4 texto-vazio">Nenhuma execução registrada ainda.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($aba === 'configuracoes'): ?>
            <h2 class="titulo-secao">Alertas</h2>
            <form method="POST" class="filtros-acessos row g-3 mb-2">
                <input type="hidden" name="acao" value="configuracoes">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_monitoramento']) ?>">
                <div class="col-md-6">
                    <label class="form-label" for="alertaEmail">E-mail que recebe os alertas</label>
                    <input type="email" class="form-control" id="alertaEmail" name="alerta_email" value="<?= e($alertaEmail) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="limiteDisco">Limite de disco do plano (MB)</label>
                    <input type="number" class="form-control" id="limiteDisco" name="limite_disco_mb" min="0" step="1" value="<?= $limiteDiscoMb ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filtrar w-100">Salvar</button>
                </div>
            </form>
            <p class="texto-secundario small mb-4">
                Os alertas chegam por e-mail só quando a manutenção diária encontra algum problema em qualquer tarefa agendada (no máximo um por noite).
                O alerta de disco dispara quando o site passa de 80% do limite. Use 0 para desligar.
            </p>

            <form method="POST" class="mb-4">
                <input type="hidden" name="acao" value="email_teste">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_monitoramento']) ?>">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-paper-plane me-1"></i> Enviar e-mail de teste
                </button>
                <span class="texto-secundario small ms-2">Confirma que os alertas chegam no endereço acima.</span>
            </form>

            <h2 class="titulo-secao">Minha senha</h2>
            <form method="POST" class="filtros-acessos row g-3 mb-2" autocomplete="off">
                <input type="hidden" name="acao" value="senha">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_monitoramento']) ?>">
                <div class="col-md-4">
                    <label class="form-label" for="senhaAtual">Senha atual</label>
                    <input type="password" class="form-control" id="senhaAtual" name="senha_atual" autocomplete="current-password" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="novaSenha">Nova senha</label>
                    <input type="password" class="form-control" id="novaSenha" name="nova_senha" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="confirmaSenha">Confirmar</label>
                    <input type="password" class="form-control" id="confirmaSenha" name="confirma_senha" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filtrar w-100">Trocar</button>
                </div>
            </form>
            <p class="texto-secundario small">Mínimo de 8 caracteres.</p>

        <?php elseif ($aba === 'auditoria'): ?>
            <form method="GET" class="filtros-acessos row g-3 mb-4">
                <input type="hidden" name="aba" value="auditoria">
                <div class="col-md-4">
                    <label class="form-label" for="buscaAuditoria">Buscar</label>
                    <input type="text" class="form-control" id="buscaAuditoria" name="busca" value="<?= e($busca) ?>" placeholder="Nome ou descrição...">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="acaoFiltro">Ação</label>
                    <select class="form-select" id="acaoFiltro" name="acao_filtro">
                        <option value="">Todas</option>
                        <?php foreach (AUDITORIA_ACOES as $grupo => $acoes): ?>
                            <optgroup label="<?= e($grupo) ?>">
                                <option value="grupo:<?= e($grupo) ?>" <?= $filtroAcao === 'grupo:' . $grupo ? 'selected' : '' ?>>Tudo de <?= e(mb_strtolower($grupo)) ?></option>
                                <?php foreach ($acoes as $chave => $rotulo): ?>
                                    <option value="<?= e($chave) ?>" <?= $filtroAcao === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="periodo">Período</label>
                    <select class="form-select" id="periodo" name="periodo">
                        <?php foreach ($periodos as $dias => $rotulo): ?>
                            <option value="<?= $dias ?>" <?= $periodo === $dias ? 'selected' : '' ?>>Últimos <?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filtrar w-100">Filtrar</button>
                </div>
            </form>

            <p class="contagem-acessos"><?= $totalAuditoria ?> registro(s) · mais recentes primeiro · guardados por 1 ano</p>

            <div class="table-responsive">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead>
                        <tr><th>Data</th><th>Quem</th><th>Ação</th><th>Descrição</th><th>IP</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $r): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($r['data_hora'])) ?></td>
                                <td>
                                    <?= e($r['usuario_nome'] ?? '—') ?>
                                    <?php if ($r['perfil']): ?><span class="texto-secundario small">· <?= e($r['perfil']) ?></span><?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= e($rotulos[$r['acao']] ?? $r['acao']) ?></td>
                                <td><?= e($r['descricao']) ?></td>
                                <td class="texto-secundario text-nowrap"><?= e($r['ip']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$registros): ?>
                            <tr><td colspan="5" class="text-center py-5 texto-vazio">Nenhum registro para esses filtros.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Páginas">
                    <a class="btn btn-outline-secondary <?= $pagina <= 1 ? 'disabled' : '' ?>" href="<?= e($urlFiltros . '&pagina=' . ($pagina - 1)) ?>">&larr; Mais recentes</a>
                    <span class="texto-secundario small">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                    <a class="btn btn-outline-secondary <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>" href="<?= e($urlFiltros . '&pagina=' . ($pagina + 1)) ?>">Mais antigos &rarr;</a>
                </nav>
            <?php endif; ?>

        <?php elseif ($aba === 'seguranca'): ?>
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="cartao-status neutro">
                        <div class="rotulo">Logins (7 dias)</div>
                        <div class="valor"><?= $loginsOk ?></div>
                        <div class="detalhe">entradas com sucesso</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="cartao-status <?= $loginsFalha > 30 ? 'atencao' : 'neutro' ?>">
                        <div class="rotulo">Senhas erradas (7 dias)</div>
                        <div class="valor"><?= $loginsFalha ?></div>
                        <div class="detalhe">em contas existentes · <?= $emailsInexistentes ?> com e-mail inexistente</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="cartao-status <?= $contasBloqueadas ? 'atencao' : 'neutro' ?>">
                        <div class="rotulo">Contas bloqueadas agora</div>
                        <div class="valor"><?= count($contasBloqueadas) ?></div>
                        <div class="detalhe">por 5 senhas erradas</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="cartao-status <?= $ipsBloqueados ? 'atencao' : 'neutro' ?>">
                        <div class="rotulo">IPs bloqueados agora</div>
                        <div class="valor"><?= $ipsBloqueados ?></div>
                        <div class="detalhe">por excesso de tentativas</div>
                    </div>
                </div>
            </div>

            <?php if ($contasBloqueadas): ?>
                <h2 class="titulo-secao">Contas bloqueadas agora</h2>
                <ul class="lista-simples mb-4">
                    <?php foreach ($contasBloqueadas as $c): ?>
                        <li><?= e($c['nome']) ?> <span class="texto-secundario">(<?= e($c['tipo']) ?>) · até <?= date('H:i', strtotime($c['bloqueado_ate'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h2 class="titulo-secao">Quem mais errou a senha (7 dias)</h2>
            <?php if ($falhasPorUsuario): ?>
                <ul class="lista-simples mb-4">
                    <?php foreach ($falhasPorUsuario as $f): ?>
                        <li><?= e($f['nome']) ?> <span class="texto-secundario">(<?= e($f['tipo']) ?>) · <?= (int) $f['falhas'] ?> erro(s), último em <?= date('d/m H:i', strtotime($f['ultima'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="texto-vazio mb-4">Ninguém errou a senha nos últimos 7 dias.</p>
            <?php endif; ?>

            <h2 class="titulo-secao">Eventos de segurança (30 dias)</h2>
            <div class="table-responsive">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead><tr><th>Data</th><th>Quem</th><th>Evento</th><th>Descrição</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php foreach ($eventosSeguranca as $r): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($r['data_hora'])) ?></td>
                                <td><?= e($r['usuario_nome'] ?? '—') ?></td>
                                <td class="text-nowrap"><?= e($rotulos[$r['acao']] ?? $r['acao']) ?></td>
                                <td><?= e($r['descricao']) ?></td>
                                <td class="texto-secundario text-nowrap"><?= e($r['ip']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$eventosSeguranca): ?>
                            <tr><td colspan="5" class="text-center py-4 texto-vazio">Nenhum evento nos últimos 30 dias.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($aba === 'erros'): ?>
            <p class="contagem-acessos">
                Erros registrados pelo PHP no site (mais recentes primeiro) · arquivo com <?= tamanho_legivel($tamanhoLogErros) ?>,
                trocado automaticamente ao passar de 5 MB. Linhas em vermelho são erros graves.
            </p>
            <?php if ($linhasErro): ?>
                <div class="log-erros">
                    <?php foreach ($linhasErro as $linha): ?>
                        <div class="<?= preg_match('/PHP Fatal error|Uncaught|SQLSTATE/', $linha) ? 'grave' : '' ?>"><?= e($linha) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="texto-vazio">Nenhum erro registrado.</p>
            <?php endif; ?>

        <?php elseif ($aba === 'backups'): ?>
            <p class="contagem-acessos">
                Backup do banco feito toda semana pela manutenção diária. Ficam guardados os 4 mais recentes e 1 por mês dos últimos 6 meses
                (no máximo 50 MB), numa pasta fora do site. Os arquivos enviados (fotos e documentos) não entram aqui: eles estão no backup da Hostinger.
            </p>
            <div class="table-responsive">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead><tr><th>Backup</th><th>Tamanho</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($backups as $arquivo): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', filemtime($arquivo)) ?> <span class="texto-secundario small">· <?= tempo_atras(filemtime($arquivo)) ?></span></td>
                                <td class="texto-secundario"><?= tamanho_legivel(filesize($arquivo)) ?></td>
                                <td class="td-acoes text-end">
                                    <a class="btn btn-sm btn-outline-secondary" href="baixar_backup?arquivo=<?= urlencode(basename($arquivo)) ?>">
                                        <i class="fas fa-download"></i> Baixar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$backups): ?>
                            <tr><td colspan="3" class="text-center py-4 texto-vazio">Nenhum backup ainda. O primeiro sai na próxima manutenção (1h da manhã).</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="texto-secundario small mt-3">
                <i class="fas fa-lock"></i> O backup contém todos os dados do site (inclusive dados pessoais de compradores, vendedores e transportadores). Guarde o arquivo baixado em local seguro.
                Para restaurar: phpMyAdmin → banco → aba Importar → escolher o arquivo .sql.gz.
            </p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
