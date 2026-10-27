<?php
/**
 * Monitoramento do site — SÓ para o usuário master (dono do sistema), que não usa as páginas do admin.
 * Seções (no menu lateral): Saúde, Auditoria, Segurança, Pagamentos, Erros, Backups e
 * Configurações. Admins, LGPD e Checklist são páginas próprias (admins.php, lgpd.php, checklist.php).
 * Os dados vêm de cron_execucoes e logs_auditoria (gravados pelos crons e pelas telas)
 * e dos arquivos da pasta privada (backups e log de erros), fora do public_html.
 */
require_once __DIR__ . '/includes/comum.php';
require_once __DIR__ . '/../functions/seguranca_functions.php';

// ===== Configurações (e-mail de alerta, limite de disco e resumo semanal) =====
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'configuracoes') {
    $ok = hash_equals($_SESSION['csrf_monitoramento'], (string) ($_POST['csrf'] ?? ''));
    $email = trim((string) ($_POST['alerta_email'] ?? ''));
    $limite = (int) ($_POST['limite_disco_mb'] ?? 0);
    if ($ok && filter_var($email, FILTER_VALIDATE_EMAIL) && $limite >= 0) {
        salvarConfig('alerta_email', $email);
        salvarConfig('limite_disco_mb', (string) $limite);
        salvarConfig('resumo_semanal', isset($_POST['resumo_semanal']) ? '1' : '0');
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
// ===== Resumo semanal: enviar agora (o cron manda sozinho toda segunda) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'resumo_agora') {
    require_once __DIR__ . '/../functions/resumo_functions.php';
    $destino = obterConfig('alerta_email', '');
    if (!csrfOk()) {
        avisar('danger', 'Requisição inválida. Tente novamente.');
    } elseif (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        avisar('danger', 'Cadastre um e-mail de alerta válido antes de enviar o resumo.');
    } elseif (enviarResumoSemanal($pdo, $destino)) {
        avisar('success', "Resumo dos últimos 7 dias enviado para {$destino}.");
    } else {
        avisar('danger', 'O envio falhou. O motivo foi registrado na seção Erros.');
    }
    header('Location: monitoramento?aba=configuracoes');
    exit;
}

// ===== Conferência das assinaturas com o Stripe (aba Pagamentos) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['conferir_stripe', 'corrigir_stripe'], true)) {
    require_once __DIR__ . '/../functions/pagamentos_functions.php';
    if (!csrfOk()) {
        avisar('danger', 'Requisição inválida. Tente novamente.');
    } elseif ($_POST['acao'] === 'conferir_stripe') {
        try {
            $linhas = conferirAssinaturasComStripe($pdo);
            $comDivergencia = count(array_filter($linhas, fn ($l) => !empty($l['divergencias'])));
            $semResposta = count(array_filter($linhas, fn ($l) => !empty($l['erro'])));
            $_SESSION['conferencia_stripe'] = ['em' => time(), 'linhas' => $linhas];
            salvarConfig('stripe_ultima_conferencia', date('Y-m-d H:i:s'));
            registrarAuditoria('pagamento_conferido', 'Conferiu ' . count($linhas) . " assinatura(s) com o Stripe: {$comDivergencia} com divergência"
                . ($semResposta ? ", {$semResposta} sem resposta" : ''), 'pagamento');
            avisar($comDivergencia || $semResposta ? 'warning' : 'success', $comDivergencia || $semResposta
                ? "De " . count($linhas) . " assinatura(s): {$comDivergencia} diferente(s) do Stripe" . ($semResposta ? " e {$semResposta} que não deu para consultar" : '') . '. Veja abaixo.'
                : 'Conferidas ' . count($linhas) . ' assinatura(s): o site está igual ao Stripe.');
        } catch (\Stripe\Exception\AuthenticationException $e) {
            unset($_SESSION['conferencia_stripe']);
            avisar('danger', 'O Stripe recusou a chave do site (STRIPE_SECRET_KEY no .env). Veja o Checklist.');
        } catch (Throwable $e) {
            error_log('Conferência Stripe: ' . $e->getMessage());
            avisar('danger', 'Não foi possível falar com o Stripe: ' . $e->getMessage());
        }
    } else {
        $vendedorId = (int) ($_POST['vendedor_id'] ?? 0);
        try {
            $feito = corrigirVendedorPeloStripe($pdo, $vendedorId);
            registrarAuditoria('pagamento_corrigido', mb_substr($feito, 0, 250), 'vendedor', $vendedorId);
            // Tira o vendedor corrigido da lista da última conferência
            if (isset($_SESSION['conferencia_stripe']['linhas'])) {
                foreach ($_SESSION['conferencia_stripe']['linhas'] as &$linha) {
                    if ((int) $linha['vendedor']['id'] === $vendedorId) {
                        $linha['corrigido'] = true;
                    }
                }
                unset($linha);
            }
            avisar('success', $feito);
        } catch (Throwable $e) {
            avisar('danger', 'Não foi possível corrigir: ' . $e->getMessage());
        }
    }
    header('Location: monitoramento?aba=pagamentos');
    exit;
}

