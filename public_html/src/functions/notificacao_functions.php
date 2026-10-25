<?php
// src/functions/notificacao_functions.php
// Limpeza de notificações que apontam para algo que deixou de existir.
//
// A tabela notificacoes só tem FK para usuarios (as do próprio usuário somem
// com ele). O vínculo com anúncio/proposta existe só dentro da URL, então ao
// apagar um anúncio as notificações de OUTRAS pessoas sobre ele ficariam com
// link quebrado (conversas, propostas e entregas do anúncio somem em cascata).

/**
 * Apaga as notificações que levam a um anúncio que vai ser excluído:
 * URLs com produto_id=/anuncio_id= do produto (chat, avaliação) e
 * detalhes_proposta?id= das propostas dele.
 * Chamar ANTES do DELETE do produto (as propostas ainda precisam existir).
 * Retorna quantas notificações foram apagadas.
 */
function excluirNotificacoesDoProduto(PDO $db, int $produtoId): int {
    $stmt = $db->prepare("SELECT ID FROM propostas WHERE produto_id = ?");
    $stmt->execute([$produtoId]);
    $propostas = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    // Os ids entram no padrão já convertidos para inteiro
    $padroes = ['[?&](produto_id|anuncio_id)=' . $produtoId . '([^0-9]|$)'];
    foreach (array_chunk($propostas, 200) as $lote) {
        $padroes[] = 'detalhes_proposta(\\.php)?\\?id=(' . implode('|', $lote) . ')([^0-9]|$)';
    }

    $apagadas = 0;
    $delete = $db->prepare("DELETE FROM notificacoes WHERE url REGEXP ?");
    foreach ($padroes as $padrao) {
        $delete->execute([$padrao]);
        $apagadas += $delete->rowCount();
    }
    return $apagadas;
}
