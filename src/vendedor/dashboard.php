<?php
// src/vendedor/dashboard.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

// Verificar se o usuário tem permissão para ver dashboard completo
$usuario_status = $_SESSION['usuario_status'] ?? 'pendente';
$is_pendente = ($usuario_status === 'pendente');

$usuario_nome = htmlspecialchars($_SESSION['vendedor_nome'] ?? 'Vendedor');
$usuario_id = $_SESSION['usuario_id'];

// Conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Buscar dados do vendedor E O PLANO (Mesma lógica de anuncios.php)
$vendedor_id = null;
$vendedor_nome_comercial = '';
$limite_permitido = 1; // Padrão

try {
    $sql_vendedor = "SELECT v.id, v.nome_comercial, v.plano_id, p.nome as nome_plano, p.limite_total_anuncios
                     FROM vendedores v
                     LEFT JOIN planos p ON v.plano_id = p.id
                     WHERE v.usuario_id = :usuario_id";
                     
    $stmt_vendedor = $db->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);
    
    if ($vendedor) {
        $vendedor_id = $vendedor['id'];
        $vendedor_nome_comercial = $vendedor['nome_comercial'] ?? $usuario_nome;
        // Pega o limite do banco ou usa 1 como fallback
        $limite_permitido = $vendedor['limite_total_anuncios'] ?? 1;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do vendedor: " . $e->getMessage());
}

