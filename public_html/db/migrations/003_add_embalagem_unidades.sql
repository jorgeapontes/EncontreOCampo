-- Migration: 003_add_embalagem_unidades.sql
-- Adiciona coluna 'embalagem_unidades' para armazenar número de unidades por embalagem
ALTER TABLE produtos
ADD COLUMN embalagem_unidades INT NULL DEFAULT NULL AFTER embalagem_peso_kg;

CREATE INDEX idx_produtos_embalagem_unidades ON produtos (embalagem_unidades);
