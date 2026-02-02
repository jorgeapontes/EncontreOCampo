<?php
session_start();
require_once __DIR__ . '/conexao.php';

$database = new Database();
$conn = $database->getConnection();

// Testar consulta de vendedor
$vendedor_id = 11; // ID do vendedor V1
$sqlVendedorInfo = "SELECT u.nome, u.email FROM usuarios u 
                   JOIN vendedores v ON u.id = v.usuario_id 
                   WHERE v.id = :vendedor_id";

$stmtVendedorInfo = $conn->prepare($sqlVendedorInfo);
$stmtVendedorInfo->bindParam(':vendedor_id', $vendedor_id);
$stmtVendedorInfo->execute();
$vendedorInfo = $stmtVendedorInfo->fetch(PDO::FETCH_ASSOC);

echo "Informações do Vendedor (ID: $vendedor_id):<br>";
var_dump($vendedorInfo);

// Testar consulta de comprador
$comprador_id = 13; // ID do comprador Jorge
$sqlCompradorInfo = "SELECT u.nome, u.email FROM usuarios u 
                    JOIN compradores c ON u.id = c.usuario_id 
                    WHERE c.id = :comprador_id";

$stmtCompradorInfo = $conn->prepare($sqlCompradorInfo);
$stmtCompradorInfo->bindParam(':comprador_id', $comprador_id);
$stmtCompradorInfo->execute();
$compradorInfo = $stmtCompradorInfo->fetch(PDO::FETCH_ASSOC);

echo "<br><br>Informações do Comprador (ID: $comprador_id):<br>";
var_dump($compradorInfo);