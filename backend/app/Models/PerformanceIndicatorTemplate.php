<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceIndicatorTemplate extends Model
{
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

