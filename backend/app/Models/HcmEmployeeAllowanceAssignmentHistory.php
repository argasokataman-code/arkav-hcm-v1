<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcmEmployeeAllowanceAssignmentHistory extends Model
{
    protected $table = 'hcm_employee_allowance_assignment_histories';

    protected $fillable = [
        'company_id',
        'assignment_id',
        'assignment_uuid',
        'action_type',
        'snapshot',
        'changed_by_user_id',
        'changed_by_user_uuid',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'assignment_id' => 'integer',
        'snapshot' => 'array',
        'changed_by_user_id' => 'integer',
    ];
}
