// src/cron/verificar_vencimentos.php
<?php
require_once __DIR__ . '/../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Buscar assinaturas que vencem em 3 dias
$data_aviso = date('Y-m-d', strtotime('+3 days'));
$query = "SELECT a.*, v.email, u.nome 
          FROM vendedor_assinaturas a
          JOIN vendedores v ON a.vendedor_id = v.id
          JOIN usuarios u ON v.usuario_id = u.id
          WHERE a.status = 'active' 
          AND a.data_vencimento = :data_aviso";

$stmt = $db->prepare($query);
$stmt->bindParam(':data_aviso', $data_aviso);
$stmt->execute();
$assinaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($assinaturas as $assinatura) {
    // Enviar email de aviso
    $assunto = "Sua assinatura vence em 3 dias";
    $mensagem = "Olá " . $assinatura['nome'] . ",\n\n";
    $mensagem .= "Sua assinatura do Encontre o Campo vence em 3 dias.\n";
    $mensagem .= "Para continuar usando todos os benefícios, renove sua assinatura.\n\n";
    $mensagem .= "Acesse: " . $_ENV['SITE_URL'] . "/src/vendedor/gerenciar_assinatura.php";
    
    // sendEmail($assinatura['email'], $assunto, $mensagem);
    
    // Registrar notificação no sistema
    $query_notif = "INSERT INTO notificacoes 
                   (usuario_id, titulo, mensagem, tipo, link) 
                   VALUES (:usuario_id, :titulo, :mensagem, 'assinatura', :link)";
    $stmt_notif = $db->prepare($query_notif);
    $stmt_notif->bindParam(':usuario_id', $assinatura['vendedor_id']);
    $titulo = "Assinatura próxima do vencimento";
    $stmt_notif->bindParam(':titulo', $titulo);
    $stmt_notif->bindParam(':mensagem', $mensagem);
    $link = 'gerenciar_assinatura.php';
    $stmt_notif->bindParam(':link', $link);
    $stmt_notif->execute();
}

echo "Vencimentos verificados: " . count($assinaturas) . " assinaturas\n";