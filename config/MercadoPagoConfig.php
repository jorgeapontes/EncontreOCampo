<?php
// src/config/MercadoPagoConfig.php
require_once __DIR__ . '/../vendor/autoload.php'; // Se usar Composer

use Dotenv\Dotenv;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoAPI {
    private static $accessToken;
    private static $publicKey;
    private static $initialized = false;

    private static function init() {
        if (!self::$initialized) {
            // Carrega variáveis do .env
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
            
            self::$accessToken = $_ENV['MERCADOPAGO_ACCESS_TOKEN'];
            self::$publicKey = $_ENV['MERCADOPAGO_PUBLIC_KEY'];
            
            // Configura o SDK do Mercado Pago
            MercadoPagoConfig::setAccessToken(self::$accessToken);
            
            self::$initialized = true;
        }
    }

    public static function getAccessToken() {
        self::init();
        return self::$accessToken;
    }

    public static function getPublicKey() {
        self::init();
        return self::$publicKey;
    }

    public static function createPreference($items, $payer, $externalReference, $backUrls) {
        self::init();
        
        $client = new PreferenceClient();
        
        $preferenceData = [
            "items" => $items,
            "payer" => $payer,
            "external_reference" => $externalReference,
            "back_urls" => $backUrls,
            "auto_return" => "approved",
            "notification_url" => $_ENV['WEBHOOK_URL'],
            "statement_descriptor" => "ENCONTREOCAMPO",
            "binary_mode" => true
        ];

        try {
            $preference = $client->create($preferenceData);
            return $preference;
        } catch (Exception $e) {
            error_log("Erro ao criar preferência: " . $e->getMessage());
            return false;
        }
    }

    public static function getPayment($paymentId) {
        self::init();
        
        $client = new PaymentClient();
        
        try {
            $payment = $client->get($paymentId);
            return $payment;
        } catch (Exception $e) {
            error_log("Erro ao buscar pagamento: " . $e->getMessage());
            return false;
        }
    }

    public static function createSubscription($planData, $payer, $externalReference) {
        self::init();
        
        // Para assinaturas recorrentes, você precisará criar um plano primeiro
        // Esta é uma implementação simplificada
        // Na prática, você criaria o plano via API do Mercado Pago
        
        $client = new PreferenceClient();
        
        $preferenceData = [
            "items" => [
                [
                    "title" => $planData['title'],
                    "quantity" => 1,
                    "currency_id" => "BRL",
                    "unit_price" => (float)$planData['unit_price']
                ]
            ],
            "payer" => $payer,
            "external_reference" => $externalReference,
            "back_urls" => [
                "success" => $_ENV['SUCCESS_URL'],
                "failure" => $_ENV['FAILURE_URL'],
                "pending" => $_ENV['PENDING_URL']
            ],
            "auto_return" => "approved",
            "notification_url" => $_ENV['WEBHOOK_URL']
        ];

        try {
            $preference = $client->create($preferenceData);
            return $preference;
        } catch (Exception $e) {
            error_log("Erro ao criar assinatura: " . $e->getMessage());
            return false;
        }
    }
}