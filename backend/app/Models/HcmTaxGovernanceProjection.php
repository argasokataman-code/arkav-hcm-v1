<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HcmTaxGovernanceProjection extends Model
{
    use AssignsUuid;

    protected $table = 'hcm_tax_governance_projections';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'policy_uuid',
        'status',
        'version',
        'effective_date',
        'end_date',
        'last_actor_user_id',
        'last_actor_action',
        'last_actor_timestamp',
        'policy_complexity_score',
        'anomaly_flags',
        'tenant_risk_level',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'last_actor_timestamp' => 'datetime',
        'anomaly_flags' => 'array',
        'policy_complexity_score' => 'integer',
    ];

    const STATUS_DRAFT = 'draft';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_APPROVED = 'approved';

    const STATUS_PUBLISHED = 'published';

    const STATUS_SUPERSEDED = 'superseded';

    const STATUS_VOID = 'void';

    const ACTION_CREATED = 'created';

    const ACTION_SUBMITTED = 'submitted';

    const ACTION_APPROVED = 'approved';

    const ACTION_PUBLISHED = 'published';

    const ACTION_SUPERSEDED = 'superseded';

    const ACTION_VOIDED = 'voided';

    const RISK_LEVEL_GREEN = 'green';

    const RISK_LEVEL_YELLOW = 'yellow';

    const RISK_LEVEL_RED = 'red';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(HcmTaxGovernancePolicy::class, 'policy_uuid', 'uuid');
    }

    public function lastActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_actor_user_id', 'id');
    }

    /**
     * Compute risk level from anomaly flags
     */
    public function computeRiskLevel(): string
    {
        if (! $this->anomaly_flags || count($this->anomaly_flags) === 0) {
            return self::RISK_LEVEL_GREEN;
        }

        $criticalFlags = [
            'POLICY_SUPERSEDED_ACTIVE',
            'PUBLISH_FAILURE',
        ];

        $warningFlags = [
            'POLICY_DRAFT_STALE',
            'POLICY_DRIFT_DETECTED',
            'POLICY_VERSION_CONFLICT',
        ];

        $hasWarning = false;

        foreach ($this->anomaly_flags ?? [] as $flag) {
            if (in_array($flag, $criticalFlags)) {
                return self::RISK_LEVEL_RED;
            }
            if (in_array($flag, $warningFlags)) {
                $hasWarning = true;
            }
        }

        return $hasWarning ? self::RISK_LEVEL_YELLOW : self::RISK_LEVEL_GREEN;
    }
}
