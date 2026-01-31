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
    <link rel="stylesheet" href="css/dashboard.css">
    <title>Dashboard Admin - Encontre o Campo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="todos_usuarios.php" class="nav-link">
                            Usuários
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="chats_admin.php" class="nav-link">
                            Chats
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_comprovantes.php" class="nav-link">
                            Comprovantes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../../index.php" class="nav-link">
                            Home
                        </a>
                    </li>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <br>

    <div class="main-content">
        <section class="header">
            <center>
                <h1>Bem-vindo(a), Administrador</h1>
                <p>Gerencie solicitações de cadastro e usuários do sistema</p>
            </center>
        </section>

        <?php if ($feedback_msg): ?>
            <div class="alert <?= $is_error ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars(urldecode($feedback_msg)); ?>
            </div>
        <?php endif; ?>

        <div class="info-cards">
            <a>
                <div class="card">
                    <i class="fas fa-user-clock"></i>
                    <h3>Solicitações pendentes</h3>
                    <p><?= count($solicitacoes) ?> não lidas</p>
                </div>
            </a>
            <a>
                <div class="card">
                    <i class="fas fa-user-check"></i>
                    <h3>Usuários ativos</h3>
                    <p>
                        <?php 
                        $sql_total = "SELECT COUNT(*) as total FROM usuarios WHERE status = 'ativo'";
                        $stmt_total = $conn->prepare($sql_total);
                        $stmt_total->execute();
                        echo $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
                        ?>
                    </p>
                </div>
            </a>
        </div>

        <div class="section-anuncios">
            <div id="header">
                <h2>Solicitações de Cadastro Pendentes</h2>
                <div class="table-controls">
                    <select id="filtro-tipo" class="filter-select">
                        <option value="todos">Todos</option>
                        <option value="admin">Administrador</option>
                        <option value="comprador">Comprador</option>
                        <option value="vendedor">Vendedor</option>
                        <option value="transportador">Transportador</option>
                    </select>
                    <select id="ordenar-por" class="filter-select">
                        <option value="recente">Mais recentes</option>
                        <option value="antigo">Mais antigos</option>
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
                            <th class="th-center">Detalhes</th>
                            <th class="th-center">Ações</th>
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
                            <td class="td-center">
                                <button class="btn btn-secondary btn-ver-detalhes"
                                    data-nome="<?= htmlspecialchars($s['nome']) ?>"
                                    data-tipo="<?= ucfirst($s['tipo_solicitacao']) ?>"
                                    data-json='<?= htmlspecialchars(json_encode(json_decode($s["dados_json"], true)), ENT_QUOTES) ?>'>
                                    Ver Detalhes
                                </button>
                            </td>
                            <td class="actions td-center">
                                <button class="btn btn-success btn-sm btn-aprovar" 
                                        data-id="<?= $s['id'] ?>"
                                        data-nome="<?= htmlspecialchars($s['nome']) ?>"
                                        data-tipo="<?= $s['tipo_solicitacao'] ?>"
                                        data-acao="aprovar">
                                    Aprovar
                                </button>
                                <button class="btn btn-danger btn-sm btn-rejeitar"
                                        data-id="<?= $s['id'] ?>"
                                        data-nome="<?= htmlspecialchars($s['nome']) ?>"
                                        data-tipo="<?= $s['tipo_solicitacao'] ?>"
                                        data-acao="rejeitar">
                                    Rejeitar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <a href="todos_usuarios.php" class="btn-ver-todos">Ver todos os usuários cadastrados</a>
            
            <?php else: ?>
                <div class="empty-state">
                    <h3>Não há solicitações pendentes</h3>
                    <p>Todas as solicitações foram processadas.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- MODAL DE DETALHES -->
<div id="detalhesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-content">
                <h3 id="modal-titulo">Detalhes da Solicitação</h3>
                <span class="user-type-badge" id="modal-tipo-badge"></span>
            </div>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body" id="modal-corpo-completo">
            <!-- Conteúdo será preenchido dinamicamente -->
        </div>
    </div>
</div>

