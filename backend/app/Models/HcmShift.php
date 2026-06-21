<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;

class HcmShift extends Model
{
    use AssignsUuid;

    protected $table = 'hcm_shifts';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'shift_type',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'shift_type' => 'string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
