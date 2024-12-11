<?php 

namespace Project\Installer\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Project\Installer\Helpers\URLHelper;

class Helper {

    public $cache_key = "installer_cache_store";

    const ENV_CONTENT_CACHE_KEY = "installer_env_content";

    public function cache(array $data = []) {
        if(count($data) == 0) {
            return cache()->driver('file')->get($this->cache_key);
        }
        if(cache()->driver('file')->get($this->cache_key)) {
            $cache = cache()->driver('file')->get($this->cache_key);
            $data = array_merge($cache,$data);
            cache()->driver('file')->forever($this->cache_key,$data);
        }else {
            cache()->driver('file')->forever($this->cache_key,$data);
        }
    }

    public function client() {
        $url = new URLHelper();
        return [
            'client'   => $url->base_get(),
        ];
    }

    public function connection(array $data) {
        $url = new URLHelper();

        $response = Http::withHeaders([
            'Content-Type'  => "application/json",
            'Accept'        => "application/json",
        ])->asForm()->post($url->getValidation('v3'),$data);

        if($response->failed()) {
            $message =  $response->json('data')['message'] ?? $response->json('message')['error'][0] ?? "Something went wrong! Please try again";

            if($message == "") $message = $response->status();
            throw new Exception($message);
        }
    }

    public function signature(string|array $data) {
        if(is_string($data)) return base64_encode($data);
        $data = json_encode($data);
        return base64_encode($data);
    }

    public function generateAppKey() {
        return Artisan::call("key:generate");
    }
}