<?php
/**
 * Administradores do site — gestão pelo master: criar, ativar/desativar, mandar
 * link para definir a senha e encerrar as sessões de um admin.
 * O master nunca digita nem vê a senha de um admin: o admin recebe por e-mail um
 * link (válido por 48 h) para criar a própria senha, pelo mesmo fluxo do
 * "esqueci a senha" (includes/reset_password.php).
 */
require_once __DIR__ . '/includes/comum.php';

const ADMIN_LINK_HORAS = 48;
const ADMIN_SENHA_MINIMA = 8;

/** Gera um link novo para o admin definir a senha e tenta mandar por e-mail. Retorna [enviou, link]. */
function mandarLinkSenhaAdmin(PDO $pdo, array $admin, bool $novo): array
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expira = ? WHERE id = ? AND tipo = 'admin'")
        ->execute([$token, date('Y-m-d H:i:s', time() + ADMIN_LINK_HORAS * 3600), $admin['id']]);
    $link = urlDoSite() . '/includes/reset_password.php?token=' . $token;

    $texto = ($novo
            ? 'Você foi cadastrado como <strong>administrador</strong> do Encontre o Campo.'
            : 'Foi pedido um novo link para você definir a sua senha de administrador do Encontre o Campo.')
        . '<br><br>Para criar a sua senha, acesse: <a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a>'
        . '<br><br>O link vale por ' . ADMIN_LINK_HORAS . ' horas. Se você não esperava este e-mail, ignore-o.';
    require_once __DIR__ . '/../../includes/send_notification.php';
    $enviou = (bool) enviarEmailNotificacao($admin['email'], $admin['nome'], '[Encontre o Campo] Defina sua senha de administrador', $texto);
    return [$enviou, $link];
}

/** Valida a senha escolhida pelo master; retorna a mensagem de erro ou null se estiver boa. */
function erroNaSenhaAdmin(string $senha, string $confirmacao): ?string
{
    if (strlen($senha) < ADMIN_SENHA_MINIMA) {
        return 'A senha deve ter pelo menos ' . ADMIN_SENHA_MINIMA . ' caracteres.';
    }
    if ($senha !== $confirmacao) {
        return 'A confirmação não confere com a senha.';
    }
    return null;
}

