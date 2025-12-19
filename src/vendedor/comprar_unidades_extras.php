<?php
// src/vendedor/comprar_unidades_extras.php
require_once 'auth.php';

$vendedor_id = $vendedor['id'];

// Verificar se está no Plano 5
if ($vendedor['plano_id'] != 5) {
    header('Location: escolher_plano.php');
    exit;
}

// Buscar assinatura ativa
$query_assinatura = "SELECT * FROM vendedor_assinaturas 
                     WHERE vendedor_id = :vendedor_id AND status = 'active'";
$stmt_assinatura = $db->prepare($query_assinatura);
$stmt_assinatura->bindParam(':vendedor_id', $vendedor_id);
$stmt_assinatura->execute();
$assinatura = $stmt_assinatura->fetch(PDO::FETCH_ASSOC);

if (!$assinatura) {
    header('Location: escolher_plano.php');
    exit;
}

// Preço por unidade extra (definir no .env)
$preco_unidade = 9.90;

// Processar compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantidade'])) {
    $quantidade = (int)$_POST['quantidade'];
    
    if ($quantidade > 0 && $quantidade <= 20) {
        $valor_total = $quantidade * $preco_unidade;
        
        // Criar pagamento para unidades extras
        require_once '../config/MercadoPagoConfig.php';
        
        $external_reference = 'extras_vend_' . $vendedor_id . '_' . time();
        
        $items = [
            [
                "title" => "Unidades Extras de Anúncio ($quantidade un.)",
                "quantity" => 1,
                "currency_id" => "BRL",
                "unit_price" => (float)$valor_total
            ]
        ];
        
        $payer = [
            "name" => $usuario['nome'],
            "email" => $usuario['email']
        ];
        
        $backUrls = [
            "success" => $_ENV['SUCCESS_URL'] . "?tipo=extras&vendedor_id=" . $vendedor_id,
            "failure" => $_ENV['FAILURE_URL'],
            "pending" => $_ENV['PENDING_URL']
        ];
        
        $preference = MercadoPagoAPI::createPreference($items, $payer, $external_reference, $backUrls);
        
        if ($preference && isset($preference->id)) {
            // Salvar transação pendente
            $query_transacao = "INSERT INTO pagamentos 
                               (vendedor_id, valor, status, descricao, id_mercadopago)
                               VALUES (:vendedor_id, :valor, 'pending', :descricao, :id_mp)";
            $stmt_transacao = $db->prepare($query_transacao);
            $stmt_transacao->bindParam(':vendedor_id', $vendedor_id);
            $stmt_transacao->bindParam(':valor', $valor_total);
            
            $descricao = "Compra de $quantidade unidades extras de anúncio";
            $stmt_transacao->bindParam(':descricao', $descricao);
            $stmt_transacao->bindParam(':id_mp', $preference->id);
            $stmt_transacao->execute();
            
            header("Location: " . $preference->init_point);
            exit;
        } else {
            $mensagem_erro = "Erro ao criar pagamento.";
        }
    } else {
        $mensagem_erro = "Quantidade inválida.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unidades Extras - Vendedor</title>
    <style>
        .extras-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .extras-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .preco-unidade {
            font-size: 36px;
            color: #3498db;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .quantidade-selector {
            margin: 30px 0;
        }
        
        .quantidade-input {
            width: 100px;
            padding: 10px;
            font-size: 18px;
            text-align: center;
            border: 2px solid #3498db;
            border-radius: 5px;
        }
        
        .quantidade-buttons {
            margin-top: 10px;
        }
        
        .qty-btn {
            background: #3498db;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            margin: 0 5px;
        }
        
        .qty-btn:hover {
            background: #2980b9;
        }
        
        .valor-total {
            font-size: 24px;
            font-weight: bold;
            color: #2ecc71;
            margin: 20px 0;
        }
        
        .btn-comprar {
            background: #2ecc71;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }
        
        .btn-comprar:hover {
            background: #27ae60;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="extras-container">
        <h1>Comprar Unidades Extras de Anúncio</h1>
        
        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert error-alert"><?php echo $mensagem_erro; ?></div>
        <?php endif; ?>
        
        <div class="extras-card">
            <h2>Unidades Extras</h2>
            <p>Compre unidades extras para publicar mais anúncios além do limite do seu plano.</p>
            
            <div class="preco-unidade">
                R$ <?php echo number_format($preco_unidade, 2, ',', '.'); ?> / unidade
            </div>
            
            <form method="POST" action="comprar_unidades_extras.php">
                <div class="quantidade-selector">
                    <label>Quantidade desejada:</label><br>
                    <input type="number" name="quantidade" id="quantidade" class="quantidade-input" 
                           value="1" min="1" max="20" required>
                    
                    <div class="quantidade-buttons">
                        <button type="button" class="qty-btn" onclick="alterarQuantidade(-1)">-</button>
                        <button type="button" class="qty-btn" onclick="alterarQuantidade(1)">+</button>
                    </div>
                </div>
                
                <div class="valor-total" id="valor-total">
                    Total: R$ <?php echo number_format($preco_unidade, 2, ',', '.'); ?>
                </div>
                
                <button type="submit" class="btn-comprar">
                    <i class="fas fa-shopping-cart"></i> Comprar Agora
                </button>
            </form>
            
            <p style="margin-top: 20px; font-size: 0.9rem; color: #666;">
                <i class="fas fa-info-circle"></i> As unidades extras são válidas enquanto sua assinatura estiver ativa.
            </p>
        </div>
    </div>
    
    <script>
        const precoUnidade = <?php echo $preco_unidade; ?>;
        
        function alterarQuantidade(change) {
            const input = document.getElementById('quantidade');
            let qty = parseInt(input.value) + change;
            
            if (qty < 1) qty = 1;
            if (qty > 20) qty = 20;
            
            input.value = qty;
            calcularTotal();
        }
        
        function calcularTotal() {
            const qty = document.getElementById('quantidade').value;
            const total = qty * precoUnidade;
            
            document.getElementById('valor-total').textContent = 
                'Total: R$ ' + total.toFixed(2).replace('.', ',');
        }
        
        // Calcular total inicial
        calcularTotal();
        
        // Atualizar quando o input mudar
        document.getElementById('quantidade').addEventListener('input', calcularTotal);
    </script>
</body>
</html>