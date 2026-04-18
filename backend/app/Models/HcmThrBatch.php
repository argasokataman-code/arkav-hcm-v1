<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HcmThrBatch extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ASSIGNED = 'assigned';

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'company_id',
        'calendar_year',
        'hcm_thr_yearly_setting_id',
        'cutoff_date',
        'grand_total_eligible',
        'eligible_line_count',
        'total_line_count',
        'status',
        'assigned_at',
        'assigned_by_user_id',
        'hcm_payroll_period_id',
        'hcm_payroll_run_id',
        'generated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'calendar_year' => 'integer',
            'cutoff_date' => 'date',
            'grand_total_eligible' => 'decimal:2',
            'eligible_line_count' => 'integer',
            'total_line_count' => 'integer',
            'assigned_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HcmThrBatchLine::class, 'hcm_thr_batch_id');
    }

    public function yearlySetting(): BelongsTo
    {
        return $this->belongsTo(HcmThrYearlySetting::class, 'hcm_thr_yearly_setting_id');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollPeriod::class, 'hcm_payroll_period_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollRun::class, 'hcm_payroll_run_id');
    }

    public function disbursements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HcmThrDisbursement::class, 'hcm_thr_batch_id');
    }
}
