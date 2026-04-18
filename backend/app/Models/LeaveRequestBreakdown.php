<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaveRequestBreakdown extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (! Schema::hasColumn($record->getTable(), 'uuid')) {
                return;
            }

            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'leave_request_id',
        'leave_date',
        'unit_type',
        'session',
        'minutes',
        'is_working_day',
        'is_holiday',
        'holiday_name',
        'holiday_calendar_id',
        'deducted_days',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'leave_request_id' => 'integer',
            'holiday_calendar_id' => 'integer',
            'leave_date' => 'date',
            'minutes' => 'integer',
            'is_working_day' => 'boolean',
            'is_holiday' => 'boolean',
            'deducted_days' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function holidayCalendar(): BelongsTo
    {
        return $this->belongsTo(HolidayCalendar::class, 'holiday_calendar_id');
    }
}
