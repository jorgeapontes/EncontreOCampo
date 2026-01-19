<?php
// buscar_anuncios.php

// Ativar display de erros para debug
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Caminho absoluto para o arquivo de conexão
$conexao_path = __DIR__ . '/src/conexao.php';
if (!file_exists($conexao_path)) {
    echo json_encode([
        'success' => false,
        'message' => 'Arquivo de conexão não encontrado: ' . $conexao_path,
        'produtos' => []
    ]);
    exit;
}
require_once $conexao_path;

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Falha ao conectar ao banco de dados');
    }

    // Consulta para buscar produtos
    $sql = "
        SELECT 
            p.id,
            p.nome,
            p.descricao,
            p.preco,
            p.imagem_url,
            p.estoque,
            p.preco_desconto,
            p.desconto_data_fim,
            p.categoria,
            v.nome_comercial as vendedor_nome
        FROM produtos p 
        INNER JOIN vendedores v ON p.vendedor_id = v.id 
        WHERE p.status = 'ativo' 
        AND p.estoque > 0 
        ORDER BY p.data_criacao DESC 
        LIMIT 12
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // CORREÇÃO DEFINITIVA DAS IMAGENS
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $project_root = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    
    foreach ($produtos as &$produto) {
        if (!empty($produto['imagem_url'])) {
            $imagem_path = $produto['imagem_url'];
            
            // Remove '../' do início
            $imagem_path = str_replace('../', '', $imagem_path);
            
            // Se o arquivo existe localmente, usa URL relativa
            if (file_exists($imagem_path)) {
                // URL relativa ao projeto
                $produto['imagem_url'] = $project_root . '/' . $imagem_path;
            } else {
                // Se não existe, tenta encontrar em outras localizações possíveis
                $possible_paths = [
                    $imagem_path,
                    'uploads/' . basename($imagem_path),
                    'src/' . $imagem_path,
                    '../' . $imagem_path
                ];
                
                $found = false;
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $produto['imagem_url'] = $project_root . '/' . $path;
                        $found = true;
                        break;
                    }
                }
                
                // Se não encontrou em nenhum lugar, usa placeholder
                if (!$found) {
                    $produto['imagem_url'] = 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80';
                }
            }
        } else {
            // Sem imagem no banco
            $produto['imagem_url'] = 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80';
        }
    }

    echo json_encode([
        'success' => true, 
        'produtos' => $produtos,
        'total' => count($produtos),
        'message' => 'Anúncios carregados com sucesso'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro de banco de dados: ' . $e->getMessage(),
        'produtos' => []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro geral: ' . $e->getMessage(),
        'produtos' => []
    ]);
}
?>