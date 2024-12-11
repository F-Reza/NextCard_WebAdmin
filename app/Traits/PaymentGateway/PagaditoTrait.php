<?php

namespace App\Traits\PaymentGateway;

use App\Constants\NotificationConst;
use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\Pagadito;
use App\Http\Helpers\PaymentGateway;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\BasicSettings;
use App\Models\UserNotification;
use App\Notifications\User\AddMoney\ApprovedMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Traits\AdminNotifications\AddMoneyNotifications;

trait PagaditoTrait {

    use AddMoneyNotifications;

    private $pagadito_gateway_credentials;

    public function pagaditoInit($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getPagaditoCredentials($output);
        $this->pagaditoSetSecreteKey($credentials);
        return $this->pagaditoCreateOrder($credentials,$output);
    }
    public function pagaditoInitApi($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getPagaditoCredentials($output);
        $this->pagaditoSetSecreteKey($credentials);
        return $this->pagaditoCreateOrder($credentials,$output);
    }

    public function getPagaditoCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception(__("Payment gateway not available"));

        $uid_sample = ['UID','uid','u_id'];
        $wsk_sample = ['WSK','wsk','w_sk'];
        $base_url_sample = ['Base URL','base_url','base-url', 'base url'];

        $uid =  PaymentGateway::getValueFromGatewayCredentials($gateway,$uid_sample);
        $wsk =  PaymentGateway::getValueFromGatewayCredentials($gateway,$wsk_sample);
        $base_url =  PaymentGateway::getValueFromGatewayCredentials($gateway,$base_url_sample);
        $mode = $gateway->env;

