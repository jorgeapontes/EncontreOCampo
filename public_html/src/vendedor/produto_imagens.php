<?php
// src/vendedor/produto_imagens.php
require_once 'auth.php';

header('Content-Type: application/json');

// Verificar se é uma requisição AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $produto_id = intval($_POST['produto_id'] ?? 0);
    
    // Verificar se o produto pertence ao vendedor logado
    $query = "SELECT id FROM produtos WHERE id = :produto_id AND vendedor_id = :vendedor_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':produto_id', $produto_id);
    $stmt->bindParam(':vendedor_id', $vendedor['id']);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado ou sem permissão.']);
        exit();
    }
    
    switch ($action) {
        case 'upload':
            handleUpload($db, $produto_id);
            break;
            
        case 'delete':
            $imagem_id = intval($_POST['imagem_id'] ?? 0);
            handleDelete($db, $produto_id, $imagem_id);
            break;
            
        case 'reorder':
            $orderData = $_POST['order'] ?? [];
            handleReorder($db, $produto_id, $orderData);
            break;
            
        case 'set_primary':
            $imagem_id = intval($_POST['imagem_id'] ?? 0);
            handleSetPrimary($db, $produto_id, $imagem_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    }
}

function handleUpload($db, $produto_id) {
    $upload_dir = '../uploads/produtos/';
    
    // Criar diretório se não existir
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['imagem']['name'];
        $file_tmp = $_FILES['imagem']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $max_file_size = 2097152; // 2MB
        
        if (!in_array($file_ext, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Formato de arquivo inválido.']);
            exit();
        }
        
        if ($_FILES['imagem']['size'] > $max_file_size) {
            echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo: 2MB.']);
            exit();
        }
        
        // Gerar nome único para o arquivo
        $novo_nome = uniqid('prod_img_', true) . '.' . $file_ext;
        $destino_servidor = $upload_dir . $novo_nome;
        
        if (move_uploaded_file($file_tmp, $destino_servidor)) {
            // Verificar quantas imagens já existem para este produto
            $query_count = "SELECT COUNT(*) as total FROM produto_imagens WHERE produto_id = :produto_id";
            $stmt_count = $db->prepare($query_count);
            $stmt_count->bindParam(':produto_id', $produto_id);
            $stmt_count->execute();
            $count = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Inserir no banco de dados
            $query = "INSERT INTO produto_imagens (produto_id, imagem_url, ordem) 
                      VALUES (:produto_id, :imagem_url, :ordem)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':produto_id', $produto_id);
            $stmt->bindParam(':imagem_url', $destino_servidor);
            $stmt->bindParam(':ordem', $count);
            
            if ($stmt->execute()) {
                $imagem_id = $db->lastInsertId();
                
                // Se for a primeira imagem, definir como imagem principal
                if ($count === 0) {
                    $update_query = "UPDATE produtos SET imagem_url = :imagem_url WHERE id = :produto_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':imagem_url', $destino_servidor);
                    $update_stmt->bindParam(':produto_id', $produto_id);
                    $update_stmt->execute();
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Imagem enviada com sucesso!',
                    'imagem_id' => $imagem_id,
                    'imagem_url' => $destino_servidor
                ]);
            } else {
                // Remover arquivo se falhar ao inserir no banco
                unlink($destino_servidor);
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar imagem no banco de dados.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao mover arquivo para o servidor.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Nenhuma imagem enviada ou erro no upload.']);
    }
}

function handleDelete($db, $produto_id, $imagem_id) {
    // Buscar informações da imagem
    $query = "SELECT imagem_url FROM produto_imagens WHERE id = :imagem_id AND produto_id = :produto_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':imagem_id', $imagem_id);
    $stmt->bindParam(':produto_id', $produto_id);
    $stmt->execute();
    
    $imagem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$imagem) {
        echo json_encode(['success' => false, 'message' => 'Imagem não encontrada.']);
        exit();
    }
    
    // Verificar se é a imagem principal
    $query_principal = "SELECT imagem_url FROM produtos WHERE id = :produto_id";
    $stmt_principal = $db->prepare($query_principal);
    $stmt_principal->bindParam(':produto_id', $produto_id);
    $stmt_principal->execute();
    $produto = $stmt_principal->fetch(PDO::FETCH_ASSOC);
    
    $is_primary = ($produto['imagem_url'] === $imagem['imagem_url']);
    
    // Excluir do banco de dados
    $delete_query = "DELETE FROM produto_imagens WHERE id = :imagem_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':imagem_id', $imagem_id);
    
    if ($delete_stmt->execute()) {
        // Remover arquivo físico
        if (file_exists($imagem['imagem_url'])) {
            unlink($imagem['imagem_url']);
        }
        
        // Se era a imagem principal, definir a primeira imagem restante como principal
        if ($is_primary) {
            $query_primeira = "SELECT imagem_url FROM produto_imagens WHERE produto_id = :produto_id ORDER BY ordem ASC LIMIT 1";
            $stmt_primeira = $db->prepare($query_primeira);
            $stmt_primeira->bindParam(':produto_id', $produto_id);
            $stmt_primeira->execute();
            
            $nova_principal = $stmt_primeira->fetch(PDO::FETCH_ASSOC);
            
            if ($nova_principal) {
                $update_query = "UPDATE produtos SET imagem_url = :imagem_url WHERE id = :produto_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':imagem_url', $nova_principal['imagem_url']);
                $update_stmt->bindParam(':produto_id', $produto_id);
                $update_stmt->execute();
            } else {
                // Se não houver mais imagens, limpar a imagem principal
                $update_query = "UPDATE produtos SET imagem_url = NULL WHERE id = :produto_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':produto_id', $produto_id);
                $update_stmt->execute();
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Imagem excluída com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir imagem do banco de dados.']);
    }
}

function handleReorder($db, $produto_id, $orderData) {
    foreach ($orderData as $order => $imagem_id) {
        $query = "UPDATE produto_imagens SET ordem = :ordem WHERE id = :imagem_id AND produto_id = :produto_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':ordem', $order);
        $stmt->bindParam(':imagem_id', $imagem_id);
        $stmt->bindParam(':produto_id', $produto_id);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Ordem atualizada com sucesso!']);
}

function handleSetPrimary($db, $produto_id, $imagem_id) {
    // Buscar URL da imagem
    $query = "SELECT imagem_url FROM produto_imagens WHERE id = :imagem_id AND produto_id = :produto_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':imagem_id', $imagem_id);
    $stmt->bindParam(':produto_id', $produto_id);
    $stmt->execute();
    
    $imagem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$imagem) {
        echo json_encode(['success' => false, 'message' => 'Imagem não encontrada.']);
        exit();
    }
    
    // Atualizar imagem principal do produto
    $update_query = "UPDATE produtos SET imagem_url = :imagem_url WHERE id = :produto_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':imagem_url', $imagem['imagem_url']);
    $update_stmt->bindParam(':produto_id', $produto_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Imagem principal definida com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao definir imagem principal.']);
    }
}
?>