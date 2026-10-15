<?php
// src/functions/conta_functions.php
// Exclusão de conta (LGPD art. 18, VI) e limpeza de cadastros rejeitados.
//
// Por que ANONIMIZAR em vez de apagar a linha:
// as chaves estrangeiras de chat_conversas, chat_mensagens e propostas usam
// ON DELETE CASCADE para usuarios. Um DELETE apagaria também as conversas e
// negociações da OUTRA parte (comprador/vendedor/transportador), que têm
// direito a manter esse histórico. Por isso a linha fica, mas sem nenhum dado
// que identifique a pessoa, e os arquivos de documento/foto são apagados.
//
// O que é mantido (obrigação legal / direito de terceiros - LGPD art. 16):
// - conteúdo de chats e propostas (histórico da outra parte)
// - log_acessos (Marco Civil, 6 meses; apagado pelo cron_limpar_banco)
// - cidade/estado do perfil (não identificam a pessoa sozinhos)
// - stripe_customer_id do vendedor (registro fiscal da cobrança)

const NOME_ANONIMIZADO = 'Usuário removido';

/**
 * Colunas existentes de uma tabela (vazio se a tabela não existir).
 * Permite que o código funcione mesmo se prod tiver um schema um pouco
 * diferente do repositório.
 */
function contaColunas(PDO $db, string $tabela): array {
    static $cache = [];
    if (!isset($cache[$tabela])) {
        $stmt = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$tabela]);
        $cache[$tabela] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $cache[$tabela];
}

/**
 * UPDATE só com as colunas que existem na tabela.
 * $valores: coluna => valor. $where: SQL com placeholders posicionais.
 */
function contaAtualizar(PDO $db, string $tabela, array $valores, string $where, array $params): int {
    $colunas = contaColunas($db, $tabela);
    if (!$colunas) {
        return 0;
    }
    $sets = [];
    $binds = [];
    foreach ($valores as $coluna => $valor) {
        if (in_array($coluna, $colunas, true)) {
            $sets[] = "`$coluna` = ?";
            $binds[] = $valor;
        }
    }
    if (!$sets) {
        return 0;
    }
    $stmt = $db->prepare("UPDATE `$tabela` SET " . implode(', ', $sets) . " WHERE $where");
    $stmt->execute(array_merge($binds, $params));
    return $stmt->rowCount();
}

