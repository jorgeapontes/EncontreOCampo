<?php
/**
 * Rodapé compartilhado (site-footer).
 *
 * Uso:   <?php require __DIR__ . '/includes/footer.php'; ?>
 * Requer o CSS:  <link rel="stylesheet" href="css/footer.css">
 *
 * Caminhos relativos a src/. Para páginas em subpastas (ex.: comprador/),
 * defina $footer_prefix = '../' antes do require.
 */
$footer_prefix = $footer_prefix ?? '';
?>
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <h4>Encontre o Campo</h4>
                <ul>
                    <li><a href="<?= $footer_prefix ?>../index">Página Inicial</a></li>
                    <li><a href="<?= $footer_prefix ?>anuncios">Ver Anúncios</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Suporte</h4>
                <ul>
                    <li><a href="<?= $footer_prefix ?>../ajuda">Central de Ajuda</a></li>
                    <li><a href="<?= $footer_prefix ?>../contato">Fale Conosco</a></li>
                    <li><a href="<?= $footer_prefix ?>sobre">Sobre Nós</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Legal</h4>
                <ul>
                    <li><a href="<?= $footer_prefix ?>faq">FAQ</a></li>
                    <li><a href="<?= $footer_prefix ?>termos">Termos de Uso</a></li>
                    <li><a href="<?= $footer_prefix ?>privacidade">Política de Privacidade</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contato</h4>
                <div class="contact-info">
                    <p><i class="fas fa-envelope"></i> contato@encontreocampo.com.br</p>
                    <div class="social-links">
                        <a href="#">Instagram</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; Encontre o Campo. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>
