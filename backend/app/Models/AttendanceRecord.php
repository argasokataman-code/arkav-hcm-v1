<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'status',
        'correction_status',
        'check_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_at',
        'check_out_latitude',
        'check_out_longitude',
        'break_minutes',
        'break_started_at',
        'late_minutes',
        'correction_reason',
        'correction_requested_at',
        'corrected_by_user_id',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_out_at' => 'datetime',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'break_minutes' => 'integer',
            'break_started_at' => 'datetime',
            'late_minutes' => 'integer',
            'correction_requested_at' => 'datetime',
            'corrected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
