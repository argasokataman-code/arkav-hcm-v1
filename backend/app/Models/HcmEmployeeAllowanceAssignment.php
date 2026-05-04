<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;

class HcmEmployeeAllowanceAssignment extends Model
{
    use AssignsUuid;

    protected $table = 'hcm_employee_allowance_assignments';

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'policy_id',
        'policy_uuid',
        'user_id',
        'user_uuid',
        'amount_override',
        'effective_start_date',
        'effective_end_date',
        'status',
        'is_active',
        'notes',
        'created_by_user_id',
        'created_by_user_uuid',
        'updated_by_user_id',
        'updated_by_user_uuid',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'policy_id' => 'integer',
        'user_id' => 'integer',
        'amount_override' => 'decimal:2',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
        'created_by_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
