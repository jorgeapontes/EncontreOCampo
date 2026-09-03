<?php
// src/transportador/dashboard.php
require_once __DIR__ . '/../permissions.php';
require_once __DIR__ . '/../conexao.php';

// 1. VERIFICAÇÃO DE ACESSO E SEGURANÇA
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'transportador') {
    header("Location: ../login?erro=" . urlencode("Acesso restrito. Faça login como Transportador."));
    exit();
}

// Verificar se o usuário tem permissão para ver dashboard completo
$usuario_status = $_SESSION['usuario_status'] ?? 'pendente';
$is_pendente = ($usuario_status === 'pendente');

$usuario_nome = htmlspecialchars($_SESSION['transportador_nome'] ?? 'Transportador');
$usuario_id = $_SESSION['usuario_id'];

// Conexão com o banco de dados
$database = new Database();
$db = $database->getConnection();

// Buscar dados do transportador
$transportador_id = null;
$transportador_nome_comercial = '';

try {
    $sql_transportador = "SELECT id, nome_comercial 
                         FROM transportadores 
                         WHERE usuario_id = :usuario_id";
                     
    $stmt_transportador = $db->prepare($sql_transportador);
    $stmt_transportador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_transportador->execute();
    $transportador = $stmt_transportador->fetch(PDO::FETCH_ASSOC);
    
    if ($transportador) {
        $transportador_id = $transportador['id'];
        $transportador_nome_comercial = $transportador['nome_comercial'] ?? $usuario_nome;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do transportador: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Entregas - Encontre Ocampo</title>
    <link rel="stylesheet" href="../css/transportador/navbar.css">
    <link rel="stylesheet" href="../css/transportador/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
</head>
<body>
    <?php $active_nav = ''; require __DIR__ . '/includes/navbar.php'; ?>
    <br>
    <div class="main-content">
        <section class="header">
            <center>
                <h1>Histórico de Entregas</h1>
                <p class="subtitulo">Visualize todas as suas entregas finalizadas</p>
            </center>
        </section>

        <section class="historico-entregas">
            <h2>Histórico de Entregas Finalizadas</h2>
            
            <div class="tabela-entregas">
                <?php
                if (!$is_pendente && $transportador_id) {
                    $sql_hist = "SELECT e.id, e.endereco_origem, e.endereco_destino, e.valor_frete, e.data_entrega, e.foto_comprovante, e.assinatura_comprovante, p.nome as produto_nome, c.nome_comercial as comprador_nome, v.nome_comercial as vendedor_nome, v.cep as vendedor_cep, v.rua as vendedor_rua, v.numero as vendedor_numero, v.cidade as vendedor_cidade, v.estado as vendedor_estado
                        FROM entregas e
                        INNER JOIN produtos p ON e.produto_id = p.id
                        LEFT JOIN compradores c ON e.comprador_id = c.usuario_id
                        INNER JOIN vendedores v ON v.id = COALESCE(e.vendedor_id, p.vendedor_id)
                        WHERE e.transportador_id = :transportador_id AND e.status = 'entregue' AND e.status_detalhado = 'finalizada'
                        ORDER BY e.data_entrega DESC";
                    $stmt_hist = $db->prepare($sql_hist);
                    $stmt_hist->bindParam(':transportador_id', $transportador_id);
                    $stmt_hist->execute();
                    $entregas_finalizadas = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($entregas_finalizadas) === 0) {
                        echo '<div class="empty-state-container">
                                <div class="empty-state-icon"><i class="fas fa-history"></i></div>
                                <h3>Nenhuma entrega finalizada</h3>
                                <p>Quando você finalizar entregas, elas aparecerão aqui.</p>
                                <a href="disponiveis" class="empty-state-button"><i class="fas fa-search"></i> Buscar Entregas Disponíveis</a>
                              </div>';
                    } else {
                        // Tabela para desktop
                        echo '<table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Produto</th>
                                        <th>Comprador</th>
                                        <th>Vendedor</th>
                                        <th>Origem</th>
                                        <th>Destino</th>
                                        <th>Valor Frete</th>
                                        <th>Data Entrega</th>
                                        <th>Comprovante</th>
                                        <th>Assinatura</th>
                                    </tr>
                                </thead>
                                <tbody>';
                        
                        foreach ($entregas_finalizadas as $e) {
                            $origem_full = '';
                            if (!empty(trim($e['endereco_origem'] ?? ''))) {
                                $origem_full = $e['endereco_origem'];
                            } else {
                                $origem_full = (trim($e['vendedor_rua'] ?? '') !== '' ? ($e['vendedor_rua'] . ', ') : '')
                                    . ($e['vendedor_numero'] ?? '')
                                    . (isset($e['vendedor_cidade']) ? ' - ' . $e['vendedor_cidade'] : '')
                                    . (isset($e['vendedor_estado']) ? '/' . $e['vendedor_estado'] : '')
                                    . (!empty($e['vendedor_cep'] ?? '') ? ' - CEP: ' . $e['vendedor_cep'] : '');
                            }
                            $destino_full = $e['endereco_destino'] ?? '';

                            echo '<tr>';
                            echo '<td>' . $e['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($e['produto_nome']) . '</td>';
                            echo '<td>' . htmlspecialchars($e['comprador_nome'] ?? '—') . '</td>';
                            echo '<td>' . htmlspecialchars($e['vendedor_nome']) . '</td>';
                            echo '<td>' . htmlspecialchars(mb_substr($origem_full, 0, 20)) . (mb_strlen($origem_full) > 20 ? '...' : '') . '</td>';
                            echo '<td>' . htmlspecialchars(mb_substr($destino_full, 0, 20)) . (mb_strlen($destino_full) > 20 ? '...' : '') . '</td>';
                            echo '<td>R$ ' . number_format($e['valor_frete'], 2, ',', '.') . '</td>';
                            echo '<td>' . ($e['data_entrega'] ? date('d/m/Y', strtotime($e['data_entrega'])) : '-') . '</td>';
                            
                            // Comprovante
                            if (!empty($e['foto_comprovante'])) {
                                echo '<td><a href="../../uploads/entregas/' . htmlspecialchars($e['foto_comprovante']) . '" target="_blank" class="hist-link">Ver Foto</a></td>';
                            } else {
                                echo '<td>-</td>';
                            }

                            // Assinatura
                            if (!empty($e['assinatura_comprovante'])) {
                                echo '<td><a href="../../uploads/entregas/' . htmlspecialchars($e['assinatura_comprovante']) . '" target="_blank" class="hist-link">Ver Assinatura</a></td>';
                            } else {
                                echo '<td>-</td>';
                            }
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                        
                        // Cards para mobile
                        echo '<div class="cards-entregas-mobile">';
                        
                        foreach ($entregas_finalizadas as $e) {
                            $origem_full = '';
                            if (!empty(trim($e['endereco_origem'] ?? ''))) {
                                $origem_full = $e['endereco_origem'];
                            } else {
                                $origem_full = (trim($e['vendedor_rua'] ?? '') !== '' ? ($e['vendedor_rua'] . ', ') : '')
                                    . ($e['vendedor_numero'] ?? '')
                                    . (isset($e['vendedor_cidade']) ? ' - ' . $e['vendedor_cidade'] : '')
                                    . (isset($e['vendedor_estado']) ? '/' . $e['vendedor_estado'] : '')
                                    . (!empty($e['vendedor_cep'] ?? '') ? ' - CEP: ' . $e['vendedor_cep'] : '');
                            }
                            $destino_full = $e['endereco_destino'] ?? '';
                            
                            echo '<div class="card-entrega">';
                            echo '<div class="card-entrega-header">';
                            echo '<div class="card-entrega-title">';
                            echo '<h3>' . htmlspecialchars($e['produto_nome']) . '</h3>';
                            echo '<span class="card-entrega-id">ID: ' . $e['id'] . '</span>';
                            echo '</div>';
                            echo '<span class="card-entrega-status status entregue">Entregue</span>';
                            echo '</div>';
                            
                            echo '<div class="card-entrega-body">';
                            echo '<div class="card-info-item">';
                            echo '<span class="card-info-label">Comprador</span>';
                            echo '<span class="card-info-value">' . htmlspecialchars($e['comprador_nome'] ?? '—') . '</span>';
                            echo '</div>';
                            
                            echo '<div class="card-info-item">';
                            echo '<span class="card-info-label">Vendedor</span>';
                            echo '<span class="card-info-value">' . htmlspecialchars($e['vendedor_nome']) . '</span>';
                            echo '</div>';
                            
                            echo '<div class="card-info-item">';
                            echo '<span class="card-info-label">Valor Frete</span>';
                            echo '<span class="card-info-value">R$ ' . number_format($e['valor_frete'], 2, ',', '.') . '</span>';
                            echo '</div>';
                            
                            echo '<div class="card-info-item">';
                            echo '<span class="card-info-label">Data Entrega</span>';
                            echo '<span class="card-info-value">' . ($e['data_entrega'] ? date('d/m/Y', strtotime($e['data_entrega'])) : '-') . '</span>';
                            echo '</div>';
                            
                            echo '<div class="card-info-item">';
                            echo '<span class="card-info-label">Origem</span>';
                            echo '<span class="card-info-value small">' . htmlspecialchars(substr($origem_full, 0, 25)) . (strlen($origem_full) > 25 ? '...' : '') . '</span>';
                            echo '</div>';
                            
                            echo '<div class="card-info-item">';
                            echo '<span class="card-info-label">Destino</span>';
                            echo '<span class="card-info-value small">' . htmlspecialchars(substr($destino_full, 0, 25)) . (strlen($destino_full) > 25 ? '...' : '') . '</span>';
                            echo '</div>';
                            echo '</div>';
                            
                            echo '<div class="card-entrega-actions">';
                            
                            // Botão para ver foto do comprovante
                            if (!empty($e['foto_comprovante'])) {
                                echo '<a href="../../uploads/entregas/' . htmlspecialchars($e['foto_comprovante']) . '" target="_blank" class="hist-doc-btn foto">';
                                echo '<i class="fas fa-camera hist-doc-icon"></i>Foto';
                                echo '</a>';
                            }

                            // Botão para ver assinatura
                            if (!empty($e['assinatura_comprovante'])) {
                                echo '<a href="../../uploads/entregas/' . htmlspecialchars($e['assinatura_comprovante']) . '" target="_blank" class="hist-doc-btn assinatura">';
                                echo '<i class="fas fa-signature hist-doc-icon"></i>Assinatura';
                                echo '</a>';
                            }
                            
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        echo '</div>'; // Fecha cards-entregas-mobile
                    }
                } else {
                    echo '<div class="empty-state-container">
                            <div class="empty-state-icon"><i class="fas fa-history"></i></div>
                            <h3>Nenhuma entrega finalizada</h3>
                            <p>Quando você finalizar entregas, elas aparecerão aqui.</p>
                            <a href="disponiveis" class="empty-state-button"><i class="fas fa-search"></i> Buscar Entregas Disponíveis</a>
                          </div>';
                }
                ?>
            </div>
        </section>
    </div>
</body>
</html>