
-- Migration: 004_add_cidades_atendidas.sql
-- Adiciona coluna 'cidades_atendidas' na tabela vendedores para armazenar municípios por estado (JSON)
-- Uso: execute este arquivo manualmente (por exemplo: mysql -u user -p database < 004_add_cidades_atendidas.sql)

/*
	Observações:
	- Utiliza `ADD COLUMN IF NOT EXISTS` (MySQL 8+). Se estiver usando versão antiga do MySQL
		remova "IF NOT EXISTS" ou verifique antes com QUERY ao administrador do banco.
	- A coluna armazena JSON serializado (LONGTEXT) com estrutura: {"SP":["São Paulo","Campinas"], ...}
*/

SET @OLD_SQL_MODE = @@sql_mode;
SET sql_mode = '';

START TRANSACTION;

ALTER TABLE `vendedores`
	ADD COLUMN IF NOT EXISTS `cidades_atendidas` LONGTEXT DEFAULT NULL
	COMMENT 'JSON com cidades atendidas por estado, ex: {"SP":["São Paulo","Campinas"]}'
	AFTER `estados_atendidos`;

COMMIT;

SET sql_mode = @OLD_SQL_MODE;

-- (Opcional) Para validar conteúdo JSON em colunas existentes (caso seu MySQL suporte json_valid):
-- UPDATE vendedores SET cidades_atendidas = NULL WHERE cidades_atendidas = '';
-- ALTER TABLE vendedores MODIFY cidades_atendidas LONGTEXT CHECK (json_valid(cidades_atendidas));

-- (Opcional) Se o banco suportar, validar JSON
-- ALTER TABLE vendedores MODIFY cidades_atendidas LONGTEXT CHECK (json_valid(cidades_atendidas));
