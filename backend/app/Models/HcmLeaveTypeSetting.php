<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HcmLeaveTypeSetting extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'company_id',
        'code',
        'leave_type_id',
        'name',
        'is_enabled',
        'days',
        'carry_forward',
        'max_carry_days',
        'earned_leave',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'company_id'    => 'integer',
            'leave_type_id' => 'integer',
            'is_enabled'    => 'boolean',
            'days'          => 'decimal:2',
            'carry_forward' => 'boolean',
            'earned_leave'  => 'boolean',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
