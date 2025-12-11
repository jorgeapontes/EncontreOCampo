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
                
                // Cabeçalho com informações principais
                html += `
                    <div class="info-header">
                        <div class="user-summary">
                            <h3>${nome}</h3>
                            <div class="user-type-tag user-type-${tipo.toLowerCase()}">
                                ${tipo}
                            </div>
                            <div class="user-status-badge status-pendente">
                                <span>●</span> Pendente
                            </div>
                        </div>
                        <p class="user-email">${dados.email || 'Email não informado'}</p>
                    </div>
                    <hr>
                    <div class="info-details">
                        <h4>Informações ${tipo === 'Transportador' ? 'do Transportador' : tipo === 'Vendedor' ? 'do Vendedor' : 'do Comprador'}</h4>
                `;
                
                // Mapeamento de campos por tipo
                const camposPorTipo = {
                    'Comprador': ['tipoPessoaComprador', 'cpfCnpjComprador', 'nomeComercialComprador', 
                                  'cipComprador', 'telefone1Comprador', 'telefone2Comprador',
                                  'cepComprador', 'ruaComprador', 'numeroComprador', 'complementoComprador',
                                  'estadoComprador', 'cidadeComprador', 'planoComprador'],
                    'Vendedor': ['nomeComercialVendedor', 'cpfCnpjVendedor', 'cipVendedor',
                                 'telefone1Vendedor', 'telefone2Vendedor', 'cepVendedor',
                                 'ruaVendedor', 'numeroVendedor', 'complementoVendedor',
                                 'estadoVendedor', 'cidadeVendedor', 'planoVendedor'],
                    'Transportador': ['telefoneTransportador', 'numeroANTT', 'placaVeiculo',
                                      'modeloVeiculo', 'descricaoVeiculo', 'estadoTransportador',
                                      'cidadeTransportador']
                };
                
                // Nomes amigáveis
                const nomesCampos = {
                    'tipoPessoaComprador': 'Tipo de Pessoa',
                    'cpfCnpjComprador': 'CPF/CNPJ',
                    'nomeComercialComprador': 'Nome Comercial',
                    'cipComprador': 'CIP',
                    'telefone1Comprador': 'Telefone Principal',
                    'telefone2Comprador': 'Telefone Secundário',
                    'cepComprador': 'CEP',
                    'ruaComprador': 'Rua',
                    'numeroComprador': 'Número',
                    'complementoComprador': 'Complemento',
                    'estadoComprador': 'Estado',
                    'cidadeComprador': 'Cidade',
                    'planoComprador': 'Plano',
                    
                    'nomeComercialVendedor': 'Nome Comercial',
                    'cpfCnpjVendedor': 'CNPJ',
                    'cipVendedor': 'CIP',
                    'telefone1Vendedor': 'Telefone Principal',
                    'telefone2Vendedor': 'Telefone Secundário',
                    'cepVendedor': 'CEP',
                    'ruaVendedor': 'Rua',
                    'numeroVendedor': 'Número',
                    'complementoVendedor': 'Complemento',
                    'estadoVendedor': 'Estado',
                    'cidadeVendedor': 'Cidade',
                    'planoVendedor': 'Plano',
                    
                    'telefoneTransportador': 'Telefone',
                    'numeroANTT': 'Número ANTT',
                    'placaVeiculo': 'Placa do Veículo',
                    'modeloVeiculo': 'Modelo do Veículo',
                    'descricaoVeiculo': 'Descrição do Veículo',
                    'estadoTransportador': 'Estado',
                    'cidadeTransportador': 'Cidade'
                };
                
                // Função para formatar
                function formatarValor(campo, valor) {
                    if (!valor || valor.toString().trim() === '') {
                        return '<span class="detail-value empty">Não informado</span>';
                    }
                    
                    // CPF/CNPJ
                    if ((campo.includes('cpf') || campo.includes('cnpj')) && valor.replace(/\D/g, '').length === 11) {
                        valor = valor.replace(/\D/g, '');
                        return valor.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                    }
                    if ((campo.includes('cpf') || campo.includes('cnpj')) && valor.replace(/\D/g, '').length === 14) {
                        valor = valor.replace(/\D/g, '');
                        return valor.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                    }
                    
                    // CEP
                    if (campo.includes('cep') && valor.replace(/\D/g, '').length === 8) {
                        valor = valor.replace(/\D/g, '');
                        return valor.replace(/(\d{5})(\d{3})/, '$1-$2');
                    }
                    
                    // Telefone
                    if (campo.includes('telefone') && valor.replace(/\D/g, '').length >= 10) {
                        valor = valor.replace(/\D/g, '');
                        if (valor.length === 11) {
                            return valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                        } else if (valor.length === 10) {
                            return valor.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                        }
                    }
                    
                    return valor.toString().trim();
                }
                
                // Mostra campos específicos do tipo
                const camposExibir = camposPorTipo[tipo] || [];
                let camposMostrados = 0;
                
                for (const campo of camposExibir) {
                    if (dados[campo]) {
                        const valor = dados[campo];
                        const valorFormatado = formatarValor(campo, valor);
                        const nomeCampo = nomesCampos[campo] || campo;
                        
                        html += `
                            <div class="detail-row">
                                <span class="detail-label">${nomeCampo}:</span>
                                <span class="detail-value">${valorFormatado}</span>
                            </div>
                        `;
                        camposMostrados++;
                    }
                }
                
                // Se não encontrou campos específicos, mostra todos
                if (camposMostrados === 0) {
                    for (const [chave, valor] of Object.entries(dados)) {
                        if (chave !== 'nome' && chave !== 'email' && valor) {
                            const valorFormatado = formatarValor(chave, valor);
                            const nomeCampo = chave.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase());
                            
                            html += `
                                <div class="detail-row">
                                    <span class="detail-label">${nomeCampo}:</span>
                                    <span class="detail-value">${valorFormatado}</span>
                                </div>
                            `;
                        }
                    }
                }
                
                // Mensagem adicional se houver
                if (dados.message && dados.message.trim() !== '') {
                    html += `
                        <div class="detail-row-full" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                            <span class="detail-label">Mensagem:</span>
                            <div class="detail-value" style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 5px;">
                                ${dados.message}
                            </div>
                        </div>
                    `;
                }
                
                html += `
                    </div>
                    <div class="modal-footer">
                        <p><em>Clique fora do modal ou no X para fechar</em></p>
                    </div>
                `;
                
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
</script>

</body>
</html>