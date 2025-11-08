<?php
session_start();
require_once '../conexao.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    $solicitacao_id = sanitizeInput($_POST['solicitacao_id']);
    $usuario_id = sanitizeInput($_POST['usuario_id']);
    $tipo = sanitizeInput($_POST['tipo']);
    $dados_json = $_POST['dados_json'];
    $acao = sanitizeInput($_POST['acao']);

    try {
        $db->beginTransaction();

        if ($acao === 'aprovar') {
            // Atualizar status do usuário para ativo
            $query = "UPDATE usuarios SET status = 'ativo', data_aprovacao = NOW() WHERE id = :usuario_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario_id', $usuario_id);
            $stmt->execute();

            // Inserir dados específicos na tabela correspondente
            $dados = json_decode($dados_json, true);
            
            if ($tipo === 'comprador') {
                $query = "INSERT INTO compradores (usuario_id, nome_comercial, cpf_cnpj, cip, cep, rua, numero, complemento, estado, cidade, telefone1, telefone2, plano) 
                          VALUES (:usuario_id, :nome_comercial, :cpf_cnpj, :cip, :cep, :rua, :numero, :complemento, :estado, :cidade, :telefone1, :telefone2, :plano)";
            } elseif ($tipo === 'vendedor') {
                $query = "INSERT INTO vendedores (usuario_id, nome_comercial, cpf_cnpj, cip, cep, rua, numero, complemento, estado, cidade, telefone1, telefone2, plano) 
                          VALUES (:usuario_id, :nome_comercial, :cpf_cnpj, :cip, :cep, :rua, :numero, :complemento, :estado, :cidade, :telefone1, :telefone2, :plano)";
            } elseif ($tipo === 'transportador') {
                $query = "INSERT INTO transportadores (usuario_id, telefone, antt, numero_antt, placa_veiculo, modelo_veiculo, descricao_veiculo, estado, cidade) 
                          VALUES (:usuario_id, :telefone, :antt, :numero_antt, :placa_veiculo, :modelo_veiculo, :descricao_veiculo, :estado, :cidade)";
            }

            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario_id', $usuario_id);
            
            foreach ($dados as $chave => $valor) {
                $stmt->bindValue(':' . $chave, $valor);
            }
            $stmt->execute();

            $status_solicitacao = 'aprovado';
        } else {
            $status_solicitacao = 'rejeitado';
        }

        // Atualizar solicitação
        $query = "UPDATE solicitacoes_cadastro SET status = :status, data_analise = NOW(), admin_responsavel = :admin_id 
                  WHERE id = :solicitacao_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status_solicitacao);
        $stmt->bindParam(':admin_id', $_SESSION['usuario_id']);
        $stmt->bindParam(':solicitacao_id', $solicitacao_id);
        $stmt->execute();

        // Registrar ação do admin
        $acao_descricao = $acao === 'aprovar' ? "Aprovou cadastro de " . $tipo : "Rejeitou cadastro de " . $tipo;
        $query = "INSERT INTO admin_acoes (admin_id, acao, tabela_afetada, registro_id) 
                  VALUES (:admin_id, :acao, 'solicitacoes_cadastro', :registro_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':admin_id', $_SESSION['usuario_id']);
        $stmt->bindParam(':acao', $acao_descricao);
        $stmt->bindParam(':registro_id', $solicitacao_id);
        $stmt->execute();

        $db->commit();

        $_SESSION['sucesso'] = "Solicitação " . ($acao === 'aprovar' ? "aprovada" : "rejeitada") . " com sucesso!";
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['erro'] = "Erro ao processar solicitação: " . $e->getMessage();
    }

    header("Location: dashboard.php");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>