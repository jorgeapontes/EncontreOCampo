# 🌾 Encontre o Campo

Marketplace que conecta produtores rurais (vendedores) diretamente a compradores e a transportadores, com negociação de preço por propostas e chat.

Em produção em **https://encontreocampo.com.br**.

## Perfis e funcionalidades

### Vendedor (produtor rural)
* Cadastro, edição e controle de estoque dos anúncios (por quilo, unidade, caixa ou saco)
* Planos de assinatura que definem o limite de anúncios, com pagamento pelo **Stripe**
* Recebe propostas, aceita, recusa ou envia contrapropostas pelo chat
* Define os estados e cidades que atende

### Comprador
* Busca com filtros por categoria, preço e localização
* Negocia preço, quantidade, pagamento e entrega com o vendedor antes de fechar
* Painel com propostas, negociações e favoritos

### Transportador
* Vê fretes disponíveis e envia propostas de frete
* Acompanha entregas e confirma a entrega com assinatura e foto

### Administração
* Aprovação dos cadastros, com análise das fotos de documento e do rosto
* Gestão de usuários, comprovantes de entrega e mediação de chats

## Regras de negócio

* **Preço final:** proposta aceita > desconto vigente > preço normal.
* **Assinaturas:** o Stripe é a fonte da verdade sobre o status de cada assinatura; a plataforma acompanha as mudanças automaticamente.
* **Exclusão de conta (LGPD):** os dados pessoais são anonimizados e os documentos apagados, mas chats e propostas continuam disponíveis para a outra parte da negociação.
* **Retenção de dados:** registros de acesso por 6 meses (Marco Civil da Internet); cadastros rejeitados têm documentos e dados apagados 30 dias após a rejeição.

## Tecnologias

* PHP 8 e MySQL/MariaDB
* Stripe (assinaturas) e PHPMailer (e-mails)
