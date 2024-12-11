<?php

namespace App\Models;

use App\Constants\GlobalConst;
use App\Constants\PaymentGatewayConst;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['details' => 'object' ,'codes' => 'object'];

    const STATUS_SUCCESS    = "SUCCESS";
    const STATUS_PENDING    = "PENDING";
    const STATUS_PROCESSING = "PROCESSING";
    const STATUS_REFUNDED   = "REFUNDED";
    const STATUS_FAILED     = "FAILED";

    // public function getStringStatusAttribute()
    // {
    //     $data = [
    //         'class' => "",
    //         'value' => "",
    //     ];
    //     if($this->status == self::STATUS_SUCCESS) {
    //         $data['class']  = "badge badge--success";
    //         $data['value']  = __("Success");
    //     }

    //     return (object) $data;
    // }
    public function getStringStatusAttribute() {
        $status = $this->status;
        $data = [
            'class' => "",
            'value' => "",
        ];
        if($status == PaymentGatewayConst::STATUSSUCCESS) {
            $data = [
                'class'     => "badge badge--success",
                'value'     => __("Success"),
            ];
        }else if($status == PaymentGatewayConst::STATUSPENDING) {
            $data = [
                'class'     => "badge badge--warning",
                'value'     => __("pending"),
            ];
        }

        return (object) $data;
    }

    public function scopeAuth($query)
    {
        return $query->where('user_id', auth()->id());
    }

    public function scopeUser($query)
    {
        return $query->where('user_type', GlobalConst::USER);
    }

    public function scopeCard($query, $uuid)
    {
        return $query->where('uuid', $uuid);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userWallet()
    {
        return $this->belongsTo(UserWallet::class,"user_wallet_id");
    }

    public function getCreatorWalletAttribute()
    {
        if($this->user_type == GlobalConst::USER) {
            return $this->userWallet;
        }
    }

    public function getCreatorAttribute()
    {
        if($this->user_type == GlobalConst::USER) {
            return $this->user;
        }
    }

    public function isSuccess()
    {
        if($this->status == self::STATUS_SUCCESS) return true;

        return false;
    }

    public function isPending()
    {
        if($this->status == self::STATUS_PENDING) return true;

        return false;
    }

    public function isProcessing()
    {
        if($this->status == self::STATUS_PROCESSING) return true;

        return false;
    }
}
