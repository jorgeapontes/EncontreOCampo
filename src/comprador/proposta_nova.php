<?php
// src/comprador/proposta_nova.php (Versão Atualizada com Cálculo Dinâmico)

session_start();
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['comprador', 'vendedor'])) {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador ou Vendedor."));
    exit();
}

// 2. OBTENÇÃO DO ID DO ANÚNCIO
if (!isset($_GET['anuncio_id']) || !is_numeric($_GET['anuncio_id'])) {
    header("Location: dashboard.php?erro=" . urlencode("Anúncio não especificado ou inválido."));
    exit();
}

$anuncio_id = (int)$_GET['anuncio_id'];
$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];

$database = new Database();
$conn = $database->getConnection();
$anuncio = null;

// Função auxiliar para calcular desconto (Reutilizável)
function calcularDesconto($preco, $preco_desconto, $data_expiracao) {
    if ($preco_desconto && $preco_desconto > 0 && $preco_desconto < $preco) {
        $agora = date('Y-m-d H:i:s');
        if (empty($data_expiracao) || $data_expiracao > $agora) {
            return [
                'ativo' => true,
                'preco_original' => $preco,
                'preco_final' => $preco_desconto,
                'porcentagem' => round((($preco - $preco_desconto) / $preco) * 100)
            ];
        }
    }
    return [
        'ativo' => false,
        'preco_final' => $preco
    ];
}

// 3. BUSCA DOS DETALHES DO ANÚNCIO (Incluindo campos de desconto)
try {
    $sql = "SELECT 
                p.id, 
                p.nome AS produto, 
                p.descricao,
                p.estoque AS quantidade_disponivel, 
                p.preco, 
                p.preco_desconto,             -- NOVO
                p.desconto_data_fim,    -- NOVO
                p.unidade_medida,
                p.imagem_url, 
                v.id AS vendedor_sistema_id, 
                u.id AS vendedor_usuario_id, 
                v.nome_comercial AS nome_vendedor
            FROM produtos p
            JOIN vendedores v ON p.vendedor_id = v.id 
            JOIN usuarios u ON v.usuario_id = u.id
            WHERE p.id = :anuncio_id AND p.status = 'ativo'";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt->execute();
    $anuncio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$anuncio) {
        header("Location: dashboard.php?erro=" . urlencode("Anúncio não encontrado ou inativo."));
        exit();
    }
} catch (PDOException $e) {
    die("Erro ao carregar anúncio: " . $e->getMessage()); 
}

// 4. VERIFICAR SE O VENDEDOR ESTÁ TENTANDO COMPRAR DE SI MESMO
if ($usuario_tipo === 'vendedor') {
    // Precisamos verificar se este vendedor é o dono do anúncio
    try {
        $sql_verifica_vendedor = "SELECT v.id 
                                  FROM vendedores v 
                                  JOIN produtos p ON p.vendedor_id = v.id 
                                  WHERE p.id = :anuncio_id AND v.usuario_id = :usuario_id";
        
        $stmt_verifica = $conn->prepare($sql_verifica_vendedor);
        $stmt_verifica->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
        $stmt_verifica->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_verifica->execute();
        
        if ($stmt_verifica->rowCount() > 0) {
            // O vendedor é o dono do anúncio, não pode comprar de si mesmo
            header("Location: ../anuncios.php?erro=" . urlencode("Você não pode comprar ou fazer proposta para seu próprio anúncio."));
            exit();
        }
    } catch (PDOException $e) {
        // Em caso de erro, continua (não bloqueia o acesso)
        error_log("Erro ao verificar vendedor: " . $e->getMessage());
    }
}

// Na seção onde busca o comprador_id, substitua pelo código completo:

