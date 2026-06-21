<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmLeaveCustomPolicy extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'leave_type_code',
        'leave_type_id',
        'leave_policy_id',
        'name',
        'days',
        'assignee_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'leave_type_id' => 'integer',
            'leave_policy_id' => 'integer',
            'days' => 'decimal:2',
            'assignee_user_ids' => 'array',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }
}
