<?php

namespace App\Notifications\User\GiftCard;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class GiftCardMail extends Notification
{
    use Queueable;

    public $user;
    public $data;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user,$data)
    {
        $this->user = $user;
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $user = $this->user;
        $data = $this->data;
        $trx_id = $this->data['giftCard']['trx_id'];
        $date = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');
        return (new MailMessage)
                    ->greeting(__("Hello")." ".$user->fullname." !")
                    ->subject($data['title']??__("Gift Card Order"))
                    ->line(__("Your Gift Card Order Has Been Successfully Processed")." :")
                    ->line(__("TRX ID")." : " .$trx_id)
                    ->line(__("Card Name")." : " .$data['giftCard']['card_name'])
                    ->line(__("receiver Email")." : " .$data['giftCard']['recipient_email'])
                    ->line(__("Receiver Phone")." : " ."+".$data['giftCard']['recipient_phone'])
                    ->line(__("Card Unit Price")." : " .get_amount($data['charge_info']['card_unit_price'],$data['charge_info']['card_currency']))
                    ->line(__("Card Quantity")." : " .$data['charge_info']['qty'])
                    ->line(__("Card Total Price")." : " .get_amount($data['charge_info']['total_receiver_amount'],$data['charge_info']['card_currency']))
                    ->line(__("Exchange Rate")." : " .get_amount(1,$data['charge_info']['card_currency'])." = ".get_amount($data['charge_info']['exchange_rate'],$data['charge_info']['card_currency']))
                    ->line(__("Payable Unit Price")." : " .get_amount($data['charge_info']['sender_unit_price'],$data['charge_info']['wallet_currency']))
                    ->line(__("Payable Amount")." : " .get_amount($data['charge_info']['conversion_amount'],$data['charge_info']['wallet_currency']))
                    ->line(__("Total Charge")." : " .get_amount($data['charge_info']['total_charge'],$data['charge_info']['wallet_currency']))
                    ->line(__("Total Payable Amount")." : " .get_amount($data['charge_info']['payable'],$data['charge_info']['wallet_currency']))
                    ->line(__("Time & Date")." : " .$dateTime)
                    ->line(__('Thank you for using our application!'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
