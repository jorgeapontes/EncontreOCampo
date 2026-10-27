<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/includes/toast.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    if (isset($_SESSION['usuario_id'])) { // logado com outro perfil: fica na auditoria
        require_once __DIR__ . '/../functions/auditoria_functions.php';
        auditoriaAcessoNegado('admin');
    }
    header("Location: ../index");
    exit;
}


$database = new Database();
$conn = $database->getConnection();

$usuario_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$novo_status = filter_input(INPUT_GET, 'status');

if ($usuario_id === false || $usuario_id === null || !in_array($novo_status, ['ativo', 'inativo'], true)) {
    adminToast('erro', 'Não foi possível alterar', 'Parâmetros inválidos.');
    header('Location: todos_usuarios');
    exit;
}

try {
    $stmt_nome = $conn->prepare("SELECT nome, tipo FROM usuarios WHERE id = :id");
    $stmt_nome->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt_nome->execute();
    $alvo = $stmt_nome->fetch(PDO::FETCH_ASSOC) ?: [];
    $nome = $alvo['nome'] ?? "Usuário #$usuario_id";

    // A conta master (dono do sistema) não é gerenciada pelo admin
    if (($alvo['tipo'] ?? '') === 'master') {
        adminToast('erro', 'Não foi possível alterar', 'Usuário não encontrado.');
        header('Location: todos_usuarios');
        exit;
    }

    $sql = "UPDATE usuarios SET status = :status WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $novo_status);
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();

    // Registrar ação do admin
    $admin_id = $_SESSION['usuario_id'] ?? 1;
    $acao_desc = "Alterou status do usuário (ID: $usuario_id) para $novo_status";
    $sql_acao = "INSERT INTO admin_acoes (admin_id, acao, tabela_afetada, registro_id)
                 VALUES (:admin_id, :acao, 'usuarios', :registro_id)";
    $stmt_acao = $conn->prepare($sql_acao);
    $stmt_acao->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt_acao->bindParam(':acao', $acao_desc);
    $stmt_acao->bindParam(':registro_id', $usuario_id, PDO::PARAM_INT);
    $stmt_acao->execute();

    // Desativado sai do site na hora (não só quando fechar o navegador)
    if ($novo_status === 'inativo' && function_exists('encerrarSessoesUsuario')) {
        encerrarSessoesUsuario($usuario_id);
    }

    require_once __DIR__ . '/../functions/auditoria_functions.php';
    registrarAuditoria('usuario_status', ($novo_status === 'ativo' ? 'Ativou' : 'Desativou') . " o usuário $nome (" . ($alvo['tipo'] ?? '?') . ')',
        'usuario', $usuario_id);

    if ($novo_status === 'ativo') {
        adminToast('aprovado', 'Usuário ativado', "$nome voltou a ter acesso à plataforma.");
    } else {
        adminToast('rejeitado', 'Usuário desativado', "$nome não consegue mais acessar a plataforma.");
    }
    header('Location: todos_usuarios');
} catch (Exception $e) {
    adminToast('erro', 'Não foi possível alterar o status', $e->getMessage());
    header('Location: todos_usuarios');
}
