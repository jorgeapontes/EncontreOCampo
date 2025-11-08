<?php
session_start();
require_once '../conexao.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Buscar solicitações pendentes
$query = "SELECT sc.*, u.nome, u.email 
          FROM solicitacoes_cadastro sc 
          JOIN usuarios u ON sc.usuario_id = u.id 
          WHERE sc.status = 'pendente' 
          ORDER BY sc.data_solicitacao DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Encontre Ocampo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: #2E7D32; color: white; padding: 1rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .card { background: white; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .solicitacao { border-left: 4px solid #FF9800; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; margin-right: 0.5rem; }
        .btn-success { background: #4CAF50; color: white; }
        .btn-danger { background: #f44336; color: white; }
        .dados-item { margin-bottom: 0.5rem; }
        .dados-item strong { display: inline-block; width: 150px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Painel Administrativo - Encontre Ocampo</h1>
        <p>Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?> | <a href="../logout.php" style="color: white;">Sair</a></p>
    </div>

    <div class="container">
        <h2>Solicitações de Cadastro Pendentes</h2>
        
        <?php if (empty($solicitacoes)): ?>
            <div class="card">
                <p>Nenhuma solicitação pendente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($solicitacoes as $solicitacao): ?>
                <div class="card solicitacao">
                    <h3>Solicitação de <?php echo ucfirst($solicitacao['tipo_solicitacao']); ?></h3>
                    <p><strong>Nome:</strong> <?php echo $solicitacao['nome']; ?></p>
                    <p><strong>Email:</strong> <?php echo $solicitacao['email']; ?></p>
                    <p><strong>Data:</strong> <?php echo $solicitacao['data_solicitacao']; ?></p>
                    
                    <h4>Dados Específicos:</h4>
                    <?php 
                    $dados = json_decode($solicitacao['dados_json'], true);
                    foreach ($dados as $chave => $valor): 
                        if (!empty($valor)):
                    ?>
                        <div class="dados-item">
                            <strong><?php echo ucfirst(str_replace('_', ' ', $chave)); ?>:</strong>
                            <?php echo $valor; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    
                    <div style="margin-top: 1rem;">
                        <form action="processar_solicitacao.php" method="POST" style="display: inline;">
                            <input type="hidden" name="solicitacao_id" value="<?php echo $solicitacao['id']; ?>">
                            <input type="hidden" name="usuario_id" value="<?php echo $solicitacao['usuario_id']; ?>">
                            <input type="hidden" name="tipo" value="<?php echo $solicitacao['tipo_solicitacao']; ?>">
                            <input type="hidden" name="dados_json" value='<?php echo $solicitacao['dados_json']; ?>'>
                            <button type="submit" name="acao" value="aprovar" class="btn btn-success">Aprovar</button>
                            <button type="submit" name="acao" value="rejeitar" class="btn btn-danger">Rejeitar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>