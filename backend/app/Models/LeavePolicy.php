<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeavePolicy extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'leave_type_id',
        'name',
        'days_per_year',
        'min_service_months',
        'is_prorated',
        'carry_forward',
        'max_carry_days',
        'expire_after_days',
        'is_earned_leave',
        'allow_negative_balance',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'days_per_year' => 'decimal:2',
            'min_service_months' => 'integer',
            'is_prorated' => 'boolean',
            'carry_forward' => 'boolean',
            'max_carry_days' => 'integer',
            'expire_after_days' => 'integer',
            'is_earned_leave' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeavePolicyAssignment::class, 'policy_id');
    }
}
