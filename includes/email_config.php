<?php
// Carregar variáveis de ambiente
require_once '../vendor/autoload.php'; // Carregar autoload do Composer
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Carregar PHPMailer manualmente
require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function enviarEmailRecuperacao($destinatario, $nome, $reset_link) {
    $mail = new PHPMailer(true);
    
    try {
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $_ENV['SMTP_PORT'];
        
        // Remetente e destinatário
        $mail->setFrom($_ENV['SMTP_USERNAME'], 'Encontre o Campo');
        $mail->addAddress($destinatario, $nome);
        
        // Conteúdo do email
        $mail->isHTML(true);
        $mail->Subject = 'Redefinição de Senha - Encontre o Campo';
        
        // Template do email
        $mail->Body = gerarTemplateEmail($nome, $reset_link);
        $mail->AltBody = "Olá $nome,\n\nPara redefinir sua senha, clique no link: $reset_link\n\nEste link expira em 1 hora.\n\nSe você não solicitou isso, ignore este email.";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Erro ao enviar email: " . $mail->ErrorInfo);
        return false;
    }
}

function gerarTemplateEmail($nome, $reset_link) {
    // ... (mantenha o mesmo template HTML da versão anterior)
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
                <p>Você solicitou a redefinição da sua senha.</p>
                <p>Clique no botão abaixo para criar uma nova senha:</p>
                
                <p style="text-align: center;">
                    <a href="<?php echo $reset_link; ?>" class="button">Redefinir Senha</a>
                </p>
                
                <p>Ou copie e cole este link no seu navegador:<br>
                <small><?php echo $reset_link; ?></small></p>
                
                <p><strong>Este link expira em 1 hora.</strong></p>
                
                <p>Se você não solicitou a redefinição de senha, ignore este email.</p>
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