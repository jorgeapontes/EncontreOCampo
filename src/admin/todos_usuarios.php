<?php
session_start();

// Verificar se é admin
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../conexao.php';

$database = new Database();
$conn = $database->getConnection();

// FILTRO
$filtro_tipo = $_GET['filtro_tipo'] ?? '';

// ORDENAÇÃO
$ordenar = $_GET['ordenar'] ?? '';
$orderBy = "data_criacao DESC";

if ($ordenar === "novo_velho") {
    $orderBy = "data_criacao DESC";
} elseif ($ordenar === "velho_novo") {
    $orderBy = "data_criacao ASC";
} elseif ($ordenar === "az") {
    $orderBy = "nome ASC";
} elseif ($ordenar === "za") {
    $orderBy = "nome DESC";
}

// Query com filtro + ordenação
$sql = "SELECT id, nome, email, tipo, status, data_criacao FROM usuarios";
$params = [];

if (!empty($filtro_tipo) && $filtro_tipo !== "todos") {
    $sql .= " WHERE tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

$sql .= " ORDER BY $orderBy";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$feedback_msg = $_GET['msg'] ?? '';
$is_error = strpos($feedback_msg, 'erro') !== false || strpos($feedback_msg, 'Erro') !== false;

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos os Usuários - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <style>
        /* Estilos adicionais para separar melhor as colunas */
        .actions-details {
            text-align: center;
            padding: 8px !important;
        }
        
        .actions-status {
            text-align: center;
            padding: 8px !important;
        }
        
        .btn-ver-detalhes {
            width: 100%;
            min-width: 120px;
            padding: 8px 12px;
        }
        
        .btn-status-action {
            width: 100%;
            min-width: 100px;
            padding: 8px 12px;
        }
        
        .admin-label {
            display: block;
            text-align: center;
            font-size: 0.85em;
            color: #95a5a6;
            font-style: italic;
            padding: 5px;
        }
    </style>
</head>

<body>

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
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="todos_usuarios.php" class="nav-link active">Todos os Usuários</a>
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
        <h1>Todos os Usuários Cadastrados</h1>
        <p>Gerencie todos os usuários do sistema</p>
    </div>

    <!-- BOTÕES DE FILTRO E ORDENAR -->
    <form method="GET">
        <div class="table-controls">
            <!-- FILTRAR -->
            <select name="filtro_tipo" class="filter-select" onchange="this.form.submit()">
                <option value="todos">Todos</option>
                <option value="admin" <?= $filtro_tipo === "admin" ? "selected" : "" ?>>Admin</option>
                <option value="comprador" <?= $filtro_tipo === "comprador" ? "selected" : "" ?>>Comprador</option>
                <option value="vendedor" <?= $filtro_tipo === "vendedor" ? "selected" : "" ?>>Vendedor</option>
                <option value="transportador" <?= $filtro_tipo === "transportador" ? "selected" : "" ?>>Transportador</option>
            </select>

            <!-- ORDENAR -->
            <select name="ordenar" class="filter-select" onchange="this.form.submit()">
                <option value="">Ordenar por</option>
                <option value="novo_velho" <?= $ordenar === "novo_velho" ? "selected" : "" ?>>Mais novo → Mais velho</option>
                <option value="velho_novo" <?= $ordenar === "velho_novo" ? "selected" : "" ?>>Mais velho → Mais novo</option>
                <option value="az" <?= $ordenar === "az" ? "selected" : "" ?>>A → Z</option>
                <option value="za" <?= $ordenar === "za" ? "selected" : "" ?>>Z → A</option>
            </select>
        </div>
    </form>

    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Data de Criação</th>
                    <th>Detalhes</th>
                    <th>Gerenciar Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?= htmlspecialchars($usuario['id']); ?></td>
                    <td><?= htmlspecialchars($usuario['nome']); ?></td>
                    <td><?= htmlspecialchars($usuario['email']); ?></td>
                    <td>
                        <span class="badge badge-<?= $usuario['tipo']; ?>">
                            <?= ucfirst(htmlspecialchars($usuario['tipo'])); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $usuario['status']; ?>">
                            <?= ucfirst(htmlspecialchars($usuario['status'])); ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($usuario['data_criacao'])); ?></td>
                    
                    <!-- COLUNA DETALHES -->
                    <td class="actions-details">
                        <button class="btn btn-secondary btn-sm btn-ver-detalhes" 
                                data-user-id="<?= $usuario['id']; ?>"
                                data-user-nome="<?= htmlspecialchars($usuario['nome']); ?>"
                                data-user-tipo="<?= htmlspecialchars($usuario['tipo']); ?>"
                                data-user-email="<?= htmlspecialchars($usuario['email']); ?>">
                            Ver Detalhes
                        </button>
                    </td>
                    
                    <!-- COLUNA GERENCIAR STATUS -->
                    <td class="actions-status">
                        <?php if ($usuario['tipo'] !== 'admin'): ?>
                            <?php if ($usuario['status'] === 'ativo'): ?>
                                <a href="alterar_status.php?id=<?= $usuario['id']; ?>&status=inativo"
                                   class="btn btn-warning btn-sm btn-status-action"
                                   onclick="return confirm('Tem certeza que deseja desativar este usuário?')">
                                    Desativar
                                </a>
                            <?php else: ?>
                                <a href="alterar_status.php?id=<?= $usuario['id']; ?>&status=ativo"
                                   class="btn btn-success btn-sm btn-status-action"
                                   onclick="return confirm('Tem certeza que deseja ativar este usuário?')">
                                    Ativar
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="admin-label">Admin</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL DE DETALHES DO USUÁRIO -->
<div id="detalhesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-titulo">Detalhes do Usuário</h3>
            <span class="user-type-badge" id="modal-tipo-badge"></span>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body" id="modal-corpo-completo">
            <!-- Conteúdo será preenchido dinamicamente -->
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("detalhesModal");
    const closeBtn = document.querySelector(".close-button");
    const modalCorpo = document.getElementById("modal-corpo-completo");
    const modalTitulo = document.getElementById("modal-titulo");
    const modalTipoBadge = document.getElementById("modal-tipo-badge");

    // Mapeamento de campos por tipo de usuário
    const camposPorTipo = {
        'comprador': [
            'nome', 'email', 'telefone1Comprador', 'telefone2Comprador',
            'tipoPessoaComprador', 'cpfCnpjComprador', 'nomeComercialComprador',
            'cipComprador', 'cepComprador', 'ruaComprador', 'numeroComprador',
            'complementoComprador', 'estadoComprador', 'cidadeComprador',
            'planoComprador', 'cpf_cnpj', 'nome_comercial', 'telefone1', 'telefone2',
            'cep', 'rua', 'numero', 'complemento', 'cidade', 'estado', 'plano'
        ],
        'vendedor': [
            'nome', 'email', 'telefone1Vendedor', 'telefone2Vendedor',
            'nomeComercialVendedor', 'cpfCnpjVendedor', 'cipVendedor',
            'cepVendedor', 'ruaVendedor', 'numeroVendedor', 'complementoVendedor',
            'estadoVendedor', 'cidadeVendedor', 'planoVendedor',
            'cpf_cnpj', 'nome_comercial', 'razao_social', 'telefone1', 'telefone2',
            'cep', 'rua', 'numero', 'complemento', 'cidade', 'estado', 'plano'
        ],
        'transportador': [
            'nome', 'email', 'telefoneTransportador', 'numeroANTT',
            'placaVeiculo', 'modeloVeiculo', 'descricaoVeiculo',
            'estadoTransportador', 'cidadeTransportador', 'telefone',
            'numero_antt', 'placa_veiculo', 'modelo_veiculo', 'descricao_veiculo',
            'cidade', 'estado'
        ],
        'admin': [
            'nome', 'email'
        ]
    };

    // Nomes amigáveis para os campos
    const nomesCampos = {
        // Campos comuns
        'nome': 'Nome Completo',
        'email': 'E-mail',
        'telefone': 'Telefone',
        'telefone1': 'Telefone Principal',
        'telefone2': 'Telefone Secundário',
        'telefone1Comprador': 'Telefone Principal',
        'telefone2Comprador': 'Telefone Secundário',
        'telefone1Vendedor': 'Telefone Principal',
        'telefone2Vendedor': 'Telefone Secundário',
        'telefoneTransportador': 'Telefone',
        
        // Documentos
        'tipoPessoaComprador': 'Tipo de Pessoa',
        'cpfCnpjComprador': 'CPF/CNPJ',
        'cpfCnpjVendedor': 'CNPJ',
        'cpf_cnpj': 'CPF/CNPJ',
        'numeroANTT': 'Número ANTT',
        'numero_antt': 'Número ANTT',
        'cipComprador': 'CIP',
        'cipVendedor': 'CIP',
        
        // Informações comerciais
        'nomeComercialComprador': 'Nome Comercial',
        'nomeComercialVendedor': 'Nome Comercial/Razão Social',
        'nome_comercial': 'Nome Comercial',
        'razao_social': 'Razão Social',
        
        // Endereço
        'cepComprador': 'CEP',
        'cepVendedor': 'CEP',
        'cep': 'CEP',
        'ruaComprador': 'Rua',
        'ruaVendedor': 'Rua',
        'rua': 'Rua',
        'numeroComprador': 'Número',
        'numeroVendedor': 'Número',
        'numero': 'Número',
        'complementoComprador': 'Complemento',
        'complementoVendedor': 'Complemento',
        'complemento': 'Complemento',
        'estadoComprador': 'Estado',
        'estadoVendedor': 'Estado',
        'estadoTransportador': 'Estado',
        'estado': 'Estado',
        'cidadeComprador': 'Cidade',
        'cidadeVendedor': 'Cidade',
        'cidadeTransportador': 'Cidade',
        'cidade': 'Cidade',
        
        // Planos
        'planoComprador': 'Plano',
        'planoVendedor': 'Plano',
        'plano': 'Plano',
        
        // Dados do transportador
        'placaVeiculo': 'Placa do Veículo',
        'placa_veiculo': 'Placa do Veículo',
        'modeloVeiculo': 'Modelo do Veículo',
        'modelo_veiculo': 'Modelo do Veículo',
        'descricaoVeiculo': 'Descrição do Veículo',
        'descricao_veiculo': 'Descrição do Veículo',
        
        // Datas
        'data_criacao': 'Data de Cadastro',
        'data_atualizacao': 'Última Atualização'
    };

    // Função para formatar valores
    function formatarValor(campo, valor) {
        if (!valor || valor.toString().trim() === '' || valor === 'null' || valor === 'undefined') {
            return '<span class="detail-value empty">Não informado</span>';
        }

        // Formatar CPF
        if ((campo.toLowerCase().includes('cpf') || campo === 'cpf_cnpj') && valor.replace(/\D/g, '').length === 11) {
            valor = valor.replace(/\D/g, '');
            return valor.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        
        // Formatar CNPJ
        if ((campo.toLowerCase().includes('cnpj') || campo === 'cpf_cnpj') && valor.replace(/\D/g, '').length === 14) {
            valor = valor.replace(/\D/g, '');
            return valor.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
        }
        
        // Formatar CEP
        if (campo.toLowerCase().includes('cep') && valor.replace(/\D/g, '').length === 8) {
            valor = valor.replace(/\D/g, '');
            return valor.replace(/(\d{5})(\d{3})/, '$1-$2');
        }
        
        // Formatar telefone
        if ((campo.toLowerCase().includes('telefone') || campo.includes('tel')) && valor.replace(/\D/g, '').length >= 10) {
            valor = valor.replace(/\D/g, '');
            if (valor.length === 11) {
                return valor.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            } else if (valor.length === 10) {
                return valor.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
            }
        }
        
        // Formatar placa de veículo
        if ((campo.toLowerCase().includes('placa') || campo === 'placa_veiculo') && valor) {
            valor = valor.toUpperCase().replace(/\s/g, '');
            if (valor.length === 7) {
                // Formato Mercosul: AAA1B23
                return valor.replace(/([A-Z]{3})([A-Z0-9]{4})/, '$1-$2');
            } else if (valor.length === 8 && valor.includes('-')) {
                // Já formatado: AAA-1234
                return valor;
            }
        }
        
        // Formatar data
        if (campo.includes('data') && valor) {
            try {
                const data = new Date(valor);
                if (!isNaN(data.getTime())) {
                    return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
                }
            } catch (e) {
                // Se não conseguir formatar, retorna o valor original
            }
        }
        
        return valor.toString().trim();
    }

    // Adiciona event listener a todos os botões "Ver Detalhes"
    document.querySelectorAll(".btn-ver-detalhes").forEach(btn => {
        btn.addEventListener("click", function () {
            const userId = this.getAttribute("data-user-id");
            const nome = this.getAttribute("data-user-nome");
            const tipo = this.getAttribute("data-user-tipo");
            const email = this.getAttribute("data-user-email");
            
            // Mostrar loading no modal
            modalTitulo.innerText = `Carregando...`;
            modalTipoBadge.innerText = tipo.charAt(0).toUpperCase() + tipo.slice(1);
            modalCorpo.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 3rem; color: #4CAF50; margin-bottom: 20px;">⏳</div>
                    <p>Carregando detalhes do usuário...</p>
                </div>
            `;
            modal.style.display = "block";
            
            // Buscar detalhes do usuário via AJAX
            fetch(`get_user_details.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalCorpo.innerHTML = `
                            <div class="error-message">
                                <h4>Erro ao carregar dados</h4>
                                <p>${data.error}</p>
                            </div>
                        `;
                        return;
                    }
                    
                    // Atualiza título do modal com dados reais
                    modalTitulo.innerText = `Detalhes do Usuário`;
                    
                    const usuario = data.usuario;
                    const detalhes = data.detalhes || {};
                    
                    // Combina dados do usuário com detalhes específicos
                    const todosDados = {
                        ...usuario,
                        ...detalhes
                    };
                    
                    let html = '';
                    
                    // Cabeçalho com informações principais
                    html += `
                        <div class="info-header">
                            <div class="user-summary">
                                <h3>${usuario.nome}</h3>
                                <div class="user-type-tag user-type-${usuario.tipo}">
                                    ${usuario.tipo.charAt(0).toUpperCase() + usuario.tipo.slice(1)}
                                </div>
                                <div class="user-status-badge status-${usuario.status}">
                                    <span>●</span> ${usuario.status.charAt(0).toUpperCase() + usuario.status.slice(1)}
                                </div>
                            </div>
                            <p class="user-email">${usuario.email}</p>
                            <p class="user-id">ID: ${usuario.id}</p>
                        </div>
                        <hr>
                        <div class="info-details">
                            <h4>Informações ${usuario.tipo === 'transportador' ? 'do Transportador' : usuario.tipo === 'vendedor' ? 'do Vendedor' : 'do Comprador'}</h4>
                    `;
                    
                    // Filtra os campos baseados no tipo
                    const camposExibir = camposPorTipo[usuario.tipo] || ['nome', 'email', 'status', 'data_criacao'];
                    let camposEncontrados = 0;
                    
                    // Adiciona apenas os campos específicos do tipo
                    for (const campo of camposExibir) {
                        if (todosDados.hasOwnProperty(campo) && todosDados[campo] !== null) {
                            const valor = todosDados[campo];
                            const valorFormatado = formatarValor(campo, valor);
                            const nomeCampo = nomesCampos[campo] || campo.replace(/_/g, ' ').replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase());
                            
                            // Ignora campos que já foram mostrados no cabeçalho
                            if (campo !== 'nome' && campo !== 'email' && campo !== 'tipo' && campo !== 'status' && campo !== 'id') {
                                html += `
                                    <div class="detail-row">
                                        <span class="detail-label">${nomeCampo}:</span>
                                        <span class="detail-value">${valorFormatado}</span>
                                    </div>
                                `;
                                camposEncontrados++;
                            }
                        }
                    }
                    
                    // Se não encontrou nenhum campo específico, mostra mensagem
                    if (camposEncontrados === 0) {
                        html += `
                            <div class="empty-state" style="padding: 20px; text-align: center;">
                                <p style="color: #7f8c8d;">Nenhum detalhe adicional encontrado para este usuário.</p>
                            </div>
                        `;
                    }
                    
                    // Informações básicas (sempre mostradas)
                    html += `
                        <div class="details-section" style="margin-top: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <h4 style="color: #2c3e50; margin-bottom: 10px;">Informações da Conta</h4>
                            <div class="detail-row">
                                <span class="detail-label">ID do Usuário:</span>
                                <span class="detail-value">${usuario.id}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status da Conta:</span>
                                <span class="detail-value status-${usuario.status}">${usuario.status.charAt(0).toUpperCase() + usuario.status.slice(1)}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Data de Cadastro:</span>
                                <span class="detail-value">${formatarValor('data_criacao', usuario.data_criacao)}</span>
                            </div>
                        </div>
                    `;
                    
                    html += `
                        </div>
                        <div class="modal-footer">
                            <p><em>Clique fora do modal ou no X para fechar</em></p>
                        </div>
                    `;
                    
                    modalCorpo.innerHTML = html;
                })
                .catch(error => {
                    modalCorpo.innerHTML = `
                        <div class="error-message">
                            <h4>Erro ao carregar dados</h4>
                            <p>${error.message}</p>
                        </div>
                    `;
                });
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