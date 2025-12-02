-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 02/12/2025 às 15:18
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
CREATE DATABASE IF NOT EXISTS `encontre_ocampo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `encontre_ocampo`;

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
(13, 1, 'Aprovou cadastro de vendedor (ID: 12)', 'usuarios', 12, '2025-12-02 12:52:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `compradores`
--

CREATE TABLE `compradores` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_pessoa` enum('cpf','cnpj') DEFAULT NULL,
  `nome_comercial` varchar(255) DEFAULT NULL,
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

INSERT INTO `compradores` (`id`, `usuario_id`, `tipo_pessoa`, `nome_comercial`, `cpf_cnpj`, `cip`, `cep`, `rua`, `numero`, `complemento`, `estado`, `cidade`, `telefone1`, `telefone2`, `plano`) VALUES
(1, 3, NULL, 'Jorge Pontes', '411.115.848-00', NULL, '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(2, 8, NULL, NULL, '123.456.789-99', NULL, '13218-200', 'Rua Paschoal Segre', '225', NULL, 'SP', 'Jundiaí', '11912341234', NULL, 'free'),
(3, 13, 'cpf', 'Jorginho', '411.115.848-00', '', '13211-873', 'Rua Seis', '206', '', 'SP', 'Jundiaí', '(11) 99656-3500', '', 'free');

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

--
-- Despejando dados para a tabela `favoritos`
--

INSERT INTO `favoritos` (`id`, `usuario_id`, `produto_id`, `data_criacao`) VALUES
(3, 3, 2, '2025-11-20 17:35:23');

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
(1, 1, 'Nova solicitação de cadastro de vendedor: SILENE CRISTINA POSSANI', 'info', 0, 'src/admin/solicitacoes.php', '2025-12-01 14:14:23'),
(2, 1, 'Nova solicitação de cadastro de vendedor: JorgeV', 'info', 0, 'src/admin/solicitacoes.php', '2025-12-02 12:44:56'),
(3, 1, 'Nova solicitação de cadastro de comprador: teste', 'info', 0, 'src/admin/solicitacoes.php', '2025-12-02 14:16:39');

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
(2, 1, 'uvas', 'uvas verdes', 3.33, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_691527af8dea16.84286255.jpg', 10, NULL, 'ativo', '2025-11-13 00:34:55', NULL),
(6, 1, 'Limão', 'teste', 5.00, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_691d2b4d8c5da3.53661953.jpeg', 90, NULL, 'ativo', '2025-11-19 02:28:29', '2025-11-19 03:12:54'),
(7, 1, 'Teste esgotar estoque', '', 5.00, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_691d3821c54958.39837631.jpeg', 0, NULL, 'ativo', '2025-11-19 03:23:13', '2025-11-20 22:10:44'),
(8, 2, 'banana', 'bananas amarelas', 2.22, NULL, 0.00, 0, NULL, NULL, 'Frutas Tropicais', '../uploads/produtos/prod_691f52c5b25cb6.01137275.jpeg', 12, NULL, 'ativo', '2025-11-20 17:41:25', NULL),
(9, 2, 'melancia', 'melancias', 1.11, NULL, 0.00, 0, NULL, NULL, 'Frutas Cítricas', '../uploads/produtos/prod_691f52db2a3106.65007213.jpg', 1, NULL, 'ativo', '2025-11-20 17:41:47', NULL),
(10, 1, 'teste', '', 20.00, 18.00, 10.00, 1, NULL, NULL, 'Frutas Tropicais', '../uploads/produtos/prod_692ce592138434.75940422.jpeg', 1, NULL, 'ativo', '2025-11-20 17:43:41', '2025-12-01 12:43:39'),
(11, 1, 'Mamao', 'mamao tropical', 100.00, NULL, 0.00, 0, NULL, NULL, 'Frutas Tropicais', '../uploads/produtos/prod_692e48c33210d6.50109901.png', 100, NULL, 'ativo', '2025-12-02 02:02:43', NULL),
(12, 3, 'testando', 'Lorem ipsum dolor sit amet consectetur, adipisicing elit. Laudantium ipsam similique illo pariatur minus? Inventore, repellendus placeat. Vitae illum architecto, animi et a ex sit aspernatur assumenda saepe necessitatibus incidunt! Quisquam, dicta.', 0.01, NULL, 0.00, 0, NULL, NULL, 'Frutas Exóticas', '../uploads/produtos/prod_692ee095e98983.96559895.jpg', 1, NULL, 'ativo', '2025-12-02 12:50:29', NULL);

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
  `status` enum('enviada','pendente','aceita','recusada') NOT NULL DEFAULT 'enviada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas_comprador`
--

INSERT INTO `propostas_comprador` (`id`, `comprador_id`, `preco_proposto`, `quantidade_proposta`, `condicoes_compra`, `data_proposta`, `status`) VALUES
(6, 1, 11.00, 1, 'teste', '2025-11-14 19:13:44', ''),
(7, 2, 10.00, 5, 'testando proposta', '2025-11-17 19:20:14', 'enviada'),
(12, 2, 4.00, 10, 'teste ACEITAR', '2025-11-19 02:34:24', 'aceita'),
(13, 2, 4.00, 10, 'teste CONTRAPROPOSTA', '2025-11-19 02:35:23', 'pendente'),
(14, 2, 1.00, 1, 'teste NEGAR', '2025-11-19 02:35:43', 'recusada'),
(15, 2, 4.00, 10, 'teste novo ACEITAR', '2025-11-19 03:12:32', 'aceita'),
(16, 2, 5.00, 10, NULL, '2025-11-19 03:23:36', 'aceita'),
(17, 2, 1.00, 1, 'teste RECUSAR', '2025-11-19 03:43:01', 'recusada'),
(18, 2, 1.00, 1, 'teste CONTADOR propostas pendentes vendedor', '2025-11-19 14:58:29', 'recusada'),
(19, 2, 80.00, 80, 'proposta editada', '2025-12-02 02:06:06', 'enviada'),
(21, 2, 100.00, 1, 'ACEITAR', '2025-12-02 03:08:06', 'aceita'),
(22, 2, 100.00, 1, 'RECUSAR', '2025-12-02 03:08:55', 'recusada'),
(31, 2, 85.00, 85, 'C', '2025-12-02 06:44:08', 'aceita'),
(34, 2, 100.00, 1, NULL, '2025-12-02 07:14:18', 'recusada'),
(35, 2, 100.00, 1, NULL, '2025-12-02 07:19:42', 'enviada');

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

--
-- Despejando dados para a tabela `propostas_negociacao`
--

INSERT INTO `propostas_negociacao` (`id`, `produto_id`, `proposta_comprador_id`, `proposta_vendedor_id`, `preco_final`, `quantidade_final`, `status`, `data_criacao`, `data_atualizacao`) VALUES
(1, 2, 6, NULL, 11.00, 1, '', '2025-11-14 19:13:44', NULL),
(2, 2, 7, NULL, 10.00, 5, '', '2025-11-17 19:20:14', NULL),
(3, 6, 12, NULL, 4.00, 10, 'aceita', '2025-11-19 02:34:24', NULL),
(4, 6, 13, NULL, 4.00, 10, 'negociacao', '2025-11-19 02:35:23', NULL),
(5, 6, 14, NULL, 1.00, 1, 'recusada', '2025-11-19 02:35:43', NULL),
(6, 6, 15, NULL, 4.00, 10, 'aceita', '2025-11-19 03:12:32', NULL),
(7, 7, 16, NULL, 5.00, 10, 'aceita', '2025-11-19 03:23:36', NULL),
(8, 6, 17, NULL, 1.00, 1, 'recusada', '2025-11-19 03:43:01', NULL),
(9, 6, 18, NULL, 1.00, 1, 'recusada', '2025-11-19 14:58:29', NULL),
(18, 11, 21, NULL, NULL, NULL, 'aceita', '2025-12-02 03:08:06', '2025-12-02 04:49:40'),
(19, 11, 22, NULL, NULL, NULL, 'recusada', '2025-12-02 03:08:55', '2025-12-02 04:49:03'),
(29, 11, 31, 15, NULL, NULL, 'aceita', '2025-12-02 06:44:08', '2025-12-02 07:13:40'),
(32, 11, 34, 16, NULL, NULL, 'recusada', '2025-12-02 07:14:18', '2025-12-02 07:15:49'),
(33, 11, 35, NULL, NULL, NULL, 'negociacao', '2025-12-02 07:19:42', '2025-12-02 07:20:29');

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

--
-- Despejando dados para a tabela `propostas_negociacao_old`
--

INSERT INTO `propostas_negociacao_old` (`id`, `produto_id`, `comprador_id`, `preco_proposto`, `quantidade_proposta`, `condicoes_comprador`, `status`, `data_proposta`, `data_resposta`, `data_atualizacao`, `observacoes_vendedor`, `observacoes_vendedor_teste`) VALUES
(6, 2, 1, 11.00, 1, 'teste', '', '2025-11-14 19:13:44', '2025-11-15 16:17:19', '2025-11-15 19:17:19', NULL, NULL),
(7, 2, 2, 10.00, 5, 'testando proposta', 'pendente', '2025-11-17 19:20:14', NULL, NULL, NULL, NULL),
(12, 6, 2, 4.00, 10, 'teste ACEITAR', 'aceita', '2025-11-19 02:34:24', '2025-11-18 23:59:06', '2025-11-19 02:59:06', NULL, NULL),
(13, 6, 2, 4.00, 10, 'teste CONTRAPROPOSTA', 'negociacao', '2025-11-19 02:35:23', '2025-11-19 12:02:15', '2025-11-19 15:11:47', 'testando CONTRAPROPOSTA', NULL),
(14, 6, 2, 1.00, 1, 'teste NEGAR', 'recusada', '2025-11-19 02:35:43', '2025-11-19 00:29:34', '2025-11-19 03:44:42', NULL, NULL),
(15, 6, 2, 4.00, 10, 'teste novo ACEITAR', 'aceita', '2025-11-19 03:12:32', '2025-11-19 00:12:54', '2025-11-19 03:12:54', NULL, NULL),
(16, 7, 2, 5.00, 10, NULL, 'aceita', '2025-11-19 03:23:36', '2025-11-19 00:23:59', '2025-11-19 03:23:59', NULL, NULL),
(17, 6, 2, 1.00, 1, 'teste RECUSAR', 'recusada', '2025-11-19 03:43:01', '2025-11-19 00:43:37', '2025-11-19 03:43:37', NULL, NULL),
(18, 6, 2, 1.00, 1, 'teste CONTADOR propostas pendentes vendedor', 'recusada', '2025-11-19 14:58:29', '2025-11-19 11:58:51', '2025-11-19 14:58:51', NULL, NULL);

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
  `status` enum('enviada','pendente','aceita','recusada') NOT NULL DEFAULT 'enviada',
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `propostas_vendedor`
--

INSERT INTO `propostas_vendedor` (`id`, `proposta_comprador_id`, `vendedor_id`, `preco_proposto`, `quantidade_proposta`, `condicoes_venda`, `data_contra_proposta`, `status`, `observacao`) VALUES
(14, 31, 1, 25.00, 50, 'B', '2025-12-02 06:46:42', 'enviada', NULL),
(15, 31, 1, 30.00, 30, 'D', '2025-12-02 07:13:03', 'enviada', NULL),
(16, 34, 1, 10.00, 10, '', '2025-12-02 07:14:59', 'enviada', NULL);

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
(10, NULL, 'testando', 'testando@gmail.com', '11111111111', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"estadoComprador\":\"SP\",\"nomeComercialVendedor\":\"teste\",\"cpfCnpjVendedor\":\"111111111111111111\",\"cipVendedor\":\"1111\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundia\\u00ed\",\"telefone1Vendedor\":\"11111111111\",\"planoVendedor\":\"basico\",\"senha_hash\":\"$2y$10$NfhFfw0I8bgyOGANrDkG9u\\/gkWg6EeHAKy\\/97wU7u7bVhe01M4wCW\"}', 'pendente', '2025-12-01 13:49:16', NULL, NULL, NULL),
(11, 12, 'teste', 'teste@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"estadoComprador\":\"SP\",\"nomeComercialVendedor\":\"teste\",\"cpfCnpjVendedor\":\"1111111111111111111\",\"cipVendedor\":\"11111\",\"cepVendedor\":\"13211-873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundia\\u00ed\",\"telefone1Vendedor\":\"11996563500\",\"planoVendedor\":\"basico\",\"senha_hash\":\"$2y$10$aXdfuVEyQhKloWsi5stRa.8.EBl6Oo5NI7mf.ZDXP19qg7kp5m206\"}', 'aprovado', '2025-12-01 14:09:40', '2025-12-02 12:52:04', NULL, NULL),
(12, NULL, 'SILENE CRISTINA POSSANI', 'testa@gamil.com', '1111111111', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"nome\":\"SILENE CRISTINA POSSANI\",\"email\":\"testa@gamil.com\",\"tipo_solicitacao\":\"vendedor\",\"senha_hash\":\"$2y$10$IHfi0i6llU30Ws6hBl.L7eyrgTXncaSDhDxBRCOvpeWUbF1WCOgOC\",\"nomeComercialVendedor\":\"teste\",\"cpfCnpjVendedor\":\"41111584800\",\"cipVendedor\":\"21212121212\",\"cepVendedor\":\"13211873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"1111111111\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"basico\"}', 'pendente', '2025-12-01 14:14:23', NULL, NULL, NULL),
(13, 11, 'JorgeV', 'jorgev@gmail.com', '11996563500', 'Rua Seis, 206, Jundiaí, SP', 'vendedor', '{\"nome\":\"JorgeV\",\"email\":\"jorgev@gmail.com\",\"tipo_solicitacao\":\"vendedor\",\"senha_hash\":\"$2y$10$dYUUcwFHeSAZKAHMbystPe22T1fZtfuBiMhbKy1Zje7PraxRIqb0G\",\"nomeComercialVendedor\":\"Rondon\",\"cpfCnpjVendedor\":\"41111584800\",\"cipVendedor\":\"1212121\",\"cepVendedor\":\"13211873\",\"ruaVendedor\":\"Rua Seis\",\"numeroVendedor\":\"206\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"Jundiaí\",\"telefone1Vendedor\":\"11996563500\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"basico\"}', 'aprovado', '2025-12-02 12:44:56', '2025-12-02 12:49:02', NULL, NULL),
(14, 13, 'teste', 'teste2@gmail.com', '(11) 99656-3500', 'Rua Seis, 206, Jundiaí, SP', 'comprador', '{\"name\":\"teste\",\"email\":\"teste2@gmail.com\",\"subject\":\"comprador\",\"tipoPessoaComprador\":\"cpf\",\"cpfCnpjComprador\":\"411.115.848-00\",\"nomeComercialComprador\":\"Jorginho\",\"cipComprador\":\"\",\"cepComprador\":\"13211-873\",\"ruaComprador\":\"Rua Seis\",\"numeroComprador\":\"206\",\"complementoComprador\":\"\",\"estadoComprador\":\"SP\",\"cidadeComprador\":\"Jundiaí\",\"telefone1Comprador\":\"(11) 99656-3500\",\"telefone2Comprador\":\"\",\"planoComprador\":\"free\",\"nomeComercialVendedor\":\"\",\"cpfCnpjVendedor\":\"\",\"cipVendedor\":\"\",\"cepVendedor\":\"\",\"ruaVendedor\":\"\",\"numeroVendedor\":\"\",\"complementoVendedor\":\"\",\"estadoVendedor\":\"SP\",\"cidadeVendedor\":\"\",\"telefone1Vendedor\":\"\",\"telefone2Vendedor\":\"\",\"planoVendedor\":\"free\",\"telefoneTransportador\":\"\",\"numeroANTT\":\"\",\"placaVeiculo\":\"\",\"modeloVeiculo\":\"\",\"descricaoVeiculo\":\"\",\"estadoTransportador\":\"\",\"cidadeTransportador\":\"\",\"message\":\"\",\"tipo_pessoa_comprador\":\"cpf\",\"senha_hash\":\"$2y$10$4Ce1YFZHYbe77LxsbLr.O.g4zvLdtb65BZsGJwEDPw2pTLBdq.tqe\"}', 'pendente', '2025-12-02 14:16:39', NULL, NULL, NULL);

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
(13, 'teste2@gmail.com', '$2y$10$4Ce1YFZHYbe77LxsbLr.O.g4zvLdtb65BZsGJwEDPw2pTLBdq.tqe', 'comprador', 'teste', 'pendente', '2025-12-02 14:16:39', NULL, NULL, NULL);

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
(1, 4, 'cnpj', 'Vendedor', '11.111.111/1111-11', '', '../uploads/vendedores/vend_1_691529daacfe9.png', NULL, '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(2, 9, 'cnpj', 'vendedor2', '111111111111', NULL, NULL, NULL, '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11111111111', NULL, 'free'),
(3, 11, 'cnpj', 'Rondon', '41111584800', NULL, NULL, '1212121', '13211873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free'),
(4, 12, 'cnpj', 'teste', '1111111111111111111', NULL, NULL, '11111', '13211-873', 'Rua Seis', '206', NULL, 'SP', 'Jundiaí', '11996563500', NULL, 'free');

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
-- Índices de tabela `compradores`
--
ALTER TABLE `compradores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

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
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendedor_id` (`vendedor_id`);

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
-- AUTO_INCREMENT de tabela `admin_acoes`
--
ALTER TABLE `admin_acoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `compradores`
--
ALTER TABLE `compradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `propostas_comprador`
--
ALTER TABLE `propostas_comprador`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `propostas_negociacao`
--
ALTER TABLE `propostas_negociacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `propostas_negociacao_old`
--
ALTER TABLE `propostas_negociacao_old`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `propostas_vendedor`
--
ALTER TABLE `propostas_vendedor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `solicitacoes_cadastro`
--
ALTER TABLE `solicitacoes_cadastro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `transportadores`
--
ALTER TABLE `transportadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `vendedores`
--
ALTER TABLE `vendedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `admin_acoes`
--
ALTER TABLE `admin_acoes`
  ADD CONSTRAINT `admin_acoes_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `compradores`
--
ALTER TABLE `compradores`
  ADD CONSTRAINT `compradores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

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
--
-- Banco de dados: `phpmyadmin`
--
CREATE DATABASE IF NOT EXISTS `phpmyadmin` DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
USE `phpmyadmin`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__bookmark`
--

CREATE TABLE `pma__bookmark` (
  `id` int(10) UNSIGNED NOT NULL,
  `dbase` varchar(255) NOT NULL DEFAULT '',
  `user` varchar(255) NOT NULL DEFAULT '',
  `label` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `query` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Bookmarks';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__central_columns`
--

CREATE TABLE `pma__central_columns` (
  `db_name` varchar(64) NOT NULL,
  `col_name` varchar(64) NOT NULL,
  `col_type` varchar(64) NOT NULL,
  `col_length` text DEFAULT NULL,
  `col_collation` varchar(64) NOT NULL,
  `col_isNull` tinyint(1) NOT NULL,
  `col_extra` varchar(255) DEFAULT '',
  `col_default` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Central list of columns';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__column_info`
--

CREATE TABLE `pma__column_info` (
  `id` int(5) UNSIGNED NOT NULL,
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `column_name` varchar(64) NOT NULL DEFAULT '',
  `comment` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `mimetype` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `transformation` varchar(255) NOT NULL DEFAULT '',
  `transformation_options` varchar(255) NOT NULL DEFAULT '',
  `input_transformation` varchar(255) NOT NULL DEFAULT '',
  `input_transformation_options` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Column information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__designer_settings`
--

CREATE TABLE `pma__designer_settings` (
  `username` varchar(64) NOT NULL,
  `settings_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Settings related to Designer';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__export_templates`
--

CREATE TABLE `pma__export_templates` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `export_type` varchar(10) NOT NULL,
  `template_name` varchar(64) NOT NULL,
  `template_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved export templates';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__favorite`
--

CREATE TABLE `pma__favorite` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Favorite tables';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__history`
--

CREATE TABLE `pma__history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db` varchar(64) NOT NULL DEFAULT '',
  `table` varchar(64) NOT NULL DEFAULT '',
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp(),
  `sqlquery` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='SQL history for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__navigationhiding`
--

CREATE TABLE `pma__navigationhiding` (
  `username` varchar(64) NOT NULL,
  `item_name` varchar(64) NOT NULL,
  `item_type` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Hidden items of navigation tree';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__pdf_pages`
--

CREATE TABLE `pma__pdf_pages` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `page_nr` int(10) UNSIGNED NOT NULL,
  `page_descr` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='PDF relation pages for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__recent`
--

CREATE TABLE `pma__recent` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Recently accessed tables';

--
-- Despejando dados para a tabela `pma__recent`
--

INSERT INTO `pma__recent` (`username`, `tables`) VALUES
('root', '[{\"db\":\"risenglish\",\"table\":\"conteudos\"}]');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__relation`
--

CREATE TABLE `pma__relation` (
  `master_db` varchar(64) NOT NULL DEFAULT '',
  `master_table` varchar(64) NOT NULL DEFAULT '',
  `master_field` varchar(64) NOT NULL DEFAULT '',
  `foreign_db` varchar(64) NOT NULL DEFAULT '',
  `foreign_table` varchar(64) NOT NULL DEFAULT '',
  `foreign_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Relation table';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__savedsearches`
--

CREATE TABLE `pma__savedsearches` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `search_name` varchar(64) NOT NULL DEFAULT '',
  `search_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved searches';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_coords`
--

CREATE TABLE `pma__table_coords` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `pdf_page_number` int(11) NOT NULL DEFAULT 0,
  `x` float UNSIGNED NOT NULL DEFAULT 0,
  `y` float UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table coordinates for phpMyAdmin PDF output';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_info`
--

CREATE TABLE `pma__table_info` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `display_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table information for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__table_uiprefs`
--

CREATE TABLE `pma__table_uiprefs` (
  `username` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `prefs` text NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Tables'' UI preferences';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__tracking`
--

CREATE TABLE `pma__tracking` (
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `version` int(10) UNSIGNED NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `schema_snapshot` text NOT NULL,
  `schema_sql` text DEFAULT NULL,
  `data_sql` longtext DEFAULT NULL,
  `tracking` set('UPDATE','REPLACE','INSERT','DELETE','TRUNCATE','CREATE DATABASE','ALTER DATABASE','DROP DATABASE','CREATE TABLE','ALTER TABLE','RENAME TABLE','DROP TABLE','CREATE INDEX','DROP INDEX','CREATE VIEW','ALTER VIEW','DROP VIEW') DEFAULT NULL,
  `tracking_active` int(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Database changes tracking for phpMyAdmin';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__userconfig`
--

CREATE TABLE `pma__userconfig` (
  `username` varchar(64) NOT NULL,
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `config_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User preferences storage for phpMyAdmin';

--
-- Despejando dados para a tabela `pma__userconfig`
--

INSERT INTO `pma__userconfig` (`username`, `timevalue`, `config_data`) VALUES
('root', '2025-10-02 13:57:59', '{\"Console\\/Mode\":\"collapse\",\"lang\":\"pt_BR\"}');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__usergroups`
--

CREATE TABLE `pma__usergroups` (
  `usergroup` varchar(64) NOT NULL,
  `tab` varchar(64) NOT NULL,
  `allowed` enum('Y','N') NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User groups with configured menu items';

-- --------------------------------------------------------

--
-- Estrutura para tabela `pma__users`
--

CREATE TABLE `pma__users` (
  `username` varchar(64) NOT NULL,
  `usergroup` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Users and their assignments to user groups';

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `pma__central_columns`
--
ALTER TABLE `pma__central_columns`
  ADD PRIMARY KEY (`db_name`,`col_name`);

--
-- Índices de tabela `pma__column_info`
--
ALTER TABLE `pma__column_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `db_name` (`db_name`,`table_name`,`column_name`);

--
-- Índices de tabela `pma__designer_settings`
--
ALTER TABLE `pma__designer_settings`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`);

--
-- Índices de tabela `pma__favorite`
--
ALTER TABLE `pma__favorite`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__history`
--
ALTER TABLE `pma__history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`,`db`,`table`,`timevalue`);

--
-- Índices de tabela `pma__navigationhiding`
--
ALTER TABLE `pma__navigationhiding`
  ADD PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`);

--
-- Índices de tabela `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  ADD PRIMARY KEY (`page_nr`),
  ADD KEY `db_name` (`db_name`);

--
-- Índices de tabela `pma__recent`
--
ALTER TABLE `pma__recent`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__relation`
--
ALTER TABLE `pma__relation`
  ADD PRIMARY KEY (`master_db`,`master_table`,`master_field`),
  ADD KEY `foreign_field` (`foreign_db`,`foreign_table`);

--
-- Índices de tabela `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`);

--
-- Índices de tabela `pma__table_coords`
--
ALTER TABLE `pma__table_coords`
  ADD PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`);

--
-- Índices de tabela `pma__table_info`
--
ALTER TABLE `pma__table_info`
  ADD PRIMARY KEY (`db_name`,`table_name`);

--
-- Índices de tabela `pma__table_uiprefs`
--
ALTER TABLE `pma__table_uiprefs`
  ADD PRIMARY KEY (`username`,`db_name`,`table_name`);

--
-- Índices de tabela `pma__tracking`
--
ALTER TABLE `pma__tracking`
  ADD PRIMARY KEY (`db_name`,`table_name`,`version`);

--
-- Índices de tabela `pma__userconfig`
--
ALTER TABLE `pma__userconfig`
  ADD PRIMARY KEY (`username`);

--
-- Índices de tabela `pma__usergroups`
--
ALTER TABLE `pma__usergroups`
  ADD PRIMARY KEY (`usergroup`,`tab`,`allowed`);

--
-- Índices de tabela `pma__users`
--
ALTER TABLE `pma__users`
  ADD PRIMARY KEY (`username`,`usergroup`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__column_info`
--
ALTER TABLE `pma__column_info`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__history`
--
ALTER TABLE `pma__history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  MODIFY `page_nr` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Banco de dados: `risenglish`
--
CREATE DATABASE IF NOT EXISTS `risenglish` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `risenglish`;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_turmas`
--

CREATE TABLE `alunos_turmas` (
  `id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `alunos_turmas`
--

INSERT INTO `alunos_turmas` (`id`, `aluno_id`, `turma_id`) VALUES
(27, 17, 8);

-- --------------------------------------------------------

--
-- Estrutura para tabela `arquivos_visiveis`
--

CREATE TABLE `arquivos_visiveis` (
  `id` int(11) NOT NULL,
  `aula_id` int(11) NOT NULL,
  `conteudo_id` int(11) NOT NULL,
  `visivel` tinyint(1) NOT NULL DEFAULT 1,
  `data_modificacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `aulas`
--

CREATE TABLE `aulas` (
  `id` int(11) NOT NULL,
  `titulo_aula` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_aula` date NOT NULL,
  `horario` time NOT NULL,
  `turma_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `recorrente` tinyint(1) NOT NULL DEFAULT 0,
  `dia_semana` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `aulas`
--

INSERT INTO `aulas` (`id`, `titulo_aula`, `descricao`, `data_aula`, `horario`, `turma_id`, `professor_id`, `recorrente`, `dia_semana`) VALUES
(39, 'Aulas Jorge', '', '2025-11-17', '09:00:00', 8, 16, 1, 'monday'),
(40, 'Aulas Jorge', '', '2025-10-27', '09:00:00', 8, 16, 1, 'monday'),
(41, 'Aulas Jorge', '', '2025-11-03', '09:00:00', 8, 16, 1, 'monday'),
(42, 'Aulas Jorge', '', '2025-11-10', '09:00:00', 8, 16, 1, 'monday'),
(43, 'Aulas Jorge', '', '2025-11-17', '09:00:00', 8, 16, 1, 'monday');

-- --------------------------------------------------------

--
-- Estrutura para tabela `aulas_conteudos`
--

CREATE TABLE `aulas_conteudos` (
  `id` int(11) NOT NULL,
  `aula_id` int(11) NOT NULL,
  `conteudo_id` int(11) NOT NULL,
  `planejado` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `conteudos`
--

CREATE TABLE `conteudos` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo_arquivo` varchar(50) NOT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp(),
  `eh_subpasta` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `conteudos`
--

INSERT INTO `conteudos` (`id`, `professor_id`, `parent_id`, `titulo`, `descricao`, `tipo_arquivo`, `caminho_arquivo`, `data_upload`, `eh_subpasta`) VALUES
(58, 16, NULL, 'teste', '', 'TEMA', '', '2025-10-21 12:58:39', 0),
(59, 16, 58, 'teste', '', 'SUBPASTA', '', '2025-10-21 12:58:55', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `presenca_aula`
--

CREATE TABLE `presenca_aula` (
  `id` int(11) NOT NULL,
  `aula_id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `presente` tinyint(1) NOT NULL DEFAULT 1,
  `data_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `recursos_uteis`
--

CREATE TABLE `recursos_uteis` (
  `id` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `link` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `recursos_uteis`
--

INSERT INTO `recursos_uteis` (`id`, `titulo`, `link`, `descricao`, `data_criacao`) VALUES
(5, 'Linguee', 'https://www.linguee.com.br/', 'Dicionário inglês-português\r\ne buscador de traduções.', '2025-10-09 22:40:41'),
(6, 'DeepL', 'https://www.deepl.com/pt-BR/translator', 'Serviço de tradução que utiliza IA para oferecer traduções de qualidade.', '2025-10-16 10:07:00'),
(7, 'toPhonetics', 'https://tophonetics.com/', 'Conversos de textos em Inglês para sua tradução fonética.', '2025-10-16 10:07:44'),
(8, 'Youglish', 'https://pt.youglish.com/', 'O YouGlish te dá respostas rápidas sobre como o inglês é falado por pessoas reais dentro de um contexto.', '2025-10-16 10:08:26');

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE `turmas` (
  `id` int(11) NOT NULL,
  `nome_turma` varchar(100) NOT NULL,
  `professor_id` int(11) DEFAULT NULL,
  `inicio_turma` date NOT NULL,
  `link_aula` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `turmas`
--

INSERT INTO `turmas` (`id`, `nome_turma`, `professor_id`, `inicio_turma`, `link_aula`) VALUES
(8, 'Jorge', 16, '2025-10-20', 'https://meet.google.com/aji-dvjc-rqs');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo_usuario` enum('admin','professor','aluno') NOT NULL,
  `informacoes` text DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `token_expira_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo_usuario`, `informacoes`, `reset_token`, `token_expira_em`) VALUES
(1, 'Admin Risenglish', 'admin@risenglish.com', '$2y$10$/43jnz3JO8o5umNcabQ16eXnt1.pVdef3L7.6HvaILzUYbrRqPhBS', 'admin', NULL, NULL, NULL),
(16, 'Laura', 'laura@risenglish.com', '$2y$10$ITUszPmaUWJpTf0jeNCWbOTZb/zUPtYHPZXcJbZh1OcR/MdBrUz2S', 'professor', '', NULL, NULL),
(17, 'Jorge Pontes', 'jorgeappontes13@gmail.com', '$2y$10$lqwgCxf3iB/cYCQ/9iLRLe.r2YFZJbOYy3E2SaFjyGuj4JWeKvv.q', 'aluno', '', NULL, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alunos_turmas`
--
ALTER TABLE `alunos_turmas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aluno_id` (`aluno_id`,`turma_id`),
  ADD KEY `turma_id` (`turma_id`);

--
-- Índices de tabela `arquivos_visiveis`
--
ALTER TABLE `arquivos_visiveis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aula_conteudo_unique` (`aula_id`,`conteudo_id`),
  ADD KEY `aula_id` (`aula_id`),
  ADD KEY `conteudo_id` (`conteudo_id`);

--
-- Índices de tabela `aulas`
--
ALTER TABLE `aulas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Índices de tabela `aulas_conteudos`
--
ALTER TABLE `aulas_conteudos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aula_id` (`aula_id`,`conteudo_id`),
  ADD KEY `conteudo_id` (`conteudo_id`);

--
-- Índices de tabela `conteudos`
--
ALTER TABLE `conteudos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Índices de tabela `presenca_aula`
--
ALTER TABLE `presenca_aula`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aula_aluno_unique` (`aula_id`,`aluno_id`),
  ADD KEY `aluno_id` (`aluno_id`);

--
-- Índices de tabela `recursos_uteis`
--
ALTER TABLE `recursos_uteis`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos_turmas`
--
ALTER TABLE `alunos_turmas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de tabela `arquivos_visiveis`
--
ALTER TABLE `arquivos_visiveis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `aulas`
--
ALTER TABLE `aulas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT de tabela `aulas_conteudos`
--
ALTER TABLE `aulas_conteudos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT de tabela `conteudos`
--
ALTER TABLE `conteudos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT de tabela `presenca_aula`
--
ALTER TABLE `presenca_aula`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `recursos_uteis`
--
ALTER TABLE `recursos_uteis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `alunos_turmas`
--
ALTER TABLE `alunos_turmas`
  ADD CONSTRAINT `alunos_turmas_ibfk_1` FOREIGN KEY (`aluno_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alunos_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `arquivos_visiveis`
--
ALTER TABLE `arquivos_visiveis`
  ADD CONSTRAINT `arquivos_visiveis_ibfk_1` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arquivos_visiveis_ibfk_2` FOREIGN KEY (`conteudo_id`) REFERENCES `conteudos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aulas`
--
ALTER TABLE `aulas`
  ADD CONSTRAINT `aulas_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `aulas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `aulas_ibfk_3` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aulas_conteudos`
--
ALTER TABLE `aulas_conteudos`
  ADD CONSTRAINT `aulas_conteudos_ibfk_1` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `aulas_conteudos_ibfk_2` FOREIGN KEY (`conteudo_id`) REFERENCES `conteudos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `conteudos`
--
ALTER TABLE `conteudos`
  ADD CONSTRAINT `conteudos_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `presenca_aula`
--
ALTER TABLE `presenca_aula`
  ADD CONSTRAINT `presenca_aula_ibfk_1` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `presenca_aula_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `turmas`
--
ALTER TABLE `turmas`
  ADD CONSTRAINT `turmas_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
--
-- Banco de dados: `test`
--
CREATE DATABASE IF NOT EXISTS `test` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `test`;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