// ===== Ações de emergência (aba Segurança) =====
$acoesSeguranca = ['desbloquear_conta', 'desbloquear_ip_login', 'bloquear_ip', 'desbloquear_ip_site', 'encerrar_sessoes'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', $acoesSeguranca, true)) {
    $acao = $_POST['acao'];
    $ip = trim((string) ($_POST['ip'] ?? ''));
    if (!csrfOk()) {
        avisar('danger', 'Requisição inválida. Tente novamente.');
    } elseif ($acao === 'desbloquear_conta') {
        $id = (int) ($_POST['usuario_id'] ?? 0);
        $pdo->prepare("UPDATE usuarios SET tentativas_falhas = 0, bloqueado_ate = NULL WHERE id = ?")->execute([$id]);
        registrarAuditoria('conta_desbloqueada', 'Desbloqueou a conta de ' . auditoriaUsuario($id), 'usuario', $id);
        avisar('success', 'Conta desbloqueada: a pessoa já pode tentar entrar de novo.');
    } elseif (!in_array($acao, ['desbloquear_conta', 'encerrar_sessoes'], true) && !filter_var($ip, FILTER_VALIDATE_IP)) {
        avisar('danger', 'Informe um IP válido (ex.: 189.40.22.10).');
    } elseif ($acao === 'desbloquear_ip_login') {
        $pdo->prepare("UPDATE tentativas_ip SET tentativas = 0, bloqueado_ate = NULL WHERE ip = ?")->execute([$ip]);
        registrarAuditoria('ip_desbloqueado', "Liberou o login do IP {$ip} (bloqueado por tentativas)", 'ip');
        avisar('success', "O IP {$ip} já pode tentar entrar de novo.");
    } elseif ($acao === 'bloquear_ip') {
        $dias = in_array((int) ($_POST['dias'] ?? 0), [1, 7, 30], true) ? (int) $_POST['dias'] : 1;
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        if ($ip === ($_SERVER['REMOTE_ADDR'] ?? '')) {
            avisar('danger', 'Esse é o IP que você está usando agora: bloqueá-lo tiraria você do site.');
        } elseif (bloquearIpNoSite($ip, $dias, $motivo)) {
            registrarAuditoria('ip_bloqueado', "Bloqueou o IP {$ip} no site por {$dias} dia(s)" . ($motivo !== '' ? ": {$motivo}" : ''), 'ip');
            avisar('success', "IP {$ip} bloqueado no site inteiro por {$dias} dia(s).");
        } else {
            avisar('danger', 'Não foi possível gravar o bloqueio (a pasta privada existe e aceita escrita?).');
        }
    } elseif ($acao === 'desbloquear_ip_site') {
        desbloquearIpNoSite($ip);
        registrarAuditoria('ip_desbloqueado', "Tirou o bloqueio do IP {$ip} no site", 'ip');
        avisar('success', "IP {$ip} desbloqueado.");
    } else { // encerrar_sessoes
        $stmt = $pdo->prepare("SELECT id, nome, tipo FROM usuarios WHERE email = ?");
        $stmt->execute([trim((string) ($_POST['email'] ?? ''))]);
        $alvo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$alvo) {
            avisar('danger', 'Nenhuma conta com esse e-mail.');
        } elseif ((int) $alvo['id'] === (int) $_SESSION['usuario_id']) {
            avisar('danger', 'Essa é a sua própria conta. Para sair, use o botão Sair.');
        } elseif (encerrarSessoesUsuario((int) $alvo['id'])) {
            registrarAuditoria('sessoes_encerradas', "Encerrou as sessões de {$alvo['nome']} ({$alvo['tipo']})", 'usuario', $alvo['id']);
            avisar('success', "Sessões de {$alvo['nome']} encerradas: na próxima página, a pessoa terá de entrar de novo. Se a senha vazou, peça para trocá-la.");
        } else {
            avisar('danger', 'Não foi possível gravar (a pasta privada existe e aceita escrita?).');
        }
    }
    header('Location: monitoramento?aba=seguranca');
    exit;
}

