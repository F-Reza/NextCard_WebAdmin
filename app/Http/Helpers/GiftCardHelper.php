<?php

namespace App\Http\Helpers;

use App\Constants\GlobalConst;
use App\Models\Admin\GiftCardApi;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GiftCardHelper {

    /**
     * Active API
     */
    public $api;

    /**
     * Store access token
     */
    protected $access_token;

    /**
     * store configuration
     */
    protected array $config;

    /**
     * Country Cache KEY
     */
    const COUNTRIES_CACHE_KEY = "gift_card_api_countries_{provider}_{env}";

    /**
     * All Products Cache Key
     */
    const ALL_PRODUCTS_CACHE_KEY = "gift_card_api_all_products";

    /**
     * API Status
     */
    const STATUS_SUCCESS    = "SUCCESSFUL";
    const STATUS_PENDING    = "PENDING";
    const STATUS_PROCESSING = "PROCESSING";
    const STATUS_REFUNDED   = "REFUNDED";
    const STATUS_FAILED     = "FAILED";

    /**
     * Price Types
     */
    const PRICE_TYPES = [
        "FIXED" => "FIXED",
        "RANGE" => "RANGE",
    ];

    public function __construct()
    {
        $this->api = GiftCardApi::reloadly()->first();
        $this->setConfig();
        $this->accessToken();
    }

    /**
     * Set configuration
     */
    public function setConfig()
    {
        $api = $this->api;

        if(!$api) throw new Exception("Gift Card Provider Not Found!");

        $config['client_id']    = $api->credentials?->client_id;
        $config['secret_key']   = $api->credentials?->secret_key;
        $config['env']          = $api->env;

        if($config['env'] == GlobalConst::ENV_PRODUCTION) {
            $config['request_url']  = $api->credentials?->production_base_url;
        }else {
            $config['request_url']  = $api->credentials?->sandbox_base_url;
        }

        $this->config = $config;

        return $this;
    }

    /**
     * Authenticate API access token retrieve
     */
    public function accessToken()
    {
        if(!$this->config) $this->setConfig();

        $api = $this->api;

        $request_endpoint = "https://auth.reloadly.com/oauth/token";

        $client_id = $this->config['client_id'];
        $secret_key = $this->config['secret_key'];
        $request_url    = $this->config['request_url'];

        $grant_type = "client_credentials";

        $response = Http::post($request_endpoint,[
            "client_id" => $client_id,
            "client_secret" => $secret_key,
            "grant_type" => $grant_type,
            "audience" => $request_url,
        ])->throw(function(Response $response, RequestException $exception) {
            $response = $response->json();

            $message = $response['message'];
            $message_type   = $response['errorCode'];

            $error_message = $message . " [$message_type]";

            throw new Exception($error_message);

        })->json();


        $access_token = $response['access_token'];
        $expire_in      = $response['expires_in'];

        $this->access_token = $access_token;

        return $this;
    }

    /**
     * Get Countries
     */
    public function getCountries():array
    {
        $country_cache_key = $this->resolveCacheKey(self::COUNTRIES_CACHE_KEY);
        if(cache()->driver('file')->get($country_cache_key)) return cache()->driver('file')->get($country_cache_key);

        if(!$this->access_token) $this->accessToken();

        $access_token = $this->access_token;

        $base_url = $this->config['request_url'];

        $request_endpoint = $base_url . "/countries";

        $response = Http::withHeaders([
            'Authorization' => "Bearer " . $access_token,
            'accept'        => "application/com.reloadly.giftcards-v1+json",
        ])->get($request_endpoint)->throw(function(Response $response, RequestException $exception) {
            throw new Exception($exception->getMessage());
        })->json();

        if(!is_array($response)) throw new Exception("Something went wrong! Please try again");

        cache()->driver('file')->put($country_cache_key, $response, 43200);

        return $response;
    }

    /**
     * Resolve cache key
     */
    public function resolveCacheKey(string $key):string
    {
        $api = $this->api;

        $provider = $api->provider;
        $env = $api->env;

        $cache_key = str_replace(['{provider}','{env}'],[$provider, $env], $key);

        return $cache_key;
    }

    /**
     * get products
     */
    public function getProducts(array $params = [], $cache = false):array
    {
        if($cache) {
            if(cache()->driver('file')->get(self::ALL_PRODUCTS_CACHE_KEY)) return cache()->driver('file')->get(self::ALL_PRODUCTS_CACHE_KEY);
        }

        $base_url = $this->config['request_url'];
        $request_endpoint = $base_url . "/products";

        if(!$this->access_token) $this->accessToken();

        $response = Http::withHeaders([
            'Authorization'     => 'Bearer ' . $this->access_token,
            'accept'            => 'application/com.reloadly.giftcards-v1+json',
        ])->get($request_endpoint,$params)->throw(function(Response $response, RequestException $exception) {
            throw new Exception($exception->getMessage());
        })->json();

        if(!is_array($response)) throw new Exception("Something went wrong! Please try again");

        if($cache) {
            cache()->driver('file')->put(self::ALL_PRODUCTS_CACHE_KEY, $response, 3600);
        }

        return $response;
    }

    /**
     * get specific product information
     */
    public function getProductInfo(int $productId):array
    {
        if(!$this->access_token) $this->accessToken();

        $base_url = $this->config['request_url'];
        $endpoint = $base_url . "/products/" . $productId;

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $this->access_token,
            "Accept"        => "application/com.reloadly.giftcards-v1+json",
        ])->get($endpoint)->throw(function(Response $response, RequestException $exception) {

            $response_array = $response->json();
            $error_message = $response_array['message'] ?? "";

            throw new Exception($error_message);

        })->json();

        if(!is_array($response)) throw new Exception("Something went wrong! Please try again");

        return $response;
    }
    public function getProductInfoByIso(string $iso):array
    {
        if(!$this->access_token) $this->accessToken();

        $base_url = $this->config['request_url'];
        $endpoint = $base_url . "/countries/" . $iso."/products";

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $this->access_token,
            "Accept"        => "application/com.reloadly.giftcards-v1+json",
        ])->get($endpoint)->throw(function(Response $response, RequestException $exception) {

            $response_array = $response->json();
            $error_message = $response_array['message'] ?? "";

            throw new Exception($error_message);

        })->json();

        if(!is_array($response)) throw new Exception("Something went wrong! Please try again");

        return $response;
    }

    /**
     * Create gift card order
     */
    public function createOrder(array $data)
    {
        if(!$this->access_token) $this->accessToken();

        $base_url = $this->config['request_url'];
        $endpoint = $base_url . "/orders";

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $this->access_token,
            "Accept"        => "application/com.reloadly.giftcards-v1+json",
        ])->post($endpoint, $data)->throw(function(Response $response, RequestException $exception) {

            $response_array = $response->json();
            $message = $response_array['message'] ?? "";

            throw new Exception($message);
        })->json();

        if(!is_array($response)) throw new Exception("Something went wrong! Please try again");

        return $response;
    }

    /**
     * Redeem Gift Card Codes
     */
    public function redeemCodes(string $trx_id):array
    {
        if(!$this->access_token) $this->accessToken();

        $base_url = $this->config['request_url'];
        $endpoint = $base_url . "/orders/transactions/$trx_id/cards";

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $this->access_token,
            "Accept"        => "application/com.reloadly.giftcards-v1+json",
        ])->get($endpoint)->throw(function(Response $response, RequestException $exception) {

            $response_array = $response->json();
            $message = $response_array['message'] ?? "";

            throw new Exception($message);
        })->json();

        if(!is_array($response)) throw new Exception("Something went wrong! Please try again");

        return $response;
    }

    /**
     * Receive API Webhook Response
     */
    public function webhookResponse(array $response_data)
    {
        $custom_identifier = $response_data['data']['customIdentifier'];

        // logger("Gift Card Webhook Response Received!", ['uuid' => $custom_identifier, 'response' => $response_data]);

        return $response_data;
    }
}
