<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'send_notification.php';
    
    $destinatario = 'rafaeltonetti.cardoso@gmail.com';  // ALTERE PARA SEU EMAIL
    $nome = 'Usuário Teste';
    $assunto = 'Teste de Email - Encontre o Campo';
    $conteudo = 'Este é um email de teste enviado através do botão de teste.';
    
    // Tentar enviar o email
    $resultado = enviarEmailNotificacao($destinatario, $nome, $assunto, $conteudo);
    
    // Mensagem de resultado
    if ($resultado) {
        $mensagem = '✅ Email enviado com sucesso!';
        $classe = 'sucesso';
    } else {
        $mensagem = '❌ Falha ao enviar email. Verifique os logs.';
        $classe = 'erro';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Email</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        h1 {
            color: #2E7D32;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #2E7D32;
            padding: 15px;
            margin-bottom: 25px;
            text-align: left;
            border-radius: 0 8px 8px 0;
        }
        
        .info-box p {
            margin: 5px 0;
            color: #555;
            font-size: 14px;
        }
        
        .info-box strong {
            color: #2E7D32;
        }
        
        .test-button {
            background: #2E7D32;
            color: white;
            border: none;
            padding: 16px 40px;
            font-size: 18px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .test-button:hover {
            background: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        }
        
        .test-button:active {
            transform: translateY(0);
        }
        
        .resultado {
            margin-top: 25px;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
        }
        
        .sucesso {
            background: #e8f5e9;
            color: #2E7D32;
            border: 2px solid #a5d6a7;
        }
        
        .erro {
            background: #ffebee;
            color: #c62828;
            border: 2px solid #ef9a9a;
        }
        
        .instructions {
            margin-top: 30px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 8px;
            font-size: 14px;
            color: #1565c0;
            text-align: left;
        }
        
        .instructions h3 {
            margin-bottom: 10px;
            color: #0d47a1;
        }
        
        .instructions ul {
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 8px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link:hover {
            color: #2E7D32;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Teste de Envio de Email</h1>
        
        <div class="info-box">
            <p><strong>Sistema:</strong> Encontre o Campo</p>
            <p><strong>Função testada:</strong> enviarEmailNotificacao()</p>
            <p><strong>Arquivo:</strong> send_notification.php</p>
            <p><strong>Destinatário:</strong> email_teste@exemplo.com</p>
            <p><em>⚠️ Altere no código para seu email real</em></p>
        </div>
        
        <form method="POST" action="">
            <button type="submit" class="test-button">
                <i class="fas fa-paper-plane"></i> ENVIAR EMAIL DE TESTE
            </button>
        </form>
        
        <?php if (isset($mensagem)): ?>
            <div class="resultado <?php echo $classe; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
        
        <div class="instructions">
            <h3>📋 Instruções para teste:</h3>
            <ul>
                <li>1. Altere a variável $destinatario no código PHP para seu email real</li>
                <li>2. Clique no botão "ENVIAR EMAIL DE TESTE"</li>
                <li>3. Verifique sua caixa de entrada (e spam)</li>
                <li>4. Consulte os logs do PHP para erros detalhados</li>
            </ul>
        </div>
        
        <a href="javascript:history.back()" class="back-link">← Voltar</a>
    </div>
    
    <!-- Font Awesome para o ícone -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
        // Adicionar ícone via JavaScript se necessário
        document.querySelector('.test-button').innerHTML = `
            <svg style="width:20px;height:20px" viewBox="0 0 24 24">
                <path fill="currentColor" d="M2,5.5V18.5L8.13,12.36L11.16,15.39C11.63,15.86 12.36,15.85 12.83,15.37L18.77,9.17L22,5.5H2M4,7.5H18.16L13.3,12.36L11.69,10.75C11.3,10.36 10.69,10.36 10.3,10.75L4,17.17V7.5Z" />
            </svg>
            ENVIAR EMAIL DE TESTE
        `;
    </script>
</body>
</html>