$mensagem = mensagemPendente();

$abas = ['saude' => 'Saúde', 'auditoria' => 'Auditoria', 'seguranca' => 'Segurança', 'pagamentos' => 'Pagamentos', 'erros' => 'Erros', 'backups' => 'Backups', 'configuracoes' => 'Configurações'];
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
    $resumoSemanal = obterConfig('resumo_semanal', '1') === '1';
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

    // Eventos do Stripe ficam na seção Pagamentos (os stripe_* são registros antigos, de antes dela)
    $where = ["data_hora >= ?", "acao NOT LIKE 'stripe\\_%'"];
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
    $stmt = $pdo->prepare("SELECT id, nome, tipo, bloqueado_ate FROM usuarios WHERE tentativas_falhas >= 5 AND bloqueado_ate > ? ORDER BY bloqueado_ate");
    $stmt->execute([date('Y-m-d H:i:s')]);
    $contasBloqueadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT ip, tentativas, bloqueado_ate FROM tentativas_ip WHERE bloqueado_ate > ? ORDER BY bloqueado_ate");
    $stmt->execute([date('Y-m-d H:i:s')]);
    $ipsLogin = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ipsBloqueados = count($ipsLogin);
    $ipsSite = ipsBloqueadosNoSite();
    $meuIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM logs_auditoria WHERE acao IN ('acesso_negado', 'conta_bloqueada', 'senha_alterada', 'senha_redefinida', 'reset_solicitado', 'backup_baixado',
                                                                   'email_alterado', 'conta_desbloqueada', 'ip_desbloqueado', 'ip_bloqueado', 'sessoes_encerradas')
                           AND data_hora >= ? ORDER BY data_hora DESC LIMIT 50");
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-30 days'))]);
    $eventosSeguranca = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== Aba Pagamentos: assinaturas dos vendedores e eventos do Stripe =====
