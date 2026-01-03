<?php
require_once dirname(__DIR__) . '/conexao.php'; 

$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, nome, preco_mensal FROM planos ORDER BY preco_mensal ASC";
$result = $db->query($query); 
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos - Encontre O Campo</title>
    <link rel="stylesheet" href="../css/vendedor/escolher_plano.css">
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
            // Adiciona classe 'destaque' ao plano do meio (se houver 3 planos)
            $classe_destaque = ($contador == 2) ? 'destaque' : '';
        ?>
            <div class="card-plano <?php echo $classe_destaque; ?>">
                <h2><?php echo htmlspecialchars($plano['nome']); ?></h2>
                
                <div class="preco-wrapper">
                    <div class="preco">
                        <span class="cifrao">R$</span>
                        <?php echo number_format($plano['preco_mensal'], 2, ',', '.'); ?>
                    </div>
                    <p class="periodicidade">por mês</p>
                </div>
                
                <ul class="recursos">
                    <li>Acesso completo à plataforma</li>
                    <li>Reservas ilimitadas</li>
                    <li>Suporte prioritário</li>
                    <li>Cancelamento a qualquer momento</li>
                </ul>
                
                <a href="processar_assinatura.php?id=<?php echo $plano['id']; ?>" class="btn-assinar">
                    Assinar Agora
                </a>
            </div>
        <?php endwhile; ?>
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

</body>
</html>