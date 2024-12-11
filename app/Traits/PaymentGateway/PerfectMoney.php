<?php

namespace App\Traits\PaymentGateway;

use App\Constants\NotificationConst;
use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Helpers\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\UserNotification;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\AddMoney\ApprovedMail;
use Jenssegers\Agent\Agent;
use Carbon\Carbon;
use App\Traits\AdminNotifications\AddMoneyNotifications;

trait PerfectMoney {

    use AddMoneyNotifications;

    private $perfect_money_credentials;
    private $perfect_money_request_credentials;

    public function perfectMoneyInit($output = null)
    {
        if(!$output) $output = $this->output;
        $gateway_credentials = $this->perfectMoneyGatewayCredentials($output['gateway']);
        $request_credentials = $this->perfectMoneyRequestCredentials($gateway_credentials, $output['gateway'], $output['currency']);
        $output['request_credentials'] = $request_credentials;

        if($gateway_credentials->passphrase == "") {
            throw new Exception("You must set Alternate Passphrase under Settings section in your Perfect Money account before starting receiving payment confirmations.");
        }

        // need to insert junk for temporary data
        $temp_record        = $this->perfectMoneyJunkInsert($output, 'web');
        $temp_identifier    = $temp_record->identifier;

        $link_for_redirect_form = route('user.add.money.payment.redirect.form', [PaymentGatewayConst::PERFECT_MONEY, 'token' => $temp_identifier]);

        return redirect()->away($link_for_redirect_form);
    }

    public function perfectMoneyInitApi($output = null)
    {
        if(!$output) $output = $this->output;
        $gateway_credentials = $this->perfectMoneyGatewayCredentials($output['gateway']);
        $request_credentials = $this->perfectMoneyRequestCredentials($gateway_credentials, $output['gateway'], $output['currency']);
        $output['request_credentials'] = $request_credentials;

        if($gateway_credentials->passphrase == "") {
            throw new Exception("You must set Alternate Passphrase under Settings section in your Perfect Money account before starting receiving payment confirmations.");
        }

        // need to insert junk for temporary data
        $temp_record        = $this->perfectMoneyJunkInsert($output, 'api');
        $temp_identifier    = $temp_record->identifier;

        $link_for_redirect_form = route('user.add.money.payment.redirect.form', [PaymentGatewayConst::PERFECT_MONEY, 'token' => $temp_identifier]);

        $this->output['redirection_response']   = [];
        $this->output['redirect_links']         = [];
        $this->output['temp_identifier']        = $temp_identifier;
        $this->output['redirect_url']           = $link_for_redirect_form;
        return $this->get();
    }

    /**
     * Get payment gateway credentials for both sandbox and production
     */
    public function perfectMoneyGatewayCredentials($gateway)
    {
        if(!$gateway) throw new Exception("Oops! Payment Gateway Not Found!");

        $usd_account_sample     = ['usd account','usd','usd wallet','account usd'];
        $eur_account_sample     = ['eur account','eur','eur wallet', 'account eur'];
        $pass_phrase_sample     = ['alternate passphrase' ,'passphrase', 'perfect money alternate passphrase', 'alternate passphrase perfect money' , 'alternate phrase' , 'alternate pass'];

        $usd_account            = PaymentGateway::getValueFromGatewayCredentials($gateway,$usd_account_sample);
        $eur_account            = PaymentGateway::getValueFromGatewayCredentials($gateway,$eur_account_sample);
        $pass_phrase            = PaymentGateway::getValueFromGatewayCredentials($gateway,$pass_phrase_sample);

        $credentials = (object) [
            'usd_account'   => $usd_account,
            'eur_account'   => $eur_account,
            'passphrase'    => $pass_phrase, // alternate passphrase
        ];

        $this->perfect_money_credentials = $credentials;

        return $credentials;
    }

    /**
     * Get payment gateway credentials for making api request
     */
    public function perfectMoneyRequestCredentials($gateway_credentials, $payment_gateway, $gateway_currency)
    {
        if($gateway_currency->currency_code == "EUR") {
            $request_credentials = [
                'account'   => $gateway_credentials->eur_account
            ];
        }else if($gateway_currency->currency_code == "USD") {
            $request_credentials = [
                'account'   => $gateway_credentials->usd_account
            ];
        }

        $request_credentials = (object) $request_credentials;

        $this->perfect_money_request_credentials = $request_credentials;

        return $request_credentials;
    }

