<?php
namespace App\Traits\PaymentGateway;

use App\Constants\NotificationConst;
use Exception;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use App\Http\Helpers\PaymentGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Models\UserNotification;
use App\Notifications\User\AddMoney\ApprovedMail;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Traits\AdminNotifications\AddMoneyNotifications;

trait RazorTrait  {

    use AddMoneyNotifications;

    private $razorpay_gateway_credentials;
    private $request_credentials;
    private $razorpay_api_base_url  = "https://api.razorpay.com/";
    private $razorpay_api_v1        = "v1";
    private $razorpay_btn_pay       = true;

    public function razorInit($output) {
        if(!$output) $output = $this->output;

        $request_credentials = $this->getRazorpayRequestCredentials($output);

        try{
            if($this->razorpay_btn_pay) {
                // create link for btn pay
                return $this->razorpayCreateLinkForBtnPay($output);
            }
            return $this->createRazorpayPaymentLink($output, $request_credentials);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    public function razorInitApi($output) {
        if(!$output) $output = $this->output;

        $request_credentials = $this->getRazorpayRequestCredentials($output);


        try{
            if($this->razorpay_btn_pay) {
                // create link for btn pay
                return $this->razorpayCreateLinkForBtnPay($output);
            }
            return $this->createRazorpayPaymentLink($output, $request_credentials);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    /**
     * Create Link for Button Pay (JS Checkout)
     */
    public function razorpayCreateLinkForBtnPay($output)
    {
        $temp_record_token = generate_unique_string('temporary_datas','identifier',35);
        $temp_data = $this->razorPayJunkInsert($temp_record_token); // create temporary information
        $temp_identifier    = $temp_record_token;


        $btn_link = $this->generateLinkForBtnPay($temp_record_token, PaymentGatewayConst::RAZORPAY);
        if(request()->expectsJson()) {
            $this->output['temp_identifier']        = $temp_identifier;
            $this->output['redirection_response']   = [];
            $this->output['redirect_links']         = [];
            $this->output['redirect_url']           = $btn_link;
            return $this->get();
        }

        return redirect($btn_link);
    }
    /**
     * Button Pay page redirection with necessary data
     */
    public function razorpayBtnPay($temp_data)
    {
        $data = $temp_data->data;
        $output = $this->output;

        if(!isset($data->razorpay_order)) { // is order is not created the create new order
            // Need to create order
            $order = $this->razorpayCreateOrder([
                'amount'            => get_amount($data->amount->total_amount, null, 2) * 100,
                'currency'          => $data->amount->sender_cur_code,
                'receipt'           => $temp_data->identifier,
                'partial_payment'   => false,
            ]);

            // Update TempData
            $update_data = json_decode(json_encode($data), true);
            $update_data['razorpay_order'] = $order;

            $temp_data->update([
                'data'  => $update_data,
            ]);

            $temp_data->refresh();
        }

        $data = $temp_data->data; // update the data variable
        $order = $data->razorpay_order;

        $order_id                   = $order->id;
        $request_credentials        = $this->getRazorpayRequestCredentials($output);
        $output['order_id']         = $order_id;
        $output['key']              = $request_credentials->key_id;
        $output['callback_url']     = $data->callback_url;
        $output['cancel_url']       = $data->cancel_url;
        $output['user']             = auth()->guard(get_auth_guard())->user();

        return view('payment-gateway.btn-pay.razorpay', compact('output'));
    }
    /**
     * Create order for receive payment
     */
    public function razorpayCreateOrder($request_data, $output = null)
    {
        if($output == null) $output = $this->output;

        $endpoint = $this->razorpay_api_base_url . $this->razorpay_api_v1 . "/orders";

        $request_credentials = $this->getRazorpayRequestCredentials($output);

        $key_id = $request_credentials->key_id;
        $secret_key = $request_credentials->secret_key;

        $response = Http::withBasicAuth($key_id, $secret_key)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($endpoint, $request_data)->throw(function(Response $response, RequestException $exception) {
            $response_body = json_decode(json_encode($response->json()), true);
            throw new Exception($response_body['error']['description'] ?? "");
        })->json();

        return $response;
    }
    public function createRazorpayPaymentLink($output, $request_credentials)
    {
        $endpoint = $this->razorpay_api_base_url . $this->razorpay_api_v1 . "/payment_links";

        $key_id = $request_credentials->key_id;
        $secret_key = $request_credentials->secret_key;

        $temp_record_token = generate_unique_string('temporary_datas','identifier',35);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel

        $redirection = $this->getRedirection();
        $url_parameter = $this->getUrlParams();

        $user = auth()->guard(get_auth_guard())->user();

        $temp_data = $this->razorPayJunkInsert($temp_record_token); // create temporary information

        $response = Http::withBasicAuth($key_id, $secret_key)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'amount' => ceil($output['amount']->total_amount) * 100,
            'currency' => $output['currency']->currency_code,
            'expire_by' => now()->addMinutes(20)->timestamp,
            'reference_id' => $temp_record_token,
            'description' => 'Add Money',
            'customer' => [
                'name' => $user->firstname ?? "",
                'email' => $user->email ?? "",
            ],
            'notify' => [
                'sms' => false,
                'email' => true,
            ],
            'reminder_enable' => true,
            'callback_url' => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::RAZORPAY,$url_parameter),
            'callback_method' => 'get',
        ])->throw(function(Response $response, RequestException $exception) use ($temp_data) {
            $response_body = json_decode(json_encode($response->json()), true);
            $temp_data->delete();
            throw new Exception($response_body['error']['description'] ?? "");
        })->json();

        $response_array = json_decode(json_encode($response), true);

        $temp_data_contains = json_decode(json_encode($temp_data->data),true);
        $temp_data_contains['response'] = $response_array;

        $temp_data->update([
            'data'  => $temp_data_contains,
        ]);

        // make api response
        if(request()->expectsJson()) {
            $this->output['redirection_response']   = $response_array;
            $this->output['redirect_links']         = [];
            $this->output['redirect_url']           = $response_array['short_url'];
            return $this->get();
        }

        return redirect()->away($response_array['short_url']);
    }
    public function razorPayJunkInsert($temp_token)
    {
        $output = $this->output;
        $this->setUrlParams("token=" . $temp_token); // set Parameter to URL for identifying when return success/cancel
        $redirection = $this->getRedirection();
        $url_parameter = $this->getUrlParams();

        $data = [
            'gateway'       => $output['gateway']->id,
            'currency'      => $output['currency']->id,
            'amount'        => json_decode(json_encode($output['amount']),true),
            'wallet_table'  => $output['wallet']->getTable(),
            'wallet_id'     => $output['wallet']->id,
            'creator_table' => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'    => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard' => get_auth_guard(),
            'callback_url'  => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::RAZORPAY,$url_parameter),
            'cancel_url'    => $this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::RAZORPAY,$url_parameter),
        ];

