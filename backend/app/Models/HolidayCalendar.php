<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HolidayCalendar extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'company_id',
        'holiday_id',
        'date',
        'name',
        'is_national',
        'is_joint_leave',
        'deduct_from_leave',
        'source',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'holiday_id' => 'integer',
            'date' => 'date',
            'is_national' => 'boolean',
            'is_joint_leave' => 'boolean',
            'deduct_from_leave' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class, 'holiday_id');
    }
}
