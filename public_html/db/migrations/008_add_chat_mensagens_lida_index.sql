-- Migration: Índice para leitura/contagem de mensagens não lidas do chat
-- Data: 2026-10-21
-- Descrição: o polling do chat marca mensagens como lidas e as listas de
-- conversas contam não lidas por conversa, ambos filtrando por
-- (conversa_id, lida). O índice idx_conversa sozinho obriga a varrer a
-- conversa inteira a cada requisição.
-- A busca de mensagens novas (conversa_id = ? AND id > ?) já é atendida pelo
-- idx_conversa existente, porque o InnoDB inclui a chave primária (id) em
-- todo índice secundário.
-- IF NOT EXISTS: o índice já existe em alguns bancos (foi criado à mão).

ALTER TABLE `chat_mensagens`
ADD KEY IF NOT EXISTS `idx_conversa_lida` (`conversa_id`, `lida`);
