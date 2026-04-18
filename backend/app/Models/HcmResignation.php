<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;

class HcmResignation extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    protected $table = 'hcm_resignations';

    protected $fillable = [
        'company_id',
        'user_id',
        'department',
        'reason',
        'notice_date',
        'resignation_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'resignation_date' => 'date',
    ];

    /**
     * Valid resignation statuses
     */
    public const VALID_STATUSES = ['pending', 'approved', 'cancelled'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Validate and set status attribute
     */
    protected function setStatusAttribute($value): void
    {
        if ($value && !in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid resignation status: {$value}. Must be one of: " . implode(', ', self::VALID_STATUSES)
            );
        }
        $this->attributes['status'] = $value;
    }
}
