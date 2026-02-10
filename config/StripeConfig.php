<?php
namespace Config;

require_once dirname(__DIR__) . '/vendor/autoload.php';

class StripeConfig {
    private static $apiKey = '***REMOVED_STRIPE_TEST_KEY***';

    public static function init() {
        \Stripe\Stripe::setApiKey(self::$apiKey);
    }

    public static function getApiKey() {
        return self::$apiKey;
    }
}