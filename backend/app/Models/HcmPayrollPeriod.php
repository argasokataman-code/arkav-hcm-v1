<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmPayrollPeriod extends Model
{
    use AssignsUuid;
    public const STATUS_OPEN = 'open';

    /** Set when a run for this period has been finalized (posted payslip). */
    public const STATUS_POSTED = 'posted';


    protected $fillable = [
        'company_id',
        'period_year',
        'period_month',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'period_year' => 'integer',
            'period_month' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(HcmPayrollRun::class, 'hcm_payroll_period_id');
    }
}
