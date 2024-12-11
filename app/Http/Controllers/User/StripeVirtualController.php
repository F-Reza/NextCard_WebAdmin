<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PushNotificationHelper;
use App\Http\Helpers\Response;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\StripeVirtualCard;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Models\VirtualCardApi;
use App\Notifications\Admin\ActivityNotification;
use App\Notifications\User\VirtualCard\CreateMail;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StripeVirtualController extends Controller
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
        $myCards = StripeVirtualCard::where('user_id',auth()->user()->id)->get();
        $totalCards = StripeVirtualCard::where('user_id',auth()->user()->id)->count();
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $transactions = Transaction::auth()->virtualCard()->latest()->take(5)->get();
        $cardApi = $this->api;
        return view('user.sections.virtual-card-stripe.index',compact(
            'page_title','myCards','cardApi',
            'transactions','cardCharge','totalCards'
        ));
    }
    public function cardDetails($card_id)
    {
        $page_title = __("Card Details");
        $myCard = StripeVirtualCard::where('card_id',$card_id)->first();
        $cardApi = $this->api;
        return view('user.sections.virtual-card-stripe.details',compact('page_title','myCard','cardApi'));
    }
    public function cardTransaction($card_id) {
        $page_title = __("Virtual Card Transactions");
        $user = auth()->user();
        $card = StripeVirtualCard::where('user_id',$user->id)->where('card_id', $card_id)->first();
        $card_truns =  getStripeCardTransactions($card->card_id);
        return view('user.sections.virtual-card-stripe.trx',compact('page_title','card','card_truns'));
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
            $card = StripeVirtualCard::where('id',$request->data_target)->where('status',1)->first();
            $status = 'inactive';
            if(!$card){
                $error = ['error' => [__('Something Is Wrong In Your Card')]];
                return Response::error($error,null,404);
            }
            $result = cardActiveInactive($card->card_id,$status);
            if(isset($result['status'])){
                if($result['status'] == true){
                    $card->status = false;
                    $card->save();
                    $success = ['success' => [__('Card Inactive Successfully!')]];
                    return Response::success($success,null,200);
                }elseif($result['status'] == false){
                    $success = ['error' => [$result['message']??"Something is wrong"]];
                    return Response::success($success,null,200);
                }
            }
        }else{
        $card = StripeVirtualCard::where('id',$request->data_target)->where('status',0)->first();
        $status = 'active';
        if(!$card){
            $error = ['error' => [__('Something Is Wrong In Your Card')]];
            return Response::error($error,null,404);
        }
        $result = cardActiveInactive($card->card_id,$status);
        if(isset($result['status'])){
            if($result['status'] == true){
                $card->status = true;
                $card->save();
                $success = ['success' => [__('Card Active Successfully!')]];
                return Response::success($success,null,200);
            }elseif($result['status'] == false){
                $success = ['error' => [$result['message']??"Something is wrong"]];
                return Response::success($success,null,200);
            }
        }

        }
    }
    public function makeDefaultOrRemove(Request $request) {
        $validated = Validator::make($request->all(),[
            'target'        => "required|numeric",
        ])->validate();
        $user = auth()->user();
        $targetCard =  StripeVirtualCard::where('id',$validated['target'])->where('user_id',$user->id)->first();
        $withOutTargetCards =  StripeVirtualCard::where('id','!=',$validated['target'])->where('user_id',$user->id)->get();
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

        }catch(Exception $e) {
            return back()->with(['error' => [__("Something went wrong! Please try again.")]]);
        }
        return back()->with(['success' => [__('Status Updated Successfully')]]);
    }
    public function cardBuy(Request $request)
    {
        $request->validate([
            'fund_amount' => 'required|numeric|gt:0',
        ]);

        $user = auth()->user();
        $amount = $request->fund_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            return back()->with(['error' => [__('User wallet not found')]]);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();

        if(!$baseCurrency){
            return back()->with(['error' => [__('Default Currency Not Setup Yet')]]);
        }
        $rate = $baseCurrency->rate;
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

        //create connected account
       if( $user->stripe_connected_account == null){
        $c_account =  createConnectAccount($user);
        if( isset($c_account['status'])){
           if($c_account['status'] == false){
            return back()->with(['error' => [$c_account['message']]]);
           }
        }
        $stripe_connected_account_data =[
            'id' => $c_account['data']['id'],
            'object' => $c_account['data']['object'],
            'business_profile' => $c_account['data']['business_profile'],
            'business_type' => $c_account['data']['business_type'],
            'capabilities' => $c_account['data']['capabilities'],
            'charges_enabled' => $c_account['data']['charges_enabled'],
            'country' => $c_account['data']['country'],
            'created' => $c_account['data']['created'],
            'default_currency' => $c_account['data']['default_currency'],
            'details_submitted' => $c_account['data']['details_submitted'],
            'external_accounts' => $c_account['data']['external_accounts'],
            'future_requirements' => $c_account['data']['future_requirements'],
            'metadata' => $c_account['data']['metadata'],
            'payouts_enabled' => $c_account['data']['payouts_enabled'],
            'requirements' => $c_account['data']['requirements'],
            'settings' => $c_account['data']['settings'],
            'tos_acceptance' => $c_account['data']['tos_acceptance'],
            'type' => $c_account['data']['type'],

        ];
        $stripe_connected_account_data = (object)$stripe_connected_account_data;
        $user->stripe_connected_account = $stripe_connected_account_data;
        $user->save();
        $c_account = $user->stripe_connected_account->id;

       }else{
        $c_account = $user->stripe_connected_account->id;
       }

        //create card holder
       if( $user->stripe_card_holders == null){
        $card_holder =  createCardHolders($user,$c_account);
        if( isset($card_holder['status'])){
           if($card_holder['status'] == false){
            return back()->with(['error' => [$card_holder['message']]]);
           }
        }
        $stripe_card_holders_data =[
            'id' => $c_account['data']['id'],
        ];
        $stripe_card_holders_data = (object)$stripe_card_holders_data;

        $user->stripe_card_holders =   (object)$stripe_card_holders_data;
        $user->save();
        $card_holder_id = $user->stripe_card_holders->id;

       }else{
        $card_holder_id = $user->stripe_card_holders->id;
       }
       //create card now
       $created_card = createVirtualCard($card_holder_id,$c_account);
       if(isset($created_card['status'])){
            if($created_card['status'] == false){
                return back()->with(['error' => [$created_card['message']]]);
            }
       }
        //account update
        $account_update = updateAccount($c_account);
        if(isset($account_update['status'])){
            if($account_update['status'] == false){
                return back()->with(['error' => [$account_update['message']]]);
            }
        }

       //now funded amount
       $funded_amount = transfer($amount,  $c_account);
       if(isset($funded_amount['status'])){
            if($funded_amount['status'] == false){
                return back()->with(['error' => [$funded_amount['message']]]);
            }
        }

       if($created_card['status']  = true){
            $card_info = (object)$created_card['data'];
            $v_card = new StripeVirtualCard();
            $v_card->user_id = $user->id;
            $v_card->name = $user->fullname;
            $v_card->card_id = $card_info->id;
            $v_card->type = $card_info->type;
            $v_card->brand = $card_info->brand;
            $v_card->currency = $card_info->currency;
            $v_card->amount = $amount;
            $v_card->charge = $total_charge;
            $v_card->maskedPan = "0000********".$card_info->last4;
            $v_card->last4 = $card_info->last4;
            $v_card->expiryMonth = $card_info->exp_month;
            $v_card->expiryYear = $card_info->exp_year;
            $v_card->status = true;
            $v_card->card_details = $card_info;
            $v_card->save();

            $trx_id =  'CB'.getTrxNum();
            try{
                $sender = $this->insertCardBuy( $trx_id,$user,$wallet,$amount, $v_card ,$payable);
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
                return redirect()->route("user.stripe.virtual.card.index")->with(['success' => [__('Card Buy Successfully')]]);
            }catch(Exception $e){
                return back()->with(['error' => [__("Something Went Wrong! Please Try Again")]]);
            }

       }

    }
    public function getSensitiveData(Request $request){
        $card_id = $request->card_id;
        $data['result'] = getSensitiveData( $card_id);
        return response()->json($data);
    }
     //card buy helper
     public function insertCardBuy( $trx_id,$user,$wallet,$amount, $v_card ,$payable) {
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
            throw new Exception(__('Something went wrong! Please try again'));
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
            throw new Exception(__('Something went wrong! Please try again'));
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
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("card Masked")." : ".@$v_card->maskedPan."<br>".__("Status")." : ".__("Success"),

            //push notification
            'push_title' => __("Virtual Card (Buy Card)")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." : ".$trx_id." ".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("card Masked")." : ".$v_card->maskedPan??"",

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
}
