<?php
/**
 * Evita o aviso "Confirmar reenvio do formulário" ao recarregar uma página que
 * acabou de processar um POST: troca a entrada atual do histórico por um GET da
 * mesma URL, então o F5 apenas recarrega a página sem reenviar o formulário.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'): ?>
<script>
    if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', window.location.href);
    }
</script>
<?php endif; ?>
