-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 10/12/2025 às 15:37
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
-- Estrutura para tabela `acordos`
--

CREATE TABLE `acordos` (
  `id` int(11) NOT NULL,
  `conversa_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` decimal(10,2) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `preco_total` decimal(10,2) NOT NULL,
  `condicoes` text DEFAULT NULL,
  `status` enum('pendente','aceito','recusado','expirado') DEFAULT 'pendente',
  `valido_ate` datetime DEFAULT NULL,
  `criado_por` int(11) NOT NULL,
  `aceito_por` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_aceitacao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(22, 1, 'Alterou status do usuário (ID: 14) para ativo', 'usuarios', 14, '2025-12-09 13:14:13');

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
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `chat_mensagens`
--

CREATE TABLE `chat_mensagens` (
  `id` int(11) NOT NULL,
  `conversa_id` int(11) NOT NULL,
  `remetente_id` int(11) NOT NULL,
  `mensagem` text NOT NULL,
  `lida` tinyint(1) DEFAULT 0,
  `data_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 8, NULL, NULL, NULL, '123.456.789-99', NULL, '13218-200', 'Rua Paschoal Segre', '225', NULL, 'SP', 'Jundiaí', '11912341234', NULL, 'free'),
(3, 13, 'cpf', 'Jorginho', NULL, '411.115.848-00', '', '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free'),
(4, 4, 'cpf', 'Vendedor', NULL, '11.111.111/1111-11', NULL, '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(5, 14, 'cpf', 'teste', NULL, '111111111111111111', NULL, '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '11111111111', NULL, 'free'),
(6, 11, 'cpf', 'Rondon', NULL, '41111584800', NULL, '13211873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(9, 17, 'cpf', 'Jorge', NULL, '166.076.628-11', NULL, '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free');

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
(1, 1, 'Nova solicitação de cadastro de vendedor: SILENE CRISTINA POSSANI', 'info', 1, 'src/admin/solicitacoes.php', '2025-12-01 14:14:23'),
(2, 1, 'Nova solicitação de cadastro de vendedor: JorgeV', 'info', 1, 'src/admin/solicitacoes.php', '2025-12-02 12:44:56'),
(3, 1, 'Nova solicitação de cadastro de comprador: teste', 'info', 1, 'src/admin/solicitacoes.php', '2025-12-02 14:16:39'),
(4, 1, 'Nova solicitação de cadastro de comprador: ok', 'info', 1, 'src/admin/solicitacoes.php', '2025-12-05 01:23:33'),
(5, 1, 'Nova solicitação de cadastro de comprador: Jorge', 'info', 1, 'src/admin/solicitacoes.php', '2025-12-09 12:02:45'),
(6, 1, 'Nova solicitação de cadastro de comprador: Jorge', 'info', 0, 'src/admin/solicitacoes.php', '2025-12-09 13:10:53');

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
  `preco_desconto` decimal(10,2) DEFAULT NULL,
  `desconto_percentual` decimal(5,2) DEFAULT 0.00,
  `desconto_ativo` tinyint(1) DEFAULT 0,
  `desconto_data_inicio` datetime DEFAULT NULL,
  `desconto_data_fim` datetime DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `imagem_url` varchar(500) DEFAULT NULL,
  `estoque` int(11) DEFAULT 0,
  `unidade_medida` varchar(50) DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `vendedor_id`, `nome`, `descricao`, `preco`, `preco_desconto`, `desconto_percentual`, `desconto_ativo`, `desconto_data_inicio`, `desconto_data_fim`, `categoria`, `imagem_url`, `estoque`, `unidade_medida`, `status`, `data_criacao`, `data_atualizacao`) VALUES
(15, 1, 'teste 1', '1', 0.01, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_69398052be9755.34966564.jpeg', 1, NULL, 'ativo', '2025-12-10 14:14:42', '2025-12-10 14:15:18'),
(16, 1, 'teste x', '1', 10.00, 8.00, 20.00, 1, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_69398067b3f823.59696952.jpg', 1, NULL, 'ativo', '2025-12-10 14:15:03', '2025-12-10 14:24:29'),
(17, 1, 'testenovop', '1', 0.01, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_693983f26c4a61.72410777.jpg', 1, NULL, 'ativo', '2025-12-10 14:30:10', NULL);

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
(9, 17, '../uploads/produtos/prod_693983f26c4a61.72410777.jpg', 0, '2025-12-10 14:30:10');

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_comprador`
--

CREATE TABLE `propostas_comprador` (
  `id` int(11) NOT NULL,
  `comprador_id` int(11) NOT NULL,
  `preco_proposto` decimal(10,2) NOT NULL,
  `quantidade_proposta` int(11) NOT NULL,
  `condicoes_compra` text DEFAULT NULL,
  `data_proposta` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enviada','pendente','aceita','recusada','finalizada') NOT NULL DEFAULT 'enviada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas_comprador`
--

INSERT INTO `propostas_comprador` (`id`, `comprador_id`, `preco_proposto`, `quantidade_proposta`, `condicoes_compra`, `data_proposta`, `status`) VALUES
(59, 1, 18.00, 1, 'teste', '2025-12-09 13:17:17', 'enviada');

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_negociacao`
--

CREATE TABLE `propostas_negociacao` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `proposta_comprador_id` int(11) NOT NULL,
  `proposta_vendedor_id` int(11) DEFAULT NULL,
  `preco_final` decimal(10,2) DEFAULT NULL,
  `quantidade_final` int(11) DEFAULT NULL,
  `status` enum('aceita','negociacao','recusada','finalizada') NOT NULL DEFAULT 'negociacao',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_negociacao_old`
--

CREATE TABLE `propostas_negociacao_old` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL COMMENT 'O anúncio ao qual a proposta se refere',
  `comprador_id` int(11) NOT NULL COMMENT 'O comprador que fez a proposta',
  `preco_proposto` decimal(10,2) NOT NULL COMMENT 'Preço unitário (por Kg, por exemplo) proposto pelo comprador',
  `quantidade_proposta` int(11) NOT NULL COMMENT 'Quantidade total em Kg ou unidades proposta',
  `condicoes_comprador` text DEFAULT NULL,
  `status` enum('pendente','aceita','recusada','finalizada','cancelada','negociacao') NOT NULL DEFAULT 'pendente',
  `data_proposta` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_resposta` datetime DEFAULT NULL,
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `observacoes_vendedor` text DEFAULT NULL,
  `observacoes_vendedor_teste` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_vendedor`
--

CREATE TABLE `propostas_vendedor` (
  `id` int(11) NOT NULL,
  `proposta_comprador_id` int(11) NOT NULL,
  `vendedor_id` int(11) NOT NULL,
  `preco_proposto` decimal(10,2) NOT NULL,
  `quantidade_proposta` int(11) NOT NULL,
  `condicoes_venda` text DEFAULT NULL,
  `data_contra_proposta` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enviada','pendente','aceita','recusada','finalizada') NOT NULL DEFAULT 'enviada',
  `observacao` text DEFAULT NULL
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
(17, 17, 'Jorge', 'ok@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"name\":\"Jorge\",\"email\":\"ok@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"166.076.628-11\",\"nomeComercialComprador\":\"Jorge\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99656-3500\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$Doo5HH4LGV82QBL\\/kFRmu.YyBAm\\/lTgrxGy30amMAqe4WHGiiLwkW\"}', 'pendente', '2025-12-09 13:10:53', NULL, NULL, NULL);

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
  `estado` varchar(2) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `plano` enum('free','basico','premium','empresarial') DEFAULT 'free'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `transportadores`
--

INSERT INTO `transportadores` (`id`, `usuario_id`, `nome_comercial`, `telefone`, `antt`, `numero_antt`, `placa_veiculo`, `modelo_veiculo`, `descricao_veiculo`, `estado`, `cidade`, `plano`) VALUES
(1, 7, NULL, '11996563500', NULL, '13231231434142243', '111-1111', 'teste', 'carreta', 'SP', 'Campinas', 'free');

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
(8, 'comprador@gmail.com', '$2y$10$eeHvPWmRt.UfXNCIhs.0GuX5hR8IS/sPBZj/LxhzRRHnuRJOU4k2W', 'comprador', 'Comprador', 'ativo', '2025-11-17 18:55:39', NULL, NULL, NULL),
(9, 'vendedor2@gmail.com', '$2y$10$s.T35LUiwMUk90sR5QNhResWbIid1TkOZlo7GrUjavpSlqeMvwnW2', 'vendedor', 'vendedor2', 'ativo', '2025-11-20 17:40:01', NULL, NULL, NULL),
(11, 'jorgev@gmail.com', '$2y$10$dYUUcwFHeSAZKAHMbystPe22T1fZtfuBiMhbKy1Zje7PraxRIqb0G', 'vendedor', 'JorgeV', 'ativo', '2025-12-02 12:49:02', NULL, NULL, NULL),
(12, 'teste@gmail.com', '$2y$10$aXdfuVEyQhKloWsi5stRa.8.EBl6Oo5NI7mf.ZDXP19qg7kp5m206', 'vendedor', 'teste', 'ativo', '2025-12-02 12:52:04', NULL, NULL, NULL),
(13, 'teste2@gmail.com', '$2y$10$4Ce1YFZHYbe77LxsbLr.O.g4zvLdtb65BZsGJwEDPw2pTLBdq.tqe', 'comprador', 'teste', 'ativo', '2025-12-02 14:16:39', NULL, NULL, NULL),
(14, 'testando@gmail.com', '$2y$10$NfhFfw0I8bgyOGANrDkG9u/gkWg6EeHAKy/97wU7u7bVhe01M4wCW', 'vendedor', 'testando', 'ativo', '2025-12-04 01:15:01', NULL, NULL, NULL),
(17, 'ok@gmail.com', '$2y$10$Doo5HH4LGV82QBL/kFRmu.YyBAm/lTgrxGy30amMAqe4WHGiiLwkW', 'comprador', 'Jorge', 'pendente', '2025-12-09 13:10:53', NULL, NULL, NULL);

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
  `plano` enum('free','basico','premium','empresarial') DEFAULT 'free'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vendedores`
--

INSERT INTO `vendedores` (`id`, `usuario_id`, `tipo_pessoa`, `nome_comercial`, `cpf_cnpj`, `razao_social`, `foto_perfil_url`, `cip`, `cep`, `rua`, `numero`, `complemento`, `estado`, `cidade`, `telefone1`, `telefone2`, `plano`) VALUES
(1, 4, 'cnpj', 'Vendedor', '11.111.111/1111-11', '', '../uploads/vendedores/vend_1_693811ef0cf33.jpg', NULL, '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(2, 9, 'cnpj', 'vendedor2', '111111111111', NULL, NULL, NULL, '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11111111111', NULL, 'free'),
(3, 11, 'cnpj', 'Rondon', '41111584800', NULL, NULL, '1212121', '13211873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(4, 12, 'cnpj', 'teste', '1111111111111111111', NULL, NULL, '11111', '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(5, 14, 'cnpj', 'teste', '111111111111111111', NULL, NULL, '1111', '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11111111111', NULL, 'basico');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `acordos`
--
ALTER TABLE `acordos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversa_id` (`conversa_id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `admin_acoes`
--
ALTER TABLE `admin_acoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Índices de tabela `chat_conversas`
--
ALTER TABLE `chat_conversas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conversa_unica` (`produto_id`,`comprador_id`,`vendedor_id`),
  ADD KEY `comprador_id` (`comprador_id`),
  ADD KEY `vendedor_id` (`vendedor_id`);

--
-- Índices de tabela `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversa` (`conversa_id`),
  ADD KEY `idx_remetente` (`remetente_id`),
  ADD KEY `idx_data` (`data_envio`);

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
-- Índices de tabela `propostas_comprador`
--
ALTER TABLE `propostas_comprador`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comprador_id` (`comprador_id`);

--
-- Índices de tabela `propostas_negociacao`
--
ALTER TABLE `propostas_negociacao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `proposta_comprador_id` (`proposta_comprador_id`),
  ADD KEY `proposta_vendedor_id` (`proposta_vendedor_id`);

--
-- Índices de tabela `propostas_negociacao_old`
--
ALTER TABLE `propostas_negociacao_old`
  ADD PRIMARY KEY (`id`),
  ADD KEY `propostas_negociacao_ibfk_1` (`produto_id`),
  ADD KEY `propostas_negociacao_ibfk_2` (`comprador_id`);

--
-- Índices de tabela `propostas_vendedor`
--
ALTER TABLE `propostas_vendedor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proposta_comprador_id` (`proposta_comprador_id`),
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
-- Índices de tabela `vendedores`
--
ALTER TABLE `vendedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `acordos`
--
ALTER TABLE `acordos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `admin_acoes`
--
ALTER TABLE `admin_acoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `chat_conversas`
--
ALTER TABLE `chat_conversas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `chat_mensagens`
--
ALTER TABLE `chat_mensagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `compradores`
--
ALTER TABLE `compradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `conversas`
--
ALTER TABLE `conversas`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `produto_imagens`
--
ALTER TABLE `produto_imagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `propostas_comprador`
--
ALTER TABLE `propostas_comprador`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT de tabela `propostas_negociacao`
--
ALTER TABLE `propostas_negociacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT de tabela `propostas_negociacao_old`
--
ALTER TABLE `propostas_negociacao_old`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `propostas_vendedor`
--
ALTER TABLE `propostas_vendedor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de tabela `solicitacoes_cadastro`
--
ALTER TABLE `solicitacoes_cadastro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `transportadores`
--
ALTER TABLE `transportadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `vendedores`
--
ALTER TABLE `vendedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `acordos`
--
ALTER TABLE `acordos`
  ADD CONSTRAINT `acordos_ibfk_1` FOREIGN KEY (`conversa_id`) REFERENCES `conversas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `acordos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

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
-- Restrições para tabelas `propostas_comprador`
--
ALTER TABLE `propostas_comprador`
  ADD CONSTRAINT `propostas_comprador_ibfk_1` FOREIGN KEY (`comprador_id`) REFERENCES `compradores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `propostas_negociacao`
--
ALTER TABLE `propostas_negociacao`
  ADD CONSTRAINT `propostas_negociacao_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_negociacao_ibfk_2` FOREIGN KEY (`proposta_comprador_id`) REFERENCES `propostas_comprador` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_negociacao_ibfk_3` FOREIGN KEY (`proposta_vendedor_id`) REFERENCES `propostas_vendedor` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `propostas_negociacao_old`
--
ALTER TABLE `propostas_negociacao_old`
  ADD CONSTRAINT `propostas_negociacao_old_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_negociacao_old_ibfk_2` FOREIGN KEY (`comprador_id`) REFERENCES `compradores` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `propostas_vendedor`
--
ALTER TABLE `propostas_vendedor`
  ADD CONSTRAINT `propostas_vendedor_ibfk_1` FOREIGN KEY (`proposta_comprador_id`) REFERENCES `propostas_comprador` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_vendedor_ibfk_2` FOREIGN KEY (`vendedor_id`) REFERENCES `vendedores` (`id`) ON DELETE CASCADE;

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
-- Restrições para tabelas `vendedores`
--
ALTER TABLE `vendedores`
  ADD CONSTRAINT `vendedores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