// ===== Ações =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    if (!csrfOk()) {
        avisar('danger', 'Requisição inválida. Tente novamente.');
    } elseif ($acao === 'criar') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $stmt = $pdo->prepare("SELECT tipo FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $existente = $stmt->fetchColumn();
        $erroSenha = erroNaSenhaAdmin((string) ($_POST['senha'] ?? ''), (string) ($_POST['confirma_senha'] ?? ''));
        if (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            avisar('danger', 'Informe o nome e um e-mail válido.');
        } elseif ($existente !== false) {
            avisar('danger', "Já existe uma conta ({$existente}) com esse e-mail. Use outro e-mail para o admin.");
        } elseif ($erroSenha) {
            avisar('danger', $erroSenha);
        } else {
            // A senha é escolhida pelo master; a auditoria registra a criação, nunca a senha
            $pdo->prepare("INSERT INTO usuarios (email, senha, tipo, nome, status, data_criacao) VALUES (?, ?, 'admin', ?, 'ativo', NOW())")
                ->execute([$email, password_hash((string) $_POST['senha'], PASSWORD_DEFAULT), mb_substr($nome, 0, 100)]);
            $novoId = (int) $pdo->lastInsertId();
            registrarAuditoria('admin_criado', "Criou o admin {$nome} ({$email}) com senha definida pelo master", 'usuario', $novoId);
            avisar('success', "Admin {$nome} criado. Passe a ele o e-mail e a senha que você definiu; ele já pode entrar pelo login do site.");
        }
    } else {
        $id = (int) ($_POST['usuario_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, nome, email, status FROM usuarios WHERE id = ? AND tipo = 'admin'");
        $stmt->execute([$id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            avisar('danger', 'Admin não encontrado.');
        } elseif ($acao === 'status') {
            $novo = $admin['status'] === 'ativo' ? 'inativo' : 'ativo';
            $pdo->prepare("UPDATE usuarios SET status = ? WHERE id = ? AND tipo = 'admin'")->execute([$novo, $id]);
            if ($novo === 'inativo') {
                encerrarSessoesUsuario($id); // sai do painel na hora
            }
            registrarAuditoria('admin_status', ($novo === 'ativo' ? 'Reativou' : 'Desativou') . " o admin {$admin['nome']}", 'usuario', $id);
            avisar('success', $novo === 'ativo' ? "{$admin['nome']} voltou a ter acesso ao painel do admin." : "{$admin['nome']} foi desativado e saiu do painel.");
        } elseif ($acao === 'definir_senha') {
            $erroSenha = erroNaSenhaAdmin((string) ($_POST['senha'] ?? ''), (string) ($_POST['confirma_senha'] ?? ''));
            if ($erroSenha) {
                avisar('danger', $erroSenha);
            } else {
                // Senha nova: invalida links pendentes, zera o bloqueio por tentativas e tira as sessões abertas
                $pdo->prepare("UPDATE usuarios SET senha = ?, reset_token = NULL, reset_token_expira = NULL, tentativas_falhas = 0, bloqueado_ate = NULL
                               WHERE id = ? AND tipo = 'admin'")->execute([password_hash((string) $_POST['senha'], PASSWORD_DEFAULT), $id]);
                encerrarSessoesUsuario($id);
                registrarAuditoria('admin_senha_definida', "Definiu uma nova senha para o admin {$admin['nome']}", 'usuario', $id);
                avisar('success', "Senha de {$admin['nome']} trocada. Ele saiu do painel e entra com a senha nova; passe-a para ele.");
            }
        } elseif ($acao === 'link_senha') {
            [$enviou, $link] = mandarLinkSenhaAdmin($pdo, $admin, false);
            registrarAuditoria('admin_link_senha', "Mandou link de definição de senha para o admin {$admin['nome']}", 'usuario', $id);
            avisar($enviou ? 'success' : 'warning', $enviou
                ? "Link para definir a senha enviado para {$admin['email']}."
                : "O e-mail não saiu. Mande este link para {$admin['nome']} (vale " . ADMIN_LINK_HORAS . " h): {$link}");
        } elseif ($acao === 'encerrar') {
            encerrarSessoesUsuario($id);
            registrarAuditoria('sessoes_encerradas', "Encerrou as sessões do admin {$admin['nome']}", 'usuario', $id);
            avisar('success', "Sessões de {$admin['nome']} encerradas: ele terá de entrar de novo.");
        }
    }
    header('Location: admins');
    exit;
}

// ===== Lista =====
$admins = $pdo->query(
    "SELECT u.id, u.nome, u.email, u.status, u.data_criacao,
            (SELECT MAX(l.data_tentativa) FROM log_acessos l WHERE l.usuario_id = u.id AND l.sucesso = 1) AS ultimo_login,
            (SELECT COUNT(*) FROM logs_auditoria a WHERE a.usuario_id = u.id AND a.data_hora >= NOW() - INTERVAL 30 DAY) AS acoes_30d
     FROM usuarios u WHERE u.tipo = 'admin' ORDER BY u.status = 'ativo' DESC, u.nome"
)->fetchAll(PDO::FETCH_ASSOC);

$mensagem = mensagemPendente();
$paginaAtiva = 'admins';
$tituloPagina = 'Administradores';
require __DIR__ . '/includes/layout_inicio.php';
?>
            <div class="table-responsive mb-4">
                <table class="table tabela-acessos tabela-cartoes">
                    <thead><tr><th>Admin</th><th>Situação</th><th>Último login</th><th>Ações (30 dias)</th><th>Ações</th></tr></thead>
                    <tbody>
                        <?php foreach ($admins as $a): ?>
                            <tr>
                                <td><?= e($a['nome']) ?><br><span class="texto-secundario small"><?= e($a['email']) ?></span></td>
                                <td class="text-nowrap"><span class="selo <?= $a['status'] === 'ativo' ? 'ok' : 'problema' ?>"><?= $a['status'] === 'ativo' ? 'Ativo' : 'Desativado' ?></span></td>
                                <td class="text-nowrap"><?= $a['ultimo_login'] ? date('d/m/Y H:i', strtotime($a['ultimo_login'])) : '<span class="texto-secundario">nunca</span>' ?></td>
                                <td>
                                    <a href="monitoramento?aba=auditoria&periodo=90&busca=<?= urlencode($a['nome']) ?>"><?= (int) $a['acoes_30d'] ?> registro(s)</a>
                                </td>
                                <td class="td-acoes">
                                    <div class="d-flex flex-wrap gap-1">
                                        <form method="POST">
                                            <input type="hidden" name="acao" value="status"><?= campoCsrf() ?>
                                            <input type="hidden" name="usuario_id" value="<?= (int) $a['id'] ?>">
                                            <?php if ($a['status'] === 'ativo'): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm(<?= e(json_encode('Desativar ' . $a['nome'] . '? Ele sai do painel na hora.')) ?>)"><i class="fas fa-user-lock"></i> Desativar</button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-user-check"></i> Reativar</button>
                                            <?php endif; ?>
                                        </form>
                                        <details class="definir-senha w-100">
                                            <summary class="btn btn-sm btn-outline-secondary"><i class="fas fa-key"></i> Definir senha</summary>
                                            <form method="POST" class="d-flex flex-wrap gap-1 mt-2" autocomplete="off">
                                                <input type="hidden" name="acao" value="definir_senha"><?= campoCsrf() ?>
                                                <input type="hidden" name="usuario_id" value="<?= (int) $a['id'] ?>">
                                                <input type="password" class="form-control form-control-sm campo-senha" name="senha" minlength="<?= ADMIN_SENHA_MINIMA ?>" placeholder="Nova senha" autocomplete="new-password" required aria-label="Nova senha">
                                                <input type="password" class="form-control form-control-sm campo-senha" name="confirma_senha" minlength="<?= ADMIN_SENHA_MINIMA ?>" placeholder="Confirmar" autocomplete="new-password" required aria-label="Confirmar senha">
                                                <button type="button" class="btn btn-sm btn-outline-secondary gerar-senha" title="Gera uma senha forte e mostra para você copiar"><i class="fas fa-magic"></i> Gerar</button>
                                                <button type="submit" class="btn btn-sm btn-filtrar" onclick="return confirm(<?= e(json_encode('Trocar a senha de ' . $a['nome'] . '? Ele sai do painel e entra com a senha nova.')) ?>)">Salvar</button>
                                            </form>
                                        </details>
                                        <form method="POST">
                                            <input type="hidden" name="acao" value="link_senha"><?= campoCsrf() ?>
                                            <input type="hidden" name="usuario_id" value="<?= (int) $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Manda um link para ele criar uma senha nova"><i class="fas fa-key"></i> Link de senha</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="acao" value="encerrar"><?= campoCsrf() ?>
                                            <input type="hidden" name="usuario_id" value="<?= (int) $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Tira o admin do painel em todos os aparelhos"><i class="fas fa-sign-out-alt"></i> Encerrar sessões</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$admins): ?>
                            <tr><td colspan="5" class="text-center py-4 texto-vazio">Nenhum admin cadastrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h2 class="titulo-secao">Novo admin</h2>
            <form method="POST" class="filtros-acessos row g-3 mb-2">
                <input type="hidden" name="acao" value="criar"><?= campoCsrf() ?>
                <div class="col-md-6">
                    <label class="form-label" for="nomeAdmin">Nome</label>
                    <input type="text" class="form-control" id="nomeAdmin" name="nome" maxlength="100" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="emailAdmin">E-mail</label>
                    <input type="email" class="form-control" id="emailAdmin" name="email" required autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="senhaAdmin">Senha</label>
                    <input type="password" class="form-control campo-senha" id="senhaAdmin" name="senha" minlength="<?= ADMIN_SENHA_MINIMA ?>" autocomplete="new-password" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="confirmaAdmin">Confirmar senha</label>
                    <input type="password" class="form-control campo-senha" id="confirmaAdmin" name="confirma_senha" minlength="<?= ADMIN_SENHA_MINIMA ?>" autocomplete="new-password" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary w-100 gerar-senha" title="Gera uma senha forte e mostra para você copiar"><i class="fas fa-magic"></i> Gerar</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filtrar w-100">Criar</button>
                </div>
            </form>
            <p class="texto-secundario small">
                Você define a senha do admin (mínimo de <?= ADMIN_SENHA_MINIMA ?> caracteres) e a passa para ele; "Gerar" cria uma senha forte e a mostra para você copiar.
                Em cada admin, "Definir senha" troca a senha (ele sai do painel e entra com a nova) e "Link de senha" manda um link para ele mesmo escolher, válido por <?= ADMIN_LINK_HORAS ?> horas.
                Desativar tira o admin do painel na hora, em todos os aparelhos. Tudo fica registrado na Auditoria, menos a senha.
            </p>
            <script>
                // "Gerar": senha forte (sem caracteres parecidos, como l/1 e O/0) nos dois campos do mesmo formulário, visível para copiar
                document.querySelectorAll('.gerar-senha').forEach(function (botao) {
                    botao.addEventListener('click', function () {
                        var letras = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
                        var aleatorio = new Uint32Array(14);
                        crypto.getRandomValues(aleatorio);
                        var senha = Array.from(aleatorio, function (n) { return letras[n % letras.length]; }).join('');
                        botao.closest('form').querySelectorAll('.campo-senha').forEach(function (campo) {
                            campo.type = 'text';
                            campo.value = senha;
                        });
                    });
                });
            </script>
<?php require __DIR__ . '/includes/layout_fim.php'; ?>
