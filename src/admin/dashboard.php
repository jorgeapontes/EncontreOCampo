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
    <style>
        /* Correções específicas para o modal */
        .modal-header h3 {
            color: white !important;
            font-size: 1.5rem;
            margin: 0;
            flex: 1;
        }
        
        .modal-header .user-type-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            white-space: nowrap;
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 25px 30px !important;
        }
        
        .modal-header-content {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .close-button {
            position: static !important;
            margin-left: auto;
        }
        
        /* Estilos para o modal de confirmação */
        #confirmModal {
            display: none;
            position: fixed;
            z-index: 1002;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .confirm-modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .confirm-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .confirm-modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .confirm-modal-body {
            padding: 25px;
            text-align: center;
            font-size: 1.1rem;
            color: #333;
        }
        
        .confirm-modal-body strong {
            color: #667eea;
        }
        
        .confirm-modal-footer {
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .btn-confirm {
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            min-width: 120px;
        }
        
        .btn-confirm-yes {
            background-color: #28a745;
            color: white;
        }
        
        .btn-confirm-yes:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        
        .btn-confirm-no {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-confirm-no:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        /* NOVOS ESTILOS PARA OS CONTAINERS */
        .modal-container-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
            padding: 0;
        }

        .data-container {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .data-container.pessoal {
            border-left-color: #667eea;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eaf6 100%);
        }

        .data-container.especifico {
            border-left-color: #764ba2;
            background: linear-gradient(135deg, #f8f9fa 0%, #f3e5f5 100%);
        }

        .data-container.endereco {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f5e9 100%);
        }

        .container-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0,0,0,0.1);
        }

        .container-header h4 {
            margin: 0;
            font-size: 1.1rem;
            color: #333;
            font-weight: 600;
            flex: 1;
        }

        .container-header-icon {
            font-size: 1.3rem;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 18px;
        }

        .data-field {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .field-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .field-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
            word-break: break-word;
        }

        .field-value.empty {
            color: #999;
            font-style: italic;
        }

        .message-box {
            background: white;
            padding: 18px;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
            margin-top: 20px;
        }

        .message-box-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .message-box-content {
            background: #fffbf0;
            padding: 12px;
            border-radius: 4px;
            color: #333;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
            padding: 30px !important;
        }

        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
        }
    </style>
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
            <a href="chats_admin.php" class="nav-link">Chats</a>
            <a href="manage_comprovantes.php" class="nav-link">Comprovantes de Entrega</a>
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
            <button id="confirmYes" class="btn-confirm btn-confirm-yes">Sim</button>
            <button id="confirmNo" class="btn-confirm btn-confirm-no">Não</button>
            <button id="confirmCancel" class="btn-confirm btn-cancel">Cancelar</button>
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