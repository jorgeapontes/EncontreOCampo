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

## Estrutura

```
public_html/            raiz publicada (document root)
├── index.php           página inicial e formulário de cadastro
├── src/
│   ├── admin/          painel administrativo
│   ├── comprador/      painel do comprador
│   ├── vendedor/       painel do vendedor (+ webhook do Stripe)
│   ├── transportador/  painel do transportador
│   ├── chat/           chat e negociação comprador ↔ vendedor
│   ├── functions/      funções compartilhadas
│   ├── scripts/        crons (executados apenas via CLI)
│   └── conexao.php     conexão com o banco (fora do git)
├── config/             configuração do Stripe
├── includes/           e-mail (PHPMailer) e recuperação de senha
└── db/migrations/      alterações de schema em SQL
```

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

### Crons (hPanel > Avançado > Cron Jobs)

| Horário | Script | O que faz |
|---|---|---|
| `0 0 * * *` | `src/scripts/cron_verificar_vencimentos.php` | Confere no Stripe as assinaturas vencidas sem renovação |
| `0 0 * * *` | `limpar_logs.php` | Apaga logs antigos (logs de segurança: 190 dias) |
| `0 4 * * *` | `src/scripts/cron_limpar_banco.php` | Retenção de `log_acessos`, `tentativas_ip` e notificações lidas |
| `30 4 * * *` | `src/scripts/cron_limpar_rejeitados.php` | Apaga documentos e dados de cadastros rejeitados há mais de 30 dias |
| `45 4 * * *` | `src/scripts/cron_limpar_documentos_orfaos.php` | Apaga fotos de documento sem usuário (cadastros que falharam) e lista documentos que sumiram do servidor. Aceita `--simular` |

Os horários são em **UTC** (o servidor da Hostinger): `0 0` roda às 21:00 de Brasília.

Comando: `/usr/bin/php /home/u569225384/domains/encontreocampo.com.br/public_html/<script>`
