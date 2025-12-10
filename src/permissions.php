<?php
// src/permissions.php

function verificarPermissao($acaoPermitidaPara = 'ativo') {
    if (!isset($_SESSION['usuario_status'])) {
        return false;
    }
    
    $status = $_SESSION['usuario_status'];
    $tipo = $_SESSION['usuario_tipo'];
    
    // Se ação é permitida apenas para ativos
    if ($acaoPermitidaPara === 'ativo') {
        return $status === 'ativo';
    }
    
    // Se ação é permitida para pendentes também
    if ($acaoPermitidaPara === 'ambos') {
        return $status === 'ativo' || $status === 'pendente';
    }
    
    return false;
}

function restringirAcesso($pagina, $statusPermitido = 'ativo') {
    if (!verificarPermissao($statusPermitido)) {
        $_SESSION['erro_acesso'] = "Você precisa ter sua conta aprovada para acessar esta funcionalidade.";
        header("Location: ../index.php");
        exit();
    }
}

// Função para verificar se usuário pode interagir
function usuarioPodeComprar() {
    if (!isset($_SESSION['usuario_status']) || $_SESSION['usuario_status'] !== 'ativo') {
        return false;
    }
    return true;
}

// Função para verificar se usuário pode conversar
function usuarioPodeConversar() {
    if (!isset($_SESSION['usuario_status']) || $_SESSION['usuario_status'] !== 'ativo') {
        return false;
    }
    return true;
}
?>