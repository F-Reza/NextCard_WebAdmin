<?php

namespace App\Http\Controllers\User;

use App\Constants\GlobalConst;
use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\GiftCardHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PushNotificationHelper;
use App\Models\Admin\Currency;
use App\Models\Admin\ExchangeRate;
use App\Models\Admin\TransactionSetting;
use App\Models\GiftCard;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Notifications\Admin\ActivityNotification;
use App\Notifications\User\GiftCard\GiftCardMail;
use App\Providers\Admin\BasicSettingsProvider;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class GiftCardController extends Controller
{
    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }
    public function index()
    {
        $page_title = __("My Gift Card");
        $giftCards = GiftCard::auth()->user()->where('status',1)->latest()->paginate(20);
        return view('user.sections.gift-card.index',compact(
            'page_title','giftCards'
        ));
    }
    public function giftCards()
    {
        $page_title = __("Gift Cards");
        $products = (new GiftCardHelper())->getProducts([
            'size' => 500,
            'page' =>0
        ], true);
        $products =$products['content'];
        $perPage = 18;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = array_slice($products, ($currentPage - 1) * $perPage, $perPage);
        $products = new LengthAwarePaginator($currentItems, count($products), $perPage);

        return view('user.sections.gift-card.list',compact(
            'page_title','products'
        ));
    }
    public function details($productId)
    {
        $page_title = __("Gift Card Details");
        $product = (new GiftCardHelper())->getProductInfo($productId);
        $product_receiver_code = $product['recipientCurrencyCode'];
        $check_receiver_currency_code = ExchangeRate::where('status', true)->where('currency_code',$product_receiver_code)->first();
        if(!$check_receiver_currency_code){
            return back()->with(['error' => [__("The system wallet does not have the currency code for the product currency code")." ".$product_receiver_code]]);
        }
        $currencies = Currency::where('status', true)->get();
        $cardCharge = TransactionSetting::where('slug','gift_card')->where('status',1)->first();
        return view('user.sections.gift-card.details',compact(
            'page_title','product','currencies','cardCharge'
        ));
    }
    public function giftCardOrder(Request $request){
        try{
            $form_data = $request->all();
            $product = (new GiftCardHelper())->getProductInfo($form_data['product_id']);
            $unit_price =  $form_data['g_unit_price'];
            $qty =  $form_data['g_qty'];

            $user = auth()->user();
            $sender_country = Currency::where('code',$form_data['wallet_currency'])->first();
            if(!$sender_country) return back()->with(['error' => [__('Sender Country Is Not Valid')]]);

            $userWallet = UserWallet::where(['user_id' => $user->id, 'currency_id' => $sender_country->id, 'status' => 1])->first();
            if(!$userWallet) return back()->with(['error' => [__('User wallet not found')]]);

            $receiver_country = ExchangeRate::where('currency_code',$form_data['receiver_currency'])->first();
            if(!$receiver_country) return back()->with(['error' => [__('Receiver Country Is Not Valid')]]);
            $cardCharge = TransactionSetting::where('slug','gift_card')->where('status',1)->first();
            $charges = $this->giftCardCharge( $form_data, $cardCharge,$userWallet,$sender_country,$receiver_country);
            if($charges['payable'] > $userWallet->balance) return back()->with(['error' => [__("You don't have sufficient balance")]]);

            // store data as per API requirement
            $orderData = [
                'customIdentifier'      => Str::uuid() . "|" . "GIFT_CARD",
                'preOrder'              => false,
                'productId'             => $form_data['product_id'],
                'quantity'              => $qty,
                'recipientEmail'        => $form_data['g_recipient_email'],
                'recipientPhoneDetails' => [
                    'countryCode'       => $form_data['g_recipient_iso'],
                    'phoneNumber'       => $form_data['g_recipient_phone'],
                ],
                'senderName'            => $form_data['g_from_name']??$user->fullname,
                'unitPrice'             => $unit_price,
            ];
            $order = (new GiftCardHelper())->createOrder($orderData);

            if(isset($order['status'])){
               if($order['status'] == 'SUCCESSFUL'){
                $status  = GlobalConst::SUCCESS;
               }else{
                $status  = GlobalConst::PENDING;
               }
                $giftCard['user_type']                  = GlobalConst::USER;
                $giftCard['user_id']                    =  $user->id;
                $giftCard['user_wallet_id']             =  $userWallet->id;
                $giftCard['recipient_currency_id']      =  $receiver_country->id;
                $giftCard['uuid']                       =  $order['customIdentifier'];
                $giftCard['trx_id']                     = 'GC'.getTrxNum();
                $giftCard['api_trx_id']                 =  $order['transactionId'];
                $giftCard['card_amount']                = $charges['card_unit_price'];
                $giftCard['card_total_amount']          = $charges['total_receiver_amount'];
                $giftCard['card_currency']              = $charges['card_currency'];
                $giftCard['card_name']                  = $product['productName'];
                $giftCard['card_image']                 = $product['logoUrls'][0]??"";
                $giftCard['user_wallet_currency']       = $userWallet->currency->code;
                $giftCard['exchange_rate']              = $charges['exchange_rate'];
                $giftCard['default_currency']           = get_default_currency_code();
                $giftCard['percent_charge']             = $cardCharge->percent_charge;
                $giftCard['fixed_charge']               = $cardCharge->fixed_charge;
                $giftCard['percent_charge_calc']        = $charges['percent_charge'];
                $giftCard['fixed_charge_calc']          = $charges['fixed_charge'];
                $giftCard['total_charge']               = $charges['total_charge'];
                $giftCard['qty']                        = $charges['qty'];
                $giftCard['unit_amount']                = $charges['sender_unit_price'];
                $giftCard['conversion_amount']          = $charges['conversion_amount'];
                $giftCard['total_payable']              = $charges['payable'];
                $giftCard['api_currency']               = $order['currencyCode'];
                $giftCard['api_discount']               = $order['discount'];
                $giftCard['api_fee']                    = $order['fee'];
                $giftCard['api_sms_fee']                = $order['smsFee'];
                $giftCard['api_total_fee']              = $order['totalFee'];
                $giftCard['pre_order']                  = $order['preOrdered'];
                $giftCard['recipient_email']            = $order['recipientEmail'];
                $giftCard['recipient_country_iso2']     = $form_data['g_recipient_iso'];
                $giftCard['recipient_phone']            = remove_speacial_char($form_data['g_recipient_phone_code']).remove_speacial_char($form_data['g_recipient_phone']);
                $giftCard['status']                     = $status;
                $giftCard['details']                    = json_encode($order);
            }
            //global transaction
            $card = GiftCard::create($giftCard);
            //send notification
            try{
                if( $this->basic_settings->email_notification == true){
                    $notifyData = [
                        'charge_info'   => $charges,
                        'giftCard'      => $giftCard,
                        'title'      => __("Gift Card Order"),
                    ];
                    //send notifications
                    $user->notify(new GiftCardMail($user,$notifyData));
                }
            }catch(Exception $e){}
            $sender = $this->insertCardBuy($card->trx_id,$user,$userWallet,$charges,$giftCard,$status);
            $this->insertBuyCardCharge( $sender,$charges,$user,$giftCard);
            //admin notification
            $this->adminNotification($card->trx_id,$charges,$user,$giftCard,$status);
        }catch(Exception $e){
            return back()->with(['error' => [__('Something went wrong! Please try again')]]);
        }
        return redirect()->route('user.gift.card.index')->with(['success' => [__('Your Gift Card Order Has Been Successfully Processed')]]);
    }
    public function insertCardBuy( $trx_id,$user,$userWallet,$charges,$giftCard,$status) {
        $authWallet = $userWallet;
        $afterCharge = ($authWallet->balance - $charges['payable']);
        $details =[
            'card_info' =>   $giftCard,
            'charge_info' =>   $charges,
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::GIFTCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $charges['conversion_amount'],
                'payable'                       => $charges['payable'],
                'available_balance'             => $afterCharge,
                'remark'                        => PaymentGatewayConst::GIFTCARD,
                'callback_ref'                  => $giftCard['uuid'],
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::SEND,
                'status'                        => $status,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception(__("Something went wrong! Please try again"));
        }
        return $id;
    }
    public function insertBuyCardCharge($id,$charges,$user,$giftCard) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    =>$charges['percent_charge'],
                'fixed_charge'      =>$charges['fixed_charge'],
                'total_charge'      =>$charges['total_charge'],
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>'Buy Card',
                'message'       => 'Buy card successful'.' ('.$giftCard['card_name'].')',
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::GIFTCARD,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);


         //Push Notification
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
            throw new Exception(__("Something went wrong! Please try again"));
        }
    }
    public function updateSenderWalletBalance($authWallet,$afterCharge) {
        $authWallet->update([
            'balance'   => $afterCharge,
        ]);
    }
    public function giftCardCharge($form_data, $cardCharge,$userWallet,$sender_country,$receiver_country) {
        $exchange_rate = $sender_country->rate/$receiver_country->rate;

        $data['exchange_rate']                      = $exchange_rate;
        $data['card_unit_price']                    = $form_data['g_unit_price'];
        $data['card_currency']                      = $receiver_country->currency_code;
        $data['sender_unit_price']                  = $form_data['g_unit_price'] * $exchange_rate;
        $data['qty']                                = $form_data['g_qty'];
        $data['total_receiver_amount']              = $data['card_unit_price'] *  $data['qty'];
        $data['conversion_amount']                  = $data['total_receiver_amount'] * $exchange_rate;
        $data['percent_charge']                     = ($data['conversion_amount'] / 100) * $cardCharge->percent_charge ?? 0;
        $data['fixed_charge']                       = $sender_country->rate * $cardCharge->fixed_charge ?? 0;
        $data['total_charge']                       = $data['percent_charge'] + $data['fixed_charge'];
        $data['sender_wallet_balance']              = $userWallet->balance;
        $data['payable']                            =  $data['conversion_amount'] + $data['total_charge'];
        $data['wallet_currency']                    =  $sender_country->code;

        return $data;
    }
    public function giftSearch(){
        $page_title = __("Gift Cards");
        $country_iso = request()->country;
        try{
            $products = (new GiftCardHelper())->getProductInfoByIso($country_iso);
        }catch (Exception $e) {
            return redirect()->back()->with(['error' => [__($e->getMessage()??"")]]);
        }
        $perPage = 16;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = array_slice($products, ($currentPage - 1) * $perPage, $perPage);
        $products = new LengthAwarePaginator($currentItems, count($products), $perPage);
        return view('user.sections.gift-card.search',compact(
            'page_title','products'
        ));

    }
    public function webhookInfo(Request $request){
        $response_data = $request->all();
        $custom_identifier = $response_data['data']['customIdentifier'];
        $transaction = Transaction::where('type',PaymentGatewayConst::GIFTCARD)->where('callback_ref',$custom_identifier)->first();
        $giftCard = GiftCard::where('uuid',$custom_identifier)->first();
        if( $response_data ['data']['status'] =="SUCCESSFUL"){
            $transaction->update([
                'status' => true,
            ]);
            $giftCard->update([
                'status' => true,
            ]);
        }elseif($response_data ['data']['status'] !="SUCCESSFUL" ){
            $transaction->update([
                'status' => false,
            ]);
            $giftCard->update([
                'status' => false,
            ]);
        }

        logger("Gift Order Success!", ['uuid' => $custom_identifier, 'status' => $response_data ['data']['status']]);
    }
    //admin notification
    public function adminNotification($trx_id,$charges,$user,$data,$status){
        $exchange_rate = get_amount(1,$charges['card_currency'])." = ".get_amount($charges['exchange_rate'],$charges['card_currency']);
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
        }
        $notification_content = [
            //email notification
            'subject' => __("Gift Card Order")." @" . @$user->username." (".@$user->email.")",
            'greeting' =>__("Gift Card Information"),
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Card Name")." : ".$data['card_name']."<br>".__("Receiver Email").": @".$data['recipient_email']."<br>".__("Receiver Phone")." : ".$data['recipient_phone']."<br>".__("Card Unit Price")." : ".get_amount($charges['card_unit_price'],$charges['card_currency'])."<br>".__("Card Quantity")." : ".$charges['qty']."<br>".__("Card Total Price")." : ".get_amount($charges['total_receiver_amount'],$charges['card_currency'])."<br>".__("Exchange Rate")." : ".$exchange_rate."<br>".__("Payable Unit Price")." : ".get_amount($charges['sender_unit_price'],$charges['wallet_currency'])."<br>".__("Payable Amount")." : ".get_amount($charges['conversion_amount'],$charges['wallet_currency'])."<br>".__("Total Charge")." : ".get_amount($charges['total_charge'],$charges['wallet_currency'])."<br>".__("Total Payable Amount")." : ".get_amount($charges['payable'],$charges['wallet_currency'])."<br>".__("Status")." : ".__($status),

            //push notification
            'push_title' => __("Gift Card Order")." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." ".$trx_id." ".__("Card Name")." : ".$data['card_name']." ".__("Card Quantity")." : ".$charges['qty']." ".__("Total Payable Amount")." : ".get_amount($charges['payable'],$charges['wallet_currency']),

            //admin db notification
            'notification_type' =>  NotificationConst::GIFTCARD,
            'admin_db_title' => "Gift Card Order"." (".userGuard()['type'].")",
            'admin_db_message' => "Transaction ID"." : ".$trx_id.","."Card Name"." : ".$data['card_name'].","."Card Quantity"." : ".$charges['qty'].","."Total Payable Amount"." : ".get_amount($charges['payable'],$charges['wallet_currency'])." (".$user->email.")"
        ];

        try{
            //notification
            (new NotificationHelper())->admin(['admin.gift.card.logs','admin.gift.card.details'])
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
