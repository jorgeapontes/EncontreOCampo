-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 28/01/2026 às 16:07
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `encontre_ocampo`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `admin_acoes`
--

CREATE TABLE `admin_acoes` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `acao` varchar(500) NOT NULL,
  `tabela_afetada` varchar(100) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `data_acao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `admin_acoes`
--

INSERT INTO `admin_acoes` (`id`, `admin_id`, `acao`, `tabela_afetada`, `registro_id`, `data_acao`) VALUES
(1, 1, 'Aprovou cadastro de comprador (ID: 3)', 'usuarios', 3, '2025-11-09 00:23:59'),
(2, 1, 'Aprovou cadastro de vendedor (ID: 4)', 'usuarios', 4, '2025-11-09 00:28:21'),
(3, 1, 'Aprovou cadastro de transportador (ID: 7)', 'usuarios', 7, '2025-11-09 00:29:26'),
(4, 1, 'Alterou status do usuário (ID: 7) para inativo', 'usuarios', 7, '2025-11-10 14:28:45'),
(5, 1, 'Alterou status do usuário (ID: 7) para ativo', 'usuarios', 7, '2025-11-10 14:29:10'),
(6, 1, 'Alterou status do usuário (ID: 3) para inativo', 'usuarios', 3, '2025-11-10 14:39:04'),
(7, 1, 'Alterou status do usuário (ID: 3) para ativo', 'usuarios', 3, '2025-11-10 14:39:06'),
(8, 1, 'Alterou status do usuário (ID: 7) para inativo', 'usuarios', 7, '2025-11-10 14:53:29'),
(9, 1, 'Alterou status do usuário (ID: 7) para ativo', 'usuarios', 7, '2025-11-10 14:53:31'),
(10, 1, 'Aprovou cadastro de comprador (ID: 8)', 'usuarios', 8, '2025-11-17 18:55:39'),
(11, 1, 'Aprovou cadastro de vendedor (ID: 9)', 'usuarios', 9, '2025-11-20 17:40:01'),
(12, 1, 'Aprovou cadastro de vendedor (ID: 11)', 'usuarios', 11, '2025-12-02 12:49:02'),
(13, 1, 'Aprovou cadastro de vendedor (ID: 12)', 'usuarios', 12, '2025-12-02 12:52:04'),
(14, 1, 'Aprovou cadastro de vendedor (ID: 14)', 'usuarios', 14, '2025-12-04 01:15:01'),
(15, 1, 'Alterou status do usuário (ID: 15) para ativo', 'usuarios', 15, '2025-12-09 12:04:46'),
(16, 1, 'Alterou status do usuário (ID: 13) para ativo', 'usuarios', 13, '2025-12-09 12:06:08'),
(17, 1, 'Alterou status do usuário (ID: 14) para inativo', 'usuarios', 14, '2025-12-09 12:07:10'),
(18, 1, 'Alterou status do usuário (ID: 14) para ativo', 'usuarios', 14, '2025-12-09 12:07:12'),
(19, 1, 'Alterou status do usuário (ID: 14) para inativo', 'usuarios', 14, '2025-12-09 12:24:16'),
(20, 1, 'Alterou status do usuário (ID: 14) para ativo', 'usuarios', 14, '2025-12-09 12:24:20'),
(21, 1, 'Alterou status do usuário (ID: 14) para inativo', 'usuarios', 14, '2025-12-09 13:14:03'),
(22, 1, 'Alterou status do usuário (ID: 14) para ativo', 'usuarios', 14, '2025-12-09 13:14:13'),
(23, 1, 'Aprovou cadastro de vendedor (ID: 22)', 'usuarios', 22, '2026-01-08 13:48:40'),
(24, 1, 'Aprovou cadastro de comprador (ID: 23)', 'usuarios', 23, '2026-01-10 15:12:27'),
(25, 1, 'Aprovou cadastro de vendedor (ID: 24)', 'usuarios', 24, '2026-01-14 14:09:52'),
(26, 1, 'Aprovou cadastro de vendedor (ID: 25)', 'usuarios', 25, '2026-01-14 14:20:52'),
(27, 1, 'Aprovou cadastro de vendedor (ID: 26)', 'usuarios', 26, '2026-01-14 19:38:29'),
(28, 1, 'Alterou status do usuário (ID: 8) para ativo', 'usuarios', 8, '2026-01-22 16:06:03'),
(29, 1, 'Aprovou cadastro de comprador (ID: 28)', 'usuarios', 28, '2026-01-26 23:02:59'),
(30, 1, 'Aprovou cadastro de transportador (ID: 29)', 'usuarios', 29, '2026-01-28 13:35:04'),
(31, 1, 'Aprovou cadastro de comprador (ID: 30)', 'usuarios', 30, '2026-01-28 14:00:24'),
(32, 1, 'Aprovou cadastro de comprador (ID: 31)', 'usuarios', 31, '2026-01-28 14:00:26'),
(33, 1, 'Aprovou cadastro de vendedor (ID: 32)', 'usuarios', 32, '2026-01-28 14:00:48'),
(34, 1, 'Aprovou cadastro de vendedor (ID: 33)', 'usuarios', 33, '2026-01-28 14:00:50'),
(35, 1, 'Aprovou cadastro de transportador (ID: 34)', 'usuarios', 34, '2026-01-28 14:00:59'),
(36, 1, 'Aprovou cadastro de transportador (ID: 35)', 'usuarios', 35, '2026-01-28 14:01:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_auditoria`
--

