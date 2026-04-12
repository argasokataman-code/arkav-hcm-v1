<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}