        $gateway_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => PaymentGatewayConst::ENV_SANDBOX,
            PaymentGatewayConst::ENV_PRODUCTION => PaymentGatewayConst::ENV_PRODUCTION,
        ];

        if(array_key_exists($mode,$gateway_register_mode)) {
            $mode = $gateway_register_mode[$mode];
        }else {
            $mode = PaymentGatewayConst::ENV_SANDBOX;
        }

        $credentials = (object) [
            'uid'     => $uid,
            'wsk'     => $wsk,
            'base_url'     => $base_url,
            'mode'          => $mode,
        ];

        $this->pagadito_gateway_credentials = $credentials;

        return $credentials;
    }

    public function pagaditoSetSecreteKey($credentials){
        Config::set('pagadito.UID',$credentials->uid);
        Config::set('pagadito.WSK',$credentials->wsk);
        if($credentials->mode == "SANDBOX"){
            Config::set('pagadito.SANDBOX',true);
        }else{
            Config::set('pagadito.SANDBOX',false);
        }

    }

    public function pagaditoCreateOrder($credentials, $output) {
        if(!$output) $output = $this->output;
        $uid = $credentials->uid;
        $wsk = $credentials->wsk;
        $mode = $credentials->mode;
        $Pagadito = new Pagadito($uid,$wsk,$credentials,$output['amount']->sender_cur_code);
        $Pagadito->config( $credentials,$output['amount']->sender_cur_code);

        if ($mode == "SANDBOX") {
            $Pagadito->mode_sandbox_on();
        }
        if ($Pagadito->connect()) {
            $Pagadito->add_detail(1,"Please Pay For  Transfer Money", $output['amount']->total_amount);
            $Pagadito->set_custom_param("param1", "Valor de param1");
            $Pagadito->set_custom_param("param2", "Valor de param2");
            $Pagadito->set_custom_param("param3", "Valor de param3");
            $Pagadito->set_custom_param("param4", "Valor de param4");
            $Pagadito->set_custom_param("param5", "Valor de param5");

            $Pagadito->enable_pending_payments();
            $getUrls = (object)$Pagadito->exec_trans($Pagadito->get_rs_code());

            if($getUrls->code == "PG1002" ){
                $parts = parse_url($getUrls->value);
                parse_str($parts['query'], $query);
                // Extract the token value
                if (isset($query['token'])) {
                    $tokenValue = $query['token'];
                } else {
                    $tokenValue = '';
                }
                $this->pagaditioJunkInsert($getUrls,$tokenValue);
                if(request()->expectsJson()) { // API Response
                    $this->output['temp_identifier']        = $tokenValue;
                    $this->output['redirection_response']   = $getUrls;
                    $this->output['redirect_links']         = [];
                    $this->output['redirect_url']           = $getUrls->value;
                    return $this->get();
                }

                return redirect($getUrls->value);

            }
            $ern = rand(1000, 2000);
            if (!$Pagadito->exec_trans($ern)) {
                switch($Pagadito->get_rs_code())
                {
                    case "PG2001":
                        /*Incomplete data*/
                    case "PG3002":
                        /*Error*/
                    case "PG3003":
                        /*Unregistered transaction*/
                    case "PG3004":
                        /*Match error*/
                    case "PG3005":
                        /*Disabled connection*/
                    default:
                        throw new Exception($Pagadito->get_rs_code().": ".$Pagadito->get_rs_message());
                        break;
                }
            }
            return redirect($Pagadito->exec_trans($Pagadito->get_rs_code()));
        } else {

            switch($Pagadito->get_rs_code())
            {
                case "PG2001":
                    /*Incomplete data*/
                case "PG3001":
                    /*Problem connection*/
                case "PG3002":
                    /*Error*/
                case "PG3003":
                    /*Unregistered transaction*/
                case "PG3005":
                    /*Disabled connection*/
                case "PG3006":
                    /*Exceeded*/
                default:
                    throw new Exception($Pagadito->get_rs_code().": ".$Pagadito->get_rs_message());
                    break;
            }

        }

        throw new Exception(__("Something went wrong! Please try again"));
    }

    public function pagaditioJunkInsert($response, $identifier_token) {
        $output = $this->output;
        $user = auth()->guard(get_auth_guard())->user();
        $creator_table = $creator_id = $wallet_table = $wallet_id = null;

        $creator_table = auth()->guard(get_auth_guard())->user()->getTable();
        $creator_id = auth()->guard(get_auth_guard())->user()->id;
        $wallet_table = $output['wallet']->getTable();
        $wallet_id = $output['wallet']->id;

            $data = [
                'gateway'      => $output['gateway']->id,
                'currency'     => $output['currency']->id,
                'amount'       => json_decode(json_encode($output['amount']),true),
                'response'     => $response,
                'wallet_table'  => $wallet_table,
                'wallet_id'     => $wallet_id,
                'creator_table' => $creator_table,
                'creator_id'    => $creator_id,
                'creator_guard' => get_auth_guard(),
            ];

        return TemporaryData::create([
            'type'          => PaymentGatewayConst::PAGADITO,
            'identifier'    => $identifier_token,
            'data'          => $data,
        ]);

    }

    public function pagaditoSuccess($output = null) {
        $output['capture']              = $output['tempData']['data']->response ?? "";
        $output['record_handler']       = 'insertRecordWeb';
        // need to insert new transaction in database
        try{
            $this->createTransactionPagadito($output);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    public function createTransactionPagadito($output) {
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        $trx_id = 'AM'.getTrxNum();
        $inserted_id = $this->insertRecordPagadito($output,$trx_id);
        $this->insertChargesPagadito($output,$inserted_id);
        $this->adminNotification($trx_id,$output,PaymentGatewayConst::STATUSSUCCESS);
        $this->insertDevicePagadito($output,$inserted_id);
        $this->removeTempDataPagadito($output);

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
    public function insertRecordPagadito($output,$trx_id) {

        $trx_id = $trx_id;
        $token = $this->output['tempData']['identifier'] ?? "";
        DB::beginTransaction();
        try{
            if(Auth::guard(get_auth_guard())->check()){
                $user_id = auth()->guard(get_auth_guard())->user()->id;
            }
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user_id,
                'user_wallet_id'                => $output['wallet']->id,
                'payment_gateway_currency_id'   => $output['currency']->id,
                'type'                          =>  "ADD-MONEY",
                'trx_id'                        => $trx_id,
                'request_amount'                => $output['amount']->requested_amount,
                'payable'                       => $output['amount']->total_amount,
                'available_balance'             => $output['wallet']->balance + $output['amount']->requested_amount,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::TYPEADDMONEY," ")) . " With " . $output['gateway']->name,
                'details'                       => "Pagadito Payment Successful",
                'status'                        => true,
                'attribute'                      =>PaymentGatewayConst::SEND,
                'created_at'                    => now(),
            ]);

            $this->updateWalletBalancePagadito($output);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__('Something went wrong! Please try again'));
        }
        return $id;
    }

    public function updateWalletBalancePagadito($output) {
        $update_amount = $output['wallet']->balance + $output['amount']->requested_amount;
        $output['wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function insertChargesPagadito($output,$id) {
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
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'user_id'  =>  auth()->user()->id,
                'message'   => $notification_content,
            ]);
            //Push Notifications
            try{
                (new PushNotificationHelper())->prepare([auth()->user()->id],[
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

    public function insertDevicePagadito($output,$id) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();

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

    public function removeTempDataPagadito($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }

    public static function isPagadito($gateway) {
        $search_keyword = ['pagadito','pagadito gateway','pagadito payment','pagadito fait gateway','gateway pagadito'];
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

}
