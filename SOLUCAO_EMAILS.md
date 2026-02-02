# 📧 GUIA DE SOLUÇÃO - Problema de Envio de Emails com PHPMailer

## 🔍 Problema Identificado

Os emails não estavam sendo entregues apesar de nenhum erro ser exibido. Isso acontece porque:

### Causas Raiz Encontradas:

1. **PORT não era convertida para inteiro** - `$_ENV['SMTP_PORT']` retorna string
2. **Charset UTF-8 não definido** - Pode causar problemas com codificação
3. **Debug desabilitado** - Nenhuma informação de erro era registrada
4. **Tratamento de exceção inadequado** - Não capturava a exceção real

---

## ✅ Soluções Aplicadas

### 1️⃣ Arquivo: `includes/send_notification.php`

**Adições feitas:**

```php
// Conversão de PORT para inteiro
$mail->Port = (int)$_ENV['SMTP_PORT'];

// Configurações de Debug e Encoding
$mail->SMTPDebug = SMTP::DEBUG_OFF;
$mail->Debugoutput = 'error_log';
$mail->CharSet = PHPMailer::CHARSET_UTF8;

// Timeout aumentado
$mail->Timeout = 10;
$mail->SMTPKeepAlive = true;

// Logs melhorados
if ($resultado) {
    error_log("Email enviado com sucesso para: $destinatario");
}
```

### 2️⃣ Arquivo: `includes/email_config.php`

**Mesmas correções aplicadas** à função `enviarEmailRecuperacao()`

---

## 🧪 Como Testar

### Opção 1: Usar o Script de Diagnóstico (RECOMENDADO)

1. Acesse no navegador:
   ```
   http://localhost/EncontreOCampo/includes/teste_diagnostico_email.php
   ```

2. O script irá:
   - ✓ Verificar todas as configurações SMTP
   - ✓ Validar arquivos necessários
   - ✓ Permitir enviar um email de teste
   - ✓ Exibir os últimos logs de erro

### Opção 2: Script Rápido de Teste

Crie um arquivo `teste_rapido.php` na raiz do projeto:

```php
<?php
require_once 'includes/send_notification.php';

$resultado = enviarEmailNotificacao(
    'seu-email@gmail.com',
    'Seu Nome',
    'Teste de Email',
    'Se você receber este email, o problema foi resolvido!'
);

echo $resultado ? "✅ Enviado!" : "❌ Falhou!";
echo "\n\nVerifique os logs em: C:\\xampp\\apache\\logs\\error.log";
?>
```

---

## 📋 Verificação de Logs

Se o email ainda não funcionar:

1. **Localize o arquivo de log:**
   ```
   C:\xampp\apache\logs\error.log
   ```

2. **Procure por erros relacionados a SMTP:**
   - Busque por: `SMTP`, `email`, `PHPMailer`

3. **Tipos de erros comuns:**
   - `Connection refused` → Verificar SMTP_HOST e SMTP_PORT
   - `Authentication failed` → Verificar SMTP_USERNAME e SMTP_PASSWORD
   - `Timeout` → Verificar conexão com o servidor SMTP
   - `SSL/TLS` → Verificar SMTP_ENCRYPTION

---

## 🔐 Verificação das Credenciais SMTP

Seu `.env` contém:

```
SMTP_HOST=smtp.hostinger.com
SMTP_USERNAME=contato@encontreocampo.com.br
SMTP_PASSWORD=***REMOVED_PASSWORD***
SMTP_PORT=587
SMTP_ENCRYPTION=tls
```

**⚠️ IMPORTANTE:** Se mudou a senha do email, é necessário atualizá-la no `.env`!

---

## 🛠️ Possíveis Soluções Adicionais

### Se ainda não funcionar:

#### A. Testar Conexão SMTP Direta

Execute este teste via PHP:

```php
<?php
require_once 'vendor/autoload.php';
require_once 'includes/PHPMailer-master/src/PHPMailer.php';
require_once 'includes/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\SMTP;

$smtp = new SMTP;
$smtp->Debugoutput = 'error_log';

try {
    if ($smtp->connect('smtp.hostinger.com', 587)) {
        echo "✓ Conectado ao servidor SMTP\n";
        if ($smtp->authenticate('contato@encontreocampo.com.br', '***REMOVED_PASSWORD***')) {
            echo "✓ Autenticação bem-sucedida\n";
        } else {
            echo "✗ Falha na autenticação\n";
        }
    } else {
        echo "✗ Não foi possível conectar\n";
    }
} catch (Exception $e) {
    echo "✗ Erro: " . $e->getMessage();
}
?>
```

#### B. Verificar Whitelist/Firewall

- O servidor pode estar bloqueando a porta 587
- Contate o suporte do host para liberar a porta SMTP

#### C. Limpar e Reconfigurar

Se tudo mais falhar:

1. Delete o arquivo `.env` e recrie com as credenciais corretas
2. Certifique-se que a senha NÃO tem caracteres especiais que precisem escape
3. Se houver, envolva em aspas: `SMTP_PASSWORD="senha com \"aspas\""`

---

## 📧 Arquivos Modificados

- `includes/send_notification.php` ✓
- `includes/email_config.php` ✓
- `includes/teste_diagnostico_email.php` (NOVO - Ferramenta de diagnóstico)

---

## 🎯 Próximos Passos

1. **Teste com o script de diagnóstico**
2. **Se erro de conexão:** Verifique firewall/host
3. **Se erro de autenticação:** Valide credenciais no `.env`
4. **Se silencioso:** Aguarde mais 5 minutos (servidor SMTP às vezes demora)
5. **Verifique SPAM:** Gmail, Hotmail, etc. podem marcar como spam

---

## 📞 Informações Úteis

- **SMTP Hostinger:** https://support.hostinger.com/en/articles/4727947-how-to-check-email-settings
- **PHPMailer Docs:** https://github.com/PHPMailer/PHPMailer
- **Troubleshooting:** https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting

---

**Data da Correção:** 02/02/2026  
**Versão:** 1.0
