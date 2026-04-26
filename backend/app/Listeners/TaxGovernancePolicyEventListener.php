<?php

namespace App\Listeners;

use App\Events\TaxGovernancePolicyTransitioned;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\HcmTaxGovernanceProjection;
use App\Models\HcmTaxGovernanceAnomaly;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TaxGovernancePolicyEventListener
{
    /**
     * Update projection and detect anomalies when policy transitions
     */
    public function handle(TaxGovernancePolicyTransitioned $event): void
    {
        $policy = $event->policy;
        $actionMap = [
            HcmTaxGovernancePolicy::STATUS_SUBMITTED => HcmTaxGovernanceProjection::ACTION_SUBMITTED,
            HcmTaxGovernancePolicy::STATUS_APPROVED => HcmTaxGovernanceProjection::ACTION_APPROVED,
            HcmTaxGovernancePolicy::STATUS_PUBLISHED => HcmTaxGovernanceProjection::ACTION_PUBLISHED,
            HcmTaxGovernancePolicy::STATUS_SUPERSEDED => HcmTaxGovernanceProjection::ACTION_SUPERSEDED,
            HcmTaxGovernancePolicy::STATUS_VOID => HcmTaxGovernanceProjection::ACTION_VOIDED,
        ];

        $action = $actionMap[$event->newStatus] ?? HcmTaxGovernanceProjection::ACTION_CREATED;

        // Update or create projection  
        $projection = HcmTaxGovernanceProjection::firstOrCreate(
            ['policy_uuid' => $policy->id],
            [
                'company_id' => $policy->company_id,
                'status' => $policy->status,
                'version' => $policy->version,
                'effective_date' => $policy->effective_date,
                'end_date' => $policy->end_date,
                'last_actor_user_id' => $event->actorUserId,
                'last_actor_action' => $action,
                'last_actor_timestamp' => now(),
                'policy_complexity_score' => $policy->rule_count ?? 0,
                'anomaly_flags' => [],
            ]
        );

        // Update projection for state transitions
        if ($projection->wasRecentlyCreated === false) {
            $projection->update([
                'status' => $policy->status,
                'version' => $policy->version,
                'effective_date' => $policy->effective_date,
                'end_date' => $policy->end_date,
                'last_actor_user_id' => $event->actorUserId,
                'last_actor_action' => $action,
                'last_actor_timestamp' => now(),
            ]);
        }

        // Detect and record anomalies
        $this->detectAnomalies($policy, $projection);

        // Update projection risk level based on anomalies
        $projection->tenant_risk_level = $projection->computeRiskLevel();
        $projection->save();
    }

    /**
     * Detect and record anomalies based on policy state
     */
    private function detectAnomalies(HcmTaxGovernancePolicy $policy, HcmTaxGovernanceProjection $projection): void
    {
        // Clear previously detected anomalies for this policy when it transitions
        HcmTaxGovernanceAnomaly::where('affected_policy_id', $policy->id)
            ->whereNull('resolved_at')
            ->delete();

        $anomalies = [];

        // Detect: policy draft stale (created > 30 days ago, still draft)
        if ($policy->status === HcmTaxGovernancePolicy::STATUS_DRAFT) {
            if ($policy->created_at->diffInDays(now()) > 30) {
                $anomalies[] = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'company_id' => $policy->company_id,
                    'anomaly_type' => HcmTaxGovernanceAnomaly::TYPE_POLICY_DRAFT_STALE,
                    'severity' => HcmTaxGovernanceAnomaly::SEVERITY_INFO,
                    'affected_policy_id' => $policy->id,
                    'description' => sprintf(
                        'Draft policy created %d days ago but not submitted for approval.',
                        $policy->created_at->diffInDays(now())
                    ),
                    'evidence_snapshot' => [
                        'created_at' => $policy->created_at->toIso8601String(),
                        'days_stale' => $policy->created_at->diffInDays(now()),
                    ],
                    'detected_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Detect: published policy older than 90 days (drift)
        if ($policy->status === HcmTaxGovernancePolicy::STATUS_PUBLISHED) {
            if ($policy->published_at && $policy->published_at->diffInDays(now()) > 90) {
                $anomalies[] = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'company_id' => $policy->company_id,
                    'anomaly_type' => HcmTaxGovernanceAnomaly::TYPE_DRIFT_DETECTED,
                    'severity' => HcmTaxGovernanceAnomaly::SEVERITY_WARNING,
                    'affected_policy_id' => $policy->id,
                    'description' => sprintf(
                        'Published policy version %d last updated %d days ago; may need review for updates.',
                        $policy->version,
                        $policy->published_at->diffInDays(now())
                    ),
                    'evidence_snapshot' => [
                        'published_at' => $policy->published_at->toIso8601String(),
                        'days_old' => $policy->published_at->diffInDays(now()),
                        'version' => $policy->version,
                    ],
                    'detected_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Batch insert anomalies
        if (!empty($anomalies)) {
            HcmTaxGovernanceAnomaly::insert($anomalies);
        }

        // Update projection anomaly flags
        $flags = [];
        if (!empty($anomalies)) {
            $flags = array_unique(array_map(fn($a) => $a['anomaly_type'], $anomalies));
        }
        $projection->anomaly_flags = $flags;
        $projection->save();
    }
}
