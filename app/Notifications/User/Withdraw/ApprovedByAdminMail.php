<?php

namespace App\Notifications\User\Withdraw;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class ApprovedByAdminMail extends Notification
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
        $trx_id = $this->data->trx_id;
        $date = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');
            return (new MailMessage)
            ->greeting(__("Hello")." ".$user->fullname." !")
            ->subject(__("Withdraw Money Via").", ". $data->gateway_name.' ('.$data->gateway_type.' )')
            ->line(__("Withdraw Approved Heading").", ".$data->gateway_name." ".__("Details of withdraw money")." :")
            ->line(__("TRX ID").": " .$trx_id)
            ->line(__("Request Amount").": " . getAmount($data->amount,4).' '.get_default_currency_code())
            ->line(__("Exchange Rate").": " ." 1 ". get_default_currency_code().' = '. getAmount($data->gateway_rate,4).' '.$data->gateway_currency)
            ->line(__("Fees & Charges").": " . getAmount($data->gateway_charge,4).' '.$data->gateway_currency)
            ->line(__("Will Get").": " .  get_amount($data->will_get,$data->gateway_currency,'',4))
            ->line(__("Total Payable Amount").": " . get_amount($data->payable,get_default_currency_code(),'4'))
            ->line(__("Status").": ".__("Successful"))
            ->line(__("Date And Time").": " .$dateTime)
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
