<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmOvertimeType extends Model
{
    protected $table = 'hcm_overtime_types';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'payment_multiplier',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'payment_multiplier' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'hcm_overtime_type_id');
    }
}
