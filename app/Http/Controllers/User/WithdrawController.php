<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\PaymentGatewayCurrency;
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

    public function index()
    {
        $page_title = __("Withdraw Money");
        $user_wallets = UserWallet::auth()->get();
        $user_currencies = Currency::whereIn('id',$user_wallets->pluck('id')->toArray())->get();
        $payment_gateways = PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::money_out_slug());
            $gateway->where('status', 1);
        })->get();
        $transactions = Transaction::auth()->withdrawMoney()->orderByDesc("id")->latest()->take(10)->get();
        return view('user.sections.withdraw.index',compact('page_title','payment_gateways','transactions','user_wallets'));
    }
    public function paymentInsert(Request $request){
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'gateway' => 'required'
        ]);
        $user = auth()->user();
        $userWallet = UserWallet::where('user_id',$user->id)->where('status',1)->first();
        if(!$userWallet){
            return back()->with(['error' => [__('User wallet not found')]]);
        }
        $gate =PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::money_out_slug());
            $gateway->where('status', 1);
        })->where('alias',$request->gateway)->first();
        if (!$gate) {
            return back()->with(['error' => [__("Invalid Gateway!")]]);
        }
        $baseCurrency = Currency::default();
        if (!$baseCurrency) {
            return back()->with(['error' => [__('Default Currency Not Setup Yet')]]);
        }

        $amount = $request->amount;
        $min_limit =  $gate->min_limit / $gate->rate;
        $max_limit =  $gate->max_limit / $gate->rate;
        if($amount < $min_limit || $amount > $max_limit) {
            return back()->with(['error' => [__('Please follow the transaction limit')]]);
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
            return back()->with(['error' => [__('Sorry, insufficient balance')]]);
        }
        $data['user_id']= $user->id;
        $data['gateway_name']= $gate->gateway->name;
        $data['gateway_type']= $gate->gateway->type;
        $data['wallet_id']= $userWallet->id;
        $data['trx_id']= 'WM'.getTrxNum();
        $data['amount'] =  $amount;
        $data['base_cur_fixed_charge'] = $baseFixedCharge;
        $data['base_cur_percent_charge'] = $basePercent_charge;
        $data['base_cur_charge'] = $base_total_charge;
        $data['base_cur_rate'] = $baseCurrency->rate;
        $data['gateway_id'] = $gate->gateway->id;
        $data['gateway_currency_id'] = $gate->id;
        $data['gateway_currency'] = strtoupper($gate->currency_code);
        $data['gateway_percent_charge'] = $percent_charge;
        $data['gateway_fixed_charge'] = $fixedCharge;
        $data['gateway_charge'] = $charge;
        $data['gateway_rate'] = $gate->rate;
        $data['conversion_amount'] = $conversion_amount;
        $data['will_get'] = $will_get;
        $data['payable'] = $reduceAbleTotal;
        session()->put('moneyoutData', $data);
        return redirect()->route('user.withdraw.preview');
   }

   public function preview(){
        $moneyOutData = (object)session()->get('moneyoutData');
        $moneyOutDataExist = session()->get('moneyoutData');
        if($moneyOutDataExist  == null){
            return redirect()->route('user.withdraw.index');
        }
        $gateway = PaymentGateway::where('id', $moneyOutData->gateway_id)->first();
        if($gateway->type == "AUTOMATIC"){
            $page_title = __("Withdraw Via")." ".$gateway->name;
            return back()->with(['error' => [__("Something Went Wrong! Please Try Again")]]);
        }else{
            $page_title = __("Withdraw Via")." ".$gateway->name;
            return view('user.sections.withdraw.preview',compact('page_title','gateway','moneyOutData'));

        }

   }

   public function confirmMoneyOut(Request $request){
    $user = auth()->user();
    $moneyOutData = (object)session()->get('moneyoutData');
    $gateway = PaymentGateway::where('id', $moneyOutData->gateway_id)->first();
    $payment_fields = $gateway->input_fields ?? [];

    $validation_rules = $this->generateValidationRules($payment_fields);
    $payment_field_validate = Validator::make($request->all(),$validation_rules)->validate();
    $get_values = $this->placeValueWithFields($payment_fields,$payment_field_validate);
        try{
            $inserted_id = $this->insertRecordManual($moneyOutData,$gateway,$get_values);
            $this->insertChargesManual($moneyOutData,$inserted_id);
            $this->adminNotification($moneyOutData,PaymentGatewayConst::STATUSPENDING);
            $this->insertDeviceManual($moneyOutData,$inserted_id);
            try{
                if( $this->basic_settings->email_notification == true){
                    $user->notify(new WithdrawMail($user,$moneyOutData));
                }
            }catch(Exception $e){}
            session()->forget('moneyoutData');

            return redirect()->route("user.withdraw.index")->with(['success' => [__('Withdraw Money Request Send To Admin Successful')]]);
        }catch(Exception $e) {
            dd($e);
            return back()->with(['error' => [__("Something Went Wrong! Please Try Again")]]);
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
            throw new Exception(__("Something Went Wrong! Please Try Again"));
        }
        return $id;
    }
    public function updateWalletBalanceManual($authWallet,$afterCharge) {
        $authWallet->update([
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
            throw new Exception(__("Something Went Wrong! Please Try Again"));
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
            throw new Exception(__("Something Went Wrong! Please Try Again"));
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
            (new NotificationHelper())->admin(['admin.money.out.index','admin.money.out.pending','admin.money.out.complete','admin.money.out.canceled','admin.money.out.details','admin.money.out.approved','admin.money.out.rejected'])
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
