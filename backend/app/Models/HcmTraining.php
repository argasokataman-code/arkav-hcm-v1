<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class HcmTraining extends Model
{
    protected $table = 'hcm_trainings';

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'training_type_id',
        'trainer_id',
        'trainer_name',
        'start_date',
        'end_date',
        'description',
        'cost_cents',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'cost_cents' => 'integer',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(HcmTrainingType::class, 'training_type_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(HcmTrainer::class, 'trainer_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hcm_training_participants', 'training_id', 'user_id')
            ->withTimestamps();
    }
}

