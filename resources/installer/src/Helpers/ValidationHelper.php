<?php 

namespace Project\Installer\Helpers;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Project\Installer\Helpers\Helper;
use Project\Installer\Helpers\URLHelper;

class ValidationHelper {

    public function validate(array $data) {
        
        $helper = new Helper();

        $data['client']     = $helper->client();
        $data['product_id'] = env('AD_PRODUCT_ID');

        $helper->connection($data);

        $helper->cache($data);

        $this->setStepSession();
    }

    public function setStepSession() {
        session()->put('validation',"PASSED");
    }

    public static function step() {
        return session('validation');
    }

    public function isLocalInstallation() {
    	return true;
        $url = request()->url();
        $url_path = parse_url($url);
        $host = $url_path['host'];
        if($host == "localhost" || $host == "127.0.0.1") return true;
        return false;
    }
}