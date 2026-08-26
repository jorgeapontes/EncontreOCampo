-- Migration: 001_add_modo_precificacao.sql
-- Adiciona a coluna 'modo_precificacao' na tabela 'produtos'
ALTER TABLE produtos
ADD COLUMN modo_precificacao VARCHAR(50) NOT NULL DEFAULT 'por_quilo' AFTER imagem_url;

-- INDEX opcional para acelerar consultas por modo_precificacao
CREATE INDEX idx_produtos_modo_precificacao ON produtos (modo_precificacao);
