-- Migration: 002_add_embalagem_columns.sql
-- Adiciona colunas para armazenar peso por embalagem e estoque em unidades (sacos/caixas/unidades)
ALTER TABLE produtos
ADD COLUMN embalagem_peso_kg DECIMAL(8,3) NULL DEFAULT NULL AFTER modo_precificacao,
ADD COLUMN estoque_unidades INT NULL DEFAULT NULL AFTER embalagem_peso_kg;

CREATE INDEX idx_produtos_estoque_unidades ON produtos (estoque_unidades);
