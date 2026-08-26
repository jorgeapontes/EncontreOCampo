-- Migração para fluxo de frete com transportador

-- 1. Tabela de propostas de frete dos transportadores
CREATE TABLE IF NOT EXISTS propostas_frete_transportador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposta_id INT NOT NULL,
    transportador_id INT NOT NULL,
    valor_frete DECIMAL(10,2) NOT NULL,
    status ENUM('pendente','aceita','recusada','contraproposta') DEFAULT 'pendente',
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_resposta TIMESTAMP NULL DEFAULT NULL,
    observacoes TEXT DEFAULT NULL,
    FOREIGN KEY (proposta_id) REFERENCES propostas(id),
    FOREIGN KEY (transportador_id) REFERENCES transportadores(id)
);

-- 2. Alteração na tabela entregas para foto do comprovante
ALTER TABLE entregas ADD COLUMN foto_comprovante VARCHAR(500) DEFAULT NULL;

-- 3. Ajuste na tabela propostas para opção de frete
ALTER TABLE propostas ADD COLUMN tipo_frete ENUM('vendedor','comprador','plataforma') DEFAULT 'vendedor';

-- 4. Adicionar status detalhado na tabela entregas
ALTER TABLE entregas ADD COLUMN status_detalhado ENUM('aguardando_frete','aguardando_entrega','em_transporte','entregue','finalizada','cancelada') DEFAULT 'aguardando_frete';
