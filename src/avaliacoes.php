<?php
// avaliacoes.php - Página reutilizável para exibir todas as avaliações
session_start();
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/permissions.php';

// Verificar acesso
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor','transportador'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
}

// Verificar parâmetros obrigatórios
if (!isset($_GET['tipo']) || !isset($_GET['id'])) {
    header("Location: dashboard.php?erro=" . urlencode("Parâmetros inválidos."));
    exit();
}

$tipo_avaliacao = $_GET['tipo']; // 'produto', 'vendedor', 'comprador', 'transportador'
$id_referencia = (int)$_GET['id']; // ID do produto, vendedor, etc.
$pagina_titulo = '';
$subtitulo = '';
$mostrar_botao_avaliar = false;
$comprador_id_tabela = null;
$transportador_id_tabela = null;
$vendedor_id_tabela = null;  // 

$database = new Database();
$conn = $database->getConnection();

// Configurar título e consulta baseado no tipo
switch ($tipo_avaliacao) {
    case 'produto':
        // Buscar informações do produto
        try {
            $sql_produto = "SELECT p.nome, p.id as produto_id, v.nome_comercial, v.id as vendedor_id FROM produtos p 
                           JOIN vendedores v ON p.vendedor_id = v.id 
                           WHERE p.id = :id AND p.status = 'ativo'";
            $stmt_produto = $conn->prepare($sql_produto);
            $stmt_produto->bindParam(':id', $id_referencia, PDO::PARAM_INT);
            $stmt_produto->execute();
            
            if ($produto = $stmt_produto->fetch(PDO::FETCH_ASSOC)) {
                $pagina_titulo = "Avaliações do Produto: " . htmlspecialchars($produto['nome']);
                $subtitulo = "Vendedor: " . htmlspecialchars($produto['nome_comercial']);
                $produto_id = $produto['produto_id'];
                $vendedor_id = $produto['vendedor_id'];
                
                // Verificar se o usuário atual pode avaliar este produto
                if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo' && $_SESSION['usuario_tipo'] === 'comprador') {
                    $usuario_logado = $_SESSION['usuario_id'];
                    
                    // Verificar se o usuário já comprou este produto (mesma lógica do view_ad.php)
                    $sql_check = "SELECT p.opcao_frete, p.comprador_id FROM propostas p 
                                 LEFT JOIN compradores c ON p.comprador_id = c.id 
                                 WHERE p.produto_id = :produto_id 
                                 AND (p.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) 
                                 AND p.status = 'aceita' 
                                 ORDER BY p.data_inicio DESC LIMIT 1";
                    $stc = $conn->prepare($sql_check);
                    $stc->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                    $stc->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                    $stc->execute();
                    $rowc = $stc->fetch(PDO::FETCH_ASSOC);
                    
                    if ($rowc) {
                        $op = $rowc['opcao_frete'] ?? null;
                        if (in_array($op, ['vendedor','comprador'])) {
                            $mostrar_botao_avaliar = true;
                        } elseif ($op === 'entregador') {
                            // Verificar se houve entrega concluída
                            $sql_ent = "SELECT e.id FROM entregas e 
                                       LEFT JOIN compradores c ON e.comprador_id = c.id 
                                       WHERE e.produto_id = :produto_id 
                                       AND (e.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) 
                                       AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') 
                                       LIMIT 1";
                            $ste = $conn->prepare($sql_ent);
                            $ste->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                            $ste->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                            $ste->execute();
                            if ($ste->fetch(PDO::FETCH_ASSOC)) {
                                $mostrar_botao_avaliar = true;
                            }
                        }
                    }
                }
            } else {
                header("Location: dashboard.php?erro=" . urlencode("Produto não encontrado."));
                exit();
            }
        } catch (PDOException $e) {
            die("Erro ao buscar informações do produto: " . $e->getMessage());
        }
        break;
        
    case 'vendedor':
        // Buscar informações do vendedor
        try {
            $sql_vendedor = "SELECT id, nome_comercial FROM vendedores WHERE usuario_id = :id";
            $stmt_vendedor = $conn->prepare($sql_vendedor);
            $stmt_vendedor->bindParam(':id', $id_referencia, PDO::PARAM_INT);
            $stmt_vendedor->execute();
            
            if ($vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC)) {
                $vendedor_id_tabela = $vendedor['id']; // ADICIONAR ESTA LINHA
                $pagina_titulo = "Avaliações do Vendedor: " . htmlspecialchars($vendedor['nome_comercial']);
                $subtitulo = "Perfil do vendedor";

                // Verificar se usuário logado pode avaliar este vendedor
                if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo') {
                    $usuario_logado = $_SESSION['usuario_id'];
                    
                    // Verificar diretamente na tabela propostas
                    $sql_check = "SELECT 1 FROM propostas p 
                                WHERE p.comprador_id = :usuario_id
                                AND p.vendedor_id = :vendedor_usuario_id
                                AND p.status = 'aceita' 
                                LIMIT 1";
                    
                    $stc = $conn->prepare($sql_check);
                    $stc->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                    $stc->bindParam(':vendedor_usuario_id', $id_referencia, PDO::PARAM_INT); // id_referencia é o usuario_id do vendedor
                    $stc->execute();
                    
                    if ($stc->fetch(PDO::FETCH_ASSOC)) {
                        $mostrar_botao_avaliar = true;
                    }
                }
            } else {
                header("Location: dashboard.php?erro=" . urlencode("Vendedor não encontrado."));
                exit();
            }
        } catch (PDOException $e) {
            die("Erro ao buscar informações do vendedor: " . $e->getMessage());
        }
        break;
        
    case 'comprador':
        // Buscar informações do comprador
        try {
            $sql_comprador = "SELECT id, nome_comercial FROM compradores WHERE usuario_id = :id";
            $stmt_comprador = $conn->prepare($sql_comprador);
            $stmt_comprador->bindParam(':id', $id_referencia, PDO::PARAM_INT);
            $stmt_comprador->execute();
            
            if ($comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC)) {
                $comprador_id_tabela = $comprador['id']; // ID da tabela compradores
                $pagina_titulo = "Avaliações do Comprador: " . htmlspecialchars($comprador['nome_comercial']);
                $subtitulo = "Perfil do comprador";
                
                // Verificar se usuário logado pode avaliar este comprador
                if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo') {
                    $usuario_logado = $_SESSION['usuario_id'];
                    
                    // Verificar se foi vendedor com este comprador
                    // compradores.usuario_id precisa ser comparado com propostas.comprador_id
                    $sql_comp_uid = "SELECT usuario_id FROM compradores WHERE id = :comprador_id LIMIT 1";
                    $st_comp_uid = $conn->prepare($sql_comp_uid);
                    $st_comp_uid->bindParam(':comprador_id', $comprador_id_tabela, PDO::PARAM_INT);
                    $st_comp_uid->execute();
                    $comp_uid_row = $st_comp_uid->fetch(PDO::FETCH_ASSOC);
                    
                    if ($comp_uid_row) {
                        $comprador_usuario_id = $comp_uid_row['usuario_id'];
                        
                        $sql_check = "SELECT 1 FROM propostas p 
                                      WHERE p.comprador_id = :comprador_usuario_id
                                      AND p.status = 'aceita' 
                                      AND p.vendedor_id = :usuario_id
                                      LIMIT 1";
                        $stc = $conn->prepare($sql_check);
                        $stc->bindParam(':comprador_usuario_id', $comprador_usuario_id, PDO::PARAM_INT);
                        $stc->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                        $stc->execute();
                        if ($stc->fetch(PDO::FETCH_ASSOC)) {
                            $mostrar_botao_avaliar = true;
                        }
                    }
                }
            } else {
                header("Location: dashboard.php?erro=" . urlencode("Comprador não encontrado."));
                exit();
            }
        } catch (PDOException $e) {
            die("Erro ao buscar informações do comprador: " . $e->getMessage());
        }
        break;
        
    case 'transportador':
        // Buscar informações do transportador (se houver tabela específica)
        try {
            // Adapte conforme sua estrutura de tabelas
            $sql_transportador = "SELECT id, nome_comercial FROM transportadores WHERE usuario_id = :id";
            $stmt_transportador = $conn->prepare($sql_transportador);
            $stmt_transportador->bindParam(':id', $id_referencia, PDO::PARAM_INT);
            $stmt_transportador->execute();
            
            if ($transportador = $stmt_transportador->fetch(PDO::FETCH_ASSOC)) {
                $transportador_id_tabela = $transportador['id']; // ID da tabela transportadores
                $pagina_titulo = "Avaliações do Transportador: " . htmlspecialchars($transportador['nome_comercial']);
                $subtitulo = "Perfil do transportador";
                
                // Verificar se usuário logado pode avaliar este transportador
                if (isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo') {
                    $usuario_logado = $_SESSION['usuario_id'];
                    
                    // Verificar se teve entrega com este transportador
                    $sql_check = "SELECT 1 FROM entregas e 
                                  LEFT JOIN compradores c ON e.comprador_id = c.id 
                                  LEFT JOIN vendedores v ON e.vendedor_id = v.id 
                                  WHERE e.transportador_id = :transportador_id 
                                  AND (e.vendedor_id = :usuario_id OR v.usuario_id = :usuario_id OR e.comprador_id = :usuario_id OR c.usuario_id = :usuario_id) 
                                  AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') LIMIT 1";
                    $stc = $conn->prepare($sql_check);
                    $stc->bindParam(':transportador_id', $transportador_id_tabela, PDO::PARAM_INT);
                    $stc->bindParam(':usuario_id', $usuario_logado, PDO::PARAM_INT);
                    $stc->execute();
                    if ($stc->fetch(PDO::FETCH_ASSOC)) {
                        $mostrar_botao_avaliar = true;
                    }
                }
            } else {
                header("Location: dashboard.php?erro=" . urlencode("Transportador não encontrado."));
                exit();
            }
        } catch (PDOException $e) {
            die("Erro ao buscar informações do transportador: " . $e->getMessage());
        }
        break;
        
    default:
        header("Location: dashboard.php?erro=" . urlencode("Tipo de avaliação inválido."));
        exit();
}

