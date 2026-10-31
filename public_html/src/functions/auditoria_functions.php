<?php
// src/functions/auditoria_functions.php
// Trilha de auditoria: registra quem fez o quê, em qual registro e quando.
//
// Uso: registrarAuditoria('cadastro_aprovado', 'Aprovou o cadastro de Maria (vendedor)', 'usuario', 12);
//
// Regras:
//   - Nunca grava senhas, tokens nem conteúdo (texto de mensagens, documentos):
//     só a descrição da ação.
//   - O IP é gravado ofuscado (LGPD). O IP completo do login continua em
//     log_acessos, que o Marco Civil exige por 6 meses.
//   - Se a gravação falhar, a ação do usuário continua normalmente: o erro vai
//     só para o log de erros.
//   - Os registros ficam 1 ano (limpeza no cron_limpar_banco).

require_once __DIR__ . '/../conexao.php';

/** Rótulos das ações (usados na tela de monitoramento), agrupados por categoria. */
const AUDITORIA_ACOES = [
    'Cadastros e usuários' => [
        'cadastro_aprovado'    => 'Cadastro aprovado',
        'cadastro_rejeitado'   => 'Cadastro rejeitado',
        'usuario_status'       => 'Usuário ativado/desativado',
        'documento_visto'      => 'Documento de identidade visualizado',
        'conta_excluida'       => 'Conta excluída pelo usuário',
        'email_alterado'       => 'E-mail da conta alterado',
    ],
    'Chats' => [
        'chat_visualizado'     => 'Conversa aberta pelo admin',
        'chat_exportado'       => 'Conversa exportada em PDF',
        'chat_excluido'        => 'Conversa excluída pelo admin',
    ],
    'Anúncios' => [
        'anuncio_excluido'     => 'Anúncio excluído',
    ],
    'Senhas e segurança' => [
        'senha_alterada'       => 'Senha alterada',
        'senha_redefinida'     => 'Senha redefinida (esqueci a senha)',
        'reset_solicitado'     => 'Redefinição de senha solicitada',
        'conta_bloqueada'      => 'Conta bloqueada por tentativas',
        'acesso_negado'        => 'Acesso negado',
        'backup_baixado'       => 'Backup baixado',
        'conta_desbloqueada'   => 'Conta desbloqueada pelo master',
        'ip_desbloqueado'      => 'IP desbloqueado pelo master',
        'ip_bloqueado'         => 'IP bloqueado no site pelo master',
        'sessoes_encerradas'   => 'Sessões encerradas pelo master',
    ],
    'Administradores' => [
        'admin_criado'         => 'Admin criado',
        'admin_status'         => 'Admin ativado/desativado',
        'admin_link_senha'     => 'Link de senha enviado ao admin',
        'admin_senha_definida' => 'Senha do admin definida pelo master',
    ],
    'Pagamentos' => [
        'pagamento_conferido'  => 'Assinaturas conferidas com o Stripe',
        'pagamento_corrigido'  => 'Assinatura corrigida pelo Stripe',
    ],
    'LGPD' => [
        'lgpd_exportacao'      => 'Dados exportados a pedido do titular',
    ],
];

/** Conexão própria, reaproveitada entre chamadas na mesma requisição. */
function auditoriaConexao(): ?PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = (new Database())->getConnection();
    }
    return $pdo;
}

/** Ofusca o IP para a auditoria (LGPD): 189.40.22.10 → 189.40.*.* ; IPv6 mantém só os 4 primeiros grupos. */
function auditoriaIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return PHP_SAPI === 'cli' ? 'cron' : '';
    }
    if (strpos($ip, ':') === false) {
        $partes = explode('.', $ip);
        return $partes[0] . '.' . $partes[1] . '.*.*';
    }
    // Expande o IPv6 (sem "::") antes de cortar, para nunca mostrar a parte que identifica o aparelho
    $grupos = array_map(fn ($g) => ltrim($g, '0') ?: '0', str_split(bin2hex(inet_pton($ip)), 4));
    return implode(':', array_slice($grupos, 0, 4)) . ':****:****:****:****';
}

/**
 * Registra uma ação. Usuário, nome e perfil vêm da sessão quando não informados
 * (informe-os em ações feitas sem login, como "esqueci a senha").
 */
function registrarAuditoria(string $acao, string $descricao, ?string $entidade = null, $entidadeId = null,
                            ?int $usuarioId = null, ?string $usuarioNome = null, ?string $perfil = null): void
{
    try {
        $pdo = auditoriaConexao();
        if (!$pdo) {
            throw new RuntimeException('sem conexão com o banco');
        }
        if ($usuarioId === null && isset($_SESSION['usuario_id'])) {
            $usuarioId = (int) $_SESSION['usuario_id'];
            $usuarioNome = $usuarioNome ?? ($_SESSION['usuario_nome'] ?? null);
            $perfil = $perfil ?? ($_SESSION['usuario_tipo'] ?? null);
        }
        $stmt = $pdo->prepare(
            "INSERT INTO logs_auditoria (data_hora, usuario_id, usuario_nome, perfil, acao, entidade, entidade_id, descricao, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            date('Y-m-d H:i:s'),
            $usuarioId,
            $usuarioNome !== null ? mb_substr($usuarioNome, 0, 100) : null,
            $perfil,
            $acao,
            $entidade,
            $entidadeId !== null && $entidadeId !== '' ? (int) $entidadeId : null,
            mb_substr($descricao, 0, 255),
            auditoriaIp(),
        ]);
    } catch (Throwable $e) {
        error_log('Auditoria: falha ao registrar "' . $acao . '": ' . $e->getMessage());
    }
}

/** "Maria (vendedor)" — para descrever o alvo da ação antes de ele ser alterado. */
function auditoriaUsuario(int $id): string
{
    try {
        $stmt = auditoriaConexao()->prepare("SELECT nome, tipo FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        return $u ? $u['nome'] . ' (' . $u['tipo'] . ')' : "#{$id}";
    } catch (Throwable $e) {
        return "#{$id}";
    }
}

/** E-mail atual da conta (ler ANTES do UPDATE, para comparar depois com auditoriaEmailAlterado). */
function auditoriaEmailAtual(int $usuarioId): string
{
    try {
        $stmt = auditoriaConexao()->prepare("SELECT email FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        return (string) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return '';
    }
}

/** "jorge@gmail.com" → "jo***@gmail.com" (a auditoria guarda 1 ano; o e-mail completo não precisa). */
function auditoriaMascararEmail(string $email): string
{
    [$nome, $dominio] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($nome, 0, 2) . '***@' . $dominio;
}

/** Registra a troca de e-mail feita pelo próprio usuário no perfil (chamar depois do commit). */
function auditoriaEmailAlterado(int $usuarioId, string $anterior, string $novo): void
{
    if ($anterior === '' || strcasecmp(trim($anterior), trim($novo)) === 0) {
        return;
    }
    registrarAuditoria('email_alterado', 'Trocou o e-mail da conta de ' . auditoriaMascararEmail($anterior)
        . ' para ' . auditoriaMascararEmail($novo), 'usuario', $usuarioId);
}

/** Registra a tentativa de abrir uma área sem permissão (chamar antes do redirecionamento). */
function auditoriaAcessoNegado(string $area): void
{
    $pagina = basename($_SERVER['SCRIPT_FILENAME'] ?? '', '.php');
    registrarAuditoria('acesso_negado', "Tentou abrir {$area}/{$pagina} sem permissão", 'pagina');
}
