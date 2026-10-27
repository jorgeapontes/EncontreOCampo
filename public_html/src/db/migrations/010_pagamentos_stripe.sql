-- Migration: Histórico dos eventos do Stripe (seção Pagamentos do master)
-- Data: 2026-10-27
-- Descrição:
--   - pagamentos_stripe: uma linha por evento recebido no webhook do Stripe
--     (pagamento recebido/falhou, nova assinatura, cancelamento...), com valor e
--     vendedor quando houver. Nunca guarda dados de cartão.
--   - evento_id é único: quando o Stripe reenvia o mesmo evento, a linha é
--     atualizada em vez de duplicada (o valor recebido não conta duas vezes).
--   - status: ok (processado), falha (pagamento recusado pelo Stripe),
--     erro (o site não conseguiu processar), recusado (chamada sem assinatura
--     válida do Stripe — não veio do Stripe).
--   - Retenção (cron_limpar_banco): 5 anos (registro financeiro); "recusado", 90 dias.
-- Pode rodar mais de uma vez: usa IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `pagamentos_stripe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data_hora` datetime NOT NULL,
  `evento_id` varchar(255) DEFAULT NULL,
  `tipo` varchar(60) NOT NULL,
  `status` varchar(10) NOT NULL,
  `vendedor_id` int(11) DEFAULT NULL,
  `cliente_stripe` varchar(255) DEFAULT NULL,
  `assinatura_stripe` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `moeda` char(3) DEFAULT NULL,
  `detalhe` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_evento` (`evento_id`),
  KEY `idx_data` (`data_hora`),
  KEY `idx_status` (`status`, `data_hora`),
  KEY `idx_vendedor` (`vendedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SELECT 'pagamentos_stripe criada' AS resultado, COUNT(*) AS linhas FROM `pagamentos_stripe`;
