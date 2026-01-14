<?php
// O auth.php já inicia a sessão e carrega a variável $vendedor
require_once 'auth.php'; 
require_once dirname(__DIR__) . '/conexao.php'; 

$database = new Database();
$db = $database->getConnection();

// Agora usamos a variável $vendedor que o seu auth.php já criou!
$plano_atual_id = (int)($vendedor['plano_id'] ?? 1);

// Adicionamos 'descricao_recursos' na busca
$query = "SELECT id, nome, preco_mensal, descricao_recursos FROM planos ORDER BY preco_mensal ASC";
$result = $db->query($query); 
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos - Encontre O Campo</title>
    <link rel="stylesheet" href="../css/vendedor/escolher_plano.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos para o botão bloqueado */
        .btn-assinar.plano-atual {
            background-color: #cbd5e0 !important;
            color: #718096 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            border: 1px solid #a0aec0 !important;
            box-shadow: none !important;
            transform: none !important;
        }
        .selo-atual {
            background: #28a745;
            color: white;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        /* Garantir que o card destaque não sobrescreva o cursor do botão bloqueado */
        .card-plano.destaque .btn-assinar.plano-atual {
            background-color: #cbd5e0 !important;
        }

        /* --- ESTILOS DO MODAL --- */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; top: 0; width: 100%; height: 100%; 
            background-color: rgba(0,0,0,0.5);
            align-items: center; justify-content: center;
        }
        .modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 15px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-content h3 { margin-bottom: 15px; color: #333; }
        .modal-content p { color: #666; margin-bottom: 25px; line-height: 1.5; }
        .btn-gerenciar {
            background-color: #28a745;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            display: inline-block;
        }
        .btn-fechar {
            display: block;
            margin-top: 15px;
            color: #999;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1>Escolha seu Plano</h1>
        <p>Encontre o plano perfeito para suas necessidades e comece a aproveitar!</p>
    </div>
    
    <div class="planos-container">
        <?php 
        $contador = 0;
        while($plano = $result->fetch(PDO::FETCH_ASSOC)): 
            $contador++;
            $classe_destaque = ($contador == 2) ? 'destaque' : '';
            $eh_plano_atual = ((int)$plano['id'] === $plano_atual_id);
        ?>
            <div class="card-plano <?php echo $classe_destaque; ?>">
                <?php if ($eh_plano_atual): ?>
                    <span class="selo-atual"><i class="fas fa-check"></i> SEU PLANO ATUAL</span>
                <?php endif; ?>

                <h2><?php echo htmlspecialchars($plano['nome']); ?></h2>
                
                <div class="preco-wrapper">
                    <div class="preco">
                        <span class="cifrao">R$</span>
                        <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?>
                    </div>
                    <p class="periodicidade">por mês</p>
                </div>
                
                <ul class="recursos">
    <?php 
    // Se a coluna estiver vazia, mostramos um padrão, caso contrário, explodimos a string por vírgula
    $recursos_texto = $plano['descricao_recursos'] ?? 'Acesso completo, Suporte padrão';
    $lista_recursos = explode(',', $recursos_texto);
    
    foreach ($lista_recursos as $item): 
    ?>
        <li> <?php echo htmlspecialchars(trim($item)); ?></li>
    <?php endforeach; ?>
</ul>
                
                <?php if ($eh_plano_atual): ?>
                    <a href="javascript:void(0);" class="btn-assinar plano-atual">
                        Plano Ativado
                    </a>
                <?php else: ?>
                    <?php if ($plano_atual_id > 1): ?>
                        <a href="javascript:void(0);" onclick="abrirModal()" class="btn-assinar">
                            Mudar de Plano
                        </a>
                    <?php else: ?>
                        <a href="processar_assinatura.php?id=<?php echo $plano['id']; ?>" class="btn-assinar">
                            Assinar Agora
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>

    <div id="modalMudarPlano" class="modal">
        <div class="modal-content">
            <h3>Mudar de Plano?</h3>
            <p>Você já possui uma assinatura ativa. Para fazer um upgrade, downgrade ou cancelar, utilize nossa central de gestão.</p>
            <a href="gerenciar_assinatura.php" class="btn-gerenciar">Ir para Gerenciar Assinatura</a>
            <a href="javascript:void(0);" onclick="fecharModal()" class="btn-fechar">Voltar</a>
        </div>
    </div>

    <div class="voltar">
        <a href="perfil.php" class="btn-voltar">Voltar</a>
    </div>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Encontre o Campo</h4>
                    <ul>
                        <li><a href="../../index.php">Página Inicial</a></li>
                        <li><a href="../anuncios.php">Ver Anúncios</a></li>
                        <li><a href="../comprador/favoritos.php">Meus Favoritos</a></li>
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
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; Encontre o Campo. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        function abrirModal() {
            document.getElementById('modalMudarPlano').style.display = 'flex';
        }

        function fecharModal() {
            document.getElementById('modalMudarPlano').style.display = 'none';
        }

        // Fecha o modal se o usuário clicar fora da caixa branca
        window.onclick = function(event) {
            var modal = document.getElementById('modalMudarPlano');
            if (event.target == modal) {
                fecharModal();
            }
        }
    </script>

</body>
</html>