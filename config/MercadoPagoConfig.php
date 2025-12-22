<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MercadoPago\MercadoPagoConfig as SDK;
use Dotenv\Dotenv;

class MercadoPagoAPI {
    private static $initialized = false;

    public static function init() {
        if (self::$initialized) return;

        // 1. Carregar o .env
        $dotenvPath = __DIR__ . '/../';
        if (file_exists($dotenvPath . '.env')) {
            $dotenv = Dotenv::createImmutable($dotenvPath);
            $dotenv->load();
        }

        // 2. BUSCA ROBUSTA DA CHAVE
        $accessToken = $_ENV['MP_ACCESS_TOKEN'] 
                    ?? $_SERVER['MP_ACCESS_TOKEN'] 
                    ?? getenv('MP_ACCESS_TOKEN');

        if (!$accessToken) {
            die("<h1>ERRO CRÍTICO</h1><p>MP_ACCESS_TOKEN não encontrado no .env</p>");
        }

        SDK::setAccessToken($accessToken);
        self::$initialized = true;
    }

    /**
     * FUNÇÃO PARA ASSINATURA RECORRENTE 
     */
    public static function createSubscription($plan_title, $price, $vendedor_id, $plano_id_db, $payer_email) {
        self::init();
        
        $client = new \MercadoPago\Client\Preapproval\PreapprovalClient();

        try {
            // No Mercado Pago, assinaturas são chamadas de 'Preapproval'
            $subscription = $client->create([
                "reason" => $plan_title,
                "external_reference" => "vendedor_" . $vendedor_id . "_plano_" . $plano_id_db,
                "payer_email" => $payer_email, 
                "auto_recurring" => [
                    "frequency" => 1,
                    "frequency_type" => "months",
                    "transaction_amount" => round((float)$price, 2),
                    "currency_id" => "BRL"
                ],
                // URL para onde o usuário volta após assinar
                "back_url" => "https://umbrageous-noma-autophytically.ngrok-free.dev/src/vendedor/assinatura_confirmada.php",
                "status" => "pending"
            ]);

            return $subscription->init_point; 
        } catch (\Exception $e) {
            if (method_exists($e, 'getApiResponse')) {
                $content = $e->getApiResponse()->getContent();
                throw new Exception("Erro API Mercado Pago: " . json_encode($content));
            }
            throw new Exception("Erro ao criar assinatura: " . $e->getMessage());
        }
    }

}