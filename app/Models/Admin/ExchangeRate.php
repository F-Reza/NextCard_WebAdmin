<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $guarded = ['id'];


    protected $casts = [
        'id'              => 'integer',
        'name'            => 'string',
        'mobile_code'     => 'string',
        'currency_code'   => 'string',
        'currency_name'   => 'string',
        'currency_symbol' => 'string',
        'rate'            => 'double',
        'status'          => 'integer',
    ];

    function scopeMyCurrency(){
        return $this->where('name', auth()->user()->address->country)->first();
    }

    public function scopeSearch($query,$text) {
        $query->where(function($q) use ($text) {
            $q->where("name","like","%".$text."%");
        })->orWhere("currency_name","like","%".$text."%")
        ->orWhere("currency_code","like","%".$text."%");
    }
    public function scopeActive($query) {
        return $query->where("status",true);
    }
}
