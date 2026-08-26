<?php
// src/permissions.php (adicione esta função ou ajuste as existentes)

// Função específica para verificar permissão do vendedor
function vendedorPode($acao) {
    if (!isset($_SESSION['usuario_status']) || !isset($_SESSION['usuario_tipo'])) {
        return false;
    }
    
    $status = $_SESSION['usuario_status'];
    $tipo = $_SESSION['usuario_tipo'];
    
    // Verifica se é vendedor
    if ($tipo !== 'vendedor') {
        return false;
    }
    
    // Lista de ações permitidas para vendedores pendentes
    $acoesPermitidasPendentes = [
        'ver_anuncios',
        'favoritar',
        'ver_perfil',
        'editar_perfil'
    ];
    
    // Se for ativo, todas ações são permitidas
    if ($status === 'ativo') {
        return true;
    }
    
    // Se for pendente, verifica se a ação está na lista permitida
    if ($status === 'pendente') {
        return in_array($acao, $acoesPermitidasPendentes);
    }
    
    return false;
}
?>