<?php

namespace App\Traits\PaymentGateway;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\Api\Helpers;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\BasicSettings;
use App\Models\TemporaryData;
use App\Models\UserNotification;
use App\Notifications\User\AddMoney\ApprovedMail;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Http;
use App\Traits\AdminNotifications\AddMoneyNotifications;


trait CoinGate
{
    use AddMoneyNotifications;
    private $coinGate_gateway_credentials;
    private $coinGate_access_token;

    private $coinGate_status_paid = "paid";

    public function coingateInit($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getCoinGateCredentials($output);
        $request_credentials = $this->getCoinGateRequestCredentials();
        return $this->coinGateCreateOrder($request_credentials);

    }
    public function getCoinGateCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception(__("Payment gateway not available"));

        $production_url_sample = ['live','live url','live env','live environment', 'coin gate live url','coin gate live','production url', 'production link'];
        $production_app_token_sample = ['production token','production app token','production auth token','live token','live app token','live auth token'];
        $sandbox_url_sample = ['sandbox','sandbox url','sandbox env', 'test url', 'test', 'sandbox environment', 'coin gate sandbox url', 'coin gate sandbox' , 'coin gate test'];
        $sandbox_app_token_sample = ['sandbox token','sandbox app token','test app token','test token','test auth token','sandbox auth token'];

        $production_url = $this->getValueFromGatewayCredentials($gateway,$production_url_sample);
        $production_app_token = $this->getValueFromGatewayCredentials($gateway,$production_app_token_sample);
        $sandbox_url = $this->getValueFromGatewayCredentials($gateway,$sandbox_url_sample);
        $sandbox_app_token = $this->getValueFromGatewayCredentials($gateway,$sandbox_app_token_sample);

        $mode = $gateway->env;

