<?php
// src/comprador/editar_perfil.php

session_start();
require_once __DIR__ . '/../conexao.php'; 

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'comprador') {
    header("Location: ../login.php?erro=" . urlencode("Acesso restrito. Faça login como Comprador."));
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = htmlspecialchars($_SESSION['usuario_nome'] ?? 'Comprador');

$database = new Database();
$conn = $database->getConnection();
$comprador_data = null;
$mensagem_sucesso = isset($_GET['sucesso']) ? htmlspecialchars($_GET['sucesso']) : null;
$mensagem_erro = isset($_GET['erro']) ? htmlspecialchars($_GET['erro']) : null;

// 2. BUSCAR DADOS DO COMPRADOR
try {
    $sql = "SELECT 
                c.*,
                u.email,
                u.nome as nome_usuario
            FROM compradores c
            JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.usuario_id = :usuario_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $comprador_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comprador_data) {
        die("Perfil de comprador não encontrado.");
    }

} catch (PDOException $e) {
    die("Erro ao carregar perfil: " . $e->getMessage());
}

// 3. PROCESSAR ATUALIZAÇÃO SE FOR POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coletar e validar dados do formulário
    $nome_comercial = filter_input(INPUT_POST, 'nome_comercial', FILTER_SANITIZE_STRING);
    $cep = filter_input(INPUT_POST, 'cep', FILTER_SANITIZE_STRING);
    $rua = filter_input(INPUT_POST, 'rua', FILTER_SANITIZE_STRING);
    $numero = filter_input(INPUT_POST, 'numero', FILTER_SANITIZE_STRING);
    $complemento = filter_input(INPUT_POST, 'complemento', FILTER_SANITIZE_STRING);
    $estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_STRING);
    $cidade = filter_input(INPUT_POST, 'cidade', FILTER_SANITIZE_STRING);
    $telefone1 = filter_input(INPUT_POST, 'telefone1', FILTER_SANITIZE_STRING);
    $telefone2 = filter_input(INPUT_POST, 'telefone2', FILTER_SANITIZE_STRING);
    $plano = filter_input(INPUT_POST, 'plano', FILTER_SANITIZE_STRING);
    $nome_usuario = filter_input(INPUT_POST, 'nome_usuario', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    // Validações básicas
    if (!$nome_usuario || !$email) {
        header("Location: editar_perfil.php?erro=" . urlencode("Nome e email são obrigatórios."));
        exit();
    }

    try {
        // Iniciar transação
        $conn->beginTransaction();

        // Atualizar tabela usuarios
        $sql_usuario = "UPDATE usuarios SET nome = :nome, email = :email WHERE id = :usuario_id";
        $stmt_usuario = $conn->prepare($sql_usuario);
        $stmt_usuario->bindParam(':nome', $nome_usuario);
        $stmt_usuario->bindParam(':email', $email);
        $stmt_usuario->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_usuario->execute();

        // Atualizar tabela compradores
        $sql_comprador = "UPDATE compradores SET 
                            nome_comercial = :nome_comercial,
                            cep = :cep,
                            rua = :rua,
                            numero = :numero,
                            complemento = :complemento,
                            estado = :estado,
                            cidade = :cidade,
                            telefone1 = :telefone1,
                            telefone2 = :telefone2,
                            plano = :plano
                          WHERE usuario_id = :usuario_id";
        
        $stmt_comprador = $conn->prepare($sql_comprador);
        $stmt_comprador->bindValue(':nome_comercial', empty($nome_comercial) ? null : $nome_comercial, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':cep', empty($cep) ? null : $cep, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':rua', empty($rua) ? null : $rua, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':numero', empty($numero) ? null : $numero, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':complemento', empty($complemento) ? null : $complemento, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':estado', empty($estado) ? null : $estado, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':cidade', empty($cidade) ? null : $cidade, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':telefone1', empty($telefone1) ? null : $telefone1, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':telefone2', empty($telefone2) ? null : $telefone2, PDO::PARAM_STR);
        $stmt_comprador->bindValue(':plano', $plano, PDO::PARAM_STR);
        $stmt_comprador->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $stmt_comprador->execute();

        // Commit da transação
        $conn->commit();

        // Atualizar sessão com novo nome
        $_SESSION['usuario_nome'] = $nome_usuario;

        header("Location: perfil.php?sucesso=" . urlencode("Perfil atualizado com sucesso!"));
        exit();

    } catch (PDOException $e) {
        // Rollback em caso de erro
        $conn->rollBack();
        error_log("Erro ao atualizar perfil: " . $e->getMessage());
        header("Location: editar_perfil.php?erro=" . urlencode("Erro ao atualizar perfil. Tente novamente."));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil - Comprador</title>
    <link rel="stylesheet" href="../../index.css"> 
    <link rel="stylesheet" href="../css/comprador/comprador.css">
    <link rel="stylesheet" href="../css/comprador/editar_perfil.css">
    <link rel="shortcut icon" href="../../img/logo-nova.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <h1>ENCONTRE</h1>
                <h2>OCAMPO</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="../anuncios.php" class="nav-link">Ver Anúncios</a></li>
                <li class="nav-item"><a href="minhas_propostas.php" class="nav-link">Minhas Propostas</a></li>
                <li class="nav-item"><a href="perfil.php" class="nav-link">Meu Perfil</a></li>
                <li class="nav-item"><a href="editar_perfil.php" class="nav-link active">Editar Perfil</a></li>
                <li class="nav-item"><a href="../logout.php" class="nav-link logout">Sair</a></li>
            </ul>
        </div>
    </nav>

    <main class="container editar-container">
        <div class="page-header">
            <h1>Editar Perfil</h1>
            <p class="page-subtitle">Atualize suas informações pessoais e de negócio</p>
        </div>

        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $mensagem_sucesso; ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-times-circle"></i>
                <?php echo $mensagem_erro; ?>
            </div>
        <?php endif; ?>

        <div class="editar-card">
            <div class="editar-header">
                <h2><i class="fas fa-user-edit"></i> Editar Informações</h2>
                <p>Atualize seus dados abaixo (CPF/CNPJ não pode ser alterado)</p>
            </div>

            <div class="editar-body">
                <form action="editar_perfil.php" method="POST">
                    <div class="form-section">
                        <h3><i class="fas fa-id-card"></i> Informações Pessoais</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome_usuario" class="required">Nome Completo</label>
                                <input type="text" id="nome_usuario" name="nome_usuario" 
                                       value="<?php echo htmlspecialchars($comprador_data['nome_usuario']); ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="email" class="required">Email</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($comprador_data['email']); ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="cpf_cnpj">CPF/CNPJ</label>
                                <input type="text" id="cpf_cnpj" name="cpf_cnpj" 
                                       value="<?php echo htmlspecialchars($comprador_data['cpf_cnpj']); ?>" 
                                       readonly style="background-color: #f8f9fa;">
                                <small style="color: #6c757d; font-size: 0.9em;">CPF/CNPJ não pode ser alterado</small>
                            </div>
                            <div class="form-group">
                                <label for="nome_comercial">Nome Comercial (Opcional)</label>
                                <input type="text" id="nome_comercial" name="nome_comercial" 
                                       value="<?php echo htmlspecialchars($comprador_data['nome_comercial'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-map-marker-alt"></i> Endereço</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="cep">CEP</label>
                                <input type="text" id="cep" name="cep" 
                                       value="<?php echo htmlspecialchars($comprador_data['cep'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="rua">Rua</label>
                                <input type="text" id="rua" name="rua" 
                                       value="<?php echo htmlspecialchars($comprador_data['rua'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="numero">Número</label>
                                <input type="text" id="numero" name="numero" 
                                       value="<?php echo htmlspecialchars($comprador_data['numero'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="complemento">Complemento (Opcional)</label>
                                <input type="text" id="complemento" name="complemento" 
                                       value="<?php echo htmlspecialchars($comprador_data['complemento'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="cidade">Cidade</label>
                                <input type="text" id="cidade" name="cidade" 
                                       value="<?php echo htmlspecialchars($comprador_data['cidade'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="estado">Estado</label>
                                <select id="estado" name="estado">
                                    <option value="">Selecione...</option>
                                    <option value="AC" <?php echo ($comprador_data['estado'] ?? '') === 'AC' ? 'selected' : ''; ?>>Acre</option>
                                    <option value="AL" <?php echo ($comprador_data['estado'] ?? '') === 'AL' ? 'selected' : ''; ?>>Alagoas</option>
                                    <option value="AP" <?php echo ($comprador_data['estado'] ?? '') === 'AP' ? 'selected' : ''; ?>>Amapá</option>
                                    <option value="AM" <?php echo ($comprador_data['estado'] ?? '') === 'AM' ? 'selected' : ''; ?>>Amazonas</option>
                                    <option value="BA" <?php echo ($comprador_data['estado'] ?? '') === 'BA' ? 'selected' : ''; ?>>Bahia</option>
                                    <option value="CE" <?php echo ($comprador_data['estado'] ?? '') === 'CE' ? 'selected' : ''; ?>>Ceará</option>
                                    <option value="DF" <?php echo ($comprador_data['estado'] ?? '') === 'DF' ? 'selected' : ''; ?>>Distrito Federal</option>
                                    <option value="ES" <?php echo ($comprador_data['estado'] ?? '') === 'ES' ? 'selected' : ''; ?>>Espírito Santo</option>
                                    <option value="GO" <?php echo ($comprador_data['estado'] ?? '') === 'GO' ? 'selected' : ''; ?>>Goiás</option>
                                    <option value="MA" <?php echo ($comprador_data['estado'] ?? '') === 'MA' ? 'selected' : ''; ?>>Maranhão</option>
                                    <option value="MT" <?php echo ($comprador_data['estado'] ?? '') === 'MT' ? 'selected' : ''; ?>>Mato Grosso</option>
                                    <option value="MS" <?php echo ($comprador_data['estado'] ?? '') === 'MS' ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?php echo ($comprador_data['estado'] ?? '') === 'MG' ? 'selected' : ''; ?>>Minas Gerais</option>
                                    <option value="PA" <?php echo ($comprador_data['estado'] ?? '') === 'PA' ? 'selected' : ''; ?>>Pará</option>
                                    <option value="PB" <?php echo ($comprador_data['estado'] ?? '') === 'PB' ? 'selected' : ''; ?>>Paraíba</option>
                                    <option value="PR" <?php echo ($comprador_data['estado'] ?? '') === 'PR' ? 'selected' : ''; ?>>Paraná</option>
                                    <option value="PE" <?php echo ($comprador_data['estado'] ?? '') === 'PE' ? 'selected' : ''; ?>>Pernambuco</option>
                                    <option value="PI" <?php echo ($comprador_data['estado'] ?? '') === 'PI' ? 'selected' : ''; ?>>Piauí</option>
                                    <option value="RJ" <?php echo ($comprador_data['estado'] ?? '') === 'RJ' ? 'selected' : ''; ?>>Rio de Janeiro</option>
                                    <option value="RN" <?php echo ($comprador_data['estado'] ?? '') === 'RN' ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?php echo ($comprador_data['estado'] ?? '') === 'RS' ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?php echo ($comprador_data['estado'] ?? '') === 'RO' ? 'selected' : ''; ?>>Rondônia</option>
                                    <option value="RR" <?php echo ($comprador_data['estado'] ?? '') === 'RR' ? 'selected' : ''; ?>>Roraima</option>
                                    <option value="SC" <?php echo ($comprador_data['estado'] ?? '') === 'SC' ? 'selected' : ''; ?>>Santa Catarina</option>
                                    <option value="SP" <?php echo ($comprador_data['estado'] ?? '') === 'SP' ? 'selected' : ''; ?>>São Paulo</option>
                                    <option value="SE" <?php echo ($comprador_data['estado'] ?? '') === 'SE' ? 'selected' : ''; ?>>Sergipe</option>
                                    <option value="TO" <?php echo ($comprador_data['estado'] ?? '') === 'TO' ? 'selected' : ''; ?>>Tocantins</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-phone"></i> Contato</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="telefone1" class="required">Telefone Principal</label>
                                <input type="text" id="telefone1" name="telefone1" 
                                       value="<?php echo htmlspecialchars($comprador_data['telefone1'] ?? ''); ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="telefone2">Telefone Secundário (Opcional)</label>
                                <input type="text" id="telefone2" name="telefone2" 
                                       value="<?php echo htmlspecialchars($comprador_data['telefone2'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-crown"></i> Plano</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="plano" class="required">Plano</label>
                                <select id="plano" name="plano" required>
                                    <option value="basico" <?php echo ($comprador_data['plano'] ?? '') === 'basico' ? 'selected' : ''; ?>>Básico</option>
                                    <option value="premium" <?php echo ($comprador_data['plano'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                    <option value="empresarial" <?php echo ($comprador_data['plano'] ?? '') === 'empresarial' ? 'selected' : ''; ?>>Empresarial</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i>
                            Salvar Alterações
                        </button>
                        <a href="perfil.php" class="btn-cancel">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Máscara para telefone
        function mascaraTelefone(telefone) {
            const texto = telefone.value.replace(/\D/g, '');
            const textoAjustado = texto.replace(/^(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            telefone.value = textoAjustado;
        }

        // Máscara para CEP
        function mascaraCEP(cep) {
            const texto = cep.value.replace(/\D/g, '');
            const textoAjustado = texto.replace(/^(\d{5})(\d{3})/, '$1-$2');
            cep.value = textoAjustado;
        }

        // Aplicar máscaras
        document.addEventListener('DOMContentLoaded', function() {
            const telefone1 = document.getElementById('telefone1');
            const telefone2 = document.getElementById('telefone2');
            const cep = document.getElementById('cep');

            if (telefone1) {
                telefone1.addEventListener('input', function() { mascaraTelefone(this); });
            }
            if (telefone2) {
                telefone2.addEventListener('input', function() { mascaraTelefone(this); });
            }
            if (cep) {
                cep.addEventListener('input', function() { mascaraCEP(this); });
            }
        });
    </script>
</body>
</html>