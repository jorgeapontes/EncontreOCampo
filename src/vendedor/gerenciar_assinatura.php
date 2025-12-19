<?php
// src/vendedor/gerenciar_assinatura.php
require_once 'auth.php';

$vendedor_id = $vendedor['id'];

// Buscar assinatura ativa
$query_assinatura = "SELECT a.*, p.nome as plano_nome, p.preco_mensal 
                     FROM vendedor_assinaturas a
                     JOIN planos p ON a.plano_id = p.id
                     WHERE a.vendedor_id = :vendedor_id AND a.status = 'active'
                     ORDER BY a.created_at DESC LIMIT 1";
$stmt_assinatura = $db->prepare($query_assinatura);
$stmt_assinatura->bindParam(':vendedor_id', $vendedor_id);
$stmt_assinatura->execute();
$assinatura = $stmt_assinatura->fetch(PDO::FETCH_ASSOC);

// Buscar histórico de pagamentos
$query_pagamentos = "SELECT * FROM pagamentos 
                     WHERE vendedor_id = :vendedor_id AND assinatura_id = :assinatura_id
                     ORDER BY created_at DESC";
$stmt_pagamentos = $db->prepare($query_pagamentos);
$assinatura_id = $assinatura['id'] ?? 0;
$stmt_pagamentos->bindParam(':vendedor_id', $vendedor_id);
$stmt_pagamentos->bindParam(':assinatura_id', $assinatura_id);
$stmt_pagamentos->execute();
$pagamentos = $stmt_pagamentos->fetchAll(PDO::FETCH_ASSOC);

// Processar cancelamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_assinatura'])) {
    try {
        $db->beginTransaction();
        
        // Atualizar plano para Free
        $query_update_vendedor = "UPDATE vendedores SET plano_id = 1 WHERE id = :vendedor_id";
        $stmt_update_vendedor = $db->prepare($query_update_vendedor);
        $stmt_update_vendedor->bindParam(':vendedor_id', $vendedor_id);
        $stmt_update_vendedor->execute();
        
        // Cancelar assinatura
        $query_cancel = "UPDATE vendedor_assinaturas SET status = 'cancelled' WHERE id = :id";
        $stmt_cancel = $db->prepare($query_cancel);
        $stmt_cancel->bindParam(':id', $assinatura['id']);
        $stmt_cancel->execute();
        
        // Se tiver ID do Mercado Pago, cancelar lá também via API
        
        $db->commit();
        
        $mensagem_sucesso = "Assinatura cancelada com sucesso. Seu plano foi alterado para Free.";
        
        // Recarregar dados
        $vendedor['plano_id'] = 1;
        $assinatura = null;
        
    } catch (PDOException $e) {
        $db->rollBack();
        $mensagem_erro = "Erro ao cancelar assinatura: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Assinatura - Vendedor</title>
    <link rel="stylesheet" href="../css/vendedor/gerenciar.css">
    <style>
        .assinatura-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .assinatura-info {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .status-active {
            color: #2ecc71;
            font-weight: bold;
        }
        
        .status-cancelled {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .btn-cancelar {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-cancelar:hover {
            background: #c0392b;
        }
        
        .pagamentos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .pagamentos-table th,
        .pagamentos-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .pagamentos-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .status-approved {
            color: #2ecc71;
        }
        
        .status-pending {
            color: #f39c12;
        }
        
        .status-rejected {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="assinatura-container">
        <h1>Gerenciar Assinatura</h1>
        
        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert success-alert"><?php echo $mensagem_sucesso; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert error-alert"><?php echo $mensagem_erro; ?></div>
        <?php endif; ?>
        
        <?php if ($assinatura): ?>
            <div class="assinatura-info">
                <h2>Informações da Assinatura</h2>
                
                <div class="info-row">
                    <div class="info-label">Plano:</div>
                    <div class="info-value"><?php echo htmlspecialchars($assinatura['plano_nome']); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value status-active">ATIVA</div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Valor Mensal:</div>
                    <div class="info-value">R$ <?php echo number_format($assinatura['preco_mensal'], 2, ',', '.'); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Data de Início:</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($assinatura['data_inicio'])); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Próximo Vencimento:</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($assinatura['data_vencimento'])); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Referência:</div>
                    <div class="info-value"><?php echo htmlspecialchars($assinatura['referencia_mercadopago']); ?></div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <form method="POST" onsubmit="return confirm('Tem certeza que deseja cancelar sua assinatura?')">
                        <button type="submit" name="cancelar_assinatura" class="btn-cancelar">
                            <i class="fas fa-times"></i> Cancelar Assinatura
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($pagamentos)): ?>
                <div class="assinatura-info">
                    <h2>Histórico de Pagamentos</h2>
                    
                    <table class="pagamentos-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Método</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagamentos as $pagamento): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($pagamento['created_at'])); ?></td>
                                    <td>R$ <?php echo number_format($pagamento['valor'], 2, ',', '.'); ?></td>
                                    <td class="status-<?php echo $pagamento['status']; ?>">
                                        <?php 
                                        $status_labels = [
                                            'approved' => 'Aprovado',
                                            'pending' => 'Pendente',
                                            'rejected' => 'Rejeitado'
                                        ];
                                        echo $status_labels[$pagamento['status']] ?? $pagamento['status'];
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($pagamento['metodo_pagamento']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="assinatura-info">
                <h2>Nenhuma Assinatura Ativa</h2>
                <p>Você não possui uma assinatura ativa no momento.</p>
                <p><a href="escolher_plano.php" class="btn-plano">Ver Planos Disponíveis</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>