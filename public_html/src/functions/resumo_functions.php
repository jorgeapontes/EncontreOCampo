<?php
// src/functions/resumo_functions.php
// Resumo semanal do site por e-mail para o master: uso (cadastros, anúncios,
// negociações), pagamentos, segurança, erros e espaço em disco dos últimos 7 dias,
// comparando com os 7 dias anteriores.
// Mandado pelo cron_limpar_banco toda segunda (se configuracoes_site.resumo_semanal
// não for "0") e pelo botão "Enviar o resumo semanal agora" das Configurações.

require_once __DIR__ . '/../conexao.php';

/** Conta de uma consulta com um "?" para a data inicial e outro para a final. */
function resumoContar(PDO $pdo, string $sql, int $inicio, int $fim): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([date('Y-m-d H:i:s', $inicio), date('Y-m-d H:i:s', $fim)]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Resumo semanal: ' . $e->getMessage());
        return 0;
    }
}

function resumoValor(PDO $pdo, string $sql)
{
    try {
        return $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log('Resumo semanal: ' . $e->getMessage());
        return null;
    }
}

/** Erros graves e de JavaScript no log de erros desde $inicio (lê só o fim do arquivo). */
function resumoErrosDoLog(int $inicio): array
{
    $arquivo = PASTA_PRIVADA . '/logs/erros_site.log';
    $graves = $js = $outros = 0;
    if (is_file($arquivo)) {
        $tamanho = filesize($arquivo);
        $trecho = (string) file_get_contents($arquivo, false, null, max(0, $tamanho - 3 * 1024 * 1024));
        preg_match_all('/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2})[^\]]*\] (.*)$/m', $trecho, $linhas, PREG_SET_ORDER);
        foreach ($linhas as [, $data, $texto]) {
            if (strtotime($data) < $inicio) {
                continue;
            }
            if (strpos($texto, 'JS: ') === 0) {
                $js++;
            } elseif (preg_match('/PHP Fatal error|Uncaught|SQLSTATE/', $texto)) {
                $graves++;
            } else {
                $outros++;
            }
        }
    }
    return ['graves' => $graves, 'js' => $js, 'outros' => $outros];
}

