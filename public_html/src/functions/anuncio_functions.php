<?php

function podeCriarAnuncio($vendedor_id, $db) {
    // Buscar plano do vendedor
    $query = "SELECT v.plano_id, p.limite_total_anuncios, p.quantidade_anuncios_gratis, 
                     p.quantidade_anuncios_pagos, a.unidades_extras
              FROM vendedores v
              JOIN planos p ON v.plano_id = p.id
              LEFT JOIN vendedor_assinaturas a ON v.id = a.vendedor_id AND a.status = 'active'
              WHERE v.id = :vendedor_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendedor_id', $vendedor_id);
    $stmt->execute();
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dados) {
        return false;
    }
    
    // Contar anúncios ativos do vendedor
    $query_count = "SELECT COUNT(*) as total FROM anuncios 
                    WHERE vendedor_id = :vendedor_id AND status = 'ativo'";
    $stmt_count = $db->prepare($query_count);
    $stmt_count->bindParam(':vendedor_id', $vendedor_id);
    $stmt_count->execute();
    $count = $stmt_count->fetch(PDO::FETCH_ASSOC);
    
    $anuncios_ativos = $count['total'] ?? 0;
    
    // Calcular limite total
    $limite_total = $dados['limite_total_anuncios'];
    if ($dados['plano_id'] == 5) {
        // Plano 5 tem unidades extras
        $limite_total += $dados['unidades_extras'] ?? 0;
    }
    
    // Verificar se ainda pode criar
    if ($limite_total === null) {
        return true; // Ilimitado
    }
    
    return $anuncios_ativos < $limite_total;
}

function getLimiteAnuncios($vendedor_id, $db) {
    $query = "SELECT v.plano_id, p.limite_total_anuncios, a.unidades_extras
              FROM vendedores v
              JOIN planos p ON v.plano_id = p.id
              LEFT JOIN vendedor_assinaturas a ON v.id = a.vendedor_id AND a.status = 'active'
              WHERE v.id = :vendedor_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':vendedor_id', $vendedor_id);
    $stmt->execute();
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dados) {
        return 1;
    }
    
    $limite = $dados['limite_total_anuncios'];
    if ($dados['plano_id'] == 5) {
        $limite += $dados['unidades_extras'] ?? 0;
    }
    
    return $limite;
}