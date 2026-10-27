-- Migration: Usuário master, trilha de auditoria e monitoramento
-- Data: 2026-10-27
-- Descrição:
--   - tipo 'master' em usuarios: dono do sistema, entra só em src/master
--     (monitoramento). Não aparece nas telas do admin e não pode ser alterado por elas.
--   - logs_auditoria: quem fez o quê, em qual registro e quando (ações sensíveis).
--     Guardada por 1 ano; o cron_limpar_banco faz a limpeza.
--   - cron_execucoes: histórico de cada execução de cron (resultado, alertas,
--     tamanhos do banco/uploads/backups). É o que a tela de Saúde mostra.
--   - configuracoes_site: chave/valor (e-mail de alerta, limite de disco do plano).
-- Pode rodar mais de uma vez: tudo usa IF NOT EXISTS / WHERE NOT EXISTS.

ALTER TABLE `usuarios`
  MODIFY `tipo` ENUM('admin','vendedor','comprador','transportador','master') NOT NULL;

CREATE TABLE IF NOT EXISTS `logs_auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data_hora` datetime NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nome` varchar(100) DEFAULT NULL,
  `perfil` varchar(20) DEFAULT NULL,
  `acao` varchar(40) NOT NULL,
  `entidade` varchar(30) DEFAULT NULL,
  `entidade_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_data` (`data_hora`),
  KEY `idx_acao` (`acao`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cron_execucoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cron` varchar(50) NOT NULL,
  `data_hora` datetime NOT NULL,
  `duracao_segundos` int(11) NOT NULL DEFAULT 0,
  `status` varchar(10) NOT NULL,
  `resumo` text DEFAULT NULL,
  `alertas` text DEFAULT NULL,
  `email_enviado` tinyint(1) NOT NULL DEFAULT 0,
  `tamanho_banco` bigint(20) DEFAULT NULL,
  `tamanho_uploads` bigint(20) DEFAULT NULL,
  `tamanho_backups` bigint(20) DEFAULT NULL,
  `ultimo_backup` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_data` (`data_hora`),
  KEY `idx_cron` (`cron`, `data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `configuracoes_site` (
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Conta master. Só é criada se o e-mail ainda não existir: se já houver um
-- usuário com esse e-mail, NADA é alterado (para não estragar uma conta real).
-- A senha está só em hash (bcrypt); troque em Monitoramento > Configurações.
INSERT INTO `usuarios` (`email`, `senha`, `tipo`, `nome`, `status`, `data_aprovacao`, `tentativas_falhas`)
SELECT 'jorgeappontes13@gmail.com',
       '$2y$12$hUGtWS7uYk4Otr.pZ1UZfO4bfiUUE8W1R6k41iQP0Pg8uv7C16VjO',
       'master', 'Jorge', 'ativo', NOW(), 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `usuarios` WHERE `email` = 'jorgeappontes13@gmail.com');

-- Confere o resultado: deve listar uma linha com tipo 'master'.
-- Se o tipo vier diferente, o e-mail já era de outro usuário e o master NÃO foi criado.
SELECT `id`, `email`, `tipo`, `status` FROM `usuarios` WHERE `email` = 'jorgeappontes13@gmail.com';
