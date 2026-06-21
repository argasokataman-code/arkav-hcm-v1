<?php

namespace App\Models;

use App\Models\Concerns\AssignsUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class HcmTermination extends Model
{
    use AssignsUuid;
    use SoftDeletes;

    protected $table = 'hcm_terminations';

    protected $fillable = [
        'company_id',
        'user_id',
        'department',
        'termination_type',
        'termination_reason_code',
        'legal_basis_code',
        'policy_profile_key',
        'policy_formula_version',
        'workflow_stage',
        'workflow_reviewed_by_user_id',
        'workflow_reviewed_at',
        'workflow_approved_by_user_id',
        'workflow_approved_at',
        'workflow_finalized_by_user_id',
        'workflow_finalized_at',
        'non_asset_checklist',
        'reason',
        'notice_date',
        'termination_date',
        'status',
        'notes',
        'settlement_payroll_period',
        'settlement_payroll_period_id',
        'final_salary_amount',
        'final_allowance_amount',
        'final_deduction_amount',
        'asset_return_notes',
        'clearance_notes',
        'settlement_breakdown',
        'clearance_items',
        // Slice A — evidence snapshot + leave availability flag
        'settlement_evidence_snapshot',
        'leave_balance_available',
        // Slice B — workflow audit trail + optimistic lock
        'workflow_history',
        'workflow_version',
    ];

    protected $casts = [
        'settlement_payroll_period_id' => 'integer',
        'workflow_reviewed_by_user_id' => 'integer',
        'workflow_approved_by_user_id' => 'integer',
        'workflow_finalized_by_user_id' => 'integer',
        'notice_date' => 'date',
        'termination_date' => 'date',
        'workflow_reviewed_at' => 'datetime',
        'workflow_approved_at' => 'datetime',
        'workflow_finalized_at' => 'datetime',
        'non_asset_checklist' => 'array',
        'final_salary_amount' => 'decimal:2',
        'final_allowance_amount' => 'decimal:2',
        'final_deduction_amount' => 'decimal:2',
        'settlement_breakdown' => 'array',
        'clearance_items' => 'array',
        // Slice A
        'settlement_evidence_snapshot' => 'array',
        'leave_balance_available' => 'boolean',
        // Slice B
        'workflow_history' => 'array',
        'workflow_version' => 'integer',
    ];

    /**
     * Valid termination statuses
     */
    public const VALID_STATUSES = ['pending', 'approved', 'finalized', 'cancelled'];

    /**
     * Workflow stages for compliance approval trail.
     */
    public const WORKFLOW_STAGES = [
        'draft_review',
        'legal_review',
        'approved_internal',
        'finalized_execution',
        'cancelled',
    ];

    /**
     * Controlled reason codes for legal-compliance mapping.
     */
    public const TERMINATION_REASON_CODES = [
        'contract_end',
        'retirement',
        'company_efficiency',
        'misconduct',
        'company_closure',
        'force_majeure',
        'long_term_illness',
        'court_order',
        'death',
        'other',
    ];

    /**
     * Controlled legal basis references used by policy mapping.
     */
    public const LEGAL_BASIS_CODES = [
        'uu_ketenagakerjaan',
        'uu_cipta_kerja',
        'pp_35_2021',
        'pkwt_contract',
        'company_regulation',
        'collective_labor_agreement',
        'settlement_agreement',
        'court_decision',
        'other',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function settlementPayrollPeriodRef(): BelongsTo
    {
        return $this->belongsTo(HcmPayrollPeriod::class, 'settlement_payroll_period_id');
    }

    public function workflowReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workflow_reviewed_by_user_id');
    }

    public function workflowApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workflow_approved_by_user_id');
    }

    public function workflowFinalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workflow_finalized_by_user_id');
    }

    /**
     * Slice C — Checklist items stored in the dedicated table.
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(HcmTerminationChecklistItem::class, 'termination_id');
    }

    /**
     * Validate and set status attribute
     */
    protected function setStatusAttribute($value): void
    {
        if ($value && ! in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid termination status: {$value}. Must be one of: ".implode(', ', self::VALID_STATUSES)
            );
        }
        $this->attributes['status'] = $value;
    }
}
