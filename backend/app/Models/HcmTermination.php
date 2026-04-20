<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class HcmTermination extends Model
{
    use SoftDeletes;
    use AssignsUuid;


    protected $table = 'hcm_terminations';

    protected $fillable = [
        'company_id',
        'user_id',
        'department',
        'termination_type',
        'reason',
        'notice_date',
        'termination_date',
        'status',
        'notes',
        'settlement_payroll_period',
        'settlement_payroll_period_id',
        'final_salary_amount',
        'final_allowance_amount',
        'final_deduction_amount',
        'asset_return_notes',
        'clearance_notes',
        'settlement_breakdown',
        'clearance_items',
    ];

    protected $casts = [
        'settlement_payroll_period_id' => 'integer',
        'notice_date' => 'date',
        'termination_date' => 'date',
        'final_salary_amount' => 'decimal:2',
        'final_allowance_amount' => 'decimal:2',
        'final_deduction_amount' => 'decimal:2',
        'settlement_breakdown' => 'array',
        'clearance_items' => 'array',
    ];

    /**
     * Valid termination statuses
     */
    public const VALID_STATUSES = ['pending', 'approved', 'finalized', 'cancelled'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function settlementPayrollPeriodRef(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollPeriod::class, 'settlement_payroll_period_id');
    }

    /**
     * Validate and set status attribute
     */
    protected function setStatusAttribute($value): void
    {
        if ($value && !in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid termination status: {$value}. Must be one of: " . implode(', ', self::VALID_STATUSES)
            );
        }
        $this->attributes['status'] = $value;
    }
}
