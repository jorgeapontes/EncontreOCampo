<?php
// src/admin/visualizar_chat.php
session_start();
require_once __DIR__ . '/../conexao.php';

// Verificar se é admin
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$conversa_id = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;

if ($conversa_id <= 0) {
    header("Location: chats_admin.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Buscar info da conversa
try {
    $sql_conversa = "SELECT 
                cc.*,
                p.nome AS produto_nome,
                p.imagem_url AS produto_imagem,
                p.preco AS produto_preco,
                p.preco_desconto AS produto_preco_desconto,
                p.desconto_data_fim AS produto_desconto_data_fim,
                uc.nome AS comprador_nome,
                uc.email AS comprador_email,
                uv.nome AS vendedor_nome,
                uv.email AS vendedor_email
            FROM chat_conversas cc
            INNER JOIN produtos p ON cc.produto_id = p.id
            INNER JOIN usuarios uc ON cc.comprador_id = uc.id
            LEFT JOIN vendedores v ON p.vendedor_id = v.id
            LEFT JOIN usuarios uv ON v.usuario_id = uv.id
            WHERE cc.id = :conversa_id";
    
    $stmt = $conn->prepare($sql_conversa);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $conversa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversa) {
        header("Location: chats_admin.php");
        exit();
    }
    
    // Calcular preço final com desconto se aplicável
    $preco_final = $conversa['produto_preco'];
    $tem_desconto = false;
    $porcentagem_desconto = 0;
    
    if ($conversa['produto_preco_desconto'] && 
        $conversa['produto_preco_desconto'] > 0 && 
        $conversa['produto_preco_desconto'] < $conversa['produto_preco']) {
        
        // Verificar se o desconto ainda está válido
        $agora = date('Y-m-d H:i:s');
        if (empty($conversa['produto_desconto_data_fim']) || $conversa['produto_desconto_data_fim'] > $agora) {
            $tem_desconto = true;
            $preco_final = $conversa['produto_preco_desconto'];
            $porcentagem_desconto = round((($conversa['produto_preco'] - $conversa['produto_preco_desconto']) / $conversa['produto_preco']) * 100);
        }
    }
    
} catch (PDOException $e) {
    die("Erro ao buscar conversa: " . $e->getMessage());
}

// Buscar TODAS as mensagens (incluindo deletadas)
try {
    $sql_mensagens = "SELECT 
                cm.*,
                u.nome AS remetente_nome,
                DATE_FORMAT(cm.data_envio, '%d/%m/%Y %H:%i:%s') as data_formatada
            FROM chat_mensagens cm
            INNER JOIN usuarios u ON cm.remetente_id = u.id
            WHERE cm.conversa_id = :conversa_id
            ORDER BY cm.data_envio ASC";
    
    $stmt = $conn->prepare($sql_mensagens);
    $stmt->bindParam(':conversa_id', $conversa_id, PDO::PARAM_INT);
    $stmt->execute();
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $mensagens = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Chat - Admin</title>
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }

        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #dc3545; /* Cor de PDF padrão */
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-export:hover {
            background: #b02a37;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #2E7D32;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .produto-info {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .produto-info img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .produto-details h3 {
            margin-bottom: 5px;
        }
        
        .produto-preco {
            color: #28a745;
            font-weight: 700;
            font-size: 18px;
        }
        
        .preco-original {
            text-decoration: line-through;
            color: #999;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .badge-desconto {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 10px;
        }
        
        .usuarios-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .usuario-box {
            padding: 15px;
            background: #e9ecef;
            border-radius: 8px;
        }
        
        .usuario-box h4 {
            margin-bottom: 8px;
            color: #333;
        }
        
        .usuario-box p {
            font-size: 14px;
            color: #666;
            margin: 4px 0;
        }
        
        .chat-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-height: 600px;
            overflow-y: auto;
        }
        
        .message {
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 12px;
            max-width: 70%;
        }
        
        .message.comprador {
            background: #e3f2fd;
            margin-left: 0;
            border-left: 4px solid #2196f3;
        }
        
        .message.vendedor {
            background: #e8f5e9;
            margin-left: auto;
            border-left: 4px solid #4caf50;
        }
        
        .message.deletada {
            opacity: 0.5;
            background: #ffebee;
            border-left: 4px solid #f44336;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .message-remetente {
            color: #333;
        }
        
        .message-data {
            color: #999;
        }
        
        .message-conteudo {
            color: #333;
            line-height: 1.4;
            word-wrap: break-word;
        }
        
        .message-deletada-info {
            margin-top: 8px;
            font-size: 11px;
            color: #f44336;
            font-style: italic;
        }
        
        .alert-deletada {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #856404;
        }
        
        .alert-deletada i {
            margin-right: 8px;
        }
        
        .empty-chat {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-chat i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-controls">
            <a href="chats_admin.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Voltar para Lista
            </a>
            
            <a href="exportar_pdf.php?conversa_id=<?php echo $conversa_id; ?>" target="_blank" class="btn-export">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
        </div>
        
        <div class="header">
            <h1>
                <i class="fas fa-eye"></i>
                Visualização de Chat (Admin)
            </h1>
            
            <?php if ($conversa['deletado']): ?>
                <div class="alert-deletada">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>ATENÇÃO:</strong> Esta conversa foi deletada em 
                    <?php echo date('d/m/Y H:i', strtotime($conversa['data_delecao'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="produto-info">
                <img src="<?php echo htmlspecialchars($conversa['produto_imagem'] ?: '../../img/placeholder.png'); ?>" alt="Produto">
                <div class="produto-details">
                    <h3><?php echo htmlspecialchars($conversa['produto_nome']); ?></h3>
                    <div class="produto-preco">
                        <?php if ($tem_desconto): ?>
                            <span class="preco-original">R$ <?php echo number_format($conversa['produto_preco'], 2, ',', '.'); ?></span>
                            <span>R$ <?php echo number_format($preco_final, 2, ',', '.'); ?></span>
                            <span class="badge-desconto">-<?php echo $porcentagem_desconto; ?>%</span>
                        <?php else: ?>
                            R$ <?php echo number_format($preco_final, 2, ',', '.'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="usuarios-box">
                <div class="usuario-box">
                    <h4><i class="fas fa-user"></i> Comprador</h4>
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($conversa['comprador_nome']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($conversa['comprador_email']); ?></p>
                    <p><strong>ID:</strong> <?php echo $conversa['comprador_id']; ?></p>
                </div>
                
                <div class="usuario-box">
                    <h4><i class="fas fa-store"></i> Vendedor</h4>
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($conversa['vendedor_nome']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($conversa['vendedor_email']); ?></p>
                    <p><strong>ID:</strong> <?php echo $conversa['vendedor_id']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="chat-box">
            <?php if (count($mensagens) > 0): ?>
                <?php foreach ($mensagens as $msg): 
                    $eh_comprador = $msg['remetente_id'] == $conversa['comprador_id'];
                    $classe = $eh_comprador ? 'comprador' : 'vendedor';
                    if ($msg['deletado']) $classe .= ' deletada';
                ?>
                    <div class="message <?php echo $classe; ?>">
                        <div class="message-header">
                            <span class="message-remetente">
                                <?php echo htmlspecialchars($msg['remetente_nome']); ?>
                                (<?php echo $eh_comprador ? 'Comprador' : 'Vendedor'; ?>)
                            </span>
                            <span class="message-data"><?php echo $msg['data_formatada']; ?></span>
                        </div>
                        <div class="message-conteudo">
                            <?php echo nl2br(htmlspecialchars($msg['mensagem'])); ?>
                        </div>
                        <?php if ($msg['deletado']): ?>
                            <div class="message-deletada-info">
                                <i class="fas fa-trash"></i>
                                Mensagem deletada em <?php echo date('d/m/Y H:i', strtotime($msg['data_delecao'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-chat">
                    <i class="fas fa-comments"></i>
                    <h3>Nenhuma mensagem nesta conversa</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>