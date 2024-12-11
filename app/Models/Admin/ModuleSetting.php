<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleSetting extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'user_type' => 'string',
        'slug' => 'string',
        'status' => 'boolean',
    ];

}
