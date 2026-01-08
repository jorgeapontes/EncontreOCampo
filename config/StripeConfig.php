<?php
namespace Config;

// Carrega o autoload do Composer na pasta vendor do projeto
require_once dirname(__DIR__) . '/vendor/autoload.php';

class StripeConfig {
    // Substitua pela sua chave sk_test...
    private static $apiKey = 'sk_test_51ShWLz0lZtce65b7sveBBh6ymEbPNn18PnzdF3xQYJiRuL8XpyGu13TpsG0VL16RCyXU6s2mX43LF70yYLTnHA4D00xeYtVfdF';

    public static function init() {
        \Stripe\Stripe::setApiKey(self::$apiKey);
    }

    public static function getApiKey() {
        return self::$apiKey;
    }
}