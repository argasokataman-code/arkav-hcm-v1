<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;

class HcmBpjsGovernancePolicy extends Model
{
    use AssignsUuid;

    protected $table = 'hcm_bpjs_governance_policies';

    protected $fillable = [
        'uuid',
        'company_id',
        'company_uuid',
        'program_code',
        'contribution_party',
        'rate_percent',
        'wage_base',
        'effective_start_date',
        'effective_end_date',
        'legal_basis',
        'notes',
        'is_active',
        'created_by_user_id',
        'created_by_user_uuid',
        'updated_by_user_id',
        'updated_by_user_uuid',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'rate_percent' => 'decimal:4',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
        'created_by_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
