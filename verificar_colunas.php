<?php
require_once 'src/conexao.php';

$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    $result = $conn->query("DESCRIBE usuarios");
    echo "<h2>Estrutura da tabela usuarios:</h2>";
    echo "<pre>";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    echo "</pre>";
    
    echo "<h2>Dados dos usuários:</h2>";
    $result2 = $conn->query("SELECT id, nome, email, foto_rosto, foto_documento_frente, foto_documento_verso FROM usuarios LIMIT 5");
    echo "<pre>";
    while ($row = $result2->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Nome: {$row['nome']}\n";
        echo "  Rosto: " . ($row['foto_rosto'] ? $row['foto_rosto'] : 'NULL') . "\n";
        echo "  Frente: " . ($row['foto_documento_frente'] ? $row['foto_documento_frente'] : 'NULL') . "\n";
        echo "  Verso: " . ($row['foto_documento_verso'] ? $row['foto_documento_verso'] : 'NULL') . "\n";
    }
    echo "</pre>";
} else {
    echo "Erro ao conectar ao banco de dados";
}
