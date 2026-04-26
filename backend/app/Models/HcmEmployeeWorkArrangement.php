<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmEmployeeWorkArrangement extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'hcm_payroll_work_profile_id',
        'arrangement_mode',
        'default_day_type',
        'weekly_work_days',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'hcm_payroll_work_profile_id' => 'integer',
            'weekly_work_days' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollWorkProfile::class, 'hcm_payroll_work_profile_id');
    }
}
