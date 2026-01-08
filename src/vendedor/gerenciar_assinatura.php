<?php
// src/vendedor/gerenciar_assinatura.php
require_once 'auth.php'; // Garante que $vendedor e $db estão carregados

// 1. Buscamos os dados atualizados do banco
$stmt = $db->prepare("
    SELECT 
        v.plano_id, 
        v.status_assinatura, 
        v.data_vencimento_assinatura, 
        v.Data_assinatura, 
        v.Data_inicio_assinatura,
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

// 4. Fallback para data de início (caso uma coluna esteja vazia, tenta a outra)
$data_exibicao_inicio = $dados['Data_inicio_assinatura'] ?? $dados['Data_assinatura'] ?? null;
?>

<?php
// ... (mantenha o código que já fizemos no topo)

// --- NOVA LÓGICA: BUSCAR RECIBOS DA STRIPE ---
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/StripeConfig.php';
\Config\StripeConfig::init();

$historico_pagamentos = [];

try {
    // 1. Pegamos o email do vendedor logado
    $email_vendedor = $_SESSION['usuario_email'];

    // 2. Localizamos o ID do cliente na Stripe
    $customers = \Stripe\Customer::all(['email' => $email_vendedor, 'limit' => 1]);
    
    if (!empty($customers->data)) {
        $customer_id = $customers->data[0]->id;

        // 3. Buscamos as últimas faturas pagas
        $invoices = \Stripe\Invoice::all([
            'customer' => $customer_id,
            'limit' => 5, // Mostrar as últimas 5
            'status' => 'paid'
        ]);
        $historico_pagamentos = $invoices->data;
    }
} catch (Exception $e) {
    // Silencioso: se não encontrar faturas, a lista fica vazia
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Assinatura | Encontre o Campo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #28a745;
            --dark-green: #1e7e34;
            --soft-green: #eafaf1;
            --text-main: #2d3436;
            --text-light: #636e72;
            --white: #ffffff;
            --border: #e2e8f0;
        }

        body {
            background-color: #f8faf9;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
        }

        .header-section {
            margin-bottom: 30px;
            text-align: center;
        }

        h1 { font-size: 28px; font-weight: 700; color: var(--text-main); }

        .card-assinatura {
            background: var(--white);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            text-align: left;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .badge-pro { background: var(--soft-green); color: var(--primary-green); }
        .badge-free { background: #f1f3f5; color: #6c757d; }

        .plano-nome {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 10px;
        }

        .price-tag {
            font-size: 18px;
            color: var(--text-light);
            margin-bottom: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }

        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 12px; color: var(--text-light); text-transform: uppercase; margin-bottom: 5px; }
        .info-value { font-size: 16px; font-weight: 600; color: var(--text-main); }

        .actions {
            margin-top: 40px;
            display: flex;
            gap: 15px;
        }

        .btn {
            flex: 1;
            padding: 15px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s ease;
            font-size: 15px;
            border: none;
            cursor: pointer;
        }

        .btn-green { background-color: var(--primary-green); color: white; }
        .btn-green:hover { background-color: var(--dark-green); transform: translateY(-2px); }

        .btn-outline { border: 1px solid var(--border); color: var(--text-light); background: white; }
        .btn-outline:hover { background: #f8faf9; color: var(--text-main); }

        .btn-danger-outline { border: 1px solid #ffcdd2; color: #d63031; background: white; }
        .btn-danger-outline:hover { background: #fff5f5; }


        .historico-section {
        margin-top: 40px;
        background: white;
        padding: 30px;
        border-radius: 20px;
        border: 1px solid var(--border);
    }
    .historico-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .tabela-recibos {
        width: 100%;
        border-collapse: collapse;
    }
    .tabela-recibos th {
        text-align: left;
        font-size: 12px;
        color: var(--text-light);
        text-transform: uppercase;
        padding: 10px;
        border-bottom: 2px solid var(--bg);
    }
    .tabela-recibos td {
        padding: 15px 10px;
        border-bottom: 1px solid var(--bg);
        font-size: 14px;
    }
    .btn-recibo {
        color: var(--primary-green);
        text-decoration: none;
        font-weight: 600;
    }
    .btn-recibo:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <h1>Minha Assinatura</h1>
        <p style="color: var(--text-light)">Gerencie seu plano e recursos de vendas</p>
    </div>

    <div class="card-assinatura">
        <?php if ($eh_plano_pago): ?>
            <div class="status-badge badge-pro">
                <i class="fa-solid fa-crown"></i> Plano Profissional
            </div>

            <div class="plano-nome"><?php echo htmlspecialchars($dados['nome_plano']); ?></div>
            <div class="price-tag">R$ <?php echo number_format($dados['preco_mensal'], 2, ',', '.'); ?> <span style="font-size: 14px;">/ mês</span></div>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Início da Assinatura</span>
                    <span class="info-value">
                        <?php echo ($data_exibicao_inicio) ? date('d/m/Y', strtotime($data_exibicao_inicio)) : '---'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Próxima Cobrança</span>
                    <span class="info-value" style="color: var(--primary-green);">
                        <?php echo !empty($dados['data_vencimento_assinatura']) ? date('d/m/Y', strtotime($dados['data_vencimento_assinatura'])) : '---'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Produtos Ativos</span>
                    <span class="info-value"><?php echo $total_produtos; ?> cadastrados</span>
                </div>
            </div>

            <div class="actions">
                <a href="escolher_plano.php" class="btn btn-outline">Mudar de Plano</a>
                <a href="gerar_portal_stripe.php" class="btn btn-danger-outline">
                    <i class="fa-solid fa-gear"></i> Cancelar ou Alterar Cartão
                </a>
            </div>

        <?php else: ?>
            <div class="status-badge badge-free">
                <i class="fa-solid fa-seedling"></i> Plano Gratuito
            </div>

            <div class="plano-nome">Versão Limitada</div>
            <p style="color: var(--text-light); margin-top: 10px;">
                Você está no plano básico. Seus produtos têm visibilidade limitada e você possui um limite menor de anúncios.
            </p>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Custo Mensal</span>
                    <span class="info-value">R$ 0,00</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Produtos Ativos</span>
                    <span class="info-value"><?php echo $total_produtos; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value">Sempre Ativo</span>
                </div>
            </div>

            <div class="actions">
                <a href="escolher_plano.php" class="btn btn-green">Fazer Upgrade para Profissional</a>
            </div>
        <?php endif; ?>
    </div>


    <div class="historico-section">
    <div class="historico-title">
        <i class="fa-solid fa-file-invoice-dollar"></i> Histórico de Pagamentos
    </div>

    <?php if (empty($historico_pagamentos)): ?>
        <p style="color: var(--text-light); font-size: 14px;">Nenhum pagamento registrado até o momento.</p>
    <?php else: ?>
        <table class="tabela-recibos">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Plano</th>
                    <th>Valor</th>
                    <th>Recibo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historico_pagamentos as $invoice): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', $invoice->created); ?></td>
                        <td><?php echo $invoice->lines->data[0]->description ?? 'Assinatura'; ?></td>
                        <td>R$ <?php echo number_format($invoice->amount_paid / 100, 2, ',', '.'); ?></td>
                        <td>
                            <a href="<?php echo $invoice->hosted_invoice_url; ?>" target="_blank" class="btn-recibo">
                                <i class="fa-solid fa-download"></i> Ver Recibo
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="perfil.php" style="color: var(--text-light); text-decoration: none; font-size: 14px;">
            <i class="fa-solid fa-arrow-left"></i> Voltar ao Painel
        </a>
    </div>
</div>

</body>
</html>