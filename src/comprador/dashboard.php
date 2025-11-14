<?php
// src/comprador/dashboard.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_nome = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Comprador');
$usuario_id = $_SESSION['usuario_id']; // ID do usuário logado na tabela 'usuarios'

$database = new Database();
$conn = $database->getConnection();
$dashboard_data = [
    'total_propostas' => 0,
    'pendentes' => 0,
    'aceitas' => 0,
    'recusadas' => 0,
    'negociacao' => 0
];
$comprador_id = null;


// 2. OBTENDO O ID DO COMPRADOR (ID da tabela 'compradores')
try {
    $sql_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    $stmt_comprador = $conn->prepare($sql_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    $resultado_comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);

    if ($resultado_comprador) {
        $comprador_id = $resultado_comprador['id'];
    } else {
        die("Erro: ID de comprador não encontrado. Não é possível carregar o dashboard.");
    }
} catch (PDOException $e) {
    die("Erro ao buscar ID do comprador: " . $e->getMessage());
}

// 3. BUSCA DOS TOTAIS DAS PROPOSTAS POR STATUS
try {
    $sql_propostas = "SELECT status, COUNT(id) AS total FROM propostas_negociacao 
                      WHERE comprador_id = :comprador_id
                      GROUP BY status";
            
    $stmt_propostas = $conn->prepare($sql_propostas);
    $stmt_propostas->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_propostas->execute();
    $totais_status = $stmt_propostas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($totais_status as $item) {
        $dashboard_data['total_propostas'] += $item['total'];
        $status_key = strtolower($item['status']);
        if (isset($dashboard_data[$status_key])) {
            $dashboard_data[$status_key] = $item['total'];
        }
    }

} catch (PDOException $e) {
    // Apenas registra o erro, mas permite que o restante da página carregue
    error_log("Erro ao carregar totais de propostas: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Comprador</title>
    <link rel="stylesheet" href="../../index.css"> 
    <link rel="stylesheet" href="../css/comprador/comprador.css"> 
    <link rel="shortcut icon" href="../../img/Logo - Copia.jpg" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* Estilos Específicos do Dashboard */
        .dashboard-container {
            padding-top: 120px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-header {
            background-color: var(--primary-light);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid var(--primary-color);
        }

        .welcome-header h1 {
            color: var(--dark-color);
            font-size: 2.2em;
            margin-bottom: 5px;
        }

        .welcome-header p {
            color: var(--text-color);
            font-size: 1.1em;
            margin: 0;
        }

        /* Cartões de Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background-color: var(--white);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        .stat-card .icon {
            font-size: 2.5em;
            padding: 10px;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card .details {
            text-align: right;
        }

        .stat-card .details h2 {
            font-size: 2.2em;
            margin: 0;
            color: var(--dark-color);
            line-height: 1;
        }

        .stat-card .details p {
            font-size: 1em;
            color: var(--text-light);
            margin: 0;
            font-weight: 500;
        }

        /* Cores dos Cartões */
        .card-total {
            border-bottom: 3px solid var(--secondary-color);
        }
        .card-total .icon {
            background-color: #ffe0b2; /* Laranja claro */
            color: var(--secondary-color);
        }

        .card-pending {
            border-bottom: 3px solid #FFC107;
        }
        .card-pending .icon {
            background-color: #FFF8E1; /* Amarelo claro */
            color: #FFC107;
        }

        .card-accepted {
            border-bottom: 3px solid var(--primary-color);
        }
        .card-accepted .icon {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        .card-rejected {
            border-bottom: 3px solid #E53935;
        }
        .card-rejected .icon {
            background-color: #FFEBEE; /* Vermelho claro */
            color: #E53935;
        }
        
        /* Seção de Ações Rápidas */
        .quick-actions {
            margin-bottom: 40px;
        }

        .quick-actions h2 {
            text-align: left;
            margin-bottom: 20px;
            font-size: 1.8em;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .action-link {
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: background-color 0.2s;
        }

        .action-link:hover {
            background-color: var(--primary-light);
        }

        .action-link i {
            font-size: 1.5em;
            color: var(--primary-color);
        }

        .action-link span {
            font-weight: bold;
            font-size: 1.1em;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .welcome-header h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <h1>ENCONTRE</h1>
                <h2>OCAMPO</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
                <li class="nav-item"><a href="../anuncios.php" class="nav-link">Ver Anúncios</a></li>
                <li class="nav-item"><a href="minhas_propostas.php" class="nav-link">Minhas Propostas</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link logout">Sair</a></li>
            </ul>
        </div>
    </nav>

    <main class="container dashboard-container">
        <div class="welcome-header">
            <h1>Bem-vindo, <?php echo $usuario_nome; ?>!</h1>
            <p>Seu centro de controle para negociações de produtos frescos.</p>
        </div>
        
        <section class="stats-grid">
            <div class="stat-card card-total">
                <div class="icon"><i class="fas fa-handshake"></i></div>
                <div class="details">
                    <h2><?php echo $dashboard_data['total_propostas']; ?></h2>
                    <p>Total de Propostas</p>
                </div>
            </div>

            <div class="stat-card card-pending">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="details">
                    <h2><?php echo $dashboard_data['pendentes']; ?></h2>
                    <p>Propostas Pendentes</p>
                </div>
            </div>

            <div class="stat-card card-accepted">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="details">
                    <h2><?php echo $dashboard_data['aceitas']; ?></h2>
                    <p>Propostas Aceitas</p>
                </div>
            </div>
            
            <div class="stat-card card-rejected">
                <div class="icon"><i class="fas fa-times-circle"></i></div>
                <div class="details">
                    <h2><?php echo $dashboard_data['recusadas']; ?></h2>
                    <p>Propostas Recusadas</p>
                </div>
            </div>
        </section>

        <section class="quick-actions">
            <h2>Ações Rápidas</h2>
            <div class="actions-grid">
                <a href="../anuncios.php" class="action-link">
                    <i class="fas fa-leaf"></i>
                    <span>Ver Anúncios e Negociar</span>
                </a>
                <a href="minhas_propostas.php" class="action-link">
                    <i class="fas fa-list-alt"></i>
                    <span>Acompanhar Minhas Propostas</span>
                </a>
                <a href="#" class="action-link">
                    <i class="fas fa-truck"></i>
                    <span>Solicitar Transporte (Em Breve)</span>
                </a>
                <a href="#" class="action-link">
                    <i class="fas fa-user-circle"></i>
                    <span>Gerenciar Perfil</span>
                </a>
            </div>
        </section>
    </main>
</body>
</html>