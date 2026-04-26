<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmScheduleRoster extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'work_date',
        'hcm_shift_id',
        'start_time',
        'end_time',
        'cross_day',
        'roster_status',
        'source',
        'published_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'hcm_shift_id' => 'integer',
            'work_date' => 'date',
            'cross_day' => 'boolean',
            'published_by_user_id' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HcmShift::class, 'hcm_shift_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
