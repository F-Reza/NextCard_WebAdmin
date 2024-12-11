<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrowalletVirtualCard extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts            = [
        'id'                    => 'integer',
        'user_id'               => 'integer',
        'name_on_card'          => 'string',
        'card_id'               => 'string',
        'card_created_date'     => 'string',
        'card_type'             => 'string',
        'card_brand'            => 'string',
        'card_user_id'          => 'string',
        'reference'             => 'string',
        'card_status'           => 'string',
        'customer_id'           => 'string',
        'card_name'             => 'string',
        'card_number'           => 'string',
        'last4'                 => 'string',
        'cvv'                   => 'string',
        'expiry'                => 'string',
        'customer_email'        => 'string',
        'balance'               => 'string',
        'status'                => 'boolean',
        'is_active'             => 'boolean',
        'is_default'            => 'boolean',
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
}
