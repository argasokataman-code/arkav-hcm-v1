<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeaveRequest extends Model
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
        'user_id',
        'leave_type',
        'date_from',
        'date_to',
        'days',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'date_from' => 'date',
            'date_to' => 'date',
            'days' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(LeaveRequestBreakdown::class);
    }
}
