<?php
// 404.php - Página de "não encontrado" (ErrorDocument 404 no .htaccess).
// Pode ser exibida em qualquer profundidade de URL, por isso todos os
// caminhos são absolutos a partir da raiz do site.
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Página não encontrada - Encontre o Campo</title>
    <link rel="shortcut icon" href="/img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --dark-color: #2E7D32;
            --light-color: #F1F8E9;
            --text-color: #212121;
            --text-light: #757575;
            --white: #FFFFFF;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: "Montserrat", sans-serif;
            color: var(--text-color);
            background: var(--light-color);
            line-height: 1.6;
        }

        header {
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .marca {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px 20px;
            text-decoration: none;
        }

        .marca img { height: 50px; }
        .marca span { display: block; line-height: 1.1; }
        .marca .linha1 { color: var(--primary-color); font-weight: 800; font-size: 1.3rem; }
        .marca .linha2 { color: var(--dark-color); font-weight: 700; font-size: 0.95rem; letter-spacing: 1px; }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        .cartao {
            max-width: 560px;
            width: 100%;
            text-align: center;
            background: var(--white);
            border-radius: 16px;
            padding: 48px 32px;
            box-shadow: 0 10px 30px rgba(46, 125, 50, 0.12);
            border-top: 6px solid var(--primary-color);
        }

        .icone {
            width: 88px;
            height: 88px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--dark-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
        }

        .codigo {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1;
            color: var(--primary-color);
            letter-spacing: 4px;
        }

        h1 {
            margin: 12px 0 8px;
            font-size: 1.5rem;
            color: var(--dark-color);
        }

        p { color: var(--text-light); }

        .acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-top: 28px;
        }

        .botao {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid var(--primary-color);
        }

        .botao-primario { background: var(--primary-color); color: var(--white); }
        .botao-primario:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .botao-secundario { background: transparent; color: var(--dark-color); }
        .botao-secundario:hover { background: var(--light-color); }

        .ajuda {
            margin-top: 24px;
            font-size: 0.9rem;
        }

        .ajuda a { color: var(--dark-color); font-weight: 600; }

        footer {
            text-align: center;
            padding: 20px 16px;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        @media (max-width: 480px) {
            .cartao { padding: 36px 20px; }
            .codigo { font-size: 3.5rem; }
            h1 { font-size: 1.25rem; }
            .botao { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <header>
        <a href="/" class="marca" aria-label="Encontre o Campo - página inicial">
            <img src="/img/logo-nova.png" alt="">
            <div>
                <span class="linha1">ENCONTRE</span>
                <span class="linha2">O CAMPO</span>
            </div>
        </a>
    </header>

    <main>
        <div class="cartao">
            <div class="icone"><i class="fas fa-seedling" aria-hidden="true"></i></div>
            <div class="codigo">404</div>
            <h1>Essa página não brotou por aqui</h1>
            <p>O endereço que você acessou não existe ou foi removido. Que tal voltar para o início ou dar uma olhada nos anúncios?</p>

            <div class="acoes">
                <a href="/" class="botao botao-primario"><i class="fas fa-home" aria-hidden="true"></i> Voltar ao início</a>
                <a href="/anuncios" class="botao botao-secundario"><i class="fas fa-store" aria-hidden="true"></i> Ver anúncios</a>
            </div>

            <p class="ajuda">
                Precisa de ajuda? Veja o <a href="/faq">FAQ</a> ou escreva para
                <a href="mailto:contato@encontreocampo.com.br">contato@encontreocampo.com.br</a>.
            </p>
        </div>
    </main>

    <footer>&copy; <?php echo date('Y'); ?> Encontre o Campo. Todos os direitos reservados.</footer>
</body>
</html>
