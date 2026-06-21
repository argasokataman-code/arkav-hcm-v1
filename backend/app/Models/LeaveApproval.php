<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApproval extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'leave_request_id',
        'approver_id',
        'level',
        'status',
        'acted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'leave_request_id' => 'integer',
            'approver_id' => 'integer',
            'level' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
