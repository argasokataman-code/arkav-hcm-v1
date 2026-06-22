<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CookieConsent extends Model
{
    protected $fillable = [
        'user_uuid',
        'company_id',
        'essential',
        'analytics',
        'marketing',
        'consent_ip',
        'consented_at',
    ];

    protected $casts = [
        'essential' => 'boolean',
        'analytics' => 'boolean',
        'marketing' => 'boolean',
        'consented_at' => 'datetime',
    ];
}
