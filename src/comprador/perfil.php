<?php
// src/comprador/perfil.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Comprador');

$database = new Database();
$conn = $database->getConnection();
$comprador_data = null;
$mensagem_sucesso = isset($_GET['sucesso']) ? htmlspecialchars($_GET['sucesso']) : null;
$mensagem_erro = isset($_GET['erro']) ? htmlspecialchars($_GET['erro']) : null;

// 2. BUSCAR DADOS DO COMPRADOR
try {
    $sql = "SELECT 
                c.*,
                u.email,
                u.nome as nome_usuario
            FROM compradores c
            JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.usuario_id = :usuario_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $comprador_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comprador_data) {
        die("Perfil de comprador não encontrado.");
    }

} catch (PDOException $e) {
    die("Erro ao carregar perfil: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Comprador</title>
    <link rel="stylesheet" href="../../index.css"> 
    <link rel="stylesheet" href="../css/comprador/comprador.css">
    <link rel="stylesheet" href="../css/comprador/perfil.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <img src="../../img/logo-nova.png" alt="Logo">
                <div>
                        <h1>ENCONTRE</h1>
                        <h2>O CAMPO</h2>
                    </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="../anuncios.php" class="nav-link">Comprar</a></li>
                <li class="nav-item"><a href="minhas_propostas.php" class="nav-link">Minhas Propostas</a></li>
                <li class="nav-item"><a href="perfil.php" class="nav-link active">Meu Perfil</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link logout">Sair</a></li>
            </ul>
        </div>
    </nav>

    <main class="container perfil-container">
        <div class="page-header">
            <h1>Meu Perfil</h1>
            <p class="page-subtitle">Gerencie suas informações pessoais e de negócio</p>
        </div>

        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <div class="perfil-card">
            <div class="perfil-header">
                <div class="perfil-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h2><?php echo htmlspecialchars($comprador_data['nome_usuario']); ?></h2>
                <p>Comprador</p>
            </div>

            <div class="perfil-body">
                <div class="info-section">
                    <h3><i class="fas fa-id-card"></i> Informações Pessoais</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Nome Completo:</span>
                            <span class="info-value"><?php echo htmlspecialchars($comprador_data['nome_usuario']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($comprador_data['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">CPF/CNPJ:</span>
                            <span class="info-value"><?php echo htmlspecialchars($comprador_data['cpf_cnpj']); ?></span>
                        </div>
                        <?php if (!empty($comprador_data['nome_comercial'])): ?>
                        <div class="info-item">
                            <span class="info-label">Nome Comercial:</span>
                            <span class="info-value"><?php echo htmlspecialchars($comprador_data['nome_comercial']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-section">
                    <h3><i class="fas fa-map-marker-alt"></i> Endereço</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">CEP:</span>
                            <span class="info-value"><?php echo htmlspecialchars($comprador_data['cep'] ?? 'Não informado'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Endereço:</span>
                            <span class="info-value">
                                <?php 
                                $endereco = [];
                                if (!empty($comprador_data['rua'])) $endereco[] = $comprador_data['rua'];
                                if (!empty($comprador_data['numero'])) $endereco[] = $comprador_data['numero'];
                                if (!empty($comprador_data['complemento'])) $endereco[] = $comprador_data['complemento'];
                                echo htmlspecialchars(implode(', ', $endereco) ?: 'Não informado');
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Cidade/Estado:</span>
                            <span class="info-value">
                                <?php 
                                $cidade_estado = [];
                                if (!empty($comprador_data['cidade'])) $cidade_estado[] = $comprador_data['cidade'];
                                if (!empty($comprador_data['estado'])) $cidade_estado[] = $comprador_data['estado'];
                                echo htmlspecialchars(implode(' / ', $cidade_estado) ?: 'Não informado');
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h3><i class="fas fa-phone"></i> Contato</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Telefone Principal:</span>
                            <span class="info-value"><?php echo htmlspecialchars($comprador_data['telefone1'] ?? 'Não informado'); ?></span>
                        </div>
                        <?php if (!empty($comprador_data['telefone2'])): ?>
                        <div class="info-item">
                            <span class="info-label">Telefone Secundário:</span>
                            <span class="info-value"><?php echo htmlspecialchars($comprador_data['telefone2']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-section">
                    <h3><i class="fas fa-crown"></i> Plano</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Plano Atual:</span>
                            <span class="info-value">
                                <span class="plano-badge">
                                    <?php 
                                    $planos = [
                                        'basico' => 'Básico',
                                        'premium' => 'Premium', 
                                        'empresarial' => 'Empresarial'
                                    ];
                                    echo htmlspecialchars($planos[$comprador_data['plano']] ?? 'Básico');
                                    ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="perfil-actions">
                    <a href="editar_perfil.php" class="btn-edit">
                        <i class="fas fa-edit"></i>
                        Editar Perfil
                    </a>
                    <a href="dashboard.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Voltar ao Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>