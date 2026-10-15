<?php
/**
 * Notificação (toast) do painel administrativo.
 *
 * Antes do redirecionamento, registre a mensagem na sessão:
 *   require_once __DIR__ . '/includes/toast.php';
 *   adminToast('aprovado', 'Cadastro aprovado', 'Fulano já pode acessar a plataforma.');
 *   header('Location: dashboard'); exit;
 *
 * Na página de destino, logo depois do <body>:
 *   <?php require_once __DIR__ . '/includes/toast.php'; adminToastRender(); ?>
 *
 * Tipos: aprovado | rejeitado | sucesso | erro
 * A mensagem fica na sessão (e não na URL) para não reaparecer ao recarregar.
 * Também exibe o antigo ?msg= das páginas que ainda redirecionam assim.
 */

if (!function_exists('adminToast')) {

    function adminToast(string $tipo, string $titulo, string $mensagem = ''): void
    {
        $_SESSION['admin_toast'] = ['tipo' => $tipo, 'titulo' => $titulo, 'mensagem' => $mensagem];
    }

    function adminToastRender(): void
    {
        $toast = $_SESSION['admin_toast'] ?? null;
        unset($_SESSION['admin_toast']);

        // Compatibilidade com redirecionamentos que ainda usam ?msg=
        if (!$toast && !empty($_GET['msg'])) {
            $msg = (string)$_GET['msg'];
            $ehErro = stripos($msg, 'erro') !== false;
            $toast = [
                'tipo'     => $ehErro ? 'erro' : 'sucesso',
                'titulo'   => $ehErro ? 'Algo deu errado' : 'Tudo certo',
                'mensagem' => $msg,
            ];
        }
        if (!$toast) {
            return;
        }

        $icones = [
            'aprovado'  => 'fa-user-check',
            'rejeitado' => 'fa-user-xmark',
            'sucesso'   => 'fa-circle-check',
            'erro'      => 'fa-triangle-exclamation',
        ];
        $tipo = isset($icones[$toast['tipo']]) ? $toast['tipo'] : 'sucesso';
        ?>
        <style>
            .admin-toast {
                position: fixed;
                top: 90px;
                right: 20px;
                z-index: 2000;
                width: 360px;
                max-width: calc(100vw - 32px);
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 16px 40px 20px 16px;
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
                border-left: 5px solid var(--toast-cor);
                overflow: hidden;
                font-family: inherit;
                animation: admin-toast-entrar 0.3s ease-out;
                transition: opacity 0.3s ease, transform 0.3s ease;
            }
            .admin-toast--aprovado  { --toast-cor: #2e7d32; --toast-fundo: #e8f5e9; }
            .admin-toast--sucesso   { --toast-cor: #2e7d32; --toast-fundo: #e8f5e9; }
            .admin-toast--rejeitado { --toast-cor: #c62828; --toast-fundo: #ffebee; }
            .admin-toast--erro      { --toast-cor: #b45309; --toast-fundo: #fff4e5; }

            .admin-toast__icone {
                flex-shrink: 0;
                width: 38px;
                height: 38px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--toast-fundo);
                color: var(--toast-cor);
                font-size: 1.1rem;
            }
            .admin-toast__texto { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
            .admin-toast__texto strong { color: var(--toast-cor); font-size: 1rem; }
            .admin-toast__texto span { color: #444; font-size: 0.9rem; line-height: 1.4; overflow-wrap: anywhere; }

            .admin-toast__fechar {
                position: absolute;
                top: 8px;
                right: 10px;
                border: none;
                background: none;
                font-size: 1.3rem;
                line-height: 1;
                color: #999;
                cursor: pointer;
            }
            .admin-toast__fechar:hover { color: #333; }

            /* Barra da contagem regressiva: 5s, pausa com o mouse em cima */
            .admin-toast__barra {
                position: absolute;
                left: 0;
                bottom: 0;
                height: 4px;
                width: 100%;
                background: var(--toast-cor);
                transform-origin: left;
                animation: admin-toast-tempo 5s linear forwards;
            }
            .admin-toast:hover .admin-toast__barra { animation-play-state: paused; }

            .admin-toast--saindo { opacity: 0; transform: translateX(20px); }

            @keyframes admin-toast-entrar {
                from { opacity: 0; transform: translateX(30px); }
                to   { opacity: 1; transform: translateX(0); }
            }
            @keyframes admin-toast-tempo {
                from { transform: scaleX(1); }
                to   { transform: scaleX(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .admin-toast { animation: none; }
            }
        </style>

        <div class="admin-toast admin-toast--<?= $tipo ?>" id="admin-toast"
             role="<?= $tipo === 'erro' ? 'alert' : 'status' ?>" aria-live="<?= $tipo === 'erro' ? 'assertive' : 'polite' ?>">
            <div class="admin-toast__icone"><i class="fas <?= $icones[$tipo] ?>" aria-hidden="true"></i></div>
            <div class="admin-toast__texto">
                <strong><?= htmlspecialchars($toast['titulo']) ?></strong>
                <?php if ($toast['mensagem'] !== ''): ?>
                <span><?= htmlspecialchars($toast['mensagem']) ?></span>
                <?php endif; ?>
            </div>
            <button type="button" class="admin-toast__fechar" aria-label="Fechar notificação">&times;</button>
            <div class="admin-toast__barra"></div>
        </div>

        <script>
            (function () {
                var toast = document.getElementById('admin-toast');
                if (!toast) return;

                function fechar() {
                    toast.classList.add('admin-toast--saindo');
                    setTimeout(function () { toast.remove(); }, 300);
                }

                toast.querySelector('.admin-toast__barra').addEventListener('animationend', fechar);
                toast.querySelector('.admin-toast__fechar').addEventListener('click', fechar);

                // Tira o ?msg= da URL para a mensagem não voltar ao recarregar
                if (window.history.replaceState && /[?&]msg=/.test(window.location.search)) {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('msg');
                    url.searchParams.delete('success');
                    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
                }
            })();
        </script>
        <?php
    }
}
