<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CalendarEvent extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'company_id',
        'title',
        'location',
        'description',
        'start_at',
        'end_at',
        'all_day',
    ];

    protected $casts = [
        'all_day' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $event): void {
            if (! $event->uuid) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }
}
