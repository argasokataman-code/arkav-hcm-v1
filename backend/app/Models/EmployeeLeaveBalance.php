<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'year',
        'balance',
        'used',
        'expired',
        'carried_forward',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'employee_id' => 'integer',
            'leave_type_id' => 'integer',
            'year' => 'integer',
            'balance' => 'decimal:2',
            'used' => 'decimal:2',
            'expired' => 'decimal:2',
            'carried_forward' => 'decimal:2',
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
}
