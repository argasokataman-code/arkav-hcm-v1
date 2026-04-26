<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcmSmartPlannerSetting extends Model
{
    protected $fillable = [
        'company_id',
        'company_uuid',
        'default_rules',
        'forbidden_transitions',
        'created_by_user_id',
        'created_by_user_uuid',
        'updated_by_user_id',
        'updated_by_user_uuid',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'company_uuid' => 'string',
            'default_rules' => 'array',
            'forbidden_transitions' => 'array',
            'created_by_user_id' => 'integer',
            'created_by_user_uuid' => 'string',
            'updated_by_user_id' => 'integer',
            'updated_by_user_uuid' => 'string',
        ];
    }
}
