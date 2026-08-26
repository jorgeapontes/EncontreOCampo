<?php
// src/criar_entrega_apos_aceite.php
require_once __DIR__ . '/conexao.php';

function criarEntregaSeAceita($proposta_frete_id) {
    $database = new Database();
    $db = $database->getConnection();
    // Buscar dados da proposta de frete aceita
    $sql = "SELECT pf.*, p.produto_id, p.comprador_id, p.vendedor_id, p.id as proposta_id
            FROM propostas_frete_transportador pf
            INNER JOIN propostas p ON pf.proposta_id = p.id
            WHERE pf.id = :id AND pf.status = 'aceita'";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $proposta_frete_id, PDO::PARAM_INT);
    $stmt->execute();
    $pf = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pf) return false;
    // Verifica se já existe entrega para esse acordo e transportador
    $sql_check = "SELECT id FROM entregas WHERE produto_id = :produto_id AND transportador_id = :transportador_id AND comprador_id = :comprador_id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':produto_id', $pf['produto_id']);
    $stmt_check->bindParam(':transportador_id', $pf['transportador_id']);
    $stmt_check->bindParam(':comprador_id', $pf['comprador_id']);
    $stmt_check->execute();
    if ($stmt_check->fetch()) return true;
    // Buscar endereços
    $sql_v = "SELECT rua, numero, cidade, estado, cep FROM vendedores WHERE id = :id";
    $stmt_v = $db->prepare($sql_v);
    $stmt_v->bindParam(':id', $pf['vendedor_id']);
    $stmt_v->execute();
    $vend = $stmt_v->fetch(PDO::FETCH_ASSOC);
    $origem = $vend ? ($vend['rua'] . ', ' . $vend['numero'] . ' - ' . $vend['cidade'] . '/' . $vend['estado'] . ' - CEP: ' . $vend['cep']) : '';
    $sql_c = "SELECT rua, numero, cidade, estado, cep FROM compradores WHERE id = :id";
    $stmt_c = $db->prepare($sql_c);
    $stmt_c->bindParam(':id', $pf['comprador_id']);
    $stmt_c->execute();
    $comp = $stmt_c->fetch(PDO::FETCH_ASSOC);
    $destino = $comp ? ($comp['rua'] . ', ' . $comp['numero'] . ' - ' . $comp['cidade'] . '/' . $comp['estado'] . ' - CEP: ' . $comp['cep']) : '';
    // Inserir entrega
    $sql_insert = "INSERT INTO entregas (produto_id, transportador_id, endereco_origem, endereco_destino, status, valor_frete, vendedor_id, comprador_id, status_detalhado) VALUES (:produto_id, :transportador_id, :origem, :destino, 'pendente', :valor_frete, :vendedor_id, :comprador_id, 'aguardando_entrega')";
    $stmt_insert = $db->prepare($sql_insert);
    $stmt_insert->bindParam(':produto_id', $pf['produto_id']);
    $stmt_insert->bindParam(':transportador_id', $pf['transportador_id']);
    $stmt_insert->bindParam(':origem', $origem);
    $stmt_insert->bindParam(':destino', $destino);
    $stmt_insert->bindParam(':valor_frete', $pf['valor_frete']);
    $stmt_insert->bindParam(':vendedor_id', $pf['vendedor_id']);
    $stmt_insert->bindParam(':comprador_id', $pf['comprador_id']);
    $stmt_insert->execute();
    return true;
}
