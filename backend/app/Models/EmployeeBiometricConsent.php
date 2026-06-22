<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeBiometricConsent extends Model
{
    protected $fillable = [
        'employee_uuid',
        'company_id',
        'selfie_consent',
        'gps_consent',
        'photo_consent',
        'consent_given_at',
        'consent_withdrawn_at',
        'consent_ip',
    ];

    protected $casts = [
        'selfie_consent' => 'boolean',
        'gps_consent' => 'boolean',
        'photo_consent' => 'boolean',
        'consent_given_at' => 'datetime',
        'consent_withdrawn_at' => 'datetime',
    ];
}
