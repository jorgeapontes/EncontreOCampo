<?php
// corrigir_imagens.php

$config = [
    'host' => '127.0.0.1',
    'dbname' => 'encontre_ocampo', 
    'username' => 'root',
    'password' => ''
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8", 
        $config['username'], 
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscar todos os produtos
    $stmt = $pdo->query("SELECT id, nome, imagem_url FROM produtos");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>Corrigindo Caminhos de Imagens</h1>";
    
    foreach ($produtos as $produto) {
        echo "<h3>Produto ID: {$produto['id']} - {$produto['nome']}</h3>";
        echo "<p>Caminho atual: {$produto['imagem_url']}</p>";
        
        if (!empty($produto['imagem_url'])) {
            $caminho_antigo = $produto['imagem_url'];
            $caminho_novo = str_replace('../', '', $caminho_antigo);
            
            // Verifica se o arquivo existe no caminho antigo
            if (file_exists($caminho_antigo)) {
                echo "<p style='color: green;'>✅ Arquivo encontrado em: $caminho_antigo</p>";
                
                // Atualiza no banco para o caminho sem '../'
                $update = $pdo->prepare("UPDATE produtos SET imagem_url = ? WHERE id = ?");
                $update->execute([$caminho_novo, $produto['id']]);
                echo "<p style='color: blue;'>📝 Atualizado no BD para: $caminho_novo</p>";
                
            } else if (file_exists($caminho_novo)) {
                echo "<p style='color: orange;'>⚠️ Arquivo encontrado em: $caminho_novo (já está correto)</p>";
            } else {
                echo "<p style='color: red;'>❌ Arquivo NÃO encontrado em nenhum local</p>";
                
                // Tenta encontrar o arquivo
                $possiveis_locais = [
                    'uploads/produtos/' . basename($caminho_antigo),
                    'src/uploads/produtos/' . basename($caminho_antigo),
                    '../src/uploads/produtos/' . basename($caminho_antigo),
                    basename($caminho_antigo)
                ];
                
                foreach ($possiveis_locais as $local) {
                    if (file_exists($local)) {
                        echo "<p style='color: green;'>✅ Encontrado em: $local</p>";
                        $update = $pdo->prepare("UPDATE produtos SET imagem_url = ? WHERE id = ?");
                        $update->execute([$local, $produto['id']]);
                        echo "<p style='color: blue;'>📝 Atualizado no BD para: $local</p>";
                        break;
                    }
                }
            }
        } else {
            echo "<p style='color: gray;'>ℹ️ Sem imagem</p>";
        }
        echo "<hr>";
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>