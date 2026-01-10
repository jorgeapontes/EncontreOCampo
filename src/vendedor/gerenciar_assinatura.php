<?php
// src/vendedor/gerenciar_assinatura.php
require_once 'auth.php'; // Garante que $vendedor e $db estão carregados

// 1. Buscamos os dados atualizados do banco (incluindo o stripe_customer_id que é essencial)
$stmt = $db->prepare("
    SELECT 
        v.id,
        v.plano_id, 
        v.status_assinatura, 
        v.data_vencimento_assinatura, 
        v.Data_assinatura, 
        v.Data_inicio_assinatura,
        v.stripe_customer_id,
        p.nome as nome_plano, 
        p.preco_mensal 
    FROM vendedores v 
    LEFT JOIN planos p ON v.plano_id = p.id 
    WHERE v.id = ?
");
$stmt->execute([$vendedor['id']]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Contagem de produtos para exibir no resumo
$stmt_prod = $db->prepare("SELECT COUNT(*) FROM produtos WHERE vendedor_id = ?");
$stmt_prod->execute([$vendedor['id']]);
$total_produtos = $stmt_prod->fetchColumn();

// 3. Lógica: Definimos se é um plano pago (ID > 1) e se está ativo
$eh_plano_pago = ($dados['plano_id'] > 1 && $dados['status_assinatura'] === 'ativo');

$data_exibicao_inicio = $dados['Data_inicio_assinatura'] ?? $dados['Data_assinatura'] ?? null;

// --- BUSCAR RECIBOS DA STRIPE ---
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/StripeConfig.php';
\Config\StripeConfig::init();

$historico_pagamentos = [];

try {
    // Usamos o customer_id direto do banco se ele existir, senão buscamos pelo email
    $customer_id = $dados['stripe_customer_id'] ?? null;

    if (!$customer_id) {
        $email_vendedor = $_SESSION['usuario_email'];
        $customers = \Stripe\Customer::all(['email' => $email_vendedor, 'limit' => 1]);
        if (!empty($customers->data)) {
            $customer_id = $customers->data[0]->id;
        }
    }
    
    if ($customer_id) {
        $invoices = \Stripe\Invoice::all([
            'customer' => $customer_id,
            'limit' => 5,
            'status' => 'paid'
        ]);
        $historico_pagamentos = $invoices->data;
    }
} catch (Exception $e) {
    // Silencioso
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Assinatura | Encontre o Campo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Mantendo seus estilos originais */
        :root { --primary-green: #28a745; --dark-green: #1e7e34; --white: #ffffff; --border: #e2e8f0; --text-light: #636e72; --text-main: #2d3436; }
        body { background-color: #f8faf9; font-family: 'Inter', sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 40px auto; }
        .card-assinatura { background: var(--white); border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 700; text-transform: uppercase; margin-bottom: 20px; }
        .badge-pro { background: #eafaf1; color: var(--primary-green); }
        .badge-free { background: #f1f3f5; color: #6c757d; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; border-top: 1px solid var(--border); padding-top: 20px;}
        .actions { margin-top: 40px; display: flex; gap: 15px; }
        .btn { flex: 1; padding: 15px; border-radius: 12px; font-weight: 600; text-decoration: none; text-align: center; transition: all 0.2s; cursor: pointer; border: none;}
        .btn-green { background-color: var(--primary-green); color: white; }
        .btn-outline { border: 1px solid var(--border); color: var(--text-light); background: white; }
        .btn-danger-outline { border: 1px solid #ffcdd2; color: #d63031; background: white; }
        .historico-section { margin-top: 40px; background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--border); }
        .tabela-recibos { width: 100%; border-collapse: collapse; }
        .tabela-recibos td, th { padding: 15px 10px; border-bottom: 1px solid #f1f3f5; text-align: left; }
    </style>
</head>
<body>

<div class="container">
    <div class="card-assinatura">
        <?php if ($eh_plano_pago): ?>
            <div class="status-badge badge-pro">Plano Profissional Ativo</div>
            <div style="font-size: 32px; font-weight: 800;"><?php echo htmlspecialchars($dados['nome_plano']); ?></div>
            
            <div class="info-grid">
                <div><small>Próxima Cobrança</small><br><strong><?php echo date('d/m/Y', strtotime($dados['data_vencimento_assinatura'])); ?></strong></div>
                <div><small>Valor Mensal</small><br><strong>R$ <?php echo number_format($dados['preco_mensal'], 2, ',', '.'); ?></strong></div>
            </div>

            <div class="actions">
                <a href="gerar_portal_stripe.php" class="btn btn-green">
                    <i class="fa-solid fa-arrows-rotate"></i> Mudar de Plano (Upgrade/Downgrade)
                </a>
                <a href="gerar_portal_stripe.php" class="btn btn-danger-outline">
                    <i class="fa-solid fa-xmark"></i> Cancelar Assinatura
                </a>
            </div>

        <?php else: ?>
            <div class="status-badge badge-free">Plano Gratuito</div>
            <div style="font-size: 32px; font-weight: 800;">Versão Limitada</div>
            <p>Faça um upgrade para anunciar mais produtos e ter maior visibilidade.</p>
            <div class="actions">
                <a href="escolher_plano.php" class="btn btn-green">Escolher um Plano Profissional</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="historico-section">
        <h3><i class="fa-solid fa-file-invoice-dollar"></i> Recibos Anteriores</h3>
        <?php if (empty($historico_pagamentos)): ?>
            <p>Nenhum recibo disponível.</p>
        <?php else: ?>
            <table class="tabela-recibos">
                <?php foreach ($historico_pagamentos as $invoice): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', $invoice->created); ?></td>
                        <td>R$ <?php echo number_format($invoice->amount_paid / 100, 2, ',', '.'); ?></td>
                        <td><a href="<?php echo $invoice->hosted_invoice_url; ?>" target="_blank">Ver Recibo</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>