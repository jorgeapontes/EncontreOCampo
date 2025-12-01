<?php
session_start();

require_once __DIR__ . '/../conexao.php'; 

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php"); 
    exit();
}

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Erro fatal: Não foi possível conectar ao banco de dados.");
}

try {
    $sql = "SELECT id, nome, email, tipo_solicitacao, data_solicitacao, dados_json 
            FROM solicitacoes_cadastro 
            WHERE status = 'pendente'
            ORDER BY data_solicitacao DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar solicitações: " . $e->getMessage()); 
}

$feedback_msg = $_GET['msg'] ?? '';
$is_error = strpos($feedback_msg, 'erro') !== false;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Encontre O Campo</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <img src="../../img/logo-nova.png" class="logo">
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
        <div class="alert <?= $is_error ? 'alert-error' : 'alert-success' ?>">
            <?= htmlspecialchars(urldecode($feedback_msg)); ?>
        </div>
    <?php endif; ?>

    <div class="header-section">
        <h1>Dashboard Administrativo</h1>
        <p>Gerencie solicitações de cadastro e usuários do sistema</p>
    </div>

    <div class="stats-cards">
        <div class="stat-card">
            <h3>Solicitações Pendentes</h3>
            <span class="stat-number"><?= count($solicitacoes) ?></span>
        </div>
        <div class="stat-card">
            <h3>Usuários ativos</h3>
            <span class="stat-number">
                <?php 
                $sql_total = "SELECT COUNT(*) as total FROM usuarios WHERE status = 'ativo'";
                $stmt_total = $conn->prepare($sql_total);
                $stmt_total->execute();
                echo $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
                ?>
            </span>
        </div>
    </div>

    <div class="section-header">
        <h2>Solicitações de Cadastro Pendentes</h2>

        <!-- BOTÕES DE FILTRO E ORDENAÇÃO -->
        <div class="table-controls">
            <!-- FILTRO -->
            <select id="filtro-tipo" class="filter-select">
                <option value="todos">Todos</option>
                <option value="admin">Administrador</option>
                <option value="comprador">Comprador</option>
                <option value="vendedor">Vendedor</option>
                <option value="transportador">Transportador</option>
            </select>

            <!-- ORDENAR -->
            <select id="ordenar-por" class="filter-select">
                <option value="recente">Mais recente</option>
                <option value="antigo">Mais antigo</option>
                <option value="az">Nome A-Z</option>
                <option value="za">Nome Z-A</option>
            </select>
        </div>
    </div>

    <?php if (count($solicitacoes) > 0): ?>
    <div class="table-responsive">
        <table class="modern-table" id="tabela-solicitacoes">
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
                <?php foreach ($solicitacoes as $s): ?>
                <tr data-tipo="<?= $s['tipo_solicitacao'] ?>">
                    <td><?= $s['id'] ?></td>
                    <td><?= htmlspecialchars($s['nome']) ?></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td><span class="badge badge-<?= $s['tipo_solicitacao'] ?>"><?= ucfirst($s['tipo_solicitacao']) ?></span></td>
                    <td data-data="<?= $s['data_solicitacao'] ?>">
                        <?= date('d/m/Y H:i', strtotime($s['data_solicitacao'])) ?>
                    </td>
                    <td>
                        <button class="btn btn-secondary btn-ver-detalhes"
                            data-nome="<?= htmlspecialchars($s['nome']) ?>"
                            data-tipo="<?= ucfirst($s['tipo_solicitacao']) ?>"
                            data-json='<?= htmlspecialchars(json_encode(json_decode($s["dados_json"], true)), ENT_QUOTES) ?>'>
                            Ver Detalhes
                        </button>
                    </td>
                    <td class="actions">
                        <a href="processar_admin_acao.php?id=<?= $s['id'] ?>&acao=aprovar" class="btn btn-success btn-sm">Aprovar</a>
                        <a href="processar_admin_acao.php?id=<?= $s['id'] ?>&acao=rejeitar" class="btn btn-danger btn-sm">Rejeitar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="section-header">
        <a href="todos_usuarios.php" class="btn btn-primary">Ver todos os usuários cadastrados</a>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>Não há solicitações pendentes</h3>
            <p>Todas as solicitações foram processadas.</p>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL -->
