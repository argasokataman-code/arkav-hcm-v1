<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcmShift extends Model
{
    protected $table = 'hcm_shifts';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
