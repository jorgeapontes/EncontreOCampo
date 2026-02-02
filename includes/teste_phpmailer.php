<?php
// Testar se PHPMailer está funcionando

// Incluir o send_notification.php atual
require_once __DIR__ . '/send_notification.php';

// Testar a função atual
echo "Testando função enviarEmailNotificacao...<br>";

// Substitua com um email real para testar
$test_email = "rafaeltonetti.cardoso@gmail.com"; // Use um email real
$test_nome = "Rafael Teste";

$resultado = enviarEmailNotificacao(
    $test_email,
    $test_nome,
    'Teste PHPMailer - Encontre o Campo',
    'Esta é uma mensagem de teste do sistema Encontre o Campo.'
);

if ($resultado) {
    echo "Email enviado com sucesso para $test_email<br>";
    echo "Verifique sua caixa de entrada e spam.<br>";
} else {
    echo "Falha ao enviar email.<br>";
    echo "Verifique os logs do PHP para mais informações.<br>";
}

// Testar também a função mail() do PHP
echo "<br><br>Testando função mail() nativa do PHP...<br>";
if (function_exists('mail')) {
    $test_mail = mail(
        $test_email,
        'Teste mail() nativo',
        'Teste da função mail()',
        'From: teste@encontreocampo.com.br'
    );
    
    if ($test_mail) {
        echo "Função mail() executada sem erros (pode não ter sido entregue).<br>";
    } else {
        echo "Função mail() retornou false.<br>";
    }
} else {
    echo "Função mail() não está disponível.<br>";
}