<div id="detalhesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-titulo">Detalhes da Solicitação</h3>
            <span class="user-type-badge" id="modal-tipo-badge"></span>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body" id="modal-corpo-completo">
            <!-- Conteúdo será preenchido dinamicamente -->
        </div>
    </div>
</div>

<script>
// ==========================
// FILTRO POR TIPO
// ==========================
document.getElementById("filtro-tipo").addEventListener("change", function () {
    const filtro = this.value.toLowerCase();
    document.querySelectorAll("#tabela-solicitacoes tbody tr").forEach(tr => {
        const tipo = tr.getAttribute("data-tipo").toLowerCase();
        tr.style.display = (filtro === "todos" || filtro === tipo) ? "" : "none";
    });
});

// ==========================
// ORDENAR TABELA
// ==========================
document.getElementById("ordenar-por").addEventListener("change", function () {
    const tabela = document.querySelector("#tabela-solicitacoes tbody");
    const linhas = Array.from(tabela.querySelectorAll("tr"));
    const criterio = this.value;

    linhas.sort((a, b) => {
        const nomeA = a.children[1].innerText.trim().toLowerCase();
        const nomeB = b.children[1].innerText.trim().toLowerCase();
        const dataA = new Date(a.children[4].getAttribute("data-data"));
        const dataB = new Date(b.children[4].getAttribute("data-data"));

        switch (criterio) {
            case "az": return nomeA.localeCompare(nomeB);
            case "za": return nomeB.localeCompare(nomeA);
            case "antigo": return dataA - dataB;
            default: return dataB - dataA;
        }
    });

    linhas.forEach(l => tabela.appendChild(l));
});

