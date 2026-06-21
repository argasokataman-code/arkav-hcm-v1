<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class HcmTraining extends Model
{
    use AssignsUuid;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (Schema::hasColumn($record->getTable(), 'company_uuid') && ! $record->company_uuid && $record->company_id) {
                $record->company_uuid = (string) (Company::query()->where('id', $record->company_id)->value('uuid') ?? '');
            }
        });
    }

    protected $fillable = [
        'company_id',
        'company_uuid',
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
        'company_uuid' => 'string',
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
