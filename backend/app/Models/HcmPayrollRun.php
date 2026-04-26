<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmPayrollRun extends Model
{
    use AssignsUuid;
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_VOID = 'void';

    public const PURPOSE_MONTHLY = 'monthly';

    public const PURPOSE_THR = 'thr';

    public const PURPOSE_PKWT_COMPENSATION = 'pkwt_compensation';


    protected $fillable = [
        'company_id',
        'hcm_payroll_period_id',
        'meta',
        'purpose',
        'status',
        'calculated_at',
        'finalized_at',
        'finalized_by_user_id',
        'voided_at',
        'voided_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'meta' => 'array',
            'calculated_at' => 'datetime',
            'finalized_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollPeriod::class, 'hcm_payroll_period_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HcmPayrollLine::class, 'hcm_payroll_run_id');
    }
}
