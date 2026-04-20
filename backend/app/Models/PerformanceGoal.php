<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PerformanceGoal extends Model
{
    use AssignsUuid;

    protected $fillable = [
        'goal_type_id',
        'user_id',
        'manager_user_id',
        'subject',
        'target_achievement',
        'start_date',
        'end_date',
        'description',
        'status',
        'progress_percent',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress_percent' => 'int',
    ];

    public function goalType(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoalType::class, 'goal_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
}

