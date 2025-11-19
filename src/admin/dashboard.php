<?php
// src/admin/dashboard.php
session_start();

// 1. INCLUIR CONEXÃO
require_once __DIR__ . '/../conexao.php'; 

// Verificar se é admin
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php"); 
    exit();
}

// Iniciar conexão com o Banco de Dados
$database = new Database();
$conn = $database->getConnection();

// Verificar se a conexão falhou
if (!$conn) {
    die("Erro fatal: Não foi possível conectar ao banco de dados.");
}

// 2. BUSCAR SOLICITAÇÕES PENDENTES
try {
    $sql = "SELECT id, nome, email, tipo_solicitacao, data_solicitacao, dados_json 
            FROM solicitacoes_cadastro 
            WHERE status = 'pendente' 
            ORDER BY data_solicitacao ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar solicitações: " . $e->getMessage()); 
}

// Coleta a mensagem de feedback após uma ação (aprovar/rejeitar)
$feedback_msg = $_GET['msg'] ?? '';
$is_error = strpos($feedback_msg, 'erro') !== false || strpos($feedback_msg, 'Erro') !== false;

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Encontre O Campo</title>
    <link rel="stylesheet" href="../css/admin.css"> 
     <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="../../img/logo-nova.png" alt="Logo Encontre Ocampo" class="logo">
                <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link active">Dashboard</a>
                <a href="todos_usuarios.php" class="nav-link">Todos os Usuários</a>
                <a href="../../index.php" class="nav-link">Home</a>
                <a href="../logout.php" class="nav-link logout">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($feedback_msg): ?>
            <div class="alert <?php echo $is_error ? 'alert-error' : 'alert-success'; ?>">
                <?php echo htmlspecialchars(urldecode($feedback_msg)); ?>
            </div>
        <?php endif; ?>

        <div class="header-section">
            <h1>Dashboard Administrativo</h1>
            <p>Gerencie solicitações de cadastro e usuários do sistema</p>
        </div>

        <div class="stats-cards">
            <div class="stat-card">
                <h3>Solicitações Pendentes</h3>
                <span class="stat-number"><?php echo count($solicitacoes); ?></span>
            </div>
            <div class="stat-card">
                <h3>Usuários ativos</h3>
                <span class="stat-number">
                    <?php 
                    $sql_total = "SELECT COUNT(*) as total FROM usuarios WHERE status = 'ativo'";
                    $stmt_total = $conn->prepare($sql_total);
                    $stmt_total->execute();
                    $total_usuarios = $stmt_total->fetch(PDO::FETCH_ASSOC);
                    echo $total_usuarios['total'];
                    ?>
                </span>
            </div>
        </div>

        <div class="section-header">
            <h2>Solicitações de Cadastro Pendentes</h2>
            <a href="todos_usuarios.php" class="btn btn-primary">Ver todos os usuários cadastrados</a>
        </div>

        <?php if (count($solicitacoes) > 0): ?>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Data Solicitação</th>
                        <th>Detalhes</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitacoes as $solicitacao): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($solicitacao['id']); ?></td>
                        <td><?php echo htmlspecialchars($solicitacao['nome']); ?></td>
                        <td><?php echo htmlspecialchars($solicitacao['email']); ?></td>
                        <td><span class="badge badge-<?php echo $solicitacao['tipo_solicitacao']; ?>"><?php echo htmlspecialchars(ucfirst($solicitacao['tipo_solicitacao'])); ?></span></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($solicitacao['data_solicitacao'])); ?></td>
                        <td>
                            <button class="btn btn-secondary btn-ver-detalhes" 
                                    data-nome="<?php echo htmlspecialchars($solicitacao['nome']); ?>"
                                    data-tipo="<?php echo htmlspecialchars(ucfirst($solicitacao['tipo_solicitacao'])); ?>"
                                    data-json='<?php 
                                        $safe_json = json_encode(json_decode($solicitacao['dados_json'], true));
                                        echo htmlspecialchars($safe_json, ENT_QUOTES, 'UTF-8');
                                    ?>'>
                                Ver Detalhes
                            </button>
                        </td>
                        <td class="actions">
                            <a href="processar_admin_acao.php?id=<?php echo $solicitacao['id']; ?>&acao=aprovar" class="btn btn-success btn-sm">Aprovar</a>
                            <a href="processar_admin_acao.php?id=<?php echo $solicitacao['id']; ?>&acao=rejeitar" class="btn btn-danger btn-sm">Rejeitar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>Não há solicitações pendentes</h3>
                <p>Todas as solicitações foram processadas.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal de Detalhes -->
    <div id="detalhesModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3 id="modal-titulo">Detalhes da Solicitação</h3>
            <p><strong>Nome:</strong> <span id="modal-nome"></span></p>
            <p><strong>Tipo de Cadastro:</strong> <span id="modal-tipo"></span></p>
            
            <hr>
            
            <h4>Dados Adicionais:</h4>
            <div id="modal-corpo-json"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('detalhesModal');
        const closeButton = document.querySelector('.close-button');
        const modalBody = document.getElementById('modal-corpo-json');
        const modalNome = document.getElementById('modal-nome');
        const modalTipo = document.getElementById('modal-tipo');
        
        function formatKey(key) {
            let formatted = key.replace(/([0-9])/g, '')
                               .replace(/([A-Z])/g, ' $1')
                               .trim();
            formatted = formatted.replace(/Comprador|Vendedor|Transportador/i, '').trim();
            return formatted.charAt(0).toUpperCase() + formatted.slice(1);
        }

        document.querySelectorAll('.btn-ver-detalhes').forEach(button => {
            button.addEventListener('click', function() {
                const nome = this.getAttribute('data-nome');
                const tipo = this.getAttribute('data-tipo');
                const jsonString = this.getAttribute('data-json');
                const dados = JSON.parse(jsonString);

                modalNome.textContent = nome;
                modalTipo.textContent = tipo;
                modalBody.innerHTML = '';

                for (const key in dados) {
                    if (dados.hasOwnProperty(key) && dados[key].trim() !== '') {
                        const detailItem = document.createElement('p');
                        detailItem.innerHTML = `<strong>${formatKey(key)}:</strong> ${dados[key]}`;
                        modalBody.appendChild(detailItem);
                    }
                }

                modal.style.display = 'block';
            });
        });

        closeButton.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>