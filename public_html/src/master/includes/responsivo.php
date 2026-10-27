<?php
/**
 * Responsividade da área do master (incluído pela sidebar), igual à do Risenglish:
 * nas tabelas .tabela-cartoes, preenche o rótulo de cada célula a partir do
 * cabeçalho e liga o modo cartão (.em-cartoes) quando a tabela não cabe no espaço
 * disponível ou a tela é bem estreita. Recalcula ao girar o tablet/redimensionar.
 */
?>
<link rel="stylesheet" href="../css/master/responsivo.css?v=<?= @filemtime(__DIR__ . '/../../css/master/responsivo.css') ?>">
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var LARGURA_MINIMA_TABELA = 576; // abaixo disso sempre vira cartão
        var tabelas = [].slice.call(document.querySelectorAll('table.tabela-cartoes'));
        if (!tabelas.length) return;

        tabelas.forEach(function (tabela) {
            var rotulos = [].map.call(tabela.querySelectorAll('thead th'), function (th) {
                return th.textContent.trim();
            });
            tabela.querySelectorAll('tbody tr').forEach(function (linha) {
                [].forEach.call(linha.children, function (celula, i) {
                    if (!celula.hasAttribute('data-label')) {
                        celula.setAttribute('data-label', rotulos[i] || '');
                    }
                    if (i === 0) {
                        celula.classList.add('td-titulo');
                    }
                    if (/^a[çc][õo]es$/i.test(rotulos[i] || '')) {
                        celula.classList.add('td-acoes');
                    }
                });
            });
        });

        function ajustar() {
            tabelas.forEach(function (tabela) {
                // mede a tabela no formato normal para saber se ela cabe
                tabela.classList.remove('em-cartoes');
                var espaco = tabela.parentElement.clientWidth;
                var naoCabe = tabela.scrollWidth > espaco + 1;
                tabela.classList.toggle('em-cartoes', naoCabe || espaco < LARGURA_MINIMA_TABELA);
            });
        }

        var espera;
        window.addEventListener('resize', function () {
            clearTimeout(espera);
            espera = setTimeout(ajustar, 150);
        });
        ajustar();
    });
</script>
