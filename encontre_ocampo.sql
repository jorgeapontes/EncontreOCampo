-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 05/02/2026 às 02:17
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
(36, 1, 'Aprovou cadastro de transportador (ID: 35)', 'usuarios', 35, '2026-01-28 14:01:00'),
(37, 1, 'Aprovou cadastro de vendedor (ID: 36)', 'usuarios', 36, '2026-02-04 22:30:03'),
(38, 1, 'Aprovou cadastro de comprador (ID: 37)', 'usuarios', 37, '2026-02-04 22:33:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes`
--

CREATE TABLE `avaliacoes` (
  `id` int(11) NOT NULL,
  `avaliador_usuario_id` int(11) NOT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `proposta_id` int(11) DEFAULT NULL,
  `entrega_id` int(11) DEFAULT NULL,
  `nota` tinyint(1) NOT NULL,
  `comentario` text DEFAULT NULL,
  `tipo` enum('produto','vendedor') NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `avaliacoes`
--

INSERT INTO `avaliacoes` (`id`, `avaliador_usuario_id`, `produto_id`, `vendedor_id`, `proposta_id`, `entrega_id`, `nota`, `comentario`, `tipo`, `data_criacao`) VALUES
(1, 37, 34, NULL, NULL, NULL, 4, 'Muito boa a banana', 'produto', '2026-02-04 23:28:05');

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
(22, 47, 30, 'enviar_mensagem', 'Mensagem ID: 79 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,', NULL, NULL, '2026-01-28 15:06:15'),
(23, 58, 30, 'enviar_mensagem', 'Mensagem ID: 1 - Conteúdo: quero', NULL, NULL, '2026-01-30 15:21:19'),
(24, 58, 32, 'enviar_mensagem', 'Mensagem ID: 2 - Conteúdo: opa manda proposta', NULL, NULL, '2026-01-30 15:21:33'),
(25, 58, 30, 'enviar_mensagem', 'Mensagem ID: 3 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,', NULL, NULL, '2026-01-30 15:21:39'),
(26, 58, 32, 'enviar_mensagem', 'Mensagem ID: 4 - Conteúdo: ✅ ACORDO CONCLUÍDO!\n\nAmbas as partes assinaram o acordo digitalmente.\nA proposta foi oficialmente ac', NULL, NULL, '2026-01-30 15:22:06'),
(27, 59, 34, 'enviar_mensagem', 'Mensagem ID: 5 - Conteúdo: oba', NULL, NULL, '2026-01-30 15:22:35'),
(28, 59, 30, 'enviar_mensagem', 'Mensagem ID: 6 - Conteúdo: *PROPOSTA DE ENTREGA*\nValor: R$ 100,00\nPrazo: 2026-01-31\nID: 16', NULL, NULL, '2026-01-30 15:23:00'),
(29, 59, 34, 'enviar_mensagem', 'Mensagem ID: 7 - Conteúdo: ✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.', NULL, NULL, '2026-01-30 15:23:03'),
(30, 60, 34, 'enviar_mensagem', 'Mensagem ID: 8 - Conteúdo: eba', NULL, NULL, '2026-01-30 15:23:19'),
(31, 60, 30, 'enviar_mensagem', 'Mensagem ID: 9 - Conteúdo: *PROPOSTA DE ENTREGA*\nValor: R$ 222,00\nPrazo: 2026-01-31\nID: 17', NULL, NULL, '2026-01-30 15:23:30'),
(32, 60, 34, 'enviar_mensagem', 'Mensagem ID: 10 - Conteúdo: ✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.', NULL, NULL, '2026-01-30 15:23:32'),
(33, 61, 37, 'enviar_mensagem', 'Mensagem ID: 11 - Conteúdo: Olá', NULL, NULL, '2026-02-04 22:34:24'),
(34, 61, 37, 'enviar_mensagem', 'Mensagem ID: 12 - Conteúdo: Quero comprar banana', NULL, NULL, '2026-02-04 22:34:28'),
(35, 61, 37, 'enviar_mensagem', 'Mensagem ID: 13 - Conteúdo: **NOVA PROPOSTA DE COMPRA**\n\n**Produto:** Banana\n**Quantidade:** 10 kg\n**Valor unitário:** R$ 5,50\n*', NULL, NULL, '2026-02-04 22:35:14'),
(36, 61, 36, 'enviar_mensagem', 'Mensagem ID: 14 - Conteúdo: **PROPOSTA ACEITA PARA ASSINATURA DIGITAL**\n\nO vendedor aceitou a proposta!\nAgora ambas as partes pr', NULL, NULL, '2026-02-04 22:35:40'),
(37, 61, 36, 'enviar_mensagem', 'Mensagem ID: 15 - Conteúdo: ✅ ACORDO CONCLUÍDO!\n\nAmbas as partes assinaram o acordo digitalmente.\nA proposta foi oficialmente ac', NULL, NULL, '2026-02-04 22:35:57');

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
(58, 32, 30, 32, NULL, '✅ ACORDO CONCLUÍDO!\n\nAmbas as partes assinaram o acordo digitalmente.\nA proposta foi oficialmente aceita e a compra está confirmada.\n\n', '2026-01-30 15:22:06', 0, 1, 'ativo', '2026-01-30 15:21:12', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0),
(59, 32, 30, 32, 34, '*PROPOSTA DE ENTREGA*\nValor: R$ 100,00\nPrazo: 2026-01-31\nID: 16', '2026-01-30 15:23:00', 1, 0, 'ativo', '2026-01-30 15:22:33', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0),
(60, 33, 30, 33, 34, '*PROPOSTA DE ENTREGA*\nValor: R$ 222,00\nPrazo: 2026-01-31\nID: 17', '2026-01-30 15:23:30', 1, 0, 'ativo', '2026-01-30 15:23:17', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0),
(61, 34, 37, 36, NULL, '✅ ACORDO CONCLUÍDO!\n\nAmbas as partes assinaram o acordo digitalmente.\nA proposta foi oficialmente aceita e a compra está confirmada.\n\n', '2026-02-04 22:35:57', 0, 1, 'ativo', '2026-02-04 22:33:41', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL, 0, 0);

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
(1, 58, 30, 'quero', 'texto', NULL, 1, '2026-01-30 15:21:19', 0, NULL, NULL, 'texto', NULL, NULL),
(2, 58, 32, 'opa manda proposta', 'texto', NULL, 1, '2026-01-30 15:21:33', 0, NULL, NULL, 'texto', NULL, NULL),
(3, 58, 30, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** Banana\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 10,00\n\n**ID da proposta:** 72', 'texto', NULL, 1, '2026-01-30 15:21:39', 0, NULL, NULL, 'texto', NULL, NULL),
(4, 58, 32, '✅ ACORDO CONCLUÍDO!\n\nAmbas as partes assinaram o acordo digitalmente.\nA proposta foi oficialmente aceita e a compra está confirmada.\n\n', 'texto', NULL, 1, '2026-01-30 15:22:06', 0, NULL, NULL, 'texto', NULL, NULL),
(5, 59, 34, 'oba', 'texto', NULL, 1, '2026-01-30 15:22:35', 0, NULL, NULL, 'texto', NULL, NULL),
(6, 59, 30, '*PROPOSTA DE ENTREGA*\nValor: R$ 100,00\nPrazo: 2026-01-31\nID: 16', '', '{\"proposta_id\":73,\"propostas_transportador_id\":16,\"valor\":\"100\",\"prazo\":\"2026-01-31\"}', 1, '2026-01-30 15:23:00', 0, NULL, NULL, 'texto', NULL, NULL),
(7, 59, 34, '✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.', '', NULL, 1, '2026-01-30 15:23:03', 0, NULL, NULL, 'texto', NULL, NULL),
(8, 60, 34, 'eba', 'texto', NULL, 1, '2026-01-30 15:23:19', 0, NULL, NULL, 'texto', NULL, NULL),
(9, 60, 30, '*PROPOSTA DE ENTREGA*\nValor: R$ 222,00\nPrazo: 2026-01-31\nID: 17', '', '{\"proposta_id\":74,\"propostas_transportador_id\":17,\"valor\":\"222\",\"prazo\":\"2026-01-31\"}', 1, '2026-01-30 15:23:30', 0, NULL, NULL, 'texto', NULL, NULL),
(10, 60, 34, '✅ Proposta aceita. Informações de entrega repassadas ao transportador. Aguarde a coleta e entrega.', '', NULL, 1, '2026-01-30 15:23:32', 0, NULL, NULL, 'texto', NULL, NULL),
(11, 61, 37, 'Olá', 'texto', NULL, 1, '2026-02-04 22:34:24', 0, NULL, NULL, 'texto', NULL, NULL),
(12, 61, 37, 'Quero comprar banana', 'texto', NULL, 1, '2026-02-04 22:34:28', 0, NULL, NULL, 'texto', NULL, NULL),
(13, 61, 37, '**NOVA PROPOSTA DE COMPRA**\n\n**Produto:** Banana\n**Quantidade:** 10 kg\n**Valor unitário:** R$ 5,50\n**Forma de pagamento:** À Vista\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 20,00\n**Valor total:** R$ 75,00', 'texto', NULL, 1, '2026-02-04 22:35:14', 0, NULL, NULL, 'texto', NULL, NULL),
(14, 61, 36, '**PROPOSTA ACEITA PARA ASSINATURA DIGITAL**\n\nO vendedor aceitou a proposta!\nAgora ambas as partes precisam assinar digitalmente o acordo.', 'texto', NULL, 1, '2026-02-04 22:35:40', 0, NULL, NULL, 'texto', NULL, NULL),
(15, 61, 36, '✅ ACORDO CONCLUÍDO!\n\nAmbas as partes assinaram o acordo digitalmente.\nA proposta foi oficialmente aceita e a compra está confirmada.\n\n', 'texto', NULL, 1, '2026-02-04 22:35:57', 0, NULL, NULL, 'texto', NULL, NULL);

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
(14, 31, 'cpf', 'Silene', NULL, '166.076.628-11', NULL, '13214-065', 'Rua Itirapina', '837', '', 'SP', 'Jundiaí', '(11) 99841-8020', '', 'free'),
(15, 37, 'cpf', 'rafael', NULL, '473.486.448-99', NULL, '13218-340', 'Avenida Brígido Marcassa', '70', '', 'SP', 'Jundiaí', '(11) 99771-4455', '', 'free'),
(16, 36, 'cpf', 'Vendedor 1 teste', NULL, '96.071.129/0001-60', NULL, '13218-340', 'Avenida Brígido Marcassa', '70', '', 'SP', 'Jundiaí', '(11) 99771-4455', NULL, 'free');

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
  `assinatura_comprovante` varchar(500) DEFAULT NULL,
  `status_detalhado` enum('aguardando_frete','aguardando_entrega','em_transporte','entregue','finalizada','cancelada') DEFAULT 'aguardando_frete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `entregas`
--

INSERT INTO `entregas` (`id`, `produto_id`, `transportador_id`, `endereco_origem`, `endereco_destino`, `status`, `data_solicitacao`, `valor_frete`, `vendedor_id`, `comprador_id`, `data_aceitacao`, `data_inicio_transporte`, `data_entrega`, `observacoes`, `foto_comprovante`, `assinatura_comprovante`, `status_detalhado`) VALUES
(31, 33, 4, '', 'Rua Seis, 206 - Jundiaí/SP - CEP: 13211-873', 'entregue', '2026-01-28 14:46:43', 100.00, 12, 30, '2026-01-28 14:46:43', NULL, '2026-01-30 00:22:12', '', 'entrega_31_1769732532.png', 'assinatura_31_1769732532.png', 'finalizada'),
(32, 32, 5, '', 'Rua Seis, 206 - Jundiaí/SP - CEP: 13211-873', 'pendente', '2026-01-28 14:53:37', 111.00, 11, 30, '2026-01-28 14:53:37', NULL, NULL, '', NULL, NULL, 'aguardando_entrega'),
(33, 32, 4, '', 'Rua Seis, 206 - Jundiaí/SP - CEP: 13211-873', 'pendente', '2026-01-30 15:23:03', 100.00, 11, 30, '2026-01-30 15:23:03', NULL, NULL, '', NULL, NULL, 'aguardando_entrega'),
(34, 33, 4, '', 'Rua Seis, 206 - Jundiaí/SP - CEP: 13211-873', 'entregue', '2026-01-30 15:23:32', 222.00, 12, 30, '2026-01-30 15:23:32', NULL, '2026-01-30 15:25:50', '', 'entrega_34_1769786750.jpg', 'assinatura_34_1769786750.png', 'finalizada');

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
(10, 32, 'Nova proposta para \'Banana\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=32&conversa_id=47', '2026-01-28 15:06:15'),
(11, 32, 'Nova proposta para \'Banana\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=32&conversa_id=58', '2026-01-30 15:21:39'),
(12, 32, 'Acordo assinado por todas as partes! A proposta para \'\' foi concluída.', 'sucesso', 0, '../../src/chat/chat.php?produto_id=32&conversa_id=', '2026-01-30 15:22:06'),
(13, 1, 'Nova solicitação de cadastro de vendedor: Vendedor', 'info', 0, 'src/admin/solicitacoes.php', '2026-02-04 22:29:33'),
(14, 1, 'Nova solicitação de cadastro de comprador: Rafael', 'info', 0, 'src/admin/solicitacoes.php', '2026-02-04 22:32:04'),
(15, 36, 'Nova proposta para \'Banana\' - Quantidade: 10 kg', 'info', 0, '../../src/chat/chat.php?produto_id=34&conversa_id=61', '2026-02-04 22:35:14'),
(16, 37, 'Acordo assinado por todas as partes! A proposta para \'\' foi concluída.', 'sucesso', 0, '../../src/chat/chat.php?produto_id=34&conversa_id=', '2026-02-04 22:35:57'),
(17, 36, 'Você recebeu uma nova avaliação para seu produto.', 'info', 0, 'src/verperfil.php?usuario_id=36', '2026-02-04 23:28:05');

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
(32, 11, 'Banana', 'bananas amarelas gostosas', 10.00, 'caixa_unidades', NULL, 10, 250, NULL, 0.00, 0, NULL, NULL, 'Frutas Tropicais', '../uploads/produtos/prod_697a16f35b3ee0.25475716.jpeg', 247, 'caixa', 1, 'ativo', '2026-01-28 14:02:27', '2026-01-30 15:22:06'),
(33, 12, 'melancia', 'melancias redondas', 5.00, 'por_unidade', NULL, NULL, 110, NULL, 0.00, 0, NULL, NULL, 'Frutas Vermelhas', '../uploads/produtos/prod_697a1720a5ee03.43633754.jpg', 109, 'unidade', 0, 'ativo', '2026-01-28 14:03:12', '2026-01-28 14:06:26'),
(34, 13, 'Banana', '', 6.00, 'por_quilo', NULL, NULL, NULL, NULL, 0.00, 0, NULL, NULL, 'Frutas Tropicais', '../uploads/produtos/prod_6983c88d9224e4.04124695.webp', 15, 'kg', 0, 'ativo', '2026-02-04 22:30:37', '2026-02-04 22:35:57');

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
(29, 33, '../uploads/produtos/prod_697a1720a5ee03.43633754.jpg', 0, '2026-01-28 14:03:12'),
(30, 34, '../uploads/produtos/prod_6983c88d9224e4.04124695.webp', 0, '2026-02-04 22:30:37');

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
  `status` enum('assinando','aceita','negociacao','recusada','cancelada') DEFAULT 'negociacao',
  `tipo_frete` enum('vendedor','comprador','plataforma') DEFAULT 'vendedor',
  `status_entrega` enum('pendente','aceita','coletado','transporte','entregue','cancelada') DEFAULT 'pendente',
  `data_entrega_estimada` date DEFAULT NULL,
  `transportador_id` int(11) DEFAULT NULL,
  `endereco_vendedor` text DEFAULT NULL,
  `endereco_comprador` text DEFAULT NULL,
  `valor_frete_final` decimal(10,2) DEFAULT NULL,
  `confirmado` tinyint(1) DEFAULT 0,
  `arquivado` tinyint(1) DEFAULT 0,
  `frete_resolvido` tinyint(1) DEFAULT 0 CHECK (`frete_resolvido` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas`
--

INSERT INTO `propostas` (`ID`, `comprador_id`, `vendedor_id`, `produto_id`, `data_inicio`, `data_atualizacao`, `preco_proposto`, `quantidade_proposta`, `forma_pagamento`, `opcao_frete`, `valor_frete`, `valor_total`, `status`, `tipo_frete`, `status_entrega`, `data_entrega_estimada`, `transportador_id`, `endereco_vendedor`, `endereco_comprador`, `valor_frete_final`, `confirmado`, `arquivado`, `frete_resolvido`) VALUES
(67, 30, 32, 32, '2026-01-28 14:03:40', '2026-01-30 15:23:03', 10.00, 2, 'à vista', 'entregador', 0.00, 20.00, 'aceita', 'vendedor', 'pendente', NULL, 4, NULL, NULL, 100.00, 0, 0, 1),
(68, 30, 33, 33, '2026-01-28 14:04:49', '2026-01-30 15:23:32', 5.00, 1, 'à vista', 'entregador', 0.00, 5.00, 'aceita', 'vendedor', 'pendente', NULL, 4, NULL, NULL, 222.00, 0, 0, 1),
(69, 30, 33, 33, '2026-01-28 14:46:36', '2026-01-28 14:46:43', 5.00, 1, 'à vista', 'entregador', 100.00, 100.00, 'aceita', 'vendedor', 'pendente', '2026-01-30', 4, NULL, NULL, NULL, 0, 0, 0),
(70, 30, 32, 32, '2026-01-28 14:53:32', '2026-01-28 14:53:37', 10.00, 1, 'à vista', 'entregador', 111.00, 111.00, 'aceita', 'vendedor', 'pendente', '2026-01-29', 5, NULL, NULL, NULL, 0, 0, 0),
(71, 30, 32, 32, '2026-01-28 15:06:15', '2026-01-30 15:21:15', 10.00, 1, 'à vista', 'entregador', 0.00, 10.00, 'cancelada', 'vendedor', 'pendente', NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(72, 30, 32, 32, '2026-01-30 15:21:39', '2026-01-30 15:22:06', 10.00, 1, 'à vista', 'entregador', 0.00, 10.00, 'aceita', 'vendedor', 'pendente', NULL, NULL, NULL, NULL, NULL, 0, 0, 0),
(73, 30, 32, 32, '2026-01-30 15:23:00', '2026-01-30 15:23:00', 10.00, 1, 'à vista', 'entregador', 100.00, 100.00, 'negociacao', 'vendedor', 'pendente', '2026-01-31', 4, NULL, NULL, NULL, 0, 0, 0),
(74, 30, 33, 33, '2026-01-30 15:23:30', '2026-01-30 15:23:30', 5.00, 1, 'à vista', 'entregador', 222.00, 222.00, 'negociacao', 'vendedor', 'pendente', '2026-01-31', 4, NULL, NULL, NULL, 0, 0, 0),
(75, 37, 36, 34, '2026-02-04 22:35:14', '2026-02-04 22:35:57', 5.50, 10, 'à vista', 'vendedor', 20.00, 75.00, 'aceita', 'vendedor', 'pendente', NULL, NULL, NULL, NULL, NULL, 0, 0, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_assinaturas`
--

CREATE TABLE `propostas_assinaturas` (
  `id` int(11) NOT NULL,
  `proposta_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `assinatura_hash` varchar(255) NOT NULL COMMENT 'Hash SHA256 da assinatura',
  `assinatura_imagem` text DEFAULT NULL COMMENT 'Base64 da imagem da assinatura (opcional)',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `data_assinatura` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas_assinaturas`
--

INSERT INTO `propostas_assinaturas` (`id`, `proposta_id`, `usuario_id`, `assinatura_hash`, `assinatura_imagem`, `ip_address`, `user_agent`, `data_assinatura`) VALUES
(1, 72, 32, 'd842b84db0511dca08146e89fe3f2b27c578f8acb05ab0ca811c6e332452cfef', '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', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-01-30 15:22:00'),
(2, 72, 30, '58e6e5c479e4ca12946f9ba5a3794491677c5c0a78dd076fd2dfbe9983d88331', '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', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 15:22:06'),
(3, 75, 37, 'b002ff3347b0d3eacd4af0099e54a2fb710b4b64d5471d224e96e3c591395de3', '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', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 22:35:50'),
(4, 75, 36, '01c990fe38363e030cb8b4e713fe43097bf8d3d0d550a7355253799c6c6901ca', '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', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2026-02-04 22:35:57');

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
(15, 70, 5, 111.00, 1, '', 'aceita', '2026-01-28 11:53:32', '2026-01-28 11:53:37'),
(16, 73, 4, 100.00, 1, '', 'aceita', '2026-01-30 12:23:00', '2026-01-30 12:23:03'),
(17, 74, 4, 222.00, 1, '', 'aceita', '2026-01-30 12:23:30', '2026-01-30 12:23:32');

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
(6, 35, 'transportador2', 'transportador2@gmail.com', '(11) 99841-8020', 'Rua Seis, 206, Jundiaí, SP', 'transportador', '{\"name\":\"transportador2\",\"email\":\"transportador2@gmail.com\",\"subject\":\"transportador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"(11) 99841-8020\",\"numeroANTT\":\"1111\",\"placaVeiculo\":\"QPS2-F96\",\"modeloVeiculo\":\"sandero\",\"descricaoVeiculo\":\"preto\",\"cepTransportador\":\"13211-873\",\"ruaTransportador\":\"Rua Seis\",\"numeroTransportador\":\"206\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"SP\",\"cidadeTransportador\":\"Jundiaí\",\"message\":\"\",\"senha_hash\":\"$2y$10$J7oxEDC1Heq.gN3.FTDBLenskb4eiXqgrLGOr3TzYhGKAYJgGFGyG\"}', 'aprovado', '2026-01-28 14:00:03', '2026-01-28 14:01:00', NULL, NULL),
(7, 36, 'Vendedor', 'vendedor@gmail.com', '(11) 99771-4455', 'Avenida Brígido Marcassa, 70, Jundiaí, SP', 'vendedor', '{\"name\":\"Vendedor\",\"email\":\"vendedor@gmail.com\",\"subject\":\"vendedor\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"nomeComercialVendedor\":\"Vendedor 1 teste\",\"cpfCnpjVendedor\":\"96.071.129\\/0001-60\",\"cipVendedor\":\"123123123123123\",\"cepVendedor\":\"13218-340\",\"ruaVendedor\":\"Avenida Brígido Marcassa\",\"numeroVendedor\":\"70\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"(11) 99771-4455\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"cepTransportador\":\"\",\"ruaTransportador\":\"\",\"numeroTransportador\":\"\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$K9DbPUsy.6b8yZvG5WSNG.daj3HI3MTV9IzI4\\/bU9EHBX7vGl2JS6\"}', 'aprovado', '2026-02-04 22:29:33', '2026-02-04 22:30:03', NULL, NULL),
(8, 37, 'Rafael', 'rafaeltonetti.cardoso@gmail.com', '(11) 99771-4455', 'Avenida Brígido Marcassa, 70, Jundiaí, SP', 'comprador', '{\"name\":\"Rafael\",\"email\":\"rafaeltonetti.cardoso@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"473.486.448-99\",\"nomeComercialComprador\":\"rafael\",\"cepComprador\":\"13218-340\",\"ruaComprador\":\"Avenida Brígido Marcassa\",\"numeroComprador\":\"70\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99771-4455\",\"telefone2Comprador\":\"\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"cepTransportador\":\"\",\"ruaTransportador\":\"\",\"numeroTransportador\":\"\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$50wXTus323lzAm\\/tExc1SusCJ3jU8duC.cc361x2n55uyxptAbMZ.\"}', 'aprovado', '2026-02-04 22:32:04', '2026-02-04 22:33:12', NULL, NULL);

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
  `reset_token_expira` timestamp NULL DEFAULT NULL,
  `foto_rosto` varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do rosto do usuário',
  `foto_documento_frente` varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do documento (frente)',
  `foto_documento_verso` varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do documento (verso)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `senha`, `tipo`, `nome`, `status`, `data_criacao`, `data_aprovacao`, `reset_token`, `reset_token_expira`, `foto_rosto`, `foto_documento_frente`, `foto_documento_verso`) VALUES
(1, 'admin@encontreocampo.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrador', 'ativo', '2025-11-08 14:33:23', '2025-11-08 14:33:23', NULL, NULL, NULL, NULL, NULL),
(30, 'jorgeappontes13@gmail.com', '$2y$10$CY0vnKldO3Eb/0tu0H2LluSCLCcPgWtyBVLz03X2KVM5LBnWZH8da', 'comprador', 'Jorge', 'ativo', '2026-01-28 13:55:02', NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'silene@gmail.com', '$2y$10$vzuEOlFd1FhOux6mdxY4iuYCqjifjvd9uPn5V9/SS3OsyMi5rEuFC', 'comprador', 'Silene', 'ativo', '2026-01-28 13:56:18', NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'vendedor1@gmail.com', '$2y$10$aXg6jKop7d3j98qXOXnNpeIqHOek6iHgsVeYPJABERWHKtlOWYlGO', 'vendedor', 'vendedor1', 'ativo', '2026-01-28 13:57:22', NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'vendedor2@gmail.com', '$2y$10$oWfsoMmnbpTvCiWoD0y4xOwh1XiNFujUW8VPmRMRukwJCoHAfowmu', 'vendedor', 'vendedor2', 'ativo', '2026-01-28 13:58:32', NULL, NULL, NULL, NULL, NULL, NULL),
(34, 'transportador1@gmail.com', '$2y$10$v0FHugd9.omegQFeNdFtBeWtuND0mHdK3RVrg3t4vPkJe1f6IEn76', 'transportador', 'transportador1', 'ativo', '2026-01-28 13:59:24', NULL, NULL, NULL, NULL, NULL, NULL),
(35, 'transportador2@gmail.com', '$2y$10$J7oxEDC1Heq.gN3.FTDBLenskb4eiXqgrLGOr3TzYhGKAYJgGFGyG', 'transportador', 'transportador2', 'ativo', '2026-01-28 14:00:03', NULL, NULL, NULL, NULL, NULL, NULL),
(36, 'vendedor@gmail.com', '$2y$10$K9DbPUsy.6b8yZvG5WSNG.daj3HI3MTV9IzI4/bU9EHBX7vGl2JS6', 'vendedor', 'Vendedor', 'ativo', '2026-02-04 22:29:33', NULL, NULL, NULL, 'uploads/documentos/1770244173_6983c84dd0e4b_vendedor_rosto.webp', 'uploads/documentos/1770244173_6983c84dd0ff6_vendedor_documento_frente.webp', 'uploads/documentos/1770244173_6983c84dd1243_vendedor_documento_verso.webp'),
(37, 'rafaeltonetti.cardoso@gmail.com', '$2y$10$50wXTus323lzAm/tExc1SusCJ3jU8duC.cc361x2n55uyxptAbMZ.', 'comprador', 'Rafael', 'ativo', '2026-02-04 22:32:04', NULL, NULL, NULL, 'uploads/documentos/1770244324_6983c8e42365a_comprador_rosto.webp', 'uploads/documentos/1770244324_6983c8e423b33_comprador_documento_frente.webp', 'uploads/documentos/1770244324_6983c8e423e69_comprador_documento_verso.webp');

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

--
-- Despejando dados para a tabela `usuario_avisos_preferencias`
--

INSERT INTO `usuario_avisos_preferencias` (`id`, `usuario_id`, `aviso_regioes_entrega`, `data_criacao`, `data_atualizacao`) VALUES
(3, 36, 0, '2026-02-04 22:30:16', NULL);

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
(12, 33, 'cnpj', 'V2', '06.634.459/0001-23', NULL, NULL, '', '13209-772', 'Avenida Comendador Hermes Traldi', '1', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free', NULL, NULL, 1, 'inativo', NULL, NULL, NULL, 0, 0, 0, NULL, NULL),
(13, 36, 'cnpj', 'Vendedor 1 teste', '96.071.129/0001-60', NULL, NULL, '123123123123123', '13218-340', 'Avenida Brígido Marcassa', '70', '', 'SP', 'Jundiaí', '(11) 99771-4455', '', 'free', NULL, NULL, 1, 'inativo', NULL, NULL, NULL, 0, 0, 0, NULL, NULL);

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
-- Índices de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produto` (`produto_id`),
  ADD KEY `idx_vendedor` (`vendedor_id`),
  ADD KEY `idx_avaliador` (`avaliador_usuario_id`);

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
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

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
-- Índices de tabela `propostas_assinaturas`
--
ALTER TABLE `propostas_assinaturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assinatura` (`proposta_id`,`usuario_id`),
  ADD KEY `proposta_id` (`proposta_id`),
  ADD KEY `usuario_id` (`usuario_id`);

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
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `admin_acoes`
--
ALTER TABLE `admin_acoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `chat_auditoria`
--
ALTER TABLE `chat_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de tabela `chat_conversas`
--
ALTER TABLE `chat_conversas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT de tabela `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `compradores`
--
ALTER TABLE `compradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `entregas`
--
ALTER TABLE `entregas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de tabela `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `planos`
--
ALTER TABLE `planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de tabela `produto_imagens`
--
ALTER TABLE `produto_imagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de tabela `propostas`
--
ALTER TABLE `propostas`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT de tabela `propostas_assinaturas`
--
ALTER TABLE `propostas_assinaturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `propostas_transportadores`
--
ALTER TABLE `propostas_transportadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `solicitacoes_cadastro`
--
ALTER TABLE `solicitacoes_cadastro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de tabela `usuario_avisos_preferencias`
--
ALTER TABLE `usuario_avisos_preferencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `vendedores`
--
ALTER TABLE `vendedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `notificacoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

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
-- Restrições para tabelas `propostas_assinaturas`
--
ALTER TABLE `propostas_assinaturas`
  ADD CONSTRAINT `propostas_assinaturas_ibfk_1` FOREIGN KEY (`proposta_id`) REFERENCES `propostas` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_assinaturas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

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