<!-- MODAL DE CONFIRMAÇÃO -->
<div id="confirmModal" class="modal">
    <div class="confirm-modal-content">
        <div class="confirm-modal-header">
            <h3 id="confirmModalTitle">Confirmar Ação</h3>
        </div>
        <div class="confirm-modal-body">
            <p id="confirmModalMessage">Você tem certeza que deseja realizar esta ação?</p>
        </div>
        <div class="confirm-modal-footer">
            <div class="modal-flex">
                <button id="confirmYes" class="btn-confirm btn-confirm-yes">Sim</button>
                <button id="confirmNo" class="btn-confirm btn-confirm-no">Não</button>
                <button id="confirmCancel" class="btn-confirm btn-cancel">Cancelar</button>
            </div>
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
// MODAL DETALHES - VERSÃO CORRIGIDA E SIMPLIFICADA
// ==========================
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("detalhesModal");
    const closeBtn = document.querySelector(".close-button");
    const modalCorpo = document.getElementById("modal-corpo-completo");
    const modalTitulo = document.getElementById("modal-titulo");
    const modalTipoBadge = document.getElementById("modal-tipo-badge");

    // Adiciona event listener a todos os botões "Ver Detalhes"
    document.querySelectorAll(".btn-ver-detalhes").forEach(btn => {
        btn.addEventListener("click", function () {
            const nome = this.getAttribute("data-nome");
            const tipo = this.getAttribute("data-tipo");
            const jsonData = this.getAttribute("data-json");
            
            // Atualiza título do modal
            modalTitulo.innerText = `Detalhes da Solicitação`;
            modalTipoBadge.innerText = tipo.charAt(0).toUpperCase() + tipo.slice(1);
            
            // Tenta parsear o JSON
            try {
                const dados = JSON.parse(jsonData);
                let html = '';
                
                // ============ CONTAINER 1: DADOS PESSOAIS ============
                html += `
                    <div class="data-container pessoal">
                        <div class="container-header">
                            <span class="container-header-icon">👤</span>
                            <h4>Dados Pessoais da Conta</h4>
                        </div>
                        <div class="data-grid">
                            <div class="data-field">
                                <div class="field-label">Nome</div>
                                <div class="field-value">${nome || 'Não informado'}</div>
                            </div>
                            <div class="data-field">
                                <div class="field-label">Email</div>
                                <div class="field-value">${dados.email || 'Não informado'}</div>
                            </div>
                            <div class="data-field">
                                <div class="field-label">Tipo de Usuário</div>
                                <div class="field-value">
                                    <span style="background: #667eea; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; font-size: 0.9rem;">
                                        ${tipo}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // ============ CONTAINER 2: DADOS ESPECÍFICOS POR TIPO ============
                let dadosEspecificos = '';
                
                if (tipo === 'Comprador') {
                    dadosEspecificos = `
                        <div class="data-container especifico">
                            <div class="container-header">
                                <span class="container-header-icon">🛒</span>
                                <h4>Dados do Comprador</h4>
                            </div>
                            <div class="data-grid">
                                <div class="data-field">
                                    <div class="field-label">Tipo de Pessoa</div>
                                    <div class="field-value">${dados.tipoPessoaComprador ? (dados.tipoPessoaComprador.toUpperCase()) : 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">CPF/CNPJ</div>
                                    <div class="field-value">${dados.cpfCnpjComprador || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Nome Comercial</div>
                                    <div class="field-value">${dados.nomeComercialComprador || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Telefone Principal</div>
                                    <div class="field-value">${dados.telefone1Comprador || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Telefone Secundário</div>
                                    <div class="field-value ${!dados.telefone2Comprador ? 'empty' : ''}">${dados.telefone2Comprador || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Plano</div>
                                    <div class="field-value">${dados.planoComprador || 'Não informado'}</div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (tipo === 'Vendedor') {
                    dadosEspecificos = `
                        <div class="data-container especifico">
                            <div class="container-header">
                                <span class="container-header-icon">🏪</span>
                                <h4>Dados do Vendedor</h4>
                            </div>
                            <div class="data-grid">
                                <div class="data-field">
                                    <div class="field-label">Nome Comercial</div>
                                    <div class="field-value">${dados.nomeComercialVendedor || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">CNPJ</div>
                                    <div class="field-value">${dados.cpfCnpjVendedor || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Razão Social</div>
                                    <div class="field-value ${!dados.cipVendedor ? 'empty' : ''}">${dados.cipVendedor || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Telefone Principal</div>
                                    <div class="field-value">${dados.telefone1Vendedor || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Telefone Secundário</div>
                                    <div class="field-value ${!dados.telefone2Vendedor ? 'empty' : ''}">${dados.telefone2Vendedor || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Plano</div>
                                    <div class="field-value">${dados.planoVendedor || 'Não informado'}</div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (tipo === 'Transportador') {
                    dadosEspecificos = `
                        <div class="data-container especifico">
                            <div class="container-header">
                                <span class="container-header-icon">🚚</span>
                                <h4>Dados do Transportador</h4>
                            </div>
                            <div class="data-grid">
                                <div class="data-field">
                                    <div class="field-label">Telefone</div>
                                    <div class="field-value">${dados.telefoneTransportador || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Número ANTT</div>
                                    <div class="field-value">${dados.numeroANTT || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Placa do Veículo</div>
                                    <div class="field-value">${dados.placaVeiculo || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Modelo do Veículo</div>
                                    <div class="field-value">${dados.modeloVeiculo || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Descrição do Veículo</div>
                                    <div class="field-value">${dados.descricaoVeiculo || 'Não informado'}</div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                html += dadosEspecificos;
                
                // ============ CONTAINER 3: DADOS DE ENDEREÇO ============
                let enderecoCampos = [];
                
                if (tipo === 'Comprador') {
                    enderecoCampos = {
                        cep: dados.cepComprador,
                        rua: dados.ruaComprador,
                        numero: dados.numeroComprador,
                        complemento: dados.complementoComprador,
                        estado: dados.estadoComprador,
                        cidade: dados.cidadeComprador
                    };
                } else if (tipo === 'Vendedor') {
                    enderecoCampos = {
                        cep: dados.cepVendedor,
                        rua: dados.ruaVendedor,
                        numero: dados.numeroVendedor,
                        complemento: dados.complementoVendedor,
                        estado: dados.estadoVendedor,
                        cidade: dados.cidadeVendedor
                    };
                } else if (tipo === 'Transportador') {
                    enderecoCampos = {
                        cep: dados.cepTransportador,
                        rua: dados.ruaTransportador,
                        numero: dados.numeroTransportador,
                        complemento: dados.complementoTransportador,
                        estado: dados.estadoTransportador,
                        cidade: dados.cidadeTransportador
                    };
                }
                
                html += `
                    <div class="data-container endereco">
                        <div class="container-header">
                            <span class="container-header-icon">📍</span>
                            <h4>Endereço</h4>
                        </div>
                        <div class="data-grid">
                            <div class="data-field">
                                <div class="field-label">CEP</div>
                                <div class="field-value ${!enderecoCampos.cep ? 'empty' : ''}">${enderecoCampos.cep || 'Não informado'}</div>
                            </div>
                            <div class="data-field">
                                <div class="field-label">Rua</div>
                                <div class="field-value ${!enderecoCampos.rua ? 'empty' : ''}">${enderecoCampos.rua || 'Não informado'}</div>
                            </div>
                            <div class="data-field">
                                <div class="field-label">Número</div>
                                <div class="field-value ${!enderecoCampos.numero ? 'empty' : ''}">${enderecoCampos.numero || 'Não informado'}</div>
                            </div>
                            <div class="data-field">
                                <div class="field-label">Complemento</div>
                                <div class="field-value ${!enderecoCampos.complemento ? 'empty' : ''}">${enderecoCampos.complemento || 'Não informado'}</div>
                            </div>
                            <div class="data-field">
                                <div class="field-label">Estado</div>
                                <div class="field-value ${!enderecoCampos.estado ? 'empty' : ''}">${enderecoCampos.estado || 'Não informado'}</div>
                            </div>
                            <div class="data-field">
                                <div class="field-label">Cidade</div>
                                <div class="field-value ${!enderecoCampos.cidade ? 'empty' : ''}">${enderecoCampos.cidade || 'Não informado'}</div>
                            </div>
                        </div>
                    </div>
                `;
                
                // ============ MENSAGEM ADICIONAL ============
                if (dados.message && dados.message.trim() !== '') {
                    html += `
                        <div class="message-box">
                            <div class="message-box-label">💬 Mensagem Adicional</div>
                            <div class="message-box-content">${dados.message}</div>
                        </div>
                    `;
                }
                
                modalCorpo.innerHTML = html;
            } catch (e) {
                modalCorpo.innerHTML = `
                    <div class="error-message">
                        <h4>Erro ao carregar dados</h4>
                        <p>${e.message}</p>
                    </div>
                `;
            }
            
            // Mostra o modal
            modal.style.display = "block";
        });
    });

    // Fecha o modal ao clicar no X
    closeBtn.addEventListener("click", () => {
        modal.style.display = "none";
    });
    
    // Fecha o modal ao clicar fora dele
    window.addEventListener("click", e => { 
        if (e.target === modal) {
            modal.style.display = "none";
        }
    });
    
    // Adiciona também suporte para tecla ESC
    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && modal.style.display === "block") {
            modal.style.display = "none";
        }
    });
});

// ==========================
// CONFIRMAÇÃO DE AÇÃO (APROVAR/REJEITAR)
// ==========================
document.addEventListener("DOMContentLoaded", function() {
    const confirmModal = document.getElementById("confirmModal");
    const confirmModalTitle = document.getElementById("confirmModalTitle");
    const confirmModalMessage = document.getElementById("confirmModalMessage");
    const confirmYes = document.getElementById("confirmYes");
    const confirmNo = document.getElementById("confirmNo");
    const confirmCancel = document.getElementById("confirmCancel");
    
    let currentAction = null;
    let currentId = null;
    let currentUrl = null;
    
    // Função para abrir o modal de confirmação
    function openConfirmModal(id, nome, tipo, acao) {
        currentId = id;
        currentAction = acao;
        
        // Define a mensagem com base na ação
        const acaoTexto = acao === 'aprovar' ? 'APROVAR' : 'REJEITAR';
        const tipoTexto = tipo === 'vendedor' ? 'vendedor' : 
                         tipo === 'comprador' ? 'comprador' : 
                         tipo === 'transportador' ? 'transportador' : 'usuário';
        
        confirmModalTitle.textContent = acao === 'aprovar' ? 'Confirmar Aprovação' : 'Confirmar Rejeição';
        confirmModalMessage.innerHTML = `Você tem certeza que deseja <strong>${acaoTexto}</strong> a solicitação de <strong>${nome}</strong> como ${tipoTexto}?`;
        
        // Mostra o modal
        confirmModal.style.display = "block";
    }
    
    // Adiciona eventos aos botões de Aprovar
    document.querySelectorAll('.btn-aprovar').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const nome = this.getAttribute('data-nome');
            const tipo = this.getAttribute('data-tipo');
            
            openConfirmModal(id, nome, tipo, 'aprovar');
        });
    });
    
    // Adiciona eventos aos botões de Rejeitar
    document.querySelectorAll('.btn-rejeitar').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const nome = this.getAttribute('data-nome');
            const tipo = this.getAttribute('data-tipo');
            
            openConfirmModal(id, nome, tipo, 'rejeitar');
        });
    });
    
    // Botão SIM - Confirma a ação
    confirmYes.addEventListener('click', function() {
        if (currentId && currentAction) {
            // Redireciona para processar a ação
            window.location.href = `processar_admin_acao.php?id=${currentId}&acao=${currentAction}`;
        }
        confirmModal.style.display = "none";
    });
    
    // Botão NÃO - Nega a ação
    confirmNo.addEventListener('click', function() {
        confirmModal.style.display = "none";
    });
    
    // Botão CANCELAR - Fecha o modal
    confirmCancel.addEventListener('click', function() {
        confirmModal.style.display = "none";
    });
    
    // Fecha o modal ao clicar fora dele
    window.addEventListener('click', function(e) {
        if (e.target === confirmModal) {
            confirmModal.style.display = "none";
        }
    });
    
    // Fecha o modal com a tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && confirmModal.style.display === 'block') {
            confirmModal.style.display = "none";
        }
    });
});
</script>

</body>
</html>