        return TemporaryData::create([
            'type'          => PaymentGatewayConst::RAZORPAY,
            'identifier'    => $temp_token,
            'data'          => $data,
        ]);
    }
    public function getRazorpayCredentials($output)
    {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $key_id             = ['public key','razorpay public key','key id','razorpay public', 'public'];
        $secret_key_sample  = ['secret','secret key','razorpay secret','razorpay secret key'];

        $key_id             = PaymentGateway::getValueFromGatewayCredentials($gateway,$key_id);
        $secret_key         = PaymentGateway::getValueFromGatewayCredentials($gateway,$secret_key_sample);

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
            'key_id'                    => $key_id,
            'secret_key'                => $secret_key,
            'mode'                      => $mode
        ];

        $this->razorpay_gateway_credentials = $credentials;

        return $credentials;
    }
    public function getRazorpayRequestCredentials($output = null)
    {
        if(!$this->razorpay_gateway_credentials) $this->getRazorpayCredentials($output);
        $credentials = $this->razorpay_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        $request_credentials['key_id']          = $credentials->key_id;
        $request_credentials['secret_key']      = $credentials->secret_key;

        $this->request_credentials = (object) $request_credentials;
        return (object) $request_credentials;
    }
    public function isRazorpay($gateway)
    {
        $search_keyword = ['razorpay','razorpay gateway','gateway razorpay','razorpay payment gateway'];
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
    /**
     * Razorpay Success Response
     */
    public function razorpaySuccess($output)
    {
        $reference              = $output['tempData']['identifier'];
        $order_info             = $output['tempData']['data']->razorpay_order ?? false;
        $output['callback_ref'] = $reference;

        if($order_info == false) {
            throw new Exception("Invalid Order");
        }

        $redirect_response = $output['tempData']['data']->callback_data ?? false;
        if($redirect_response == false) {
            throw new Exception("Invalid response");
        }

        if(isset($redirect_response->razorpay_payment_id) && isset($redirect_response->razorpay_order_id) && isset($redirect_response->razorpay_signature)) {
            // Response Data
            $output['capture']      = $output['tempData']['data']->callback_data ?? "";

            $gateway_credentials = $this->getRazorpayRequestCredentials($output);
            // Need to verify payment signature
            $request_order_id       = $order_info->id; // database order
            $razorpay_payment_id    = $redirect_response->razorpay_payment_id; // response payment id
            $key_secret             = $gateway_credentials->secret_key;

            $generated_signature = hash_hmac("sha256", $request_order_id . "|" . $razorpay_payment_id, $key_secret);

            if($generated_signature == $redirect_response->razorpay_signature) {

                if(!$this->searchWithReferenceInTransaction($reference)) {
                    try{
                        $this->createTransactionRazorPay($output, PaymentGatewayConst::STATUSSUCCESS);
                    }catch(Exception $e) {
                        throw new Exception($e->getMessage());
                    }
                }

            }else {
                throw new Exception("Payment Failed, Invalid Signature Found!");
            }

        }
    }
    public function createTransactionRazorPay($output,$status = PaymentGatewayConst::STATUSSUCCESS){
        $basic_setting = BasicSettings::first();
        if($this->predefined_user) {
            $user = $this->predefined_user;
        }else {
            $user = auth()->guard(get_auth_guard())->user();
        }
        $trx_id ='AM'.getTrxNum();
        $inserted_id = $this->insertRecordRazorPay($output,$trx_id,$status);
        $this->insertChargesRazorPay($output,$inserted_id);
        $this->adminNotification($trx_id,$output,$status);
        $this->insertDeviceRazorPay($output,$inserted_id);

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
    public function insertRecordRazorPay($output,$trx_id,$status = PaymentGatewayConst::STATUSSUCCESS) {
        DB::beginTransaction();
        try{
                if($this->predefined_user) {
                    $user = $this->predefined_user;
                }else {
                    $user = auth()->guard(get_auth_guard())->user();
                }
                $user_id = $user->id;

                if($status === PaymentGatewayConst::STATUSSUCCESS) {
                    $available_balance = $output['wallet']->balance + $output['amount']->requested_amount;
                }else{
                    $available_balance = $output['wallet']->balance;
                }
                // Add money
                $trx_id = $trx_id;
                $id = DB::table("transactions")->insertGetId([
                    'user_id'                     => $user_id,
                    'user_wallet_id'              => $output['wallet']->id,
                    'payment_gateway_currency_id' => $output['currency']->id,
                    'type'                        => $output['type'],
                    'trx_id'                      => $trx_id,
                    'request_amount'              => $output['amount']->requested_amount,
                    'payable'                     => $output['amount']->total_amount,
                    'available_balance'           => $available_balance,
                    'callback_ref'                => $output['callback_ref'] ?? null,
                    'remark'                      => ucwords(remove_speacial_char($output['type']," ")) . " With " . $output['gateway']->name,
                    'details'                     => json_encode($output),
                    'status'                      => $status,
                    'created_at'                  => now(),
                ]);
                if($status === PaymentGatewayConst::STATUSSUCCESS) {
                    $this->updateWalletBalanceRzorPay($output);
                }
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    public function updateWalletBalanceRzorPay($output) {
        $update_amount = $output['wallet']->balance + $output['amount']->requested_amount;
        $output['wallet']->update([
            'balance'   => $update_amount,
        ]);
    }
    public function insertChargesRazorPay($output,$id) {
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
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::BALANCE_ADDED,
                'user_id'  =>  $user->id,
                'message'   => $notification_content,
            ]);
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
    public function insertDeviceRazorPay($output,$id) {
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
    public function removeTempDataRazorPay($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }

    /**
     * Razorpay Callback Response
     */
    public function razorpayCallbackResponse($response_data, $gateway)
    {
        $entity = $response_data['entity'] ?? false;
        $event  = $response_data['event'] ?? false;

        if($entity == "event" && $event == "order.paid") { // order response event data is valid
            // get the identifier
            $token = $response_data['payload']['order']['entity']['receipt'] ?? "";

            $temp_data = TemporaryData::where('identifier', $token)->first();

            // if transaction is already exists need to update status, balance & response data
            $transaction = Transaction::where('callback_ref', $token)->first();

            $status = PaymentGatewayConst::STATUSSUCCESS;

            if($temp_data) {
                $gateway_currency_id = $temp_data->data->currency ?? null;
                $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
                if($gateway_currency) {

                    $requested_amount = $temp_data['data']->amount->requested_amount ?? 0;
                    $validator_data = [
                        $this->currency_input_name  => $gateway_currency->alias,
                        $this->amount_input         => $requested_amount
                    ];

                    $get_wallet_model = PaymentGatewayConst::registerWallet()[$temp_data->data->creator_guard];
                    $user_wallet = $get_wallet_model::find($temp_data->data->wallet_id);
                    $this->predefined_user_wallet = $user_wallet;
                    $this->predefined_guard = $user_wallet->user->modelGuardName();
                    $this->predefined_user = $user_wallet->user;

                    $this->output['tempData'] = $temp_data;
                }

                $this->request_data = $validator_data;
                $this->gateway();
            }

            $output                     = $this->output;
            $output['callback_ref']     = $token;
            $output['capture']          = $response_data;

            if($transaction && $transaction->status != PaymentGatewayConst::STATUSSUCCESS) {

                $update_data                        = json_decode(json_encode($transaction->details), true);
                $update_data['gateway_response']    = $response_data;

                // update information
                $transaction->update([
                    'status'    => $status,
                    'details'   => $update_data
                ]);

                // update balance
                $this->updateWalletBalanceRzorPay($output);
            }else {
                // create new transaction with success
                $this->createTransactionRazorPay($output, $status, false);
            }

        }
    }
}
