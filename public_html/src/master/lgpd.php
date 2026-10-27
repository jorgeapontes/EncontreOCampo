<?php
/**
 * Pedidos da LGPD (art. 18, direito de acesso): acha a conta do titular e gera um
 * arquivo JSON com os dados pessoais que o site guarda sobre ele. Cada exportação
 * fica na Auditoria (com o protocolo/observação), como registro do atendimento.
 *
 * Entram só registros que com certeza são do titular. Ficam de fora: senhas,
 * tokens e o hash da senha do pedido de cadastro; o texto das mensagens que ele
 * RECEBEU (são dados de outra pessoa; vai só a contagem); e o conteúdo dos
 * arquivos (fotos/documentos), dos quais vai só a indicação de que existem.
 */
require_once __DIR__ . '/includes/comum.php';

/** Nome de cada seção do arquivo na tela. */
const LGPD_SECOES = [
    'perfil'                   => 'Perfil',
    'pedido_de_cadastro'       => 'Pedido de cadastro',
    'negociacoes'              => 'Negociações',
    'assinaturas_de_acordo'    => 'Assinaturas de acordo',
    'conversas'                => 'Conversas',
    'mensagens_enviadas'       => 'Mensagens enviadas',
    'favoritos'                => 'Favoritos',
    'avaliacoes_feitas'        => 'Avaliações feitas',
    'notificacoes'             => 'Notificações',
    'preferencias'             => 'Preferências',
    'historico_de_logins'      => 'Histórico de logins',
    'anuncios'                 => 'Anúncios',
    'pagamentos_da_assinatura' => 'Pagamentos da assinatura',
    'propostas_de_frete'       => 'Propostas de frete',
    'entregas'                 => 'Entregas',
    'fretes_favoritos'         => 'Fretes favoritos',
];

/** Linhas de uma consulta, sem as colunas sensíveis; tabela que não existe vira lista vazia. */
function lgpdLinhas(PDO $pdo, string $sql, array $params): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('LGPD: ' . $e->getMessage());
        return [];
    }
    foreach ($linhas as &$linha) {
        foreach (array_keys($linha) as $coluna) {
            if (preg_match('/senha|token|password|assinatura_hash/i', $coluna)) {
                unset($linha[$coluna]);
            }
        }
    }
    return $linhas;
}

