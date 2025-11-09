<?php
// src/admin/processar_admin_acao.php
session_start();

// Redireciona se não for um admin logado
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Incluir a conexão
require_once __DIR__ . '/../conexao.php'; 

// Iniciar conexão com o Banco de Dados
$database = new Database();
$conn = $database->getConnection();

// Verificar se a conexão falhou
if (!$conn) {
    header('Location: dashboard.php?msg=' . urlencode('Erro fatal: Falha na conexão com o Banco de Dados.'));
    exit;
}

// 1. COLETAR E VALIDAR DADOS
$solicitacao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$acao = filter_input(INPUT_GET, 'acao', FILTER_SANITIZE_STRING);
$admin_id = $_SESSION['usuario_id'] ?? 1; 

if ($solicitacao_id === null || $solicitacao_id === false || !in_array($acao, ['aprovar', 'rejeitar'])) {
    header('Location: dashboard.php?msg=' . urlencode('Erro: Dados de solicitação inválidos.'));
    exit;
}

$novo_status = ($acao === 'aprovar') ? 'aprovado' : 'rejeitado';


// 2. FUNÇÃO DE LIMPEZA E MAPEAMENTO (Com correção para ANTT)
function map_data_to_columns($data, $tipo_usuario) {
    $map = [];
    $prefix = ['Comprador', 'Vendedor', 'Transportador'];
    $exclude_keys = ['senha_hash', 'message']; 

    foreach ($data as $key => $value) {
        if (in_array($key, $exclude_keys) || empty($value)) {
            continue;
        }

        // 1. Remove prefixos (Ex: 'ruaComprador' -> 'rua')
        $cleaned_key = $key;
        foreach ($prefix as $p) {
            // Usa str_ireplace para ser insensível a maiúsculas/minúsculas no prefixo
            $cleaned_key = str_ireplace($p, '', $cleaned_key);
        }
        
        // 2. Converte de camelCase para snake_case
        $cleaned_key = preg_replace('/(?<!^)([A-Z])/', '_$1', $cleaned_key);
        $cleaned_key = strtolower($cleaned_key);
        
        // 🎯 CORREÇÃO ANTT: Força a coluna a ter o nome correto, assumindo 'numero_antt' no BD.
        if (strpos($cleaned_key, 'a_n_t_t') !== false || strpos($cleaned_key, 'antt') !== false) {
             // O campo é relacionado à ANTT. Assumimos o nome de coluna 'numero_antt'.
             // Se sua coluna for apenas 'antt', você deve mudar para $cleaned_key = 'antt';
             $cleaned_key = 'numero_antt'; 
        }

        $map[$cleaned_key] = $value;
    }
    
    return $map;
}


