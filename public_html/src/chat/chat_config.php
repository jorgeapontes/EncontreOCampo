<?php
// src/chat/chat_config.php
require_once __DIR__ . '/../conexao.php';

// Limites compartilhados pelos endpoints de mensagens
const CHAT_TAMANHO_MAX_MENSAGEM = 5000;
const CHAT_TAMANHO_MAX_IMAGEM = 5 * 1024 * 1024; // 5MB
const CHAT_MENSAGENS_POR_PAGINA = 50;

// MIME detectado pelo finfo => extensão gravada no disco. A extensão nunca
// vem do nome original do arquivo (que o cliente controla).
const CHAT_TIPOS_IMAGEM = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

// Resposta de erro padronizada dos endpoints do chat: status HTTP correto
// e sempre a chave 'erro'.
function chatErro($status, $mensagem) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'erro' => $mensagem]);
    exit();
}

function verificarLoginChat() {
    if (!isset($_SESSION['usuario_id'])) {
        chatErro(401, 'Sua sessão expirou. Faça login novamente.');
    }
}

// Token CSRF da sessão (mesma chave usada pelo login.php)
function chatCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Aceita o token no header X-CSRF-Token (fetch) ou no campo csrf_token (FormData)
function chatExigirCsrf() {
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !is_string($enviado) || !hash_equals($_SESSION['csrf_token'], $enviado)) {
        chatErro(403, 'Sua sessão foi atualizada. Recarregue a página e tente novamente.');
    }
}

// Libera o lock do arquivo de sessão. Sem isso, as requisições do mesmo
// usuário (polling + envio) ficam enfileiradas uma atrás da outra.
// Chamar só depois de ler tudo o que precisa de $_SESSION.
function chatLiberarSessao() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

// Valida e grava uma imagem enviada no chat. Retorna o nome do arquivo
// gravado em uploads/chat/ ou chama chatErro().
function chatSalvarImagem($file, $prefixo) {
    if (!is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
        chatErro(400, 'Erro no upload do arquivo.');
    }
    if ($file['size'] > CHAT_TAMANHO_MAX_IMAGEM) {
        chatErro(413, 'Arquivo muito grande (máx. 5MB).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset(CHAT_TIPOS_IMAGEM[$mime])) {
        chatErro(415, 'Tipo de arquivo não permitido. Envie JPG, PNG, GIF ou WEBP.');
    }

    $uploadDir = __DIR__ . '/../../uploads/chat/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $nome = uniqid($prefixo) . '_' . time() . '.' . CHAT_TIPOS_IMAGEM[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $nome)) {
        error_log("Falha ao mover arquivo de upload de chat: tmp_name={$file['tmp_name']} destino={$uploadDir}{$nome}");
        chatErro(500, 'Falha ao salvar o arquivo.');
    }

    return $nome;
}

// Mensagens marcadas como apagadas pelo admin não são exibidas
const CHAT_SQL_NAO_DELETADA = "(m.deletado = 0 OR m.deletado IS NULL)";

// Maior id de mensagem minha que o outro lado já leu (para o ✓✓)
function chatUltimoLidoId($conn, $conversa_id, $usuario_id) {
    $stmt = $conn->prepare("SELECT MAX(id) FROM chat_mensagens
                            WHERE conversa_id = :conversa_id AND remetente_id = :usuario_id AND lida = 1");
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

// Data de envio gravada pelo banco (o relógio do MySQL pode ter fuso
// diferente do PHP, e o cliente compara com as datas vindas do banco)
function chatDataEnvio($conn, $mensagem_id) {
    $stmt = $conn->prepare("SELECT data_envio FROM chat_mensagens WHERE id = :id");
    $stmt->bindParam(':id', $mensagem_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn() ?: '';
}

// Busca mensagens de uma conversa em um dos três modos:
//  - ultimo_id > 0: só as novas (polling)
//  - antes_id > 0: página de mensagens mais antigas (rolar para cima)
//  - nenhum: a última página (carregamento inicial)
// $filtro_extra é um trecho SQL fixo (nunca vindo do usuário).
// Retorna [mensagens, tem_mais].
function chatBuscarMensagens($conn, $conversa_id, $ultimo_id, $antes_id, $colunas_extra = '', $filtro_extra = '') {
    $base = "SELECT m.id, m.remetente_id, m.mensagem, m.tipo, m.dados_json, m.data_envio,
                    DATE_FORMAT(m.data_envio, '%d/%m %H:%i') AS data_formatada,
                    m.lida, u.tipo AS remetente_tipo {$colunas_extra}
             FROM chat_mensagens m
             LEFT JOIN usuarios u ON m.remetente_id = u.id
             WHERE m.conversa_id = :conversa_id AND " . CHAT_SQL_NAO_DELETADA . " {$filtro_extra}";

    $limite = CHAT_MENSAGENS_POR_PAGINA + 1;

    if ($ultimo_id > 0) {
        $stmt = $conn->prepare($base . " AND m.id > :ref ORDER BY m.id ASC LIMIT 500");
        $stmt->bindValue(':ref', $ultimo_id, PDO::PARAM_INT);
    } elseif ($antes_id > 0) {
        $stmt = $conn->prepare($base . " AND m.id < :ref ORDER BY m.id DESC LIMIT {$limite}");
        $stmt->bindValue(':ref', $antes_id, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare($base . " ORDER BY m.id DESC LIMIT {$limite}");
    }
    $stmt->bindValue(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tem_mais = false;
    if ($ultimo_id <= 0) {
        if (count($mensagens) > CHAT_MENSAGENS_POR_PAGINA) {
            $tem_mais = true;
            array_pop($mensagens);
        }
        $mensagens = array_reverse($mensagens);
    }

    return [$mensagens, $tem_mais];
}

// Marca como lidas as mensagens recebidas nesta conversa
function marcarMensagensComoLidas($conn, $conversa_id, $usuario_id) {
    try {
        $sql = "UPDATE chat_mensagens
                SET lida = 1
                WHERE conversa_id = :conversa_id
                AND remetente_id != :usuario_id
                AND lida = 0";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
        $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    } catch (PDOException $e) {
        error_log("Erro ao marcar mensagens como lidas: " . $e->getMessage());
        return false;
    }
}

function obterOuCriarConversa($conn, $produto_id, $comprador_id, $vendedor_id) {
    try {
        // Verificar se já existe uma conversa
        $sql = "SELECT id FROM chat_conversas
                WHERE produto_id = :produto_id
                AND comprador_id = :comprador_id
                AND vendedor_id = :vendedor_id";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
        $stmt->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
        $stmt->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $conversa = $stmt->fetch(PDO::FETCH_ASSOC);
            return $conversa['id'];
        } else {
            // Criar nova conversa
            $sql_insert = "INSERT INTO chat_conversas (produto_id, comprador_id, vendedor_id)
                          VALUES (:produto_id, :comprador_id, :vendedor_id)";

            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
            $stmt_insert->execute();

            return $conn->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log("Erro ao obter/criar conversa: " . $e->getMessage());
        return false;
    }
}
?>
