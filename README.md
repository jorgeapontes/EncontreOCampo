# 🌾 Encontre o Campo

Marketplace que conecta produtores rurais (vendedores) diretamente a compradores e a transportadores, com negociação de preço por propostas e chat.

Em produção em **https://encontreocampo.com.br**.

## Perfis e funcionalidades

### Vendedor (produtor rural)
* Cadastro, edição e controle de estoque dos anúncios (por quilo, unidade, caixa ou saco)
* Planos de assinatura que definem o limite de anúncios (pagamento pelo **Stripe**)
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
* Aprovação dos cadastros (com análise das fotos de documento e do rosto)
* Gestão de usuários, comprovantes de entrega e mediação de chats

## Regras de negócio importantes

* **Preço final:** proposta aceita > desconto vigente > preço normal.
* **Assinaturas:** o Stripe é a fonte da verdade. O webhook (`src/vendedor/webhook_stripe.php`) aplica as mudanças e o cron `cron_verificar_vencimentos.php` consulta o Stripe quando um vencimento passa sem renovação (webhook perdido).
* **Exclusão de conta (LGPD):** os dados pessoais são anonimizados e os documentos apagados, mas chats e propostas continuam para a outra parte. Ver `src/functions/conta_functions.php`.
* **Retenção:** logs de acesso por 6 meses (Marco Civil da Internet); cadastros rejeitados têm documentos e dados apagados 30 dias após a rejeição.

## Tecnologias

* PHP 8 (sem framework) + MySQL/MariaDB
* Composer: `stripe/stripe-php`, `vlucas/phpdotenv`, `phpmailer/phpmailer`
* Hospedagem: Hostinger (Apache, `.htaccess` com URLs sem `.php`)

## Ambiente local

1. Requisitos: PHP 8.x, MySQL/MariaDB, Composer e Apache com `mod_rewrite` (XAMPP serve).
2. Instale as dependências em `public_html/`: `composer install`.
3. Crie `public_html/.env` com as variáveis abaixo (o arquivo não vai para o git).
4. Crie o banco e aplique os arquivos de `db/migrations/` em ordem.

```
DB_HOST=localhost
DB_NAME=encontre_ocampo
DB_USER=root
DB_PASS=
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
APP_ENV=development
```

## Produção

* `src/conexao.php` e `.htaccess` estão no `.gitignore`: alterações neles precisam ser copiadas manualmente para o servidor.
* Deploy arquivo a arquivo. **Nunca** enviar o projeto inteiro por zip, porque isso sobrescreveria `uploads/` (documentos de usuários) e os logs.
