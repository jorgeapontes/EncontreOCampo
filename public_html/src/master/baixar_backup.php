<?php
/** Download de um backup do banco — só para o master. Os backups ficam fora do public_html. */
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../functions/auditoria_functions.php';

if (($_SESSION['usuario_tipo'] ?? '') !== 'master') {
    if (isset($_SESSION['usuario_id'])) {
        auditoriaAcessoNegado('master');
    }
    header("Location: ../login");
    exit;
}

$nome = (string) ($_GET['arquivo'] ?? '');
$arquivo = PASTA_PRIVADA . '/backups/' . $nome;
if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}\.sql\.gz$/', $nome) || !is_file($arquivo)) {
    http_response_code(404);
    exit('Backup não encontrado.');
}

registrarAuditoria('backup_baixado', "Baixou o backup {$nome}", 'backup');

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="encontreocampo_' . $nome . '"');
header('Content-Length: ' . filesize($arquivo));
header('Cache-Control: no-store');
readfile($arquivo);
