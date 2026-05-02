<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AssignsUuid;
use Illuminate\Support\Str;

class HcmTaxGovernanceAnomaly extends Model
{
    use AssignsUuid, HasFactory;

    protected $table = 'hcm_tax_governance_anomalies';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'company_id',
        'anomaly_type',
        'severity',
        'affected_policy_id',
        'affected_employee_id',
        'description',
        'evidence_snapshot',
        'detected_at',
        'resolved_at',
        'resolution_note',
        'acknowledged_by_user_id',
        'acknowledged_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'evidence_snapshot' => 'array',
    ];

    const TYPE_MISSING_TAX_PROFILE = 'MISSING_TAX_PROFILE';
    const TYPE_POLICY_DRAFT_STALE = 'POLICY_DRAFT_STALE';
    const TYPE_POLICY_SUPERSEDED_ACTIVE = 'POLICY_SUPERSEDED_ACTIVE';
    const TYPE_POLICY_VERSION_CONFLICT = 'POLICY_VERSION_CONFLICT';
    const TYPE_PUBLISH_FAILURE = 'PUBLISH_FAILURE';
    const TYPE_DRIFT_DETECTED = 'DRIFT_DETECTED';

    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (!is_string($model->id) || $model->id === '') {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(HcmTaxGovernancePolicy::class, 'affected_policy_id', 'uuid');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('anomaly_type', $type);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