CREATE TABLE `chat_auditoria` (
  `id` int(11) NOT NULL,
  `conversa_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `acao` enum('criar_conversa','enviar_mensagem','deletar_conversa','deletar_conta') NOT NULL,
  `detalhes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `data_acao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `chat_auditoria`
--

INSERT INTO `chat_auditoria` (`id`, `conversa_id`, `usuario_id`, `acao`, `detalhes`, `ip_address`, `user_agent`, `data_acao`) VALUES
(1, 47, 30, 'enviar_mensagem', 'Mensagem ID: 58 - Conteúdo: quero', NULL, NULL, '2026-01-28 14:03:33'),
(2, 47, 30, 'enviar_mensagem', 'Mensagem ID: 59 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,', NULL, NULL, '2026-01-28 14:03:40'),
(3, 48, 30, 'enviar_mensagem', 'Mensagem ID: 60 - Conteúdo: qoero', NULL, NULL, '2026-01-28 14:04:40'),
(4, 48, 30, 'enviar_mensagem', 'Mensagem ID: 61 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** melancia\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 5', NULL, NULL, '2026-01-28 14:04:49'),
(5, 47, 32, 'enviar_mensagem', 'Mensagem ID: 62 - Conteúdo: opa beleza?', NULL, NULL, '2026-01-28 14:04:59'),
(6, 47, 32, 'enviar_mensagem', 'Mensagem ID: 63 - Conteúdo: tem que comprar no minimo 2!', NULL, NULL, '2026-01-28 14:05:10'),
(7, 47, 30, 'enviar_mensagem', 'Mensagem ID: 64 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 2 unidades\n**Valor unitário:** R$ 10,', NULL, NULL, '2026-01-28 14:05:22'),
(8, 48, 34, 'enviar_mensagem', 'Mensagem ID: 65 - Conteúdo: opa', NULL, NULL, '2026-01-28 14:22:03'),
(9, 48, 34, 'enviar_mensagem', 'Mensagem ID: 66 - Conteúdo: cu', NULL, NULL, '2026-01-28 14:22:07'),
(10, 48, 33, 'enviar_mensagem', 'Mensagem ID: 67 - Conteúdo: teste', NULL, NULL, '2026-01-28 14:42:21'),
(11, 47, 30, 'enviar_mensagem', 'Mensagem ID: 68 - Conteúdo: pdp', NULL, NULL, '2026-01-28 14:42:39'),
(12, 48, 34, 'enviar_mensagem', 'Mensagem ID: 69 - Conteúdo: zuado', NULL, NULL, '2026-01-28 14:43:02'),
(13, 48, 34, 'enviar_mensagem', 'Mensagem ID: 70 - Conteúdo: porra', NULL, NULL, '2026-01-28 14:45:52'),
(14, 48, 30, 'enviar_mensagem', 'Mensagem ID: 71 - Conteúdo: *PROPOSTA DE ENTREGA*\nValor: R$ 100,00\nPrazo: 2026-01-30\nID: 14', NULL, NULL, '2026-01-28 14:46:36'),
(15, 48, 34, 'enviar_mensagem', 'Mensagem ID: 72 - Conteúdo: ✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.', NULL, NULL, '2026-01-28 14:46:43'),
(16, 47, 34, 'enviar_mensagem', 'Mensagem ID: 73 - Conteúdo: ei', NULL, NULL, '2026-01-28 14:47:06'),
(17, 55, 35, 'enviar_mensagem', 'Mensagem ID: 74 - Conteúdo: opa', NULL, NULL, '2026-01-28 14:53:11'),
(18, 55, 30, 'enviar_mensagem', 'Mensagem ID: 75 - Conteúdo: *PROPOSTA DE ENTREGA*\nValor: R$ 111,00\nPrazo: 2026-01-29\nID: 15', NULL, NULL, '2026-01-28 14:53:32'),
(19, 47, 35, 'enviar_mensagem', 'Mensagem ID: 76 - Conteúdo: ✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.', NULL, NULL, '2026-01-28 14:53:37'),
(20, 47, 30, 'enviar_mensagem', 'Mensagem ID: 77 - Conteúdo: epa', NULL, NULL, '2026-01-28 15:06:05'),
(21, 47, 30, 'enviar_mensagem', 'Mensagem ID: 78 - Conteúdo: quero comprar de novo', NULL, NULL, '2026-01-28 15:06:08'),
(22, 47, 30, 'enviar_mensagem', 'Mensagem ID: 79 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,', NULL, NULL, '2026-01-28 15:06:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_conversas`
--

CREATE TABLE `chat_conversas` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `comprador_id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
  `transportador_id` int(11) DEFAULT NULL,
  `ultima_mensagem` text DEFAULT NULL,
  `ultima_mensagem_data` timestamp NULL DEFAULT NULL,
  `comprador_lido` tinyint(1) DEFAULT 1,
  `vendedor_lido` tinyint(1) DEFAULT 0,
  `status` enum('ativo','arquivado') DEFAULT 'ativo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `deletado` tinyint(1) DEFAULT 0,
  `data_delecao` timestamp NULL DEFAULT NULL,
  `usuario_deletou` int(11) DEFAULT NULL,
  `favorito_comprador` tinyint(1) DEFAULT 0,
  `favorito_vendedor` tinyint(1) DEFAULT 0,
  `comprador_excluiu` tinyint(1) DEFAULT 0,
  `vendedor_excluiu` tinyint(1) DEFAULT 0,
  `ultimo_ip_comprador` varchar(45) DEFAULT NULL,
  `ultimo_ip_vendedor` varchar(45) DEFAULT NULL,
  `ultimo_user_agent_comprador` text DEFAULT NULL,
  `ultimo_user_agent_vendedor` text DEFAULT NULL,
  `favorito_transportador` tinyint(1) NOT NULL DEFAULT 0,
  `transportador_excluiu` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `chat_conversas`
--

INSERT INTO `chat_conversas` (`id`, `produto_id`, `comprador_id`, `vendedor_id`, `transportador_id`, `ultima_mensagem`, `ultima_mensagem_data`, `comprador_lido`, `vendedor_lido`, `status`, `data_criacao`, `deletado`, `data_delecao`, `usuario_deletou`, `favorito_comprador`, `favorito_vendedor`, `comprador_excluiu`, `vendedor_excluiu`, `ultimo_ip_comprador`, `ultimo_ip_vendedor`, `ultimo_user_agent_comprador`, `ultimo_user_agent_vendedor`, `favorito_transportador`, `transportador_excluiu`) VALUES
(47, 32, 30, 32, 34, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 10,00\n\n**ID da proposta:** 71', '2026-01-28 15:06:15', 1, 0, 'ativo', '2026-01-28 14:03:30', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0),
(48, 33, 30, 33, 34, '*PROPOSTA DE ENTREGA*\nValor: R$ 100,00\nPrazo: 2026-01-30\nID: 14', '2026-01-28 14:46:36', 0, 1, 'ativo', '2026-01-28 14:04:38', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0),
(55, 32, 30, 32, 35, '*PROPOSTA DE ENTREGA*\nValor: R$ 111,00\nPrazo: 2026-01-29\nID: 15', '2026-01-28 14:53:32', 1, 0, 'ativo', '2026-01-28 14:53:09', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0),
(56, 33, 34, 33, NULL, NULL, NULL, 1, 0, 'ativo', '2026-01-28 14:55:14', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0),
(57, 32, 35, 32, NULL, NULL, NULL, 1, 0, 'ativo', '2026-01-28 14:55:52', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_mensagens`
--

CREATE TABLE `chat_mensagens` (
  `id` int(11) NOT NULL,
  `conversa_id` int(11) NOT NULL,
  `remetente_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('texto','imagem','negociacao') NOT NULL DEFAULT 'texto',
  `dados_json` text DEFAULT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `data_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `deletado` tinyint(1) DEFAULT 0,
  `data_delecao` timestamp NULL DEFAULT NULL,
  `usuario_deletou` int(11) DEFAULT NULL,
  `tipo_mensagem` enum('texto','imagem','arquivo') DEFAULT 'texto',
  `anexo_url` varchar(500) DEFAULT NULL,
  `palavras_ofensivas` text DEFAULT NULL COMMENT 'Palavras ofensivas detectadas na mensagem'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `chat_mensagens`
--

INSERT INTO `chat_mensagens` (`id`, `conversa_id`, `remetente_id`, `mensagem`, `tipo`, `dados_json`, `lida`, `data_envio`, `deletado`, `data_delecao`, `usuario_deletou`, `tipo_mensagem`, `anexo_url`, `palavras_ofensivas`) VALUES
(58, 47, 30, 'quero', 'texto', NULL, 1, '2026-01-28 14:03:33', 0, NULL, NULL, 'texto', NULL, NULL),
(59, 47, 30, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 10,00\n\n**ID da proposta:** 67', 'texto', NULL, 1, '2026-01-28 14:03:40', 0, NULL, NULL, 'texto', NULL, NULL),
(60, 48, 30, 'qoero', 'texto', NULL, 1, '2026-01-28 14:04:40', 0, NULL, NULL, 'texto', NULL, NULL),
(61, 48, 30, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** melancia\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 5,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 5,00\n\n**ID da proposta:** 68', 'texto', NULL, 1, '2026-01-28 14:04:49', 0, NULL, NULL, 'texto', NULL, NULL),
(62, 47, 32, 'opa beleza?', 'texto', NULL, 1, '2026-01-28 14:04:59', 0, NULL, NULL, 'texto', NULL, NULL),
(63, 47, 32, 'tem que comprar no minimo 2!', 'texto', NULL, 1, '2026-01-28 14:05:10', 0, NULL, NULL, 'texto', NULL, NULL),
(64, 47, 30, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 2 unidades\n**Valor unitário:** R$ 10,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 20,00\n\n**ID da proposta:** 67', 'texto', NULL, 1, '2026-01-28 14:05:22', 0, NULL, NULL, 'texto', NULL, NULL),
(65, 48, 34, 'opa', 'texto', NULL, 1, '2026-01-28 14:22:03', 0, NULL, NULL, 'texto', NULL, NULL),
(66, 48, 34, 'cu', 'texto', NULL, 1, '2026-01-28 14:22:07', 0, NULL, NULL, 'texto', NULL, NULL),
(67, 48, 33, 'teste', 'texto', NULL, 1, '2026-01-28 14:42:21', 0, NULL, NULL, 'texto', NULL, NULL),
(68, 47, 30, 'pdp', 'texto', NULL, 1, '2026-01-28 14:42:39', 0, NULL, NULL, 'texto', NULL, NULL),
(69, 48, 34, 'zuado', 'texto', NULL, 1, '2026-01-28 14:43:02', 0, NULL, NULL, 'texto', NULL, NULL),
(70, 48, 34, 'porra', 'texto', NULL, 1, '2026-01-28 14:45:52', 0, NULL, NULL, 'texto', NULL, NULL),
(71, 48, 30, '*PROPOSTA DE ENTREGA*\nValor: R$ 100,00\nPrazo: 2026-01-30\nID: 14', '', '{\"proposta_id\":69,\"propostas_transportador_id\":14,\"valor\":\"100\",\"prazo\":\"2026-01-30\"}', 1, '2026-01-28 14:46:36', 0, NULL, NULL, 'texto', NULL, NULL),
(72, 48, 34, '✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.', '', NULL, 1, '2026-01-28 14:46:43', 0, NULL, NULL, 'texto', NULL, NULL),
(73, 47, 34, 'ei', 'texto', NULL, 1, '2026-01-28 14:47:06', 0, NULL, NULL, 'texto', NULL, NULL),
(74, 55, 35, 'opa', 'texto', NULL, 1, '2026-01-28 14:53:11', 0, NULL, NULL, 'texto', NULL, NULL),
(75, 55, 30, '*PROPOSTA DE ENTREGA*\nValor: R$ 111,00\nPrazo: 2026-01-29\nID: 15', '', '{\"proposta_id\":70,\"propostas_transportador_id\":15,\"valor\":\"111\",\"prazo\":\"2026-01-29\"}', 1, '2026-01-28 14:53:32', 0, NULL, NULL, 'texto', NULL, NULL),
(76, 47, 35, '✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.', '', NULL, 1, '2026-01-28 14:53:37', 0, NULL, NULL, 'texto', NULL, NULL),
(77, 47, 30, 'epa', 'texto', NULL, 0, '2026-01-28 15:06:05', 0, NULL, NULL, 'texto', NULL, NULL),
(78, 47, 30, 'quero comprar de novo', 'texto', NULL, 0, '2026-01-28 15:06:08', 0, NULL, NULL, 'texto', NULL, NULL),
(79, 47, 30, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 10,00\n\n**ID da proposta:** 71', 'texto', NULL, 0, '2026-01-28 15:06:15', 0, NULL, NULL, 'texto', NULL, NULL);

--
-- Acionadores `chat_mensagens`
--
DELIMITER $$
CREATE TRIGGER `after_chat_mensagem_insert` AFTER INSERT ON `chat_mensagens` FOR EACH ROW BEGIN
    INSERT INTO chat_auditoria (conversa_id, usuario_id, acao, detalhes)
    VALUES (NEW.conversa_id, NEW.remetente_id, 'enviar_mensagem', 
            CONCAT('Mensagem ID: ', NEW.id, ' - Conteúdo: ', SUBSTRING(NEW.mensagem, 1, 100)));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `compradores`
--

CREATE TABLE `compradores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_pessoa` enum('cpf','cnpj') DEFAULT NULL,
  `nome_comercial` varchar(255) DEFAULT NULL,
  `foto_perfil_url` varchar(500) DEFAULT NULL,
  `cpf_cnpj` varchar(20) NOT NULL,
  `cip` varchar(50) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `telefone1` varchar(20) DEFAULT NULL,
  `telefone2` varchar(20) DEFAULT NULL,
  `plano` enum('free','basico','premium','empresarial') DEFAULT 'free'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `compradores`
--

INSERT INTO `compradores` (`id`, `usuario_id`, `tipo_pessoa`, `nome_comercial`, `foto_perfil_url`, `cpf_cnpj`, `cip`, `cep`, `rua`, `numero`, `complemento`, `estado`, `cidade`, `telefone1`, `telefone2`, `plano`) VALUES
(13, 30, 'cpf', 'Jorgeagp', NULL, '411.115.848-00', NULL, '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free'),
(14, 31, 'cpf', 'Silene', NULL, '166.076.628-11', NULL, '13214-065', 'Rua Itirapina', '837', '', 'SP', 'Jundiaí', '(11) 99841-8020', '', 'free');

-- --------------------------------------------------------

--
-- Estrutura para tabela `conversas`
--

CREATE TABLE `conversas` (
  `id` int(11) NOT NULL,
  `comprador_id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `status` enum('ativa','finalizada','arquivada') DEFAULT 'ativa',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_mensagem` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `entregas`
--

CREATE TABLE `entregas` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `transportador_id` int(11) NOT NULL,
  `endereco_origem` varchar(500) NOT NULL,
  `endereco_destino` varchar(500) NOT NULL,
  `status` enum('pendente','em_transporte','entregue','cancelada') DEFAULT 'pendente',
  `data_solicitacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `valor_frete` decimal(10,2) DEFAULT 0.00,
  `vendedor_id` int(11) DEFAULT NULL,
  `comprador_id` int(11) DEFAULT NULL,
  `data_aceitacao` timestamp NULL DEFAULT NULL,
  `data_inicio_transporte` timestamp NULL DEFAULT NULL,
  `data_entrega` timestamp NULL DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `foto_comprovante` varchar(500) DEFAULT NULL,
  `status_detalhado` enum('aguardando_frete','aguardando_entrega','em_transporte','entregue','finalizada','cancelada') DEFAULT 'aguardando_frete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `entregas`
--

INSERT INTO `entregas` (`id`, `produto_id`, `transportador_id`, `endereco_origem`, `endereco_destino`, `status`, `data_solicitacao`, `valor_frete`, `vendedor_id`, `comprador_id`, `data_aceitacao`, `data_inicio_transporte`, `data_entrega`, `observacoes`, `foto_comprovante`, `status_detalhado`) VALUES
(31, 33, 4, '', 'Rua Seis, 206 - Jundiaí/SP - CEP: 13211-873', 'pendente', '2026-01-28 14:46:43', 100.00, 12, 30, '2026-01-28 14:46:43', NULL, NULL, '', NULL, 'aguardando_entrega'),
(32, 32, 5, '', 'Rua Seis, 206 - Jundiaí/SP - CEP: 13211-873', 'pendente', '2026-01-28 14:53:37', 111.00, 11, 30, '2026-01-28 14:53:37', NULL, NULL, '', NULL, 'aguardando_entrega');

-- --------------------------------------------------------

--
-- Estrutura para tabela `favoritos`
--

CREATE TABLE `favoritos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mensagens`
--

CREATE TABLE `mensagens` (
  `id` int(11) NOT NULL,
  `conversa_id` int(11) NOT NULL,
  `remetente_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('texto','imagem','proposta','aceite','oferta') DEFAULT 'texto',
  `dados_json` text DEFAULT NULL COMMENT 'JSON com dados extras (preço, quantidade, etc)',
  `lida` tinyint(1) DEFAULT 0,
  `data_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `tipo` enum('info','sucesso','alerta','erro') DEFAULT 'info',
  `lida` tinyint(1) DEFAULT 0,
  `url` varchar(500) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `notificacoes`
--

INSERT INTO `notificacoes` (`id`, `usuario_id`, `mensagem`, `tipo`, `lida`, `url`, `data_criacao`) VALUES
(1, 1, 'Nova solicitação de cadastro de comprador: Jorge', 'info', 0, 'src/admin/solicitacoes.php', '2026-01-28 13:55:02'),
(2, 1, 'Nova solicitação de cadastro de comprador: Silene', 'info', 0, 'src/admin/solicitacoes.php', '2026-01-28 13:56:18'),
(3, 1, 'Nova solicitação de cadastro de vendedor: vendedor1', 'info', 0, 'src/admin/solicitacoes.php', '2026-01-28 13:57:22'),
(4, 1, 'Nova solicitação de cadastro de vendedor: vendedor2', 'info', 0, 'src/admin/solicitacoes.php', '2026-01-28 13:58:32'),
(5, 1, 'Nova solicitação de cadastro de transportador: transportador1', 'info', 0, 'src/admin/solicitacoes.php', '2026-01-28 13:59:24'),
(6, 1, 'Nova solicitação de cadastro de transportador: transportador2', 'info', 0, 'src/admin/solicitacoes.php', '2026-01-28 14:00:03'),
(7, 32, 'Nova proposta para \'Banana\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=32&conversa_id=47', '2026-01-28 14:03:40'),
(8, 33, 'Nova proposta para \'melancia\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=33&conversa_id=48', '2026-01-28 14:04:49'),
(9, 32, 'Proposta atualizada para \'Banana\' - Quantidade: 2 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=32&conversa_id=47', '2026-01-28 14:05:22'),
(10, 32, 'Nova proposta para \'Banana\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=32&conversa_id=47', '2026-01-28 15:06:15');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos`
--

CREATE TABLE `pagamentos` (
  `id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
  `assinatura_id` int(11) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `metodo_pagamento` varchar(50) DEFAULT NULL,
  `id_mercadopago` varchar(255) DEFAULT NULL,
  `data_pagamento` timestamp NULL DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos`
--

CREATE TABLE `planos` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `preco_mensal` decimal(10,2) NOT NULL,
  `stripe_price_id` varchar(255) DEFAULT NULL,
  `quantidade_anuncios_pagos` int(11) NOT NULL,
  `quantidade_anuncios_gratis` int(11) NOT NULL DEFAULT 1,
  `limite_total_anuncios` int(11) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `descricao_recursos` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `planos`
--

INSERT INTO `planos` (`id`, `nome`, `descricao`, `preco_mensal`, `stripe_price_id`, `quantidade_anuncios_pagos`, `quantidade_anuncios_gratis`, `limite_total_anuncios`, `ativo`, `created_at`, `descricao_recursos`) VALUES
(1, 'Plano 1 Grátis', 'Plano Básico Gratuito', 0.00, NULL, 0, 1, 1, 1, '2025-12-19 14:40:55', 'Acesso básico, 1 anúncios, Cancele a qualquer momento'),
(2, 'Plano 2', '2 anúncios no total', 1.00, 'price_1SnkZj0lZtce65b7rK4KR76e', 1, 1, 2, 1, '2025-12-19 14:40:55', '2 anúncios, Suporte, Cancele a qualquer momento'),
(3, 'Plano 3', '3 anúncios no total', 1.00, 'price_1SnJ2U0lZtce65b7d6bTS00U', 2, 1, 3, 1, '2025-12-19 14:40:55', '3 anúncios, Suporte, Cancele a qualquer momento'),
(4, 'Plano 4', '4 anúncios no total', 1.00, 'price_1SnJ2U0lZtce65b7d6bTS00U', 3, 1, 4, 1, '2025-12-19 14:40:55', '4 anúncios, Suporte, Cancele a qualquer momento'),
(5, 'Plano 5', '5 anúncios no total', 1.00, 'price_1ShXqL0lZtce65b7L1OfeXwu', 4, 1, 5, 1, '2025-12-19 14:40:55', '5 anúncios, Suporte, Cancele a qualquer momento'),
(6, 'Plano 6', 'Plano Flexível', 1.00, 'price_1ShXqh0lZtce65b7z1Z3PvDD', 7, 1, 8, 1, '2025-12-19 14:40:55', '8 anúncios, Suporte, Cancele a qualquer momento');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `modo_precificacao` varchar(50) DEFAULT 'por_quilo',
  `embalagem_peso_kg` decimal(8,3) DEFAULT NULL,
  `embalagem_unidades` int(11) DEFAULT NULL,
  `estoque_unidades` int(11) DEFAULT 0,
  `preco_desconto` decimal(10,2) DEFAULT NULL,
  `desconto_percentual` decimal(5,2) DEFAULT 0.00,
  `desconto_ativo` tinyint(1) DEFAULT 0,
  `desconto_data_inicio` datetime DEFAULT NULL,
  `desconto_data_fim` datetime DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `imagem_url` varchar(500) DEFAULT NULL,
  `estoque` int(11) DEFAULT 0,
  `unidade_medida` varchar(50) DEFAULT NULL,
  `paletizado` tinyint(1) DEFAULT 0,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `vendedor_id`, `nome`, `descricao`, `preco`, `modo_precificacao`, `embalagem_peso_kg`, `embalagem_unidades`, `estoque_unidades`, `preco_desconto`, `desconto_percentual`, `desconto_ativo`, `desconto_data_inicio`, `desconto_data_fim`, `categoria`, `imagem_url`, `estoque`, `unidade_medida`, `paletizado`, `status`, `data_criacao`, `data_atualizacao`) VALUES
(32, 11, 'Banana', 'bananas amarelas gostosas', 10.00, 'caixa_unidades', NULL, 10, 250, NULL, 0.00, 0, NULL, NULL, 'Frutas Tropicais', '../uploads/produtos/prod_697a16f35b3ee0.25475716.jpeg', 248, 'caixa', 1, 'ativo', '2026-01-28 14:02:27', '2026-01-28 14:05:34'),
(33, 12, 'melancia', 'melancias redondas', 5.00, 'por_unidade', NULL, NULL, 110, NULL, 0.00, 0, NULL, NULL, 'Frutas Vermelhas', '../uploads/produtos/prod_697a1720a5ee03.43633754.jpg', 109, 'unidade', 0, 'ativo', '2026-01-28 14:03:12', '2026-01-28 14:06:26');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_imagens`
--

CREATE TABLE `produto_imagens` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `imagem_url` varchar(500) NOT NULL,
  `ordem` int(11) DEFAULT 0,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produto_imagens`
--

INSERT INTO `produto_imagens` (`id`, `produto_id`, `imagem_url`, `ordem`, `data_criacao`) VALUES
(28, 32, '../uploads/produtos/prod_697a16f35b3ee0.25475716.jpeg', 0, '2026-01-28 14:02:27'),
(29, 33, '../uploads/produtos/prod_697a1720a5ee03.43633754.jpg', 0, '2026-01-28 14:03:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas`
--

CREATE TABLE `propostas` (
  `ID` int(11) NOT NULL,
  `comprador_id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `data_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `preco_proposto` decimal(10,2) NOT NULL,
  `quantidade_proposta` int(11) NOT NULL,
  `forma_pagamento` enum('à vista','entrega') NOT NULL,
  `opcao_frete` enum('vendedor','comprador','entregador') NOT NULL,
  `valor_frete` decimal(10,2) DEFAULT 0.00,
  `valor_total` decimal(10,2) DEFAULT NULL,
  `status` enum('aceita','negociacao','recusada') DEFAULT 'negociacao',
  `tipo_frete` enum('vendedor','comprador','plataforma') DEFAULT 'vendedor',
  `status_entrega` enum('pendente','aceita','coletado','transporte','entregue','cancelada') DEFAULT 'pendente',
  `data_entrega_estimada` date DEFAULT NULL,
  `transportador_id` int(11) DEFAULT NULL,
  `endereco_vendedor` text DEFAULT NULL,
  `endereco_comprador` text DEFAULT NULL,
  `valor_frete_final` decimal(10,2) DEFAULT NULL,
  `confirmado` tinyint(1) DEFAULT 0,
  `arquivado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas`
--

INSERT INTO `propostas` (`ID`, `comprador_id`, `vendedor_id`, `produto_id`, `data_inicio`, `data_atualizacao`, `preco_proposto`, `quantidade_proposta`, `forma_pagamento`, `opcao_frete`, `valor_frete`, `valor_total`, `status`, `tipo_frete`, `status_entrega`, `data_entrega_estimada`, `transportador_id`, `endereco_vendedor`, `endereco_comprador`, `valor_frete_final`, `confirmado`, `arquivado`) VALUES
(67, 30, 32, 32, '2026-01-28 14:03:40', '2026-01-28 14:05:34', 10.00, 2, 'à vista', 'entregador', 0.00, 20.00, 'aceita', 'vendedor', 'pendente', NULL, NULL, NULL, NULL, NULL, 0, 0),
(68, 30, 33, 33, '2026-01-28 14:04:49', '2026-01-28 14:06:26', 5.00, 1, 'à vista', 'entregador', 0.00, 5.00, 'aceita', 'vendedor', 'pendente', NULL, NULL, NULL, NULL, NULL, 0, 0),
(69, 30, 33, 33, '2026-01-28 14:46:36', '2026-01-28 14:46:43', 5.00, 1, 'à vista', 'entregador', 100.00, 100.00, 'aceita', 'vendedor', 'pendente', '2026-01-30', 4, NULL, NULL, NULL, 0, 0),
(70, 30, 32, 32, '2026-01-28 14:53:32', '2026-01-28 14:53:37', 10.00, 1, 'à vista', 'entregador', 111.00, 111.00, 'aceita', 'vendedor', 'pendente', '2026-01-29', 5, NULL, NULL, NULL, 0, 0),
(71, 30, 32, 32, '2026-01-28 15:06:15', '2026-01-28 15:06:15', 10.00, 1, 'à vista', 'entregador', 0.00, 10.00, 'negociacao', 'vendedor', 'pendente', NULL, NULL, NULL, NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_transportadores`
--

CREATE TABLE `propostas_transportadores` (
  `id` int(11) NOT NULL,
  `proposta_id` int(11) NOT NULL,
  `transportador_id` int(11) NOT NULL,
  `valor_frete` decimal(10,2) NOT NULL,
  `prazo_entrega` int(11) NOT NULL,
  `observacoes` text DEFAULT NULL,
  `status` enum('pendente','aceita','recusada','cancelada') DEFAULT 'pendente',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_resposta` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas_transportadores`
--

INSERT INTO `propostas_transportadores` (`id`, `proposta_id`, `transportador_id`, `valor_frete`, `prazo_entrega`, `observacoes`, `status`, `data_criacao`, `data_resposta`) VALUES
(14, 69, 4, 100.00, 2, '', 'aceita', '2026-01-28 11:46:36', '2026-01-28 11:46:43'),
(15, 70, 5, 111.00, 1, '', 'aceita', '2026-01-28 11:53:32', '2026-01-28 11:53:37');

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacoes_cadastro`
--

CREATE TABLE `solicitacoes_cadastro` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `tipo_solicitacao` enum('vendedor','comprador','transportador') NOT NULL,
  `dados_json` text NOT NULL,
  `status` enum('pendente','aprovado','rejeitado') DEFAULT 'pendente',
  `data_solicitacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_analise` timestamp NULL DEFAULT NULL,
  `admin_responsavel` int(11) DEFAULT NULL,
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `solicitacoes_cadastro`
--

INSERT INTO `solicitacoes_cadastro` (`id`, `usuario_id`, `nome`, `email`, `telefone`, `endereco`, `tipo_solicitacao`, `dados_json`, `status`, `data_solicitacao`, `data_analise`, `admin_responsavel`, `observacoes`) VALUES
(1, 30, 'Jorge', 'jorgeappontes13@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"name\":\"Jorge\",\"email\":\"jorgeappontes13@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"411.115.848-00\",\"nomeComercialComprador\":\"Jorgeagp\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99656-3500\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"cepTransportador\":\"\",\"ruaTransportador\":\"\",\"numeroTransportador\":\"\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$CY0vnKldO3Eb\\/0tu0H2LluSCLCcPgWtyBVLz03X2KVM5LBnWZH8da\"}', 'aprovado', '2026-01-28 13:55:02', '2026-01-28 14:00:24', NULL, NULL),
(2, 31, 'Silene', 'silene@gmail.com', '(11) 99841-8020', 'Rua Itirapina, 837, Jundiaí, SP', 'comprador', '{\"name\":\"Silene\",\"email\":\"silene@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"166.076.628-11\",\"nomeComercialComprador\":\"Silene\",\"cepComprador\":\"13214-065\",\"ruaComprador\":\"Rua Itirapina\",\"numeroComprador\":\"837\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99841-8020\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"cepTransportador\":\"\",\"ruaTransportador\":\"\",\"numeroTransportador\":\"\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$vzuEOlFd1FhOux6mdxY4iuYCqjifjvd9uPn5V9\\/SS3OsyMi5rEuFC\"}', 'aprovado', '2026-01-28 13:56:18', '2026-01-28 14:00:26', NULL, NULL),
(3, 32, 'vendedor1', 'vendedor1@gmail.com', '(11) 99656-3500', 'Rua Ravenna, 65, Jundiaí, SP', 'vendedor', '{\"name\":\"vendedor1\",\"email\":\"vendedor1@gmail.com\",\"subject\":\"vendedor\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"V1\",\"cpfCnpjVendedor\":\"76.865.398\\/0001-90\",\"cipVendedor\":\"\",\"cepVendedor\":\"13214-670\",\"ruaVendedor\":\"Rua Ravenna\",\"numeroVendedor\":\"65\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"(11) 99656-3500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"cepTransportador\":\"\",\"ruaTransportador\":\"\",\"numeroTransportador\":\"\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$aXg6jKop7d3j98qXOXnNpeIqHOek6iHgsVeYPJABERWHKtlOWYlGO\"}', 'aprovado', '2026-01-28 13:57:22', '2026-01-28 14:00:48', NULL, NULL),
(4, 33, 'vendedor2', 'vendedor2@gmail.com', '(11) 99656-3500', 'Avenida Comendador Hermes Traldi, 1, Jundiaí, SP', 'vendedor', '{\"name\":\"vendedor2\",\"email\":\"vendedor2@gmail.com\",\"subject\":\"vendedor\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"V2\",\"cpfCnpjVendedor\":\"06.634.459\\/0001-23\",\"cipVendedor\":\"\",\"cepVendedor\":\"13209-772\",\"ruaVendedor\":\"Avenida Comendador Hermes Traldi\",\"numeroVendedor\":\"1\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"(11) 99656-3500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"cepTransportador\":\"\",\"ruaTransportador\":\"\",\"numeroTransportador\":\"\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$oWfsoMmnbpTvCiWoD0y4xOwh1XiNFujUW8VPmRMRukwJCoHAfowmu\"}', 'aprovado', '2026-01-28 13:58:32', '2026-01-28 14:00:50', NULL, NULL),
(5, 34, 'transportador1', 'transportador1@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'transportador', '{\"name\":\"transportador1\",\"email\":\"transportador1@gmail.com\",\"subject\":\"transportador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"(11) 99656-3500\",\"numeroANTT\":\"1111111\",\"placaVeiculo\":\"FEC8-J16\",\"modeloVeiculo\":\"fiesta\",\"descricaoVeiculo\":\"branco\",\"cepTransportador\":\"13211-873\",\"ruaTransportador\":\"Rua Seis\",\"numeroTransportador\":\"206\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"SP\",\"cidadeTransportador\":\"Jundiaí\",\"message\":\"\",\"senha_hash\":\"$2y$10$v0FHugd9.omegQFeNdFtBeWtuND0mHdK3RVrg3t4vPkJe1f6IEn76\"}', 'aprovado', '2026-01-28 13:59:24', '2026-01-28 14:00:59', NULL, NULL),
(6, 35, 'transportador2', 'transportador2@gmail.com', '(11) 99841-8020', 'Rua Seis, 206, Jundiaí, SP', 'transportador', '{\"name\":\"transportador2\",\"email\":\"transportador2@gmail.com\",\"subject\":\"transportador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"(11) 99841-8020\",\"numeroANTT\":\"1111\",\"placaVeiculo\":\"QPS2-F96\",\"modeloVeiculo\":\"sandero\",\"descricaoVeiculo\":\"preto\",\"cepTransportador\":\"13211-873\",\"ruaTransportador\":\"Rua Seis\",\"numeroTransportador\":\"206\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"SP\",\"cidadeTransportador\":\"Jundiaí\",\"message\":\"\",\"senha_hash\":\"$2y$10$J7oxEDC1Heq.gN3.FTDBLenskb4eiXqgrLGOr3TzYhGKAYJgGFGyG\"}', 'aprovado', '2026-01-28 14:00:03', '2026-01-28 14:01:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `transportadores`
--

CREATE TABLE `transportadores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nome_comercial` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `antt` varchar(100) DEFAULT NULL,
  `numero_antt` varchar(50) DEFAULT NULL,
  `placa_veiculo` varchar(8) DEFAULT NULL,
  `modelo_veiculo` varchar(100) DEFAULT NULL,
  `descricao_veiculo` text DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `plano` enum('free','basico','premium','empresarial') DEFAULT 'free',
  `foto_perfil_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `transportadores`
--

INSERT INTO `transportadores` (`id`, `usuario_id`, `nome_comercial`, `telefone`, `antt`, `numero_antt`, `placa_veiculo`, `modelo_veiculo`, `descricao_veiculo`, `cep`, `rua`, `numero`, `complemento`, `estado`, `cidade`, `plano`, `foto_perfil_url`) VALUES
(4, 34, 'transportador1', '(11) 99656-3500', NULL, '1111111', 'FEC8-J16', 'fiesta', 'branco', '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', 'free', NULL),
(5, 35, 'transportador2', '(11) 99841-8020', NULL, '1111', 'QPS2-F96', 'sandero', 'preto', '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', 'free', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `transportador_favoritos`
--

CREATE TABLE `transportador_favoritos` (
  `id` int(11) NOT NULL,
  `transportador_id` int(11) NOT NULL,
  `proposta_id` int(11) NOT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','vendedor','comprador','transportador') NOT NULL,
  `nome` varchar(255) NOT NULL,
  `status` enum('pendente','ativo','inativo') DEFAULT 'pendente',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_aprovacao` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expira` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `senha`, `tipo`, `nome`, `status`, `data_criacao`, `data_aprovacao`, `reset_token`, `reset_token_expira`) VALUES
(1, 'admin@encontreocampo.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrador', 'ativo', '2025-11-08 14:33:23', '2025-11-08 14:33:23', NULL, NULL),
(30, 'jorgeappontes13@gmail.com', '$2y$10$CY0vnKldO3Eb/0tu0H2LluSCLCcPgWtyBVLz03X2KVM5LBnWZH8da', 'comprador', 'Jorge', 'ativo', '2026-01-28 13:55:02', NULL, NULL, NULL),
(31, 'silene@gmail.com', '$2y$10$vzuEOlFd1FhOux6mdxY4iuYCqjifjvd9uPn5V9/SS3OsyMi5rEuFC', 'comprador', 'Silene', 'ativo', '2026-01-28 13:56:18', NULL, NULL, NULL),
(32, 'vendedor1@gmail.com', '$2y$10$aXg6jKop7d3j98qXOXnNpeIqHOek6iHgsVeYPJABERWHKtlOWYlGO', 'vendedor', 'vendedor1', 'ativo', '2026-01-28 13:57:22', NULL, NULL, NULL),
(33, 'vendedor2@gmail.com', '$2y$10$oWfsoMmnbpTvCiWoD0y4xOwh1XiNFujUW8VPmRMRukwJCoHAfowmu', 'vendedor', 'vendedor2', 'ativo', '2026-01-28 13:58:32', NULL, NULL, NULL),
(34, 'transportador1@gmail.com', '$2y$10$v0FHugd9.omegQFeNdFtBeWtuND0mHdK3RVrg3t4vPkJe1f6IEn76', 'transportador', 'transportador1', 'ativo', '2026-01-28 13:59:24', NULL, NULL, NULL),
(35, 'transportador2@gmail.com', '$2y$10$J7oxEDC1Heq.gN3.FTDBLenskb4eiXqgrLGOr3TzYhGKAYJgGFGyG', 'transportador', 'transportador2', 'ativo', '2026-01-28 14:00:03', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_avisos_preferencias`
--

CREATE TABLE `usuario_avisos_preferencias` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `aviso_regioes_entrega` tinyint(1) DEFAULT 1 COMMENT '1 = exibir, 0 = não exibir',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendedores`
--

CREATE TABLE `vendedores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_pessoa` enum('cpf','cnpj') DEFAULT 'cnpj',
  `nome_comercial` varchar(255) DEFAULT NULL,
  `cpf_cnpj` varchar(20) NOT NULL,
  `razao_social` varchar(255) DEFAULT NULL,
  `foto_perfil_url` varchar(255) DEFAULT NULL,
  `cip` varchar(50) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `telefone1` varchar(20) DEFAULT NULL,
  `telefone2` varchar(20) DEFAULT NULL,
  `plano` enum('free','basico','premium','empresarial') DEFAULT 'free',
  `estados_atendidos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Estados do Brasil atendidos pelo vendedor (lista branca em formato JSON)' CHECK (json_valid(`estados_atendidos`)),
  `cidades_atendidas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON com cidades atendidas por estado, ex: {"SP":["São Paulo","Campinas"]}' CHECK (json_valid(`cidades_atendidas`)),
  `plano_id` int(11) DEFAULT 1,
  `status_assinatura` varchar(20) DEFAULT 'inativo',
  `data_assinatura` datetime DEFAULT NULL,
  `data_inicio_assinatura` datetime DEFAULT NULL,
  `data_vencimento_assinatura` datetime DEFAULT NULL,
  `anuncios_ativos` int(11) DEFAULT 0,
  `anuncios_pagos_utilizados` int(11) DEFAULT 0,
  `anuncios_gratis_utilizados` int(11) DEFAULT 0,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vendedores`
--

INSERT INTO `vendedores` (`id`, `usuario_id`, `tipo_pessoa`, `nome_comercial`, `cpf_cnpj`, `razao_social`, `foto_perfil_url`, `cip`, `cep`, `rua`, `numero`, `complemento`, `estado`, `cidade`, `telefone1`, `telefone2`, `plano`, `estados_atendidos`, `cidades_atendidas`, `plano_id`, `status_assinatura`, `data_assinatura`, `data_inicio_assinatura`, `data_vencimento_assinatura`, `anuncios_ativos`, `anuncios_pagos_utilizados`, `anuncios_gratis_utilizados`, `stripe_customer_id`, `stripe_subscription_id`) VALUES
(11, 32, 'cnpj', 'V1', '76.865.398/0001-90', NULL, NULL, '', '13214-670', 'Rua Ravenna', '65', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free', NULL, NULL, 1, 'inativo', NULL, NULL, NULL, 0, 0, 0, NULL, NULL),
(12, 33, 'cnpj', 'V2', '06.634.459/0001-23', NULL, NULL, '', '13209-772', 'Avenida Comendador Hermes Traldi', '1', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free', NULL, NULL, 1, 'inativo', NULL, NULL, NULL, 0, 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendedor_anuncios_controle`
--

CREATE TABLE `vendedor_anuncios_controle` (
  `id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
  `total_anuncios` int(11) DEFAULT 0,
  `anuncios_gratis_utilizados` int(11) DEFAULT 0,
  `anuncios_pagos_utilizados` int(11) DEFAULT 0,
  `anuncios_ativos` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendedor_assinaturas`
--

CREATE TABLE `vendedor_assinaturas` (
  `id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `status` enum('active','pending','paused','cancelled') DEFAULT 'pending',
  `preco_aprovado` decimal(10,2) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `periodo` varchar(20) DEFAULT 'monthly',
  `referencia_mercadopago` varchar(255) DEFAULT NULL,
  `preferencia_mercadopago` varchar(255) DEFAULT NULL,
  `unidades_extras` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_id` varchar(100) DEFAULT NULL,
  `subscription_id` varchar(100) DEFAULT NULL,
  `init_point` varchar(500) DEFAULT NULL,
  `external_reference` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `webhook_logs`
--

CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `resource_id` varchar(100) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `admin_acoes`
--
ALTER TABLE `admin_acoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Índices de tabela `chat_auditoria`
--
ALTER TABLE `chat_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversa` (`conversa_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_data` (`data_acao`);

--
-- Índices de tabela `chat_conversas`
--
ALTER TABLE `chat_conversas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conversa_unica` (`produto_id`,`comprador_id`,`vendedor_id`,`transportador_id`),
  ADD KEY `comprador_id` (`comprador_id`),
  ADD KEY `vendedor_id` (`vendedor_id`),
  ADD KEY `idx_chat_conversas_deletado` (`deletado`);

--
-- Índices de tabela `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversa` (`conversa_id`),
  ADD KEY `idx_remetente` (`remetente_id`),
  ADD KEY `idx_data` (`data_envio`),
  ADD KEY `idx_chat_mensagens_deletado` (`deletado`);

--
-- Índices de tabela `compradores`
--
ALTER TABLE `compradores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `conversas`
--
ALTER TABLE `conversas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conversa_unica` (`comprador_id`,`vendedor_id`,`produto_id`),
  ADD KEY `comprador_id` (`comprador_id`),
  ADD KEY `vendedor_id` (`vendedor_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `entregas`
--
ALTER TABLE `entregas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `transportador_id` (`transportador_id`),
  ADD KEY `vendedor_id` (`vendedor_id`),
  ADD KEY `comprador_id` (`comprador_id`),
  ADD KEY `idx_entrega_unique` (`produto_id`,`transportador_id`,`comprador_id`,`vendedor_id`);

--
-- Índices de tabela `favoritos`
--
ALTER TABLE `favoritos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_produto` (`usuario_id`,`produto_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversa_id` (`conversa_id`),
  ADD KEY `remetente_id` (`remetente_id`),
  ADD KEY `data_envio` (`data_envio`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendedor_id` (`vendedor_id`),
  ADD KEY `assinatura_id` (`assinatura_id`);

--
-- Índices de tabela `planos`
--
ALTER TABLE `planos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendedor_id` (`vendedor_id`);

--
-- Índices de tabela `produto_imagens`
--
ALTER TABLE `produto_imagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produto_id` (`produto_id`);

--
-- Índices de tabela `propostas`
--
ALTER TABLE `propostas`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `comprador_id` (`comprador_id`),
  ADD KEY `vendedor_id` (`vendedor_id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `transportador_id` (`transportador_id`);

--
-- Índices de tabela `propostas_transportadores`
--
ALTER TABLE `propostas_transportadores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proposta_id` (`proposta_id`),
  ADD KEY `transportador_id` (`transportador_id`),
  ADD KEY `idx_proposta_status` (`proposta_id`,`status`);

--
-- Índices de tabela `solicitacoes_cadastro`
--
ALTER TABLE `solicitacoes_cadastro`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `transportadores`
--
ALTER TABLE `transportadores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `transportador_favoritos`
--
ALTER TABLE `transportador_favoritos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_transportador_proposta` (`transportador_id`,`proposta_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `reset_token` (`reset_token`);

--
-- Índices de tabela `usuario_avisos_preferencias`
--
ALTER TABLE `usuario_avisos_preferencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `vendedores`
--
ALTER TABLE `vendedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `plano_id` (`plano_id`);

--
-- Índices de tabela `vendedor_anuncios_controle`
--
ALTER TABLE `vendedor_anuncios_controle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendedor_id` (`vendedor_id`);

--
-- Índices de tabela `vendedor_assinaturas`
--
ALTER TABLE `vendedor_assinaturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendedor_id` (`vendedor_id`),
  ADD KEY `plano_id` (`plano_id`);

--
-- Índices de tabela `webhook_logs`
--
ALTER TABLE `webhook_logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admin_acoes`
--
ALTER TABLE `admin_acoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de tabela `chat_auditoria`
--
ALTER TABLE `chat_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `chat_conversas`
--
ALTER TABLE `chat_conversas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT de tabela `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT de tabela `compradores`
--
ALTER TABLE `compradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `conversas`
--
ALTER TABLE `conversas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `entregas`
--
ALTER TABLE `entregas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de tabela `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `mensagens`
--
ALTER TABLE `mensagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `planos`
--
ALTER TABLE `planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `produto_imagens`
--
ALTER TABLE `produto_imagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `propostas`
--
ALTER TABLE `propostas`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT de tabela `propostas_transportadores`
--
ALTER TABLE `propostas_transportadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `solicitacoes_cadastro`
--
ALTER TABLE `solicitacoes_cadastro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `transportadores`
--
ALTER TABLE `transportadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `transportador_favoritos`
--
ALTER TABLE `transportador_favoritos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `usuario_avisos_preferencias`
--
ALTER TABLE `usuario_avisos_preferencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `vendedores`
--
ALTER TABLE `vendedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `vendedor_anuncios_controle`
--
ALTER TABLE `vendedor_anuncios_controle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `vendedor_assinaturas`
--
ALTER TABLE `vendedor_assinaturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `webhook_logs`
--
ALTER TABLE `webhook_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `admin_acoes`
--
ALTER TABLE `admin_acoes`
  ADD CONSTRAINT `admin_acoes_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `chat_conversas`
--
ALTER TABLE `chat_conversas`
  ADD CONSTRAINT `chat_conversas_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_conversas_ibfk_2` FOREIGN KEY (`comprador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_conversas_ibfk_3` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  ADD CONSTRAINT `chat_mensagens_ibfk_1` FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_mensagens_ibfk_2` FOREIGN KEY (`remetente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `compradores`
--
ALTER TABLE `compradores`
  ADD CONSTRAINT `compradores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `conversas`
--
ALTER TABLE `conversas`
  ADD CONSTRAINT `conversas_ibfk_1` FOREIGN KEY (`comprador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversas_ibfk_2` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversas_ibfk_3` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `entregas`
--
ALTER TABLE `entregas`
  ADD CONSTRAINT `entregas_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entregas_ibfk_2` FOREIGN KEY (`transportador_id`) REFERENCES `transportadores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entregas_ibfk_3` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `entregas_ibfk_4` FOREIGN KEY (`comprador_id`) REFERENCES `compradores` (`usuario_id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `favoritos`
--
ALTER TABLE `favoritos`
  ADD CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favoritos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `mensagens`
--
ALTER TABLE `mensagens`
  ADD CONSTRAINT `mensagens_ibfk_1` FOREIGN KEY (`conversa_id`) REFERENCES `conversas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mensagens_ibfk_2` FOREIGN KEY (`remetente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `notificacoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD CONSTRAINT `pagamentos_ibfk_1` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`),
  ADD CONSTRAINT `pagamentos_ibfk_2` FOREIGN KEY (`assinatura_id`) REFERENCES `vendedor_assinaturas` (`id`);

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produto_imagens`
--
ALTER TABLE `produto_imagens`
  ADD CONSTRAINT `produto_imagens_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `propostas`
--
ALTER TABLE `propostas`
  ADD CONSTRAINT `propostas_ibfk_1` FOREIGN KEY (`comprador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_ibfk_2` FOREIGN KEY (`vendedor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_ibfk_3` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_ibfk_4` FOREIGN KEY (`transportador_id`) REFERENCES `transportadores` (`id`),
  ADD CONSTRAINT `propostas_ibfk_5` FOREIGN KEY (`transportador_id`) REFERENCES `transportadores` (`id`);

--
-- Restrições para tabelas `propostas_transportadores`
--
ALTER TABLE `propostas_transportadores`
  ADD CONSTRAINT `propostas_transportadores_ibfk_1` FOREIGN KEY (`proposta_id`) REFERENCES `propostas` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_transportadores_ibfk_2` FOREIGN KEY (`transportador_id`) REFERENCES `transportadores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `solicitacoes_cadastro`
--
ALTER TABLE `solicitacoes_cadastro`
  ADD CONSTRAINT `solicitacoes_cadastro_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `transportadores`
--
ALTER TABLE `transportadores`
  ADD CONSTRAINT `transportadores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuario_avisos_preferencias`
--
ALTER TABLE `usuario_avisos_preferencias`
  ADD CONSTRAINT `usuario_avisos_preferencias_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `vendedores`
--
ALTER TABLE `vendedores`
  ADD CONSTRAINT `vendedores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendedores_ibfk_2` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`);

--
-- Restrições para tabelas `vendedor_anuncios_controle`
--
ALTER TABLE `vendedor_anuncios_controle`
  ADD CONSTRAINT `vendedor_anuncios_controle_ibfk_1` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`);

--
-- Restrições para tabelas `vendedor_assinaturas`
--
ALTER TABLE `vendedor_assinaturas`
  ADD CONSTRAINT `vendedor_assinaturas_ibfk_1` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`),
  ADD CONSTRAINT `vendedor_assinaturas_ibfk_2` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
