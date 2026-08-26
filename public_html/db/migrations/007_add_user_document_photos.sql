-- Migration: Adicionar campos de fotos de documentação para usuários
-- Data: 2026-01-31
-- Descrição: Adiciona colunas para armazenar fotos de rosto e documentos (frente e verso) dos usuários

ALTER TABLE `usuarios` 
ADD COLUMN `foto_rosto` varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do rosto do usuário' AFTER `reset_token_expira`,
ADD COLUMN `foto_documento_frente` varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do documento (frente)' AFTER `foto_rosto`,
ADD COLUMN `foto_documento_verso` varchar(500) DEFAULT NULL COMMENT 'Caminho da foto do documento (verso)' AFTER `foto_documento_frente`;

-- Criar diretório de documentos se necessário
-- Execute manualmente: mkdir -p uploads/documentos/
