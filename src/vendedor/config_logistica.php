<?php
// src/vendedor/config_logistica.php
session_start();
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

// Verificar se usuário é vendedor
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito a vendedores."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = htmlspecialchars($_SESSION['vendedor_nome'] ?? 'Vendedor');
$database = new Database();
$conn = $database->getConnection();

// Buscar dados do vendedor
$vendedor_id = null;
$estados_atendidos_json = '[]';
$estados_atendidos = [];
$cidades_atendidos_json = '{}';
$cidades_atendidos = []; 

try {
    // Buscar vendedor_id
    $sql_vendedor = "SELECT id, nome_comercial, estados_atendidos, cidades_atendidas FROM vendedores WHERE usuario_id = :usuario_id";
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);
    
    if ($vendedor) {
        $vendedor_id = $vendedor['id'];
        $vendedor_nome_comercial = $vendedor['nome_comercial'] ?? $usuario_nome;
        $estados_atendidos_json = $vendedor['estados_atendidos'] ?? '[]';
        $cidades_atendidos_json = $vendedor['cidades_atendidas'] ?? '{}';
        
        // Decodificar JSON para array PHP
        $estados_atendidos = json_decode($estados_atendidos_json, true) ?: [];
        $cidades_atendidos = json_decode($cidades_atendidos_json, true) ?: [];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do vendedor: " . $e->getMessage());
    $_SESSION['erro'] = "Erro ao carregar configurações.";
}

// Processar formulário se enviado
$mensagem = '';
$tipo_mensagem = ''; // 'sucesso' ou 'erro'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Obter estados selecionados
        $estados_selecionados = $_POST['estados'] ?? [];
        
        // Validar estados (apenas siglas válidas)
        $estados_validos = [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 
            'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 
            'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
        ];
        
        $estados_filtrados = array_intersect($estados_selecionados, $estados_validos);
        
        // Converter para JSON
        $estados_json = json_encode(array_values($estados_filtrados));

        // Coletar cidades enviadas (inputs com nome cidades_<SIGLA>[])
        $cidades_selecionadas = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'cidades_') === 0) {
                $sigla = strtoupper(substr($key, 8));
                if (in_array($sigla, $estados_validos)) {
                    $lista = is_array($value) ? $value : [$value];
                    // limpar e validar
                    $lista_filtrada = array_values(array_filter(array_map('trim', $lista)));
                    if (!empty($lista_filtrada)) {
                        $cidades_selecionadas[$sigla] = $lista_filtrada;
                    }
                }
            }
        }

        $cidades_json = json_encode($cidades_selecionadas, JSON_UNESCAPED_UNICODE);

        // Atualizar no banco (estados e cidades)
        $sql_atualizar = "UPDATE vendedores SET estados_atendidos = :estados_json, cidades_atendidas = :cidades_json WHERE id = :vendedor_id";
        $stmt_atualizar = $conn->prepare($sql_atualizar);
        $stmt_atualizar->bindParam(':estados_json', $estados_json);
        $stmt_atualizar->bindParam(':cidades_json', $cidades_json);
        $stmt_atualizar->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);

        if ($stmt_atualizar->execute()) {
            $mensagem = "Configuração de regiões de entrega salva com sucesso!";
            $tipo_mensagem = 'sucesso';
            $estados_atendidos = $estados_filtrados;
            $estados_atendidos_json = $estados_json;
            $cidades_atendidos = $cidades_selecionadas;
            $cidades_atendidos_json = $cidades_json;
        } else {
            $mensagem = "Erro ao salvar configuração. Tente novamente.";
            $tipo_mensagem = 'erro';
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao salvar configuração de logística: " . $e->getMessage());
        $mensagem = "Erro ao processar a solicitação. Tente novamente mais tarde.";
        $tipo_mensagem = 'erro';
    }
}

