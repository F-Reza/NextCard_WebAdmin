<?php

namespace App\Notifications\User\TransferMoney;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SenderMail extends Notification
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
                    ->subject($data->title)
                    ->line(__("Transfer Money Success").":")
                    ->line($data->title)
                    ->line(__("TRX ID").": " .$trx_id)
                    ->line(__("Request Amount").": " .$data->request_amount)
                    ->line(__("Fees & Charges").": " .$data->charges)
                    ->line(__("Total Payable Amount").": " .$data->payable)
                    ->line(__("recipient Received").": " .$data->received_amount)
                   ->line(__("Status").": " .$data->status)
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
