<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MercadoPago\MercadoPagoConfig as SDK;
use Dotenv\Dotenv;

class MercadoPagoAPI {
    private static $initialized = false;

    public static function init() {
        if (self::$initialized) return;

        // 1. Carregar o .env
        // __DIR__ é a pasta config. O .env está um nível acima (na raiz).
        $dotenvPath = __DIR__ . '/../';
        
        if (file_exists($dotenvPath . '.env')) {
            $dotenv = Dotenv::createImmutable($dotenvPath);
            $dotenv->load();
        }

        // 2. BUSCA ROBUSTA DA CHAVE (AQUI ESTÁ A CORREÇÃO MÁGICA)
        // Tenta pegar do $_ENV, se não der, tenta do $_SERVER, se não der, tenta getenv()
        $accessToken = $_ENV['MP_ACCESS_TOKEN'] 
                    ?? $_SERVER['MP_ACCESS_TOKEN'] 
                    ?? getenv('MP_ACCESS_TOKEN');

        if (!$accessToken) {
            // Se falhar, vamos imprimir o erro na tela para você ver
            die("<h1>ERRO CRÍTICO DE CONFIGURAÇÃO</h1>
                 <p>O PHP não conseguiu ler a variável <strong>MP_ACCESS_TOKEN</strong>.</p>
                 <p>Verifique:</p>
                 <ul>
                    <li>Se o arquivo se chama <strong>.env</strong> e não <strong>.env.txt</strong></li>
                    <li>Se a linha no arquivo é: <code>MP_ACCESS_TOKEN=TEST-seu-token...</code> (sem espaços antes)</li>
                 </ul>");
        }

        SDK::setAccessToken($accessToken);
        self::$initialized = true;
    }

    public static function createPreference($item, $payer, $external_reference, $urls) {
        self::init();
        
        $client = new \MercadoPago\Client\Preference\PreferenceClient();

        $preference = $client->create([
            "items" => [
                [
                    "title" => $item['title'],
                    "quantity" => (int)$item['quantity'],
                    "unit_price" => (float)$item['unit_price'],
                    "currency_id" => "BRL"
                ]
            ],
            "payer" => [
                "name" => $payer['name'],
                "email" => $payer['email']
            ],
            "external_reference" => $external_reference,
            "back_urls" => [
                "success" => $urls['success'],
                "failure" => $urls['failure'],
                "pending" => $urls['pending']
            ],
            // "auto_return" => "approved"
        ]);

        return $preference->id;
    }
}