<?php
// src/vendedor/processar_decisao.php

session_start();
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/send_notification.php'; // NOVO: Adicionado para notificações
require_once __DIR__ . '/../funcoes_notificacoes.php';

function redirecionar($id, $tipo, $mensagem) {
    header("Location: detalhes_proposta.php?id=" . $id . "&" . $tipo . "=" . urlencode($mensagem));
    exit();
}

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'vendedor') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Vendedor."));
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: propostas.php?erro=" . urlencode("ID da proposta inválido."));
    exit();
}

if (!isset($_GET['action'])) {
    header("Location: propostas.php?erro=" . urlencode("Ação não especificada."));
    exit();
}

$proposta_comprador_id = (int)$_GET['id'];
$acao = $_GET['action'];
$usuario_id = $_SESSION['usuario_id'];
$database = new Database();
$conn = $database->getConnection();

// Ações válidas
$acoes_validas = ['aceitar', 'recusar', 'contraproposta'];
if (!in_array($acao, $acoes_validas)) {
    redirecionar($proposta_comprador_id, 'erro', "Ação inválida.");
}

try {
    // Primeiro, obtém o ID do vendedor
    $sql_vendedor = "SELECT id FROM vendedores WHERE usuario_id = :usuario_id";
    $stmt_vendedor = $conn->prepare($sql_vendedor);
    $stmt_vendedor->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_vendedor->execute();
    $vendedor = $stmt_vendedor->fetch(PDO::FETCH_ASSOC);

    if (!$vendedor) {
        redirecionar($proposta_comprador_id, 'erro', "Vendedor não encontrado.");
    }

    $vendedor_id = $vendedor['id'];

    // Buscar dados da proposta atual com informações para notificação
    $sql_proposta = "SELECT 
                        pc.*,
                        pn.id AS negociacao_id,
                        pn.produto_id,  -- ADICIONADO: produto_id da negociação
                        pn.status AS negociacao_status,
                        p.nome AS produto_nome,
                        p.vendedor_id AS produto_vendedor_id,
                        u.nome AS comprador_nome,
                        u.email AS comprador_email,
                        uv.nome AS vendedor_nome,
                        uv.email AS vendedor_email
                    FROM propostas_comprador pc
                    JOIN propostas_negociacao pn ON pc.id = pn.proposta_comprador_id
                    JOIN produtos p ON pn.produto_id = p.id  -- ADICIONADO: JOIN com produtos
                    JOIN compradores c ON pc.comprador_id = c.id
                    JOIN usuarios u ON c.usuario_id = u.id
                    JOIN vendedores v ON p.vendedor_id = v.id
                    JOIN usuarios uv ON v.usuario_id = uv.id
                    WHERE pc.id = :proposta_comprador_id AND p.vendedor_id = :vendedor_id";
    
    $stmt_proposta = $conn->prepare($sql_proposta);
    $stmt_proposta->bindParam(':proposta_comprador_id', $proposta_comprador_id, PDO::PARAM_INT);
    $stmt_proposta->bindParam(':vendedor_id', $vendedor_id, PDO::PARAM_INT);
    $stmt_proposta->execute();
    $proposta = $stmt_proposta->fetch(PDO::FETCH_ASSOC);

    if (!$proposta) {
        redirecionar($proposta_comprador_id, 'erro', "Proposta não encontrada ou acesso negado.");
    }

    // Verificar se a proposta pode ser processada
    if ($proposta['negociacao_status'] !== 'negociacao') {
        redirecionar($proposta_comprador_id, 'erro', "Esta proposta não pode mais ser processada.");
    }

    // Iniciar transação
    $conn->beginTransaction();
    
    try {
        if ($acao === 'aceitar') {
            // Verificar se há contraproposta do vendedor ativa
            $sql_check_vendedor = "SELECT pv.id, pv.status 
                                FROM propostas_vendedor pv
                                JOIN propostas_negociacao pn ON pv.id = pn.proposta_vendedor_id
                                WHERE pn.proposta_comprador_id = :proposta_comprador_id 
                                AND pv.status IN ('enviada', 'pendente')";
            $stmt_check_vendedor = $conn->prepare($sql_check_vendedor);
            $stmt_check_vendedor->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_check_vendedor->execute();
            $vendedor_proposal = $stmt_check_vendedor->fetch(PDO::FETCH_ASSOC);
            
            // Determinar status baseado na existência de contraproposta do vendedor
            if ($vendedor_proposal) {
                // Existe contraproposta do vendedor: situação 3
                $novo_status_comprador = 'aceita';
                $novo_status_vendedor = 'finalizada';
            } else {
                // Não existe contraproposta: situação 1
                $novo_status_comprador = 'aceita';
                $novo_status_vendedor = null; // Não aplicável
            }
            
            // 1. Atualizar status da proposta do comprador
            $sql_update_comprador = "UPDATE propostas_comprador 
                                    SET status = :status 
                                    WHERE id = :proposta_comprador_id";
            
            $stmt_update_comprador = $conn->prepare($sql_update_comprador);
            $stmt_update_comprador->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_comprador->bindParam(':status', $novo_status_comprador);
            $stmt_update_comprador->execute();
            
            // 2. Se existir proposta do vendedor, atualizar status
            if ($vendedor_proposal && $novo_status_vendedor) {
                $sql_update_vendedor = "UPDATE propostas_vendedor 
                                    SET status = :status
                                    WHERE id = :proposta_vendedor_id";
                
                $stmt_update_vendedor = $conn->prepare($sql_update_vendedor);
                $stmt_update_vendedor->bindParam(':proposta_vendedor_id', $vendedor_proposal['id']);
                $stmt_update_vendedor->bindParam(':status', $novo_status_vendedor);
                $stmt_update_vendedor->execute();
            }
            
            // 3. Atualizar negociação
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                    SET status = 'aceita',
                                        preco_final = :preco_final,
                                        quantidade_final = :quantidade_final,
                                        data_atualizacao = NOW()
                                    WHERE proposta_comprador_id = :proposta_comprador_id";
            
            $stmt_update_negociacao = $conn->prepare($sql_update_negociacao);
            $stmt_update_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_negociacao->bindParam(':preco_final', $proposta['preco_proposto']);
            $stmt_update_negociacao->bindParam(':quantidade_final', $proposta['quantidade_proposta']);
            $stmt_update_negociacao->execute();
            
            $conn->commit();
            
            // NOTIFICAÇÃO POR EMAIL - CORRIGIDO
            // Notificar o comprador
            if (!empty($proposta['comprador_email'])) {
                $subject = "Proposta Aceita - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($proposta['comprador_nome']) . ",\n\n";
                
                if ($vendedor_proposal) {
                    $message .= "Parabéns! O vendedor aceitou sua contraproposta para o produto '" . htmlspecialchars($proposta['produto_nome']) . "'.\n\n";
                    $message .= "A negociação foi finalizada com sucesso!\n\n";
                } else {
                    $message .= "Parabéns! O vendedor aceitou sua proposta para o produto '" . htmlspecialchars($proposta['produto_nome']) . "'.\n\n";
                    $message .= "A negociação foi concluída com sucesso!\n\n";
                }
                
                $message .= "Detalhes da negociação:\n";
                $message .= "- Produto: " . htmlspecialchars($proposta['produto_nome']) . "\n";
                $message .= "- Preço Final: R$ " . number_format($proposta['preco_proposto'], 2, ',', '.') . "\n";
                $message .= "- Quantidade: " . $proposta['quantidade_proposta'] . "\n";
                $message .= "- Vendedor: " . htmlspecialchars($proposta['vendedor_nome']) . "\n";
                $message .= "- Data da Aceitação: " . date('d/m/Y H:i') . "\n\n";
                $message .= "Entre em contato com o vendedor para acertar os detalhes finais da transação.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($proposta['comprador_email'], $proposta['comprador_nome'], $subject, $message);
            }
            
            // Notificar o vendedor
            if (!empty($proposta['vendedor_email'])) {
                $subject = "Proposta Aceita - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($proposta['vendedor_nome']) . ",\n\n";
                
                if ($vendedor_proposal) {
                    $message .= "Você aceitou a contraproposta do comprador para o produto '" . htmlspecialchars($proposta['produto_nome']) . "'.\n\n";
                    $message .= "A negociação foi finalizada com sucesso!\n\n";
                } else {
                    $message .= "Você aceitou a proposta do comprador para o produto '" . htmlspecialchars($proposta['produto_nome']) . "'.\n\n";
                    $message .= "A negociação foi concluída com sucesso!\n\n";
                }
                
                $message .= "Detalhes da negociação:\n";
                $message .= "- Produto: " . htmlspecialchars($proposta['produto_nome']) . "\n";
                $message .= "- Preço Final: R$ " . number_format($proposta['preco_proposto'], 2, ',', '.') . "\n";
                $message .= "- Quantidade: " . $proposta['quantidade_proposta'] . "\n";
                $message .= "- Comprador: " . htmlspecialchars($proposta['comprador_nome']) . "\n";
                $message .= "- Data da Aceitação: " . date('d/m/Y H:i') . "\n\n";
                $message .= "Entre em contato com o comprador para acertar os detalhes finais da transação.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($proposta['vendedor_email'], $proposta['vendedor_nome'], $subject, $message);
            }
            
            // Após aceitar a proposta: se a opção de frete for retirada/vendedor (não entregador), liberar avaliação imediata
            try {
                // Buscar usuario_id do comprador (tabela compradores -> usuario_id)
                $sql_usuario_comprador = "SELECT usuario_id FROM compradores WHERE id = :comprador_id";
                $stmt_uc = $conn->prepare($sql_usuario_comprador);
                $stmt_uc->bindParam(':comprador_id', $proposta['comprador_id']);
                $stmt_uc->execute();
                $comprador_row = $stmt_uc->fetch(PDO::FETCH_ASSOC);
                $comprador_usuario_id = $comprador_row['usuario_id'] ?? null;

                if ($comprador_usuario_id) {
                    // Buscar registro na tabela propostas para checar opcao_frete (usa IDs de usuário)
                    $sql_check_opcao = "SELECT opcao_frete, ID FROM propostas WHERE comprador_id = :usuario_id AND vendedor_id = :vendedor_id AND produto_id = :produto_id AND status = 'aceita' ORDER BY data_inicio DESC LIMIT 1";
                    $stmt_check = $conn->prepare($sql_check_opcao);
                    $stmt_check->bindParam(':usuario_id', $comprador_usuario_id, PDO::PARAM_INT);
                    $stmt_check->bindParam(':vendedor_id', $proposta['produto_vendedor_id'], PDO::PARAM_INT);
                    $stmt_check->bindParam(':produto_id', $proposta['produto_id'], PDO::PARAM_INT);
                    $stmt_check->execute();
                    $row_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

                    $opcao_frete = $row_check['opcao_frete'] ?? null;
                    $proposta_id_original = $row_check['ID'] ?? null;

                    if ($opcao_frete && in_array($opcao_frete, ['vendedor','comprador'])) {
                        // Criar notificação e enviar email pedindo avaliação
                        $url_avaliacao = "../avaliar.php?tipo=produto&produto_id=" . urlencode($proposta['produto_id']) . "&proposta_id=" . urlencode($proposta_comprador_id);
                        criarNotificacao($comprador_usuario_id, "Avalie seu produto: {$proposta['produto_nome']}", 'info', $url_avaliacao);

                        if (!empty($proposta['comprador_email'])) {
                            $assunto_av = "Avalie seu produto - Encontre o Campo";
                            $mensagem_av = "Olá {$proposta['comprador_nome']},\n\nSua compra do produto '{$proposta['produto_nome']}' foi confirmada. Agradecemos se puder avaliar sua experiência. Acesse: " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/' : '') . $url_avaliacao;
                            enviarEmailNotificacao($proposta['comprador_email'], $proposta['comprador_nome'], $assunto_av, $mensagem_av);
                        }
                    }
                }
            } catch (Exception $e) {
                // Não bloquear fluxo por falha na notificação
                error_log('Erro ao criar notificação de avaliação: ' . $e->getMessage());
            }

            $msg = $vendedor_proposal ? 
                "Contraproposta do comprador aceita com sucesso! Negociação finalizada." :
                "Proposta do comprador aceita com sucesso! Negociação concluída.";
                
            redirecionar($proposta_comprador_id, 'sucesso', $msg);
            
        } elseif ($acao === 'recusar') {
            // 1. Atualizar status da proposta do comprador
            $sql_update_comprador = "UPDATE propostas_comprador 
                                    SET status = 'recusada' 
                                    WHERE id = :proposta_comprador_id";
            
            $stmt_update_comprador = $conn->prepare($sql_update_comprador);
            $stmt_update_comprador->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_comprador->execute();
            
            // 2. Atualizar status da negociação
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                     SET status = 'recusada',
                                         data_atualizacao = NOW()
                                     WHERE proposta_comprador_id = :proposta_comprador_id";
            
            $stmt_update_negociacao = $conn->prepare($sql_update_negociacao);
            $stmt_update_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_negociacao->execute();
            
            $conn->commit();
            
            // NOTIFICAÇÃO POR EMAIL - CORRIGIDO
            // Notificar o comprador
            if (!empty($proposta['comprador_email'])) {
                $subject = "Proposta Recusada - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($proposta['comprador_nome']) . ",\n\n";
                $message .= "Infelizmente, o vendedor recusou sua proposta para o produto '" . htmlspecialchars($proposta['produto_nome']) . "'.\n\n";
                $message .= "Detalhes:\n";
                $message .= "- Produto: " . htmlspecialchars($proposta['produto_nome']) . "\n";
                $message .= "- Vendedor: " . htmlspecialchars($proposta['vendedor_nome']) . "\n";
                $message .= "- Data da Recusa: " . date('d/m/Y H:i') . "\n\n";
                $message .= "Você pode buscar outros produtos similares ou tentar negociar com outros vendedores.\n";
                $message .= "Acesse o sistema para explorar novas oportunidades.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($proposta['comprador_email'], $proposta['comprador_nome'], $subject, $message);
            }
            
            // Notificar o vendedor
            if (!empty($proposta['vendedor_email'])) {
                $subject = "Proposta Recusada - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($proposta['vendedor_nome']) . ",\n\n";
                $message .= "Você recusou a proposta do comprador para o produto '" . htmlspecialchars($proposta['produto_nome']) . "'.\n\n";
                $message .= "Detalhes:\n";
                $message .= "- Produto: " . htmlspecialchars($proposta['produto_nome']) . "\n";
                $message .= "- Comprador: " . htmlspecialchars($proposta['comprador_nome']) . "\n";
                $message .= "- Data da Recusa: " . date('d/m/Y H:i') . "\n\n";
                $message .= "A negociação foi encerrada. Você pode continuar recebendo outras propostas para este produto.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($proposta['vendedor_email'], $proposta['vendedor_nome'], $subject, $message);
            }
            
            redirecionar($proposta_comprador_id, 'sucesso', "Proposta recusada. A negociação foi encerrada.");
            
        } elseif ($acao === 'contraproposta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Processar contraproposta
            $novo_preco = $_POST['novo_preco'];
            $nova_quantidade = $_POST['nova_quantidade'];
            $novas_condicoes = $_POST['novas_condicoes'] ?? '';
            
            // 1. Criar nova proposta do vendedor
            $sql_insert_vendedor = "INSERT INTO propostas_vendedor 
                                   (proposta_comprador_id, vendedor_id, preco_proposto, 
                                    quantidade_proposta, condicoes_venda, status) 
                                   VALUES (:proposta_comprador_id, :vendedor_id, :preco, 
                                           :quantidade, :condicoes, 'enviada')";
            
            $stmt_insert_vendedor = $conn->prepare($sql_insert_vendedor);
            $stmt_insert_vendedor->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_insert_vendedor->bindParam(':vendedor_id', $vendedor_id);
            $stmt_insert_vendedor->bindParam(':preco', $novo_preco);
            $stmt_insert_vendedor->bindParam(':quantidade', $nova_quantidade);
            $stmt_insert_vendedor->bindParam(':condicoes', $novas_condicoes);
            $stmt_insert_vendedor->execute();
            
            $nova_proposta_vendedor_id = $conn->lastInsertId();
            
            // 2. Atualizar status da proposta do comprador para 'pendente'
            $sql_update_comprador = "UPDATE propostas_comprador 
                                    SET status = 'pendente' 
                                    WHERE id = :proposta_comprador_id";
            
            $stmt_update_comprador = $conn->prepare($sql_update_comprador);
            $stmt_update_comprador->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_comprador->execute();
            
            // 3. Atualizar a negociação para vincular com a nova proposta do vendedor
            $sql_update_negociacao = "UPDATE propostas_negociacao 
                                     SET proposta_vendedor_id = :proposta_vendedor_id,
                                         status = 'negociacao',
                                         data_atualizacao = NOW()
                                     WHERE proposta_comprador_id = :proposta_comprador_id";
            
            $stmt_update_negociacao = $conn->prepare($sql_update_negociacao);
            $stmt_update_negociacao->bindParam(':proposta_vendedor_id', $nova_proposta_vendedor_id);
            $stmt_update_negociacao->bindParam(':proposta_comprador_id', $proposta_comprador_id);
            $stmt_update_negociacao->execute();
            
            $conn->commit();
            
            // NOTIFICAÇÃO POR EMAIL - CORRIGIDO
            // Notificar o comprador
            if (!empty($proposta['comprador_email'])) {
                $subject = "Nova Contraproposta Recebida - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($proposta['comprador_nome']) . ",\n\n";
                $message .= "O vendedor fez uma contraproposta para o produto '" . htmlspecialchars($proposta['produto_nome']) . "'.\n\n";
                $message .= "Detalhes da contraproposta:\n";
                $message .= "- Preço: R$ " . number_format($novo_preco, 2, ',', '.') . "\n";
                $message .= "- Quantidade: " . $nova_quantidade . "\n";
                if (!empty($novas_condicoes)) {
                    $message .= "- Condições: " . htmlspecialchars($novas_condicoes) . "\n";
                }
                $message .= "- Vendedor: " . htmlspecialchars($proposta['vendedor_nome']) . "\n";
                $message .= "- Data: " . date('d/m/Y H:i') . "\n\n";
                $message .= "Acesse o sistema para analisar a contraproposta e tomar uma decisão.\n";
                $message .= "Você pode aceitar, recusar ou fazer uma nova contraproposta.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($proposta['comprador_email'], $proposta['comprador_nome'], $subject, $message);
            }
            
            // Notificar o vendedor
            if (!empty($proposta['vendedor_email'])) {
                $subject = "Contraproposta Enviada - Encontre o Campo";
                $message = "Olá " . htmlspecialchars($proposta['vendedor_nome']) . ",\n\n";
                $message .= "Você enviou uma contraproposta para o comprador sobre o produto '" . htmlspecialchars($proposta['produto_nome']) . "'.\n\n";
                $message .= "Detalhes da contraproposta:\n";
                $message .= "- Preço: R$ " . number_format($novo_preco, 2, ',', '.') . "\n";
                $message .= "- Quantidade: " . $nova_quantidade . "\n";
                if (!empty($novas_condicoes)) {
                    $message .= "- Condições: " . htmlspecialchars($novas_condicoes) . "\n";
                }
                $message .= "- Comprador: " . htmlspecialchars($proposta['comprador_nome']) . "\n";
                $message .= "- Data: " . date('d/m/Y H:i') . "\n\n";
                $message .= "Aguarde a resposta do comprador. Ele foi notificado sobre sua contraproposta.\n\n";
                $message .= "Atenciosamente,\nEquipe Encontre o Campo";
                
                enviarEmailNotificacao($proposta['vendedor_email'], $proposta['vendedor_nome'], $subject, $message);
            }
            
            redirecionar($proposta_comprador_id, 'sucesso', "Contraproposta enviada com sucesso! Aguarde a resposta do comprador.");
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro ao processar decisão: " . $e->getMessage());
    redirecionar($proposta_comprador_id, 'erro', "Erro interno do sistema. Tente novamente.");
}
?>