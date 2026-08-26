<?php
require_once __DIR__ . '/../conexao.php';

$database = new Database();
$db = $database->getConnection();

// 1. Primeiro, vamos ver o que o PHP acha que é "agora"
echo "Horário do Servidor agora: " . date('Y-m-d H:i:s') . "<br>";

// 2. Vamos listar os usuários que estão no banco e ver os dados deles
$check = $db->query("SELECT id, status_assinatura, data_vencimento_assinatura FROM vendedores");
$usuarios = $check->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Lista de Usuários no Banco:</h3>";
foreach ($usuarios as $u) {
    $vencimento = $u['data_vencimento_assinatura'];
    $status = $u['status_assinatura'];
    $agora = date('Y-m-d H:i:s');
    
    echo "ID: {$u['id']} | Status: $status | Vencimento: $vencimento | ";
    
    if ($status !== 'ativo') {
        echo "<span style='color:red'>- Não é 'ativo'</span><br>";
    } elseif ($vencimento >= $agora) {
        echo "<span style='color:orange'>- Ainda não venceu</span><br>";
    } else {
        echo "<span style='color:green'>- DEVERIA ATUALIZAR!</span><br>";
    }
}

// 3. Tenta rodar o UPDATE
$sql = "UPDATE vendedores SET plano_id = 1, status_assinatura = 'expirado' 
        WHERE status_assinatura = 'ativo' AND data_vencimento_assinatura < NOW()";
$stmt = $db->prepare($sql);
$stmt->execute();

echo "<h4>Resultado final: " . $stmt->rowCount() . " linhas afetadas.</h4>";