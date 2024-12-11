<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Models\Admin\Currency;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use App\Traits\PaymentGateway\Manual;
use App\Traits\PaymentGateway\Stripe;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\Api\Helpers;
use Illuminate\Support\Facades\Session;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;
use App\Http\Helpers\PaymentGatewayApi;
use App\Models\Admin\CryptoTransaction;
use App\Models\UserNotification;
use KingFlamez\Rave\Facades\Rave as Flutterwave;
use App\Traits\PaymentGateway\FlutterwaveTrait;
use App\Traits\PaymentGateway\RazorTrait;
use App\Traits\PaymentGateway\SslcommerzTrait;
use App\Traits\PaymentGateway\QrpayTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\RedirectResponse;

class AddMoneyController extends Controller
{
    use Stripe,Manual,FlutterwaveTrait,RazorTrait,SslcommerzTrait,QrpayTrait;

    public function index() {
        $page_title = __("Add Money");
        $user_wallets = UserWallet::auth()->get();
        $user_currencies = Currency::whereIn('id',$user_wallets->pluck('id')->toArray())->get();

        $payment_gateways_currencies = PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::add_money_slug());
            $gateway->where('status', 1);
        })->get();
        $transactions = Transaction::auth()->addMoney()->latest()->take(5)->get();
        return view('user.sections.add-money.index',compact("page_title","payment_gateways_currencies","transactions"));
    }
    public function submit(Request $request) {
        try{
          $instance = PaymentGatewayHelper::init($request->all())->type(PaymentGatewayConst::TYPEADDMONEY)->gateway()->render();
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return $instance;
    }
    public function success(Request $request, $gateway){
        $requestData = $request->all();
        $token = $requestData['token'] ?? "";
        $checkTempData = TemporaryData::where("type",$gateway)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();

        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive();
        }catch(Exception $e) {

            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully added money')]]);
    }
    public function cancel(Request $request, $gateway) {
        $token = session()->get('identifier');
        if( $token){
            TemporaryData::where("identifier",$token)->delete();
        }

        return redirect()->route('user.add.money.index');
    }
    //stripe success
    public function stripePaymentSuccess($trx){
        $token = $trx;
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::STRIPE)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();

        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('stripe');
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something went wrong! Please try again')]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);

    }

    public function manualPayment(){
        $tempData = Session::get('identifier');
        $hasData = TemporaryData::where('identifier', $tempData)->first();
        $gateway = PaymentGateway::manual()->where('slug',PaymentGatewayConst::add_money_slug())->where('id',$hasData->data->gateway)->first();
        $page_title = __("Manual Payment Via").' '.$gateway->name;
        if(!$hasData){
            return redirect()->route('user.add.money.index');
        }
        return view('user.sections.add-money.manual.payment_confirmation',compact("page_title","hasData",'gateway'));
    }
    public function flutterwaveCallback()
    {
        $status = request()->status;
        //if payment is successful
        if ($status ==  'successful' || $status == 'completed') {

            $transactionID = Flutterwave::getTransactionIDFromCallback();
            $data = Flutterwave::verifyTransaction($transactionID);

            $requestData = request()->tx_ref;
            $token = $requestData;

            $checkTempData = TemporaryData::where("type",'flutterwave')->where("identifier",$token)->first();

            if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);

            $checkTempData = $checkTempData->toArray();

            try{
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('flutterWave');
            }catch(Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);

        }
        elseif ($status ==  'cancelled'){
            return redirect()->route('user.add.money.index')->with(['error' => [__('Add money cancelled')]]);
        }
        else{
            return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction failed")]]);
        }
    }
    //sslcommerz success
    public function sllCommerzSuccess(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;

        $user = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if( $data['status'] != "VALID"){
            return redirect()->route("user.add.money.index")->with(['error' => [__('Added Money Failed')]]);
        }
        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('sslcommerz');
        }catch(Exception $e) {
            return back()->with(['error' => ["Something Is Wrong..."]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    //sslCommerz fails
    public function sllCommerzFails(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $user = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if( $data['status'] == "FAILED"){
            TemporaryData::destroy($checkTempData['id']);
            return redirect()->route("user.add.money.index")->with(['error' => [__('Added Money Failed')]]);
        }

    }
    //sslCommerz canceled
    public function sllCommerzCancel(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
        $checkTempData = $checkTempData->toArray();
        $creator_id = $checkTempData['data']->creator_id ?? null;
        $creator_guard = $checkTempData['data']->creator_guard ?? null;
        $user = Auth::guard($creator_guard)->loginUsingId($creator_id);
        if( $data['status'] != "VALID"){
            TemporaryData::destroy($checkTempData['id']);
            return redirect()->route("user.add.money.index")->with(['error' => [__('Added Money Canceled')]]);
        }

    }
    // Qrpay Call Back
    public function qrPaySuccess(Request $request)
    {
        if ($request->type ==  'success') {
            $requestData = $request->all();
            $checkTempData = TemporaryData::where("type", 'qrpay')->where("identifier", $requestData['data']['custom'])->first();
            if (!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            $checkTempData = $checkTempData->toArray();
            try {
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('qrpay');
            } catch (Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
        } else {
            return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed')]]);
        }
    }
    // QrPay Cancel
    public function qrPayCancel(Request $request, $trx_id)
    {
        TemporaryData::where("identifier", $trx_id)->delete();
        return redirect()->route("user.add.money.index")->with(['error' => [__('Your Payment Canceled Successfully')]]);
    }
    //coingate response start
    public function coinGateSuccess(Request $request, $gateway){

        try{
            $token = $request->token;
            $checkTempData = TemporaryData::where("type",PaymentGatewayConst::COINGATE)->where("identifier",$token)->first();
            if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
            }else {
                if(!$checkTempData) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }
            $update_temp_data = json_decode(json_encode($checkTempData->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $checkTempData->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $checkTempData->toArray();
            PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('coingate');
        }catch(Exception $e) {
            return redirect()->route("user.add.money.index")->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    public function coinGateCancel(Request $request, $gateway){
        if($request->has('token')) {
            $identifier = $request->token;
            if($temp_data = TemporaryData::where('identifier', $identifier)->first()) {
                $temp_data->delete();
            }
        }
        return redirect()->route("user.add.money.index")->with(['error' => [__('Added Money Canceled Successfully')]]);
    }
    public function callback(Request $request,$gateway){
        $callback_token = $request->get('token');
        $callback_data = $request->all();
        try{
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEADDMONEY)->handleCallback($callback_token,$callback_data,$gateway);
        }catch(Exception $e) {
            // handle Error
            logger($e);
        }
    }
    //coingate response end
    public function cryptoPaymentAddress(Request $request, $trx_id) {
        $page_title = "Crypto Payment Address";
        $transaction = Transaction::where('trx_id', $trx_id)->firstOrFail();

        if($transaction->gateway_currency->gateway->isCrypto() && $transaction->details?->payment_info?->receiver_address ?? false) {

            return view('user.sections.add-money.payment.crypto.address', compact(
                'transaction',
                'page_title',
            ));
        }

        return abort(404);
    }
    public function cryptoPaymentConfirm(Request $request, $trx_id)
    {
        // $basic_setting = BasicSettings::first();
        $transaction = Transaction::where('trx_id',$trx_id)->where('status', PaymentGatewayConst::STATUSWAITING)->firstOrFail();
        $user =  $transaction->user;
        $gateway_currency =  $transaction->currency->alias;

        $request_data = $request->merge([
            'currency' => $gateway_currency,
            'amount' => $transaction->request_amount,
        ]);
        $output = PaymentGatewayHelper::init($request_data->all())->type(PaymentGatewayConst::TYPEADDMONEY)->gateway()->get();

        $dy_input_fields = $transaction->details->payment_info->requirements ?? [];
        $validation_rules = $this->generateValidationRules($dy_input_fields);

        $validated = [];
        if(count($validation_rules) > 0) {
            $validated = Validator::make($request->all(), $validation_rules)->validate();
        }

        if(!isset($validated['txn_hash'])) return back()->with(['error' => [__('Transaction hash is required for verify')]]);

        $receiver_address = $transaction->details->payment_info->receiver_address ?? "";

        // check hash is valid or not
        $crypto_transaction = CryptoTransaction::where('txn_hash', $validated['txn_hash'])
                                                ->where('receiver_address', $receiver_address)
                                                ->where('asset',$transaction->gateway_currency->currency_code)
                                                ->where(function($query) {
                                                    return $query->where('transaction_type',"Native")
                                                                ->orWhere('transaction_type', "native");
                                                })
                                                ->where('status',PaymentGatewayConst::NOT_USED)
                                                ->first();

        if(!$crypto_transaction) return back()->with(['error' => [__('Transaction hash is not valid! Please input a valid hash')]]);

        if($crypto_transaction->amount >= $transaction->total_payable == false) {
            if(!$crypto_transaction) return back()->with(['error' => [__("Insufficient amount added. Please contact with system administrator")]]);
        }

        DB::beginTransaction();
        try{

            // Update user wallet balance
            DB::table($transaction->creator_wallet->getTable())
                ->where('id',$transaction->creator_wallet->id)
                ->increment('balance',$transaction->request_amount);

            // update crypto transaction as used
            DB::table($crypto_transaction->getTable())->where('id', $crypto_transaction->id)->update([
                'status'        => PaymentGatewayConst::USED,
            ]);

            // update transaction status
            $transaction_details = json_decode(json_encode($transaction->details), true);
            $transaction_details['payment_info']['txn_hash'] = $validated['txn_hash'];

            DB::table($transaction->getTable())->where('id', $transaction->id)->update([
                'details'       => json_encode($transaction_details),
                'status'        => PaymentGatewayConst::STATUSSUCCESS,
                'available_balance'        => $transaction->available_balance + $transaction->request_amount,
            ]);

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

            // if( $basic_setting->email_notification == true){
            //     $user->notify(new ApprovedMail($user,$output,$trx_id));
            // }

            DB::commit();

        }catch(Exception $e) {
            DB::rollback();
            return back()->with(['error' => [__('Something went wrong! Please try again')]]);
        }

        return back()->with(['success' => [__('Payment Confirmation Success')]]);
    }
    public function redirectUsingHTMLForm(Request $request, $gateway)
    {
        $temp_data = TemporaryData::where('identifier', $request->token)->first();
        if(!$temp_data || $temp_data->data->action_type != PaymentGatewayConst::REDIRECT_USING_HTML_FORM) return back()->with(['error' => ['Request token is invalid!']]);
        $redirect_form_data = $temp_data->data->redirect_form_data;
        $action_url         = $temp_data->data->action_url;
        $form_method        = $temp_data->data->form_method;

        return view('user.sections.add-money.automatic.redirect-form', compact('redirect_form_data', 'action_url', 'form_method'));
    }
    public function perfectSuccess(Request $request, $gateway){
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("type",PaymentGatewayConst::PERFECT_MONEY)->where("identifier",$token)->first();


            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$temp_data) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
            }else {
                if(!$temp_data) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive('perfect-money');
            if($instance instanceof RedirectResponse) return $instance;
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    public function perfectCancel(Request $request, $gateway) {

        if($request->has('token')) {
            $identifier = $request->token;
            if($temp_data = TemporaryData::where('identifier', $identifier)->first()) {
                $temp_data->delete();
            }
        }

        return redirect()->route("user.add.money.index")->with(['error' => [__('Added Money Canceled Successfully')]]);
    }
    public function perfectCallback(Request $request,$gateway) {

        $callback_token = $request->get('token');
        $callback_data = $request->all();
        try{
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEADDMONEY)->handleCallback($callback_token,$callback_data,$gateway);
        }catch(Exception $e) {
            // handle Error
            logger($e);
        }
    }
    /**
     * Redirect Users for collecting payment via Button Pay (JS Checkout)
     */
    public function redirectBtnPay(Request $request, $gateway)
    {
        try{
            return PaymentGatewayHelper::init([])->handleBtnPay($gateway, $request->all());
        }catch(Exception $e) {
            return redirect()->route('user.add.money.index')->with(['error' => [$e->getMessage()]]);
        }
    }
    public function successGlobal(Request $request, $gateway){
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("identifier",$token)->first();
            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$temp_data) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
            }else {
                if(!$temp_data) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive($temp_data['type']);
            if($instance instanceof RedirectResponse) return $instance;
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
    public function cancelGlobal(Request $request, $gateway) {
        $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
        if($temp_data = TemporaryData::where("identifier",$token)->first()) {
            $temp_data->delete();
        }
        return redirect()->route('user.add.money.index');
    }
    public function postSuccess(Request $request, $gateway)
    {
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("identifier",$token)->first();
            Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
        }catch(Exception $e) {
            return redirect()->route('index');
        }
        return $this->successGlobal($request, $gateway);
    }
    public function postCancel(Request $request, $gateway)
    {
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("identifier",$token)->first();
            Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
        }catch(Exception $e) {
            return redirect()->route('index');
        }
        return $this->cancelGlobal($request, $gateway);
    }
    public function successPagadito(Request $request, $gateway){
        $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
        $temp_data = TemporaryData::where("identifier",$token)->first();
        if($temp_data->data->creator_guard == 'web'){
            Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
            try{
                if(Transaction::where('callback_ref', $token)->exists()) {
                    if(!$temp_data) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
                }else {
                    if(!$temp_data) return redirect()->route('user.add.money.index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
                }

                $update_temp_data = json_decode(json_encode($temp_data->data),true);
                $update_temp_data['callback_data']  = $request->all();
                $temp_data->update([
                    'data'  => $update_temp_data,
                ]);
                $temp_data = $temp_data->toArray();
                $instance =PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive($temp_data['type']);
            }catch(Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
        }elseif($temp_data->data->creator_guard =='api'){
            $creator_table = $temp_data->data->creator_table ?? null;
            $creator_id = $temp_data->data->creator_id ?? null;
            $creator_guard = $temp_data->data->creator_guard ?? null;
            $api_authenticated_guards = PaymentGatewayConst::apiAuthenticateGuard();
            if($creator_table != null && $creator_id != null && $creator_guard != null) {
                if(!array_key_exists($creator_guard,$api_authenticated_guards)){
                    $error = ['error'=>[__('Request user doesn\'t save properly. Please try again')]];
                    return Helpers::error($error);

                }
                $creator = DB::table($creator_table)->where("id",$creator_id)->first();
                if(!$creator){
                    $error = ['error'=>[__('Request user doesn\'t save properly. Please try again')]];
                    return Helpers::error($error);
                }
                $api_user_login_guard = $api_authenticated_guards[$creator_guard];
                Auth::guard($api_user_login_guard)->loginUsingId($creator->id);
            }
            try{
                if(!$temp_data) {
                    if(Transaction::where('callback_ref',$token)->exists()) {
                        $message =  ['success'=>[__('Transaction request sended successfully!')]];
                        return Helpers::onlysuccess($message);
                    }else {
                        $error = ['error'=>[__('Transaction failed. Record didn\'t saved properly. Please try again')]];
                        return Helpers::error($error);
                    }
                }
                $update_temp_data = json_decode(json_encode($temp_data->data),true);
                $update_temp_data['callback_data']  = $request->all();
                $temp_data->update([
                    'data'  => $update_temp_data,
                ]);
                $temp_data = $temp_data->toArray();
                $instance = PaymentGatewayApi::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive($temp_data['type']);

                // return $instance;
            }catch(Exception $e) {
                $error = ['error'=>[$e->getMessage()]];
                return Helpers::error($error);
            }
            $message = ['success' => [__('Successfully Added Money')]];
            return Helpers::onlysuccess($message);

        }



    }

    public function paystackCallback(Request $request){
        $request_data = $request->all();
        $reference =  $request_data['reference'];
        $temp_data = TemporaryData::where("identifier",$reference)->first();
        if(!$temp_data) return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction Failed. The record didn't save properly. Please try again")]]);

        $secret_key = $temp_data->data->credentials->secret_key??"";
        $gateway = PaymentGateway::where('id',$temp_data->data->gateway)->first();

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/".$reference,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $secret_key",
            "Cache-Control: no-cache",
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $response   = json_decode($response,true);
        return $this->successPaystack($request,$response,$gateway);
    }
    public function successPaystack(Request $request,$response,$gateway){
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway->alias);
            $temp_data = TemporaryData::where("identifier",$token)->first();
            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$temp_data) return redirect()->route('user.add.money.index')->with(['success' => [__('Transaction request sended successfully!')]]);
            }else {
                if(!$temp_data) return redirect()->route('user.add.money.index')->with(['error' => [__("Transaction Failed. The record didn't save properly. Please try again")]]);
            }
            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $response;
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEADDMONEY)->responseReceive($temp_data['type']);
            if($instance instanceof RedirectResponse) return $instance;
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route("user.add.money.index")->with(['success' => [__('Successfully Added Money')]]);
    }
}


