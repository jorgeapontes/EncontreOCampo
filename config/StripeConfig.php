<?php
namespace Config;

// Carrega o autoload do Composer na pasta vendor do projeto
require_once dirname(__DIR__) . '/vendor/autoload.php';

class StripeConfig {
    // Substitua pela sua chave sk_test...
    private static $apiKey = 'pk_live_51ShWLz0lZtce65b7V1uIErXQaFKRyYwxaZli3l388yTJbPP2eUZJH3QxuAihWDiDuxMJFxjxqqecWNKRDNEiGz47004zhy0NHv';

    public static function init() {
        \Stripe\Stripe::setApiKey(self::$apiKey);
    }

    public static function getApiKey() {
        return self::$apiKey;
    }
}