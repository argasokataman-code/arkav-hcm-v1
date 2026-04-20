<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Holiday extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'title',
        'holiday_date',
        'description',
        'is_active',
        'source',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(HolidayCalendar::class, 'holiday_id');
    }
}
