<?php

namespace App\Http\Controllers\Api\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Api\Helpers;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Models\TemporaryData;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Notifications\Admin\ActivityNotification;
use App\Notifications\User\Withdraw\WithdrawMail;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Http\Request;
use App\Traits\ControlDynamicInputFields;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Jenssegers\Agent\Agent;


class WithdrawController extends Controller
{
    use ControlDynamicInputFields;

    protected $basic_settings;
    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }

    public function withdrawInfo(){
        $user = auth()->user();
        $userWallet = UserWallet::where('user_id',$user->id)->get()->map(function($data){
                return[
                    'balance' => getAmount($data->balance,4),
                    'currency' => get_default_currency_code(),
                ];
            })->first();

            $transactions = Transaction::auth()->withdrawMoney()->latest()->take(5)->get()->map(function($item){
                    $statusInfo = [
                        "success" =>      1,
                        "pending" =>      4,
                        "rejected" =>     3,
                        ];
                    return[
                        'id' => $item->id,
                        'trx' => $item->trx_id,
                        'gateway_name' => $item->currency->gateway->name,
                        'gateway_currency_name' => $item->currency->name,
                        'transaction_type' => $item->type,
                        'request_amount' => getAmount($item->request_amount,4).' '.get_default_currency_code() ,
                        'exchange_rate' => '1 ' .get_default_currency_code().' = '.getAmount($item->currency->rate,4).' '.$item->currency->currency_code,
                        'will_get' => getAmount($item->details->withdraw_data->will_get,4).' '.$item->currency->currency_code,
                        'total_charge' => getAmount($item->charge->total_charge,4).' '.get_default_currency_code(),
                        'payable' => getAmount($item->payable,4).' '.get_default_currency_code(),
                        'current_balance' => getAmount($item->available_balance,4).' '.get_default_currency_code(),
                        'status' => $item->stringStatus->value ,
                        'date_time' => $item->created_at ,
                        'status_info' =>(object)$statusInfo ,
                        'rejection_reason' =>$item->reject_reason??"" ,

                    ];
            });
            $gateways = PaymentGateway::where('status', 1)->where('slug', PaymentGatewayConst::money_out_slug())->get()->map(function($gateway){
                    $currencies = PaymentGatewayCurrency::where('payment_gateway_id',$gateway->id)->get()->map(function($data){
                    return[
                        'id' => $data->id,
                        'payment_gateway_id' => $data->payment_gateway_id,
                        'type' => $data->gateway->type,
                        'name' => $data->name,
                        'alias' => $data->alias,
                        'currency_code' => $data->currency_code,
                        'currency_symbol' => $data->currency_symbol,
                        'image' => $data->image,
                        'min_limit' => getAmount($data->min_limit,4),
                        'max_limit' => getAmount($data->max_limit,4),
                        'percent_charge' => getAmount($data->percent_charge,4),
                        'fixed_charge' => getAmount($data->fixed_charge,4),
                        'rate' => getAmount($data->rate,4),
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                    ];

                    });
                    return[
                        'id' => $gateway->id,
                        'name' => $gateway->name,
                        'image' => $gateway->image,
                        'slug' => $gateway->slug,
                        'code' => $gateway->code,
                        'type' => $gateway->type,
                        'alias' => $gateway->alias,
                        'supported_currencies' => $gateway->supported_currencies,
                        'input_fields' => $gateway->input_fields??null,
                        'status' => $gateway->status,
                        'currencies' => $currencies

                    ];
            });
            $data =[
                'base_curr'    => get_default_currency_code(),
                'base_curr_rate'    => getAmount(1,4),
                'default_image'    => "public/backend/images/default/default.webp",
                "image_path"  =>  "public/backend/images/payment-gateways",
                'userWallet'   =>   (object)$userWallet,
                'gateways'   => $gateways,
                'transactions'   =>   $transactions,
            ];
            $message =  ['success'=>[__("Withdraw Information!")]];
            return Helpers::success($data,$message);

    }
    public function withdrawInsert(Request $request){
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0',
            'gateway' => 'required'
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $user = auth()->user();
        $userWallet = UserWallet::where('user_id',$user->id)->where('status',1)->first();
        if(!$userWallet){
            $error = ['error'=>[__('User wallet not found')]];
            return Helpers::error($error);
        }
        $gate =PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::money_out_slug());
            $gateway->where('status', 1);
        })->where('alias',$request->gateway)->first();
        if (!$gate) {
            $error = ['error'=>[__("Invalid Gateway!")]];
            return Helpers::error($error);
        }
        $baseCurrency = Currency::default();
        if (!$baseCurrency) {
            $error = ['error'=>[__('Default Currency Not Setup Yet')]];
            return Helpers::error($error);
        }
        $amount = $request->amount;

        $min_limit =  $gate->min_limit / $gate->rate;
        $max_limit =  $gate->max_limit / $gate->rate;
        if($amount < $min_limit || $amount > $max_limit) {
            $error = ['error'=>[__('Please follow the transaction limit')]];
            return Helpers::error($error);
        }
        //gateway charge
        $fixedCharge = $gate->fixed_charge;
        $percent_charge =  (((($request->amount)/ 100) * $gate->percent_charge));
        $charge = $fixedCharge + $percent_charge;
        $conversion_amount = $request->amount * $gate->rate;
        $will_get = $conversion_amount;
        //base_cur_charge
        $baseFixedCharge = $gate->fixed_charge /  $gate->rate;
        $basePercent_charge = (($request->amount / 100) * $gate->percent_charge)/ $gate->rate;
        $base_total_charge = $baseFixedCharge + $basePercent_charge;
        $reduceAbleTotal = $amount + $base_total_charge;

        if( $reduceAbleTotal > $userWallet->balance){
            $error = ['error'=>[__('Sorry, insufficient balance')]];
            return Helpers::error($error);
        }

        $insertData = [
            'user_id'=> $user->id,
            'gateway_name'=> strtolower($gate->gateway->name),
            'gateway_type'=> $gate->gateway->type,
            'wallet_id'=> $userWallet->id,
            'trx_id'=> 'WM'.getTrxNum(),
            'amount' =>  $amount,
            'base_cur_fixed_charge' => $baseFixedCharge,
            'base_cur_percent_charge' => $basePercent_charge,
            'base_cur_charge' => $base_total_charge,
            'base_cur_rate' => $baseCurrency->rate,
            'gateway_id' => $gate->gateway->id,
            'gateway_currency_id' => $gate->id,
            'gateway_currency' => strtoupper($gate->currency_code),
            'gateway_percent_charge' => $percent_charge,
            'gateway_fixed_charge' => $fixedCharge,
            'gateway_charge' => $charge,
            'gateway_rate' => $gate->rate,
            'conversion_amount' => $conversion_amount,
            'will_get' => $will_get,
            'payable' => $reduceAbleTotal,
        ];
        $identifier = generate_unique_string("transactions","trx_id",16);
        $inserted = TemporaryData::create([
            'type'          => PaymentGatewayConst::WITHDRAWMONEY,
            'identifier'    => $identifier,
            'data'          => $insertData,
        ]);
        if( $inserted){
            $payment_gateway = PaymentGateway::where('id',$gate->payment_gateway_id)->first();
            $payment_informations =[
                'trx' =>  $identifier,
                'gateway_currency_name' =>  $gate->name,
                'request_amount' => getAmount($request->amount,2).' '.get_default_currency_code(),
                'exchange_rate' => "1".' '.get_default_currency_code().' = '.getAmount($gate->rate).' '.$gate->currency_code,
                'conversion_amount' =>  getAmount($conversion_amount,2).' '.$gate->currency_code,
                'total_charge' => getAmount($base_total_charge,2).' '.get_default_currency_code(),
                'will_get' => getAmount($will_get,2).' '.$gate->currency_code,
                'payable' => getAmount($reduceAbleTotal,2).' '.get_default_currency_code(),

            ];
            if($gate->gateway->type == "AUTOMATIC"){
                $url = route('api.withdraw.automatic.confirmed');
                $data =[
                    'payment_informations' => $payment_informations,
                    'gateway_type' => $payment_gateway->type,
                    'gateway_currency_name' => $gate->name,
                    'alias' => $gate->alias,
                    'url' => $url??'',
                    'method' => "post",
                    ];
                    $message =  ['success'=>['Withdraw Money Inserted Successfully']];
                    return Helpers::success($data, $message);
            }else{
                $url = route('api.withdraw.manual.confirmed');
                $data =[
                    'payment_informations' => $payment_informations,
                    'gateway_type' => $payment_gateway->type,
                    'gateway_currency_name' => $gate->name,
                    'alias' => $gate->alias,
                    'details' => $payment_gateway->desc??null,
                    'input_fields' => $payment_gateway->input_fields??null,
                    'url' => $url??'',
                    'method' => "post",
                    ];
                    $message =  ['success'=>[__('Withdraw Money Inserted Successfully')]];
                    return Helpers::success($data, $message);
            }

        }else{
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
    }
    public function withdrawConfirmed(Request $request){
        $validator = Validator::make($request->all(), [
            'trx'  => "required",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }

        $track = TemporaryData::where('identifier',$request->trx)->where('type',PaymentGatewayConst::WITHDRAWMONEY)->first();
        if(!$track){
            $error = ['error'=>[__("Sorry, your payment information is invalid")]];
            return Helpers::error($error);

        }
        $moneyOutData =  $track->data;
        $gateway = PaymentGateway::where('id', $moneyOutData->gateway_id)->first();
        if($gateway->type != "MANUAL"){
            $error = ['error'=>[__("Invalid request, it is not manual gateway request")]];
            return Helpers::error($error);
        }
        $payment_fields = $gateway->input_fields ?? [];
        $validation_rules = $this->generateValidationRules($payment_fields);
        $validator2 = Validator::make($request->all(), $validation_rules);
        if ($validator2->fails()) {
            $message =  ['error' => $validator2->errors()->all()];
            return Helpers::error($message);
        }
        $validated = $validator2->validate();
        $get_values = $this->placeValueWithFields($payment_fields, $validated);
            try{
                //send notifications
                $user = auth()->user();
                $inserted_id = $this->insertRecordManual($moneyOutData,$gateway,$get_values);
                $this->insertChargesManual($moneyOutData,$inserted_id);
                $this->insertDeviceManual($moneyOutData,$inserted_id);
                try{
                    if( $this->basic_settings->email_notification == true){
                        $user->notify(new WithdrawMail($user,$moneyOutData));
                    }
                }catch(Exception $e){}
                $this->adminNotification($moneyOutData,PaymentGatewayConst::STATUSPENDING);
                $track->delete();
                $message =  ['success'=>[__("Withdraw Money Request Send To Admin Successful")]];
                return Helpers::onlysuccess($message);
            }catch(Exception $e) {
                $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
                return Helpers::error($error);
            }

    }
    public function insertRecordManual($moneyOutData,$gateway,$get_values) {
        if($moneyOutData->gateway_type == "AUTOMATIC"){
            $status = 1;
        }else{
            $status = 2;
        }
        $details = [
            'user_data' => $get_values,
            'withdraw_data' => $moneyOutData,
        ];
        $trx_id = $moneyOutData->trx_id ??'MO'.getTrxNum();
        $authWallet = UserWallet::where('id',$moneyOutData->wallet_id)->where('user_id',$moneyOutData->user_id)->first();
        $afterCharge = ($authWallet->balance - ($moneyOutData->payable));
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => auth()->user()->id,
                'user_wallet_id'                => $moneyOutData->wallet_id,
                'payment_gateway_currency_id'   => $moneyOutData->gateway_currency_id,
                'type'                          => PaymentGatewayConst::WITHDRAWMONEY,
                'trx_id'                        => $trx_id,
                'request_amount'                => $moneyOutData->amount,
                'payable'                       => $moneyOutData->payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::WITHDRAWMONEY," ")) . " by " .$gateway->name,
                'details'                       => json_encode($details),
                'status'                        => $status,
                'created_at'                    => now(),
            ]);
            $this->updateWalletBalanceManual($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
        return $id;
    }
    public function updateWalletBalanceManual($authWalle,$afterCharge) {
        $authWalle->update([
            'balance'   => $afterCharge,
        ]);
    }
    public function insertChargesManual($moneyOutData,$id) {

        if(Auth::guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
        }
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $moneyOutData->base_cur_percent_charge,
                'fixed_charge'      => $moneyOutData->base_cur_fixed_charge,
                'total_charge'      => $moneyOutData->base_cur_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
             $notification_content = [
                'title'         => "Withdraw Money",
                'message'       => "Your Withdraw Request Send To Admin"." " .$moneyOutData->amount.' '.get_default_currency_code()." "."Successful",
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::WITHDRAWMONEY,
                'user_id'  =>  auth()->user()->id,
                'message'   => $notification_content,
            ]);
            //Push Notifications
            if( $this->basic_settings->push_notification == true){
                try{
                    (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }catch(Exception $e) {}
            }
        }catch(Exception $e) {
            DB::rollBack();
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
    }
    public function insertDeviceManual($output,$id) {
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
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
    }
    //admin notification global(Agent & User)
    public function adminNotification($data,$status){
        $user = auth()->guard(userGuard()['guard'])->user();
        $exchange_rate = " 1 ". get_default_currency_code().' = '. get_amount($data->gateway_rate,$data->gateway_currency);
        if($status == PaymentGatewayConst::STATUSSUCCESS){
            $status ="success";
        }elseif($status == PaymentGatewayConst::STATUSPENDING){
            $status ="Pending";
        }elseif($status == PaymentGatewayConst::STATUSHOLD){
            $status ="Hold";
        }elseif($status == PaymentGatewayConst::STATUSWAITING){
            $status ="Waiting";
        }elseif($status == PaymentGatewayConst::STATUSPROCESSING){
            $status ="Processing";
        }elseif($status == PaymentGatewayConst::STATUSFAILD){
            $status ="Failed";
        }

        $notification_content = [
            //email notification
            'subject' =>__("Withdraw Money")." (".userGuard()['type'].")",
            'greeting' =>__("Withdraw Money Via")." ".$data->gateway_name.' ('.$data->gateway_type.' )',
            'email_content' =>__("TRX ID")." : ".$data->trx_id."<br>".__("Request Amount")." : ".get_amount($data->amount,get_default_currency_code())."<br>".__("Exchange Rate")." : ". $exchange_rate."<br>".__("Fees & Charges")." : ". get_amount($data->gateway_charge,$data->gateway_currency)."<br>".__("Total Payable Amount")." : ".get_amount($data->payable,get_default_currency_code())."<br>".__("Will Get")." : ".get_amount($data->will_get,$data->gateway_currency,2)."<br>".__("Status")." : ".__($status),
            //push notification
            'push_title' =>  __("Withdraw Money")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." ".$data->trx_id." ". __("Withdraw Money").' '.get_amount($data->amount,get_default_currency_code()).' '.__('By').' '.$data->gateway_name.' ('.$user->username.')',

            //admin db notification
            'notification_type' =>  NotificationConst::WITHDRAWMONEY,
            'trx_id' => $data->trx_id,
            'admin_db_title' =>  "Withdraw Money"." (".userGuard()['type'].")",
            'admin_db_message' =>  "Withdraw Money".' '.get_amount($data->amount,get_default_currency_code()).' '.'By'.' '.$data->gateway_name.' ('.$user->username.')'
        ];

        try{
            //notification
            (new NotificationHelper())->admin(['admin.money.out.index','admin.money.out.pending','admin.money.out.complete','admin.money.out.canceled','admin.money.out.details','admin.money.out.approved','admin.money.out.rejected','admin.money.out.export.data'])
                                    ->mail(ActivityNotification::class, [
                                        'subject'   => $notification_content['subject'],
                                        'greeting'  => $notification_content['greeting'],
                                        'content'   => $notification_content['email_content'],
                                    ])
                                    ->push([
                                        'user_type' => "admin",
                                        'title' => $notification_content['push_title'],
                                        'desc'  => $notification_content['push_content'],
                                    ])
                                    ->adminDbContent([
                                        'type' => $notification_content['notification_type'],
                                        'title' => $notification_content['admin_db_title'],
                                        'message'  => $notification_content['admin_db_message'],
                                    ])
                                    ->send();


        }catch(Exception $e) {}

    }
}
