<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcmSmartPlannerSetting extends Model
{
    protected $fillable = [
        'company_id',
        'default_rules',
        'forbidden_transitions',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'default_rules' => 'array',
            'forbidden_transitions' => 'array',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }
}
