-- Criação das tabelas para chat-negociação de frete

CREATE TABLE IF NOT EXISTS negociacao_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    comprador_id INT NOT NULL,
    transportador_id INT NOT NULL,
    status ENUM('ativo','finalizado','cancelado','recusado','aceito') DEFAULT 'ativo',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (entrega_id) REFERENCES entregas(id),
    FOREIGN KEY (comprador_id) REFERENCES compradores(id),
    FOREIGN KEY (transportador_id) REFERENCES transportadores(id)
);

CREATE TABLE IF NOT EXISTS negociacao_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    remetente_id INT NOT NULL,
    mensagem TEXT,
    tipo ENUM('texto','sistema','proposta','contraproposta') DEFAULT 'texto',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES negociacao_chats(id)
);

CREATE TABLE IF NOT EXISTS negociacao_propostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    frete_detalhes TEXT,
    autor_id INT NOT NULL,
    tipo ENUM('proposta','contraproposta') DEFAULT 'proposta',
    status ENUM('pendente','aceita','recusada','expirada') DEFAULT 'pendente',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES negociacao_chats(id)
);
