<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class AddMoneyLogs extends JsonResource
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
        return [
            'id' => $this->id,
            'trx' => $this->trx_id,
            'gateway_name' => $this->currency->name,
            'transaction_type' => __($this->type),
            'request_amount' => getAmount($this->request_amount,4).' '.get_default_currency_code() ,
            'payable' => getAmount($this->payable,4).' '.$this->currency->currency_code,
            'exchange_rate' => '1 ' .get_default_currency_code().' = '.getAmount($this->currency->rate,4).' '.$this->currency->currency_code,
            'total_charge' => getAmount($this->charge->total_charge,4).' '.$this->currency->currency_code,
            'current_balance' => getAmount($this->available_balance,4).' '.get_default_currency_code(),
            "confirm" =>$this->confirm??false,
            "dynamic_inputs" =>$this->dynamic_inputs,
            "confirm_url" =>$this->confirm_url,
            'status' => $this->stringStatus->value ,
            'date_time' => $this->created_at ,
            'status_info' =>(object)$statusInfo ,
            'rejection_reason' =>$this->reject_reason??"" ,
        ];
    }
}
