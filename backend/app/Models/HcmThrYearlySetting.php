<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;

class HcmThrYearlySetting extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'calendar_year',
        'eid_date',
        'payment_date',
        'calculation_cutoff_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'calendar_year' => 'integer',
            'eid_date' => 'date',
            'payment_date' => 'date',
            'calculation_cutoff_date' => 'date',
        ];
    }
}
