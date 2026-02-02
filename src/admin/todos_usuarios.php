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

// --- LÓGICA DE FILTROS ---

$termo_pesquisa = $_GET['pesquisa'] ?? '';
$filtro_tipo = $_GET['filtro_tipo'] ?? '';
$ordenar = $_GET['ordenar'] ?? '';
$orderBy = "data_criacao DESC"; // Padrão

if ($ordenar === "novo_velho") {
    $orderBy = "data_criacao DESC";
} elseif ($ordenar === "velho_novo") {
    $orderBy = "data_criacao ASC";
} elseif ($ordenar === "az") {
    $orderBy = "nome ASC";
} elseif ($ordenar === "za") {
    $orderBy = "nome DESC";
}

// Construção da Query Dinâmica
$sql = "SELECT u.id, u.nome, u.email, u.tipo, u.status, u.data_criacao 
        FROM usuarios u 
        WHERE (u.status = 'ativo' OR u.status = 'inativo')";
$params = [];

// Adiciona filtro de tipo se selecionado
if (!empty($filtro_tipo) && $filtro_tipo !== "todos") {
    $sql .= " AND u.tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

// Adiciona busca por nome, email ou CPF/CNPJ se houver pesquisa
if (!empty(trim($termo_pesquisa))) {
    // Normaliza o termo de pesquisa para busca flexível
    $termo_busca = '%' . trim($termo_pesquisa) . '%';
    
    // Remove caracteres não numéricos para busca por CPF/CNPJ
    $termo_apenas_numeros = preg_replace('/[^0-9]/', '', trim($termo_pesquisa));
    
    $sql .= " AND (u.nome LIKE :pesquisa 
                OR u.email LIKE :pesquisa";
    
    // Busca por CPF/CNPJ apenas se houver números no termo
    if (!empty($termo_apenas_numeros)) {
        $termo_cpf_cnpj = '%' . $termo_apenas_numeros . '%';
        
        // Correção: REMOVA qualquer referência a 'wunnies' e use 'usuarios' ou aliases corretos
        $sql .= " OR EXISTS (SELECT 1 FROM compradores c WHERE c.usuario_id = u.id AND 
                  (REPLACE(REPLACE(REPLACE(c.cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :cpf_cnpj_limpo))";
        
        $sql .= " OR EXISTS (SELECT 1 FROM vendedores v WHERE v.usuario_id = u.id AND 
                  (REPLACE(REPLACE(REPLACE(v.cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :cpf_cnpj_limpo))";
        
        $sql .= " OR EXISTS (SELECT 1 FROM transportadores t WHERE t.usuario_id = u.id AND 
                  (t.numero_antt LIKE :pesquisa
                   OR REPLACE(REPLACE(t.numero_antt, '-', ''), '.', '') LIKE :cpf_cnpj_limpo))";
        
        $params[':cpf_cnpj_limpo'] = $termo_cpf_cnpj;
    }
    
    $sql .= ")";
    
    $params[':pesquisa'] = $termo_busca;
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
    <link rel="stylesheet" href="css/todos_usuarios.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
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
            <a href="chats_admin.php" class="nav-link">Chats</a>
                <a href="manage_comprovantes.php" class="nav-link">Comprovantes</a>
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

    <form method="GET" action="todos_usuarios.php" class="admin-toolbar">
        
        <div class="toolbar-search">
            <input type="text" 
                   name="pesquisa" 
                   class="search-input-inline" 
                   placeholder="Pesquisar por nome, e-mail ou CPF/CNPJ..." 
                   value="<?php echo htmlspecialchars($termo_pesquisa); ?>">
            
            <?php if (!empty($termo_pesquisa)): ?>
                <a href="todos_usuarios.php" class="clear-search-inline" title="Limpar pesquisa">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>

            <button type="submit" class="search-btn-inline">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <div class="toolbar-filters">
            <select name="filtro_tipo" class="filter-select" onchange="this.form.submit()">
                <option value="todos">Todos os Tipos</option>
                <option value="admin" <?= $filtro_tipo === "admin" ? "selected" : "" ?>>Admin</option>
                <option value="comprador" <?= $filtro_tipo === "comprador" ? "selected" : "" ?>>Comprador</option>
                <option value="vendedor" <?= $filtro_tipo === "vendedor" ? "selected" : "" ?>>Vendedor</option>
                <option value="transportador" <?= $filtro_tipo === "transportador" ? "selected" : "" ?>>Transportador</option>
            </select>

            <select name="ordenar" class="filter-select" onchange="this.form.submit()">
                <option value="">Ordenar por</option>
                <option value="novo_velho" <?= $ordenar === "novo_velho" ? "selected" : "" ?>>Mais novo</option>
                <option value="velho_novo" <?= $ordenar === "velho_novo" ? "selected" : "" ?>>Mais velho</option>
                <option value="az" <?= $ordenar === "az" ? "selected" : "" ?>>A → Z</option>
                <option value="za" <?= $ordenar === "za" ? "selected" : "" ?>>Z → A</option>
            </select>
        </div>
    </form>

    <div class="table-responsive">
        <?php if (count($usuarios) > 0): ?>
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
                        
                        <td class="actions-details">
                            <button class="btn btn-secondary btn-sm btn-ver-detalhes" 
                                    data-user-id="<?= $usuario['id']; ?>"
                                    data-user-nome="<?= htmlspecialchars($usuario['nome']); ?>"
                                    data-user-tipo="<?= htmlspecialchars($usuario['tipo']); ?>"
                                    data-user-email="<?= htmlspecialchars($usuario['email']); ?>">
                                Ver Detalhes
                            </button>
                        </td>
                        
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
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
                <h3>Nenhum usuário encontrado</h3>
                <p>Não encontramos resultados para sua busca ou filtros aplicados.</p>
                <?php if(!empty($termo_pesquisa) || !empty($filtro_tipo)): ?>
                    <a href="todos_usuarios.php" class="btn btn-primary" style="margin-top: 15px;">Limpar Filtros</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="detalhesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-titulo">Detalhes do Usuário</h3>
            <span class="user-type-badge" id="modal-tipo-badge"></span>
            <span class="close-button">&times;</span>
        </div>
        <div class="modal-body" id="modal-corpo-completo">
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

    // Mapeamento de campos por tipo de usuário (MESMO SCRIPT DA VERSÃO ANTERIOR)
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
        'admin': [ 'nome', 'email' ]
    };

    const nomesCampos = {
        'nome': 'Nome Completo', 'email': 'E-mail', 'telefone': 'Telefone',
        'telefone1': 'Telefone Principal', 'telefone2': 'Telefone Secundário',
        'telefone1Comprador': 'Telefone Principal', 'telefone2Comprador': 'Telefone Secundário',
        'telefone1Vendedor': 'Telefone Principal', 'telefone2Vendedor': 'Telefone Secundário',
        'telefoneTransportador': 'Telefone', 'tipoPessoaComprador': 'Tipo de Pessoa',
        'cpfCnpjComprador': 'CPF/CNPJ', 'cpfCnpjVendedor': 'CNPJ', 'cpf_cnpj': 'CPF/CNPJ',
        'numeroANTT': 'Número ANTT', 'numero_antt': 'Número ANTT',
        'cipComprador': 'CIP', 'cipVendedor': 'CIP', 'nomeComercialComprador': 'Nome Comercial',
        'nomeComercialVendedor': 'Nome Comercial/Razão Social', 'nome_comercial': 'Nome Comercial',
        'razao_social': 'Razão Social', 'cepComprador': 'CEP', 'cepVendedor': 'CEP', 'cep': 'CEP',
        'ruaComprador': 'Rua', 'ruaVendedor': 'Rua', 'rua': 'Rua',
        'numeroComprador': 'Número', 'numeroVendedor': 'Número', 'numero': 'Número',
        'complementoComprador': 'Complemento', 'complementoVendedor': 'Complemento', 'complemento': 'Complemento',
        'estadoComprador': 'Estado', 'estadoVendedor': 'Estado', 'estadoTransportador': 'Estado', 'estado': 'Estado',
        'cidadeComprador': 'Cidade', 'cidadeVendedor': 'Cidade', 'cidadeTransportador': 'Cidade', 'cidade': 'Cidade',
        'planoComprador': 'Plano', 'planoVendedor': 'Plano', 'plano': 'Plano',
        'placaVeiculo': 'Placa do Veículo', 'placa_veiculo': 'Placa do Veículo',
        'modeloVeiculo': 'Modelo do Veículo', 'modelo_veiculo': 'Modelo do Veículo',
        'descricaoVeiculo': 'Descrição do Veículo', 'descricao_veiculo': 'Descrição do Veículo',
        'data_criacao': 'Data de Cadastro', 'data_atualizacao': 'Última Atualização'
    };

    function formatarValor(campo, valor) {
        if (!valor || valor.toString().trim() === '' || valor === 'null' || valor === 'undefined') return '<span class="detail-value empty">Não informado</span>';
        if ((campo.toLowerCase().includes('cpf') || campo === 'cpf_cnpj') && valor.replace(/\D/g, '').length === 11) {
            return valor.replace(/\D/g, '').replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        if ((campo.toLowerCase().includes('cnpj') || campo === 'cpf_cnpj') && valor.replace(/\D/g, '').length === 14) {
            return valor.replace(/\D/g, '').replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
        }
        if (campo.toLowerCase().includes('cep') && valor.replace(/\D/g, '').length === 8) {
            return valor.replace(/\D/g, '').replace(/(\d{5})(\d{3})/, '$1-$2');
        }
        if ((campo.toLowerCase().includes('telefone') || campo.includes('tel')) && valor.replace(/\D/g, '').length >= 10) {
            let v = valor.replace(/\D/g, '');
            return v.length === 11 ? v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3') : v.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }
        if ((campo.toLowerCase().includes('placa') || campo === 'placa_veiculo') && valor) {
            let v = valor.toUpperCase().replace(/\s/g, '');
            if (v.length === 7) return v.replace(/([A-Z]{3})([A-Z0-9]{4})/, '$1-$2');
            return v;
        }
        if (campo.includes('data') && valor) {
            try {
                const data = new Date(valor);
                if (!isNaN(data.getTime())) return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
            } catch (e) {}
        }
        return valor.toString().trim();
    }

    document.querySelectorAll(".btn-ver-detalhes").forEach(btn => {
        btn.addEventListener("click", function () {
            const userId = this.getAttribute("data-user-id");
            const nome = this.getAttribute("data-user-nome");
            const tipo = this.getAttribute("data-user-tipo").toLowerCase();
            const email = this.getAttribute("data-user-email");
            
            modalTitulo.innerText = `Detalhes do Usuário`;
            modalTipoBadge.innerText = tipo.charAt(0).toUpperCase() + tipo.slice(1);
            modalCorpo.innerHTML = `<div style="text-align: center; padding: 40px;"><div style="font-size: 3rem; color: #4CAF50; margin-bottom: 20px;">⏳</div><p>Carregando detalhes do usuário...</p></div>`;
            modal.style.display = "block";
            
            fetch(`get_user_details.php?id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        modalCorpo.innerHTML = `<div class="error-message"><h4>Erro ao carregar dados</h4><p>${data.error}</p></div>`;
                        return;
                    }
                    
                    const usuario = data.usuario;
                    const detalhes = data.detalhes || {};
                    const todosDados = { ...usuario, ...detalhes };
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
                                    <div class="field-value">${usuario.nome || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Email</div>
                                    <div class="field-value">${usuario.email || 'Não informado'}</div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Status</div>
                                    <div class="field-value">
                                        <span style="background: ${usuario.status === 'ativo' ? '#28a745' : '#dc3545'}; color: white; padding: 4px 12px; border-radius: 20px; display: inline-block; font-size: 0.9rem;">
                                            ${usuario.status.charAt(0).toUpperCase() + usuario.status.slice(1)}
                                        </span>
                                    </div>
                                </div>
                                <div class="data-field">
                                    <div class="field-label">Data de Cadastro</div>
                                    <div class="field-value">${formatarValor('data_criacao', usuario.data_criacao)}</div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // ============ CONTAINER 1.5: FOTOS DE DOCUMENTAÇÃO ============
                    if (usuario.foto_rosto || usuario.foto_documento_frente || usuario.foto_documento_verso) {
                        html += `
                            <div class="data-container pessoal">
                                <div class="container-header">
                                    <span class="container-header-icon">📸</span>
                                    <h4>Fotos de Documentação</h4>
                                </div>
                                <div class="data-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                                    ${usuario.foto_rosto ? `
                                        <div class="data-field" style="text-align: center; padding: 15px; background: white; border: 2px solid #e0e0e0; border-radius: 8px;">
                                            <div class="field-label" style="margin-bottom: 12px; color: #555; font-weight: 600;">Foto do Rosto</div>
                                            <div style="display: flex; justify-content: center; background: #f5f5f5; padding: 10px; border-radius: 6px;">
                                                <img src="../../${usuario.foto_rosto}" alt="Foto do Rosto" style="max-width: 100%; height: auto; max-height: 300px; border-radius: 6px; border: 1px solid #ddd; cursor: pointer;" onclick="abrirImagemGrande('../../${usuario.foto_rosto}', 'Foto do Rosto')">
                                            </div>
                                        </div>
                                    ` : ''}
                                    ${usuario.foto_documento_frente ? `
                                        <div class="data-field" style="text-align: center; padding: 15px; background: white; border: 2px solid #e0e0e0; border-radius: 8px;">
                                            <div class="field-label" style="margin-bottom: 12px; color: #555; font-weight: 600;">Documento - Frente</div>
                                            <div style="display: flex; justify-content: center; background: #f5f5f5; padding: 10px; border-radius: 6px;">
                                                <img src="../../${usuario.foto_documento_frente}" alt="Documento Frente" style="max-width: 100%; height: auto; max-height: 300px; border-radius: 6px; border: 1px solid #ddd; cursor: pointer;" onclick="abrirImagemGrande('../../${usuario.foto_documento_frente}', 'Documento - Frente')">
                                            </div>
                                        </div>
                                    ` : ''}
                                    ${usuario.foto_documento_verso ? `
                                        <div class="data-field" style="text-align: center; padding: 15px; background: white; border: 2px solid #e0e0e0; border-radius: 8px;">
                                            <div class="field-label" style="margin-bottom: 12px; color: #555; font-weight: 600;">Documento - Verso</div>
                                            <div style="display: flex; justify-content: center; background: #f5f5f5; padding: 10px; border-radius: 6px;">
                                                <img src="../../${usuario.foto_documento_verso}" alt="Documento Verso" style="max-width: 100%; height: auto; max-height: 300px; border-radius: 6px; border: 1px solid #ddd; cursor: pointer;" onclick="abrirImagemGrande('../../${usuario.foto_documento_verso}', 'Documento - Verso')">
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    }
                    
                    // ============ CONTAINER 2: DADOS ESPECÍFICOS POR TIPO ============
                    let dadosEspecificos = '';
                    
                    if (tipo === 'comprador') {
                        dadosEspecificos = `
                            <div class="data-container especifico">
                                <div class="container-header">
                                    <span class="container-header-icon">🛒</span>
                                    <h4>Dados do Comprador</h4>
                                </div>
                                <div class="data-grid">
                                    <div class="data-field">
                                        <div class="field-label">Tipo de Pessoa</div>
                                        <div class="field-value">${(detalhes.tipo_pessoa || detalhes.tipoPessoaComprador || 'Não informado').toUpperCase()}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">CPF/CNPJ</div>
                                        <div class="field-value">${detalhes.cpf_cnpj || detalhes.cpfCnpjComprador || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Nome Comercial</div>
                                        <div class="field-value">${detalhes.nome_comercial || detalhes.nomeComercialComprador || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Telefone Principal</div>
                                        <div class="field-value">${detalhes.telefone1 || detalhes.telefone1Comprador || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Telefone Secundário</div>
                                        <div class="field-value ${!detalhes.telefone2 && !detalhes.telefone2Comprador ? 'empty' : ''}">${detalhes.telefone2 || detalhes.telefone2Comprador || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Plano</div>
                                        <div class="field-value">${detalhes.plano || detalhes.planoComprador || 'Não informado'}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else if (tipo === 'vendedor') {
                        dadosEspecificos = `
                            <div class="data-container especifico">
                                <div class="container-header">
                                    <span class="container-header-icon">🏪</span>
                                    <h4>Dados do Vendedor</h4>
                                </div>
                                <div class="data-grid">
                                    <div class="data-field">
                                        <div class="field-label">Nome Comercial</div>
                                        <div class="field-value">${detalhes.nome_comercial || detalhes.nomeComercialVendedor || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">CNPJ</div>
                                        <div class="field-value">${detalhes.cpf_cnpj || detalhes.cpfCnpjVendedor || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Razão Social</div>
                                        <div class="field-value ${!detalhes.razao_social && !detalhes.cipVendedor ? 'empty' : ''}">${detalhes.razao_social || detalhes.cipVendedor || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Telefone Principal</div>
                                        <div class="field-value">${detalhes.telefone1 || detalhes.telefone1Vendedor || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Telefone Secundário</div>
                                        <div class="field-value ${!detalhes.telefone2 && !detalhes.telefone2Vendedor ? 'empty' : ''}">${detalhes.telefone2 || detalhes.telefone2Vendedor || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Plano</div>
                                        <div class="field-value">${detalhes.plano || detalhes.planoVendedor || 'Não informado'}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else if (tipo === 'transportador') {
                        dadosEspecificos = `
                            <div class="data-container especifico">
                                <div class="container-header">
                                    <span class="container-header-icon">🚚</span>
                                    <h4>Dados do Transportador</h4>
                                </div>
                                <div class="data-grid">
                                    <div class="data-field">
                                        <div class="field-label">Telefone</div>
                                        <div class="field-value">${detalhes.telefone || detalhes.telefoneTransportador || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Número ANTT</div>
                                        <div class="field-value">${detalhes.numero_antt || detalhes.numeroANTT || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Placa do Veículo</div>
                                        <div class="field-value">${detalhes.placa_veiculo || detalhes.placaVeiculo || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Modelo do Veículo</div>
                                        <div class="field-value">${detalhes.modelo_veiculo || detalhes.modeloVeiculo || 'Não informado'}</div>
                                    </div>
                                    <div class="data-field">
                                        <div class="field-label">Descrição do Veículo</div>
                                        <div class="field-value">${detalhes.descricao_veiculo || detalhes.descricaoVeiculo || 'Não informado'}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    html += dadosEspecificos;
                    
                    // ============ CONTAINER 3: DADOS DE ENDEREÇO ============
                    let temEndereco = false;
                    let enderecoCampos = {};
                    
                    if (tipo === 'comprador' || tipo === 'vendedor' || tipo === 'transportador') {
                        enderecoCampos = {
                            cep: detalhes.cep || detalhes[tipo === 'comprador' ? 'cepComprador' : tipo === 'vendedor' ? 'cepVendedor' : 'cepTransportador'],
                            rua: detalhes.rua || detalhes[tipo === 'comprador' ? 'ruaComprador' : tipo === 'vendedor' ? 'ruaVendedor' : 'ruaTransportador'],
                            numero: detalhes.numero || detalhes[tipo === 'comprador' ? 'numeroComprador' : tipo === 'vendedor' ? 'numeroVendedor' : 'numeroTransportador'],
                            complemento: detalhes.complemento || detalhes[tipo === 'comprador' ? 'complementoComprador' : tipo === 'vendedor' ? 'complementoVendedor' : 'complementoTransportador'],
                            estado: detalhes.estado || detalhes[tipo === 'comprador' ? 'estadoComprador' : tipo === 'vendedor' ? 'estadoVendedor' : 'estadoTransportador'],
                            cidade: detalhes.cidade || detalhes[tipo === 'comprador' ? 'cidadeComprador' : tipo === 'vendedor' ? 'cidadeVendedor' : 'cidadeTransportador']
                        };
                        temEndereco = Object.values(enderecoCampos).some(v => v);
                    }
                    
                    if (temEndereco) {
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
                    }
                    
                    modalCorpo.innerHTML = html;
                })
                .catch(error => { modalCorpo.innerHTML = `<div class="error-message"><h4>Erro ao carregar dados</h4><p>${error.message}</p></div>`; });
        });
    });

    closeBtn.addEventListener("click", () => { modal.style.display = "none"; });
    window.addEventListener("click", e => { if (e.target === modal) modal.style.display = "none"; });
    document.addEventListener("keydown", e => { if (e.key === "Escape" && modal.style.display === "block") modal.style.display = "none"; });
});

// Função para abrir imagem em tamanho grande
function abrirImagemGrande(src, titulo) {
    const modal = document.createElement('div');
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); display: flex; align-items: center; justify-content: center; z-index: 9999; cursor: pointer;';
    
    const img = document.createElement('img');
    img.src = src;
    img.style.cssText = 'max-width: 90%; max-height: 90%; border-radius: 8px;';
    img.title = 'Clique para fechar';
    
    const titulo_div = document.createElement('div');
    titulo_div.textContent = titulo;
    titulo_div.style.cssText = 'position: absolute; top: 20px; left: 50%; transform: translateX(-50%); color: white; font-size: 1.2rem; background: rgba(0,0,0,0.7); padding: 10px 20px; border-radius: 4px;';
    
    modal.appendChild(img);
    modal.appendChild(titulo_div);
    
    modal.addEventListener('click', () => modal.remove());
    document.body.appendChild(modal);
}
</script>

</body>
</html>