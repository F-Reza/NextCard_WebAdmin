<?php

namespace App\Http\Controllers\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Notifications\Admin\ActivityNotification;
use App\Notifications\User\TransferMoney\ReceiverMail;
use App\Notifications\User\TransferMoney\SenderMail;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferMoneyController extends Controller
{
    protected   $trx_id;
    protected   $basic_settings;
    public function __construct()
    {
        $this->trx_id = 'SM'.getTrxNum();
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function index() {
        $page_title = __('transfer Money');
        $transferMoneyCharge = TransactionSetting::where('slug','transfer-money')->where('status',1)->first();
        $transactions = Transaction::auth()->transferMoney()->latest()->take(10)->get();
        return view('user.sections.transfer-money.index',compact("page_title",'transferMoneyCharge','transactions'));
    }
    public function checkUser(Request $request){
        $email = $request->email;
        $exist['data'] = User::where('email',$email)->active()->first();
        $user = auth()->user();
        if(@$exist['data'] && $user->email == @$exist['data']->email){
            return response()->json(['own'=>__("Can\'t transfer money to your own")]);
        }
        return response($exist);
    }
    public function confirmed(Request $request){
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'email' => 'required|email'
        ]);
        $user = auth()->user();
        $amount = $request->amount;
        $user = auth()->user();
        $sendMoneyCharge = TransactionSetting::where('slug','transfer-money')->where('status',1)->first();
        $userWallet = UserWallet::where('user_id',$user->id)->first();
        if(!$userWallet){
            return back()->with(['error' => [__("Sender wallet not found")]]);
        }
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return back()->with(['error' => [__('Default Currency Not Setup Yet')]]);
        }
        $receiver = User::where('email',$request->email)->active()->first();
        if(!$receiver){
            return back()->with(['error' => [__("Receiver not exist")]]);
        }
        if($receiver->email ==  $user->email){
            return back()->with(['error' => [__("Can\'t transfer money to your own")]]);
        }
        $receiverWallet = UserWallet::where('user_id',$receiver->id)->first();
        if(!$receiverWallet){
            return back()->with(['error' => [__("Receiver wallet not found")]]);
        }

        $minLimit =  $sendMoneyCharge->min_limit *  $rate;
        $maxLimit =  $sendMoneyCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return back()->with(['error' => [__('Please follow the transaction limit')]]);
        }
        //charge calculations
        $fixedCharge = $sendMoneyCharge->fixed_charge *  $rate;
        $percent_charge = ($request->amount / 100) * $sendMoneyCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        $recipient = $amount;
        if($payable > $userWallet->balance ){
            return back()->with(['error' => [__('Sorry, insufficient balance')]]);
        }
        try{
            $trx_id = $this->trx_id;
            $sender = $this->insertSender( $trx_id,$user,$userWallet,$amount,$recipient,$payable,$receiver);
            if($sender){
                 $this->insertSenderCharges( $fixedCharge,$percent_charge, $total_charge, $amount,$user,$sender,$receiver);
                 try{
                    if( $this->basic_settings->email_notification == true){
                        $notifyDataSender = [
                            'trx_id'            => $trx_id,
                            'title'             => __("Transfer Money to")." @" . @$receiver->username." (".@$receiver->email.")",
                            'request_amount'    => getAmount($amount,2).' '.get_default_currency_code(),
                            'payable'           => getAmount($payable,2).' ' .get_default_currency_code(),
                            'charges'           => getAmount( $total_charge, 2).' ' .get_default_currency_code(),
                            'received_amount'   => getAmount( $recipient,2).' ' .get_default_currency_code(),
                            'status'            => __("Success"),
                        ];
                        //sender notifications
                        $user->notify(new SenderMail($user,(object)$notifyDataSender));
                    }

                 }catch(Exception $e){}
            }

            $receiverTrans = $this->insertReceiver( $trx_id,$user,$userWallet,$amount,$recipient,$payable,$receiver,$receiverWallet);
            if($receiverTrans){
                 $this->insertReceiverCharges( $fixedCharge,$percent_charge, $total_charge, $amount,$user,$receiverTrans,$receiver);
                 try{
                    if( $this->basic_settings->email_notification == true){
                        $notifyDataReceiver = [
                            'trx_id'  => $trx_id,
                            'title'  => __("Received Money from")." @" .@$user->username." (".@$user->email.")",
                            'received_amount'  => getAmount( $recipient, 2).' ' .get_default_currency_code(),
                            'status'  => __("Success"),
                        ];
                        //send notifications
                        $receiver->notify(new ReceiverMail($receiver,(object)$notifyDataReceiver));
                    }
                 }catch(Exception $e){}
            }
            //admin notification
            $this->adminNotification($trx_id,$total_charge,$amount,$payable,$user,$receiver);
            return redirect()->route("user.transfer.money.index")->with(['success' => [__('Transfer Money successful to').' '.$receiver->fullname]]);
        }catch(Exception $e) {
            return back()->with(['error' => [__("Something Went Wrong! Please Try Again")]]);
        }

    }
    //sender transaction
     public function insertSender($trx_id,$user,$userWallet,$amount,$recipient,$payable,$receiver) {
        $trx_id = $trx_id;
        $authWallet = $userWallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'recipient_amount' => $recipient,
            'receiver' => $receiver,
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::TYPETRANSFERMONEY,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::TYPETRANSFERMONEY," ")) . " To " .$receiver->fullname,
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::SEND,
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
    public function updateSenderWalletBalance($authWallet,$afterCharge) {
        $authWallet->update([
            'balance'   => $afterCharge,
        ]);
    }
    public function insertSenderCharges($fixedCharge,$percent_charge, $total_charge, $amount,$user,$id,$receiver) {
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
               'title'         =>__("transfer Money"),
                'message'       => "Transfer Money to "." ".$receiver->fullname.' ' .$amount.' '.get_default_currency_code()." successful",
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::TRANSFER_MONEY,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
            //push notification
            if( $this->basic_settings->push_notification == true){
                try{
                    (new PushNotificationHelper())->prepare([$user->id],[
                        'title' => $notification_content['title'],
                        'desc'  => $notification_content['message'],
                        'user_type' => 'user',
                    ])->send();
                }catch(Exception $e) {dd($e);}
            }
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something Went Wrong! Please Try Again"));
        }
    }
    //Receiver Transaction
    public function insertReceiver($trx_id,$user,$userWallet,$amount,$recipient,$payable,$receiver,$receiverWallet) {
        $trx_id = $trx_id;
        $receiverWallet = $receiverWallet;
        $recipient_amount = ($receiverWallet->balance + $recipient);
        $details =[
            'sender_amount' => $amount,
            'sender' => $user,
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $receiver->id,
                'user_wallet_id'                => $receiverWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::TYPETRANSFERMONEY,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $recipient_amount,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::TYPETRANSFERMONEY," ")) . " From " .$user->fullname,
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateReceiverWalletBalance($receiverWallet,$recipient_amount);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something Went Wrong! Please Try Again"));
        }
        return $id;
    }
    public function updateReceiverWalletBalance($receiverWallet,$recipient_amount) {
        $receiverWallet->update([
            'balance'   => $recipient_amount,
        ]);
    }
    public function insertReceiverCharges($fixedCharge,$percent_charge, $total_charge, $amount,$user,$id,$receiver) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      => $fixedCharge,
                'total_charge'      => $total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>"transfer Money",
                'message'       => "Transfer Money from".' '.$user->fullname.' ' .$amount.' '.get_default_currency_code()." successful",
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::TRANSFER_MONEY,
                'user_id'  => $receiver->id,
                'message'   => $notification_content,
            ]);
           //push notification
           if( $this->basic_settings->push_notification == true){
                try{
                    (new PushNotificationHelper())->prepare([$receiver->id],[
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

     //admin notification
     public function adminNotification($trx_id,$total_charge,$amount,$payable,$user,$receiver){
        $notification_content = [
            //email notification
            'subject' =>__("transfer Money"),
            'greeting' =>__("Transfer Money Information"),
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Sender").": @".$user->email."<br>".__("Receiver").": @".$receiver->email."<br>".__("Request Amount")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Fees & Charges")." : ".get_amount($total_charge,get_default_currency_code())."<br>".__("Total Payable Amount")." : ".get_amount($payable,get_default_currency_code())."<br>".__("Recipient Received")." : ".get_amount($amount,get_default_currency_code())."<br>".__("Status")." : ".__("Success"),

            //push notification
            'push_title' => __("transfer Money")." ".__('Successful'),
            'push_content' => __('TRX ID')." ".$trx_id." ".__("Sender").": @".$user->email." ".__("Receiver").": @".$receiver->email." ".__("Sender Amount")." : ".get_amount($amount,get_default_currency_code())." ".__("Receiver Amount")." : ".get_amount($amount,get_default_currency_code()),

            //admin db notification
            'notification_type' =>  NotificationConst::TRANSFER_MONEY,
            'trx_id' =>  $trx_id,
            'admin_db_title' => "Transfer Money"." ".'Successful'." ".get_amount($amount,get_default_currency_code())." (".$trx_id.")",
            'admin_db_message' =>"Sender".": @".$user->email.","."Receiver".": @".$receiver->email.","."Sender Amount"." : ".get_amount($amount,get_default_currency_code()).","."Receiver Amount"." : ".get_amount($amount,get_default_currency_code())
        ];


        try{
            //notification
            (new NotificationHelper())->admin(['admin.transfer.money.index'])
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
