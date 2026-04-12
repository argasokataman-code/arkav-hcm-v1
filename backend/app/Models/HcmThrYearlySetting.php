<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcmThrYearlySetting extends Model
{
    protected $table = 'hcm_thr_yearly_settings';

    protected $fillable = [
        'calendar_year',
        'eid_date',
        'payment_date',
        'calculation_cutoff_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'calendar_year' => 'integer',
            'eid_date' => 'date',
            'payment_date' => 'date',
            'calculation_cutoff_date' => 'date',
        ];
    }
}
