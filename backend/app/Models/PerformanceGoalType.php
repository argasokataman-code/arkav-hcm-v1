<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceGoalType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function goals(): HasMany
    {
        return $this->hasMany(PerformanceGoal::class, 'goal_type_id');
    }
}

