<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HcmLeaveTypeSetting extends Model
{
    protected $table = 'hcm_leave_type_settings';

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
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
            'leave_type_id' => 'integer',
            'is_enabled' => 'boolean',
            'days' => 'decimal:2',
            'carry_forward' => 'boolean',
            'earned_leave' => 'boolean',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
