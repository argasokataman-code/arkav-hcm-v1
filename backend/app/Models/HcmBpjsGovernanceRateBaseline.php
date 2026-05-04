<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HcmBpjsGovernanceRateBaseline extends Model
{
    protected $table = 'hcm_bpjs_governance_rate_baselines';

    protected $fillable = [
        'company_id',
        'program_code',
        'contribution_party',
        'min_rate',
        'max_rate',
        'wage_base',
        'risk_category',
        'jp_salary_cap',
        'bpjs_kes_salary_cap',
        'notes',
        'updated_by_user_id',
        'updated_by_user_uuid',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'min_rate' => 'decimal:4',
        'max_rate' => 'decimal:4',
        'risk_category' => 'integer',
        'jp_salary_cap' => 'integer',
        'bpjs_kes_salary_cap' => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
