<?php

namespace App\Http\Controllers\Api\User;

use App\Constants\GlobalConst;
use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Api\Helpers;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\SudoVirtualCard;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Models\VirtualCardApi;
use App\Notifications\Admin\ActivityNotification;
use App\Notifications\User\VirtualCard\CreateMail;
use App\Notifications\User\VirtualCard\Fund;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SudoVirtualCardController extends Controller
{
    protected $api;
    protected $card_limit;
    protected $basic_settings;
    public function __construct()
    {
        $cardApi = VirtualCardApi::first();
        $this->api =  $cardApi;
        $this->card_limit =  $cardApi->card_limit;
        $this->basic_settings = BasicSettingsProvider::get();
    }

    public function index()
    {
        $user = auth()->user();
        $basic_settings = BasicSettings::first();
        $card_basic_info = [
            'card_create_limit' => @$this->api->card_limit,
            'card_back_details' => @$this->api->card_details,
            'card_bg'           => get_image(@$this->api->image,'card-api'),
            'site_title'        => @$basic_settings->site_name,
            'site_logo'         => get_logo(@$basic_settings,'dark'),
            'site_fav'          => get_fav($basic_settings,'dark'),
        ];
        $myCards = SudoVirtualCard::where('user_id',$user->id)->orderBy('id','DESC')->get()->map(function($data){
            $basic_settings = BasicSettings::first();
            $live_card_data = getSudoCard($data->card_id);
            $statusInfo = [
                "block" =>      0,
                "unblock" =>     1,
                ];
            return[
                'id' => $data->id,
                'card_id' => $data->card_id,
                'amount' => getAmount(updateSudoCardBalance(auth()->user(),$data->card_id,$live_card_data),2),
                'currency' => $data->currency,
                'card_holder' => $data->name,
                'brand' => $data->brand,
                'type' => $data->type,
                'card_pan' => $data->maskedPan,
                'expiry_month' => $data->expiryMonth,
                'expiry_year' => $data->expiryYear,
                'cvv' => "***",
                'card_back_details' => @$this->api->card_details,
                'site_title' =>@$basic_settings->site_name,
                'site_logo' =>get_logo(@$basic_settings,'dark'),
                'site_fav' =>get_fav($basic_settings,'dark'),
                'status' => $data->status,
                'is_default' => $data->is_default,
                'status_info' =>(object)$statusInfo ,
                'card_api_mode' => $this->api->config->sudo_mode,
            ];
        });
        $totalCards = SudoVirtualCard::where('user_id',auth()->user()->id)->count();
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){

            return [
                'id' => $data->id,
                'slug' => $data->slug,
                'title' => $data->title,
                'fixed_charge' => getAmount($data->fixed_charge,2),
                'percent_charge' => getAmount($data->percent_charge,2),
                'min_limit' => getAmount($data->min_limit,2),
                'max_limit' => getAmount($data->max_limit,2),
            ];
        })->first();
        $transactions = Transaction::auth()->virtualCard()->latest()->take(10)->get()->map(function($item){
            $statusInfo = [
                "success" =>      1,
                "pending" =>      2,
                "rejected" =>     3,
                ];
            return[
                'id' => $item->id,
                'trx' => $item->trx_id,
                'transaction_type' => __("Virtual Card").'('. @$item->remark.')',
                'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                'payable' => getAmount($item->payable,2).' '.get_default_currency_code(),
                'total_charge' => getAmount($item->charge->total_charge,2).' '.get_default_currency_code(),
                'card_amount' => getAmount(@$item->details->card_info->amount,2).' '.get_default_currency_code(),
                'card_number' => $item->details->card_info->card_pan??$item->details->card_info->maskedPan??$item->details->card_info->card_number??"",
                'current_balance' => getAmount($item->available_balance,2).' '.get_default_currency_code(),
                'status' => $item->stringStatus->value ,
                'date_time' => $item->created_at ,
                'status_info' =>(object)$statusInfo ,

            ];
        });
        $userWallet = UserWallet::where('user_id',$user->id)->get()->map(function($data){
            return[
                'balance' => getAmount($data->balance,2),
                'currency' => get_default_currency_code(),
            ];
        })->first();

        $data =[
            'base_curr' => get_default_currency_code(),
            'card_create_action' => $totalCards <  $this->card_limit ? true : false,
            'card_basic_info' =>(object) $card_basic_info,
            'myCard'=> $myCards,
            'userWallet'=>  (object)$userWallet,
            'cardCharge'=>(object)$cardCharge,
            'transactions'   => $transactions,
            ];
            $message =  ['success'=>[__('Virtual Card Sudo')]];
            return Helpers::success($data,$message);
    }
    public function charges(){
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){
            return [
                'id' => $data->id,
                'slug' => $data->slug,
                'title' => $data->title,
                'fixed_charge' => getAmount($data->fixed_charge,2),
                'percent_charge' => getAmount($data->percent_charge,2),
                'min_limit' => getAmount($data->min_limit,2),
                'max_limit' => getAmount($data->max_limit,2),
            ];
        })->first();

        $data =[
            'base_curr' => get_default_currency_code(),
            'cardCharge'=>(object)$cardCharge,
            ];
            $message =  ['success'=>[__('Fess & Charges')]];
            return Helpers::success($data,$message);
    }
    public function cardDetails(){
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = request()->card_id;
        $user = auth()->user();
        $myCard = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$myCard){
            $error = ['error'=>[__('Something Is Wrong In Your Card')]];
            return Helpers::error($error);
        }
        $myCards = SudoVirtualCard::where('card_id',$card_id)->where('user_id',$user->id)->get()->map(function($data){
            $basic_settings = BasicSettings::first();
            $statusInfo = [
                "block" =>      0,
                "unblock" =>     1,
                ];

            return[
                'id' => $data->id,
                'card_id' => $data->card_id,
                'amount' => getAmount($data->amount,2),
                'currency' => $data->currency,
                'card_holder' => $data->name,
                'brand' => $data->brand,
                'type' => $data->type,
                'card_pan' => $data->maskedPan,
                'expiry_month' => $data->expiryMonth,
                'expiry_year' => $data->expiryYear,
                'cvv' => "***",
                'card_back_details' => @$this->api->card_details,
                'site_title' =>@$basic_settings->site_name,
                'site_logo' =>get_logo(@$basic_settings,'dark'),
                'site_fav' =>get_fav($basic_settings,'dark'),
                'status' => $data->status,
                'is_default' => $data->is_default,
                'status_info' =>(object)$statusInfo ,
                'card_api_mode' => $this->api->config->sudo_mode,
            ];
        })->first();

        $cardToken = getCardToken($this->api->config->sudo_api_key,$this->api->config->sudo_url,$myCard->card_id);
        if($cardToken['statusCode'] == 200){
            $cardToken = $cardToken['data']['token'];
        }else{
            $cardToken = '';
        }
        $card_secure_date =[
            'api_mode' =>  $this->api->config->sudo_mode,
            'api_vault_id' =>  $this->api->config->sudo_vault_id,
            'card_token' =>  $cardToken,
        ];
        $data =[
            'base_curr' => get_default_currency_code(),
            'card_secure_date'=> (object)$card_secure_date,
            'card_details'=> $myCards,
            ];
            $message =  ['success'=>[__('Virtual Card Details')]];
            return Helpers::success($data,$message);
    }
    public function cardTransaction() {
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = request()->card_id;
        $user = auth()->user();
        $card = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>[__('Something Is Wrong In Your Card')]];
            return Helpers::error($error);
        }
        $card_truns =  getCardTransactions($this->api->config->sudo_api_key,$this->api->config->sudo_url,$card->card_id);
        $data = [
            'cardTransactions' => $card_truns
        ];

        $message = ['success' => [__('Virtual Card Transactions')]];
        return Helpers::success($data, $message);


    }
    public function makeDefaultOrRemove(Request $request) {
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $validated = $validator->validate();
        $user = auth()->user();
        $targetCard =  SudoVirtualCard::where('card_id',$validated['card_id'])->where('user_id',$user->id)->first();
        if(!$targetCard){
            $error = ['error'=>[__('Something Is Wrong In Your Card')]];
            return Helpers::error($error);
        };
        $withOutTargetCards =  SudoVirtualCard::where('id','!=',$targetCard->id)->where('user_id',$user->id)->get();
        try{
            $targetCard->update([
                'is_default'         => $targetCard->is_default ? 0 : 1,
            ]);
            if(isset(  $withOutTargetCards)){
                foreach(  $withOutTargetCards as $card){
                    $card->is_default = false;
                    $card->save();
                }
            }
            $message =  ['success'=>[__('Status Updated Successfully')]];
            return Helpers::onlysuccess($message);

        }catch(Exception $e) {
            $error = ['error'=>[__('Something Went Wrong! Please Try Again')]];
            return Helpers::error($error);
        }
    }
    public function cardBlock(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = $request->card_id;
        $user = auth()->user();
        $status = 'inactive';
        $card = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>[__('Something Is Wrong In Your Card')]];
            return Helpers::error($error);
        }
        if($card->status == false){
            $error = ['error'=>[__('Sorry,This Card Is Already Inactive')]];
            return Helpers::error($error);
        }
        $result = cardUpdate($this->api->config->sudo_api_key,$this->api->config->sudo_url,$card->card_id,$status);
        if(isset($result['statusCode'])){
            if($result['statusCode'] == 200){
                $card->status = false;
                $card->save();
                $message =  ['success'=>[__('Card Inactive Successfully!')]];
                return Helpers::onlysuccess($message);
            }elseif($result['statusCode'] != 200){
                $error = ['error'=>[$result['message']??"Something Is Wrong"]];
                return Helpers::error($error);
            }
        }

    }
    public function cardUnBlock(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = $request->card_id;
        $user = auth()->user();
        $status = 'active';
        $card = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
          $error = ['error'=>[__('Something Is Wrong In Your Card')]];
            return Helpers::error($error);
        }
        if($card->status == true){
            $error = ['error'=>['Sorry,This Card Is Already Active']];
            return Helpers::error($error);
        }
        $result = cardUpdate($this->api->config->sudo_api_key,$this->api->config->sudo_url,$card->card_id,$status);
        if(isset($result['statusCode'])){
            if($result['statusCode'] == 200){
                $card->status = true;
                $card->save();
                $message =  ['success'=>[__('Card Active Successfully!')]];
                return Helpers::onlysuccess($message);
            }elseif($result['statusCode'] != 200){
                $error = ['error'=>[$result['message']??"Something Is Wrong"]];
                return Helpers::error($error);
            }
        }

    }
    public function cardBuy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_amount' => 'required|numeric|gt:0',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $totalCards = SudoVirtualCard::where('user_id',auth()->user()->id)->count();
        if($totalCards >= $this->card_limit){
            $error = ['error'=>[__("Sorry! You can not create more than")." ".$this->card_limit ." ".__("card using the same email address.")]];
            return Helpers::error($error);
        }

        $user = auth()->user();
        $amount = (float) $request->card_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            $error = ['error'=>[__('User wallet not found')]];
            return Helpers::error($error);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            $error = ['error'=>[__('Default Currency Not Setup Yet')]];
            return Helpers::error($error);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            $error = ['error'=>[__('Please follow the transaction limit')]];
            return Helpers::error($error);
        }
        //charge calculations
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            $error = ['error'=>[__('Sorry, insufficient balance')]];
            return Helpers::error($error);
        }
        $currency = $baseCurrency->code;
        $funding_sources =  get_funding_source( $this->api->config->sudo_api_key,$this->api->config->sudo_url);
        if(isset( $funding_sources['statusCode'])){
            if($funding_sources['statusCode'] == 403){
                $error = ['error'=>[$funding_sources['message']]];
                return Helpers::error($error);
            }elseif($funding_sources['statusCode'] == 404){
                $error = ['error'=>[$funding_sources['message']]];
                return Helpers::error($error);
            }
        }
        $supported_currency = ['USD','NGN'];
        if( !in_array($currency,$supported_currency??[])){
            $error = ['error'=>[$currency." ". __("Currency doesn't supported for creating virtual card, Please contact")]];
            return Helpers::error($error);

        }
        $account_type = 'default';
        $accountTypeArray = array_filter($funding_sources['data'], function($item) use ($account_type) {
            return $item['type'] === $account_type;
        });
        if( count($accountTypeArray) <=  0){
            $create_founding_source = funding_source_create( $this->api->config->sudo_api_key,$this->api->config->sudo_url);
            if($create_founding_source['status'] ===  false){
                $error = ['error'=>[__($create_founding_source['message'])]];
                return Helpers::error($error);
            }
            $bankCode = $create_founding_source['data']['_id']??'';
        }else{
            $funding_source_id =  array_values($accountTypeArray);
            $bankCode = $funding_source_id[0]['_id']??'';
        }

        $sudo_accounts =    get_sudo_accounts( $this->api->config->sudo_api_key,$this->api->config->sudo_url);
        $filteredArray = array_filter($sudo_accounts, function($item) use ($currency) {
            return $item['currency'] === $currency;
        });
        $matchingElements = array_values($filteredArray);
        $debitAccountId= $matchingElements[0]['_id']??"";

        if(  $debitAccountId == ""){
            //create debit account
            if( $user->sudo_account == null){
                //create account
                $store_account = create_sudo_account($this->api->config->sudo_api_key,$this->api->config->sudo_url, $currency);
                if( isset($store_account['error'])){
                    $error = ['error'=>[__("Haven't any debit account this currency, Please contact with owner")]];
                    return Helpers::error($error);
                }
                $user->sudo_account =   (object)$store_account['data'];
                $user->save();

            }
        }else{
            $user->sudo_account = (object)$matchingElements[0];
            $user->save();
        }
        $debitAccountId = $user->sudo_account->_id??'';

        $issuerCountry = '';
        if(get_default_currency_code() == "NGN"){
            $issuerCountry = "NGA";
        }elseif(get_default_currency_code() === "USD"){
            $issuerCountry = "USA";
        }

        //check sudo customer have or not
       if( $user->sudo_customer == null){
        //create customer
        $store_customer = create_sudo_customer($this->api->config->sudo_api_key,$this->api->config->sudo_url,$user);

        if( isset($store_customer['error'])){
            $error = ['error'=>[__("Customer doesn't created properly,Contact with owner")]];
            return Helpers::error($error);
        }
        $user->sudo_customer =   (object)$store_customer['data'];
        $user->save();
        $customerId = $user->sudo_customer->_id;

       }else{
        $customerId = $user->sudo_customer->_id;
       }
       //create card now
       $created_card = create_virtual_card($amount,$this->api->config->sudo_api_key,$this->api->config->sudo_url,
                            $customerId, $currency,$bankCode, $debitAccountId, $issuerCountry
                        );
       if(isset($created_card['statusCode'])){
        if($created_card['statusCode'] == 400){
            $error = ['error'=>[$created_card['message']]];
            return Helpers::error($error);
        }

       }
       if($created_card['statusCode']  = 200){
            $card_info = (object)$created_card['data'];
            $v_card = new SudoVirtualCard();
            $v_card->user_id = $user->id;
            $v_card->name = $user->fullname;
            $v_card->card_id = $card_info->_id;
            $v_card->business_id = $card_info->business;
            $v_card->customer = $card_info->customer;
            $v_card->account = $card_info->account;
            $v_card->fundingSource = $card_info->fundingSource;
            $v_card->type = $card_info->type;
            $v_card->brand = $card_info->brand;
            $v_card->currency = $card_info->currency;
            if($this->api->config->sudo_mode === GlobalConst::SANDBOX){
                $v_card->amount = $card_info->balance;
            }elseif($this->api->config->sudo_mode === GlobalConst::LIVE){
                $v_card->amount = $amount;
            }
            $v_card->charge = $total_charge;
            $v_card->maskedPan = $card_info->maskedPan;
            $v_card->last4 = $card_info->last4;
            $v_card->expiryMonth = $card_info->expiryMonth;
            $v_card->expiryYear = $card_info->expiryYear;
            $v_card->status = true;
            $v_card->isDeleted = $card_info->isDeleted;
            $v_card->billingAddress = $card_info->billingAddress;
            $v_card->save();

            $trx_id =  'CB'.getTrxNum();
            try{
                $sender = $this->insertCadrBuy( $trx_id,$user,$wallet,$amount, $v_card ,$payable);
                $this->insertBuyCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$v_card->maskedPan);
                if( $this->basic_settings->email_notification == true){
                    $notifyDataSender = [
                        'trx_id'  => $trx_id,
                        'title'  => __("Virtual Card (Buy Card)"),
                        'request_amount'  => getAmount($amount,4).' '.get_default_currency_code(),
                        'payable'   =>  getAmount($payable,4).' ' .get_default_currency_code(),
                        'charges'   => getAmount( $total_charge, 2).' ' .get_default_currency_code(),
                        'card_amount'  => getAmount( $v_card->amount, 2).' ' .get_default_currency_code(),
                        'card_pan'  => $v_card->maskedPan,
                        'status'  => __("Success"),
                    ];
                    try{
                        $user->notify(new CreateMail($user,(object)$notifyDataSender));
                    }catch(Exception $e){}
                }
                 //admin notification
                 $this->adminNotification($trx_id,$total_charge,$amount,$payable,$user,$v_card);
                $message =  ['success'=>[__('Buy Card Successfully')]];
                return Helpers::onlysuccess($message);
            }catch(Exception $e){
                $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
                return Helpers::error($error);
            }

       }

    }
    //card buy helper
    public function insertCadrBuy( $trx_id,$user,$wallet,$amount, $v_card ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $v_card??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::VIRTUALCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::CARDBUY," ")),
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
        return $id;
    }
    public function insertBuyCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$masked_card) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
             $notification_content = [
                'title'         =>"Buy Card",
                'message'       => 'Buy card successful '.$masked_card,
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_BUY,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
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

    public function cardFundConfirm(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'fund_amount' => 'required|numeric|gt:0',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $user = auth()->user();
        $myCard =  SudoVirtualCard::where('user_id',$user->id)->where('card_id',$request->card_id)->first();

        if(!$myCard){
            $error = ['error'=>[__('Something is wrong in your card')]];
            return Helpers::error($error);
        }
        $amount = $request->fund_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            $error = ['error'=>[__('User wallet not found')]];
            return Helpers::error($error);
        }
        $cardCharge = TransactionSetting::where('slug','reload_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            $error = ['error'=>[__('Default currency not found')]];
            return Helpers::error($error);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            $error = ['error'=>[__("Please follow the transaction limit")]];
            return Helpers::error($error);
        }
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            $error = ['error'=>[__('Sorry, insufficient balance')]];
            return Helpers::error($error);
        }
        $get_card_details =  getSudoCard($myCard->card_id);
        if($get_card_details['status'] === false){
            $error = ['error'=>[__('Something is wrong in your card')]];
            return Helpers::error($error);
        }
        $card_account_number =  $get_card_details['data']['account']['_id'];
        $card_fund_response = sudoFundCard( $card_account_number,(float)$amount);
        if(!empty($card_fund_response['status'])  && $card_fund_response['status'] === true){
            //added fund amount to card
            $myCard->amount += $amount;
            $myCard->save();
            $trx_id = 'CF'.getTrxNum();
            $sender = $this->insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable);
            $this->insertFundCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$myCard->maskedPan,$amount);
            if($this->basic_settings->email_notification == true){
                $notifyDataSender = [
                    'trx_id'  => $trx_id,
                     'title'  => __("Virtual Card (Fund Amount)"),
                    'request_amount'  => getAmount($amount,4).' '.get_default_currency_code(),
                    'payable'   =>  getAmount($payable,4).' ' .get_default_currency_code(),
                    'charges'   => getAmount( $total_charge,2).' ' .get_default_currency_code(),
                    'card_amount'  => getAmount($myCard->amount,2).' ' .get_default_currency_code(),
                    'card_pan'  =>    $myCard->maskedPan,
                    'status'  => __("Success"),
                ];
                try{
                    $user->notify(new Fund($user,(object)$notifyDataSender));
                }catch(Exception $e){}
            }
            $message =  ['success'=>[__('Card Funded Successfully')]];
            return Helpers::onlysuccess($message);

        }else{
            $error = ['error'=>[@$card_fund_response['message'].' ,'.__('Please Contact With Administration.')]];
            return Helpers::error($error);
        }
    }
    //card fund helper
    public function insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $myCard??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::VIRTUALCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::CARDFUND," ")),
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $error = ['error'=>[__("Something Went Wrong! Please Try Again")]];
            return Helpers::error($error);
        }
        return $id;
    }
    public function insertFundCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$masked_card,$amount) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>"Card Fund",
                'message'       => __("Card fund successful card")." : ".$masked_card.' '.getAmount($amount,2).' '.get_default_currency_code(),
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_FUND,
                'user_id'  => $user->id,
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
    //update user balance
    public function updateSenderWalletBalance($authWallet,$afterCharge) {
        $authWallet->update([
            'balance'   => $afterCharge,
        ]);
    }
    //admin notification
    public function adminNotification($trx_id,$total_charge,$amount,$payable,$user,$v_card){
        $notification_content = [
            //email notification
            'subject' => __("Virtual Card (Buy Card)"),
            'greeting' => __("Virtual Card Information"),
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("Card Masked")." : ".@$v_card->maskedPan."<br>".__("Status")." : ".__("Success"),

            //push notification
            'push_title' => __("Virtual Card (Buy Card)")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." : ".$trx_id." ".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("Card Masked")." : ".$v_card->maskedPan??"",

            //admin db notification
            'notification_type' =>  NotificationConst::CARD_BUY,
            'admin_db_title' => "Virtual Card Buy"." (".userGuard()['type'].")",
            'admin_db_message' => "Transaction ID"." : ".$trx_id.",".__("Request Amount")." : ".get_amount($amount,get_default_currency_code()).","."Card Masked"." : ".@$v_card->maskedPan." (".$user->email.")",
        ];

        try{
            //notification
            (new NotificationHelper())->admin(['admin.virtual.card.logs'])
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
    public function adminNotificationFund($trx_id,$total_charge,$amount,$payable,$user,$myCard){
        $notification_content = [
            //email notification
            'subject' => __("Virtual Card (Fund Amount)"),
            'greeting' => __("Virtual Card Information"),
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("Card Masked")." : ".$myCard->maskedPan??""."<br>".__("Status")." : ".__("Success"),

            //push notification
            'push_title' => __("Virtual Card (Fund Amount)")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." : ".$trx_id." ".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("Card Masked")." : ".$myCard->maskedPan??"",

            //admin db notification
            'notification_type' =>  NotificationConst::CARD_FUND,
            'admin_db_title' => "Virtual Card Funded"." (".userGuard()['type'].")",
            'admin_db_message' => "Transaction ID"." : ".$trx_id.",".__("Request Amount")." : ".get_amount($amount,get_default_currency_code()).","."Card Masked"." : ".$myCard->maskedPan." (".$user->email.")",
        ];

        try{
            //notification
            (new NotificationHelper())->admin(['admin.virtual.card.logs'])
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