// 3. INÍCIO DA TRANSAÇÃO
try {
    $conn->beginTransaction();

    // ... (Código para REJEITAR permanece o mesmo) ...
    if ($acao === 'rejeitar') {
        $sql_update = "UPDATE solicitacoes_cadastro SET status = :status, data_analise = NOW() WHERE id = :id AND status = 'pendente'";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bindParam(':status', $novo_status);
        $stmt_update->bindParam(':id', $solicitacao_id, PDO::PARAM_INT);
        $stmt_update->execute();
        
        $conn->commit();
        header('Location: dashboard.php?msg=' . urlencode('Solicitação rejeitada com sucesso.'));
        exit;
    }


    // 3.2. Se for APROVAR:

    // 3.2.1. Busca os dados da solicitação
    $sql_fetch = "SELECT nome, email, tipo_solicitacao, dados_json FROM solicitacoes_cadastro WHERE id = :id";
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->bindParam(':id', $solicitacao_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $solicitacao = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$solicitacao) {
        throw new Exception("Solicitação de ID $solicitacao_id não encontrada ou já processada.");
    }
    
    $dados_json = json_decode($solicitacao['dados_json'], true);
    $tipo_usuario = $solicitacao['tipo_solicitacao'];
    $senha_hash = $dados_json['senha_hash'] ?? null;

    if (!$senha_hash) {
        throw new Exception("Erro: Senha criptografada não encontrada no JSON da solicitação.");
    }

    // 3.2.2. INSERIR NA TABELA 'USUARIOS'
    $sql_user = "INSERT INTO usuarios (nome, email, senha, tipo, status) 
                 VALUES (:nome, :email, :senha, :tipo, 'ativo')";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bindParam(':nome', $solicitacao['nome']);
    $stmt_user->bindParam(':email', $solicitacao['email']);
    $stmt_user->bindParam(':senha', $senha_hash);
    $stmt_user->bindParam(':tipo', $tipo_usuario);
    $stmt_user->execute();
    
    $novo_usuario_id = $conn->lastInsertId();


    // 3.2.3. INSERIR NAS TABELAS ESPECÍFICAS (Com Pluralização Correta)
    $plural_mapping = [
        'comprador' => 'compradores',
        'vendedor' => 'vendedores',
        'transportador' => 'transportadores'
    ];
    
    if (!isset($plural_mapping[$tipo_usuario])) {
        throw new Exception("Tipo de usuário inválido para aprovação: " . $tipo_usuario);
    }
    
    $tabela_especifica = $plural_mapping[$tipo_usuario];

    $dados_para_inserir = map_data_to_columns($dados_json, $tipo_usuario);
    $dados_para_inserir['usuario_id'] = $novo_usuario_id;
    
    // Constrói a query de forma dinâmica
    $colunas = implode(', ', array_keys($dados_para_inserir));
    $placeholders = implode(', ', array_fill(0, count($dados_para_inserir), '?'));
    $valores = array_values($dados_para_inserir);
    
    $sql_especifico = "INSERT INTO {$tabela_especifica} ({$colunas}) VALUES ({$placeholders})";
    $stmt_especifico = $conn->prepare($sql_especifico);
    $stmt_especifico->execute($valores);


    // 3.2.4. ATUALIZAR STATUS DA SOLICITAÇÃO
    $sql_update_sol = "UPDATE solicitacoes_cadastro SET status = :status, data_analise = NOW(), usuario_id = :usuario_id 
                       WHERE id = :id AND status = 'pendente'";
    $stmt_update_sol = $conn->prepare($sql_update_sol);
    $stmt_update_sol->bindParam(':status', $novo_status);
    $stmt_update_sol->bindParam(':usuario_id', $novo_usuario_id, PDO::PARAM_INT);
    $stmt_update_sol->bindParam(':id', $solicitacao_id, PDO::PARAM_INT);
    $stmt_update_sol->execute();
    
    
    // 3.2.5. REGISTRAR AÇÃO DO ADMIN 
    $acao_desc = "Aprovou cadastro de $tipo_usuario (ID: $novo_usuario_id)";
    $sql_acao = "INSERT INTO admin_acoes (admin_id, acao, tabela_afetada, registro_id) 
                 VALUES (:admin_id, :acao, 'usuarios', :registro_id)";
    $stmt_acao = $conn->prepare($sql_acao);
    $stmt_acao->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt_acao->bindParam(':acao', $acao_desc);
    $stmt_acao->bindParam(':registro_id', $novo_usuario_id, PDO::PARAM_INT);
    $stmt_acao->execute();


    // 3.2.6. FINALIZAR TRANSAÇÃO
    $conn->commit();
    header('Location: dashboard.php?msg=' . urlencode('Solicitação aprovada e usuário criado com sucesso!'));


} catch (Exception $e) {
    // 4. EM CASO DE ERRO, REVERTER TUDO
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $erro_msg = "Erro ao processar aprovação: " . $e->getMessage();
    header('Location: dashboard.php?msg=' . urlencode('erro_processamento: ' . $erro_msg));
}

exit;
?>