<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmPayrollLine extends Model
{
    protected $fillable = [
        'company_id',
        'hcm_payroll_run_id',
        'user_id',
        'hcm_salary_component_id',
        'component_code',
        'component_name',
        'kind',
        'category',
        'amount',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollRun::class, 'hcm_payroll_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(HcmSalaryComponent::class, 'hcm_salary_component_id');
    }
}
