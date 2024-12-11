<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class GiftCardLogs extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $statusInfo = [
            "success" =>      1,
            "pending" =>      2,
            "rejected" =>     4,
            ];
        return[
            'id' => $this->id,
            'trx' => $this->trx_id,
            'transaction_type' => __($this->type),
            'card_unit_price' => getAmount($this->details->card_info->card_amount,2).' '.$this->details->card_info->card_currency,
            'card_quantity' => $this->details->card_info->qty,
            'card_total_price' => getAmount($this->details->card_info->card_total_amount,2).' '.$this->details->card_info->card_currency,
            'exchange_rate' => '1 ' .@$this->details->charge_info->card_currency.' = '.getAmount(@$this->details->charge_info->exchange_rate,2).' '.@$this->details->charge_info->wallet_currency,
            'request_amount' => getAmount($this->request_amount,4).' '.get_default_currency_code(),
            'total_charge' => getAmount($this->charge->total_charge,4).' '.get_default_currency_code(),
            'payable' => getAmount($this->payable,4).' '.get_default_currency_code(),
            'card_name' => $this->details->card_info->card_name,
            'receiver_email' => $this->details->card_info->recipient_email,
            'receiver_phone' => $this->details->card_info->recipient_phone,
            'current_balance' => getAmount($this->available_balance,2).' '.get_default_currency_code(),
            'status' => $this->stringStatus->value ,
            'date_time' => $this->created_at ,
            'status_info' =>(object)$statusInfo ,
            'rejection_reason' =>$this->reject_reason??"" ,

        ];
    }
}
