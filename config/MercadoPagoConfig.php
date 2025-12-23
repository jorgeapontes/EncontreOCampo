<?php
// EncontreOCampo/config/MercadoPagoConfig.php
require_once __DIR__ . '/../../vendor/autoload.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Subscription\SubscriptionClient;
use Dotenv\Dotenv;

class MercadoPagoConfig {
    private static $accessToken;
    private static $publicKey;
    private static $webhookSecret;
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        // Tentar carregar .env
        try {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
        } catch (Exception $e) {
            // Se não encontrar .env, usar valores padrão
            error_log("Aviso: Arquivo .env não encontrado. Usando valores padrão.");
        }
        
        self::$accessToken = $_ENV['MP_ACCESS_TOKEN'] ?? 'TEST-4356310954100720-121910-019bdfbe9df084bce8aa02f87ea2045a-3075113068';
        self::$publicKey = $_ENV['MP_PUBLIC_KEY'] ?? 'TEST-954e106c-102f-4084-b840-7dac2f053bb9';
        self::$webhookSecret = $_ENV['MP_WEBHOOK_SECRET'] ?? 'SEU_WEBHOOK_SECRET_AQUI';
        
        // Configurar access token global do Mercado Pago
        \MercadoPago\Config::setAccessToken(self::$accessToken);
        
        self::$initialized = true;
    }
    
    public static function getAccessToken() {
        self::init();
        return self::$accessToken;
    }
    
    public static function getPublicKey() {
        self::init();
        return self::$publicKey;
    }
    
    public static function getWebhookSecret() {
        self::init();
        return self::$webhookSecret;
    }
    
    public static function getPreferenceClient() {
        self::init();
        return new PreferenceClient();
    }
    
    public static function getPaymentClient() {
        self::init();
        return new PaymentClient();
    }
    
    public static function getSubscriptionClient() {
        self::init();
        return new SubscriptionClient();
    }
}

// Inicializar configuração
MercadoPagoConfig::init();
?>