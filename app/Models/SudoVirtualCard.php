<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SudoVirtualCard extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $table = "sudo_virtual_cards";
    protected $casts = [
        'user_id' => 'integer',
        'card_id' => 'string',
        'business_id' => 'string',
        'name' => 'string',
        'customer' => 'object',
        'account' => 'object',
        'fundingSource' => 'object',
        'type' => 'string',
        'brand' => 'string',
        'currency' => 'string',
        'balance' => 'double',
        'maskedPan' => 'string',
        'last4' => 'string',
        'expiryMonth' => 'string',
        'expiryYear' => 'string',
        'status' => 'boolean',
        'isDeleted' => 'boolean',
        'is_default' => 'boolean',
        'billingAddress' => 'object',
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
}
