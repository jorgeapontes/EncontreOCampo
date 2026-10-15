<?php
// src/transportador/deletar_conta.php
// Exclusão da conta do transportador: os dados pessoais e do veículo são
// anonimizados e os documentos apagados (ver src/functions/conta_functions.php).
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../functions/conta_functions.php';

excluirContaDaSessao('transportador');
