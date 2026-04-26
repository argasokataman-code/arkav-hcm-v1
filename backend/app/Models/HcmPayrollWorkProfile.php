<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmPayrollWorkProfile extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'arrangement_mode',
        'default_day_type',
        'weekly_work_days',
        'is_default',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'weekly_work_days' => 'integer',
            'is_default' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function arrangements(): HasMany
    {
        return $this->hasMany(HcmEmployeeWorkArrangement::class, 'hcm_payroll_work_profile_id');
    }
}