// ==========================
// MODAL DETALHES - MELHORADO
// ==========================
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("detalhesModal");
    const closeBtn = document.querySelector(".close-button");

    // Mapeamento de campos em português
    const camposMap = {
        // Campos comuns
        'nome': 'Nome Completo',
        'email': 'E-mail',
        'telefone': 'Telefone',
        'cpf': 'CPF',
        'cnpj': 'CNPJ',
        'data_nascimento': 'Data de Nascimento',
        
        // Endereço
        'cep': 'CEP',
        'rua': 'Rua',
        'numero': 'Número',
        'complemento': 'Complemento',
        'bairro': 'Bairro',
        'cidade': 'Cidade',
        'estado': 'Estado',
        
        // Comprador
        'preferencias_compra': 'Preferências de Compra',
        'forma_pagamento': 'Forma de Pagamento',
        
        // Vendedor
        'nome_propriedade': 'Nome da Propriedade',
        'tamanho_propriedade': 'Tamanho da Propriedade',
        'tipos_produtos': 'Tipos de Produtos',
        'certificacoes': 'Certificações',
        'capacidade_producao': 'Capacidade de Produção',
        
        // Transportador
        'nome_empresa': 'Nome da Empresa',
        'tipo_veiculo': 'Tipo de Veículo',
        'capacidade_carga': 'Capacidade de Carga',
        'regioes_atendidas': 'Regiões Atendidas',
        'licencas': 'Licenças'
    };

    // Categorias de informações
    const categorias = {
        'pessoal': ['nome', 'email', 'telefone', 'cpf', 'cnpj', 'data_nascimento'],
        'endereco': ['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'estado'],
        'comprador': ['preferencias_compra', 'forma_pagamento'],
        'vendedor': ['nome_propriedade', 'tamanho_propriedade', 'tipos_produtos', 'certificacoes', 'capacidade_producao'],
        'transportador': ['nome_empresa', 'tipo_veiculo', 'capacidade_carga', 'regioes_atendidas', 'licencas']
    };

    const titulosCategorias = {
        'pessoal': 'Informações Pessoais',
        'endereco': 'Endereço',
        'comprador': 'Dados do Comprador',
        'vendedor': 'Dados do Vendedor',
        'transportador': 'Dados do Transportador'
    };

    document.querySelectorAll(".btn-ver-detalhes").forEach(btn => {
        btn.addEventListener("click", function () {
            const nome = this.getAttribute("data-nome");
            const tipo = this.getAttribute("data-tipo");
            const json = JSON.parse(this.getAttribute("data-json"));

            // Atualiza header
            document.getElementById("modal-titulo").innerText = nome;
            document.getElementById("modal-tipo-badge").innerText = tipo;

            // Limpa corpo
            const corpo = document.getElementById("modal-corpo-completo");
            corpo.innerHTML = "";

            // Campos a ignorar
            const camposIgnorar = ['senha', 'senha_hash', 'confirmar_senha', 'password'];

            // Organiza dados por categoria
            for (const [categoria, campos] of Object.entries(categorias)) {
                const dadosCategoria = {};
                
                for (const campo of campos) {
                    // Procura o campo no JSON (case-insensitive)
                    const chaveEncontrada = Object.keys(json).find(k => 
                        k.toLowerCase().replace(/\s+/g, '_') === campo.toLowerCase()
                    );
                    
                    if (chaveEncontrada && json[chaveEncontrada] && json[chaveEncontrada].trim() !== "") {
                        dadosCategoria[campo] = json[chaveEncontrada];
                    }
                }

                // Se tem dados nesta categoria, cria a seção
                if (Object.keys(dadosCategoria).length > 0) {
                    const secao = document.createElement('div');
                    secao.className = 'info-section';
                    
                    const titulo = document.createElement('h4');
                    titulo.textContent = titulosCategorias[categoria];
                    secao.appendChild(titulo);

                    for (const [campo, valor] of Object.entries(dadosCategoria)) {
                        const item = document.createElement('div');
                        item.className = 'info-item';
                        
                        const label = document.createElement('div');
                        label.className = 'info-label';
                        label.textContent = camposMap[campo] || campo;
                        
                        const value = document.createElement('div');
                        value.className = 'info-value';
                        value.textContent = valor;
                        
                        item.appendChild(label);
                        item.appendChild(value);
                        secao.appendChild(item);
                    }

                    corpo.appendChild(secao);
                }
            }

            // Adiciona campos extras que não estão mapeados
            const camposMapeados = new Set(Object.values(categorias).flat());
            const camposExtras = {};
            
            for (const [chave, valor] of Object.entries(json)) {
                const chaveLimpa = chave.toLowerCase().replace(/\s+/g, '_');
                const ignorar = camposIgnorar.some(ig => chaveLimpa.includes(ig.toLowerCase()));
                
                if (!ignorar && !camposMapeados.has(chaveLimpa) && valor && valor.trim() !== "") {
                    camposExtras[chave] = valor;
                }
            }

            if (Object.keys(camposExtras).length > 0) {
                const secao = document.createElement('div');
                secao.className = 'info-section';
                
                const titulo = document.createElement('h4');
                titulo.textContent = 'Outras Informações';
                secao.appendChild(titulo);

                for (const [chave, valor] of Object.entries(camposExtras)) {
                    const item = document.createElement('div');
                    item.className = 'info-item';
                    
                    const label = document.createElement('div');
                    label.className = 'info-label';
                    label.textContent = chave;
                    
                    const value = document.createElement('div');
                    value.className = 'info-value';
                    value.textContent = valor;
                    
                    item.appendChild(label);
                    item.appendChild(value);
                    secao.appendChild(item);
                }

                corpo.appendChild(secao);
            }

            modal.style.display = "block";
        });
    });

    closeBtn.addEventListener("click", () => modal.style.display = "none");
    window.addEventListener("click", e => { if (e.target === modal) modal.style.display = "none"; });
});
</script>

</body>
</html>