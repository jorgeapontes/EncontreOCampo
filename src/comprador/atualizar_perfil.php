<?php
// src/comprador/atualizar_perfil.php

session_start();
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

// Função para validar e mover a imagem
function processarUploadFoto($campo, $usuario_id, $foto_atual = null) {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return $foto_atual; // Mantém a foto atual se não enviar nova
    }

    $arquivo = $_FILES[$campo];
    
    // Verificar erro no upload
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erro no upload da imagem: " . $arquivo['error']);
    }

    // Validar tipo de arquivo
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
    $tipoArquivo = mime_content_type($arquivo['tmp_name']);
    
    if (!in_array($tipoArquivo, $tiposPermitidos)) {
        throw new Exception("Formato de imagem inválido. Use JPG, PNG ou GIF.");
    }

    // Validar tamanho (máximo 5MB)
    $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
    if ($arquivo['size'] > $tamanhoMaximo) {
        throw new Exception("A imagem deve ter no máximo 5MB.");
    }

    // Criar diretório se não existir
    $diretorio = '../uploads/compradores/';
    if (!file_exists($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    // Gerar nome único para o arquivo
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $nomeArquivo = 'comprador_' . $usuario_id . '_' . time() . '.' . $extensao;
    $caminhoCompleto = $diretorio . $nomeArquivo;

    // Mover arquivo
    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        throw new Exception("Erro ao salvar a imagem.");
    }

    // Se tinha foto anterior e é diferente da nova, deletar a anterior
    if ($foto_atual && $foto_atual !== $caminhoCompleto && file_exists($foto_atual)) {
        unlink($foto_atual);
    }

    return $caminhoCompleto;
}

try {
    // Processar upload da foto
    $foto_url = null;
    if (isset($_FILES['foto_perfil'])) {
        $foto_atual = $_POST['foto_atual'] ?? null;
        $foto_url = processarUploadFoto('foto_perfil', $usuario_id, $foto_atual);
    }

    // Coletar dados do formulário
    $nome = $_POST['nome'] ?? '';
    $nome_comercial = $_POST['nome_comercial'] ?? '';
    $tipo_pessoa = $_POST['tipo_pessoa'] ?? 'cpf';
    $telefone1 = $_POST['telefone1'] ?? '';
    $telefone2 = $_POST['telefone2'] ?? '';
    $cip = $_POST['cip'] ?? '';
    $cep = $_POST['cep'] ?? '';
    $rua = $_POST['rua'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $complemento = $_POST['complemento'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $cidade = $_POST['cidade'] ?? '';

    // Iniciar transação
    $conn->beginTransaction();

    // Atualizar tabela usuarios
    $sql_usuario = "UPDATE usuarios SET nome = :nome WHERE id = :usuario_id";
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->bindParam(':nome', $nome);
    $stmt_usuario->bindParam(':usuario_id', $usuario_id);
    $stmt_usuario->execute();

    // Atualizar tabela compradores
    $sql_comprador = "UPDATE compradores 
                     SET tipo_pessoa = :tipo_pessoa,
                         nome_comercial = :nome_comercial,
                         telefone1 = :telefone1,
                         telefone2 = :telefone2,
                         cip = :cip,
                         cep = :cep,
                         rua = :rua,
                         numero = :numero,
                         complemento = :complemento,
                         estado = :estado,
                         cidade = :cidade";
    
    // Adicionar foto se foi enviada
    if ($foto_url !== null) {
        $sql_comprador .= ", foto_perfil_url = :foto_perfil_url";
    }
    
    $sql_comprador .= " WHERE usuario_id = :usuario_id";
    
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':tipo_pessoa', $tipo_pessoa);
    $stmt_comprador->bindParam(':nome_comercial', $nome_comercial);
    $stmt_comprador->bindParam(':telefone1', $telefone1);
    $stmt_comprador->bindParam(':telefone2', $telefone2);
    $stmt_comprador->bindParam(':cip', $cip);
    $stmt_comprador->bindParam(':cep', $cep);
    $stmt_comprador->bindParam(':rua', $rua);
    $stmt_comprador->bindParam(':numero', $numero);
    $stmt_comprador->bindParam(':complemento', $complemento);
    $stmt_comprador->bindParam(':estado', $estado);
    $stmt_comprador->bindParam(':cidade', $cidade);
    
    if ($foto_url !== null) {
        $stmt_comprador->bindParam(':foto_perfil_url', $foto_url);
    }
    
    $stmt_comprador->bindParam(':usuario_id', $usuario_id);
    $stmt_comprador->execute();

    // Confirmar transação
    $conn->commit();

    // Atualizar nome na sessão
    $_SESSION['usuario_nome'] = $nome;

    // Redirecionar com mensagem de sucesso
    header("Location: perfil.php?sucesso=" . urlencode("Perfil atualizado com sucesso!"));
    exit();

} catch (Exception $e) {
    // Rollback em caso de erro
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Redirecionar com mensagem de erro
    header("Location: perfil.php?erro=" . urlencode($e->getMessage()));
    exit();
}
?>