/** DELETE que ignora tabela inexistente. */
function contaApagarLinhas(PDO $db, string $tabela, string $where, array $params): int {
    if (!contaColunas($db, $tabela)) {
        return 0;
    }
    $stmt = $db->prepare("DELETE FROM `$tabela` WHERE $where");
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Apaga um arquivo enviado pelo usuário, aceitando os formatos de caminho que
 * o sistema já gravou no banco ("uploads/...", "../uploads/...", "../../uploads/...").
 * Só apaga se o arquivo estiver dentro de public_html/uploads ou public_html/src/uploads.
 */
function contaApagarArquivo(?string $caminho): bool {
    if (!$caminho || preg_match('#^https?://#i', $caminho)) {
        return false;
    }
    $publicHtml = realpath(__DIR__ . '/../..');
    $raizesPermitidas = array_filter([
        realpath($publicHtml . '/uploads'),
        realpath($publicHtml . '/src/uploads'),
    ]);

    $relativo = ltrim(preg_replace('#^(\.\./|\./)+#', '', str_replace('\\', '/', $caminho)), '/');
    foreach ([$publicHtml . '/' . $relativo, $publicHtml . '/src/' . $relativo] as $candidato) {
        $real = realpath($candidato);
        if (!$real || !is_file($real)) {
            continue;
        }
        foreach ($raizesPermitidas as $raiz) {
            if (strpos($real, $raiz . DIRECTORY_SEPARATOR) === 0) {
                return @unlink($real);
            }
        }
    }
    return false;
}

/**
 * Anonimiza a conta de um usuário (comprador, vendedor ou transportador).
 * Retorna ['nome' => ..., 'email' => ...] com os dados ORIGINAIS (para o
 * e-mail de confirmação) ou lança exceção. Não cancela Stripe: isso fica com
 * o chamador, porque depende de chamada externa.
 */
function anonimizarConta(PDO $db, int $usuarioId): array {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) {
        throw new Exception("Usuário $usuarioId não encontrado");
    }
    if ($usuario['tipo'] === 'admin') {
        throw new Exception('Conta de administrador não pode ser anonimizada por aqui');
    }

    // Arquivos a apagar depois do commit (se a transação falhar, nada some)
    $arquivos = [
        $usuario['foto_rosto'] ?? null,
        $usuario['foto_documento_frente'] ?? null,
        $usuario['foto_documento_verso'] ?? null,
    ];

    $perfis = [
        'comprador'     => 'compradores',
        'vendedor'      => 'vendedores',
        'transportador' => 'transportadores',
    ];
    $tabelaPerfil = $perfis[$usuario['tipo']] ?? null;
    $perfil = null;
    if ($tabelaPerfil) {
        $stmt = $db->prepare("SELECT * FROM `$tabelaPerfil` WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
        $perfil = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($perfil) {
            $arquivos[] = $perfil['foto_perfil_url'] ?? null;
        }
    }

    $iniciouTransacao = !$db->inTransaction();
    if ($iniciouTransacao) {
        $db->beginTransaction();
    }

    try {
        // 1. Usuário: sem nome, e-mail, senha utilizável nem documentos
        contaAtualizar($db, 'usuarios', [
            'nome'                  => NOME_ANONIMIZADO,
            'email'                 => 'removido' . $usuarioId . '@anonimizado.invalid',
            'senha'                 => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'status'                => 'inativo',
            'reset_token'           => null,
            'reset_token_expira'    => null,
            'foto_rosto'            => null,
            'foto_documento_frente' => null,
            'foto_documento_verso'  => null,
            'tentativas_falhas'     => 0,
            'bloqueado_ate'         => null,
        ], 'id = ?', [$usuarioId]);

        // 2. Perfil: tudo que identifica (documento, endereço, contato, veículo)
        if ($tabelaPerfil && $perfil) {
            $dadosPerfil = [
                'nome_comercial'    => NOME_ANONIMIZADO,
                'razao_social'      => null,
                'foto_perfil_url'   => null,
                'cpf_cnpj'          => '', // coluna NOT NULL
                'cip'               => null,
                'cep'               => null,
                'rua'               => null,
                'numero'            => null,
                'complemento'       => null,
                'telefone'          => null,
                'telefone1'         => null,
                'telefone2'         => null,
                'antt'              => null,
                'numero_antt'       => null,
                'placa_veiculo'     => null,
                'modelo_veiculo'    => null,
                'descricao_veiculo' => null,
            ];
            if ($tabelaPerfil === 'vendedores') {
                $dadosPerfil['plano_id'] = 1;
                $dadosPerfil['status_assinatura'] = 'inativo';
                $dadosPerfil['stripe_subscription_id'] = null;
                $dadosPerfil['data_vencimento_assinatura'] = null;
            }
            contaAtualizar($db, $tabelaPerfil, $dadosPerfil, 'id = ?', [$perfil['id']]);

            // Anúncios saem do ar, mas continuam existindo para o histórico das propostas
            if ($tabelaPerfil === 'vendedores') {
                contaAtualizar($db, 'produtos', ['status' => 'inativo'], 'vendedor_id = ?', [$perfil['id']]);
            }
            if ($tabelaPerfil === 'transportadores') {
                contaApagarLinhas($db, 'transportador_favoritos', 'transportador_id = ?', [$perfil['id']]);
            }
        }

        // 3. Solicitação de cadastro: o dados_json guarda CPF, endereço, telefone etc.
        contaAtualizar($db, 'solicitacoes_cadastro', [
            'nome'       => NOME_ANONIMIZADO,
            'email'      => null,
            'telefone'   => null,
            'endereco'   => null,
            'dados_json' => '{"anonimizado":true}',
        ], 'usuario_id = ? OR email = ?', [$usuarioId, $usuario['email']]);

        // 4. Negociações em aberto são canceladas (a outra parte não fica esperando)
        if (in_array('status', contaColunas($db, 'propostas'), true)) {
            $db->prepare("UPDATE propostas SET status = 'cancelada'
                          WHERE (comprador_id = ? OR vendedor_id = ?)
                            AND status IN ('negociacao', 'assinando')")
               ->execute([$usuarioId, $usuarioId]);
        }

        // 5. Dados que só interessam ao próprio usuário
        contaApagarLinhas($db, 'notificacoes', 'usuario_id = ?', [$usuarioId]);
        contaApagarLinhas($db, 'favoritos', 'usuario_id = ?', [$usuarioId]);
        contaApagarLinhas($db, 'usuario_avisos_preferencias', 'usuario_id = ?', [$usuarioId]);

        if ($iniciouTransacao) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($iniciouTransacao && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    foreach ($arquivos as $arquivo) {
        contaApagarArquivo($arquivo);
    }

    return ['nome' => $usuario['nome'], 'email' => $usuario['email']];
}

/**
 * Fluxo comum das páginas deletar_conta.php dos três painéis.
 * O ID vem SEMPRE da sessão (nunca do POST). $antesDeAnonimizar recebe
 * ($db, $usuarioId) e serve para passos externos, como cancelar o Stripe.
 */
function excluirContaDaSessao(string $tipoEsperado, ?callable $antesDeAnonimizar = null): void {
    if (($_SESSION['usuario_tipo'] ?? null) !== $tipoEsperado || empty($_SESSION['usuario_id'])) {
        header('Location: ../login?erro=' . urlencode('Acesso restrito'));
        exit();
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: perfil?erro=' . urlencode('Método inválido'));
        exit();
    }

    $usuarioId = (int)$_SESSION['usuario_id'];
    $db = (new Database())->getConnection();

    try {
        if ($antesDeAnonimizar) {
            $antesDeAnonimizar($db, $usuarioId);
        }
        $original = anonimizarConta($db, $usuarioId);
    } catch (Throwable $e) {
        error_log("Erro ao excluir conta ($tipoEsperado, usuario $usuarioId): " . $e->getMessage());
        header('Location: perfil?erro=' . urlencode('Erro ao excluir a conta. Tente novamente ou fale com privacidade@encontreocampo.com.br.'));
        exit();
    }

    // Confirmação para o e-mail original (já não existe mais no banco)
    $arquivoEmail = __DIR__ . '/../../includes/send_notification.php';
    if (is_file($arquivoEmail)) {
        require_once $arquivoEmail;
        if (function_exists('enviarEmailNotificacao')) {
            try {
                enviarEmailNotificacao(
                    $original['email'],
                    $original['nome'],
                    'Conta excluída - Encontre o Campo',
                    'Sua conta foi excluída e seus dados pessoais (nome, e-mail, documentos, endereço e telefones) foram removidos da plataforma. '
                    . 'Mantemos apenas o que a lei exige: registros de acesso por 6 meses (Marco Civil da Internet) e o histórico das negociações, '
                    . 'que também pertence às outras partes envolvidas, já sem os seus dados de identificação.'
                );
            } catch (Throwable $e) {
                error_log('Falha ao enviar e-mail de exclusão de conta: ' . $e->getMessage());
            }
        }
    }

    $_SESSION = [];
    session_destroy();
    header('Location: ../../index?conta_excluida=1');
    exit();
}

/**
 * Remove os dados de um cadastro REJEITADO (usado pelo cron).
 * O usuário nunca chegou a ser ativo, então não existe histórico de terceiros:
 * a linha de usuarios é apagada (cascade leva o perfil junto) e o e-mail fica
 * livre para um novo cadastro. A solicitação fica, anonimizada, como registro
 * de que houve uma análise.
 * Retorna quantos arquivos foram apagados.
 */
function limparCadastroRejeitado(PDO $db, array $solicitacao): int {
    $arquivos = [];
    $usuarioId = $solicitacao['usuario_id'] ? (int)$solicitacao['usuario_id'] : null;
    $usuarioApagado = false;

    $db->beginTransaction();
    try {
        if ($usuarioId) {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$usuarioId]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Só mexe em quem continua pendente: se o admin aprovou depois por
            // outra solicitação, a conta é de verdade e não pode ser apagada.
            if ($usuario && $usuario['status'] === 'pendente' && $usuario['tipo'] !== 'admin') {
                $arquivos = [
                    $usuario['foto_rosto'] ?? null,
                    $usuario['foto_documento_frente'] ?? null,
                    $usuario['foto_documento_verso'] ?? null,
                ];
                try {
                    $db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$usuarioId]);
                    $usuarioApagado = true;
                } catch (PDOException $e) {
                    // Alguma FK sem cascade: cai para a anonimização
                    $usuarioApagado = false;
                }
                if (!$usuarioApagado) {
                    anonimizarConta($db, $usuarioId); // usa a transação já aberta
                    $arquivos = [];                    // anonimizarConta já apagou
                }
            }
        }

        contaAtualizar($db, 'solicitacoes_cadastro', [
            'nome'       => NOME_ANONIMIZADO,
            'email'      => null,
            'telefone'   => null,
            'endereco'   => null,
            'dados_json' => '{"anonimizado":true}',
        ], 'id = ?', [$solicitacao['id']]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    $apagados = 0;
    foreach ($arquivos as $arquivo) {
        if (contaApagarArquivo($arquivo)) {
            $apagados++;
        }
    }
    return $apagados;
}