if ($aba === 'pagamentos') {
    require_once __DIR__ . '/../functions/pagamentos_functions.php';
    $periodosPag = [7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 365 => '1 ano'];
    $periodoPag = isset($periodosPag[(int) ($_GET['periodo'] ?? 0)]) ? (int) $_GET['periodo'] : 30;
    $filtroStatus = isset(STRIPE_STATUS[$_GET['status'] ?? '']) ? $_GET['status'] : '';
    $desde = date('Y-m-d H:i:s', strtotime("-{$periodoPag} days"));

    // Vendedores com plano pago (ou que já passaram pelo Stripe)
    $assinaturas = $pdo->query(
        "SELECT v.id, u.nome, p.nome AS plano, p.preco_mensal, v.status_assinatura,
                v.data_inicio_assinatura, v.data_vencimento_assinatura, v.stripe_subscription_id
         FROM vendedores v
         JOIN usuarios u ON u.id = v.usuario_id
         LEFT JOIN planos p ON p.id = v.plano_id
         WHERE p.preco_mensal > 0 OR v.stripe_subscription_id IS NOT NULL
         ORDER BY FIELD(v.status_assinatura, 'ativo', 'atrasado') DESC, v.data_vencimento_assinatura"
    )->fetchAll(PDO::FETCH_ASSOC);

    $ativas = $atrasadas = $semRenovar = 0;
    $receitaMensal = 0.0;
    foreach ($assinaturas as &$a) {
        $pago = (float) $a['preco_mensal'] > 0;
        // Vencida há mais de 2 dias (a carência do cron) e ainda "ativa": a renovação não chegou
        $a['sem_renovar'] = $pago && $a['status_assinatura'] === 'ativo' && $a['data_vencimento_assinatura']
            && strtotime($a['data_vencimento_assinatura']) < strtotime('-2 days');
        if ($pago && $a['status_assinatura'] === 'ativo') {
            $ativas++;
            $receitaMensal += (float) $a['preco_mensal'];
        }
        $atrasadas += $a['status_assinatura'] === 'atrasado' ? 1 : 0;
        $semRenovar += $a['sem_renovar'] ? 1 : 0;
    }
    unset($a);

    $conferencia = $_SESSION['conferencia_stripe'] ?? null;
    $ultimaConferencia = obterConfig('stripe_ultima_conferencia', '');

    $recebido = ['total' => 0, 'qtd' => 0];
    $problemasPag = 0;
    $eventosStripe = [];
    try {
        // invoice.payment_succeeded chega junto com invoice.paid: soma só o invoice.paid para não contar duas vezes
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor), 0) AS total, COUNT(*) AS qtd FROM pagamentos_stripe
                               WHERE tipo = 'invoice.paid' AND status = 'ok' AND data_hora >= ?");
        $stmt->execute([$desde]);
        $recebido = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pagamentos_stripe WHERE status IN ('falha', 'erro') AND data_hora >= ?");
        $stmt->execute([$desde]);
        $problemasPag = (int) $stmt->fetchColumn();

        $sql = "SELECT ps.*, u.nome AS vendedor_nome FROM pagamentos_stripe ps
                LEFT JOIN vendedores v ON v.id = ps.vendedor_id
                LEFT JOIN usuarios u ON u.id = v.usuario_id
                WHERE ps.data_hora >= ?" . ($filtroStatus ? " AND ps.status = ?" : '') . "
                ORDER BY ps.data_hora DESC, ps.id DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($filtroStatus ? [$desde, $filtroStatus] : [$desde]);
        $eventosStripe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Monitoramento (pagamentos): ' . $e->getMessage());
        $mensagem = $mensagem ?? ['warning', 'A tabela pagamentos_stripe ainda não existe: rode a migration 010 no phpMyAdmin.'];
    }
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
$paginaAtiva = $aba;
$tituloPagina = $abas[$aba];
require __DIR__ . '/includes/layout_inicio.php';
?>


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
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="resumoSemanal" name="resumo_semanal" value="1" <?= $resumoSemanal ? 'checked' : '' ?>>
                        <label class="form-check-label" for="resumoSemanal">Receber o resumo semanal por e-mail (toda segunda, de madrugada)</label>
                    </div>
                </div>
            </form>
            <p class="texto-secundario small mb-4">
                Os alertas chegam por e-mail só quando a manutenção diária encontra algum problema em qualquer tarefa agendada (no máximo um por noite).
                O alerta de disco dispara quando o site passa de 80% do limite. Use 0 para desligar.
                O resumo semanal traz cadastros, anúncios, negociações, pagamentos, erros e espaço em disco dos últimos 7 dias.
            </p>

            <form method="POST" class="mb-4 d-inline-block me-2">
                <input type="hidden" name="acao" value="resumo_agora"><?= campoCsrf() ?>
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-chart-line me-1"></i> Enviar o resumo semanal agora
                </button>
            </form>

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
                        <li class="d-flex flex-wrap align-items-center gap-2">
                            <span><?= e($c['nome']) ?> <span class="texto-secundario">(<?= e($c['tipo']) ?>) · até <?= date('H:i', strtotime($c['bloqueado_ate'])) ?></span></span>
                            <form method="POST" class="ms-auto">
                                <input type="hidden" name="acao" value="desbloquear_conta"><?= campoCsrf() ?>
                                <input type="hidden" name="usuario_id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-unlock me-1"></i> Desbloquear</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($ipsLogin): ?>
                <h2 class="titulo-secao">IPs impedidos de fazer login agora</h2>
                <ul class="lista-simples mb-4">
                    <?php foreach ($ipsLogin as $i): ?>
                        <li class="d-flex flex-wrap align-items-center gap-2">
                            <span><?= e($i['ip']) ?> <span class="texto-secundario">· <?= (int) $i['tentativas'] ?> tentativa(s) · até <?= date('d/m H:i', strtotime($i['bloqueado_ate'])) ?></span></span>
                            <form method="POST" class="ms-auto">
                                <input type="hidden" name="acao" value="desbloquear_ip_login"><?= campoCsrf() ?>
                                <input type="hidden" name="ip" value="<?= e($i['ip']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-unlock me-1"></i> Liberar</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h2 class="titulo-secao">Ações de emergência</h2>
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <form method="POST" class="filtros-acessos h-100">
                        <input type="hidden" name="acao" value="encerrar_sessoes"><?= campoCsrf() ?>
                        <label class="form-label" for="emailSessoes"><i class="fas fa-user-slash me-1"></i> Encerrar as sessões de uma conta</label>
                        <div class="d-flex gap-2">
                            <input type="email" class="form-control" id="emailSessoes" name="email" placeholder="e-mail da conta" required>
                            <button type="submit" class="btn btn-filtrar text-nowrap" onclick="return confirm('Tirar essa pessoa do site em todos os aparelhos?')">Encerrar</button>
                        </div>
                        <p class="texto-secundario small mb-0 mt-2">Para conta invadida: tira a pessoa do site em todos os aparelhos. Depois, peça para ela trocar a senha.</p>
                    </form>
                </div>
                <div class="col-lg-6">
                    <form method="POST" class="filtros-acessos h-100">
                        <input type="hidden" name="acao" value="bloquear_ip"><?= campoCsrf() ?>
                        <label class="form-label" for="ipBloquear"><i class="fas fa-ban me-1"></i> Bloquear um IP no site inteiro</label>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="text" class="form-control flex-grow-1 w-auto" id="ipBloquear" name="ip" placeholder="IP (ex.: 189.40.22.10)" required>
                            <select class="form-select w-auto" name="dias" aria-label="Por quanto tempo">
                                <option value="1">1 dia</option><option value="7">7 dias</option><option value="30">30 dias</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <input type="text" class="form-control" name="motivo" maxlength="150" placeholder="Motivo (opcional)">
                            <button type="submit" class="btn btn-filtrar text-nowrap" onclick="return confirm('Bloquear esse IP no site inteiro?')">Bloquear</button>
                        </div>
                        <p class="texto-secundario small mb-0 mt-2">Para ataque ou robô insistente. O bloqueio vence sozinho. Seu IP agora: <?= e($meuIp) ?> (não pode ser bloqueado).</p>
                    </form>
                </div>
            </div>

            <?php if ($ipsSite): ?>
                <h2 class="titulo-secao">IPs bloqueados no site</h2>
                <ul class="lista-simples mb-4">
                    <?php foreach ($ipsSite as $ipBloqueado => $b): ?>
                        <li class="d-flex flex-wrap align-items-center gap-2">
                            <span><?= e($ipBloqueado) ?> <span class="texto-secundario">· até <?= date('d/m/Y H:i', (int) $b['ate']) ?><?= !empty($b['motivo']) ? ' · ' . e($b['motivo']) : '' ?></span></span>
                            <form method="POST" class="ms-auto">
                                <input type="hidden" name="acao" value="desbloquear_ip_site"><?= campoCsrf() ?>
                                <input type="hidden" name="ip" value="<?= e($ipBloqueado) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-unlock me-1"></i> Desbloquear</button>
                            </form>
                        </li>
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

        <?php elseif ($aba === 'pagamentos'): ?>
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="cartao-status neutro">
                        <div class="rotulo"><i class="fas fa-user-check"></i> Assinaturas ativas</div>
                        <div class="valor"><?= $ativas ?></div>
                        <div class="detalhe"><?= reais($receitaMensal) ?> por mês nos planos atuais</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="cartao-status neutro">
                        <div class="rotulo"><i class="fas fa-hand-holding-usd"></i> Recebido (<?= e($periodosPag[$periodoPag]) ?>)</div>
                        <div class="valor"><?= reais((float) $recebido['total']) ?></div>
                        <div class="detalhe"><?= (int) $recebido['qtd'] ?> pagamento(s) confirmados pelo Stripe</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="cartao-status <?= ($atrasadas || $semRenovar) ? 'atencao' : 'neutro' ?>">
                        <div class="rotulo"><i class="fas fa-exclamation-circle"></i> Atrasadas agora</div>
                        <div class="valor"><?= $atrasadas + $semRenovar ?></div>
                        <div class="detalhe"><?= $atrasadas ?> com cobrança recusada · <?= $semRenovar ?> vencida(s) sem renovação</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="cartao-status <?= $problemasPag ? 'problema' : 'ok' ?>">
                        <div class="rotulo"><i class="fas fa-bug"></i> Falhas (<?= e($periodosPag[$periodoPag]) ?>)</div>
                        <div class="valor"><?= $problemasPag ?></div>
                        <div class="detalhe">pagamentos recusados ou erros do site ao processar</div>
                    </div>
                </div>
            </div>

            <h2 class="titulo-secao">Conferência com o Stripe</h2>
            <form method="POST" class="mb-3 d-flex flex-wrap align-items-center gap-2">
                <input type="hidden" name="acao" value="conferir_stripe"><?= campoCsrf() ?>
                <button type="submit" class="btn btn-filtrar"><i class="fas fa-sync-alt me-1"></i> Conferir agora</button>
                <span class="texto-secundario small">
                    Pergunta ao Stripe a situação real de cada assinatura e compara com o site.
                    <?= $ultimaConferencia ? 'Última conferência: ' . date('d/m/Y H:i', strtotime($ultimaConferencia)) . '.' : 'Nunca conferido.' ?>
                </span>
            </form>
            <?php if ($conferencia): ?>
                <?php $comProblema = array_filter($conferencia['linhas'], fn ($l) => !empty($l['divergencias']) || !empty($l['erro']) || !empty($l['avisos'])); ?>
                <?php if (!$comProblema): ?>
                    <p class="texto-vazio mb-4">Na conferência das <?= date('H:i', $conferencia['em']) ?>, as <?= count($conferencia['linhas']) ?> assinatura(s) estavam iguais ao Stripe.</p>
                <?php else: ?>
                    <div class="table-responsive mb-4">
                        <table class="table tabela-acessos tabela-cartoes">
                            <thead><tr><th>Vendedor</th><th>No site</th><th>No Stripe</th><th>O que está diferente</th><th>Ações</th></tr></thead>
                            <tbody>
                                <?php foreach ($comProblema as $l): ?>
                                    <tr>
                                        <td><?= e($l['vendedor']['nome']) ?></td>
                                        <td class="text-nowrap"><?= e($l['vendedor']['status_assinatura'] ?: '—') ?></td>
                                        <td class="text-nowrap"><?= e($l['status_stripe'] ?? '—') ?></td>
                                        <td class="small">
                                            <?php if (!empty($l['erro'])): ?>
                                                <span class="text-danger"><?= e($l['erro']) ?></span>
                                            <?php else: ?>
                                                <?= e(implode('; ', array_merge($l['divergencias'], array_map(fn ($a) => 'aviso: ' . $a, $l['avisos'])))) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="td-acoes text-nowrap">
                                            <?php if (!empty($l['corrigido'])): ?>
                                                <span class="selo ok">Corrigido</span>
                                            <?php elseif (!empty($l['divergencias'])): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="acao" value="corrigir_stripe"><?= campoCsrf() ?>
                                                    <input type="hidden" name="vendedor_id" value="<?= (int) $l['vendedor']['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                            onclick="return confirm(<?= e(json_encode('Deixar ' . $l['vendedor']['nome'] . ' igual ao Stripe? (situação: ' . $l['esperado']['status'] . ')')) ?>)">
                                                        <i class="fas fa-check"></i> Corrigir
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="texto-secundario small">só aviso</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="texto-secundario small mb-4">
                        "Corrigir" aplica no site o que o Stripe diz, com a mesma regra do aviso automático do Stripe e da tarefa noturna de vencimentos.
                        Quando o vendedor perde o plano ou fica em atraso, ele é avisado no painel dele.
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <h2 class="titulo-secao">Assinaturas dos vendedores</h2>
            <div class="table-responsive mb-4">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead><tr><th>Vendedor</th><th>Plano</th><th>Situação</th><th>Desde</th><th>Próximo vencimento</th></tr></thead>
                    <tbody>
                        <?php foreach ($assinaturas as $a): ?>
                            <tr>
                                <td><?= e($a['nome']) ?></td>
                                <td class="text-nowrap"><?= e($a['plano'] ?? '—') ?> <span class="texto-secundario small">· <?= reais((float) $a['preco_mensal']) ?>/mês</span></td>
                                <td class="text-nowrap">
                                    <?php if ($a['sem_renovar']): ?>
                                        <span class="selo problema" title="Venceu e o Stripe não avisou a renovação">Vencida sem renovar</span>
                                    <?php elseif ($a['status_assinatura'] === 'ativo'): ?>
                                        <span class="selo ok">Ativa</span>
                                    <?php elseif ($a['status_assinatura'] === 'atrasado'): ?>
                                        <span class="selo problema">Pagamento atrasado</span>
                                    <?php else: ?>
                                        <span class="selo atencao"><?= e(ucfirst((string) ($a['status_assinatura'] ?: 'sem status'))) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="texto-secundario text-nowrap"><?= $a['data_inicio_assinatura'] ? date('d/m/Y', strtotime($a['data_inicio_assinatura'])) : '—' ?></td>
                                <td class="text-nowrap"><?= $a['data_vencimento_assinatura'] ? date('d/m/Y', strtotime($a['data_vencimento_assinatura'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$assinaturas): ?>
                            <tr><td colspan="5" class="text-center py-4 texto-vazio">Nenhum vendedor com plano pago ainda.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h2 class="titulo-secao">Eventos do Stripe</h2>
            <form method="GET" class="filtros-acessos row g-3 mb-3">
                <input type="hidden" name="aba" value="pagamentos">
                <div class="col-md-5">
                    <label class="form-label" for="statusPag">Resultado</label>
                    <select class="form-select" id="statusPag" name="status">
                        <option value="">Todos</option>
                        <?php foreach (STRIPE_STATUS as $chave => [$rotulo]): ?>
                            <option value="<?= e($chave) ?>" <?= $filtroStatus === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="periodoPag">Período</label>
                    <select class="form-select" id="periodoPag" name="periodo">
                        <?php foreach ($periodosPag as $dias => $rotulo): ?>
                            <option value="<?= $dias ?>" <?= $periodoPag === $dias ? 'selected' : '' ?>>Últimos <?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filtrar w-100">Filtrar</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead><tr><th>Data</th><th>Evento</th><th>Vendedor</th><th>Valor</th><th>Resultado</th><th>Detalhe</th></tr></thead>
                    <tbody>
                        <?php foreach ($eventosStripe as $ev): ?>
                            <?php [$rotuloStatus, $classeStatus] = STRIPE_STATUS[$ev['status']] ?? [$ev['status'], 'atencao']; ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($ev['data_hora'])) ?></td>
                                <td class="text-nowrap" title="<?= e($ev['tipo'] . ($ev['evento_id'] ? ' · ' . $ev['evento_id'] : '')) ?>"><?= e(STRIPE_EVENTOS[$ev['tipo']] ?? $ev['tipo']) ?></td>
                                <td><?= e($ev['vendedor_nome'] ?? ($ev['cliente_stripe'] ?: '—')) ?></td>
                                <td class="text-nowrap"><?= $ev['valor'] !== null ? reais((float) $ev['valor']) . ($ev['moeda'] && $ev['moeda'] !== 'BRL' ? ' ' . e($ev['moeda']) : '') : '—' ?></td>
                                <td class="text-nowrap"><span class="selo <?= e($classeStatus) ?>"><?= e($rotuloStatus) ?></span></td>
                                <td class="texto-secundario small"><?= e($ev['detalhe'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$eventosStripe): ?>
                            <tr><td colspan="6" class="text-center py-4 texto-vazio">Nenhum evento do Stripe nesse período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="texto-secundario small mt-3">
                Cada aviso que o Stripe manda ao site (cobrança paga ou recusada, nova assinatura, cancelamento) fica registrado aqui por 5 anos.
                "Não veio do Stripe" são chamadas sem a assinatura secreta do Stripe (robôs ou testes): o site as ignora e nada é alterado.
                Os detalhes completos de cada cobrança estão no painel do Stripe.
            </p>

        <?php elseif ($aba === 'erros'): ?>
            <p class="contagem-acessos">
                Erros registrados pelo PHP no site (mais recentes primeiro) · arquivo com <?= tamanho_legivel($tamanhoLogErros) ?>,
                trocado automaticamente ao passar de 5 MB. Linhas em vermelho são erros graves; em azul, erros de JavaScript
                no navegador dos usuários. A linha "↳" abaixo de cada erro mostra a página e quem estava logado.
            </p>
            <?php if ($linhasErro): ?>
                <div class="log-erros">
                    <?php foreach ($linhasErro as $linha): ?>
                        <div class="<?= preg_match('/PHP Fatal error|Uncaught|SQLSTATE/', $linha) ? 'grave' : (preg_match('/^\[[^\]]+\] JS: /', $linha) ? 'js' : '') ?>"><?= e($linha) ?></div>
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
<?php require __DIR__ . '/includes/layout_fim.php'; ?>
