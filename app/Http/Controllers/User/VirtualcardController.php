<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PushNotificationHelper;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Models\VirtualCard;
use App\Models\VirtualCardApi;
use App\Notifications\Admin\ActivityNotification;
use App\Notifications\User\VirtualCard\CreateMail;
use App\Notifications\User\VirtualCard\Fund;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VirtualcardController extends Controller

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
        $page_title = __("Virtual Card");
        $myCards = VirtualCard::where('user_id',auth()->user()->id)->get();
        $totalCards = VirtualCard::where('user_id',auth()->user()->id)->count();
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $cardReloadCharge = TransactionSetting::where('slug','reload_card')->where('status',1)->first();
        $transactions = Transaction::auth()->virtualCard()->latest()->take(10)->get();
        $cardApi = $this->api;
        return view('user.sections.virtual-card.index',compact('page_title','myCards','transactions','cardCharge','cardApi','totalCards','cardReloadCharge'));
    }
    public function cardDetails($card_id)
    {
        $page_title = __("Card Details");
        $myCard = VirtualCard::where('card_id',$card_id)->first();
        $cardApi = $this->api;
        return view('user.sections.virtual-card.details',compact('page_title','myCard','cardApi'));
    }

    public function cardBuy(Request $request)
    {
        $request->validate([
            'card_amount' => 'required|numeric|gt:0',
        ]);

        $user = auth()->user();
        $amount = $request->card_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            return back()->with(['error' => [__('User wallet not found')]]);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return back()->with(['error' => [__('Default Currency Not Setup Yet')]]);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return back()->with(['error' => [__('Please follow the transaction limit')]]);
        }
        //charge calculations
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            return back()->with(['error' => [__('Sorry, insufficient balance')]]);
        }
        $currency =$baseCurrency->code;
        $tempId = 'tempId-'. $user->id . time() . rand(6, 100);
        $trx = 'VC-' . time() . rand(6, 100);

        $callBack = route('user.virtual.card.flutterWave.callBack').'?c_user_id='.$user->id.'&c_amount='.  $amount.'&c_temp_id='.$tempId.'&c_trx='.$trx;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL =>  $this->api->config->flutterwave_url.'/virtual-cards',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_POSTFIELDS => "{\n    \"currency\": \"$currency\",\n    \"amount\":  $amount,\n    \"billing_name\": \"$user->name\",\n   \"callback_url\": \"$callBack/\"\n}",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer " .$this->api->config->flutterwave_secret_key
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response, true);
        if (isset($result)){
            if ( $result['status'] === 'success' && array_key_exists('data', $result) ) {
                $values = $result['data'];
                $k = array_rand($values);
                $result = (object) $values[$k];
                //Save Card
                $v_card = new VirtualCard();
                $v_card->user_id = $user->id;
                $v_card->card_id = $result->id;
                $v_card->name = $user->fullname;
                $v_card->account_id = $result->account_id;
                $v_card->card_hash = $result->card_hash;
                $v_card->card_pan = $result->card_pan;
                $v_card->masked_card = $result->masked_pan;
                $v_card->cvv = $result->cvv;
                $v_card->expiration = $result->expiration;
                $v_card->card_type = $result->card_type;
                $v_card->name_on_card = $result->name_on_card;
                $v_card->callback = $result->callback_url;
                $v_card->ref_id = $trx;
                $v_card->secret = $trx;
                $v_card->bg = "DeepBlue";
                $v_card->city = $result->city;
                $v_card->state = $result->state;
                $v_card->zip_code = $result->zip_code;
                $v_card->address = $result->address_1;
                $v_card->amount =  $amount;
                $v_card->currency = $currency;
                $v_card->charge =  $total_charge;
                if ($result->is_active) {
                    $v_card->is_active = 1;
                } else {
                    $v_card->is_active = 0;
                }
                $v_card->funding = 1;
                $v_card->terminate = 0;
                $v_card->save();

              $trx_id =  'CB'.getTrxNum();
              $sender = $this->insertCadrBuy( $trx_id,$user,$wallet,$amount, $v_card ,$payable);
              $this->insertBuyCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$v_card->masked_card);
              //sender notifications
              if( $this->basic_settings->email_notification == true){
                $notifyDataSender = [
                    'trx_id'  => $trx_id,
                    'title'  => __("Virtual Card (Buy Card)"),
                    'request_amount'  => getAmount($amount,4).' '.get_default_currency_code(),
                    'payable'   =>  getAmount($payable,4).' ' .get_default_currency_code(),
                    'charges'   => getAmount( $total_charge, 2).' ' .get_default_currency_code(),
                    'card_amount'  => getAmount( $v_card->amount, 2).' ' .get_default_currency_code(),
                    'card_pan'  => $v_card->card_pan,
                    'status'  => __("Success"),
                ];
                try{
                    $user->notify(new CreateMail($user,(object)$notifyDataSender));
                }catch(Exception $e){}
            }
            //admin notification
            $this->adminNotification($trx_id,$total_charge,$amount,$payable,$user,$v_card);
                return redirect()->route("user.virtual.card.index")->with(['success' => [__('Buy Card Successfully')]]);
            }else {
                return redirect()->back()->with(['error' => [@$result['message']??__("Something Went Wrong! Please Try Again")]]);
            }
        }

    }
    public function cardFundConfirm(Request $request){
        $request->validate([
            'id' => 'required|integer',
            'fund_amount' => 'required|numeric|gt:0',
        ]);
        $user = auth()->user();
        $myCard =  VirtualCard::where('user_id',$user->id)->where('id',$request->id)->first();

        if(!$myCard){
            return back()->with(['error' => [__('Something Is Wrong In Your Card')]]);
        }

        $amount = $request->fund_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            return back()->with(['error' => [__('User wallet not found')]]);
        }
        $cardCharge = TransactionSetting::where('slug','reload_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return back()->with(['error' => [__('Default Currency Not Setup Yet')]]);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return back()->with(['error' => [__('Please follow the transaction limit')]]);
        }
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            return back()->with(['error' => [__('Sorry, insufficient balance')]]);
        }
        $currency =$baseCurrency->code;
        $tempId = 'tempId-'. $user->id . time() . rand(6, 100);
        $trx = 'VC-' . time() . rand(6, 100);
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL =>   $this->api->config->flutterwave_url."/"."virtual-cards/".$myCard->card_id."/fund",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS =>"{\n    \"debit_currency\": \"$currency\",\n    \"amount\": $amount\n}",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "Authorization: Bearer " .$this->api->config->flutterwave_secret_key
        ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response);
        if(!empty($result->status)  && $result->status == "success"){
            //added fund amount to card
            $myCard->amount += $amount;
            $myCard->save();
            $trx_id = 'CF'.getTrxNum();
            $sender = $this->insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable);
            $this->insertFundCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$myCard->masked_card,$amount);
            if($this->basic_settings->email_notification == true){
                $notifyDataSender = [
                    'trx_id'  => $trx_id,
                    'title'  => __("Virtual Card (Fund Amount)"),
                    'request_amount'  => getAmount($amount,4).' '.get_default_currency_code(),
                    'payable'   =>  getAmount($payable,4).' ' .get_default_currency_code(),
                    'charges'   => getAmount( $total_charge,2).' ' .get_default_currency_code(),
                    'card_amount'  => getAmount($myCard->amount,2).' ' .get_default_currency_code(),
                    'card_pan'  =>    $myCard->masked_card,
                    'status'  => __("Success"),
                ];
                try{
                    $user->notify(new Fund($user,(object)$notifyDataSender));
                }catch(Exception $e){}
            }
            //admin notification
            $this->adminNotificationFund($trx_id,$total_charge,$amount,$payable,$user,$myCard);
            return redirect()->back()->with(['success' => [__('Card Funded Successfully')]]);

        }else{
            return redirect()->back()->with(['error' => [@$result->message??__("Something Went Wrong! Please Try Again")]]);
        }

    }

    public function cardBlockUnBlock(Request $request) {
        $validator = Validator::make($request->all(),[
            'status'                    => 'required|boolean',
            'data_target'               => 'required|string',
        ]);
        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }
        $validated = $validator->safe()->all();
        if($request->status == 1 ){
            $card = VirtualCard::where('id',$request->data_target)->where('is_active',1)->first();
            $status = 'block';
            if(!$card){
                $error = ['error' => [__('Something Is Wrong In Your Card')]];
                return Response::error($error,null,404);
            }
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL =>  $this->api->config->flutterwave_url.'/'."virtual-cards/".$card->card_id."/status/".$status,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " .$this->api->config->flutterwave_secret_key
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            $result = json_decode($response, true);
            if (isset($result)) {
                if ($result['status'] === 'success' && array_key_exists('data', $result)) {
                    $card->is_active = 0;
                    $card->save();
                    $success = ['success' => [__('Card block successfully!')]];
                    return Response::success($success,null,200);
                } elseif ($result['status'] === 'error' && $result['message'] === 'Card has been blocked previously') {
                    $card->is_active = 0;
                    $card->save();
                    $error = ['error' => [__('Card has been blocked previously')]];
                    return Response::error($error, null, 404);
                } elseif ($result['status'] === 'error' && $result['message'] === 'Card not found. Please check and try again') {
                    $card->terminate = 1;
                    $card->save();
                    $error = ['error' => [__('This Card has been terminated previously')]];
                    return Response::error($error, null, 404);
                } else {
                    $error = ['error' => [$result['message']]];
                    return Response::error($error, null, 404);
                }
            }


        }else{
            $card = VirtualCard::where('id',$request->data_target)->where('is_active',0)->first();
        $status = 'unblock';
        if(!$card){
            $error = ['error' => [__('Something Is Wrong In Your Card')]];
            return Response::error($error,null,404);
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL =>   $this->api->config->flutterwave_url.'/'."virtual-cards/".$card->card_id."/status/".$status,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->api->config->flutterwave_secret_key
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response, true);
        if (isset($result)) {
            if ( $result['status'] === 'success' && array_key_exists('data', $result)) {
                $card->is_active = 1;
                $card->save();
                $success = ['success' => [__('Card unblock successfully!')]];
                return Response::success($success,null,200);
            } elseif ( $result['status'] === 'error' && $result['message'] === 'card is not blocked' ) {
                $card->is_active = 1;
                $card->save();
                $error = ['error' => [__('Card has been unblocked previously')]];
                return Response::error($error, null, 404);
            }elseif ( $result['status'] === 'error' && $result['message'] === 'Card not found. Please check and try again' ) {
                $card->terminate = 1;
                $card->save();
                $error = ['error' => [__('This Card has been terminated previously')]];
                return Response::error($error, null, 404);
            }else{
                $error = ['error' => [$result['message']]];
                return Response::error($error, null, 404);
            }
        }
        }
    }
    public function cardTransaction($card_id) {
        $user = auth()->user();
        $card = VirtualCard::where('user_id',$user->id)->where('card_id', $card_id)->first();
        $page_title = __("Virtual Card Transaction");
        $id = $card->card_id;
        $emptyMessage  = 'No Transaction Found!';
        $start_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . "-12 month" ) );
        $end_date = date('Y-m-d');
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL =>   $this->api->config->flutterwave_url."/"."virtual-cards/".$id."/transactions?from=".date('Y-m-d',strtotime($start_date))."&to=".$end_date."&index=0&size=2147483647",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->api->config->flutterwave_secret_key
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $card_truns = json_decode($response,true);
        return view('user.sections.virtual-card.trx',compact('page_title','card','card_truns'));


    }
    public function makeDefaultOrRemove(Request $request) {
        $validated = Validator::make($request->all(),[
            'target'        => "required|numeric",
        ])->validate();
        $user = auth()->user();
        $targetCard =  VirtualCard::where('id',$validated['target'])->where('user_id',$user->id)->first();
        $withOutTargetCards =  VirtualCard::where('id','!=',$validated['target'])->where('user_id',$user->id)->get();
        try{
            $targetCard->update([
                'is_default'  => $targetCard->is_default ? 0 : 1,
            ]);
            if(isset(  $withOutTargetCards)){
                foreach(  $withOutTargetCards as $card){
                    $card->is_default = false;
                    $card->save();
                }
            }

        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return back()->with(['success' => [__('Status Updated Successfully')]]);
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
            throw new Exception(__("Something Went Wrong! Please Try Again"));
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
            throw new Exception(__("Something Went Wrong! Please Try Again"));
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
                'message'       => __("Card fund successful card:")." ".$masked_card.' '.getAmount($amount,2).' '.get_default_currency_code(),
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
            throw new Exception(__("Something Went Wrong! Please Try Again"));
        }
    }
    //update user balance
    public function updateSenderWalletBalance($authWalle,$afterCharge) {
        $authWalle->update([
            'balance'   => $afterCharge,
        ]);
    }

    public function cardCallBack(Request $request){
        $body = @file_get_contents("php://input");
        $signature = (isset($_SERVER['HTTP_VERIF_HASH']) ? $_SERVER['HTTP_VERIF_HASH'] : '');
        if (!$signature) {
            exit();
        }
        $local_signature = env('SECRET_HASH');
        if ($signature !== $local_signature) {
            exit();
        }
        http_response_code(200);
        $response = json_decode($body);
        $trx = 'VC-' . str_random(6);
        if ($response->status == 'successful') {
            $card = VirtualCard::where('card_id', $response->CardId)->first();
            if ($card) {
                $card->amount = $response->balance;
                $card->save();

                //Transactions
                // $vt = new Virtualtransactions();
                // $vt->user_id = $card->user_id;
                // $vt->virtual_card_id = $card->id;
                // $vt->card_id = $card->card_id;
                // $vt->amount = $response->amount;
                // $vt->description = $response->description;
                // $vt->trx = $trx;
                // $vt->status = $response->status;
                // $vt->save();


                return true;
            }
            return true;
        }
        return false;
    }
     //admin notification
     public function adminNotification($trx_id,$total_charge,$amount,$payable,$user,$v_card){
        $notification_content = [
            //email notification
            'subject' => __("Virtual Card (Buy Card)"),
            'greeting' => __("Virtual Card Information"),
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("Card Masked")." : ".@$v_card->masked_card."<br>".__("Status")." : ".__("Success"),

            //push notification
            'push_title' => __("Virtual Card (Buy Card)")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." : ".$trx_id." ".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("Card Masked")." : ".$v_card->masked_card??"",

            //admin db notification
            'notification_type' =>  NotificationConst::CARD_BUY,
            'admin_db_title' => "Virtual Card Buy"." (".userGuard()['type'].")",
            'admin_db_message' => "Transaction ID"." : ".$trx_id.",".__("Request Amount")." : ".get_amount($amount,get_default_currency_code()).","."Card Masked"." : ".@$v_card->masked_card." (".$user->email.")",
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
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("Card Masked")." : ".$myCard->masked_card??""."<br>".__("Status")." : ".__("Success"),

            //push notification
            'push_title' => __("Virtual Card (Fund Amount)")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." : ".$trx_id." ".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("Card Masked")." : ".$myCard->masked_card??"",

            //admin db notification
            'notification_type' =>  NotificationConst::CARD_FUND,
            'admin_db_title' => "Virtual Card Funded"." (".userGuard()['type'].")",
            'admin_db_message' => "Transaction ID"." : ".$trx_id.",".__("Request Amount")." : ".get_amount($amount,get_default_currency_code()).","."Card Masked"." : ".$myCard->masked_card." (".$user->email.")",
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
