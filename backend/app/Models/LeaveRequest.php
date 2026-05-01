<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class LeaveRequest extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }

            if (Schema::hasColumn($record->getTable(), 'user_uuid') && ! $record->user_uuid && $record->user_id) {
                $record->user_uuid = (string) (User::query()->where('id', $record->user_id)->value('uuid') ?? '');
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