// VERIFICAR PREFERÊNCIAS DE AVISOS DO USUÁRIO
$exibir_aviso_regioes = true;
try {
    $sql_avisos = "SELECT aviso_regioes_entrega FROM usuario_avisos_preferencias WHERE usuario_id = :usuario_id";
    $stmt_avisos = $db->prepare($sql_avisos);
    $stmt_avisos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_avisos->execute();
    $preferencias_avisos = $stmt_avisos->fetch(PDO::FETCH_ASSOC);
    
    if ($preferencias_avisos) {
        $exibir_aviso_regioes = (bool)$preferencias_avisos['aviso_regioes_entrega'];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar preferências de avisos: " . $e->getMessage());
}

// Inicializar variáveis
$total_anuncios = 0;
$anuncios = [];
$total_propostas_pendentes = 0;
$total_mensagens_nao_lidas = 0;
$total_favoritos = 0;

// Só busca estatísticas se o vendedor for ativo
if (!$is_pendente && $vendedor_id) {
    // 1. BUSCAR TODOS OS ANÚNCIOS ORDENADOS POR ID (Antigos primeiro)
    // Removemos "AND status = 'ativo'" para calcular o bloqueio corretamente
    $query_anuncios = "SELECT id, nome, estoque, preco, status, data_criacao 
                       FROM produtos 
                       WHERE vendedor_id = :vendedor_id 
                       ORDER BY id ASC"; 
                       
    $stmt_anuncios = $db->prepare($query_anuncios);
    $stmt_anuncios->bindParam(':vendedor_id', $vendedor_id);
    $stmt_anuncios->execute();
    $anuncios = $stmt_anuncios->fetchAll(PDO::FETCH_ASSOC);

    $total_anuncios = count($anuncios);

    // CONTADOR DE PROPOSTAS PENDENTES
    try {
        $query_propostas = "SELECT COUNT(pc.id) as total_pendentes
                            FROM propostas_comprador pc
                            INNER JOIN propostas_negociacao pn ON pc.id = pn.proposta_comprador_id
                            INNER JOIN produtos p ON pn.produto_id = p.id
                            WHERE p.vendedor_id = :vendedor_id 
                            AND pc.status = 'enviada'
                            AND pn.status = 'negociacao'";
                            
        $stmt_propostas = $db->prepare($query_propostas);
        $stmt_propostas->bindParam(':vendedor_id', $vendedor_id);
        $stmt_propostas->execute();
        $resultado = $stmt_propostas->fetch(PDO::FETCH_ASSOC);
        
        $total_propostas_pendentes = $resultado['total_pendentes'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Erro ao contar propostas pendentes: " . $e->getMessage());
    }

    // CONTADOR DE MENSAGENS NÃO LIDAS
    try {
        $query_mensagens = "SELECT COUNT(DISTINCT cm.conversa_id) as total_conversas_nao_lidas
                            FROM chat_mensagens cm
                            INNER JOIN chat_conversas cc ON cm.conversa_id = cc.id
                            INNER JOIN produtos p ON cc.produto_id = p.id
                            WHERE p.vendedor_id = :vendedor_id 
                            AND cm.remetente_id != :usuario_id
                            AND cm.lida = 0";
                            
        $stmt_mensagens = $db->prepare($query_mensagens);
        $stmt_mensagens->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
        $stmt_mensagens->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_mensagens->execute();
        $resultado_msg = $stmt_mensagens->fetch(PDO::FETCH_ASSOC);
        
        $total_mensagens_nao_lidas = $resultado_msg['total_conversas_nao_lidas'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Erro ao contar mensagens não lidas: " . $e->getMessage());
    }
}

// BUSCA DO TOTAL DE FAVORITOS
try {
    $sql_favoritos = "SELECT COUNT(id) AS total_favoritos FROM favoritos WHERE usuario_id = :usuario_id";
    $stmt_favoritos = $db->prepare($sql_favoritos);
    $stmt_favoritos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_favoritos->execute();
    $resultado_favoritos = $stmt_favoritos->fetch(PDO::FETCH_ASSOC);
    $total_favoritos = $resultado_favoritos ? $resultado_favoritos['total_favoritos'] : 0;
} catch (PDOException $e) {
    $total_favoritos = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Vendedor - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/vendedor/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        /* Estilos do Pop-up (Mantidos) */
        .popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 9998; animation: fadeIn 0.3s ease; }
        .popup-overlay.active { display: flex; align-items: center; justify-content: center; }
        .popup-container { background: white; border-radius: 15px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); animation: slideUp 0.3s ease; position: relative; }
        .popup-header { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 25px; border-radius: 15px 15px 0 0; text-align: center; position: relative; }
        .popup-header h2 { margin: 0; font-size: 24px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .popup-close { position: absolute; top: 15px; right: 15px; background: rgba(255, 255, 255, 0.2); border: none; color: white; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 20px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; }
        .popup-close:hover { background: rgba(255, 255, 255, 0.3); transform: rotate(90deg); }
        .popup-body { padding: 30px; }
        .aviso-item { background: #f8f9fa; border-left: 4px solid #4CAF50; padding: 20px; margin-bottom: 20px; border-radius: 8px; transition: all 0.3s; }
        .aviso-item:hover { transform: translateX(5px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .aviso-item h3 { color: #4CAF50; margin: 0 0 10px 0; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .aviso-item p { color: #555; line-height: 1.6; margin: 0; }
        .aviso-item a { color: #4CAF50; font-weight: 600; text-decoration: none; transition: all 0.3s; }
        .aviso-item a:hover { color: #45a049; text-decoration: underline; }
        .popup-footer { padding: 20px 30px; border-top: 1px solid #e0e0e0; background: #f8f9fa; border-radius: 0 0 15px 15px; }
        .checkbox-container { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; cursor: pointer; }
        .checkbox-container input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #4CAF50; }
        .checkbox-container label { color: #555; font-size: 14px; cursor: pointer; user-select: none; }
        .popup-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-popup { padding: 12px 25px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 14px; }
        .btn-primary { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3); }
        .btn-secondary { background: #e0e0e0; color: #555; }
        .btn-secondary:hover { background: #d0d0d0; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @media (max-width: 768px) {
            .popup-container { width: 95%; max-height: 90vh; }
            .popup-actions { flex-direction: column; }
            .btn-popup { width: 100%; }
        }

        /* NOVOS ESTILOS PARA LINHAS BLOQUEADAS (Mesmo do anuncios.php) */
        .locked-row {
            background-color: #f1f2f6;
            opacity: 0.7;
        }
        .locked-row td {
            color: #636e72;
        }
        .locked-badge {
            background-color: #2d3436;
            color: #fab1a0;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
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
                    <li class="nav-item"><a href="../../index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="../anuncios.php" class="nav-link">Anúncios</a></li>
                    <li class="nav-item"><a href="" class="nav-link active">Painel</a></li>
                    <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a href="../notificacoes.php" class="nav-link no-underline">
                            <i class="fas fa-bell"></i>
                            <?php
                            if (isset($_SESSION['usuario_id'])) {
                                $sql_nao_lidas = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = :usuario_id AND lida = 0";
                                $stmt_nao_lidas = $db->prepare($sql_nao_lidas);
                                $stmt_nao_lidas->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                                $stmt_nao_lidas->execute();
                                $total_nao_lidas = $stmt_nao_lidas->fetch(PDO::FETCH_ASSOC)['total'];
                                if ($total_nao_lidas > 0) echo '<span class="notificacao-badge">'.$total_nao_lidas.'</span>';
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item"><a href="../logout.php" class="nav-link exit-button no-underline">Sair</a></li>
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

    <?php if (!$is_pendente && $exibir_aviso_regioes): ?>
    <div class="popup-overlay" id="popupAvisos">
        <div class="popup-container">
            <div class="popup-header">
                <h2><i class="fas fa-info-circle"></i> Avisos Importantes</h2>
                <button class="popup-close" onclick="fecharPopup()"><i class="fas fa-times"></i></button>
            </div>
            <div class="popup-body">
                <div class="aviso-item">
                    <h3><i class="fas fa-truck"></i> Configure suas Regiões de Entrega</h3>
                    <p>Não se esqueça de configurar as regiões onde você realiza entregas!</p>
                    <p style="margin-top: 10px;"><a href="config_logistica.php">Clique aqui para configurar agora</a></p>
                </div>
                <div class="aviso-item">
                    <h3><i class="fas fa-bell"></i> Plano de assinatura</h3>
                    <p>Caso queria ter mais anúncios ativos, altere seu plano em "Meu Perfil".</p>
                    <p style="margin-top: 10px;"><a href="perfil.php">Alterar plano</a></p>
                </div>
            </div>
            <div class="popup-footer">
                <div class="checkbox-container">
                    <input type="checkbox" id="naoExibirNovamente" name="naoExibirNovamente">
                    <label for="naoExibirNovamente">Não exibir esta mensagem novamente</label>
                </div>
                <div class="popup-actions">
                    <button class="btn-popup btn-secondary" onclick="fecharPopup()">Fechar</button>
                    <button class="btn-popup btn-primary" onclick="salvarPreferencia()">Entendi</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-content">
        <section class="header">
            <center>
                <h1>Bem-vindo(a), <?php echo htmlspecialchars($vendedor_nome_comercial); ?>!</h1>
                <?php if ($is_pendente): ?>
                    <p class="subtitulo">(Cadastro aguardando aprovação)</p>
                <?php endif; ?>
            </center>
        </section>

        <?php if ($is_pendente): ?>
            <div class="aviso-status">
                <i class="fas fa-info-circle"></i>
                <strong>Seu cadastro está aguardando aprovação.</strong>
            </div>
        <?php endif; ?>
        
        <section class="info-cards">
            <?php if (!$is_pendente): ?>
                <a href="anuncios.php">
                    <div class="card">
                        <i class="fas fa-bullhorn"></i>
                        <h3>Anúncios</h3>
                        <p><?php echo $total_anuncios; ?></p>
                    </div>
                </a>
                <a href="chats.php">
                    <div class="card">
                        <i class="fas fa-comments"></i>
                        <h3>Chats</h3>
                        <p><?php echo $total_mensagens_nao_lidas; ?> não lidas</p>
                    </div>
                </a>
                <a href="vendas.php">
                    <div class="card">
                        <i class="fas fa-dollar-sign"></i>
                        <h3>Minhas vendas</h3>
                        <p>Ver</p>
                    </div>
                </a>
                <a href="#"> 
                    <div class="card"> 
                        <i class="fa-solid fa-bag-shopping"></i> 
                        <h3>Minhas Compras</h3> 
                        <p>Ver</p> 
                    </div> 
                </a>
                <a href="config_logistica.php"> 
                    <div class="card"> 
                        <i class="fas fa-truck"></i> 
                        <h3>Regiões de entrega</h3> 
                        <p>Ver</p> 
                    </div> 
                </a>
            <?php endif; ?>
        </section>

        <?php if (!$is_pendente && $vendedor_id): ?>
            <section class="section-anuncios">
                <div id="header">
                    <h2>Meus Anúncios (<?php echo $total_anuncios; ?>)</h2>
                    
                    <?php if ($total_anuncios < $limite_permitido): ?>
                        <a href="anuncio_novo.php" class="cta-button"><i class="fas fa-plus"></i> Novo Anúncio</a>
                    <?php else: ?>
                        <button class="cta-button" style="background-color: #b2bec3; cursor: not-allowed;" onclick="alert('Limite do plano atingido!');"><i class="fas fa-lock"></i> Limite Atingido</button>
                    <?php endif; ?>
                    
                    <a href="anuncios.php" class="cta-button"><i class="fas fa-list"></i> Ver Todos</a>
                </div>
                
                <div class="tabela-anuncios">
                    <?php if ($total_anuncios > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fruta/Produto</th>
                                    <th>Estoque (Kg)</th>
                                    <th>Preço/Kg</th>
                                    <th>Status</th>
                                    <th>Criação</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $contador = 0;
                                foreach ($anuncios as $anuncio): 
                                    $contador++;
                                    // Lógica de bloqueio: se exceder o limite, bloqueia
                                    $bloqueado = ($contador > $limite_permitido);
                                ?>
                                <tr class="<?php echo $bloqueado ? 'locked-row' : ''; ?>">
                                    <td><?php echo $anuncio['id']; ?></td>
                                    <td><?php echo htmlspecialchars($anuncio['nome']); ?></td>
                                    <td><?php echo number_format($anuncio['estoque'], 0, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?></td>
                                    <td>
                                        <?php if ($bloqueado): ?>
                                            <span class="locked-badge"><i class="fas fa-lock"></i> Plano Excedido</span>
                                        <?php else: ?>
                                            <span class="status <?php echo $anuncio['status']; ?>"><?php echo ucfirst($anuncio['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($anuncio['data_criacao'])); ?></td>
                                    <td>
                                        <?php if ($bloqueado): ?>
                                            <button class="action-btn" disabled style="opacity:0.3; cursor:not-allowed;"><i class="fas fa-edit"></i></button>
                                            
                                            <form method="POST" action="processar_anuncio.php" style="display: inline;">
                                                <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                                                <button type="submit" name="acao" value="deletar" class="action-btn delete" title="Excluir para liberar espaço" onclick="return confirm('Tem certeza que deseja DELETAR este anúncio?');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a href="anuncio_editar.php?id=<?php echo $anuncio['id']; ?>" class="action-btn edit" title="Editar"><i class="fas fa-edit"></i></a>
                                            <form method="POST" action="processar_anuncio.php" style="display: inline;">
                                                <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                                                <button type="submit" name="acao" value="deletar" class="action-btn delete" title="Excluir Definitivamente" onclick="return confirm('Tem certeza que deseja DELETAR este anúncio? Esta ação é irreversível.');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="cards-anuncios-mobile">
                            <?php 
                            $contador_mobile = 0;
                            foreach ($anuncios as $anuncio): 
                                $contador_mobile++;
                                $bloqueado = ($contador_mobile > $limite_permitido);
                            ?>
                            <div class="card-anuncio <?php echo $bloqueado ? 'locked-row' : ''; ?>" style="<?php echo $bloqueado ? 'background-color: #f1f2f6; opacity: 0.8;' : ''; ?>">
                                <div class="card-anuncio-header">
                                    <div class="card-anuncio-title">
                                        <h3><?php echo htmlspecialchars($anuncio['nome']); ?></h3>
                                        <span class="card-anuncio-id">ID: <?php echo $anuncio['id']; ?></span>
                                    </div>
                                    <?php if ($bloqueado): ?>
                                        <span class="locked-badge"><i class="fas fa-lock"></i> Bloqueado</span>
                                    <?php else: ?>
                                        <span class="card-anuncio-status status <?php echo $anuncio['status']; ?>">
                                            <?php echo ucfirst($anuncio['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-anuncio-body">
                                    <div class="card-info-item">
                                        <span class="card-info-label">Estoque</span>
                                        <span class="card-info-value"><?php echo number_format($anuncio['estoque'], 0, ',', '.'); ?> <small>Kg</small></span>
                                    </div>
                                    <div class="card-info-item">
                                        <span class="card-info-label">Preço/Kg</span>
                                        <span class="card-info-value">R$ <?php echo number_format($anuncio['preco'], 2, ',', '.'); ?></span>
                                    </div>
                                    <div class="card-anuncio-data">
                                        <i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($anuncio['data_criacao'])); ?>
                                    </div>
                                </div>
                                
                                <div class="card-anuncio-actions">
                                    <?php if ($bloqueado): ?>
                                        <button class="card-action-btn" disabled style="opacity:0.3;"><i class="fas fa-edit"></i></button>
                                    <?php else: ?>
                                        <a href="anuncio_editar.php?id=<?php echo $anuncio['id']; ?>" class="card-action-btn edit" title="Editar"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="processar_anuncio.php" onsubmit="return confirm('Tem certeza?');">
                                        <input type="hidden" name="anuncio_id" value="<?php echo $anuncio['id']; ?>">
                                        <input type="hidden" name="acao" value="deletar">
                                        <button type="submit" class="card-action-btn delete" title="Excluir"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-container">
                            <div class="empty-state-icon"><i class="fas fa-bullhorn"></i></div>
                            <h3>Você ainda não tem anúncios</h3>
                            <p>Comece a vender seus produtos!</p>
                            <a href="anuncio_novo.php" class="empty-state-button"><i class="fas fa-plus"></i> Criar Primeiro Anúncio</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>
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

        <?php if (!$is_pendente && $exibir_aviso_regioes): ?>
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => { document.getElementById('popupAvisos').classList.add('active'); }, 500);
        });
        
        function fecharPopup() { 
            document.getElementById('popupAvisos').classList.remove('active'); 
        }
        
        document.getElementById('popupAvisos').addEventListener('click', function(e) { 
            if (e.target === this) fecharPopup(); 
        });
        
        document.addEventListener('keydown', function(e) { 
            if (e.key === 'Escape') fecharPopup(); 
        });
        
        function salvarPreferencia() {
            const naoExibir = document.getElementById('naoExibirNovamente').checked;
            
            if (naoExibir) {
                fetch('processar_aviso.php', { 
                    method: 'POST', 
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }, 
                    body: 'tipo_aviso=regioes_entrega' 
                })
                .then(response => response.json())
                .then(data => { 
                    if(data.success) {
                        console.log('Preferência salva com sucesso');
                    } else {
                        console.error('Erro ao salvar preferência:', data.message);
                    }
                    fecharPopup(); 
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    fecharPopup();
                });
            } else { 
                fecharPopup(); 
            }
        }
        <?php endif; ?>
    </script>
</body>
</html>