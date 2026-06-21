<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveLedger extends Model
{
    use AssignsUuid;

    protected $table = 'leave_ledger';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'policy_id',
        'transaction_type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'occurred_on',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'policy_id' => 'integer',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_on' => 'date',
            'created_by' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'policy_id');
    }
}
