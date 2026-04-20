<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PerformanceIndicatorTemplate extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'name',
        'department',
        'designation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PerformanceIndicatorItem::class, 'template_id');
    }
}

