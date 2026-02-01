<?php
require_once '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function enviarEmailNotificacao($destinatario, $nome, $assunto, $conteudo) {
    $mail = new PHPMailer(true);

    try {
        $mail -> isSMTP();
        $mail -> Host = $_ENV['SMTP_HOST'];
        $mail -> SMTPAuth = true;
        $mail -> Username = $_ENV['SMTP_USERNAME'];
        $mail -> Password = $_ENV['SMTP_PASSWORD'];
        $mail -> SMTPSecure = $_ENV['SMTP_ENCRYPTION'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail -> Port = $_ENV['SMTP_PORT'];

        $mail -> setFrom($_ENV['SMTP_USERNAME'], 'Encontre o Campo');
        $mail -> addAddress($destinatario, $nome);

        $mail -> isHTML(true);
        $mail -> Subject = $assunto;

        $mail -> Body = gerarTemplateNotificacao($nome, $assunto, $conteudo);
        $mail -> AltBody = "Notificação para $nome: $assunto. $conteudo";

        return $mail -> send();
    } catch (Exception $e) {
        error_log('Ocorreu um erro ao enviar a notificação.\n'. $mail -> ErrorInfo);
        return false;
    }
}

function gerarTemplateNotificacao($nome, $assunto, $conteudo) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
        <head>
            <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background-color: #f9f9f9; }
            .button { 
                display: inline-block; 
                padding: 12px 30px; 
                background-color: #4CAF50; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 20px 0; 
            }
            .footer { 
                text-align: center; 
                padding: 20px; 
                color: #666; 
                font-size: 12px; 
                border-top: 1px solid #ddd; 
            }
        </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Encontre o Campo</h1>
                </div>
                <div class="content">
                    <h2>Olá, <?php echo htmlspecialchars($nome); ?>!</h2>
                    <p><?= $assunto ?></p>
                    <p><?= $conteudo ?></p>
                </div>
                <div class="footer">
                    <p>© <?php echo date('Y'); ?> Encontre o Campo. Todos os direitos reservados.</p>
                </div>
            </div>
        </body>
    </html>
    <?php
    return ob_get_clean();
}
?>