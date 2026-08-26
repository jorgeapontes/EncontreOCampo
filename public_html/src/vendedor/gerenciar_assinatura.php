<?php
// src/vendedor/gerenciar_assinatura.php
require_once 'auth.php'; // Garante que $vendedor e $db estão carregados

// VERIFICA SE VEIO DO STRIPE E REDIRECIONA PARA O PERFIL
if (isset($_GET['return_from_stripe']) && $_GET['return_from_stripe'] == '1') {
    // Verifica se a sessão ainda está ativa
    if (isset($_SESSION['usuario_id'])) {
        // Redireciona para o perfil com mensagem de sucesso
        header("Location: perfil.php?stripe_return=success");
        exit();
    } else {
        // Se perdeu a sessão, redireciona para login
        header("Location: ../../login.php?msg=sessao_expirada");
        exit();
    }
}

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
        .actions { margin-top: 40px; display: flex; gap: 15px; flex-wrap: wrap; }
        .btn { flex: 1; padding: 15px; border-radius: 12px; font-weight: 600; text-decoration: none; text-align: center; transition: all 0.2s; cursor: pointer; border: none; min-width: 150px; }
        .btn-green { background-color: var(--primary-green); color: white; }
        .btn-green:hover { background-color: var(--dark-green); }
        .btn-outline { border: 1px solid var(--border); color: var(--text-light); background: white; }
        .btn-outline:hover { background: #f8f9fa; }
        .btn-danger-outline { border: 1px solid #ffcdd2; color: #d63031; background: white; }
        .btn-danger-outline:hover { background: #fff5f5; }
        .historico-section { margin-top: 40px; background: white; padding: 30px; border-radius: 20px; border: 1px solid var(--border); }
        .tabela-recibos { width: 100%; border-collapse: collapse; }
        .tabela-recibos td, th { padding: 15px 10px; border-bottom: 1px solid #f1f3f5; text-align: left; }
        .tabela-recibos tr:last-child td { border-bottom: none; }
        .btn-voltar { display: inline-block; margin-top: 20px; color: var(--text-light); text-decoration: none; padding: 10px 20px; border: 1px solid var(--border); border-radius: 8px; transition: all 0.2s; }
        .btn-voltar:hover { background: #f8f9fa; }
        .link-recibo { color: var(--primary-green); text-decoration: none; font-weight: 600; }
        .link-recibo:hover { text-decoration: underline; }
        .mensagem-sucesso {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mensagem-sucesso i { font-size: 20px; }
    </style>
</head>
<body>

<div class="container">
    <!-- Exibe mensagem de sucesso se veio do Stripe -->
    <?php if (isset($_GET['stripe_return']) && $_GET['stripe_return'] == 'success'): ?>
    <div class="mensagem-sucesso">
        <i class="fa-solid fa-check-circle"></i>
        <span>✅ Operação realizada com sucesso no Stripe! Sua assinatura foi atualizada.</span>
    </div>
    <?php endif; ?>

    <div class="card-assinatura">
        <?php if ($eh_plano_pago): ?>
            <div class="status-badge badge-pro">Plano Profissional Ativo</div>
            <div style="font-size: 32px; font-weight: 800;"><?php echo htmlspecialchars($dados['nome_plano']); ?></div>
            
            <div class="info-grid">
                <div><small>Próxima Cobrança</small><br><strong><?php echo date('d/m/Y', strtotime($dados['data_vencimento_assinatura'])); ?></strong></div>
                <div><small>Valor Mensal</small><br><strong>R$ <?php echo number_format($dados['preco_mensal'], 2, ',', '.'); ?></strong></div>
                <div><small>Status</small><br><strong style="color: var(--primary-green);">Ativo</strong></div>
            </div>

            <div class="actions">
                <a href="gerar_portal_stripe.php" class="btn btn-green">
                    <i class="fa-solid fa-arrows-rotate"></i> Gerenciar Assinatura
                </a>
            </div>
            <p style="font-size: 13px; color: var(--text-light); margin-top: 10px;">
                <i class="fa-solid fa-info-circle"></i> Clique em "Gerenciar Assinatura" para alterar seu plano, atualizar dados de pagamento ou cancelar.
            </p>

        <?php else: ?>
            <div class="status-badge badge-free">Plano Gratuito</div>
            <div style="font-size: 32px; font-weight: 800;">Versão Limitada</div>
            <p style="color: var(--text-light); margin-top: 10px;">Faça um upgrade para anunciar mais produtos e ter maior visibilidade.</p>
            <div class="actions">
                <a href="escolher_plano.php" class="btn btn-green">Escolher um Plano Profissional</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="historico-section">
        <h3><i class="fa-solid fa-file-invoice-dollar"></i> Recibos Anteriores</h3>
        <?php if (empty($historico_pagamentos)): ?>
            <p style="color: var(--text-light);">Nenhum recibo disponível.</p>
        <?php else: ?>
            <table class="tabela-recibos">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Recibo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico_pagamentos as $invoice): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', $invoice->created); ?></td>
                            <td>R$ <?php echo number_format($invoice->amount_paid / 100, 2, ',', '.'); ?></td>
                            <td><a href="<?php echo $invoice->hosted_invoice_url; ?>" target="_blank" class="link-recibo"><i class="fa-solid fa-file-pdf"></i> Ver Recibo</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <center>
        <a href="perfil.php" class="btn-voltar">
            <i class="fa-solid fa-arrow-left"></i> Voltar para o Perfil
        </a>
    </center>
</div>

</body>
</html>