<?php

namespace App\Traits\AdminNotifications;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\NotificationHelper;
use App\Notifications\Admin\ActivityNotification;
use Exception;
use Illuminate\Support\Facades\Auth;

trait AddMoneyNotifications {

    //admin notification global(Agent & User)
    public function adminNotification($trx_id,$output,$status){

        if(!empty($this->predefined_user)) {
            $user = $this->predefined_user;
        }elseif(Auth::guard(userGuard()['guard'])->check()){
            $user = auth()->guard(userGuard()['guard'])->user();
        }
        $exchange_rate = " 1 ". $output['amount']->default_currency.' = '. get_amount($output['amount']->sender_cur_rate,$output['amount']->sender_cur_code);
        if($status == PaymentGatewayConst::STATUSSUCCESS){
            $status ="Success";
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
            'subject' =>__('Add Money')." (".userGuard()['type'].")",
            'greeting' =>__("Add Money Via!")." ".$output['currency']->name,
            'email_content' =>__("TRX ID")." : ".$trx_id."<br>".__("Request Amount")." : ".get_amount($output['amount']->requested_amount,$output['amount']->default_currency)."<br>".__("Exchange Rate")." : ". $exchange_rate."<br>".__("Fees & Charges")." : ". get_amount($output['amount']->will_get,$output['amount']->default_currency)."<br>".__("Total Payable Amount")." : ".get_amount($output['amount']->total_amount,$output['amount']->sender_cur_code)."<br>".__("Status")." : ".__($status),
            //push notification
            'push_title' =>  __('Add Money')." (".userGuard()['type'].")",
            'push_content' => __('TRX ID')." ".$trx_id." ". __('Add Money').' '.$output['amount']->requested_amount.' '.$output['amount']->default_currency.' '.__('By').' '.$output['currency']->name.' ('.$user->username.')',

            //admin db notification
            'notification_type' =>  NotificationConst::ADD_MONEY,
            'trx_id' =>  $trx_id,
            'admin_db_title' =>  'Add Money'." (".userGuard()['type'].")",
            'admin_db_message' => 'Add Money'.' '.$output['amount']->requested_amount.' '.$output['amount']->default_currency.' '.'By'.' '. $output['currency']->name.' ('.$user->username.')'
        ];

        try{
            //notification
            (new NotificationHelper())->admin(['admin.add.money.index','admin.add.money.pending','admin.add.money.complete','admin.add.money.canceled','admin.add.money.details','admin.add.money.approved','admin.add.money.rejected'])
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