    public function perfectMoneyJunkInsert($output, $guard)
    {
        $action_type = PaymentGatewayConst::REDIRECT_USING_HTML_FORM;

        $payment_id = Str::uuid() . '-' . time();
        $this->setUrlParams("token=" . $payment_id); // set Parameter to URL for identifying when return success/cancel

        $redirect_form_data = $this->makingPerfectMoneyRedirectFormData($output, $payment_id,$guard);
        $form_action_url    = "https://perfectmoney.com/api/step1.asp";
        $form_method        = "POST";

        $data = [
            'gateway'               => $output['gateway']->id,
            'currency'              => $output['currency']->id,
            'amount'                => json_decode(json_encode($output['amount']),true),
            'wallet_table'          => $output['wallet']->getTable(),
            'wallet_id'             => $output['wallet']->id,
            'creator_table'         => auth()->guard(get_auth_guard())->user()->getTable(),
            'creator_id'            => auth()->guard(get_auth_guard())->user()->id,
            'creator_guard'         => get_auth_guard(),
            'action_type'           => $action_type,
            'redirect_form_data'    => $redirect_form_data,
            'action_url'            => $form_action_url,
            'form_method'           => $form_method,
        ];

        return TemporaryData::create([
            'user_id'       => Auth::id(),
            'type'          => PaymentGatewayConst::PERFECT_MONEY,
            'identifier'    => $payment_id,
            'data'          => $data,
        ]);
    }

    public function makingPerfectMoneyRedirectFormData($output, $payment_id, $guard)
    {
        $basic_settings = BasicSettingsProvider::get();

        $url_parameter = $this->getUrlParams();

        $total_amount = number_format($output['amount']->total_amount, 2, '.', '');

        if($guard == 'web'){
            $redirection = [
                'callback' => 'add.money.payment.callback',
                'success'  => 'user.add.money.perfect.payment.success',
                'cancel'   => 'user.add.money.perfect.payment.cancel',
            ];
        }else{
            $redirection = [
                'callback' => 'add.money.payment.callback',
                'success'  => 'api.add-money.perfect.success',
                'cancel'   => 'api.add-money.perfect.cancel',
            ];
        }

        return [
            [
                'name'  => 'PAYEE_ACCOUNT',
                'value' => $output['request_credentials']->account,
            ],
            [
                'name'  => 'PAYEE_NAME',
                'value' => $basic_settings->site_name,
            ],
            [
                'name'  => 'PAYMENT_AMOUNT',
                'value' => $total_amount,
            ],
            [
                'name'  => 'PAYMENT_UNITS',
                'value' => $output['currency']->currency_code,
            ],
            [
                'name'  => 'PAYMENT_ID',
                'value' => $payment_id,
            ],
            [
                'name'  => 'STATUS_URL',
                'value' => $this->setGatewayRoute($redirection['callback'],PaymentGatewayConst::PERFECT_MONEY,$url_parameter),
            ],
            [
                'name'  => 'PAYMENT_URL',
                'value' => $this->setGatewayRoute($redirection['success'],PaymentGatewayConst::PERFECT_MONEY,$url_parameter),
            ],
            [
                'name'  => 'PAYMENT_URL_METHOD',
                'value' => 'GET',
            ],
            [
                'name'  => 'NOPAYMENT_URL',
                'value' => $this->setGatewayRoute($redirection['cancel'],PaymentGatewayConst::PERFECT_MONEY,$url_parameter),
            ],
            [
                'name'  => 'NOPAYMENT_URL_METHOD',
                'value' => 'GET',
            ],
            [
                'name'  => 'BAGGAGE_FIELDS',
                'value' => '',
            ],
            [
                'name'  => 'INTERFACE_LANGUAGE',
                'value' => 'en_US',
            ],
            [
                'name'  => 'r-source',
                'value' => 'APP',
            ],
        ];
    }

