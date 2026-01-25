<?php
// src/comprador/dashboard.php

session_start();
require_once __DIR__ . '/../conexao.php'; 
require_once __DIR__ . '/../permissions.php'; 

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

// Verificar se o usuário tem permissão para ver dashboard completo
$usuario_status = $_SESSION['usuario_status'] ?? 'pendente';
$is_pendente = ($usuario_status === 'pendente');

$usuario_nome = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Comprador');
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

//
$usuario_nome = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Comprador');
$usuario_id = $_SESSION['usuario_id'];

$database = new Database();
$conn = $database->getConnection();

$dashboard_data = [
    'total_propostas' => 0,
    'enviada' => 0,
    'pendente' => 0,
    'aceita' => 0,
    'recusada' => 0,
    'favoritos' => 0,
    'total_chats' => 0,
    'chats_nao_lidos' => 0
];

$comprador_id = null;

// 2. OBTENDO O ID DO COMPRADOR
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
    $sql_propostas = "SELECT status, COUNT(id) AS total FROM propostas_comprador 
                      WHERE comprador_id = :comprador_id
                      GROUP BY status";
            
    $stmt_propostas = $conn->prepare($sql_propostas);
    $stmt_propostas->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_propostas->execute();
    $totais_status = $stmt_propostas->fetchAll(PDO::FETCH_ASSOC);

    $dashboard_data['enviada'] = 0;
    $dashboard_data['pendente'] = 0;
    $dashboard_data['aceita'] = 0;
    $dashboard_data['recusada'] = 0;
    $dashboard_data['finalizada'] = 0;
    $dashboard_data['total_propostas'] = 0;

    foreach ($totais_status as $item) {
        $dashboard_data['total_propostas'] += $item['total'];
        $status_key = strtolower($item['status']);
        if (isset($dashboard_data[$status_key])) {
            $dashboard_data[$status_key] = $item['total'];
        }
    }

    $sql_compras_realizadas = "SELECT COUNT(DISTINCT pn.id) as compras_realizadas
                               FROM propostas_negociacao pn
                               INNER JOIN propostas_comprador pc ON pn.proposta_comprador_id = pc.id
                               WHERE pc.comprador_id = :comprador_id 
                               AND pn.status = 'aceita'";
    
    $stmt_compras = $conn->prepare($sql_compras_realizadas);
    $stmt_compras->bindParam(':comprador_id', $comprador_id, PDO::PARAM_INT);
    $stmt_compras->execute();
    $compras_result = $stmt_compras->fetch(PDO::FETCH_ASSOC);
    
    $dashboard_data['aceita'] = $compras_result['compras_realizadas'] ?? 0;

} catch (PDOException $e) {
    error_log("Erro ao carregar totais de propostas: " . $e->getMessage());
}

// 4. BUSCA DO TOTAL DE FAVORITOS
try {
    $sql_favoritos = "SELECT COUNT(id) AS total_favoritos FROM favoritos 
                      WHERE usuario_id = :usuario_id";
    
    $stmt_favoritos = $conn->prepare($sql_favoritos);
    $stmt_favoritos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_favoritos->execute();
    $resultado_favoritos = $stmt_favoritos->fetch(PDO::FETCH_ASSOC);

    $dashboard_data['favoritos'] = $resultado_favoritos ? $resultado_favoritos['total_favoritos'] : 0;

} catch (PDOException $e) {
    error_log("Erro ao carregar total de favoritos: " . $e->getMessage());
    $dashboard_data['favoritos'] = 0;
}