// Lista completa de estados do Brasil (UF)
$estados_brasil = [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Regiões de Entrega - Encontre o Campo</title>
    <link rel="stylesheet" href="../css/vendedor/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        /* Reset de margens para garantir que o conteúdo comece após a navbar */
        body {
            padding-top: 80px; /* Altura da navbar + espaço extra */
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
        }

        .logistica-container {
            max-width: 1100px;
            margin: 0 auto 40px;
            padding: 0 20px;
        }

        .config-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
            position: relative;
            border: 1px solid #e0e0e0;
        }

        .header-with-close {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .page-title {
            color: #2E7D32;
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .btn-fechar {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            color: #495057;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.3rem;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-fechar:hover {
            background: #e9ecef;
            border-color: #adb5bd;
            color: #212529;
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .section-description {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 1.1rem;
            padding: 0 10px;
        }

        .estados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .estado-checkbox {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-cidades {
            background: transparent;
            border: none;
            color: #0d6efd;
            cursor: pointer;
            padding: 6px 8px;
            margin-top: 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-cidades:hover {
            background: rgba(13,110,253,0.08);
            transform: translateY(-2px);
        }

        .estado-checkbox:hover {
            border-color: #4CAF50;
            background: #f0f9f0;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.15);
        }

        .estado-checkbox input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .estado-label {
            font-weight: 600;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .estado-sigla {
            display: inline-block;
            background: #4CAF50;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            text-align: center;
            line-height: 32px;
            font-weight: bold;
            margin-right: 12px;
            font-size: 0.9rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .controles-superiores {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 18px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border-left: 5px solid #4CAF50;
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
        }

        .contador-estados {
            font-size: 1.1rem;
            color: #495057;
            font-weight: 600;
        }

        .contador-estados .numero {
            background: #4CAF50;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            margin-left: 8px;
            font-size: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .botao-acao {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 1rem;
        }

        .botao-acao:hover {
            background: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .botao-acao.primary {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            border: none;
        }

        .botao-acao.primary:hover {
            background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(46, 125, 50, 0.3);
        }

        .acoes-inferiores {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }

        .grupo-esquerda, .grupo-direita {
            display: flex;
            gap: 15px;
        }

        .info-box {
            background: linear-gradient(135deg, #e7f3ff 0%, #d4e7ff 100%);
            border: 2px solid #b3d7ff;
            border-radius: 10px;
            padding: 25px;
            margin-top: 35px;
        }

        .info-box h4 {
            color: #0066cc;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.3rem;
        }

        .info-box ul {
            list-style: none;
            padding-left: 0;
        }

        .info-box li {
            margin-bottom: 12px;
            padding-left: 30px;
            position: relative;
            line-height: 1.5;
        }

        .info-box li:before {
            content: '✓';
            color: #0066cc;
            font-size: 1.2rem;
            position: absolute;
            left: 0;
            top: -2px;
            font-weight: bold;
        }

        .alert-message {
            padding: 18px 24px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .alert-message.sucesso {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #a3d9b1;
            color: #155724;
        }

        .alert-message.erro {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 2px solid #f1b0b7;
            color: #721c24;
        }

        .alert-message i {
            font-size: 1.8rem;
        }

        .empty-state {
            text-align: center;
            padding: 50px 30px;
            color: #6c757d;
            font-style: italic;
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            margin: 30px 0;
            background: #fafafa;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #adb5bd;
        }

        /* ESTILOS PARA SELEÇÃO DE CIDADES */
        .cidades-section {
            background: linear-gradient(135deg, #f5f9ff 0%, #e8f4ff 100%);
            border: 2px solid #b3d9ff;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            display: none;
        }

        .cidades-section.ativo {
            display: block;
        }

        .cidades-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #b3d9ff;
        }

        .cidades-header h4 {
            margin: 0;
            color: #0066cc;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cidades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .cidade-checkbox {
            background: white;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .cidade-checkbox:hover {
            border-color: #0066cc;
            background: #f8faff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.1);
        }

        .cidade-checkbox input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.1);
        }

        .cidade-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            color: #333;
        }

        .estado-selecionado-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 2px solid #64b5f6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .estado-nome-selecionado {
            font-weight: 600;
            color: #0d47a1;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-fechar-estado {
            background: none;
            border: none;
            color: #0d47a1;
            font-size: 1.3rem;
            cursor: pointer;
            padding: 0 5px;
            transition: all 0.3s;
        }

        .btn-fechar-estado:hover {
            color: #f44336;
            transform: scale(1.2);
        }

        /* Ajustes para garantir que o conteúdo não fique atrás da navbar */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .main-content-wrapper {
            margin-top: 15px;
            padding: 20px 0;
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }
            
            .estados-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 12px;
            }
            
            .controles-superiores {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .acoes-inferiores {
                flex-direction: column;
                gap: 15px;
            }
            
            .grupo-esquerda, .grupo-direita {
                flex-direction: column;
                width: 100%;
            }
            
            .botao-acao {
                width: 100%;
                justify-content: center;
                padding: 14px;
            }
            
            .config-card {
                padding: 20px;
                margin: 15px 10px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding-top: 60px;
            }
            
            .estados-grid {
                grid-template-columns: 1fr;
            }
            
            .config-card {
                padding: 15px;
            }
            
            .header-with-close {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .btn-fechar {
                align-self: flex-end;
                position: absolute;
                top: 15px;
                right: 15px;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
        }

        /* Ajustes para telas muito pequenas */
        @media (max-height: 600px) {
            body {
                padding-top: 60px;
            }
            
            .config-card {
                padding: 15px;
                margin-top: 10px;
            }
            
            .estados-grid {
                max-height: 300px;
                overflow-y: auto;
                padding-right: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar similar ao dashboard do vendedor -->
    <header>
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
                    <li class="nav-item">
                        <a href="../../index.php" class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">Painel</a>
                    </li>
                    <li class="nav-item">
                        <a href="perfil.php" class="nav-link">Meu Perfil</a>
                    </li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            // Contar notificações não lidas
                            if (isset($_SESSION['usuario_id'])) {
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $conn->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) {
                                    echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                                }
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

    <!-- Conteúdo principal -->
    <div class="main-content-wrapper">
        <div class="logistica-container">
            <div class="config-card">
                <div class="header-with-close">
                    <h1 class="page-title">
                        <i class="fas fa-truck"></i>
                        Configuração de Regiões de Entrega
                    </h1>
                    <a href="dashboard.php" class="btn-fechar" title="Fechar e voltar ao painel">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
                
                <p class="section-description">
                    Selecione os estados e cidades do Brasil onde você realiza entregas. 
                    Os compradores serão notificados se estiverem em regiões não atendidas.
                    <br>
                    <small><i class="fas fa-info-circle"></i> Opcionalmente, você pode selecionar cidades específicas dentro de cada estado. Deixe todos desmarcados se atender todo o Brasil.</small>
                </p>
                
                <?php if ($mensagem): ?>
                    <div class="alert-message <?php echo $tipo_mensagem; ?>">
                        <i class="fas <?php echo $tipo_mensagem === 'sucesso' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                        <span><?php echo htmlspecialchars($mensagem); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="form-logistica">
                    <div class="controles-superiores">
                        <div class="contador-estados">
                            Estados selecionados: 
                            <span class="numero" id="contador-selecionados"><?php echo count($estados_atendidos); ?></span>
                            / 27
                        </div>
                        <button type="button" class="botao-acao" id="btn-marcar-todos">
                            <i class="fas fa-check-square"></i>
                            Marcar Todos
                        </button>
                    </div>
                    
                    <div class="estados-grid" id="estados-container">
                        <?php foreach ($estados_brasil as $sigla => $nome): ?>
                            <div class="estado-checkbox">
                                <label class="estado-label">
                                    <input type="checkbox" 
                                           name="estados[]" 
                                           value="<?php echo $sigla; ?>"
                                           <?php echo in_array($sigla, $estados_atendidos) ? 'checked' : ''; ?>
                                           class="checkbox-estado">
                                    <span class="estado-sigla"><?php echo $sigla; ?></span>
                                    <?php echo htmlspecialchars($nome); ?>
                                </label>
                                <button type="button" class="btn-cidades" data-sigla="<?php echo $sigla; ?>" title="Selecionar cidades">
                                    <i class="fas fa-city"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- SEÇÃO DE CIDADES (Opcional) -->
                    <div class="cidades-section" id="cidades-section">
                        <div class="estado-selecionado-info" id="estado-info">
                            <span class="estado-nome-selecionado">
                                <i class="fas fa-map-pin"></i>
                                <span id="estado-selecionado-label">Selecione um estado</span>
                            </span>
                            <button type="button" class="btn-fechar-estado" id="btn-fechar-cidades" title="Fechar seleção de cidades">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="cidades-grid" id="cidades-container">
                            <!-- As cidades serão carregadas via JavaScript -->
                        </div>
                    </div>
                    
                    <?php if (empty($estados_atendidos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-globe-americas"></i>
                            <p><strong>Nenhum estado selecionado</strong></p>
                            <p>Você atenderá compradores de todo o Brasil.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="acoes-inferiores">
                        <div class="grupo-esquerda">
                            <a href="dashboard.php" class="botao-acao">
                                <i class="fas fa-arrow-left"></i>
                                Voltar ao Painel
                            </a>
                            <button type="button" class="botao-acao" id="btn-limpar">
                                <i class="fas fa-trash-alt"></i>
                                Limpar Seleção
                            </button>
                        </div>
                        <div class="grupo-direita">
                            <button type="submit" class="botao-acao primary">
                                <i class="fas fa-save"></i>
                                Salvar Configuração
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Como funciona?</h4>
                    <ul>
                        <li>Selecione apenas os estados onde você tem condições de entregar seus produtos</li>
                        <li>Os compradores verão um alerta se estiverem em estados não selecionados</li>
                        <li>Deixe todos desmarcados para atender todo o território nacional</li>
                        <li>Você pode alterar essa configuração a qualquer momento</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Menu Hamburguer
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

            const checkboxes = document.querySelectorAll('.checkbox-estado');
            const btnMarcarTodos = document.getElementById('btn-marcar-todos');
            const btnLimpar = document.getElementById('btn-limpar');
            const contadorSelecionados = document.getElementById('contador-selecionados');
            
            // Atualizar contador
            function atualizarContador() {
                const selecionados = document.querySelectorAll('.checkbox-estado:checked').length;
                contadorSelecionados.textContent = selecionados;
                
                // Alterar texto do botão "Marcar Todos"
                if (selecionados === checkboxes.length) {
                    btnMarcarTodos.innerHTML = '<i class="fas fa-minus-square"></i> Desmarcar Todos';
                    btnMarcarTodos.title = "Desmarcar todos os estados";
                } else {
                    btnMarcarTodos.innerHTML = '<i class="fas fa-check-square"></i> Marcar Todos';
                    btnMarcarTodos.title = "Marcar todos os estados";
                }
            }
            
            // Marcar/desmarcar todos
            btnMarcarTodos.addEventListener('click', function() {
                const todosSelecionados = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                    cb.checked = !todosSelecionados;
                });
                
                atualizarContador();
            });
            
            // Limpar seleção
            btnLimpar.addEventListener('click', function() {
                const confirmar = confirm('Tem certeza que deseja limpar toda a seleção? Isso significa que você atenderá todo o Brasil.');
                
                if (confirmar) {
                    checkboxes.forEach(cb => {
                        cb.checked = false;
                    });
                    atualizarContador();
                }
            });
            
            // Atualizar contador quando checkboxes mudam
            checkboxes.forEach(cb => {
                cb.addEventListener('change', atualizarContador);
            });
            
            // Inicializar contador
            atualizarContador();
            
            // Prevenir envio se nenhum estado selecionado (mas permitir - significa Brasil todo)
            const formLogistica = document.getElementById('form-logistica');
            if (formLogistica) {
                formLogistica.addEventListener('submit', function(e) {
                    const selecionados = document.querySelectorAll('.checkbox-estado:checked').length;
                    
                    if (selecionados === 0) {
                        if (!confirm('Você não selecionou nenhum estado. Isso significa que atenderá todo o Brasil. Deseja continuar?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    // Mostrar mensagem de carregamento
                    const btnSalvar = this.querySelector('button[type="submit"]');
                    if (btnSalvar) {
                        const originalText = btnSalvar.innerHTML;
                        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                        btnSalvar.disabled = true;
                        
                        // Restaurar botão após 2 segundos (para caso de erro)
                        setTimeout(() => {
                            btnSalvar.innerHTML = originalText;
                            btnSalvar.disabled = false;
                        }, 2000);
                    }
                });
            }

            // Botão de fechar também funciona com tecla ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'dashboard.php';
                }
            });

            // Adicionar rolagem suave para melhor UX
            const estadoCheckboxes = document.querySelectorAll('.estado-checkbox');
            estadoCheckboxes.forEach(box => {
                box.addEventListener('click', function(e) {
                    if (e.target.type !== 'checkbox') {
                        const checkbox = this.querySelector('input[type="checkbox"]');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }
                });
            });

            // Scroll para o topo quando carregar
            window.scrollTo(0, 0);

            // ========== FUNCIONALIDADE DE SELEÇÃO DE CIDADES ==========
            
            // Carregar dados das cidades por estado (cache local). Se faltar, usaremos a API do IBGE.
            let cidadesPorEstado = {};
            // Cidades previamente salvas pelo vendedor (do servidor)
            const cidadesSelecionadas = <?php echo json_encode($cidades_atendidos ?? [], JSON_UNESCAPED_UNICODE); ?> || {};
            let cidadesLocalCarregadas = false;
            
            fetch('cidades_data.json')
                .then(response => response.json())
                .then(data => {
                    cidadesPorEstado = data || {};
                    cidadesLocalCarregadas = true;
                })
                .catch(error => {
                    console.warn('Arquivo cidades_data.json não encontrado ou inválido, usaremos API do IBGE:', error);
                    cidadesLocalCarregadas = false;
                });

            const estadosBrasil = {
                'AC': 'Acre', 'AL': 'Alagoas', 'AP': 'Amapá', 'AM': 'Amazonas',
                'BA': 'Bahia', 'CE': 'Ceará', 'DF': 'Distrito Federal', 'ES': 'Espírito Santo',
                'GO': 'Goiás', 'MA': 'Maranhão', 'MT': 'Mato Grosso', 'MS': 'Mato Grosso do Sul',
                'MG': 'Minas Gerais', 'PA': 'Pará', 'PB': 'Paraíba', 'PR': 'Paraná',
                'PE': 'Pernambuco', 'PI': 'Piauí', 'RJ': 'Rio de Janeiro', 'RN': 'Rio Grande do Norte',
                'RS': 'Rio Grande do Sul', 'RO': 'Rondônia', 'RR': 'Roraima',
                'SC': 'Santa Catarina', 'SP': 'São Paulo', 'SE': 'Sergipe', 'TO': 'Tocantins'
            };

            let estadoSelecionadoAtual = null;

            // Função para mostrar cidades de um estado
            function mostrarCidades(siglaEstado) {
                estadoSelecionadoAtual = siglaEstado;
                const nomeEstado = estadosBrasil[siglaEstado] || siglaEstado;
                const cidadesContainer = document.getElementById('cidades-container');
                cidadesContainer.innerHTML = '';

                // Atualizar header da seção de cidades
                const estadoLabelEl = document.getElementById('estado-selecionado-label');
                if (estadoLabelEl) {
                    estadoLabelEl.textContent = `${siglaEstado} - ${nomeEstado}`;
                }

                // Função auxiliar para renderizar lista de cidades
                function renderCidades(lista) {
                    if (!Array.isArray(lista) || lista.length === 0) {
                        cidadesContainer.innerHTML = '<div class="empty-state"><p>Nenhuma cidade encontrada para este estado.</p></div>';
                    } else {
                        // cidades selecionadas previamente pelo vendedor (servidor)
                        const selecionadas = (typeof cidadesSelecionadas !== 'undefined' && cidadesSelecionadas[siglaEstado]) ? cidadesSelecionadas[siglaEstado].map(c=>c.toLowerCase()) : [];

                        lista.forEach(cidade => {
                            const cidadeLower = cidade.toLowerCase();
                            const div = document.createElement('div');
                            div.className = 'cidade-checkbox';
                            const checkedAttr = selecionadas.includes(cidadeLower) ? 'checked' : '';
                            div.innerHTML = `
                                <label class="cidade-label">
                                    <input type="checkbox" name="cidades_${siglaEstado}[]" value="${cidade}" ${checkedAttr}>
                                    ${cidade}
                                </label>
                            `;
                            cidadesContainer.appendChild(div);
                        });
                    }
                    document.getElementById('cidades-section').classList.add('ativo');
                }

                // Se temos dados locais carregados e o estado existe no JSON, usar local
                const locais = cidadesPorEstado[siglaEstado];
                if (locais && locais.length > 0) {
                    renderCidades(locais);
                    return;
                }

                // Senão, buscar via API do IBGE (mais completa)
                const loading = document.createElement('div');
                loading.className = 'empty-state';
                loading.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando cidades...';
                cidadesContainer.appendChild(loading);

                fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${siglaEstado}/municipios`)
                    .then(resp => resp.json())
                    .then(municipios => {
                        const nomes = municipios.map(m => m.nome).sort((a,b) => a.localeCompare(b, 'pt-BR'));
                        // cachear localmente para evitar novas requisições
                        cidadesPorEstado[siglaEstado] = nomes;
                        cidadesContainer.innerHTML = '';
                        renderCidades(nomes);
                    })
                    .catch(err => {
                        console.error('Erro ao buscar cidades pelo IBGE:', err);
                        cidadesContainer.innerHTML = '<div class="empty-state"><p>Erro ao carregar cidades. Tente novamente mais tarde.</p></div>';
                    });
            }

            // Evento: abrir cidades ao clicar no botão .btn-cidades
            document.querySelectorAll('.btn-cidades').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const sigla = this.getAttribute('data-sigla');
                    if (sigla) {
                        mostrarCidades(sigla);
                    }
                });
            });

            // Manter dica ao marcar estado
            document.querySelectorAll('.checkbox-estado').forEach(checkbox => {
                checkbox.addEventListener('change', function(e) {
                    if (this.checked) {
                        // breve dica no console (pode ser melhorada para UI)
                        console.log('Dica: clique no ícone de cidade ao lado do estado para selecionar municípios.');
                    }
                });
            });

            // Fechar seção de cidades
            document.getElementById('btn-fechar-cidades').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('cidades-section').classList.remove('ativo');
                estadoSelecionadoAtual = null;
            });

            // Adicionar informação de dica aos usuários
            const infoBox = document.querySelector('.info-box');
            if (infoBox) {
                const dicaHtml = document.createElement('li');
                dicaHtml.innerHTML = '<strong>Dica:</strong> Clique duas vezes em um estado para selecionar cidades específicas dentro dele';
                infoBox.querySelector('ul').appendChild(dicaHtml);
            }
        });
    </script>
</body>
</html>