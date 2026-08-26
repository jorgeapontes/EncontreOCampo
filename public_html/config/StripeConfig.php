<?php
namespace Config;

// Carrega o autoload do Composer na pasta vendor do projeto
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Carregar variáveis de ambiente do .env na raiz
use Dotenv\Dotenv;

class StripeConfig {
    
    /**
     * Inicializa a configuração do Stripe carregando a chave do .env
     * 
     * @return void
     */
    public static function init() {
        // Carregar .env da raiz do projeto se ainda não foi carregado
        if (!isset($_ENV['STRIPE_SECRET_KEY'])) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->load();
        }
        
        // Pegar a chave secreta do .env
        $apiKey = $_ENV['STRIPE_SECRET_KEY'] ?? null;
        
        // Verificar se a chave foi encontrada
        if (!$apiKey) {
            // Log de erro (se já tiver função de log disponível)
            error_log("ERRO CRÍTICO: STRIPE_SECRET_KEY não definida no .env");
            throw new \Exception("Configuração do Stripe: chave secreta não encontrada");
        }
        
        // Configurar a chave da API do Stripe
        \Stripe\Stripe::setApiKey($apiKey);
    }
    
    /**
     * Retorna a chave da API do Stripe (útil para debug)
     * 
     * @return string|null
     */
    public static function getApiKey() {
        if (!isset($_ENV['STRIPE_SECRET_KEY'])) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->load();
        }
        
        return $_ENV['STRIPE_SECRET_KEY'] ?? null;
    }
    
    /**
     * Retorna a chave publicável do Stripe (se precisar)
     * 
     * @return string|null
     */
    public static function getPublishableKey() {
        if (!isset($_ENV['STRIPE_PUBLISHABLE_KEY'])) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->load();
        }
        
        return $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? null;
    }
    
    /**
     * Verifica se está em ambiente de produção
     * 
     * @return bool
     */
    public static function isProduction() {
        if (!isset($_ENV['APP_ENV'])) {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->load();
        }
        
        return ($_ENV['APP_ENV'] ?? 'production') === 'production';
    }
}