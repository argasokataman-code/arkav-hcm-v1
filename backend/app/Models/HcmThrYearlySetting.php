<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HcmThrYearlySetting extends Model
{
    protected $table = 'hcm_thr_yearly_settings';

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

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
