<?php
// src/admin/dashboard.php
session_start();

// 1. INCLUIR CONEXÃO
// Corrigindo o caminho: Se dashboard.php está em src/admin/, conexao.php está em src/
require_once __DIR__ . '/../conexao.php'; 

// Verificar se é admin (Adicionado um check básico, assumindo que a sessão 'usuario_tipo' é definida no login)
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
    // Query para buscar solicitações pendentes
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
    <title>Dashboard Admin - Solicitações Pendentes</title>
    <link rel="stylesheet" href="../css/admin.css"> 
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>ADMIN - ENCONTRE OCAMPO</h1>
            <h2>Gerenciamento de Cadastros</h2>
        </div>
    </div>

    <div class="container">
        
        <?php if ($feedback_msg): ?>
            <div class="alert <?php echo $is_error ? 'alert-error' : 'alert-success'; ?>">
                <?php echo htmlspecialchars(urldecode($feedback_msg)); // Exibe a mensagem de feedback ?>
            </div>
        <?php endif; ?>

        <h3>Solicitações de Cadastro Pendentes</h3>

        <?php if (count($solicitacoes) > 0): ?>
        
        <div class="table-responsive">
            <table>
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
                        <td><?php echo htmlspecialchars(ucfirst($solicitacao['tipo_solicitacao'])); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($solicitacao['data_solicitacao'])); ?></td>
                        <td>
                            <button class="btn btn-secondary btn-ver-detalhes" 
                                    data-nome="<?php echo htmlspecialchars($solicitacao['nome']); ?>"
                                    data-tipo="<?php echo htmlspecialchars(ucfirst($solicitacao['tipo_solicitacao'])); ?>"
                                    data-json='<?php 
                                        // Garante que o JSON é válido, codifica e escapa para ser seguro em um atributo HTML
                                        $safe_json = json_encode(json_decode($solicitacao['dados_json'], true));
                                        echo htmlspecialchars($safe_json, ENT_QUOTES, 'UTF-8');
                                    ?>'>
                                Ver Detalhes
                            </button>
                        </td>
                        <td>
                            <a href="processar_admin_acao.php?id=<?php echo $solicitacao['id']; ?>&acao=aprovar" class="btn btn-success">Aprovar</a>
                            <a href="processar_admin_acao.php?id=<?php echo $solicitacao['id']; ?>&acao=rejeitar" class="btn btn-danger">Rejeitar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php else: ?>
            <p>Não há solicitações de cadastro pendentes no momento.</p>
        <?php endif; ?>

    </div>
    
    <div id="detalhesModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3 id="modal-titulo">Detalhes da Solicitação</h3>
            <p><strong>Nome:</strong> <span id="modal-nome"></span></p>
            <p><strong>Tipo de Cadastro:</strong> <span id="modal-tipo"></span></p>
            
            <hr>
            
            <h4>Dados Adicionais:</h4>
            <div id="modal-corpo-json">
                </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('detalhesModal');
        const closeButton = document.querySelector('.close-button');
        const modalBody = document.getElementById('modal-corpo-json');
        const modalNome = document.getElementById('modal-nome');
        const modalTipo = document.getElementById('modal-tipo');
        
        // Função para formatar as chaves do JSON (CamelCase, remove números e tipos de usuário)
        function formatKey(key) {
            let formatted = key.replace(/([0-9])/g, '')
                               .replace(/([A-Z])/g, ' $1')
                               .trim();
            formatted = formatted.replace(/Comprador|Vendedor|Transportador/i, '').trim();

            return formatted.charAt(0).toUpperCase() + formatted.slice(1);
        }

        // Abrir modal
        document.querySelectorAll('.btn-ver-detalhes').forEach(button => {
            button.addEventListener('click', function() {
                // 1. Coleta dados do botão
                const nome = this.getAttribute('data-nome');
                const tipo = this.getAttribute('data-tipo');
                const jsonString = this.getAttribute('data-json');
                const dados = JSON.parse(jsonString);

                // 2. Preenche cabeçalho do modal
                modalNome.textContent = nome;
                modalTipo.textContent = tipo;
                modalBody.innerHTML = ''; // Limpa o corpo

                // 3. Preenche o corpo com os dados JSON
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

        // Fechar modal ao clicar no X
        closeButton.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        // Fechar modal ao clicar fora
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>