// Buscar todas as avaliações do tipo específico
$avaliacoes = [];
$media_avaliacao = 0;
$total_avaliacoes = 0;

try {
    $sql_avaliacoes = "SELECT a.*, u.nome 
                      FROM avaliacoes a 
                      LEFT JOIN usuarios u ON a.avaliador_usuario_id = u.id 
                      WHERE a.tipo = :tipo AND a.";
    
    // Definir o campo correto e o ID correto baseado no tipo
    $id_para_buscar = null;
    
    switch ($tipo_avaliacao) {
        case 'produto':
            $sql_avaliacoes .= "produto_id = :id";
            $id_para_buscar = $id_referencia; 
            break;
        case 'vendedor':
            $sql_avaliacoes .= "vendedor_id = :id";
            $id_para_buscar = $id_referencia ?? null; 
            break;
        case 'comprador':
            $sql_avaliacoes .= "comprador_id = :id";
            $id_para_buscar = $id_referencia ?? null; 
            break;
        case 'transportador':
            $sql_avaliacoes .= "transportador_id = :id";
            $id_para_buscar = $id_referencia ?? null; 
            break;
    }
    
    $sql_avaliacoes .= " ORDER BY a.data_criacao DESC";
    
    // Verificar se temos um ID válido para buscar
    if ($id_para_buscar === null) {
        throw new Exception("ID não encontrado para tipo: " . $tipo_avaliacao);
    }
    
    $stmt_avaliacoes = $conn->prepare($sql_avaliacoes);
    $stmt_avaliacoes->bindParam(':tipo', $tipo_avaliacao);
    $stmt_avaliacoes->bindParam(':id', $id_para_buscar, PDO::PARAM_INT);
    $stmt_avaliacoes->execute();
    $avaliacoes = $stmt_avaliacoes->fetchAll(PDO::FETCH_ASSOC);

    // Buscar fotos relacionadas às avaliações (em lote)
    if (!empty($avaliacoes)) {
        $ids = array_column($avaliacoes, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt_fotos = $conn->prepare("SELECT avaliacao_id, caminho FROM avaliacao_fotos WHERE avaliacao_id IN ($placeholders) ORDER BY id ASC");
        $stmt_fotos->execute($ids);
        $fotos_raw = $stmt_fotos->fetchAll(PDO::FETCH_ASSOC);
        $fotos_map = [];
        foreach ($fotos_raw as $f) {
            $fotos_map[$f['avaliacao_id']][] = $f['caminho'];
        }
        // Anexar lista de fotos a cada avaliação
        foreach ($avaliacoes as &$av) {
            $av['fotos'] = $fotos_map[$av['id']] ?? [];
        }
        unset($av);
    }
    
    // Calcular média
    if (!empty($avaliacoes)) {
        $soma_notas = 0;
        foreach ($avaliacoes as $av) {
            $soma_notas += (int)$av['nota'];
        }
        $media_avaliacao = round($soma_notas / count($avaliacoes), 1);
        $total_avaliacoes = count($avaliacoes);
    }
} catch (PDOException $e) {
    die("Erro ao buscar avaliações: " . $e->getMessage());
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pagina_titulo; ?> - Encontre o Campo</title>
    <link rel="shortcut icon" href="../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #2E7D32;
            --light-gray: #f5f5f5;
            --dark-gray: #333;
            --border-color: #ddd;
            --radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--light-gray);
            color: var(--dark-gray);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 100px auto auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            justify-content: space-between;
            display: flex;
        }
        
        .header h1 {
            color: var(--primary-dark);
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1em;
        }

        .header-info{
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .voltar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            width: 110.64px;
            height: 45.6px;
        }
        
        .voltar-btn:hover {
            background: var(--primary-dark);
            transform: translateX(-5px);
        }
        
        .resumo-avaliacoes {
            background: white;
            padding: 25px;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .media-container {
            align-items: center;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .numero-media {
            font-size: 3.5em;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .estrelas-media {
            display: flex;
            gap: 5px;
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        
        .estrela-cheia {
            color: #ffc107;
        }
        
        .estrela-vazia {
            color: #ddd;
        }
        
        .total-avaliacoes {
            font-size: 1.2em;
            color: #666;
        }
        
        .lista-avaliacoes {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .avaliacao-item {
            padding: 25px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s ease;
        }
        
        /* Cor para itens pares (segundo, quarto, etc.) */
        .avaliacao-item:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        /* Cor para itens ímpares (primeiro, terceiro, etc.) */
        .avaliacao-item:nth-child(odd) {
            background-color: #ffffff;
        }
        
        /* Efeito hover para melhor interatividade */
        .avaliacao-item:hover {
            background-color: #f0f7f0 !important;
        }
        
        /* Ajuste na cor do comentário para ficar bem com ambas as cores de fundo */
        .avaliacao-comentario {
            color: #555;
            line-height: 1.7;
            font-size: 1em;
            background: rgba(76, 175, 80, 0.05);
            padding: 15px;
            border-radius: var(--radius);
            border-left: 3px solid var(--primary-color);
        }
        
        .avaliacao-item:last-child {
            border-bottom: none;
        }
        
        .avaliacao-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .avaliacoes-info {
            flex: 1;
            padding: 30px;
            border-bottom: 1px solid var(--border-color);
            justify-content: space-between;
            display: flex;
        }
        
        .avaliacoes-info h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.3em;
        }
        
        .avaliacoes-info p {
            color: #666;
            margin: 5px 0;
        }

        .avaliador-info {
            flex: 1;
        }
        
        .avaliador-nome {
            font-weight: 600;
            font-size: 1.1em;
            color: var(--dark-gray);
        }
        
        .avaliacao-data {
            color: #999;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .avaliacao-nota {
            display: flex;
            gap: 3px;
            font-size: 1.2em;
        }
        
        .sem-avaliacoes {
            text-align: center;
            padding: 50px 20px;
            color: #999;
        }
        
        .sem-avaliacoes i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #ddd;
        }

        .botao-avaliar {
            margin-top: 15px;
            text-align: center;
        }
        
        .botao-avaliar .btn-avaliar {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .botao-avaliar .btn-avaliar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 152, 0, 0.3);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .media-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .numero-media {
                font-size: 2.5em;
            }
            
            .avaliacao-header {
                flex-direction: column;
                align-items: start;
            }
            
            .avaliacao-item {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="logo">
                    <a href="../index.php" class="logo-link" style="display: flex; align-items: center; text-decoration: none; color: inherit; cursor: pointer;">
                        <img src="../img/logo-nova.png" alt="Logo">
                        <div>
                            <h1>ENCONTRE</h1>
                            <h2>O CAMPO</h2>
                        </div>
                    </a>
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="anuncios.php" class="nav-link">Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="vendedor/dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="vendedor/perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link exit-button no-underline">Sair</a>
                    </li>
                </ul>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <div>
                    <h1><?php echo $pagina_titulo; ?></h1>
                <?php if (isset($subtitulo)): ?>
                    <p><?php echo $subtitulo; ?></p>
                <?php endif; ?>
                </div>
                <a href="javascript:history.back()" class="voltar-btn">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
            <div class="resumo-avaliacoes">
                <div class="media-container">
                    <div class="numero-media"><?php echo $media_avaliacao; ?></div>
                    <div>
                        <div class="estrelas-media">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= floor($media_avaliacao)) {
                                    echo '<i class="fas fa-star estrela-cheia"></i>';
                                } elseif ($i - 0.5 <= $media_avaliacao) {
                                    echo '<i class="fas fa-star-half-alt estrela-cheia"></i>';
                                } else {
                                    echo '<i class="far fa-star estrela-vazia"></i>';
                                }
                            }
                            ?>
                        </div>
                        <div class="total-avaliacoes">
                            <?php echo $total_avaliacoes; ?> <?php echo $total_avaliacoes === 1 ? 'avaliação' : 'avaliações'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($total_avaliacoes > 0): ?>
            
            
            <div class="lista-avaliacoes">
                <div class="avaliacoes-info">
                    <div>
                        <h3><i class="fas fa-comments"></i> Avaliações dos Clientes</h3>
                        <p>Veja o que os usuários acham deste 
                            <?php if ($tipo_avaliacao === 'produto'): ?>
                                produto
                            <?php elseif ($tipo_avaliacao === 'vendedor'): ?>
                                vendedor
                            <?php elseif ($tipo_avaliacao === 'comprador'): ?>
                                comprador
                            <?php elseif ($tipo_avaliacao === 'transportador'): ?>
                                transportador
                            <?php endif; ?>    
                        </p>
                    </div>
                    <?php if ($tipo_avaliacao === 'produto' && $mostrar_botao_avaliar): ?>
                        <div class="botao-avaliar">
                            <a href="./avaliar.php?tipo=produto&produto_id=<?php echo $id_referencia; ?>" class="btn-avaliar">
                            <i class="fas fa-star"></i> Avaliar este produto
                        </a>
                    </div>
                    <?php elseif ($tipo_avaliacao === 'vendedor' && $mostrar_botao_avaliar): ?>
                        <div class="botao-avaliar">
                            <a href="./avaliar.php?tipo=vendedor&vendedor_id=<?php echo $vendedor_id_tabela; ?>" class="btn-avaliar">
                            <i class="fas fa-star"></i> Avaliar este vendedor
                        </a>
                    </div>
                    <?php elseif ($tipo_avaliacao === 'transportador' && $mostrar_botao_avaliar): ?>
                        <div class="botao-avaliar">
                            <a href="./avaliar.php?tipo=transportador&transportador_id=<?php echo $transportador_id_tabela; ?>" class="btn-avaliar">
                            <i class="fas fa-star"></i> Avaliar este transportador
                        </a>
                    </div>
                    <?php elseif ($tipo_avaliacao === 'comprador' && $mostrar_botao_avaliar): ?>
                        <div class="botao-avaliar">
                            <a href="./avaliar.php?tipo=comprador&comprador_id=<?php echo $comprador_id_tabela; ?>" class="btn-avaliar">
                            <i class="fas fa-star"></i> Avaliar este comprador
                        </a>
                    </div>
                    <?php elseif ($tipo_avaliacao === 'produto' && isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                        <div style="padding: 10px 15px; background: #f8f9fa; border-radius: var(--radius); color: #666; font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> Você só pode avaliar produtos que comprou
                        </div>
                    <?php elseif ($tipo_avaliacao === 'vendedor' && isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                        <div style="padding: 10px 15px; background: #f8f9fa; border-radius: var(--radius); color: #666; font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> Você só pode avaliar vendedores com quem fez negócio
                        </div>
                    <?php elseif ($tipo_avaliacao === 'transportador' && isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                        <div style="padding: 10px 15px; background: #f8f9fa; border-radius: var(--radius); color: #666; font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> Você só pode avaliar transportadores com quem fez negócio
                        </div>
                    <?php elseif ($tipo_avaliacao === 'comprador' && isset($_SESSION['usuario_status']) && $_SESSION['usuario_status'] === 'ativo'): ?>
                        <div style="padding: 10px 15px; background: #f8f9fa; border-radius: var(--radius); color: #666; font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> Você só pode avaliar compradores com quem fez negócio
                        </div>
                    <?php endif; ?> 
                </div>
                <?php 
                    $contador = 0;
                    foreach ($avaliacoes as $av): 
                        $contador++;
                        $classe_cor = ($contador % 2 == 0) ? 'avaliacao-par' : 'avaliacao-impar';
                ?>
                    <div class="avaliacao-item <?php echo $classe_cor; ?>">
                        <div class="avaliacao-header">
                            <div class="avaliador-info">
                                <div class="avaliador-nome">
                                    <?php echo htmlspecialchars($av['nome'] ?? 'Anônimo'); ?>
                                </div>
                                <div class="avaliacao-data">
                                    <?php 
                                    $data = new DateTime($av['data_criacao']);
                                    echo $data->format('d/m/Y à\s H:i');
                                    ?>
                                </div>
                            </div>
                            <div class="avaliacao-nota">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= (int)$av['nota']) {
                                        echo '<i class="fas fa-star estrela-cheia"></i>';
                                    } else {
                                        echo '<i class="far fa-star estrela-vazia"></i>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($av['comentario'])): ?>
                            <div class="avaliacao-comentario ">
                                <?php echo nl2br(htmlspecialchars($av['comentario'])); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($av['fotos'])): ?>
                            <div class="avaliacao-fotos" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                                <?php foreach ($av['fotos'] as $foto): ?>
                                    <a href="../<?php echo htmlspecialchars($foto); ?>" target="_blank" rel="noopener" style="display:inline-block;">
                                        <img src="../<?php echo htmlspecialchars($foto); ?>" alt="Foto da avaliação" style="max-width:140px;max-height:140px;object-fit:cover;border-radius:8px;border:1px solid #e3e3e3;" />
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
        <?php else: ?>
            <div class="sem-avaliacoes">
                <div><i class="fas fa-star"></i></div>
                <p>Nenhuma avaliação encontrada.</p>
            </div>
        <?php endif; ?>

        <!-- Seção: Pessoas para avaliar -->
        <?php if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'vendedor' && isset($_SESSION['usuario_id'])): ?>
            <?php
            // Vendedor: mostrar compradores com quem fez negócio
            try {
                $vendedor_id = null;
                $sql_v = "SELECT id FROM vendedores WHERE usuario_id = :uid";
                $stmt_v = $conn->prepare($sql_v);
                $stmt_v->bindParam(':uid', $_SESSION['usuario_id'], PDO::PARAM_INT);
                $stmt_v->execute();
                $v_row = $stmt_v->fetch(PDO::FETCH_ASSOC);
                if ($v_row) {
                    $vendedor_id = $v_row['id'];
                    
                    $sql_cp = "SELECT DISTINCT c.id, c.nome_comercial, u.id as usuario_id, u.nome 
                               FROM compradores c 
                               INNER JOIN usuarios u ON c.usuario_id = u.id 
                               INNER JOIN propostas p ON p.comprador_id = c.id 
                               WHERE p.vendedor_id = :vendedor_id AND p.status = 'aceita' 
                               ORDER BY u.nome ASC";
                    $stmt_cp = $conn->prepare($sql_cp);
                    $stmt_cp->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
                    $stmt_cp->execute();
                    $compradores = $stmt_cp->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($compradores)): ?>
                        <div style="background: white; padding: 25px; border-radius: var(--radius); margin-top: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <h3><i class="fas fa-users"></i> Compradores para avaliar</h3>
                            <p style="color: #666; margin-bottom: 20px;">Veja e avalie os compradores com quem você fez negócios</p>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                                <?php foreach ($compradores as $comp): ?>
                                    <div style="border: 1px solid #e3e3e3; padding: 15px; border-radius: 6px; text-align: center;">
                                        <div style="font-weight: 600; margin-bottom: 10px;"><?php echo htmlspecialchars($comp['nome'] ?? $comp['nome_comercial']); ?></div>
                                        <a href="./avaliacoes.php?tipo=comprador&id=<?php echo $comp['usuario_id']; ?>" style="color: #4CAF50; text-decoration: none; font-weight: 500;">Ver avaliações</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif;
                }
            } catch (Exception $e) {
                // Silencioso
            }
            ?>
        <?php elseif (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'transportador' && isset($_SESSION['usuario_id'])): ?>
            <?php
            // Transportador: mostrar compradores e vendedores com quem fez entregas
            try {
                $transportador_id = null;
                $sql_t = "SELECT id FROM transportadores WHERE usuario_id = :uid";
                $stmt_t = $conn->prepare($sql_t);
                $stmt_t->bindParam(':uid', $_SESSION['usuario_id'], PDO::PARAM_INT);
                $stmt_t->execute();
                $t_row = $stmt_t->fetch(PDO::FETCH_ASSOC);
                if ($t_row) {
                    $transportador_id = $t_row['id'];
                    
                    // Compradores
                    $sql_cc = "SELECT DISTINCT c.id, u.id as usuario_id, u.nome, c.nome_comercial 
                               FROM compradores c 
                               INNER JOIN usuarios u ON c.usuario_id = u.id 
                               INNER JOIN entregas e ON e.comprador_id = c.id 
                               WHERE e.transportador_id = :transportador_id AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') 
                               ORDER BY u.nome ASC";
                    $stmt_cc = $conn->prepare($sql_cc);
                    $stmt_cc->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
                    $stmt_cc->execute();
                    $compradores_transp = $stmt_cc->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Vendedores
                    $sql_vv = "SELECT DISTINCT v.id, u.id as usuario_id, u.nome, v.nome_comercial 
                               FROM vendedores v 
                               INNER JOIN usuarios u ON v.usuario_id = u.id 
                               INNER JOIN entregas e ON e.vendedor_id = v.id 
                               WHERE e.transportador_id = :transportador_id AND (e.status = 'entregue' OR e.status_detalhado = 'finalizada') 
                               ORDER BY u.nome ASC";
                    $stmt_vv = $conn->prepare($sql_vv);
                    $stmt_vv->bindParam(':transportador_id', $transportador_id, PDO::PARAM_INT);
                    $stmt_vv->execute();
                    $vendedores_transp = $stmt_vv->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($compradores_transp) || !empty($vendedores_transp)): ?>
                        <div style="background: white; padding: 25px; border-radius: var(--radius); margin-top: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <h3><i class="fas fa-users"></i> Pessoas para avaliar</h3>
                            <p style="color: #666; margin-bottom: 20px;">Avalie os compradores e vendedores com quem você fez entregas</p>
                            
                            <?php if (!empty($compradores_transp)): ?>
                                <div style="margin-bottom: 25px;">
                                    <h4 style="margin-bottom: 15px; color: #333;">Compradores</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                                        <?php foreach ($compradores_transp as $comp): ?>
                                            <div style="border: 1px solid #e3e3e3; padding: 15px; border-radius: 6px; text-align: center;">
                                                <div style="font-weight: 600; margin-bottom: 10px;"><?php echo htmlspecialchars($comp['nome'] ?? $comp['nome_comercial']); ?></div>
                                                <a href="./avaliacoes.php?tipo=comprador&id=<?php echo $comp['usuario_id']; ?>" style="color: #4CAF50; text-decoration: none; font-weight: 500;">Ver avaliações</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($vendedores_transp)): ?>
                                <div>
                                    <h4 style="margin-bottom: 15px; color: #333;">Vendedores</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                                        <?php foreach ($vendedores_transp as $vend): ?>
                                            <div style="border: 1px solid #e3e3e3; padding: 15px; border-radius: 6px; text-align: center;">
                                                <div style="font-weight: 600; margin-bottom: 10px;"><?php echo htmlspecialchars($vend['nome'] ?? $vend['nome_comercial']); ?></div>
                                                <a href="./avaliacoes.php?tipo=vendedor&id=<?php echo $vend['usuario_id']; ?>" style="color: #4CAF50; text-decoration: none; font-weight: 500;">Ver avaliações</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif;
                }
            } catch (Exception $e) {
                // Silencioso
            }
            ?>
        <?php endif; ?>
    </div>
</body>
</html>