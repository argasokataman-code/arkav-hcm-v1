<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;

class HcmEmployeeAllowancePolicy extends Model
{
    use AssignsUuid;

    protected $table = 'hcm_employee_allowance_policies';

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'code',
        'name',
        'allowance_type',
        'is_taxable',
        'is_mandatory',
        'default_amount',
        'frequency',
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
        'is_taxable' => 'boolean',
        'is_mandatory' => 'boolean',
        'default_amount' => 'decimal:2',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
        'created_by_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
