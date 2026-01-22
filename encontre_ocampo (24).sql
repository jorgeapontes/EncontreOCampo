-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 22/01/2026 às 16:24
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
(27, 1, 'Aprovou cadastro de vendedor (ID: 26)', 'usuarios', 26, '2026-01-14 19:38:29');

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
(1, 7, 3, 'enviar_mensagem', 'Mensagem ID: 15 - Conteúdo: teset', NULL, NULL, '2025-12-11 19:37:57'),
(2, 5, 3, 'enviar_mensagem', 'Mensagem ID: 16 - Conteúdo: ok', NULL, NULL, '2025-12-13 00:29:49'),
(3, 5, 3, 'enviar_mensagem', 'Mensagem ID: 17 - Conteúdo: ok]', NULL, NULL, '2025-12-13 00:30:05'),
(4, 5, 4, 'enviar_mensagem', 'Mensagem ID: 18 - Conteúdo: teste', NULL, NULL, '2025-12-13 00:33:22'),
(5, 5, 3, 'enviar_mensagem', 'Mensagem ID: 19 - Conteúdo: kkkkkk', NULL, NULL, '2025-12-13 00:33:35'),
(6, 5, 3, 'enviar_mensagem', 'Mensagem ID: 20 - Conteúdo: /uploads/chat/img_693d7a51040dd_1765636689.jpeg', NULL, NULL, '2025-12-13 14:38:09'),
(7, 5, 3, 'enviar_mensagem', 'Mensagem ID: 21 - Conteúdo: /uploads/chat/img_693db0ae72df3_1765650606.jpg', NULL, NULL, '2025-12-13 18:30:06'),
(8, 5, 3, 'enviar_mensagem', 'Mensagem ID: 22 - Conteúdo: /EncontreOCampo/uploads/chat/img_693db2dcce582_1765651164.jpg', NULL, NULL, '2025-12-13 18:39:24'),
(9, 7, 3, 'enviar_mensagem', 'Mensagem ID: 23 - Conteúdo: /EncontreOCampo/uploads/chat/img_693db2fe18ed7_1765651198.jpeg', NULL, NULL, '2025-12-13 18:39:58'),
(10, 7, 3, '', 'Ação realizada pelo comprador', NULL, NULL, '2025-12-16 00:28:13'),
(11, 7, 3, '', 'Ação realizada pelo comprador', NULL, NULL, '2025-12-16 00:28:17'),
(12, 7, 3, '', 'Ação realizada pelo comprador', NULL, NULL, '2025-12-16 00:32:06'),
(13, 7, 3, '', 'Comprador excluiu o chat da sua lista', NULL, NULL, '2025-12-16 00:32:16'),
(14, 6, 4, '', 'Ação realizada pelo usuário', NULL, NULL, '2025-12-16 00:39:14'),
(15, 6, 4, '', 'Ação realizada pelo usuário', NULL, NULL, '2025-12-16 00:39:18'),
(16, 5, 4, '', 'Ação realizada pelo usuário', NULL, NULL, '2025-12-16 00:50:48'),
(17, 5, 4, '', 'Ação realizada pelo usuário', NULL, NULL, '2025-12-16 00:50:54'),
(18, 5, 3, '', 'Ação realizada pelo comprador', NULL, NULL, '2025-12-16 14:55:20'),
(19, 5, 3, '', 'Ação realizada pelo comprador', NULL, NULL, '2025-12-16 14:55:23'),
(20, 8, 3, 'enviar_mensagem', 'Mensagem ID: 24 - Conteúdo: quero isso', NULL, NULL, '2025-12-16 17:30:14'),
(21, 8, 4, 'enviar_mensagem', 'Mensagem ID: 25 - Conteúdo: ok', NULL, NULL, '2025-12-16 17:30:21'),
(22, 8, 3, 'enviar_mensagem', 'Mensagem ID: 26 - Conteúdo: produzindo', NULL, NULL, '2025-12-16 17:30:27'),
(23, 8, 4, '', 'Ação realizada pelo usuário', NULL, NULL, '2025-12-16 17:30:37'),
(24, 8, 4, '', 'Ação realizada pelo usuário', NULL, NULL, '2025-12-16 17:30:45'),
(25, 9, 3, 'enviar_mensagem', 'Mensagem ID: 27 - Conteúdo: teste', NULL, NULL, '2025-12-16 20:38:50'),
(26, 9, 4, 'enviar_mensagem', 'Mensagem ID: 28 - Conteúdo: ok', NULL, NULL, '2025-12-16 20:39:08'),
(27, 9, 4, 'enviar_mensagem', 'Mensagem ID: 29 - Conteúdo: testando mensagem', NULL, NULL, '2025-12-16 20:39:18'),
(28, 9, 3, 'enviar_mensagem', 'Mensagem ID: 30 - Conteúdo: ok', NULL, NULL, '2025-12-16 20:39:22'),
(29, 10, 3, 'enviar_mensagem', 'Mensagem ID: 31 - Conteúdo: ok', NULL, NULL, '2025-12-16 21:08:10'),
(30, 10, 4, 'enviar_mensagem', 'Mensagem ID: 32 - Conteúdo: teste', NULL, NULL, '2025-12-16 21:08:26'),
(31, 11, 23, 'enviar_mensagem', 'Mensagem ID: 33 - Conteúdo: oi', NULL, NULL, '2026-01-10 15:30:42'),
(32, 11, 4, 'enviar_mensagem', 'Mensagem ID: 34 - Conteúdo: Olá', NULL, NULL, '2026-01-10 15:34:59'),
(33, 11, 23, 'enviar_mensagem', 'Mensagem ID: 35 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\nProduto: teste x\nQuantidade: 1 unidades\nValor unitário: R$ 8,00\nForma de ', NULL, NULL, '2026-01-10 19:34:58'),
(34, 11, 23, 'enviar_mensagem', 'Mensagem ID: 36 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\nProduto: teste x\nQuantidade: 1 unidades\nValor unitário: R$ 8,00\nForma de ', NULL, NULL, '2026-01-12 22:00:33'),
(35, 11, 23, 'enviar_mensagem', 'Mensagem ID: 37 - Conteúdo: oi', NULL, NULL, '2026-01-12 22:19:02'),
(36, 11, 4, 'enviar_mensagem', 'Mensagem ID: 38 - Conteúdo: olá', NULL, NULL, '2026-01-12 22:19:20'),
(37, 11, 23, 'enviar_mensagem', 'Mensagem ID: 39 - Conteúdo: ???? Nova proposta de compra enviada', NULL, NULL, '2026-01-12 22:19:26'),
(38, 11, 4, 'enviar_mensagem', 'Mensagem ID: 40 - Conteúdo: oi', NULL, NULL, '2026-01-12 22:20:12'),
(39, 11, 23, 'enviar_mensagem', 'Mensagem ID: 41 - Conteúdo: a', NULL, NULL, '2026-01-12 22:20:17'),
(40, 11, 23, 'enviar_mensagem', 'Mensagem ID: 42 - Conteúdo: b', NULL, NULL, '2026-01-12 22:24:09'),
(41, 11, 23, 'enviar_mensagem', 'Mensagem ID: 43 - Conteúdo: ???? Nova proposta de compra enviada', NULL, NULL, '2026-01-12 22:24:21'),
(42, 11, 23, 'enviar_mensagem', 'Mensagem ID: 44 - Conteúdo: ???? Nova proposta de compra enviada', NULL, NULL, '2026-01-12 22:25:15'),
(43, 11, 23, 'enviar_mensagem', 'Mensagem ID: 45 - Conteúdo: Nova proposta de compra enviada', NULL, NULL, '2026-01-12 23:23:25'),
(44, 11, 23, 'enviar_mensagem', 'Mensagem ID: 46 - Conteúdo: Nova proposta de compra enviada', NULL, NULL, '2026-01-12 23:23:33'),
(45, 11, 23, 'enviar_mensagem', 'Mensagem ID: 47 - Conteúdo: Nova proposta de compra enviada', NULL, NULL, '2026-01-12 23:39:52'),
(46, 11, 23, 'enviar_mensagem', 'Mensagem ID: 48 - Conteúdo: Nova proposta de compra enviada', NULL, NULL, '2026-01-12 23:57:59'),
(47, 11, 4, 'enviar_mensagem', 'Mensagem ID: 49 - Conteúdo: Nova proposta de compra enviada', NULL, NULL, '2026-01-12 23:58:44'),
(48, 11, 4, 'enviar_mensagem', 'Mensagem ID: 50 - Conteúdo: Nova proposta de compra enviada', NULL, NULL, '2026-01-12 23:59:02'),
(49, 11, 23, 'enviar_mensagem', 'Mensagem ID: 51 - Conteúdo: ???? Nova proposta de compra', NULL, NULL, '2026-01-13 00:23:25'),
(50, 11, 23, 'enviar_mensagem', 'Mensagem ID: 52 - Conteúdo: ???? Nova proposta de compra', NULL, NULL, '2026-01-13 00:23:40'),
(51, 11, 4, 'enviar_mensagem', 'Mensagem ID: 53 - Conteúdo: ???? Nova proposta de compra', NULL, NULL, '2026-01-13 00:24:07'),
(52, 11, 23, 'enviar_mensagem', 'Mensagem ID: 54 - Conteúdo: ???? Nova proposta de compra', NULL, NULL, '2026-01-13 00:24:43'),
(53, 11, 4, 'enviar_mensagem', 'Mensagem ID: 55 - Conteúdo: ???? Nova proposta de compra', NULL, NULL, '2026-01-13 00:25:16'),
(54, 11, 23, 'enviar_mensagem', 'Mensagem ID: 56 - Conteúdo: a', NULL, NULL, '2026-01-13 02:42:33'),
(55, 11, 23, 'enviar_mensagem', 'Mensagem ID: 57 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 02:57:23'),
(56, 11, 23, 'enviar_mensagem', 'Mensagem ID: 58 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 02:57:46'),
(57, 11, 23, 'enviar_mensagem', 'Mensagem ID: 59 - Conteúdo: teste', NULL, NULL, '2026-01-13 02:59:29'),
(58, 11, 23, 'enviar_mensagem', 'Mensagem ID: 60 - Conteúdo: testando', NULL, NULL, '2026-01-13 02:59:31'),
(59, 11, 4, 'enviar_mensagem', 'Mensagem ID: 61 - Conteúdo: abc', NULL, NULL, '2026-01-13 02:59:37'),
(60, 11, 4, 'enviar_mensagem', 'Mensagem ID: 62 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 02:59:53'),
(61, 11, 23, 'enviar_mensagem', 'Mensagem ID: 63 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:04:09'),
(62, 11, 23, 'enviar_mensagem', 'Mensagem ID: 64 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:37:50'),
(63, 12, 23, 'enviar_mensagem', 'Mensagem ID: 65 - Conteúdo: oi', NULL, NULL, '2026-01-13 03:38:09'),
(64, 12, 4, 'enviar_mensagem', 'Mensagem ID: 66 - Conteúdo: olá', NULL, NULL, '2026-01-13 03:38:19'),
(65, 12, 23, 'enviar_mensagem', 'Mensagem ID: 67 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:38:23'),
(66, 12, 23, 'enviar_mensagem', 'Mensagem ID: 68 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:38:54'),
(67, 12, 23, 'enviar_mensagem', 'Mensagem ID: 69 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:40:52'),
(68, 12, 23, 'enviar_mensagem', 'Mensagem ID: 70 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:41:41'),
(70, 12, 23, 'enviar_mensagem', 'Mensagem ID: 72 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:43:31'),
(71, 12, 23, 'enviar_mensagem', 'Mensagem ID: 73 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:44:31'),
(72, 12, 4, 'enviar_mensagem', 'Mensagem ID: 74 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:45:07'),
(73, 11, 4, 'enviar_mensagem', 'Mensagem ID: 75 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:45:28'),
(74, 11, 23, 'enviar_mensagem', 'Mensagem ID: 76 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:47:42'),
(75, 11, 23, 'enviar_mensagem', 'Mensagem ID: 77 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 03:48:04'),
(76, 11, 23, 'enviar_mensagem', 'Mensagem ID: 78 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:16:45'),
(77, 11, 23, 'enviar_mensagem', 'Mensagem ID: 79 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:17:44'),
(78, 11, 23, 'enviar_mensagem', 'Mensagem ID: 80 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:17:57'),
(79, 11, 23, 'enviar_mensagem', 'Mensagem ID: 81 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:20:05'),
(80, 11, 4, 'enviar_mensagem', 'Mensagem ID: 82 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:24:23'),
(81, 11, 23, 'enviar_mensagem', 'Mensagem ID: 83 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:26:30'),
(82, 11, 23, 'enviar_mensagem', 'Mensagem ID: 84 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:26:50'),
(83, 13, 23, 'enviar_mensagem', 'Mensagem ID: 85 - Conteúdo: teste', NULL, NULL, '2026-01-13 04:28:34'),
(84, 13, 4, 'enviar_mensagem', 'Mensagem ID: 86 - Conteúdo: testando', NULL, NULL, '2026-01-13 04:28:40'),
(85, 13, 23, 'enviar_mensagem', 'Mensagem ID: 87 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:28:52'),
(86, 13, 4, 'enviar_mensagem', 'Mensagem ID: 88 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:29:21'),
(87, 13, 23, 'enviar_mensagem', 'Mensagem ID: 89 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:32:04'),
(88, 13, 4, 'enviar_mensagem', 'Mensagem ID: 90 - Conteúdo: Nova proposta de compra', NULL, NULL, '2026-01-13 04:32:50'),
(89, 14, 23, 'enviar_mensagem', 'Mensagem ID: 91 - Conteúdo: oi', NULL, NULL, '2026-01-20 15:53:32'),
(90, 14, 4, 'enviar_mensagem', 'Mensagem ID: 92 - Conteúdo: olá', NULL, NULL, '2026-01-20 15:53:39'),
(91, 14, 4, 'enviar_mensagem', 'Mensagem ID: 93 - Conteúdo: vamos testar propostas BD', NULL, NULL, '2026-01-20 16:08:22'),
(92, 14, 23, 'enviar_mensagem', 'Mensagem ID: 94 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10', NULL, NULL, '2026-01-20 16:08:37'),
(93, 14, 4, 'enviar_mensagem', 'Mensagem ID: 95 - Conteúdo: insert no BD funcionando', NULL, NULL, '2026-01-20 16:11:37'),
(94, 14, 4, 'enviar_mensagem', 'Mensagem ID: 96 - Conteúdo: a', NULL, NULL, '2026-01-20 17:33:53'),
(95, 14, 23, 'enviar_mensagem', 'Mensagem ID: 97 - Conteúdo: b', NULL, NULL, '2026-01-20 17:33:56'),
(96, 14, 23, 'enviar_mensagem', 'Mensagem ID: 98 - Conteúdo: teste', NULL, NULL, '2026-01-20 17:34:55'),
(97, 14, 23, 'enviar_mensagem', 'Mensagem ID: 99 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-20 17:37:48'),
(98, 14, 23, 'enviar_mensagem', 'Mensagem ID: 100 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 4,', NULL, NULL, '2026-01-20 17:38:32'),
(99, 14, 23, 'enviar_mensagem', 'Mensagem ID: 101 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-20 17:41:44'),
(100, 14, 23, 'enviar_mensagem', 'Mensagem ID: 102 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 7,', NULL, NULL, '2026-01-20 17:42:12'),
(101, 14, 23, 'enviar_mensagem', 'Mensagem ID: 103 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-20 17:48:02'),
(102, 14, 23, 'enviar_mensagem', 'Mensagem ID: 104 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 9,', NULL, NULL, '2026-01-20 17:59:48'),
(103, 14, 23, 'enviar_mensagem', 'Mensagem ID: 105 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10', NULL, NULL, '2026-01-20 18:01:57'),
(104, 14, 23, 'enviar_mensagem', 'Mensagem ID: 106 - Conteúdo: a', NULL, NULL, '2026-01-20 18:06:05'),
(105, 14, 4, 'enviar_mensagem', 'Mensagem ID: 107 - Conteúdo: b', NULL, NULL, '2026-01-20 18:06:07'),
(106, 14, 23, 'enviar_mensagem', 'Mensagem ID: 108 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-20 18:06:27'),
(107, 15, 23, 'enviar_mensagem', 'Mensagem ID: 109 - Conteúdo: a', NULL, NULL, '2026-01-20 18:16:18'),
(108, 15, 4, 'enviar_mensagem', 'Mensagem ID: 110 - Conteúdo: b', NULL, NULL, '2026-01-20 18:16:33'),
(109, 15, 23, 'enviar_mensagem', 'Mensagem ID: 111 - Conteúdo: *???? NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** ', NULL, NULL, '2026-01-20 18:16:42'),
(110, 15, 23, 'enviar_mensagem', 'Mensagem ID: 112 - Conteúdo: *???? PROPOSTA ATUALIZADA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 5', NULL, NULL, '2026-01-20 18:17:06'),
(111, 15, 23, 'enviar_mensagem', 'Mensagem ID: 113 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10', NULL, NULL, '2026-01-20 18:26:52'),
(112, 15, 23, 'enviar_mensagem', 'Mensagem ID: 114 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 9,', NULL, NULL, '2026-01-20 18:38:29'),
(113, 15, 23, 'enviar_mensagem', 'Mensagem ID: 115 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-20 23:19:10'),
(114, 15, 23, 'enviar_mensagem', 'Mensagem ID: 116 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 7,', NULL, NULL, '2026-01-20 23:19:29'),
(115, 16, 23, 'enviar_mensagem', 'Mensagem ID: 117 - Conteúdo: oi', NULL, NULL, '2026-01-21 01:12:16'),
(116, 16, 4, 'enviar_mensagem', 'Mensagem ID: 118 - Conteúdo: olá', NULL, NULL, '2026-01-21 01:12:23'),
(117, 16, 23, 'enviar_mensagem', 'Mensagem ID: 119 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 01:14:54'),
(118, 17, 23, 'enviar_mensagem', 'Mensagem ID: 120 - Conteúdo: oi', NULL, NULL, '2026-01-21 15:49:16'),
(119, 17, 4, 'enviar_mensagem', 'Mensagem ID: 121 - Conteúdo: olá', NULL, NULL, '2026-01-21 15:49:32'),
(120, 17, 23, 'enviar_mensagem', 'Mensagem ID: 122 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10', NULL, NULL, '2026-01-21 15:49:43'),
(121, 17, 23, 'enviar_mensagem', 'Mensagem ID: 123 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 15:49:56'),
(122, 17, 4, 'enviar_mensagem', 'Mensagem ID: 124 - Conteúdo: teste', NULL, NULL, '2026-01-21 15:50:10'),
(123, 17, 23, 'enviar_mensagem', 'Mensagem ID: 125 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 9,', NULL, NULL, '2026-01-21 15:50:41'),
(124, 17, 4, 'enviar_mensagem', 'Mensagem ID: 126 - Conteúdo: a', NULL, NULL, '2026-01-21 15:51:05'),
(125, 17, 23, 'enviar_mensagem', 'Mensagem ID: 127 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 15:57:30'),
(126, 17, 4, 'enviar_mensagem', 'Mensagem ID: 128 - Conteúdo: não mudou', NULL, NULL, '2026-01-21 15:57:52'),
(127, 17, 4, 'enviar_mensagem', 'Mensagem ID: 129 - Conteúdo: oi', NULL, NULL, '2026-01-21 16:06:04'),
(128, 17, 23, 'enviar_mensagem', 'Mensagem ID: 130 - Conteúdo: olá', NULL, NULL, '2026-01-21 16:06:07'),
(129, 17, 23, 'enviar_mensagem', 'Mensagem ID: 131 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 5,', NULL, NULL, '2026-01-21 16:06:15'),
(130, 17, 23, 'enviar_mensagem', 'Mensagem ID: 132 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 16:07:27'),
(131, 17, 23, 'enviar_mensagem', 'Mensagem ID: 133 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 3,', NULL, NULL, '2026-01-21 16:30:10'),
(132, 17, 4, 'enviar_mensagem', 'Mensagem ID: 134 - Conteúdo: aqui nada', NULL, NULL, '2026-01-21 16:30:19'),
(133, 17, 4, 'enviar_mensagem', 'Mensagem ID: 135 - Conteúdo: oi', NULL, NULL, '2026-01-21 17:01:26'),
(134, 17, 23, 'enviar_mensagem', 'Mensagem ID: 136 - Conteúdo: oi', NULL, NULL, '2026-01-21 17:01:28'),
(135, 17, 23, 'enviar_mensagem', 'Mensagem ID: 137 - Conteúdo: oi', NULL, NULL, '2026-01-21 17:01:35'),
(136, 17, 23, 'enviar_mensagem', 'Mensagem ID: 138 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 17:01:47'),
(137, 17, 23, 'enviar_mensagem', 'Mensagem ID: 139 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 17:02:29'),
(138, 17, 23, 'enviar_mensagem', 'Mensagem ID: 140 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 17:10:48'),
(139, 18, 23, 'enviar_mensagem', 'Mensagem ID: 141 - Conteúdo: oi', NULL, NULL, '2026-01-21 17:12:04'),
(140, 18, 4, 'enviar_mensagem', 'Mensagem ID: 142 - Conteúdo: olá', NULL, NULL, '2026-01-21 17:12:10'),
(141, 18, 23, 'enviar_mensagem', 'Mensagem ID: 143 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 17:12:19'),
(142, 18, 23, 'enviar_mensagem', 'Mensagem ID: 144 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10', NULL, NULL, '2026-01-21 17:12:52'),
(143, 18, 23, 'enviar_mensagem', 'Mensagem ID: 145 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 70', NULL, NULL, '2026-01-21 17:15:45'),
(144, 18, 23, 'enviar_mensagem', 'Mensagem ID: 146 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 17:16:45'),
(145, 18, 23, 'enviar_mensagem', 'Mensagem ID: 147 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 17:57:34'),
(146, 18, 23, 'enviar_mensagem', 'Mensagem ID: 148 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10', NULL, NULL, '2026-01-21 17:59:44'),
(147, 18, 23, 'enviar_mensagem', 'Mensagem ID: 149 - Conteúdo: ???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi cancelou.\n**ID da proposta:** 17\n**Status:** ❌ Re', NULL, NULL, '2026-01-21 18:07:56'),
(148, 18, 23, 'enviar_mensagem', 'Mensagem ID: 150 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 18:09:03'),
(149, 18, 23, 'enviar_mensagem', 'Mensagem ID: 151 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10', NULL, NULL, '2026-01-21 18:10:26'),
(150, 18, 4, 'enviar_mensagem', 'Mensagem ID: 152 - Conteúdo: oi', NULL, NULL, '2026-01-21 18:10:36'),
(151, 18, 4, 'enviar_mensagem', 'Mensagem ID: 153 - Conteúdo: ???? *PROPOSTA ACEITA*\n\nA proposta de compra foi aceitou.\n**ID da proposta:** 18\n**Status:** ✅ Aceit', NULL, NULL, '2026-01-21 18:20:58'),
(152, 18, 23, 'enviar_mensagem', 'Mensagem ID: 154 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 18:21:33'),
(153, 18, 4, 'enviar_mensagem', 'Mensagem ID: 155 - Conteúdo: ???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi recusada.\n**ID da proposta:** 19\n**Status:** ❌ Re', NULL, NULL, '2026-01-21 18:21:39'),
(154, 18, 23, 'enviar_mensagem', 'Mensagem ID: 156 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 18:22:44'),
(155, 18, 23, 'enviar_mensagem', 'Mensagem ID: 157 - Conteúdo: ???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi cancelada.\n**ID da proposta:** 20\n**Status:** ❌ R', NULL, NULL, '2026-01-21 18:22:48'),
(156, 18, 23, 'enviar_mensagem', 'Mensagem ID: 158 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 18:24:29'),
(157, 18, 23, 'enviar_mensagem', 'Mensagem ID: 159 - Conteúdo: ???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi cancelou.\n**ID da proposta:** 21\n**Status:** ❌ Re', NULL, NULL, '2026-01-21 18:30:12'),
(158, 18, 23, 'enviar_mensagem', 'Mensagem ID: 160 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 9,', NULL, NULL, '2026-01-21 18:30:27'),
(159, 18, 4, 'enviar_mensagem', 'Mensagem ID: 161 - Conteúdo: oi', NULL, NULL, '2026-01-21 18:30:51'),
(160, 18, 23, 'enviar_mensagem', 'Mensagem ID: 162 - Conteúdo: ???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi cancelou.\n**ID da proposta:** 22\n**Status:** ❌ Re', NULL, NULL, '2026-01-21 18:33:08'),
(161, 18, 23, 'enviar_mensagem', 'Mensagem ID: 163 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,', NULL, NULL, '2026-01-21 18:33:21'),
(162, 18, 4, 'enviar_mensagem', 'Mensagem ID: 164 - Conteúdo: oi', NULL, NULL, '2026-01-21 18:49:23'),
(163, 18, 23, 'enviar_mensagem', 'Mensagem ID: 165 - Conteúdo: olá', NULL, NULL, '2026-01-21 18:49:28'),
(164, 19, 23, 'enviar_mensagem', 'Mensagem ID: 166 - Conteúdo: oi', NULL, NULL, '2026-01-21 19:07:02'),
(165, 19, 23, 'enviar_mensagem', 'Mensagem ID: 167 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,', NULL, NULL, '2026-01-21 19:07:16'),
(166, 19, 23, 'enviar_mensagem', 'Mensagem ID: 168 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,', NULL, NULL, '2026-01-21 19:07:38'),
(167, 19, 23, 'enviar_mensagem', 'Mensagem ID: 169 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,', NULL, NULL, '2026-01-21 19:08:28'),
(168, 19, 23, 'enviar_mensagem', 'Mensagem ID: 170 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,', NULL, NULL, '2026-01-21 19:10:18'),
(169, 19, 23, 'enviar_mensagem', 'Mensagem ID: 171 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,', NULL, NULL, '2026-01-21 19:10:39'),
(170, 19, 23, 'enviar_mensagem', 'Mensagem ID: 172 - Conteúdo: oi', NULL, NULL, '2026-01-21 19:11:56'),
(171, 19, 4, 'enviar_mensagem', 'Mensagem ID: 173 - Conteúdo: olá', NULL, NULL, '2026-01-21 19:11:59'),
(172, 19, 4, 'enviar_mensagem', 'Mensagem ID: 174 - Conteúdo: certo', NULL, NULL, '2026-01-21 19:12:01'),
(173, 19, 23, 'enviar_mensagem', 'Mensagem ID: 175 - Conteúdo: certo', NULL, NULL, '2026-01-21 19:12:03'),
(174, 18, 23, 'enviar_mensagem', 'Mensagem ID: 176 - Conteúdo: *NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 2,', NULL, NULL, '2026-01-21 19:15:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_conversas`
--

CREATE TABLE `chat_conversas` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `comprador_id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
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
  `ultimo_user_agent_vendedor` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `chat_conversas`
--

INSERT INTO `chat_conversas` (`id`, `produto_id`, `comprador_id`, `vendedor_id`, `ultima_mensagem`, `ultima_mensagem_data`, `comprador_lido`, `vendedor_lido`, `status`, `data_criacao`, `deletado`, `data_delecao`, `usuario_deletou`, `favorito_comprador`, `favorito_vendedor`, `comprador_excluiu`, `vendedor_excluiu`, `ultimo_ip_comprador`, `ultimo_ip_vendedor`, `ultimo_user_agent_comprador`, `ultimo_user_agent_vendedor`) VALUES
(5, 16, 3, 4, '[Imagem]', '2025-12-13 18:39:24', 1, 0, 'ativo', '2025-12-10 16:46:53', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL),
(6, 19, 4, 9, 'ok', '2025-12-10 17:04:15', 0, 1, 'ativo', '2025-12-10 17:04:03', 0, NULL, NULL, 0, 0, 1, 0, NULL, NULL, NULL, NULL),
(7, 19, 3, 9, '[Imagem]', '2025-12-13 18:39:58', 1, 0, 'ativo', '2025-12-11 19:37:54', 0, NULL, NULL, 1, 0, 1, 0, NULL, NULL, NULL, NULL),
(8, 25, 3, 4, 'produzindo', '2025-12-16 17:30:27', 1, 0, 'ativo', '2025-12-16 17:30:09', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL),
(9, 20, 3, 4, 'ok', '2025-12-16 20:39:22', 1, 0, 'ativo', '2025-12-16 20:38:44', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL),
(10, 24, 3, 4, 'teste', '2025-12-16 21:08:26', 0, 1, 'ativo', '2025-12-16 21:08:06', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL),
(18, 16, 23, 4, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 2,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 2,00\n\n**ID da proposta:** 29', '2026-01-21 19:15:12', 1, 0, 'ativo', '2026-01-21 17:12:02', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL),
(19, 15, 23, 4, 'certo', '2026-01-21 19:12:03', 1, 0, 'ativo', '2026-01-21 19:07:00', 0, NULL, NULL, 0, 0, 0, 0, NULL, NULL, NULL, NULL);

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
(12, 5, 3, 'teste', 'texto', NULL, 1, '2025-12-10 16:47:05', 0, NULL, NULL, 'texto', NULL, NULL),
(13, 6, 4, 'quero', 'texto', NULL, 1, '2025-12-10 17:04:06', 0, NULL, NULL, 'texto', NULL, NULL),
(14, 6, 9, 'ok', 'texto', NULL, 1, '2025-12-10 17:04:15', 0, NULL, NULL, 'texto', NULL, NULL),
(15, 7, 3, 'teset', 'texto', NULL, 0, '2025-12-11 19:37:57', 0, NULL, NULL, 'texto', NULL, NULL),
(16, 5, 3, 'ok', 'texto', NULL, 1, '2025-12-13 00:29:49', 0, NULL, NULL, 'texto', NULL, NULL),
(17, 5, 3, 'ok]', 'texto', NULL, 1, '2025-12-13 00:30:05', 0, NULL, NULL, 'texto', NULL, NULL),
(18, 5, 4, 'teste', 'texto', NULL, 1, '2025-12-13 00:33:22', 0, NULL, NULL, 'texto', NULL, NULL),
(19, 5, 3, 'kkkkkk', 'texto', NULL, 1, '2025-12-13 00:33:35', 0, NULL, NULL, 'texto', NULL, NULL),
(20, 5, 3, '/uploads/chat/img_693d7a51040dd_1765636689.jpeg', 'imagem', NULL, 1, '2025-12-13 14:38:09', 0, NULL, NULL, 'texto', NULL, NULL),
(21, 5, 3, '/uploads/chat/img_693db0ae72df3_1765650606.jpg', 'imagem', NULL, 1, '2025-12-13 18:30:06', 0, NULL, NULL, 'texto', NULL, NULL),
(22, 5, 3, '/EncontreOCampo/uploads/chat/img_693db2dcce582_1765651164.jpg', 'imagem', NULL, 1, '2025-12-13 18:39:24', 0, NULL, NULL, 'texto', NULL, NULL),
(23, 7, 3, '/EncontreOCampo/uploads/chat/img_693db2fe18ed7_1765651198.jpeg', 'imagem', NULL, 0, '2025-12-13 18:39:58', 0, NULL, NULL, 'texto', NULL, NULL),
(24, 8, 3, 'quero isso', 'texto', NULL, 1, '2025-12-16 17:30:14', 0, NULL, NULL, 'texto', NULL, NULL),
(25, 8, 4, 'ok', 'texto', NULL, 1, '2025-12-16 17:30:21', 0, NULL, NULL, 'texto', NULL, NULL),
(26, 8, 3, 'produzindo', 'texto', NULL, 1, '2025-12-16 17:30:27', 0, NULL, NULL, 'texto', NULL, NULL),
(27, 9, 3, 'teste', 'texto', NULL, 1, '2025-12-16 20:38:50', 0, NULL, NULL, 'texto', NULL, NULL),
(28, 9, 4, 'ok', 'texto', NULL, 1, '2025-12-16 20:39:08', 0, NULL, NULL, 'texto', NULL, NULL),
(29, 9, 4, 'testando mensagem', 'texto', NULL, 1, '2025-12-16 20:39:18', 0, NULL, NULL, 'texto', NULL, NULL),
(30, 9, 3, 'ok', 'texto', NULL, 1, '2025-12-16 20:39:22', 0, NULL, NULL, 'texto', NULL, NULL),
(31, 10, 3, 'ok', 'texto', NULL, 1, '2025-12-16 21:08:10', 0, NULL, NULL, 'texto', NULL, NULL),
(32, 10, 4, 'teste', 'texto', NULL, 1, '2025-12-16 21:08:26', 0, NULL, NULL, 'texto', NULL, NULL),
(141, 18, 23, 'oi', 'texto', NULL, 1, '2026-01-21 17:12:04', 0, NULL, NULL, 'texto', NULL, NULL),
(142, 18, 4, 'olá', 'texto', NULL, 1, '2026-01-21 17:12:10', 0, NULL, NULL, 'texto', NULL, NULL),
(143, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Retirada pelo comprador\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 8,00\n\n**ID da proposta:** 17', 'texto', NULL, 1, '2026-01-21 17:12:19', 0, NULL, NULL, 'texto', NULL, NULL),
(144, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,00\n**Forma de pagamento:** Pagamento na Entrega\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 5,00\n**Valor total:** R$ 15,00\n\n**ID da proposta:** 17', 'texto', NULL, 1, '2026-01-21 17:12:52', 0, NULL, NULL, 'texto', NULL, NULL),
(145, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 70,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 70,00\n\n**ID da proposta:** 17', 'texto', NULL, 1, '2026-01-21 17:15:45', 0, NULL, NULL, 'texto', NULL, NULL),
(146, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 8,00\n\n**ID da proposta:** 17', 'texto', NULL, 1, '2026-01-21 17:16:45', 0, NULL, NULL, 'texto', NULL, NULL),
(147, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 8,00\n\n**ID da proposta:** 17', 'texto', NULL, 1, '2026-01-21 17:57:34', 0, NULL, NULL, 'texto', NULL, NULL),
(148, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 10,00\n\n**ID da proposta:** 17', 'texto', NULL, 1, '2026-01-21 17:59:44', 0, NULL, NULL, 'texto', NULL, NULL),
(149, 18, 23, '???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi cancelou.\n**ID da proposta:** 17\n**Status:** ❌ Recusada', 'texto', NULL, 1, '2026-01-21 18:07:56', 0, NULL, NULL, 'texto', NULL, NULL),
(150, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Retirada pelo comprador\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 8,00\n\n**ID da proposta:** 18', 'texto', NULL, 1, '2026-01-21 18:09:03', 0, NULL, NULL, 'texto', NULL, NULL),
(151, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 10,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 5,00\n**Valor total:** R$ 15,00\n\n**ID da proposta:** 18', 'texto', NULL, 1, '2026-01-21 18:10:26', 0, NULL, NULL, 'texto', NULL, NULL),
(152, 18, 4, 'oi', 'texto', NULL, 1, '2026-01-21 18:10:36', 0, NULL, NULL, 'texto', NULL, NULL),
(153, 18, 4, '???? *PROPOSTA ACEITA*\n\nA proposta de compra foi aceitou.\n**ID da proposta:** 18\n**Status:** ✅ Aceita', 'texto', NULL, 1, '2026-01-21 18:20:58', 0, NULL, NULL, 'texto', NULL, NULL),
(154, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 3,00\n**Valor total:** R$ 11,00\n\n**ID da proposta:** 19', 'texto', NULL, 1, '2026-01-21 18:21:33', 0, NULL, NULL, 'texto', NULL, NULL),
(155, 18, 4, '???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi recusada.\n**ID da proposta:** 19\n**Status:** ❌ Recusada', 'texto', NULL, 1, '2026-01-21 18:21:39', 0, NULL, NULL, 'texto', NULL, NULL),
(156, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 8,00\n\n**ID da proposta:** 20', 'texto', NULL, 1, '2026-01-21 18:22:44', 0, NULL, NULL, 'texto', NULL, NULL),
(157, 18, 23, '???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi cancelada.\n**ID da proposta:** 20\n**Status:** ❌ Recusada', 'texto', NULL, 1, '2026-01-21 18:22:48', 0, NULL, NULL, 'texto', NULL, NULL),
(158, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 2,00\n**Valor total:** R$ 10,00\n\n**ID da proposta:** 21', 'texto', NULL, 1, '2026-01-21 18:24:29', 0, NULL, NULL, 'texto', NULL, NULL),
(159, 18, 23, '???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi cancelou.\n**ID da proposta:** 21\n**Status:** ❌ Recusada', 'texto', NULL, 1, '2026-01-21 18:30:12', 0, NULL, NULL, 'texto', NULL, NULL),
(160, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 9,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Retirada pelo comprador\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 9,00\n\n**ID da proposta:** 22', 'texto', NULL, 1, '2026-01-21 18:30:27', 0, NULL, NULL, 'texto', NULL, NULL),
(161, 18, 4, 'oi', 'texto', NULL, 1, '2026-01-21 18:30:51', 0, NULL, NULL, 'texto', NULL, NULL),
(162, 18, 23, '???? *PROPOSTA RECUSADA*\n\nA proposta de compra foi cancelou.\n**ID da proposta:** 22\n**Status:** ❌ Recusada', 'texto', NULL, 1, '2026-01-21 18:33:08', 0, NULL, NULL, 'texto', NULL, NULL),
(163, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 8,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 8,00\n\n**ID da proposta:** 23', 'texto', NULL, 1, '2026-01-21 18:33:21', 0, NULL, NULL, 'texto', NULL, NULL),
(164, 18, 4, 'oi', 'texto', NULL, 1, '2026-01-21 18:49:23', 0, NULL, NULL, 'texto', NULL, NULL),
(165, 18, 23, 'olá', 'texto', NULL, 1, '2026-01-21 18:49:28', 0, NULL, NULL, 'texto', NULL, NULL),
(166, 19, 23, 'oi', 'texto', NULL, 1, '2026-01-21 19:07:02', 0, NULL, NULL, 'texto', NULL, NULL),
(167, 19, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,01\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Retirada pelo comprador\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 0,01\n\n**ID da proposta:** 24', 'texto', NULL, 1, '2026-01-21 19:07:16', 0, NULL, NULL, 'texto', NULL, NULL),
(168, 19, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,01\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 10,00\n**Valor total:** R$ 10,01\n\n**ID da proposta:** 25', 'texto', NULL, 1, '2026-01-21 19:07:38', 0, NULL, NULL, 'texto', NULL, NULL),
(169, 19, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,01\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 0,01\n\n**ID da proposta:** 26', 'texto', NULL, 1, '2026-01-21 19:08:28', 0, NULL, NULL, 'texto', NULL, NULL),
(170, 19, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,01\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 0,01\n\n**ID da proposta:** 27', 'texto', NULL, 1, '2026-01-21 19:10:18', 0, NULL, NULL, 'texto', NULL, NULL),
(171, 19, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste 1\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 0,01\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Buscar transportador na plataforma\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 0,01\n\n**ID da proposta:** 28', 'texto', NULL, 1, '2026-01-21 19:10:39', 0, NULL, NULL, 'texto', NULL, NULL),
(172, 19, 23, 'oi', 'texto', NULL, 1, '2026-01-21 19:11:56', 0, NULL, NULL, 'texto', NULL, NULL),
(173, 19, 4, 'olá', 'texto', NULL, 1, '2026-01-21 19:11:59', 0, NULL, NULL, 'texto', NULL, NULL),
(174, 19, 4, 'certo', 'texto', NULL, 1, '2026-01-21 19:12:01', 0, NULL, NULL, 'texto', NULL, NULL),
(175, 19, 23, 'certo', 'texto', NULL, 1, '2026-01-21 19:12:03', 0, NULL, NULL, 'texto', NULL, NULL),
(176, 18, 23, '*NOVA PROPOSTA DE COMPRA*\n\n**Produto:** teste x\n**Quantidade:** 1 unidades\n**Valor unitário:** R$ 2,00\n**Forma de pagamento:** Pagamento no Ato\n**Opção de frete:** Frete por conta do vendedor\n**Valor do frete:** R$ 0,00\n**Valor total:** R$ 2,00\n\n**ID da proposta:** 29', 'texto', NULL, 1, '2026-01-21 19:15:12', 0, NULL, NULL, 'texto', NULL, NULL);

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
(1, 3, NULL, 'Jorge Pontes', NULL, '411.115.848-00', NULL, '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(3, 13, 'cpf', 'Jorginho', NULL, '411.115.848-00', '', '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free'),
(4, 4, 'cpf', 'Vendedor', NULL, '11.111.111/1111-11', NULL, '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(5, 14, 'cpf', 'teste', NULL, '111111111111111111', NULL, '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '11111111111', NULL, 'free'),
(6, 11, 'cpf', 'Rondon', NULL, '41111584800', NULL, '13211873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(9, 17, 'cpf', 'Jorge', NULL, '166.076.628-11', NULL, '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free'),
(10, 18, 'cpf', 'matue', NULL, '166.076.628-11', NULL, '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free'),
(11, 23, 'cpf', 'Comprador2', NULL, '239.186.590-20', '', '01518-000', 'Rua Justo Azambuja', '79', '', 'SP', 'São Paulo', '(11) 11111-1111', '', 'free');

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
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(74, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 15:49:43'),
(75, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 15:49:56'),
(76, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 15:50:41'),
(77, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 15:57:30'),
(78, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 16:06:15'),
(79, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 16:07:27'),
(80, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 16:30:10'),
(81, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 17:01:47'),
(82, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 17:02:29'),
(83, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=17', '2026-01-21 17:10:48'),
(84, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 17:12:19'),
(85, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 17:12:52'),
(86, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 17:15:45'),
(87, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 17:16:45'),
(88, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 17:57:34'),
(89, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 17:59:44'),
(90, 4, 'Comprador cancelou sua proposta para \'teste x\'', 'info', 0, '../../src/chat/chat.php?produto_id=16', '2026-01-21 18:07:56'),
(91, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 18:09:03'),
(92, 4, 'Proposta atualizada para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 18:10:26'),
(93, 23, 'Vendedor aceitou sua proposta para \'teste x\'', 'info', 0, '../../src/chat/chat.php?produto_id=16', '2026-01-21 18:20:58'),
(94, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 18:21:33'),
(95, 23, 'Vendedor recusada sua proposta para \'teste x\'', 'info', 0, '../../src/chat/chat.php?produto_id=16', '2026-01-21 18:21:39'),
(96, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 18:22:44'),
(97, 4, 'Comprador cancelada sua proposta para \'teste x\'', 'info', 0, '../../src/chat/chat.php?produto_id=16', '2026-01-21 18:22:48'),
(98, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 18:24:29'),
(99, 4, 'Comprador cancelou sua proposta para \'teste x\'', 'info', 0, '../../src/chat/chat.php?produto_id=16', '2026-01-21 18:30:12'),
(100, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 18:30:27'),
(101, 4, 'Comprador cancelou sua proposta para \'teste x\'', 'info', 0, '../../src/chat/chat.php?produto_id=16', '2026-01-21 18:33:08'),
(102, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 18:33:21'),
(103, 4, 'Nova proposta para \'teste 1\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=15&conversa_id=19', '2026-01-21 19:07:16'),
(104, 4, 'Nova proposta para \'teste 1\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=15&conversa_id=19', '2026-01-21 19:07:38'),
(105, 4, 'Nova proposta para \'teste 1\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=15&conversa_id=19', '2026-01-21 19:08:28'),
(106, 4, 'Nova proposta para \'teste 1\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=15&conversa_id=19', '2026-01-21 19:10:18'),
(107, 4, 'Nova proposta para \'teste 1\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=15&conversa_id=19', '2026-01-21 19:10:39'),
(108, 4, 'Nova proposta para \'teste x\' - Quantidade: 1 unidades', 'info', 0, '../../src/chat/chat.php?produto_id=16&conversa_id=18', '2026-01-21 19:15:12');

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
(15, 1, 'teste 1', '1', 0.01, 'por_quilo', NULL, NULL, 10, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_69398052be9755.34966564.jpeg', 0, NULL, 0, 'ativo', '2025-12-10 14:14:42', '2026-01-21 19:13:00'),
(16, 1, 'teste x', '', 10.00, 'por_quilo', NULL, NULL, NULL, 8.00, 20.00, 1, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_69398067b3f823.59696952.jpg', 9, 'kg', 0, 'ativo', '2025-12-10 14:15:03', '2026-01-21 19:15:20'),
(17, 1, 'testenovop', '1', 0.01, 'por_quilo', NULL, NULL, 0, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_693983f26c4a61.72410777.jpg', 1, NULL, 0, 'ativo', '2025-12-10 14:30:10', NULL),
(19, 2, 'testando', '', 1.11, 'por_quilo', NULL, NULL, 0, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_6939a7f7bde745.04338148.jpeg', 11, NULL, 0, 'ativo', '2025-12-10 17:03:51', NULL),
(20, 1, 'novo anúncio', 'teste novo *anúncio*', 10.00, 'caixa_quilos', 2.000, NULL, NULL, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_6941720cc53d18.11334749.jpg', 25, 'caixa', 1, 'ativo', '2025-12-16 14:51:56', NULL),
(21, 1, 'unidade', '', 1.00, 'por_unidade', NULL, NULL, 250, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_694195ae5e5541.02524533.jpg', 250, 'unidade', 1, 'ativo', '2025-12-16 17:23:58', NULL),
(22, 1, 'caixa unidades', '', 50.00, 'caixa_unidades', NULL, 12, 23, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_694195d13a9663.37007865.jpg', 23, 'caixa', 1, 'ativo', '2025-12-16 17:24:33', NULL),
(23, 1, 'saco unidades', '', 3.00, 'saco_unidades', NULL, 10, 120, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_694195f134ac04.46666560.jpg', 120, 'saco', 1, 'ativo', '2025-12-16 17:25:05', NULL),
(24, 1, 'saco kgs', '', 0.01, 'saco_quilos', 3.000, NULL, NULL, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_694196076e7db4.43222171.jpg', 1223, 'saco', 1, 'ativo', '2025-12-16 17:25:27', '2025-12-16 19:55:16'),
(25, 1, 'unidadde', '', 0.01, 'por_unidade', NULL, NULL, 1221, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_6941964169b844.40818529.jpg', 1221, 'unidade', 1, 'ativo', '2025-12-16 17:26:25', NULL),
(26, 7, 'Banana', '', 0.01, 'por_quilo', NULL, NULL, NULL, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_695fb5d970ace9.02725513.jpeg', 1, 'kg', 0, 'ativo', '2026-01-08 13:49:13', NULL),
(27, 7, 'asdadad', '', 2.12, 'caixa_unidades', NULL, NULL, 12, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_695fb71ab9d136.78257934.jpg', 12, 'caixa', 0, 'ativo', '2026-01-08 13:54:34', NULL),
(28, 7, '12212', '', 1.21, 'caixa_unidades', NULL, NULL, 12, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_695fb724a33752.25254238.jpg', 12, 'caixa', 0, 'ativo', '2026-01-08 13:54:44', NULL),
(29, 7, 'aw', '', 0.01, 'por_quilo', NULL, NULL, NULL, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_695fb72d637dc7.50959417.jpeg', 1, 'kg', 0, 'ativo', '2026-01-08 13:54:53', NULL);

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
(5, 15, '../uploads/produtos/prod_69398052be9755.34966564.jpeg', 0, '2025-12-10 14:14:42'),
(6, 16, '../uploads/produtos/prod_69398067b3f823.59696952.jpg', 0, '2025-12-10 14:15:03'),
(7, 16, '../uploads/produtos/prod_69398067b417a9.84436515.jpeg', 1, '2025-12-10 14:15:03'),
(8, 16, '../uploads/produtos/prod_69398067b43918.23298783.jpg', 2, '2025-12-10 14:15:03'),
(9, 17, '../uploads/produtos/prod_693983f26c4a61.72410777.jpg', 0, '2025-12-10 14:30:10'),
(11, 19, '../uploads/produtos/prod_6939a7f7bde745.04338148.jpeg', 0, '2025-12-10 17:03:51'),
(12, 20, '../uploads/produtos/prod_6941720cc53d18.11334749.jpg', 0, '2025-12-16 14:51:56'),
(13, 20, '../uploads/produtos/prod_6941720cc560f0.22236072.jpeg', 1, '2025-12-16 14:51:56'),
(14, 20, '../uploads/produtos/prod_6941720cc57d82.52133605.jpg', 2, '2025-12-16 14:51:56'),
(15, 21, '../uploads/produtos/prod_694195ae5e5541.02524533.jpg', 0, '2025-12-16 17:23:58'),
(16, 22, '../uploads/produtos/prod_694195d13a9663.37007865.jpg', 0, '2025-12-16 17:24:33'),
(17, 23, '../uploads/produtos/prod_694195f134ac04.46666560.jpg', 0, '2025-12-16 17:25:05'),
(18, 24, '../uploads/produtos/prod_694196076e7db4.43222171.jpg', 0, '2025-12-16 17:25:27'),
(19, 25, '../uploads/produtos/prod_6941964169b844.40818529.jpg', 0, '2025-12-16 17:26:25'),
(20, 26, '../uploads/produtos/prod_695fb5d970ace9.02725513.jpeg', 0, '2026-01-08 13:49:13'),
(21, 27, '../uploads/produtos/prod_695fb71ab9d136.78257934.jpg', 0, '2026-01-08 13:54:34'),
(22, 28, '../uploads/produtos/prod_695fb724a33752.25254238.jpg', 0, '2026-01-08 13:54:44'),
(23, 29, '../uploads/produtos/prod_695fb72d637dc7.50959417.jpeg', 0, '2026-01-08 13:54:53');

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
  `status` enum('aceita','negociacao','recusada') DEFAULT 'negociacao'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas`
--

INSERT INTO `propostas` (`ID`, `comprador_id`, `vendedor_id`, `produto_id`, `data_inicio`, `data_atualizacao`, `preco_proposto`, `quantidade_proposta`, `forma_pagamento`, `opcao_frete`, `valor_frete`, `valor_total`, `status`) VALUES
(17, 23, 4, 16, '2026-01-21 17:12:19', '2026-01-21 18:07:56', 10.00, 1, 'à vista', 'vendedor', 0.00, 10.00, 'recusada'),
(18, 23, 4, 16, '2026-01-21 18:09:03', '2026-01-21 18:20:58', 10.00, 1, 'à vista', 'vendedor', 5.00, 15.00, 'aceita'),
(19, 23, 4, 16, '2026-01-21 18:21:33', '2026-01-21 18:21:39', 8.00, 1, 'à vista', 'vendedor', 3.00, 11.00, 'recusada'),
(20, 23, 4, 16, '2026-01-21 18:22:44', '2026-01-21 18:22:48', 8.00, 1, 'à vista', 'entregador', 0.00, 8.00, 'recusada'),
(21, 23, 4, 16, '2026-01-21 18:24:29', '2026-01-21 18:30:12', 8.00, 1, 'à vista', 'vendedor', 2.00, 10.00, 'recusada'),
(22, 23, 4, 16, '2026-01-21 18:30:27', '2026-01-21 18:33:08', 9.00, 1, 'à vista', 'comprador', 0.00, 9.00, 'recusada'),
(23, 23, 4, 16, '2026-01-21 18:33:21', '2026-01-21 19:05:54', 8.00, 1, 'à vista', 'entregador', 0.00, 8.00, 'aceita'),
(24, 23, 4, 15, '2026-01-21 19:07:16', '2026-01-21 19:07:24', 0.01, 1, 'à vista', 'comprador', 0.00, 0.01, 'recusada'),
(25, 23, 4, 15, '2026-01-21 19:07:38', '2026-01-21 19:07:44', 0.01, 1, 'à vista', 'vendedor', 10.00, 10.01, 'recusada'),
(26, 23, 4, 15, '2026-01-21 19:08:28', '2026-01-21 19:10:03', 0.01, 1, 'à vista', 'entregador', 0.00, 0.01, 'recusada'),
(27, 23, 4, 15, '2026-01-21 19:10:18', '2026-01-21 19:10:25', 0.01, 1, 'à vista', 'entregador', 0.00, 0.01, 'recusada'),
(28, 23, 4, 15, '2026-01-21 19:10:39', '2026-01-21 19:10:46', 0.01, 1, 'à vista', 'entregador', 0.00, 0.01, 'aceita'),
(29, 23, 4, 16, '2026-01-21 19:15:12', '2026-01-21 19:15:20', 2.00, 1, 'à vista', 'vendedor', 0.00, 2.00, 'aceita');

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_comprador`
--

CREATE TABLE `propostas_comprador` (
  `ID` int(11) NOT NULL,
  `comprador_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `preco_proposto` decimal(10,2) DEFAULT NULL,
  `quantidade_proposta` int(11) DEFAULT NULL,
  `data_proposta` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enviada','pendente','aceita','recusada') DEFAULT NULL,
  `forma_pagamento` enum('à vista','entrega') DEFAULT NULL,
  `opcao_frete` enum('vendedor','comprador','entregador') DEFAULT NULL,
  `valor_frete` decimal(10,2) DEFAULT NULL,
  `finalizada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_negociacao`
--

CREATE TABLE `propostas_negociacao` (
  `ID` int(11) NOT NULL,
  `proposta_comprador_id` int(11) DEFAULT NULL,
  `proposta_vendedor_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `data_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `valor_total` decimal(10,2) DEFAULT NULL,
  `quantidade_final` int(11) DEFAULT NULL,
  `status` enum('aceita','negociacao','recusada') DEFAULT NULL,
  `forma_pagamento` enum('à vista','entrega') DEFAULT NULL,
  `opcao_frete` enum('vendedor','comprador','entregador') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_vendedor`
--

CREATE TABLE `propostas_vendedor` (
  `ID` int(11) NOT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `preco_proposto` decimal(10,2) DEFAULT NULL,
  `quantidade_proposta` int(11) DEFAULT NULL,
  `data_proposta` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enviada','pendente','aceita','recusada') DEFAULT NULL,
  `forma_pagamento` enum('à vista','entrega') DEFAULT NULL,
  `opcao_frete` enum('vendedor','comprador','entregador') DEFAULT NULL,
  `valor_frete` decimal(10,2) DEFAULT NULL,
  `finalizada` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, NULL, 'Jorge Pontes', 'jorgeappontes13@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"nomeComercialComprador\":\"Jorge Pontes\",\"cpfCnpjComprador\":\"411.115.848-00\",\"cepComprador\":\"13211-87\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundia\\u00ed\",\"telefone1Comprador\":\"11996563500\",\"planoComprador\":\"basico\",\"estadoVendedor\":\"SP\"}', 'rejeitado', '2025-11-08 23:53:30', '2025-11-09 00:14:49', NULL, NULL),
(2, NULL, 'Jorge Pontes', 'jorgeappontes13@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"nomeComercialComprador\":\"Jorge Pontes\",\"cpfCnpjComprador\":\"411.115.848-00\",\"cepComprador\":\"13211-87\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundia\\u00ed\",\"telefone1Comprador\":\"11996563500\",\"planoComprador\":\"premium\",\"estadoVendedor\":\"SP\",\"senha_hash\":\"$2y$10$oM84AnD7UCT10BMttSi0ROshhjDaiDUMnMRNJnN7JEDXJeVBBfkGy\"}', 'aprovado', '2025-11-09 00:15:25', '2025-11-09 00:15:43', NULL, NULL),
(3, 3, 'Jorge Pontes', 'jorgeappontes13@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"nomeComercialComprador\":\"Jorge Pontes\",\"cpfCnpjComprador\":\"411.115.848-00\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundia\\u00ed\",\"telefone1Comprador\":\"11996563500\",\"planoComprador\":\"basico\",\"estadoVendedor\":\"SP\",\"senha_hash\":\"$2y$10$G01Lh5IUbanlM7HsqPVJCedjau8JTSyWFxoYa9DYF0EoCNsnA5L2i\"}', 'aprovado', '2025-11-09 00:18:43', '2025-11-09 00:23:59', NULL, NULL),
(4, 4, 'Vendedor', 'vendedor@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"estadoComprador\":\"SP\",\"nomeComercialVendedor\":\"Vendedor\",\"cpfCnpjVendedor\":\"11.111.111\\/1111-11\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundia\\u00ed\",\"telefone1Vendedor\":\"11996563500\",\"planoVendedor\":\"basico\",\"senha_hash\":\"$2y$10$WiZ7DfVl8YB49H7SjzLXEuk6UsnWeNGQnBoCD5bF4f5P8R.8Xt.IS\"}', 'aprovado', '2025-11-09 00:26:58', '2025-11-09 00:28:21', NULL, NULL),
(5, 7, 'Transportador', 'transportador@gmail.com', '11996563500', 'Campinas, SP', 'transportador', '{\"estadoComprador\":\"SP\",\"estadoVendedor\":\"SP\",\"telefoneTransportador\":\"11996563500\",\"numeroANTT\":\"13231231434142243\",\"placaVeiculo\":\"111-1111\",\"modeloVeiculo\":\"teste\",\"descricaoVeiculo\":\"carreta\",\"estadoTransportador\":\"SP\",\"cidadeTransportador\":\"Campinas\",\"senha_hash\":\"$2y$10$ydt.M8elv07ezQw77wQZc.HCjZ8gvIRsUM.9FvhiAGKHbLtsBi7lS\"}', 'aprovado', '2025-11-09 00:27:58', '2025-11-09 00:29:26', NULL, NULL),
(6, NULL, 'teste', 'teste@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"nomeComercialComprador\":\"1111111\",\"cpfCnpjComprador\":\"111111111111111111111111111111111111111111111111\",\"cepComprador\":\"11111-111\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundia\\u00ed\",\"telefone1Comprador\":\"11996563500\",\"planoComprador\":\"premium\",\"estadoVendedor\":\"SP\",\"senha_hash\":\"$2y$10$g0JSpSo1Is2zduL4KitN7O5TSD2q9PDIIOIBtljyvD4VqMDV3Uple\"}', 'pendente', '2025-11-10 12:24:28', NULL, NULL, NULL),
(7, 8, 'Comprador', 'comprador@gmail.com', '11912341234', 'Rua Paschoal Segre, 225, Jundiaí, SP', 'comprador', '{\"cpfCnpjComprador\":\"123.456.789-99\",\"cepComprador\":\"13218-200\",\"ruaComprador\":\"Rua Paschoal Segre\",\"numeroComprador\":\"225\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundia\\u00ed\",\"telefone1Comprador\":\"11912341234\",\"planoComprador\":\"basico\",\"senha_hash\":\"$2y$10$eeHvPWmRt.UfXNCIhs.0GuX5hR8IS\\/sPBZj\\/LxhzRRHnuRJOU4k2W\"}', 'aprovado', '2025-11-17 18:55:23', '2025-11-17 18:55:39', NULL, NULL),
(8, 9, 'vendedor2', 'vendedor2@gmail.com', '11111111111', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"estadoComprador\":\"SP\",\"nomeComercialVendedor\":\"vendedor2\",\"cpfCnpjVendedor\":\"111111111111\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundia\\u00ed\",\"telefone1Vendedor\":\"11111111111\",\"planoVendedor\":\"basico\",\"senha_hash\":\"$2y$10$s.T35LUiwMUk90sR5QNhResWbIid1TkOZlo7GrUjavpSlqeMvwnW2\"}', 'aprovado', '2025-11-20 17:39:35', '2025-11-20 17:40:01', NULL, NULL),
(9, NULL, 'testando', 'testando@gmail.com', '11111111111', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"estadoComprador\":\"SP\",\"nomeComercialVendedor\":\"teste\",\"cpfCnpjVendedor\":\"111111111111111111\",\"cipVendedor\":\"1111\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundia\\u00ed\",\"telefone1Vendedor\":\"11111111111\",\"planoVendedor\":\"basico\",\"senha_hash\":\"$2y$10$T8EZ.Gv.4vjlW2BPcd4\\/oOKxyRCTgImzX4StLSQfxVagKajWxUXIm\"}', 'pendente', '2025-12-01 13:49:12', NULL, NULL, NULL),
(10, 14, 'testando', 'testando@gmail.com', '11111111111', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"estadoComprador\":\"SP\",\"nomeComercialVendedor\":\"teste\",\"cpfCnpjVendedor\":\"111111111111111111\",\"cipVendedor\":\"1111\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundia\\u00ed\",\"telefone1Vendedor\":\"11111111111\",\"planoVendedor\":\"basico\",\"senha_hash\":\"$2y$10$NfhFfw0I8bgyOGANrDkG9u\\/gkWg6EeHAKy\\/97wU7u7bVhe01M4wCW\"}', 'aprovado', '2025-12-01 13:49:16', '2025-12-04 01:15:01', NULL, NULL),
(11, 12, 'teste', 'teste@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"estadoComprador\":\"SP\",\"nomeComercialVendedor\":\"teste\",\"cpfCnpjVendedor\":\"1111111111111111111\",\"cipVendedor\":\"11111\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundia\\u00ed\",\"telefone1Vendedor\":\"11996563500\",\"planoVendedor\":\"basico\",\"senha_hash\":\"$2y$10$aXdfuVEyQhKloWsi5stRa.8.EBl6Oo5NI7mf.ZDXP19qg7kp5m206\"}', 'aprovado', '2025-12-01 14:09:40', '2025-12-02 12:52:04', NULL, NULL),
(12, NULL, 'SILENE CRISTINA POSSANI', 'testa@gamil.com', '1111111111', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"nome\":\"SILENE CRISTINA POSSANI\",\"email\":\"testa@gamil.com\",\"tipo_solicitacao\":\"vendedor\",\"senha_hash\":\"$2y$10$IHfi0i6llU30Ws6hBl.L7eyrgTXncaSDhDxBRCOvpeWUbF1WCOgOC\",\"nomeComercialVendedor\":\"teste\",\"cpfCnpjVendedor\":\"41111584800\",\"cipVendedor\":\"21212121212\",\"cepVendedor\":\"13211873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"1111111111\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"basico\"}', 'rejeitado', '2025-12-01 14:14:23', '2025-12-09 12:01:10', NULL, NULL),
(13, 11, 'JorgeV', 'jorgev@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"nome\":\"JorgeV\",\"email\":\"jorgev@gmail.com\",\"tipo_solicitacao\":\"vendedor\",\"senha_hash\":\"$2y$10$dYUUcwFHeSAZKAHMbystPe22T1fZtfuBiMhbKy1Zje7PraxRIqb0G\",\"nomeComercialVendedor\":\"Rondon\",\"cpfCnpjVendedor\":\"41111584800\",\"cipVendedor\":\"1212121\",\"cepVendedor\":\"13211873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"11996563500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"basico\"}', 'aprovado', '2025-12-02 12:44:56', '2025-12-02 12:49:02', NULL, NULL),
(14, 13, 'teste', 'teste2@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"name\":\"teste\",\"email\":\"teste2@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"411.115.848-00\",\"nomeComercialComprador\":\"Jorginho\",\"cipComprador\":\"\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99656-3500\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$4Ce1YFZHYbe77LxsbLr.O.g4zvLdtb65BZsGJwEDPw2pTLBdq.tqe\"}', 'pendente', '2025-12-02 14:16:39', NULL, NULL, NULL),
(15, NULL, 'ok', 'ok@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"name\":\"ok\",\"email\":\"ok@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"137.490.258-60\",\"nomeComercialComprador\":\"ok\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99656-3500\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$yARAYQu3Y52xxVAjTUEFzeOoGHnU9Z.335OFLeay8oOSbppVB8uEK\"}', 'rejeitado', '2025-12-05 01:23:33', '2025-12-09 12:04:19', NULL, NULL),
(16, NULL, 'Jorge', 'abc@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"name\":\"Jorge\",\"email\":\"abc@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"166.076.628-11\",\"nomeComercialComprador\":\"okok\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99656-3500\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$\\/AbCLuI\\/OXcOAK4lkH\\/d7eoGb6LHjWA6LYv1syJz1Wopx7r52LWfe\"}', 'rejeitado', '2025-12-09 12:02:45', '2025-12-09 12:03:04', NULL, NULL),
(17, 17, 'Jorge', 'ok@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"name\":\"Jorge\",\"email\":\"ok@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"166.076.628-11\",\"nomeComercialComprador\":\"Jorge\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99656-3500\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$Doo5HH4LGV82QBL\\/kFRmu.YyBAm\\/lTgrxGy30amMAqe4WHGiiLwkW\"}', 'pendente', '2025-12-09 13:10:53', NULL, NULL, NULL),
(18, 18, 'ok', 'matue@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"name\":\"ok\",\"email\":\"matue@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"166.076.628-11\",\"nomeComercialComprador\":\"matue\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99656-3500\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$QD2\\/eOhZMRPKstti40Q3v.9n1o3Yi7MCmhcpikrAYpahk7oadL2By\"}', 'pendente', '2025-12-11 00:11:27', NULL, NULL, NULL),
(19, 19, 'test', 'tvendedor@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"name\":\"test\",\"email\":\"tvendedor@gmail.com\",\"subject\":\"vendedor\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"test\",\"cpfCnpjVendedor\":\"00.000.000\\/0001-91\",\"cipVendedor\":\"\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"(11) 99656-3500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$u.HaiRipdMCCtHAjeXSEGOaWuxq8OSuty5NtGa3sU\\/p1qrTkFx7Ty\"}', 'pendente', '2025-12-11 17:05:40', NULL, NULL, NULL),
(20, 22, 'pagamentoteste', 'pagamentoteste@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"name\":\"pagamentoteste\",\"email\":\"pagamentoteste@gmail.com\",\"subject\":\"vendedor\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"pagamento teste\",\"cpfCnpjVendedor\":\"87.772.833\\/0001-59\",\"cipVendedor\":\"\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"(11) 99656-3500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$6y69RuRenxE5j77aGmRjHO2U9IIgw806Y68f\\/S\\/IibtqiK0o3COV.\"}', 'aprovado', '2026-01-08 13:48:24', '2026-01-08 13:48:40', NULL, NULL),
(21, 23, 'Comprador2', 'comprador2@gmail.com', '(11) 11111-1111', 'Rua Vereador Saturnino M. Oliveira, 1, Várzea Grande, MT', 'comprador', '{\"name\":\"Comprador2\",\"email\":\"comprador2@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"239.186.590-20\",\"nomeComercialComprador\":\"Comprador2\",\"cepComprador\":\"78132-752\",\"ruaComprador\":\"Rua Vereador Saturnino M. Oliveira\",\"numeroComprador\":\"1\",\"complementoComprador\":\"\",\"estadoComprador\":\"MT\",\"cidadeComprador\":\"Várzea Grande\",\"telefone1Comprador\":\"(11) 11111-1111\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$OkzXwNwpYhrH.Ubm3v5zMeA0gJofcKDkpidji7fBmeQIvtjVcmBua\"}', 'aprovado', '2026-01-10 15:12:02', '2026-01-10 15:12:27', NULL, NULL),
(22, NULL, 'a', 'a@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"name\":\"a\",\"email\":\"a@gmail.com\",\"subject\":\"vendedor\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"a\",\"cpfCnpjVendedor\":\"07.263.688\\/0001-41\",\"cipVendedor\":\"\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"(11) 99656-3500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$yB5WAR7hJyj3H4c2iI.zcu.1huDieaq9BVZCRGyB8PN\\/NF.XuoLky\"}', 'aprovado', '2026-01-14 14:09:43', '2026-01-14 14:09:52', NULL, NULL),
(23, NULL, 'a', 'a@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"name\":\"a\",\"email\":\"a@gmail.com\",\"subject\":\"vendedor\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"a\",\"cpfCnpjVendedor\":\"28.692.428\\/0001-61\",\"cipVendedor\":\"\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"(11) 99656-3500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$L0MtHpIMj7VFqp4QjuOwaOXX8igRy243Fa2k2fDZi.s0s2Lz6idmy\"}', 'aprovado', '2026-01-14 14:20:42', '2026-01-14 14:20:52', NULL, NULL),
(24, 26, 'a', 'a@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"name\":\"a\",\"email\":\"a@gmail.com\",\"subject\":\"vendedor\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"a\",\"cpfCnpjVendedor\":\"05.474.594\\/0001-96\",\"cipVendedor\":\"\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"(11) 99656-3500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"senha_hash\":\"$2y$10$ur4VUeX9VISRYl1dELNSfeL0BhNRhy906zCmb7XGLGhn7nT3CdUyu\"}', 'aprovado', '2026-01-14 19:38:18', '2026-01-14 19:38:29', NULL, NULL),
(25, 27, 'assa', 'as@gmail.com', '(11) 11111-1111', 'Jundiaí, SP', 'transportador', '{\"name\":\"assa\",\"email\":\"as@gmail.com\",\"subject\":\"transportador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"\",\"nomeComercialComprador\":\"\",\"cepComprador\":\"\",\"ruaComprador\":\"\",\"numeroComprador\":\"\",\"complementoComprador\":\"\",\"estadoComprador\":\"\",\"cidadeComprador\":\"\",\"telefone1Comprador\":\"\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"(11) 11111-1111\",\"numeroANTT\":\"11111111111111111111\",\"placaVeiculo\":\"111-1111\",\"modeloVeiculo\":\"11111111111\",\"descricaoVeiculo\":\"1111111\",\"cepTransportador\":\"13211693\",\"ruaTransportador\":\"Avenida César Puglia\",\"numeroTransportador\":\"111111\",\"complementoTransportador\":\"\",\"estadoTransportador\":\"SP\",\"cidadeTransportador\":\"Jundiaí\",\"message\":\"\",\"senha_hash\":\"$2y$10$B6XGRf1Bv.5SSKyU4.jafOppc37TBnAPkMTQxXGf00GJCkJw767wG\"}', 'pendente', '2026-01-19 18:47:54', NULL, NULL, NULL);

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
(1, 7, '', '11996563500', NULL, '13231231434142243', '111-1111', 'teste', 'carreta', '13211693', '', '', '', 'SP', 'Campinas', 'free', ''),
(2, 27, 'assa', '(11) 11111-1111', NULL, '11111111111111111111', '111-1111', '11111111111', '1111111', NULL, NULL, NULL, NULL, 'SP', 'Jundiaí', 'free', NULL);

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
(3, 'jorgeappontes13@gmail.com', '$2y$10$G01Lh5IUbanlM7HsqPVJCedjau8JTSyWFxoYa9DYF0EoCNsnA5L2i', 'comprador', 'Jorge Pontes', 'ativo', '2025-11-09 00:23:59', NULL, NULL, NULL),
(4, 'vendedor@gmail.com', '$2y$10$WiZ7DfVl8YB49H7SjzLXEuk6UsnWeNGQnBoCD5bF4f5P8R.8Xt.IS', 'vendedor', 'Vendedor', 'ativo', '2025-11-09 00:28:21', NULL, NULL, NULL),
(7, 'transportador@gmail.com', '$2y$10$ydt.M8elv07ezQw77wQZc.HCjZ8gvIRsUM.9FvhiAGKHbLtsBi7lS', 'transportador', 'Transportador', 'ativo', '2025-11-09 00:29:26', NULL, NULL, NULL),
(8, 'comprador@gmail.com', '$2y$10$eeHvPWmRt.UfXNCIhs.0GuX5hR8IS/sPBZj/LxhzRRHnuRJOU4k2W', 'comprador', 'Comprador', 'inativo', '2025-11-17 18:55:39', NULL, NULL, NULL),
(9, 'vendedor2@gmail.com', '$2y$10$s.T35LUiwMUk90sR5QNhResWbIid1TkOZlo7GrUjavpSlqeMvwnW2', 'vendedor', 'vendedor2', 'ativo', '2025-11-20 17:40:01', NULL, NULL, NULL),
(11, 'jorgev@gmail.com', '$2y$10$dYUUcwFHeSAZKAHMbystPe22T1fZtfuBiMhbKy1Zje7PraxRIqb0G', 'vendedor', 'JorgeV', 'ativo', '2025-12-02 12:49:02', NULL, NULL, NULL),
(12, 'teste@gmail.com', '$2y$10$aXdfuVEyQhKloWsi5stRa.8.EBl6Oo5NI7mf.ZDXP19qg7kp5m206', 'vendedor', 'teste', 'ativo', '2025-12-02 12:52:04', NULL, NULL, NULL),
(13, 'teste2@gmail.com', '$2y$10$4Ce1YFZHYbe77LxsbLr.O.g4zvLdtb65BZsGJwEDPw2pTLBdq.tqe', 'comprador', 'teste', 'ativo', '2025-12-02 14:16:39', NULL, NULL, NULL),
(14, 'testando@gmail.com', '$2y$10$NfhFfw0I8bgyOGANrDkG9u/gkWg6EeHAKy/97wU7u7bVhe01M4wCW', 'vendedor', 'testando', 'ativo', '2025-12-04 01:15:01', NULL, NULL, NULL),
(17, 'ok@gmail.com', '$2y$10$Doo5HH4LGV82QBL/kFRmu.YyBAm/lTgrxGy30amMAqe4WHGiiLwkW', 'comprador', 'Jorge', 'pendente', '2025-12-09 13:10:53', NULL, NULL, NULL),
(18, 'matue@gmail.com', '$2y$10$QD2/eOhZMRPKstti40Q3v.9n1o3Yi7MCmhcpikrAYpahk7oadL2By', 'comprador', 'ok', 'pendente', '2025-12-11 00:11:27', NULL, NULL, NULL),
(19, 'tvendedor@gmail.com', '$2y$10$u.HaiRipdMCCtHAjeXSEGOaWuxq8OSuty5NtGa3sU/p1qrTkFx7Ty', 'vendedor', 'test', 'pendente', '2025-12-11 17:05:40', NULL, NULL, NULL),
(22, 'pagamentoteste@gmail.com', '$2y$10$6y69RuRenxE5j77aGmRjHO2U9IIgw806Y68f/S/IibtqiK0o3COV.', 'vendedor', 'pagamentoteste', 'ativo', '2026-01-08 13:48:24', NULL, NULL, NULL),
(23, 'comprador2@gmail.com', '$2y$10$OkzXwNwpYhrH.Ubm3v5zMeA0gJofcKDkpidji7fBmeQIvtjVcmBua', 'comprador', 'Comprador2', 'ativo', '2026-01-10 15:12:02', NULL, NULL, NULL),
(26, 'a@gmail.com', '$2y$10$ur4VUeX9VISRYl1dELNSfeL0BhNRhy906zCmb7XGLGhn7nT3CdUyu', 'vendedor', 'a', 'ativo', '2026-01-14 19:38:18', NULL, NULL, NULL),
(27, 'as@gmail.com', '$2y$10$B6XGRf1Bv.5SSKyU4.jafOppc37TBnAPkMTQxXGf00GJCkJw767wG', 'transportador', 'assa', 'pendente', '2026-01-19 18:47:54', NULL, NULL, NULL);

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
(1, 4, 0, '2026-01-16 15:29:02', NULL),
(2, 9, 0, '2026-01-16 15:36:42', NULL);

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
(1, 4, 'cnpj', 'Vendedor', '11.111.111/1111-11', '', '../uploads/vendedores/vend_1_693811ef0cf33.jpg', NULL, '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free', '[\"SE\"]', NULL, 3, 'ativo', '2025-12-20 12:09:14', NULL, '2026-02-07 14:42:09', 0, 0, 0, NULL, NULL),
(2, 9, 'cnpj', 'vendedor2', '111111111111', NULL, NULL, NULL, '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11111111111', NULL, 'free', NULL, NULL, 1, 'inativo', NULL, NULL, NULL, 0, 0, 0, NULL, NULL),
(3, 11, 'cnpj', 'Rondon', '41111584800', NULL, NULL, '1212121', '13211873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free', NULL, NULL, 1, 'inativo', NULL, NULL, NULL, 0, 0, 0, NULL, NULL),
(4, 12, 'cnpj', 'teste', '1111111111111111111', NULL, NULL, '11111', '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free', NULL, NULL, 4, 'ativo', NULL, NULL, '2026-02-07 14:42:09', 0, 0, 0, NULL, NULL),
(5, 14, 'cnpj', 'teste', '111111111111111111', NULL, NULL, '1111', '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11111111111', NULL, 'basico', NULL, NULL, 1, 'inativo', NULL, NULL, NULL, 0, 0, 0, NULL, NULL),
(6, 19, 'cnpj', 'test', '00.000.000/0001-91', NULL, NULL, '', '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free', NULL, NULL, 1, 'inativo', NULL, NULL, NULL, 0, 0, 0, NULL, NULL),
(7, 22, 'cnpj', 'pagamento teste', '87.772.833/0001-59', NULL, NULL, '', '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free', NULL, NULL, 4, 'ativo', '2026-01-09 01:06:35', '2026-01-09 01:06:35', '2026-02-08 01:06:35', 0, 0, 0, NULL, 'sub_1SpUJ30lZtce65b7gWdlLhGB'),
(10, 26, 'cnpj', 'a', '05.474.594/0001-96', NULL, NULL, '', '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free', NULL, NULL, 3, 'ativo', NULL, '2026-01-12 20:54:58', '2026-02-14 20:54:58', 0, 0, 0, 'cus_TnAUNEP6D8ZbgC', 'sub_1Spa9d0lZtce65b7IHGgDhZg');

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
-- Despejando dados para a tabela `vendedor_assinaturas`
--

INSERT INTO `vendedor_assinaturas` (`id`, `vendedor_id`, `plano_id`, `status`, `preco_aprovado`, `data_inicio`, `data_vencimento`, `periodo`, `referencia_mercadopago`, `preferencia_mercadopago`, `unidades_extras`, `created_at`, `updated_at`, `payment_id`, `subscription_id`, `init_point`, `external_reference`) VALUES
(1, 1, 5, 'pending', 49.90, '2025-12-19', NULL, 'monthly', 'vendedor_1_plano_2_1766159934', NULL, 0, '2025-12-19 15:58:54', '2025-12-23 00:43:46', NULL, NULL, NULL, 'vendedor_1_plano_5_1766450626');

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
  ADD UNIQUE KEY `conversa_unica` (`produto_id`,`comprador_id`,`vendedor_id`),
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
  ADD KEY `comprador_id` (`comprador_id`);

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
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `propostas_comprador`
--
ALTER TABLE `propostas_comprador`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `comprador_id` (`comprador_id`);

--
-- Índices de tabela `propostas_negociacao`
--
ALTER TABLE `propostas_negociacao`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `proposta_comprador_id` (`proposta_comprador_id`),
  ADD KEY `proposta_vendedor_id` (`proposta_vendedor_id`);

--
-- Índices de tabela `propostas_vendedor`
--
ALTER TABLE `propostas_vendedor`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `vendedor_id` (`vendedor_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `chat_auditoria`
--
ALTER TABLE `chat_auditoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=175;

--
-- AUTO_INCREMENT de tabela `chat_conversas`
--
ALTER TABLE `chat_conversas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=177;

--
-- AUTO_INCREMENT de tabela `compradores`
--
ALTER TABLE `compradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `conversas`
--
ALTER TABLE `conversas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `entregas`
--
ALTER TABLE `entregas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `mensagens`
--
ALTER TABLE `mensagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `produto_imagens`
--
ALTER TABLE `produto_imagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de tabela `propostas`
--
ALTER TABLE `propostas`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `propostas_comprador`
--
ALTER TABLE `propostas_comprador`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `propostas_negociacao`
--
ALTER TABLE `propostas_negociacao`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `propostas_vendedor`
--
ALTER TABLE `propostas_vendedor`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `solicitacoes_cadastro`
--
ALTER TABLE `solicitacoes_cadastro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de tabela `transportadores`
--
ALTER TABLE `transportadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `usuario_avisos_preferencias`
--
ALTER TABLE `usuario_avisos_preferencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `vendedores`
--
ALTER TABLE `vendedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  ADD CONSTRAINT `propostas_ibfk_3` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `propostas_comprador`
--
ALTER TABLE `propostas_comprador`
  ADD CONSTRAINT `propostas_comprador_ibfk_1` FOREIGN KEY (`comprador_id`) REFERENCES `compradores` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `propostas_negociacao`
--
ALTER TABLE `propostas_negociacao`
  ADD CONSTRAINT `propostas_negociacao_ibfk_1` FOREIGN KEY (`proposta_comprador_id`) REFERENCES `propostas_comprador` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `propostas_negociacao_ibfk_2` FOREIGN KEY (`proposta_vendedor_id`) REFERENCES `propostas_vendedor` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `propostas_vendedor`
--
ALTER TABLE `propostas_vendedor`
  ADD CONSTRAINT `propostas_vendedor_ibfk_1` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
