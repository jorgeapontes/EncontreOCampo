<?php
namespace Config;

// Carrega o autoload do Composer na pasta vendor do projeto
require_once dirname(__DIR__) . '/vendor/autoload.php';

class StripeConfig {
    // Substitua pela sua chave sk_test...
    private static $apiKey = '#';

    public static function init() {
        \Stripe\Stripe::setApiKey(self::$apiKey);
    }

    public static function getApiKey() {
        return self::$apiKey;
    }
}