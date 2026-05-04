<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcmEmployeeAllowancePolicyHistory extends Model
{
    protected $table = 'hcm_employee_allowance_policy_histories';

    protected $fillable = [
        'company_id',
        'policy_id',
        'policy_uuid',
        'action_type',
        'snapshot',
        'changed_by_user_id',
        'changed_by_user_uuid',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'policy_id' => 'integer',
        'snapshot' => 'array',
        'changed_by_user_id' => 'integer',
    ];
}