/** Monta o resumo: ['assunto' => ..., 'html' => ...]. */
function montarResumoSemanal(PDO $pdo): array
{
    $fim = time();
    $inicio = $fim - 7 * 86400;
    $antes = $inicio - 7 * 86400;
    // [rótulo, SQL com "BETWEEN ? AND ?" na data]: valor desta semana e da anterior
    $metricas = [
        'Uso do site' => [
            'Novos compradores'      => "SELECT COUNT(*) FROM usuarios WHERE tipo = 'comprador' AND data_criacao BETWEEN ? AND ?",
            'Novos vendedores'       => "SELECT COUNT(*) FROM usuarios WHERE tipo = 'vendedor' AND data_criacao BETWEEN ? AND ?",
            'Novos transportadores'  => "SELECT COUNT(*) FROM usuarios WHERE tipo = 'transportador' AND data_criacao BETWEEN ? AND ?",
            'Anúncios criados'       => "SELECT COUNT(*) FROM produtos WHERE data_criacao BETWEEN ? AND ?",
            'Conversas iniciadas'    => "SELECT COUNT(*) FROM chat_conversas WHERE data_criacao BETWEEN ? AND ?",
            'Mensagens trocadas'     => "SELECT COUNT(*) FROM chat_mensagens WHERE data_envio BETWEEN ? AND ?",
            'Negociações abertas'    => "SELECT COUNT(*) FROM propostas WHERE data_inicio BETWEEN ? AND ?",
            'Acordos fechados'       => "SELECT COUNT(*) FROM propostas WHERE status = 'aceita' AND data_atualizacao BETWEEN ? AND ?",
            'Entregas concluídas'    => "SELECT COUNT(*) FROM entregas WHERE status = 'entregue' AND data_entrega BETWEEN ? AND ?",
        ],
        'Pagamentos' => [
            'Pagamentos recebidos'   => "SELECT COUNT(*) FROM pagamentos_stripe WHERE tipo = 'invoice.paid' AND status = 'ok' AND data_hora BETWEEN ? AND ?",
            'Cobranças recusadas'    => "SELECT COUNT(*) FROM pagamentos_stripe WHERE status = 'falha' AND data_hora BETWEEN ? AND ?",
            'Erros ao processar'     => "SELECT COUNT(*) FROM pagamentos_stripe WHERE status = 'erro' AND data_hora BETWEEN ? AND ?",
        ],
        'Segurança' => [
            'Logins com sucesso'     => "SELECT COUNT(*) FROM log_acessos WHERE sucesso = 1 AND data_tentativa BETWEEN ? AND ?",
            'Senhas erradas'         => "SELECT COUNT(*) FROM log_acessos WHERE motivo_falha = 'senha_incorreta' AND data_tentativa BETWEEN ? AND ?",
            'Contas bloqueadas'      => "SELECT COUNT(*) FROM logs_auditoria WHERE acao = 'conta_bloqueada' AND data_hora BETWEEN ? AND ?",
            'Acessos negados'        => "SELECT COUNT(*) FROM logs_auditoria WHERE acao = 'acesso_negado' AND data_hora BETWEEN ? AND ?",
        ],
    ];

    $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
    $celula = 'padding:6px 10px;border-bottom:1px solid #eee;';
    $html = '';
    foreach ($metricas as $grupo => $itens) {
        $html .= "<h3 style=\"color:#1B5E20;margin:18px 0 6px\">{$e($grupo)}</h3>"
            . '<table style="border-collapse:collapse;width:100%;font-size:14px">'
            . "<tr><th style=\"text-align:left;{$celula}\"></th><th style=\"text-align:right;{$celula}\">7 dias</th><th style=\"text-align:right;{$celula}color:#888\">semana anterior</th></tr>";
        foreach ($itens as $rotulo => $sql) {
            $agora = resumoContar($pdo, $sql, $inicio, $fim);
            $passado = resumoContar($pdo, $sql, $antes, $inicio);
            $html .= "<tr><td style=\"{$celula}\">{$e($rotulo)}</td><td style=\"text-align:right;{$celula}font-weight:bold\">{$agora}</td>"
                . "<td style=\"text-align:right;{$celula}color:#888\">{$passado}</td></tr>";
        }
        $html .= '</table>';
    }

    // Situação agora
    $recebido = resumoValor($pdo, "SELECT COALESCE(SUM(valor), 0) FROM pagamentos_stripe WHERE tipo = 'invoice.paid' AND status = 'ok'
                                   AND data_hora >= '" . date('Y-m-d H:i:s', $inicio) . "'");
    $pendentes = resumoValor($pdo, "SELECT COUNT(*) FROM solicitacoes_cadastro WHERE status = 'pendente'");
    $pendenteMaisAntigo = resumoValor($pdo, "SELECT MIN(data_solicitacao) FROM solicitacoes_cadastro WHERE status = 'pendente'");
    $anunciosAtivos = resumoValor($pdo, "SELECT COUNT(*) FROM produtos WHERE status = 'ativo'");
    $assinaturasAtivas = resumoValor($pdo, "SELECT COUNT(*) FROM vendedores v JOIN planos p ON p.id = v.plano_id WHERE p.preco_mensal > 0 AND v.status_assinatura = 'ativo'");
    $atrasadas = resumoValor($pdo, "SELECT COUNT(*) FROM vendedores WHERE status_assinatura = 'atrasado'");
    $cronsComAlerta = resumoContar($pdo, "SELECT COUNT(*) FROM cron_execucoes WHERE status <> 'ok' AND data_hora BETWEEN ? AND ?", $inicio, $fim);
    $erros = resumoErrosDoLog($inicio);
    $disco = null;
    try {
        $disco = $pdo->query("SELECT tamanho_banco, tamanho_uploads, tamanho_backups, ultimo_backup FROM cron_execucoes
                              WHERE cron = 'cron_limpar_banco' AND tamanho_banco IS NOT NULL ORDER BY data_hora DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $ex) {
    }
    $mb = fn ($bytes) => $bytes === null ? '—'
        : ($bytes < 1048576 ? max(1, round($bytes / 1024)) . ' KB' : number_format($bytes / 1048576, 1, ',', '.') . ' MB');

    $situacao = [
        'Recebido nos 7 dias'        => 'R$ ' . number_format((float) $recebido, 2, ',', '.'),
        'Assinaturas pagas ativas'   => (int) $assinaturasAtivas . ($atrasadas ? " · {$atrasadas} atrasada(s)" : ''),
        'Anúncios ativos'            => (int) $anunciosAtivos,
        'Cadastros esperando análise' => (int) $pendentes . ($pendenteMaisAntigo ? ' · o mais antigo de ' . date('d/m', strtotime($pendenteMaisAntigo)) : ''),
        'Erros graves no site'       => $erros['graves'] . ' · outros avisos: ' . $erros['outros'] . ' · JavaScript: ' . $erros['js'],
        'Execuções de cron com alerta' => $cronsComAlerta,
        'Espaço em disco'            => $disco ? 'banco ' . $mb($disco['tamanho_banco']) . ' · uploads ' . $mb($disco['tamanho_uploads']) . ' · backups ' . $mb($disco['tamanho_backups']) : 'sem medição ainda',
        'Último backup'              => !empty($disco['ultimo_backup']) ? date('d/m/Y', strtotime($disco['ultimo_backup'])) : 'nenhum',
    ];
    $tabelaSituacao = '<h3 style="color:#1B5E20;margin:18px 0 6px">Situação agora</h3><table style="border-collapse:collapse;width:100%;font-size:14px">';
    foreach ($situacao as $rotulo => $valor) {
        $tabelaSituacao .= "<tr><td style=\"{$celula}\">{$e($rotulo)}</td><td style=\"text-align:right;{$celula}font-weight:bold\">{$e($valor)}</td></tr>";
    }
    $tabelaSituacao .= '</table>';

    $periodo = date('d/m', $inicio) . ' a ' . date('d/m/Y', $fim);
    $atencao = [];
    if ($erros['graves'] > 0) {
        $atencao[] = "{$erros['graves']} erro(s) grave(s) no site";
    }
    if ($cronsComAlerta > 0) {
        $atencao[] = "{$cronsComAlerta} execução(ões) de cron com alerta";
    }
    if ($atrasadas) {
        $atencao[] = "{$atrasadas} assinatura(s) atrasada(s)";
    }
    $aviso = $atencao
        ? '<p style="background:#fff1e6;padding:10px;border-radius:6px">Precisa de atenção: ' . $e(implode('; ', $atencao)) . '. Detalhes no painel do master.</p>'
        : '<p style="background:#e8f5ee;padding:10px;border-radius:6px">Nada fora do normal na semana.</p>';

    return [
        'assunto' => "[Encontre o Campo] Resumo da semana ({$periodo})",
        'html'    => "Resumo do site de {$periodo}.{$aviso}{$tabelaSituacao}{$html}"
            . '<p style="color:#888;font-size:12px;margin-top:18px">Para não receber mais, desmarque o resumo semanal em Configurações, no painel do master.</p>',
    ];
}

function enviarResumoSemanal(PDO $pdo, string $destino): bool
{
    try {
        require_once __DIR__ . '/../../includes/send_notification.php';
        $resumo = montarResumoSemanal($pdo);
        return (bool) enviarEmailNotificacao($destino, 'Master', $resumo['assunto'], $resumo['html']);
    } catch (Throwable $e) {
        error_log('Resumo semanal: falha ao enviar: ' . $e->getMessage());
        return false;
    }
}