// 5. BUSCAR TOTAL DE CHATS E MENSAGENS NÃO LIDAS
try {
    // Total de chats do comprador
    $sql_chats = "SELECT COUNT(DISTINCT cc.id) as total_chats
                  FROM chat_conversas cc
                  WHERE cc.comprador_id = :usuario_id
                  AND cc.status = 'ativo'";
    
    $stmt_chats = $conn->prepare($sql_chats);
    $stmt_chats->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_chats->execute();
    $chats_result = $stmt_chats->fetch(PDO::FETCH_ASSOC);
    
    $dashboard_data['total_chats'] = $chats_result['total_chats'] ?? 0;
    
    // Chats com mensagens não lidas
    $sql_nao_lidos = "SELECT COUNT(DISTINCT cm.conversa_id) as chats_nao_lidos
                      FROM chat_mensagens cm
                      INNER JOIN chat_conversas cc ON cm.conversa_id = cc.id
                      WHERE cc.comprador_id = :usuario_id
                      AND cm.remetente_id != :usuario_id
                      AND cm.lida = 0";
    
    $stmt_nao_lidos = $conn->prepare($sql_nao_lidos);
    $stmt_nao_lidos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_nao_lidos->execute();
    $nao_lidos_result = $stmt_nao_lidos->fetch(PDO::FETCH_ASSOC);
    
    $dashboard_data['chats_nao_lidos'] = $nao_lidos_result['chats_nao_lidos'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Erro ao carregar dados de chats: " . $e->getMessage());
    $dashboard_data['total_chats'] = 0;
    $dashboard_data['chats_nao_lidos'] = 0;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Comprador</title>
    <link rel="stylesheet" href="../css/comprador/dashboard.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                        <a href="../../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="../anuncios.php" class="nav-link">Anúncios</a>
                    </li>
                    <li class="nav-item">
                        <a href="" class="nav-link active">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                            $stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
                            $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                            $stmt_nao_lidas->execute();
                            $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                            if ($total_nao_lidas > 0) {
                                echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link exit-button no-underline">Sair</a>
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
    <br>

    <div class="main-content">
        <section class="header">    
            <center>
                <h1>Bem-vindo, <?php echo $usuario_nome ?>!</h1>
                <?php if ($is_pendente): ?>
                    <p class="subtitulo">(Cadastro aguardando aprovação)</p>
                <?php endif; ?>
            </center>
        </section>

        <?php if ($is_pendente): ?>
            <div class="aviso-status">
                <i class="fas fa-info-circle"></i>
                <strong>Seu cadastro está aguardando aprovação.</strong> 
                Enquanto isso, você pode visualizar anúncios, favoritar produtos e editar seus dados.
                <br>
            </div>
        <?php endif; ?>
        
        <section class="info-cards">
            <?php if (!$is_pendente): ?>
                <!-- Cards apenas para usuários ativos -->
                <a href="meus_chats.php">
                    <div class="card">
                        <i class="fas fa-comments"></i>
                        <h3>Meus Chats</h3>
                        <p><?php echo $dashboard_data['total_chats']; ?></p>
                    </div>
                </a>

                <a href="meus_chats.php?filtro=nao-lidos">
                    <div class="card">
                        <i class="fas fa-envelope"></i>
                        <h3>Mensagens Novas</h3>
                        <p><?php echo $dashboard_data['chats_nao_lidos']; ?></p>
                    </div>
                </a>

                <a href="../procurando_transportador.php">
                    <div class="card">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <h3>Procurando transportador </h3>
                        <p>Ver</p>
                    </div>
                </a>

                <a href="negociacoes.php">
                    <div class="card">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <h3>Compras realizadas</h3>
                        <p>Ver</p>
                    </div>
                </a>
            <?php endif; ?>
            
            <!-- Card de favoritos - disponível para todos -->
            <a href="favoritos.php">
                <div class="card">
                    <i class="fas fa-heart"></i>
                    <h3>Favoritos</h3>
                    <p><?php echo $dashboard_data['favoritos']; ?></p>
                </div>
            </a>
        </section>

        <section class="header sub">
            <center>
                <h3>Ações rápidas</h3>
            </center>
        </section>

        <section class="acoes-rapidas">
    <a href="../anuncios.php">
        <i class="fa-solid fa-dollar-sign"></i>
        <span>Ver Anúncios</span>
            </a>
            
            <?php if (!$is_pendente): ?>
                <!-- Apenas para usuários ativos -->
                <a href="meus_chats.php">
                    <i class="fas fa-comments"></i>
                    <span>Meus chats</span>
                </a>
            <?php endif; ?>
            
            <a href="perfil.php">
                <i class="fas fa-user-circle"></i>
                <span>Dados</span>
            </a>
            
        </section>
    </div>
    <script>
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");
        
        if (hamburger) {
            hamburger.addEventListener("click", () => {
                hamburger.classList.toggle("active");
                navMenu.classList.toggle("active");
            });
            
            document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
                hamburger.classList.remove("active");
                navMenu.classList.remove("active");
            }));
        }
    </script>
</body>
</html>