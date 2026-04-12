<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcmTrainingType extends Model
{
    protected $table = 'hcm_training_types';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

