<?php
// src/comprador/deletar_conta.php
// Exclusão da conta do comprador: os dados pessoais são anonimizados e os
// documentos apagados (ver src/functions/conta_functions.php).
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../functions/conta_functions.php';

excluirContaDaSessao('comprador');
