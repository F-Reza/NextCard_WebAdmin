<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Plan;

class VirtualCard extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $table = "virtual_cards";
    protected $casts = [
        'user_id' => 'integer',
        'card_id' => 'string',
        'name' => 'string',
        'account_id' => 'string',
        'card_hash' => 'string',
        'card_pan' => 'string',
        'masked_card' => 'string',
        'cvv' => 'string',
        'expiration' => 'string',
        'card_type' => 'string',
        'name_on_card' => 'string',
        'callback' => 'string',
        'ref_id' => 'string',
        'secret' => 'string',
        'city' => 'string',
        'state' => 'string',
        'zip_code' => 'string',
        'address' => 'string',
        'amount' => 'double',
        'currency' => 'string',
        'bg' => 'string',
        'charge' => 'double',
        'is_active' => 'integer',
        'funding' => 'integer',
        'terminate' => 'integer',

    ];


    public function user() {
        return $this->belongsTo(User::class);
    }

}
