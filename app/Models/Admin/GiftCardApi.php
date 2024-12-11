<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCardApi extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    const PROVIDER_RELOADLY     = "RELOADLY";
    const STATUS_ACTIVE         = 1;
    const ENV_SANDBOX           = "SANDBOX";
    const ENV_PRODUCTION        = "PRODUCTION";

    protected $casts = [
        'credentials'   => 'object',
    ];

    /**
     * Get reloadly api configuration
     */
    public function scopeReloadly($query)
    {
        return $query->where('provider', self::PROVIDER_RELOADLY);
    }

    /**
     * Get active record
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
