# 🌾 Encontre o Campo: Marketplace de Produtos Agrícolas

[![Status do Projeto](https://img.shields.io/badge/Status-Em%20Desenvolvimento-blue)](https://github.com/seu-usuario/seu-repositorio)
[![Licença](https://img.shields.io/badge/Licença-MIT-green)](LICENSE)

> Uma plataforma de comércio on-line que conecta produtores rurais (Vendedores) diretamente a compradores e empresas (Compradores), facilitando a negociação e a aquisição de produtos agrícolas com foco em transparência e eficiência.

## ✨ Funcionalidades Principais

O "Encontre o Campo" oferece um ecossistema completo de vendas e negociação:

### 🧑‍🌾 Vendedor (Produtor Rural)
* **Gestão de Anúncios:** Cadastro, edição e acompanhamento de produtos e seus estoques.
* **Planos de Assinatura:** Sistema de planos (Básico, Premium) para determinar o limite de anúncios.
* **Negociação e Propostas:** Visualização, aceitação, rejeição ou envio de contrapropostas de compra.


### 🛒 Comprador (Empresas/Consumidores)
* **Busca Avançada:** Filtros por categoria, preço e localização para encontrar produtos específicos.
* **Criação de Propostas:** Capacidade de negociar preço, quantidade e condições de pagamento/entrega com o vendedor antes de finalizar a compra.
* **Painel de Propostas:** Acompanhamento do status de todas as propostas enviadas e em negociação.

### 🛡️ Arquitetura e Pagamento
* **Precedência de Preços:** Lógica de aplicação de preço final (Proposta > Desconto > Preço Normal).
* **Congelamento de Pedidos:** Registro da transação final (`pedidos` table) no momento da compra, garantindo a integridade do valor mesmo se o preço do produto for alterado.
* **Mercado Pago Integration:** Utilização de API do Mercado Pago, para compras/vendas e assinaturas.

---

## 🚀 Pré-requisitos

* [PHP] (versão 7.4 ou superior)
* [MySQL/MariaDB]
* [Composer] (Para gerenciar dependências PHP)
* Servidor Web (Apache ou Nginx, ou use o servidor embutido do PHP)