    public function isPerfectMoney($gateway)
    {
        $search_keyword = ['perfectmoney','perfect money','perfect-money','perfect money gateway', 'perfect money payment gateway'];
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

    public function getPerfectMoneyAlternatePassphrase($gateway)
    {
        $gateway_credentials = $this->perfectMoneyGatewayCredentials($gateway);
        return $gateway_credentials->passphrase;
    }

    public function perfectmoneySuccess($output) {

        $reference              = $output['tempData']['identifier'];
        $output['capture']      = $output['tempData']['data']->callback_data ?? "";
        $output['callback_ref'] = $reference;

        $pass_phrase = strtoupper(md5($this->getPerfectMoneyAlternatePassphrase($output['gateway'])));

        if($output['capture'] != "") {

            $concat_string = $output['capture']->PAYMENT_ID . ":" . $output['capture']->PAYEE_ACCOUNT . ":" . $output['capture']->PAYMENT_AMOUNT . ":" . $output['capture']->PAYMENT_UNITS . ":" . $output['capture']->PAYMENT_BATCH_NUM . ":" . $output['capture']->PAYER_ACCOUNT . ":" . $pass_phrase . ":" . $output['capture']->TIMESTAMPGMT;

            $md5_string = strtoupper(md5($concat_string));

            $v2_hash = $output['capture']->V2_HASH;

            if($md5_string == $v2_hash) {
                // this transaction is success
                if(!$this->searchWithReferenceInTransaction($reference)) {
                    // need to insert new transaction in database
                    try{
                        $this->createTransactionPerfect($output, PaymentGatewayConst::STATUSSUCCESS);
                    }catch(Exception $e) {
                        throw new Exception($e->getMessage());
                    }
                }
            }
        }

    }

    public function createTransactionPerfect($output,$status = PaymentGatewayConst::STATUSSUCCESS){
        $basic_setting = BasicSettings::first();
        if($this->predefined_user) {
            $user = $this->predefined_user;
        }else {
            $user = auth()->guard(get_auth_guard())->user();
        }
        $trx_id ='AM'.getTrxNum();
        $inserted_id = $this->insertRecordPerfect($output,$trx_id,$status);
        $this->insertChargesPerfectMoney($output,$inserted_id);
        $this->adminNotification($trx_id,$output,$status);
        $this->insertDevicePerfectMoney($output,$inserted_id);

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

    public function insertRecordPerfect($output,$trx_id,$status = PaymentGatewayConst::STATUSSUCCESS) {
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
                    $this->updateWalletBalancePerfect($output);
                }
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    public function updateWalletBalancePerfect($output) {
        $update_amount = $output['wallet']->balance + $output['amount']->requested_amount;
        $output['wallet']->update([
            'balance'   => $update_amount,
        ]);
    }
    public function insertChargesPerfectMoney($output,$id) {
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
    public function insertDevicePerfectMoney($output,$id) {
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

    public function removeTempDataPerfect($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }


    public function perfectmoneyCallbackResponse($reference,$callback_data, $output = null) {

        if(!$output) $output = $this->output;
        $pass_phrase = strtoupper(md5($this->getPerfectMoneyAlternatePassphrase($output['gateway'])));

        if(is_array($callback_data) && count($callback_data) > 0) {
            $concat_string = $callback_data['PAYMENT_ID'] . ":" . $callback_data['PAYEE_ACCOUNT'] . ":" . $callback_data['PAYMENT_AMOUNT'] . ":" . $callback_data['PAYMENT_UNITS'] . ":" . $callback_data['PAYMENT_BATCH_NUM'] . ":" . $callback_data['PAYER_ACCOUNT'] . ":" . $pass_phrase . ":" . $callback_data['TIMESTAMPGMT'];

            $md5_string = strtoupper(md5($concat_string));
            $v2_hash = $callback_data['V2_HASH'];

            if($md5_string != $v2_hash) {
                return false;
                logger("Transaction hash did not match. ref: $reference", [$callback_data]);
            }
        }else {
            return false;
            logger("Invalid callback data. ref: $reference", [$callback_data]);
        }

        if(isset($output['transaction']) && $output['transaction'] != null && $output['transaction']->status != PaymentGatewayConst::STATUSSUCCESS) { // if transaction already created & status is not success

            // Just update transaction status and update user wallet if needed
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

                $this->updateWalletBalancePerfect($output);
                DB::commit();

            }catch(Exception $e) {
                DB::rollBack();
                logger($e);
                throw new Exception($e);
            }
        }else { // need to create transaction and update status if needed

            $status = PaymentGatewayConst::STATUSSUCCESS;

            $this->createTransactionPerfect($output, $status);
        }

        logger("Transaction Created Successfully! ref: " . $reference);
    }
}
