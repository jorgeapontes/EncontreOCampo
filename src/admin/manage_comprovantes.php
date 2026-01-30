<?php
session_start();

require_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$aba_atual = $_GET['aba'] ?? 'entrega'; // entrega ou acordo

try {
    // Parâmetros de busca e filtro de data
    $pesquisa = trim($_GET['pesquisa'] ?? '');
    $data_inicio = $_GET['data_inicio'] ?? '';
    $data_fim = $_GET['data_fim'] ?? '';

    if ($aba_atual == 'acordo') {
        // Buscar propostas que têm assinaturas, agrupadas por proposta
        // Construir query dinâmica para acordos com filtros de pesquisa e data
        $params = [];
        $sql = "SELECT pr.ID as proposta_id, pr.preco_proposto, pr.quantidade_proposta, 
                       pr.forma_pagamento, pr.opcao_frete, pr.valor_total, pr.status,
                       pr.data_inicio, pr.data_atualizacao,
                       p.nome as produto_nome, p.preco as produto_preco_original, p.descricao as produto_descricao,
                       v.nome_comercial as vendedor_nome, v.cidade as vendedor_cidade, v.estado as vendedor_estado,
                       v.telefone1 as vendedor_telefone,
                       COALESCE(c.nome_comercial, u_comp.nome) as comprador_nome,
                       c.cidade as comprador_cidade, c.estado as comprador_estado,
                       c.telefone1 as comprador_telefone,
                       (SELECT COUNT(*) FROM propostas_assinaturas pa WHERE pa.proposta_id = pr.ID) as total_assinaturas
                FROM propostas pr
                INNER JOIN produtos p ON pr.produto_id = p.id
                INNER JOIN vendedores v ON p.vendedor_id = v.id
                LEFT JOIN compradores c ON c.usuario_id = pr.comprador_id
                LEFT JOIN usuarios u_comp ON u_comp.id = pr.comprador_id
                WHERE EXISTS (SELECT 1 FROM propostas_assinaturas pa WHERE pa.proposta_id = pr.ID)";

        if (!empty($pesquisa)) {
            $params[':pesquisa'] = '%' . $pesquisa . '%';
            $apenas_numeros = preg_replace('/[^0-9]/', '', $pesquisa);

            $search_clause = " AND (p.nome LIKE :pesquisa OR v.nome_comercial LIKE :pesquisa OR COALESCE(c.nome_comercial, u_comp.nome) LIKE :pesquisa";

            if (!empty($apenas_numeros)) {
                $cpf_like = '%' . $apenas_numeros . '%';
                $search_clause .= " OR EXISTS (SELECT 1 FROM compradores c2 WHERE c2.usuario_id = pr.comprador_id AND REPLACE(REPLACE(REPLACE(c2.cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :cpf)";
                $search_clause .= " OR REPLACE(REPLACE(REPLACE(v.cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :cpf_v";
                $params[':cpf'] = $cpf_like;
                $params[':cpf_v'] = $cpf_like;
            }

            $search_clause .= ")";
            $sql .= $search_clause;
        }

        // Filtro por data de início da proposta
        if (!empty($data_inicio)) {
            $sql .= " AND pr.data_inicio >= :data_inicio";
            $params[':data_inicio'] = $data_inicio . ' 00:00:00';
        }
        if (!empty($data_fim)) {
            $sql .= " AND pr.data_inicio <= :data_fim";
            $params[':data_fim'] = $data_fim . ' 23:59:59';
        }

        $sql .= " ORDER BY pr.data_inicio DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Para cada proposta, buscar as assinaturas correspondentes
        foreach ($propostas as &$proposta) {
            $sql_assinaturas = "SELECT pa.*, u.nome as nome_assinante, u.tipo as tipo_assinante
                                FROM propostas_assinaturas pa
                                INNER JOIN usuarios u ON pa.usuario_id = u.id
                                WHERE pa.proposta_id = :proposta_id
                                ORDER BY pa.data_assinatura";
            
            $stmt_assinaturas = $db->prepare($sql_assinaturas);
            $stmt_assinaturas->bindParam(':proposta_id', $proposta['proposta_id'], PDO::PARAM_INT);
            $stmt_assinaturas->execute();
            $proposta['assinaturas'] = $stmt_assinaturas->fetchAll(PDO::FETCH_ASSOC);
        }
        
        unset($proposta); // Quebrar referência
        
        $titulo_aba = "Comprovantes de Acordo de Compra";
        $descricao_aba = "Visualização dos acordos de compra assinados digitalmente";
    } else {
        // Buscar comprovantes de entrega (original) com filtros
        $params = [];
        $sql = "SELECT e.id, e.endereco_origem, e.endereco_destino, e.valor_frete, e.data_entrega, e.foto_comprovante, e.assinatura_comprovante,
                p.nome as produto_nome,
                COALESCE(c.nome_comercial, u.nome) as comprador_nome,
                v.nome_comercial as vendedor_nome, v.cep as vendedor_cep, v.rua as vendedor_rua, v.numero as vendedor_numero, v.cidade as vendedor_cidade, v.estado as vendedor_estado
            FROM entregas e
            LEFT JOIN produtos p ON e.produto_id = p.id
            LEFT JOIN compradores c ON c.usuario_id = e.comprador_id
            LEFT JOIN usuarios u ON u.id = e.comprador_id
            LEFT JOIN vendedores v ON v.id = COALESCE(e.vendedor_id, p.vendedor_id)
            WHERE (e.foto_comprovante IS NOT NULL OR e.assinatura_comprovante IS NOT NULL)";

        if (!empty($pesquisa)) {
            $params[':pesquisa'] = '%' . $pesquisa . '%';
            $apenas_numeros = preg_replace('/[^0-9]/', '', $pesquisa);

            $search_clause = " AND (COALESCE(c.nome_comercial, u.nome) LIKE :pesquisa OR v.nome_comercial LIKE :pesquisa OR p.nome LIKE :pesquisa";
            if (!empty($apenas_numeros)) {
                $cpf_like = '%' . $apenas_numeros . '%';
                $search_clause .= " OR REPLACE(REPLACE(REPLACE(c.cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :cpf_c";
                $search_clause .= " OR REPLACE(REPLACE(REPLACE(v.cpf_cnpj, '.', ''), '-', ''), '/', '') LIKE :cpf_v";
                $params[':cpf_c'] = $cpf_like;
                $params[':cpf_v'] = $cpf_like;
            }
            $search_clause .= ")";
            $sql .= $search_clause;
        }

        if (!empty($data_inicio)) {
            $sql .= " AND e.data_entrega >= :data_inicio";
            $params[':data_inicio'] = $data_inicio . ' 00:00:00';
        }
        if (!empty($data_fim)) {
            $sql .= " AND e.data_entrega <= :data_fim";
            $params[':data_fim'] = $data_fim . ' 23:59:59';
        }

        $sql .= " ORDER BY e.data_entrega DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $comprovantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $titulo_aba = "Comprovantes de Entrega";
        $descricao_aba = "Visualização das fotos enviadas pelos entregadores";
    }
} catch (PDOException $e) {
    die("Erro ao buscar comprovantes: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovantes - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #ddd; text-align: left; }
        img.thumb { max-width: 120px; max-height: 90px; object-fit: cover; border-radius: 6px; }
        .preview-link { display:inline-block; }
        
        /* Abas */
        .abas-container { margin-bottom: 20px; }
        .abas-navegacao { display: flex; border-bottom: 2px solid #ddd; }
        .aba-link { 
            padding: 12px 20px; 
            background: none; 
            border: none; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: 500;
            color: #666;
            position: relative;
            transition: all 0.3s ease;
        }
        .aba-link:hover { color: #4CAF50; }
        .aba-link.active { 
            color: #4CAF50; 
            font-weight: 600;
        }
        .aba-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #4CAF50;
        }
        
        /* Contadores de abas */
        .contador-aba {
            display: inline-block;
            background-color: #e0e0e0;
            color: #555;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 12px;
            margin-left: 6px;
            font-weight: 500;
        }
        .aba-link.active .contador-aba {
            background-color: #4CAF50;
            color: white;
        }
        
        /* Estilo específico para acordo */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-aceita { background-color: #d4edda; color: #155724; }
        .status-negociacao { background-color: #fff3cd; color: #856404; }
        .status-recusada { background-color: #f8d7da; color: #721c24; }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 25px;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .modal-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .modal-body {
            padding: 10px 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .info-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #4CAF50;
        }
        .info-card h4 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .assinaturas-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        .assinatura-card {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 15px;
            min-width: 250px;
            flex: 1;
        }
        .assinatura-img {
            max-width: 100%;
            max-height: 150px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        /* Botão ver detalhes */
        .ver-detalhes-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.3s;
        }
        .ver-detalhes-btn:hover {
            background: #45a049;
        }
        
        /* Miniaturas de assinatura lado a lado */
        .assinaturas-mini {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .assinatura-mini {
            display: inline-block;
            position: relative;
        }
        .assinatura-mini img {
            width: 60px;
            height: 45px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .badge-tipo {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #4CAF50;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .badge-comprador { background: #2196F3; }
        .badge-vendedor { background: #FF9800; }
        .badge-transportador { background: #9C27B0; }
    </style>
</head>
<body>
<!-- NAVBAR padrão admin -->
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <img src="../../img/logo-nova.png" class="logo" alt="Logo">
            <div>
                <h1>ENCONTRE</h1>
                <h2>O CAMPO</h2>
            </div>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="todos_usuarios.php" class="nav-link">Todos os Usuários</a>
            <a href="chats_admin.php" class="nav-link">Chats</a>
            <a href="manage_comprovantes.php" class="nav-link active">Comprovantes</a>
            <a href="../../index.php" class="nav-link">Home</a>
            <a href="../logout.php" class="nav-link logout">Sair</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="header-section">
        <h1>Comprovantes</h1>
        <p><?php echo $descricao_aba; ?></p>
    </div>

    <form method="GET" action="manage_comprovantes.php" class="admin-toolbar">
        <input type="hidden" name="aba" value="<?php echo htmlspecialchars($aba_atual); ?>">

        <div class="toolbar-search">
            <input type="text"
                   name="pesquisa"
                   class="search-input-inline"
                   placeholder="Pesquisar por nome, CPF ou CNPJ..."
                   value="<?php echo htmlspecialchars($pesquisa ?? ''); ?>">

            <?php if (!empty($pesquisa)): ?>
                <a href="manage_comprovantes.php?aba=<?php echo urlencode($aba_atual); ?>" class="clear-search-inline" title="Limpar pesquisa">
                    <i class="fas fa-times"></i>
                </a>
            <?php endif; ?>

            <button type="submit" class="search-btn-inline">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <div class="toolbar-filters">
            <input type="date" name="data_inicio" class="filter-select" value="<?php echo htmlspecialchars($data_inicio ?? ''); ?>">
            <input type="date" name="data_fim" class="filter-select" value="<?php echo htmlspecialchars($data_fim ?? ''); ?>">
            <button type="submit" class="filter-select" style="background:#4CAF50;color:#fff;border:none;">Aplicar</button>
        </div>
    </form>

    <!-- Navegação por abas -->
    <div class="abas-container">
        <div class="abas-navegacao">
            <a href="?aba=entrega" class="aba-link <?php echo $aba_atual == 'entrega' ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i> Entrega
                <?php if ($aba_atual == 'entrega'): ?>
                    <span class="contador-aba"><?php echo count($comprovantes); ?></span>
                <?php endif; ?>
            </a>
            <a href="?aba=acordo" class="aba-link <?php echo $aba_atual == 'acordo' ? 'active' : ''; ?>">
                <i class="fas fa-file-signature"></i> Acordo de Compra
                <?php if ($aba_atual == 'acordo'): ?>
                    <span class="contador-aba"><?php echo count($propostas); ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <main>
        <?php if ($aba_atual == 'entrega'): ?>
            <!-- ABA ENTREGA -->
            <?php if (empty($comprovantes)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                    <p>Nenhum comprovante de entrega encontrado.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Produto</th>
                                <th>Comprador</th>
                                <th>Vendedor</th>
                                <th>Data Entrega</th>
                                <th>Endereço Origem</th>
                                <th>Endereço Destino</th>
                                <th>Foto</th>
                                <th>Assinatura</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($comprovantes as $c): ?>
                            <?php
                                $origem_full = '';
                                if (!empty(trim($c['endereco_origem'] ?? ''))) {
                                    $origem_full = $c['endereco_origem'];
                                } else {
                                    $origem_full = (trim($c['vendedor_rua'] ?? '') !== '' ? ($c['vendedor_rua'] . ', ') : '')
                                        . ($c['vendedor_numero'] ?? '')
                                        . (isset($c['vendedor_cidade']) ? ' - ' . $c['vendedor_cidade'] : '')
                                        . (isset($c['vendedor_estado']) ? '/' . $c['vendedor_estado'] : '')
                                        . (!empty($c['vendedor_cep'] ?? '') ? ' - CEP: ' . $c['vendedor_cep'] : '');
                                }
                            ?>
                            <tr>
                                <td><?php echo $c['id']; ?></td>
                                <td><?php echo htmlspecialchars($c['produto_nome'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($c['comprador_nome'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($c['vendedor_nome'] ?? '—'); ?></td>
                                <td><?php echo ($c['data_entrega'] ? date('d/m/Y H:i', strtotime($c['data_entrega'])) : '-'); ?></td>
                                <?php
                                    $orig_display = $origem_full ?: '-';
                                    $orig_query = rawurlencode($origem_full ?: ($c['endereco_origem'] ?? ''));
                                    $dest_full = trim($c['endereco_destino'] ?? '');
                                    $dest_query = rawurlencode($dest_full ?: '');
                                ?>
                                <td>
                                    <?php if (!empty(trim($orig_display)) && $orig_display !== '-'): ?>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $orig_query; ?>" target="_blank" title="<?php echo htmlspecialchars($orig_display); ?>">
                                            <?php echo htmlspecialchars(mb_substr($orig_display, 0, 40)); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($dest_full)): ?>
                                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $dest_query; ?>" target="_blank" title="<?php echo htmlspecialchars($dest_full); ?>">
                                            <?php echo htmlspecialchars(mb_substr($dest_full, 0, 40)); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['foto_comprovante'])): ?>
                                        <a class="preview-link" href="../../uploads/entregas/<?php echo htmlspecialchars($c['foto_comprovante']); ?>" target="_blank" rel="noopener noreferrer" title="Foto do comprovante">
                                            <img class="thumb" src="../../uploads/entregas/<?php echo htmlspecialchars($c['foto_comprovante']); ?>" alt="Comprovante">
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['assinatura_comprovante'])): ?>
                                        <a class="preview-link" href="../../uploads/entregas/<?php echo htmlspecialchars($c['assinatura_comprovante']); ?>" target="_blank" rel="noopener noreferrer" title="Assinatura do recebedor">
                                            <img class="thumb" src="../../uploads/entregas/<?php echo htmlspecialchars($c['assinatura_comprovante']); ?>" alt="Assinatura">
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- ABA ACORDO DE COMPRA -->
            <?php if (empty($propostas)): ?>
                <div class="no-data">
                    <i class="fas fa-file-signature" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                    <p>Nenhum acordo de compra assinado encontrado.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>ID Proposta</th>
                                <th>Produto</th>
                                <th>Comprador</th>
                                <th>Vendedor</th>
                                <th>Valor Total</th>
                                <th>Status</th>
                                <th>Data Proposta</th>
                                <th>Assinaturas</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($propostas as $proposta): ?>
                            <tr>
                                <td>#<?php echo $proposta['proposta_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($proposta['produto_nome'] ?? '—'); ?></strong><br>
                                    <small style="color: #666;"><?php echo $proposta['quantidade_proposta']; ?> unidades</small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($proposta['comprador_nome'] ?? '—'); ?><br>
                                    <?php if (!empty($proposta['comprador_telefone'])): ?>
                                        <small style="color: #666;"><?php echo $proposta['comprador_telefone']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($proposta['vendedor_nome'] ?? '—'); ?><br>
                                    <small style="color: #666;">
                                        <?php echo $proposta['vendedor_cidade'] ?? ''; ?>/<?php echo $proposta['vendedor_estado'] ?? ''; ?>
                                    </small>
                                </td>
                                <td>
                                    <strong>R$ <?php echo number_format($proposta['valor_total'] ?? 0, 2, ',', '.'); ?></strong><br>
                                    <small style="color: #666;">
                                        Preço unit.: R$ <?php echo number_format($proposta['preco_proposto'] ?? 0, 2, ',', '.'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php 
                                        $status_class = '';
                                        $status_text = $proposta['status'] ?? 'pendente';
                                        switch($status_text) {
                                            case 'aceita': $status_class = 'status-aceita'; break;
                                            case 'negociacao': $status_class = 'status-negociacao'; break;
                                            case 'recusada': $status_class = 'status-recusada'; break;
                                            default: $status_class = ''; break;
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($status_text); ?>
                                    </span><br>
                                    <small style="color: #666;">
                                        <?php echo $proposta['forma_pagamento'] ?? ''; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($proposta['data_inicio'])); ?><br>
                                    <small style="color: #666;">
                                        Atualizado: <?php echo date('d/m/Y', strtotime($proposta['data_atualizacao'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="assinaturas-mini">
                                        <?php if (!empty($proposta['assinaturas'])): ?>
                                            <?php foreach ($proposta['assinaturas'] as $assinatura): ?>
                                                <?php 
                                                    $badge_class = 'badge-comprador';
                                                    if ($assinatura['tipo_assinante'] == 'vendedor') {
                                                        $badge_class = 'badge-vendedor';
                                                    } elseif ($assinatura['tipo_assinante'] == 'transportador') {
                                                        $badge_class = 'badge-transportador';
                                                    }
                                                    
                                                    $inicial = strtoupper(substr($assinatura['tipo_assinante'], 0, 1));
                                                ?>
                                                <?php 
                                                    // Verificar se a assinatura está no formato correto
                                                    $imagem_assinatura = $assinatura['assinatura_imagem'];
                                                    
                                                    // Se NÃO começa com data:image, adicionar o prefixo
                                                    if (!empty($imagem_assinatura) && strpos($imagem_assinatura, 'data:image') !== 0) {
                                                        // Adicionar o prefixo base64
                                                        $imagem_assinatura = 'data:image/png;base64,' . $imagem_assinatura;
                                                    }
                                                    
                                                    // Para a miniatura
                                                    if (!empty($imagem_assinatura)): 
                                                ?>
                                                    <div class="assinatura-mini">
                                                        <img src="<?php echo htmlspecialchars($imagem_assinatura); ?>" 
                                                            alt="<?php echo htmlspecialchars($assinatura['nome_assinante']); ?>">
                                                        <div class="badge-tipo <?php echo $badge_class; ?>" 
                                                            title="<?php echo ucfirst($assinatura['tipo_assinante']); ?>">
                                                            <?php echo $inicial; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Mostrar ícone quando não houver imagem -->
                                                    <div style="width: 60px; height: 45px; background: #eee; border-radius: 4px; 
                                                                display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-signature" style="color: #999;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #999; font-style: italic;">Nenhuma</span>
                                        <?php endif; ?>
                                    </div>
                                    <small style="color: #666; display: block; margin-top: 5px;">
                                        <?php echo $proposta['total_assinaturas']; ?> assinatura(s)
                                    </small>
                                </td>
                                <td>
                                    <button class="ver-detalhes-btn" onclick="verDetalhesProposta(<?php echo htmlspecialchars(json_encode($proposta)); ?>)">
                                        Ver Detalhes
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- Modal para detalhes da proposta -->
<div id="modalDetalhes" class="modal-overlay">
    <div class="modal-content">
        <button class="modal-close" onclick="fecharModal()">&times;</button>
        <div class="modal-header">
            <h3>Detalhes da Proposta <span id="modalPropostaId"></span></h3>
            <p id="modalProdutoNome"></p>
        </div>
        <div class="modal-body">
            <div class="info-grid">
                <div class="info-card">
                    <h4>Informações do Produto</h4>
                    <p><strong>Nome:</strong> <span id="modalProdutoNomeDetalhes"></span></p>
                    <p><strong>Preço Original:</strong> <span id="modalProdutoPreco"></span></p>
                    <p><strong>Quantidade:</strong> <span id="modalQuantidade"></span> unidades</p>
                    <p><strong>Preço Proposto:</strong> <span id="modalPrecoProposto"></span> por unidade</p>
                </div>
                
                <div class="info-card">
                    <h4>Valores</h4>
                    <p><strong>Valor Total:</strong> <span id="modalValorTotal"></span></p>
                    <p><strong>Forma de Pagamento:</strong> <span id="modalFormaPagamento"></span></p>
                    <p><strong>Opção de Frete:</strong> <span id="modalOpcaoFrete"></span></p>
                    <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                </div>
                
                <div class="info-card">
                    <h4>Comprador</h4>
                    <p><strong>Nome:</strong> <span id="modalCompradorNome"></span></p>
                    <p><strong>Cidade/Estado:</strong> <span id="modalCompradorLocal"></span></p>
                    <p><strong>Telefone:</strong> <span id="modalCompradorTelefone"></span></p>
                </div>
                
                <div class="info-card">
                    <h4>Vendedor</h4>
                    <p><strong>Nome Comercial:</strong> <span id="modalVendedorNome"></span></p>
                    <p><strong>Cidade/Estado:</strong> <span id="modalVendedorLocal"></span></p>
                    <p><strong>Telefone:</strong> <span id="modalVendedorTelefone"></span></p>
                </div>
            </div>
            
            <div class="info-card">
                <h4>Datas</h4>
                <p><strong>Criação:</strong> <span id="modalDataInicio"></span></p>
                <p><strong>Última Atualização:</strong> <span id="modalDataAtualizacao"></span></p>
            </div>
            
            <div class="info-card">
                <h4>Assinaturas Digitais</h4>
                <div class="assinaturas-container" id="modalAssinaturasContainer">
                    <!-- Assinaturas serão inseridas aqui via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Modal functions
    function verDetalhesProposta(proposta) {
        // Preencher informações básicas
        document.getElementById('modalPropostaId').textContent = '#' + proposta.proposta_id;
        document.getElementById('modalProdutoNome').textContent = proposta.produto_nome;
        document.getElementById('modalProdutoNomeDetalhes').textContent = proposta.produto_nome;
        document.getElementById('modalProdutoPreco').textContent = 'R$ ' + parseFloat(proposta.produto_preco_original).toFixed(2).replace('.', ',');
        document.getElementById('modalQuantidade').textContent = proposta.quantidade_proposta;
        document.getElementById('modalPrecoProposto').textContent = 'R$ ' + parseFloat(proposta.preco_proposto).toFixed(2).replace('.', ',');
        document.getElementById('modalValorTotal').textContent = 'R$ ' + parseFloat(proposta.valor_total).toFixed(2).replace('.', ',');
        document.getElementById('modalFormaPagamento').textContent = proposta.forma_pagamento || 'Não informado';
        document.getElementById('modalOpcaoFrete').textContent = proposta.opcao_frete || 'Não informado';
        document.getElementById('modalStatus').textContent = proposta.status;
        document.getElementById('modalCompradorNome').textContent = proposta.comprador_nome || 'Não informado';
        document.getElementById('modalCompradorLocal').textContent = (proposta.comprador_cidade || '') + '/' + (proposta.comprador_estado || '');
        document.getElementById('modalCompradorTelefone').textContent = proposta.comprador_telefone || 'Não informado';
        document.getElementById('modalVendedorNome').textContent = proposta.vendedor_nome || 'Não informado';
        document.getElementById('modalVendedorLocal').textContent = (proposta.vendedor_cidade || '') + '/' + (proposta.vendedor_estado || '');
        document.getElementById('modalVendedorTelefone').textContent = proposta.vendedor_telefone || 'Não informado';
        
        // Formatando datas
        const dataInicio = new Date(proposta.data_inicio);
        const dataAtualizacao = new Date(proposta.data_atualizacao);
        document.getElementById('modalDataInicio').textContent = dataInicio.toLocaleDateString('pt-BR') + ' ' + dataInicio.toLocaleTimeString('pt-BR');
        document.getElementById('modalDataAtualizacao').textContent = dataAtualizacao.toLocaleDateString('pt-BR') + ' ' + dataAtualizacao.toLocaleTimeString('pt-BR');
        
        // Preencher assinaturas
        const container = document.getElementById('modalAssinaturasContainer');
        container.innerHTML = '';
        
        if (proposta.assinaturas && proposta.assinaturas.length > 0) {
            proposta.assinaturas.forEach(assinatura => {
                const card = document.createElement('div');
                card.className = 'assinatura-card';
                
                let badgeClass = 'badge-comprador';
                let badgeText = 'C';
                if (assinatura.tipo_assinante === 'vendedor') {
                    badgeClass = 'badge-vendedor';
                    badgeText = 'V';
                } else if (assinatura.tipo_assinante === 'transportador') {
                    badgeClass = 'badge-transportador';
                    badgeText = 'T';
                }
                
                const dataAssinatura = new Date(assinatura.data_assinatura);
                
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0;">${assinatura.nome_assinante || 'Não informado'}</h4>
                        <span class="badge-tipo ${badgeClass}" style="position: relative; top: 0; right: 0;">
                            ${badgeText}
                        </span>
                    </div>
                    <p style="margin: 5px 0; font-size: 13px; color: #666;">
                        <strong>Tipo:</strong> ${assinatura.tipo_assinante || 'Não informado'}
                    </p>
                    ${assinatura.assinatura_imagem ? 
                        (function() {
                            // Corrigir o formato base64 se necessário
                            let imagemSrc = assinatura.assinatura_imagem;
                            if (!imagemSrc.startsWith('data:image')) {
                                imagemSrc = 'data:image/png;base64,' + imagemSrc;
                            }
                            return `<img class="assinatura-img" src="${imagemSrc}" alt="Assinatura de ${assinatura.nome_assinante}">`;
                        })() : 
                        '<div style="padding: 20px; background: #eee; text-align: center; border-radius: 5px; margin: 10px 0;">' +
                        '<i class="fas fa-signature" style="font-size: 40px; color: #999;"></i><br>' +
                        '<span style="color: #999;">Assinatura sem imagem</span></div>'
                    }
                    <p style="margin: 5px 0; font-size: 12px; color: #666;">
                        <strong>Data:</strong> ${dataAssinatura.toLocaleDateString('pt-BR')} ${dataAssinatura.toLocaleTimeString('pt-BR')}
                    </p>
                `;
                
                container.appendChild(card);
            });
        } else {
            container.innerHTML = '<p style="color: #999; font-style: italic; text-align: center; padding: 20px;">Nenhuma assinatura encontrada para esta proposta.</p>';
        }
        
        // Mostrar modal
        document.getElementById('modalDetalhes').style.display = 'flex';
    }
    
    function fecharModal() {
        document.getElementById('modalDetalhes').style.display = 'none';
    }
    
    // Fechar modal ao clicar fora
    document.getElementById('modalDetalhes').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModal();
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharModal();
        }
    });
</script>
</body>
</html>