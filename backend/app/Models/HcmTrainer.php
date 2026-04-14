<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HcmTrainer extends Model
{
    protected $table = 'hcm_trainers';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function trainings(): HasMany
    {
        return $this->hasMany(HcmTraining::class, 'trainer_id');
    }
}