// 5. BUSCAR O COMPRADOR_ID CORRETAMENTE (COM CRIAÇÃO AUTOMÁTICA)
$comprador_id = null;
try {
    $sql_busca_comprador = "SELECT id FROM compradores WHERE usuario_id = :usuario_id";
    
    $stmt_comprador = $conn->prepare($sql_busca_comprador);
    $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_comprador->execute();
    
    if ($stmt_comprador->rowCount() > 0) {
        $comprador = $stmt_comprador->fetch(PDO::FETCH_ASSOC);
        $comprador_id = $comprador['id'];
    } else {
        // Se não encontrar registro como comprador
        if ($usuario_tipo === 'vendedor') {
            // Buscar dados do vendedor para criar perfil de comprador
            $sql_busca_vendedor = "SELECT v.*, u.email, u.nome 
                                   FROM vendedores v 
                                   JOIN usuarios u ON v.usuario_id = u.id 
                                   WHERE v.usuario_id = :usuario_id";
            
            $stmt_vendedor = $conn->prepare($sql_busca_vendedor);
            $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $stmt_vendedor->execute();
            $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);
            
            if ($vendedor) {
                // Dados padrão caso algum campo esteja vazio
                $nome_comercial = !empty($vendedor['nome_comercial']) ? $vendedor['nome_comercial'] : $vendedor['nome'];
                $cpf_cnpj = !empty($vendedor['cpf_cnpj']) ? $vendedor['cpf_cnpj'] : '';
                $cep = !empty($vendedor['cep']) ? $vendedor['cep'] : '';
                $rua = !empty($vendedor['rua']) ? $vendedor['rua'] : '';
                $numero = !empty($vendedor['numero']) ? $vendedor['numero'] : '';
                $estado = !empty($vendedor['estado']) ? $vendedor['estado'] : '';
                $cidade = !empty($vendedor['cidade']) ? $vendedor['cidade'] : '';
                $telefone1 = !empty($vendedor['telefone1']) ? $vendedor['telefone1'] : '';
                
                // Criar registro na tabela compradores
                $sql_criar_comprador = "INSERT INTO compradores 
                                        (usuario_id, tipo_pessoa, nome_comercial, cpf_cnpj, cep, rua, numero, complemento, estado, cidade, telefone1, plano)
                                        VALUES 
                                        (:usuario_id, 'cpf', :nome_comercial, :cpf_cnpj, :cep, :rua, :numero, '', :estado, :cidade, :telefone1, 'free')";
                
                $stmt_criar = $conn->prepare($sql_criar_comprador);
                $stmt_criar->bindParam(':usuario_id', $usuario_id);
                $stmt_criar->bindParam(':nome_comercial', $nome_comercial);
                $stmt_criar->bindParam(':cpf_cnpj', $cpf_cnpj);
                $stmt_criar->bindParam(':cep', $cep);
                $stmt_criar->bindParam(':rua', $rua);
                $stmt_criar->bindParam(':numero', $numero);
                $stmt_criar->bindParam(':estado', $estado);
                $stmt_criar->bindParam(':cidade', $cidade);
                $stmt_criar->bindParam(':telefone1', $telefone1);
                
                if ($stmt_criar->execute()) {
                    $comprador_id = $conn->lastInsertId();
                    // Log para auditoria
                    error_log("Perfil de comprador criado automaticamente para vendedor ID: $usuario_id");
                } else {
                    error_log("Erro ao criar perfil de comprador para vendedor ID: $usuario_id");
                    header("Location: ../anuncios.php?erro=" . urlencode("Erro ao configurar perfil de comprador. Tente novamente."));
                    exit();
                }
            } else {
                // Vendedor não encontrado (situação incomum)
                error_log("Vendedor não encontrado ao tentar criar perfil de comprador. Usuario ID: $usuario_id");
                header("Location: ../anuncios.php?erro=" . urlencode("Perfil de vendedor incompleto. Atualize seus dados primeiro."));
                exit();
            }
        } else {
            // Usuário é comprador mas não tem registro (erro grave)
            error_log("Comprador sem registro na tabela compradores. Usuario ID: $usuario_id");
            header("Location: dashboard.php?erro=" . urlencode("Perfil de comprador incompleto. Entre em contato com o suporte."));
            exit();
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar/criar comprador_id: " . $e->getMessage());
    header("Location: dashboard.php?erro=" . urlencode("Erro temporário no sistema. Tente novamente em alguns minutos."));
    exit();
}
// Calcular desconto do produto principal
$info_desconto = calcularDesconto($anuncio['preco'], $anuncio['preco_desconto'], $anuncio['desconto_data_fim']);

// Buscar produtos relacionados (outros anúncios aleatórios) - Incluindo desconto
$produtos_relacionados = [];
try {
    $sql_relacionados = "SELECT 
                            p.id, 
                            p.nome, 
                            p.preco, 
                            p.preco_desconto,             -- NOVO
                            p.desconto_data_fim,    -- NOVO
                            p.imagem_url,
                            p.unidade_medida,
                            v.nome_comercial AS nome_vendedor
                         FROM produtos p
                         JOIN vendedores v ON p.vendedor_id = v.id 
                         JOIN usuarios u ON v.usuario_id = u.id
                         WHERE p.id != :anuncio_id 
                         AND p.status = 'ativo' 
                         AND p.estoque > 0
                         ORDER BY RAND() 
                         LIMIT 4";
    
    $stmt_relacionados = $conn->prepare($sql_relacionados);
    $stmt_relacionados->bindParam(':anuncio_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_relacionados->execute();
    $produtos_relacionados = $stmt_relacionados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se der erro, continua sem produtos relacionados
}

// Verificar se o produto já está nos favoritos do usuário
$is_favorito = false;
$favorito_id = null;
try {
    $sql_favorito = "SELECT id FROM favoritos WHERE usuario_id = :usuario_id AND produto_id = :produto_id";
    $stmt_favorito = $conn->prepare($sql_favorito);
    $stmt_favorito->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_favorito->bindParam(':produto_id', $anuncio_id, PDO::PARAM_INT);
    $stmt_favorito->execute();
    if ($stmt_favorito->rowCount() > 0) {
        $is_favorito = true;
        $favorito = $stmt_favorito->fetch(PDO::FETCH_ASSOC);
        $favorito_id = $favorito['id'];
    }
} catch (PDOException $e) {
    // Se a tabela não existir, ignora o erro
}

$preco_unitario = $info_desconto['preco_final'];
$preco_display = number_format($preco_unitario, 2, ',', '.');
$unidade = htmlspecialchars($anuncio['unidade_medida']);
$imagePath = $anuncio['imagem_url'] ? htmlspecialchars($anuncio['imagem_url']) : '../../img/placeholder.png';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($anuncio['produto']); ?> - Encontre o Campo</title>
    <link rel="stylesheet" href="../../index.css">
    <link rel="stylesheet" href="../css/comprador/proposta_nova.css?v=1.1">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ESTILO TEMPORÁRIO PARA CORRIGIR O PROBLEMA DO R$ */
        .input-with-symbol {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            background: white;
        }
        
        .input-with-symbol .currency-symbol {
            padding: 10px 12px;
            background: #f5f5f5;
            border-right: 1px solid #ddd;
            font-weight: bold;
            color: #333;
            white-space: nowrap;
        }
        
        .input-with-symbol input {
            flex: 1;
            border: none;
            padding: 10px;
            font-size: 16px;
            outline: none;
        }
        
        .input-with-symbol input:focus {
            outline: none;
        }
        
        /* Estilo para o valor total dinâmico */
        .preco-dinamico {
            transition: all 0.3s ease;
        }
        
        .preco-dinamico .valor-unitario {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        
        .preco-dinamico .valor-total-label {
            font-size: 14px;
            color: #2E7D32;
            margin-top: 5px;
            font-weight: 600;
        }
    </style>
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
                <!-- <li class="nav-item"><a href="minhas_propostas.php" class="nav-link">Minhas Propostas</a></li> -->
                <li class="nav-item"><a href="favoritos.php" class="nav-link">Favoritos</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
            </ul>
            <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
            </div>
        </div>
    </nav>

    <main class="container produto-container">
        <div class="produto-content">
            <!-- Seção de Imagem do Produto -->
            <div class="produto-imagem">
                <div class="imagem-principal">
                    <?php if ($info_desconto['ativo']): ?>
                        <div class="badge-desconto-lg">-<?php echo $info_desconto['porcentagem']; ?>%</div>
                    <?php endif; ?>
                    <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($anuncio['produto']); ?>">
                </div>
                
                <!-- Botão de Favoritar -->
                <?php if ($is_favorito && $favorito_id): ?>
                    <a href="remover_favorito.php?favorito_id=<?php echo $favorito_id; ?>&redirect=proposta_nova.php?anuncio_id=<?php echo $anuncio_id; ?>" 
                       class="btn-favoritar favoritado">
                        <i class="fas fa-heart"></i>
                        <span>Favoritado</span>
                    </a>
                <?php else: ?>
                    <a href="adicionar_favorito.php?produto_id=<?php echo $anuncio_id; ?>&redirect=proposta_nova.php?anuncio_id=<?php echo $anuncio_id; ?>" 
                       class="btn-favoritar">
                        <i class="far fa-heart"></i>
                        <span>Favoritar</span>
                    </a>
                <?php endif; ?>

                <!-- Botão de Compartilhar -->
                <button class="btn-compartilhar" id="btn-compartilhar">
                    <i class="fas fa-share-alt"></i>
                    <span>Compartilhar</span>
                </button>
            </div>

            <!-- Formulário de Compra -->
            <div class="compra-section">
                <!-- Seção de Informações do Produto -->
                <div class="produto-info">
                    <div class="info-header">
                        <h1><?php echo htmlspecialchars($anuncio['produto']); ?></h1>
                        <div class="vendedor-info">
                            <span class="vendedor-label">Vendido por:</span>
                            <a href="../perfil_vendedor.php?vendedor_id=<?php echo $anuncio['vendedor_usuario_id']; ?>" class="vendedor-nome">
                                <?php echo htmlspecialchars($anuncio['nome_vendedor']); ?>
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>

                    <div class="preco-section preco-dinamico">
                        <div class="preco-wrapper">
                            <span class="unidade">por <?php echo $unidade; ?></span>
                            <?php if ($info_desconto['ativo']): ?>
                                <span class="preco-antigo">R$ <?php echo number_format($info_desconto['preco_original'], 2, ',', '.'); ?></span>
                                <span class="preco-atual destaque-oferta" id="preco-atual">R$ <?php echo $preco_display; ?></span>
                            <?php else: ?>
                                <span class="preco-atual" id="preco-atual">R$ <?php echo $preco_display; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Aqui mostraremos o valor unitário e total -->
                        <div class="valor-unitario" id="valor-unitario">
                            Preço unitário: R$ <?php echo $preco_display; ?>
                        </div>
                        <div class="valor-total-label" id="valor-total-label">
                            Total: <span id="valor-total">R$ <?php echo $preco_display; ?></span>
                        </div>
                    </div>
                    
                    <div class="estoque-info">
                        <i class="fas fa-box"></i>
                        <span><?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?> disponíveis</span>
                    </div>

                    <div class="quantidade-selector">
                        <label for="quantidade">Quantidade:</label>
                        <div class="quantidade-control">
                            <button type="button" class="qty-btn" id="decrease-qty">-</button>
                            <input type="number" id="quantidade" name="quantidade" value="1" min="1" max="<?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?>">
                            <button type="button" class="qty-btn" id="increase-qty">+</button>
                        </div>
                        <span class="unidade-info"><?php echo $unidade; ?></span>
                    </div>

                    <div class="botoes-compra">
                        <button type="button" class="btn-comprar" id="btn-comprar">
                            <i class="fas fa-shopping-cart"></i>
                            Comprar Agora
                        </button>
                        
                        <div class="proposta-option">
                            <p class="proposta-text">Algo não te agradou?</p>
                            <button type="button" class="btn-proposta" id="btn-fazer-proposta">
                                <i class="fas fa-handshake"></i>
                                Fazer Proposta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Descrição do Produto (Abaixo da seção principal) -->
        <?php if ($anuncio['descricao']): ?>
        <div class="descricao-completa">
            <div class="descricao-header">
                <h3><i class="fas fa-file-alt"></i> Descrição do Produto</h3>
            </div>
            <div class="descricao-content">
                <p><?php echo nl2br(htmlspecialchars($anuncio['descricao'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Seção de Produtos que Podem te Interessar -->
        <?php if (!empty($produtos_relacionados)): ?>
        <div class="produtos-relacionados">
            <div class="relacionados-header">
                <h3><i class="fas fa-star"></i> Outros anúncios</h3>
                <p>Descobrir outros produtos disponíveis:</p>
            </div>
            <div class="relacionados-grid">
                <?php foreach ($produtos_relacionados as $produto): 
                    $desc_rel = calcularDesconto($produto['preco'], $produto['preco_desconto'], $produto['desconto_data_fim']);
                    $imagem_produto = $produto['imagem_url'] ? htmlspecialchars($produto['imagem_url']) : '../../img/placeholder.png';
                ?>
                    <div class="produto-card <?php echo $desc_rel['ativo'] ? 'card-oferta' : ''; ?>">
                        <a href="proposta_nova.php?anuncio_id=<?php echo $produto['id']; ?>" class="produto-link">
                            <div class="produto-imagem-card">
                                <?php if ($desc_rel['ativo']): ?>
                                    <div class="badge-desconto-sm">-<?php echo $desc_rel['porcentagem']; ?>%</div>
                                <?php endif; ?>
                                <img src="<?php echo $imagem_produto; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                            </div>
                            <div class="produto-info-card">
                                <h4><?php echo htmlspecialchars($produto['nome']); ?></h4>
                                <p class="vendedor-card">por <?php echo htmlspecialchars($produto['nome_vendedor']); ?></p>
                                <div class="preco-card">
                                    <?php if ($desc_rel['ativo']): ?>
                                        <div class="preco-container-sm">
                                            <span class="preco-antigo-sm">R$ <?php echo number_format($desc_rel['preco_original'], 2, ',', '.'); ?></span>
                                            <span class="preco destaque">R$ <?php echo number_format($desc_rel['preco_final'], 2, ',', '.'); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="preco">R$ <?php echo number_format($desc_rel['preco_final'], 2, ',', '.'); ?></span>
                                    <?php endif; ?>
                                    <span class="unidade-card">/ <?php echo htmlspecialchars($produto['unidade_medida']); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulário de Proposta (Inicialmente Oculto) -->
        <div class="proposta-section" id="proposta-section">
            <div class="proposta-header">
                <h2><i class="fas fa-handshake"></i> Fazer Proposta</h2>
                <p>Negocie diretamente com o vendedor</p>
            </div>

            <form action="processar_proposta.php" method="POST" class="proposta-form">
                <input type="hidden" name="produto_id" value="<?php echo $anuncio_id; ?>">
                <input type="hidden" name="comprador_id" value="<?php echo $comprador_id; ?>">
                <input type="hidden" name="usuario_tipo" value="<?php echo $usuario_tipo; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="preco_proposto">
                            <i class="fas fa-tag"></i>
                            Seu Preço Proposto
                            <span class="unit">(por <?php echo $unidade; ?>)</span>
                        </label>
                        <div class="input-with-symbol">
                            <span class="currency-symbol">R$</span>
                            <!-- Valor inicial será calculado pelo JavaScript -->
                            <input type="number" id="preco_proposto" name="preco_proposto" 
                                   step="0.01" min="0.01" required
                                   value="<?php echo number_format($info_desconto['preco_final'], 2, '.', ''); ?>"
                                   placeholder="0.00">
                        </div>
                        <small>Digite o valor que você deseja pagar por <?php echo $unidade; ?></small>
                        <div class="proposta-info">
                            <small>Preço unitário atual: <span id="preco-unitario-info">R$ <?php echo $preco_display; ?></span></small><br>
                            <small>Quantidade: <span id="quantidade-info">1</span> <?php echo $unidade; ?></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="quantidade_proposta">
                            <i class="fas fa-box"></i>
                            Quantidade Desejada
                            <span class="unit">(em <?php echo $unidade; ?>)</span>
                        </label>
                        <input type="number" id="quantidade_proposta" name="quantidade_proposta" 
                               min="1" max="<?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?>" 
                               required value="1">
                        <small>Máximo disponível: <?php echo htmlspecialchars($anuncio['quantidade_disponivel']); ?> <?php echo $unidade; ?></small>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="condicoes">
                        <i class="fas fa-file-alt"></i>
                        Condições/Detalhes da Proposta ou Frete
                        <span class="optional">(Opcional)</span>
                    </label>
                    <textarea id="condicoes" name="condicoes" rows="4" 
                              placeholder="Adicione aqui detalhes para a negociação..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Proposta
                    </button>
                    <button type="button" class="btn btn-secondary" id="btn-cancelar-proposta">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Encontre o Campo</h4>
                    <ul>
                        <li><a href="../../index.php">Página Inicial</a></li>
                        <li><a href="../anuncios.php">Ver Anúncios</a></li>
                        <li><a href="favoritos.php">Meus Favoritos</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Suporte</h4>
                    <ul>
                        <li><a href="../ajuda.php">Central de Ajuda</a></li>
                        <li><a href="../contato.php">Fale Conosco</a></li>
                        <li><a href="../sobre.php">Sobre Nós</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="../faq.php">FAQ</a></li>
                        <li><a href="../termos.php">Termos de Uso</a></li>
                        <li><a href="../privacidade.php">Política de Privacidade</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contato</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> contato@encontreocampo.com.br</p>
                        <p><i class="fas fa-phone"></i> (11) 99999-9999</p>
                        <div class="social-links">
                            <a href="#"><i class="fab fa-facebook"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
        // Menu Hamburguer
        const hamburger = document.querySelector(".hamburger");
        const navMenu = document.querySelector(".nav-menu");

        hamburger.addEventListener("click", () => {
            hamburger.classList.toggle("active");
            navMenu.classList.toggle("active");
        });

        document.querySelectorAll(".nav-link").forEach(n => n.addEventListener("click", () => {
            hamburger.classList.remove("active");
            navMenu.classList.remove("active");
        }));
        
        // Favoritar produto - Removido o JavaScript antigo e mantido apenas links HTML
        // A funcionalidade agora é feita via links PHP (adicionar_favorito.php e remover_favorito.php)

        });

        // Scripts originais mantidos e funcionais
        const quantidadeInput = document.getElementById('quantidade');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');
        const btnFazerProposta = document.getElementById('btn-fazer-proposta');
        const btnCancelarProposta = document.getElementById('btn-cancelar-proposta');
        const propostaSection = document.getElementById('proposta-section');
        const quantidadeProposta = document.getElementById('quantidade_proposta');
        const btnCompartilhar = document.getElementById('btn-compartilhar');
        const precoAtualElement = document.getElementById('preco-atual');
        const valorTotalElement = document.getElementById('valor-total');
        const valorUnitarioElement = document.getElementById('valor-unitario');
        const valorTotalLabel = document.getElementById('valor-total-label');
        const precoPropostoInput = document.getElementById('preco_proposto');
        const precoUnitarioInfo = document.getElementById('preco-unitario-info');
        const quantidadeInfo = document.getElementById('quantidade-info');
        
        // Preço unitário do produto (com desconto se aplicável)
        const precoUnitario = <?php echo $preco_unitario; ?>;

        propostaSection.style.display = 'none';
        
        // Função para formatar valor no padrão brasileiro
        function formatarValor(valor) {
            return valor.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Função para calcular e exibir o valor total
        function atualizarValorTotal() {
            const quantidade = parseInt(quantidadeInput.value);
            
            if (quantidade && quantidade > 0) {
                const valorTotal = precoUnitario * quantidade;
                const valorUnitarioFormatado = formatarValor(precoUnitario);
                const valorTotalFormatado = formatarValor(valorTotal);
                
                // Atualizar o preço grande na tela
                if (quantidade === 1) {
                    // Se for apenas 1 unidade, mostra apenas o preço unitário
                    precoAtualElement.textContent = `R$ ${valorUnitarioFormatado}`;
                    valorTotalLabel.style.display = 'none';
                } else {
                    // Se for mais de 1 unidade, mostra o preço unitário e o total
                    precoAtualElement.textContent = `R$ ${valorTotalFormatado}`;
                    valorTotalLabel.style.display = 'block';
                    valorTotalElement.textContent = `R$ ${valorTotalFormatado}`;
                }
                
                // Atualizar também o campo de quantidade na proposta
                if (quantidadeProposta) {
                    quantidadeProposta.value = quantidade;
                }
                
                // Atualizar o valor unitário informativo
                valorUnitarioElement.textContent = `Preço unitário: R$ ${valorUnitarioFormatado} por ${'<?php echo $unidade; ?>'}`;
                
                // Atualizar as informações na seção de proposta
                precoUnitarioInfo.textContent = `R$ ${valorUnitarioFormatado}`;
                quantidadeInfo.textContent = quantidade;
                
                // Atualizar o campo de preço proposto para o valor total
                // IMPORTANTE: O campo preco_proposto é o preço POR UNIDADE, não o total
                // Por isso mantemos o preço unitário como valor inicial
                if (precoPropostoInput.value == <?php echo $preco_unitario; ?>) {
                    // Se o usuário ainda não modificou o preço proposto, atualizamos para o preço unitário atual
                    precoPropostoInput.value = precoUnitario.toFixed(2);
                }
            }
        }
        
        // Atualizar valor total inicial
        atualizarValorTotal();

        decreaseBtn.addEventListener('click', () => {
            if (quantidadeInput.value > 1) {
                quantidadeInput.value = parseInt(quantidadeInput.value) - 1;
                atualizarValorTotal();
            }
        });

        increaseBtn.addEventListener('click', () => {
            const max = parseInt(quantidadeInput.max);
            if (quantidadeInput.value < max) {
                quantidadeInput.value = parseInt(quantidadeInput.value) + 1;
                atualizarValorTotal();
            }
        });

        quantidadeInput.addEventListener('change', () => {
            let value = parseInt(quantidadeInput.value);
            const max = parseInt(quantidadeInput.max);
            const min = parseInt(quantidadeInput.min);
            if (value < min) value = min;
            if (value > max) value = max;
            quantidadeInput.value = value;
            atualizarValorTotal();
        });
        
        // Atualizar valor total também quando o usuário digitar
        quantidadeInput.addEventListener('input', () => {
            atualizarValorTotal();
        });
        
        // Quando o usuário muda a quantidade na proposta, atualizar a quantidade principal
        if (quantidadeProposta) {
            quantidadeProposta.addEventListener('change', () => {
                let value = parseInt(quantidadeProposta.value);
                const max = parseInt(quantidadeProposta.max);
                const min = parseInt(quantidadeProposta.min);
                if (value < min) value = min;
                if (value > max) value = max;
                quantidadeProposta.value = value;
                quantidadeInput.value = value;
                atualizarValorTotal();
            });
            
            quantidadeProposta.addEventListener('input', () => {
                quantidadeInput.value = quantidadeProposta.value;
                atualizarValorTotal();
            });
        }

        let propostaAberta = false;
        btnFazerProposta.addEventListener('click', () => {
            if (!propostaAberta) {
                propostaSection.style.display = 'block';
                propostaSection.classList.add('show');
                btnFazerProposta.innerHTML = '<i class="fas fa-times"></i>Fechar Proposta';
                btnFazerProposta.classList.add('active');
                propostaSection.scrollIntoView({ behavior: 'smooth' });
                propostaAberta = true;
            } else {
                propostaSection.classList.remove('show');
                setTimeout(() => { propostaSection.style.display = 'none'; }, 300);
                btnFazerProposta.innerHTML = '<i class="fas fa-handshake"></i>Fazer Proposta';
                btnFazerProposta.classList.remove('active');
                propostaAberta = false;
            }
        });

        btnCancelarProposta.addEventListener('click', () => {
            propostaSection.classList.remove('show');
            setTimeout(() => { propostaSection.style.display = 'none'; }, 300);
            btnFazerProposta.innerHTML = '<i class="fas fa-handshake"></i>Fazer Proposta';
            btnFazerProposta.classList.remove('active');
                propostaAberta = false;
            });

        // Logica de compartilhar
        if (btnCompartilhar) {
            btnCompartilhar.addEventListener('click', function() {
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    alert('Link copiado para a área de transferência!');
                });
            });
        }
        
        // Botão Comprar Agora - ação para compra direta
        const btnComprar = document.getElementById('btn-comprar');
        if (btnComprar) {
            btnComprar.addEventListener('click', function() {
                const quantidade = quantidadeInput.value;
                const valorTotal = precoUnitario * quantidade;
                
                // Formatar valores para exibição
                const valorTotalFormatado = formatarValor(valorTotal);
                const precoUnitarioFormatado = formatarValor(precoUnitario);
                
                // Confirmar a compra
                const confirmar = confirm(`Você está comprando ${quantidade} ${'<?php echo $unidade; ?>'} de ${'<?php echo htmlspecialchars($anuncio['produto']); ?>'}\n\n` +
                                         `Preço unitário: R$ ${precoUnitarioFormatado}\n` +
                                         `Valor total: R$ ${valorTotalFormatado}\n\n` +
                                         `Deseja prosseguir com a compra?`);
                
                if (confirmar) {
                    // Aqui você pode redirecionar para a página de checkout ou processar a compra
                    alert('Funcionalidade de compra direta em desenvolvimento. Para comprar, use a opção "Fazer Proposta".');
                    // window.location.href = `processar_compra.php?anuncio_id=<?php echo $anuncio_id; ?>&quantidade=${quantidade}`;
                }
            });
        }
    </script>
</body>
</html>