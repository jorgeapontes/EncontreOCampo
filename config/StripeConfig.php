<?php
namespace Config;

require_once dirname(__DIR__) . '/vendor/autoload.php';

class StripeConfig {
    private static $apiKey = 'sk_test_51ShWLz0lZtce65b7sveBBh6ymEbPNn18PnzdF3xQYJiRuL8XpyGu13TpsG0VL16RCyXU6s2mX43LF70yYLTnHA4D00xeYtVfdF';

    public static function init() {
        \Stripe\Stripe::setApiKey(self::$apiKey);
    }

    public static function getApiKey() {
        return self::$apiKey;
    }
}