        $gateway_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "production",
        ];

        if(array_key_exists($mode,$gateway_register_mode)) {
            $mode = $gateway_register_mode[$mode];
        }else {
            $mode = "sandbox";
        }

        $credentials = (object) [
            'production_url'    => $production_url,
            'production_token'  => $production_app_token,
            'sandbox_url'       => $sandbox_url,
            'sandbox_token'     => $sandbox_app_token,
            'mode'              => $mode,
        ];

        $this->coinGate_gateway_credentials = $credentials;

        return $credentials;
    }
    public function getCoinGateRequestCredentials($output = null) {
        $credentials = $this->coinGate_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        if($output['gateway']->env == PaymentGatewayConst::ENV_PRODUCTION) {
            $request_credentials['url']     = $credentials->production_url;
            $request_credentials['token']   = $credentials->production_token;
        }else {
            $request_credentials['url']     = $credentials->sandbox_url;
            $request_credentials['token']   = $credentials->sandbox_token;
        }
        return (object) $request_credentials;
    }
    public function registerCoinGateEndpoints() {
        return [
            'createOrder'       => 'orders',
        ];
    }

    public function getCoinGateEndpoint($name) {
        $endpoints = $this->registerCoinGateEndpoints();
        if(array_key_exists($name,$endpoints)) {
            return $endpoints[$name];
        }
        throw new Exception("Oops! Request endpoint not registered!");
    }

    public function coinGateCreateOrder($credentials) {
        $request_base_url       = $credentials->url;
        $request_access_token   = $credentials->token;

        $temp_record_token = generate_unique_string('temporary_datas','identifier',60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel

        $redirection  = [
            "return_url" => "user.add.money.coingate.payment.success",
            "cancel_url" => "user.add.money.coingate.payment.cancel",
            "callback_url" => "add.money.payment.callback"
        ];
        $url_parameter = $this->getUrlParams();
        $endpoint = $request_base_url . "/" . $this->getCoinGateEndpoint('createOrder');

        $response = Http::withToken($request_access_token)->post($endpoint,[
            'order_id'          => Str::uuid(),
            'price_amount'      => $this->output['amount']->total_amount,
            'price_currency'    => $this->output['amount']->sender_cur_code,
            'receive_currency'  => $this->output['amount']->default_currency,
            'callback_url'      => $this->setGatewayRoute($redirection['callback_url'],PaymentGatewayConst::COINGATE,$url_parameter),
            'cancel_url'        => $this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::COINGATE,$url_parameter),
            'success_url'       => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::COINGATE,$url_parameter),
        ]);

        if($response->failed()) {
            $message = json_decode($response->body(),true);
            throw new Exception($message['message']);
        }
        if($response->successful()) {
            $response_array = json_decode($response->body(),true);

            if(isset($response_array['payment_url'])) {
                // create junk transaction
                $this->coinGateJunkInsert($this->output,$response_array,$temp_record_token);

                if(request()->expectsJson()) {
                    $this->output['redirection_response']   = $response_array;
                    $this->output['redirect_links']         = [];
                    $this->output['redirect_url']           = $response_array['payment_url'];
                    return $this->get();
                }
                return redirect()->away($response_array['payment_url']);
            }
        }

        throw new Exception(__('Something went wrong! Please try again'));

    }
    public function coinGateJunkInsert($output,$response, $temp_identifier) {
        $data = [
            'gateway'                   => $output['gateway']->id,
            'currency'                  => $output['currency']->id,
            'amount'                    => json_decode(json_encode($output['amount']),true),
            'response'                  => $response,
            'temp_identifier'           => $temp_identifier,
            'wallet_table'              => $output['wallet']->getTable(),
            'wallet_id'                 => $output['wallet']->id,
            'creator_table'             => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'                => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard'             => get_auth_guard(),
        ];


        return TemporaryData::create([
            'type'          => PaymentGatewayConst::COINGATE,
            'identifier'    => $temp_identifier,
            'data'          => $data,
        ]);
    }
    public function coingateSuccess($output = null) {
        $reference = $output['tempData']['identifier'];

        $output['capture']      = $output['tempData']['data']->response ?? "";
        $output['callback_ref'] = $reference;

        // Search data on transaction table
        if(!$this->searchWithReferenceInTransaction($reference)) {

            // need to insert new transaction in database
            try{
                $this->createTransactioncoinGate($output, PaymentGatewayConst::STATUSPENDING);
            }catch(Exception $e) {
                throw new Exception(__('Something went wrong! Please try again'));
            }
        }
        if(!$this->predefined_user) {
            $this->removeTempDataCoinGate($output);
        }
    }
    public function createTransactioncoinGate($output,$status = PaymentGatewayConst::STATUSSUCCESS) {

        $basic_setting = BasicSettings::first();
        if($this->predefined_user) {
            $user = $this->predefined_user;
        }else {
            $user = auth()->guard(get_auth_guard())->user();
        }
        $trx_id = 'AM'.getTrxNum();
        $inserted_id = $this->insertRecordCoinGate($output,$trx_id,$status);
        $this->insertChargescoinGate($output,$inserted_id);
        $this->adminNotification($trx_id,$output,$status);
        $this->insertDeviceCoinGate($output,$inserted_id);

        if($this->requestIsApiUser()) {
            // logout user
            $api_user_login_guard = $this->output['api_login_guard'] ?? null;
            if($api_user_login_guard != null) {
                auth()->guard($api_user_login_guard)->logout();
            }
        }
        if( $basic_setting->email_notification == true){
            try{
             $user->notify(new ApprovedMail($user,$output,$trx_id));
            }catch(Exception $e) {}
         }
    }
    public function insertRecordCoinGate($output,$trx_id,$status = PaymentGatewayConst::STATUSSUCCESS) {
        DB::beginTransaction();
        try{

            if($this->predefined_user) {
                $user = $this->predefined_user;
            }else {
                $user = auth()->guard(get_auth_guard())->user();
            }
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $output['wallet']->id,
                'payment_gateway_currency_id'   => $output['currency']->id,
                'type'                          => PaymentGatewayConst::TYPEADDMONEY,
                'trx_id'                        => $trx_id,
                'request_amount'                => $output['amount']->requested_amount,
                'payable'                       => $output['amount']->total_amount,
                'available_balance'             => $output['wallet']->balance + $output['amount']->requested_amount,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::TYPEADDMONEY," ")) . " With " . $output['gateway']->name,
                'details'                       => "Coin Gate Payment Successful",
                'callback_ref'                  => $output['callback_ref'] ?? null,
                'status'                        => $status,
                'attribute'                      =>PaymentGatewayConst::SEND,
                'created_at'                    => now(),
            ]);
            if($status === PaymentGatewayConst::STATUSSUCCESS) {
                $this->updateWalletBalanceCoinGate($output);
            }
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__('Something went wrong! Please try again'));
        }
        return $id;
    }
    public function updateWalletBalanceCoinGate($output) {
        $update_amount = $output['wallet']->balance + $output['amount']->requested_amount;
        $output['wallet']->update([
            'balance'   => $update_amount,
        ]);
    }
    public function insertChargesCoinGate($output,$id) {
        if($this->predefined_user) {
            $user = $this->predefined_user;
        }else {
            $user = auth()->guard(get_auth_guard())->user();
        }
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $output['amount']->percent_charge,
                'fixed_charge'      => $output['amount']->fixed_charge,
                'total_charge'      => $output['amount']->total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         => "Add Money",
                'message'       => "Your Wallet"." (".$output['wallet']->currency->code.")  "."balance  has been added"." ".$output['amount']->requested_amount.' '. $output['wallet']->currency->code,
                'time'          => Carbon::now()->diffForHumans(),
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'user_id'  =>  $user->id,
                'message'   => $notification_content,
            ]);

            //Push Notifications
            try{
                (new PushNotificationHelper())->prepare([$user->id],[
                    'title' => $notification_content['title'],
                    'desc'  => $notification_content['message'],
                    'user_type' => 'user',
                ])->send();
                }catch(Exception $e) {}
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__('Something went wrong! Please try again'));
        }
    }
    public function insertDeviceCoinGate($output,$id) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();

        // $mac = exec('getmac');
        // $mac = explode(" ",$mac);
        // $mac = array_shift($mac);
        $mac = "";

        DB::beginTransaction();
        try{
            DB::table("transaction_devices")->insert([
                'transaction_id'=> $id,
                'ip'            => $client_ip,
                'mac'           => $mac,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__('Something went wrong! Please try again'));
        }
    }
    public function removeTempDataCoinGate($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }
    public static function isCoinGate($gateway) {
        $search_keyword = ['coingate','coinGate','coingate gateway','coingate crypto gateway','crypto gateway coingate'];
        $gateway_name = $gateway->name;

        $search_text = Str::lower($gateway_name);
        $search_text = preg_replace("/[^A-Za-z0-9]/","",$search_text);
        foreach($search_keyword as $keyword) {
            $keyword = Str::lower($keyword);
            $keyword = preg_replace("/[^A-Za-z0-9]/","",$keyword);
            if($keyword == $search_text) {
                return true;
                break;
            }
        }
        return false;
    }
    public function coingateCallbackResponse($reference,$callback_data, $output = null) {

        if(!$output) $output = $this->output;


        $callback_status = $callback_data['status'] ?? "";

        if(isset($output['transaction']) && $output['transaction'] != null && $output['transaction']->status != PaymentGatewayConst::STATUSSUCCESS) { // if transaction already created & status is not success

            // Just update transaction status and update user wallet if needed
            if($callback_status == $this->coinGate_status_paid) {

                $transaction_details                        = json_decode(json_encode($output['transaction']->details),true) ?? [];
                $transaction_details['gateway_response']    = $callback_data;

                // update transaction status
                DB::beginTransaction();

                try{
                    DB::table($output['transaction']->getTable())->where('id',$output['transaction']->id)->update([
                        'status'        => PaymentGatewayConst::STATUSSUCCESS,
                        'details'       => json_encode($transaction_details),
                        'callback_ref'  => $reference,
                    ]);

                    $this->updateWalletBalance($output);
                    DB::commit();

                }catch(Exception $e) {
                    DB::rollBack();
                    logger(__('Something went wrong! Please try again'));
                    throw new Exception($e);
                }
            }
        }else { // need to create transaction and update status if needed

            $status = PaymentGatewayConst::STATUSPENDING;

            if($callback_status == $this->coinGate_status_paid) {
                $status = PaymentGatewayConst::STATUSSUCCESS;
            }

            $this->createTransactioncoinGate($output, $status);
        }

        logger("Transaction Created Successfully ::" . $callback_data['status']);
    }
    //for api
    public function coingateInitApi($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getCoinGateCredentials($output);
        $request_credentials = $this->getCoinGateRequestCredentials();
        return $this->coinGateCreateOrderApi($request_credentials);

    }
    public function coinGateCreateOrderApi($credentials) {

        $request_base_url       = $credentials->url;
        $request_access_token   = $credentials->token;

        $temp_record_token = generate_unique_string('temporary_datas','identifier',60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel

        $redirection  = [
            "return_url" => "api.coingate.payment.success",
            "cancel_url" => "api.coingate.payment.cancel",
            "callback_url" => "add.money.payment.callback"
        ];
        $url_parameter = $this->getUrlParams();
        $endpoint = $request_base_url . "/" . $this->getCoinGateEndpoint('createOrder');

        $response = Http::withToken($request_access_token)->post($endpoint,[
            'order_id'          => Str::uuid(),
            'price_amount'      => $this->output['amount']->total_amount,
            'price_currency'    => $this->output['amount']->sender_cur_code,
            'receive_currency'  => $this->output['amount']->default_currency,
            'callback_url'      => $this->setGatewayRoute($redirection['callback_url'],PaymentGatewayConst::COINGATE,$url_parameter."&r-source=".PaymentGatewayConst::APP),
            'cancel_url'        => $this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::COINGATE,$url_parameter."&r-source=".PaymentGatewayConst::APP),
            'success_url'       => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::COINGATE,$url_parameter."&r-source=".PaymentGatewayConst::APP),
        ]);

        if($response->failed()) {
            $message = json_decode($response->body(),true);
            $error = ['error'=>[$message['message']]];
            return Helpers::error($error);
        }
        if($response->successful()) {
            $response_array = json_decode($response->body(),true);

            if(isset($response_array['payment_url'])) {

                // create junk transaction
                $this->coinGateJunkInsert($this->output,$response_array,$temp_record_token);

                if(request()->expectsJson()) {

                    $this->output['redirection_response']   = $response_array;
                    $this->output['redirect_links']         = [];
                    $this->output['redirect_url']           = $response_array['payment_url'];
                    // return $this->get();
                }

                $data['link'] =  $response_array['payment_url'];
                $data['trx'] =  $temp_record_token;

                return $data;
            }
        }
        $error = ['error'=>[__('Something went wrong! Please try again')]];
        return Helpers::error($error);


    }

}
