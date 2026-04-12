<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class HcmTermination extends Model
{
    use SoftDeletes;

    protected $table = 'hcm_terminations';

    protected $fillable = [
        'user_id',
        'department',
        'termination_type',
        'reason',
        'notice_date',
        'termination_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'termination_date' => 'date',
    ];

    /**
     * Valid termination statuses
     */
    public const VALID_STATUSES = ['pending', 'approved', 'finalized', 'cancelled'];

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
                "Invalid termination status: {$value}. Must be one of: " . implode(', ', self::VALID_STATUSES)
            );
        }
        $this->attributes['status'] = $value;
    }
}