/** Todos os dados pessoais do titular, organizados por assunto. */
function lgpdDadosDoTitular(PDO $pdo, int $id): array
{
    $conta = lgpdLinhas($pdo, "SELECT * FROM usuarios WHERE id = ?", [$id])[0] ?? null;
    if (!$conta) {
        return [];
    }
    foreach (['foto_rosto', 'foto_documento_frente', 'foto_documento_verso'] as $foto) {
        $conta[$foto] = !empty($conta[$foto]) ? 'arquivo guardado (enviado no cadastro)' : 'não guardado';
    }
    unset($conta['tentativas_falhas'], $conta['bloqueado_ate']);

    $tabelasPerfil = ['comprador' => 'compradores', 'vendedor' => 'vendedores', 'transportador' => 'transportadores'];
    $perfil = isset($tabelasPerfil[$conta['tipo']])
        ? (lgpdLinhas($pdo, "SELECT * FROM {$tabelasPerfil[$conta['tipo']]} WHERE usuario_id = ?", [$id])[0] ?? null)
        : null;
    $perfilId = (int) ($perfil['id'] ?? 0);
    if ($perfil && !empty($perfil['foto_perfil_url'])) {
        $perfil['foto_perfil_url'] = 'foto de perfil guardada';
    }

    $solicitacoes = lgpdLinhas($pdo, "SELECT * FROM solicitacoes_cadastro WHERE usuario_id = ? OR email = ?", [$id, $conta['email']]);
    foreach ($solicitacoes as &$s) {
        $json = json_decode((string) ($s['dados_json'] ?? ''), true);
        if (is_array($json)) {
            $json = array_filter($json, fn ($chave) => !preg_match('/senha|password|token|csrf/i', (string) $chave), ARRAY_FILTER_USE_KEY);
        }
        $s['dados_json'] = $json;
    }
    unset($s);

    $dados = [
        'sobre_este_arquivo' => [
            'gerado_em'    => date('d/m/Y H:i'),
            'controlador'  => 'Encontre o Campo (encontreocampo.com.br)',
            'contato'      => 'privacidade@encontreocampo.com.br',
            'observacao'   => 'Dados pessoais que o site guarda sobre o titular (LGPD, art. 18). Fotos e documentos não vão no arquivo: '
                . 'aparece só se estão guardados. Das mensagens de chat, vão as que o titular enviou.',
        ],
        'conta'                 => $conta,
        'perfil'                => $perfil,
        'pedido_de_cadastro'    => $solicitacoes,
        'negociacoes'           => lgpdLinhas($pdo, "SELECT p.*, pr.nome AS produto FROM propostas p LEFT JOIN produtos pr ON pr.id = p.produto_id
                                                     WHERE p.comprador_id = ? OR p.vendedor_id = ?", [$id, $id]),
        'assinaturas_de_acordo' => lgpdLinhas($pdo, "SELECT proposta_id, ip_address, user_agent, data_assinatura FROM propostas_assinaturas WHERE usuario_id = ?", [$id]),
        'conversas'             => lgpdLinhas($pdo, "SELECT c.id, pr.nome AS produto, c.data_criacao,
                                                       CASE WHEN c.comprador_id = ? THEN 'comprador' WHEN c.vendedor_id = ? THEN 'vendedor' ELSE 'transportador' END AS papel_do_titular,
                                                       (SELECT COUNT(*) FROM chat_mensagens m WHERE m.conversa_id = c.id AND m.remetente_id <> ?) AS mensagens_recebidas
                                                     FROM chat_conversas c LEFT JOIN produtos pr ON pr.id = c.produto_id
                                                     WHERE c.comprador_id = ? OR c.vendedor_id = ? OR c.transportador_id = ?", [$id, $id, $id, $id, $id, $id]),
        'mensagens_enviadas'    => lgpdLinhas($pdo, "SELECT conversa_id, mensagem, tipo, anexo_url, data_envio FROM chat_mensagens WHERE remetente_id = ? ORDER BY data_envio", [$id]),
        'favoritos'             => lgpdLinhas($pdo, "SELECT f.data_criacao, pr.nome AS produto FROM favoritos f LEFT JOIN produtos pr ON pr.id = f.produto_id WHERE f.usuario_id = ?", [$id]),
        'avaliacoes_feitas'     => lgpdLinhas($pdo, "SELECT nota, comentario, tipo, data_criacao FROM avaliacoes WHERE avaliador_usuario_id = ?", [$id]),
        'notificacoes'          => lgpdLinhas($pdo, "SELECT mensagem, tipo, lida, data_criacao FROM notificacoes WHERE usuario_id = ?", [$id]),
        'preferencias'          => lgpdLinhas($pdo, "SELECT * FROM usuario_avisos_preferencias WHERE usuario_id = ?", [$id]),
        'historico_de_logins'   => lgpdLinhas($pdo, "SELECT data_tentativa, ip, user_agent, sucesso, motivo_falha FROM log_acessos WHERE usuario_id = ? ORDER BY data_tentativa", [$id]),
    ];

    if ($conta['tipo'] === 'vendedor' && $perfilId) {
        $dados['anuncios'] = lgpdLinhas($pdo, "SELECT * FROM produtos WHERE vendedor_id = ?", [$perfilId]);
        $dados['pagamentos_da_assinatura'] = lgpdLinhas($pdo, "SELECT data_hora, tipo, status, valor, moeda FROM pagamentos_stripe WHERE vendedor_id = ? ORDER BY data_hora", [$perfilId]);
    }
    if ($conta['tipo'] === 'transportador' && $perfilId) {
        $dados['propostas_de_frete'] = lgpdLinhas($pdo, "SELECT * FROM propostas_transportadores WHERE transportador_id = ?", [$perfilId]);
        $dados['entregas'] = lgpdLinhas($pdo, "SELECT * FROM entregas WHERE transportador_id = ?", [$perfilId]);
        $dados['fretes_favoritos'] = lgpdLinhas($pdo, "SELECT proposta_id, data_criacao FROM transportador_favoritos WHERE transportador_id = ?", [$perfilId]);
    }
    return $dados;
}

// ===== Exportar (download do JSON) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'exportar') {
    $id = (int) ($_POST['usuario_id'] ?? 0);
    $protocolo = trim((string) ($_POST['protocolo'] ?? ''));
    $stmt = $pdo->prepare("SELECT nome, tipo FROM usuarios WHERE id = ? AND tipo <> 'master'");
    $stmt->execute([$id]);
    $titular = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!csrfOk() || !$titular) {
        avisar('danger', 'Não foi possível exportar: conta não encontrada ou requisição inválida.');
        header('Location: lgpd');
        exit;
    }
    $dados = lgpdDadosDoTitular($pdo, $id);
    registrarAuditoria('lgpd_exportacao', "Exportou os dados de {$titular['nome']} ({$titular['tipo']}) a pedido do titular"
        . ($protocolo !== '' ? " · {$protocolo}" : ''), 'usuario', $id);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="dados_pessoais_usuario_' . $id . '_' . date('Y-m-d') . '.json"');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ===== Busca do titular =====
$busca = trim((string) ($_GET['busca'] ?? ''));
$encontrados = [];
if (mb_strlen($busca) >= 3) {
    $stmt = $pdo->prepare("SELECT id, nome, email, tipo, status, data_criacao FROM usuarios
                           WHERE tipo <> 'master' AND (email LIKE ? OR nome LIKE ?) ORDER BY nome LIMIT 20");
    $stmt->execute(["%{$busca}%", "%{$busca}%"]);
    $encontrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$selecionado = null;
$resumoDados = [];
if (($idSelecionado = (int) ($_GET['usuario_id'] ?? 0)) > 0) {
    $dadosPrevia = lgpdDadosDoTitular($pdo, $idSelecionado);
    if ($dadosPrevia && ($dadosPrevia['conta']['tipo'] ?? '') !== 'master') {
        $selecionado = $dadosPrevia['conta'];
        foreach ($dadosPrevia as $secao => $conteudo) {
            if ($secao !== 'sobre_este_arquivo' && $secao !== 'conta') {
                $resumoDados[$secao] = is_array($conteudo) ? (array_is_list($conteudo) ? count($conteudo) : 1) : ($conteudo ? 1 : 0);
            }
        }
    }
}

$historico = $pdo->query("SELECT data_hora, usuario_nome, descricao FROM logs_auditoria WHERE acao = 'lgpd_exportacao' ORDER BY data_hora DESC LIMIT 30")
    ->fetchAll(PDO::FETCH_ASSOC);

$mensagem = mensagemPendente();
$paginaAtiva = 'lgpd';
$tituloPagina = 'LGPD';
require __DIR__ . '/includes/layout_inicio.php';
?>
            <p class="texto-secundario mb-3">
                Quando alguém pedir os dados que o site guarda sobre ela (pelo e-mail de privacidade), encontre a conta aqui e gere o arquivo.
                Confira antes que o pedido veio do próprio titular (o e-mail do pedido deve ser o da conta). A LGPD dá até 15 dias para responder.
            </p>

            <form method="GET" class="filtros-acessos row g-3 mb-4">
                <div class="col-md-10">
                    <label class="form-label" for="buscaTitular">E-mail ou nome do titular</label>
                    <input type="text" class="form-control" id="buscaTitular" name="busca" value="<?= e($busca) ?>" minlength="3" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filtrar w-100">Buscar</button>
                </div>
            </form>

            <?php if ($busca !== ''): ?>
                <div class="table-responsive mb-4">
                    <table class="table tabela-acessos tabela-cartoes">
                        <thead><tr><th>Conta</th><th>Perfil</th><th>Desde</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($encontrados as $u): ?>
                                <tr>
                                    <td><?= e($u['nome']) ?><br><span class="texto-secundario small"><?= e($u['email']) ?></span></td>
                                    <td class="text-nowrap"><?= e($u['tipo']) ?><?= $u['status'] !== 'ativo' ? ' <span class="texto-secundario small">· ' . e($u['status']) . '</span>' : '' ?></td>
                                    <td class="texto-secundario text-nowrap"><?= $u['data_criacao'] ? date('d/m/Y', strtotime($u['data_criacao'])) : '—' ?></td>
                                    <td class="td-acoes text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="lgpd?busca=<?= urlencode($busca) ?>&usuario_id=<?= (int) $u['id'] ?>">Ver dados</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$encontrados): ?>
                                <tr><td colspan="4" class="text-center py-4 texto-vazio">Nenhuma conta encontrada (busque com pelo menos 3 letras).</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($selecionado): ?>
                <h2 class="titulo-secao">Dados de <?= e($selecionado['nome']) ?></h2>
                <ul class="lista-simples mb-3">
                    <?php foreach ($resumoDados as $secao => $quantidade): ?>
                        <li><?= e(LGPD_SECOES[$secao] ?? ucfirst(str_replace('_', ' ', $secao))) ?> <span class="texto-secundario">· <?= (int) $quantidade ?> registro(s)</span></li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST" class="filtros-acessos row g-3 mb-4">
                    <input type="hidden" name="acao" value="exportar"><?= campoCsrf() ?>
                    <input type="hidden" name="usuario_id" value="<?= (int) $selecionado['id'] ?>">
                    <div class="col-md-8">
                        <label class="form-label" for="protocolo">Protocolo ou observação do pedido (opcional)</label>
                        <input type="text" class="form-control" id="protocolo" name="protocolo" maxlength="120" placeholder="ex.: pedido por e-mail de 27/10">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-filtrar w-100"><i class="fas fa-file-download me-1"></i> Baixar os dados (JSON)</button>
                    </div>
                </form>
                <p class="texto-secundario small mb-4">
                    <i class="fas fa-lock"></i> O arquivo tem dados pessoais: mande só para o e-mail da própria conta e apague a sua cópia depois.
                    Para excluir a conta, o próprio titular pode fazer pelo perfil (os dados são anonimizados).
                </p>
            <?php endif; ?>

            <h2 class="titulo-secao">Pedidos atendidos</h2>
            <div class="table-responsive">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead><tr><th>Data</th><th>Quem exportou</th><th>Descrição</th></tr></thead>
                    <tbody>
                        <?php foreach ($historico as $h): ?>
                            <tr>
                                <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($h['data_hora'])) ?></td>
                                <td><?= e($h['usuario_nome']) ?></td>
                                <td><?= e($h['descricao']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$historico): ?>
                            <tr><td colspan="3" class="text-center py-4 texto-vazio">Nenhum pedido atendido ainda.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
<?php require __DIR__ . '/includes/layout_fim.php'; ?>
