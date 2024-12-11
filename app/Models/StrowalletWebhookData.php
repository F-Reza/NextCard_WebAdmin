<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrowalletWebhookData extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $casts            = [
        'id'                => 'integer',
        'parent_id'         => 'integer',
        'user_id'           => 'integer',
        'transaction_id'    => 'string',
        'event'             => 'string',
        'cardId'            => 'string',
        'card_currency'     => 'string',
        'data'              => 'object',
    ];

    public function user() {
        return $this->belongsTo(User::class,'user_id','id');
    }
    public function card() {
        return $this->belongsTo(StrowalletVirtualCard::class,'parent_id','id');
    }
}
