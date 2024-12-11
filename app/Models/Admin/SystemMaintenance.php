<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemMaintenance extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'slug'      => 'string',
        'title'     => 'string',
        'details'   => 'string',
        'status'    => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeBanned($query)
    {
        return $query->where('status', false);
    }
}
