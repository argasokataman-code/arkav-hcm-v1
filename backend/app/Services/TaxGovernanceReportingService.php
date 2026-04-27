<?php

namespace App\Services;

use App\Models\Company;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\HcmTaxGovernancePolicyEvent;
use App\Models\HcmTaxGovernanceAnomaly;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaxGovernanceReportingService
{
    /**
     * Generate tenant self-audit report
     *
    * @param string $companyId Company numeric ID in tenant context
     * @param Carbon $periodStart
     * @param Carbon $periodEnd
     * @return array
     */
    public function generateTenantSelfAuditReport(string $companyId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $company = Company::find($companyId);
        if (! $company) {
            return [];
        }

        // Get current policy (active, most recent version)
        $currentPolicy = HcmTaxGovernancePolicy::where('company_id', $companyId)
            ->where('status', HcmTaxGovernancePolicy::STATUS_PUBLISHED)
            ->orderBy('version', 'desc')
            ->first();

        // Get policy change history within period
        $changeHistory = $this->getChangeHistory($companyId, $periodStart, $periodEnd);

        // Get payroll coverage stats
        $payrollCoverage = $this->getPayrollCoverageStats($companyId, $periodStart, $periodEnd);

        // Get unresolved anomalies
        $anomalies = $this->getUnresolvedAnomalies($companyId);

        // Build compliance checklist
        $complianceChecklist = $this->buildComplianceChecklist($currentPolicy, $payrollCoverage, $anomalies);

        return [
            'company_id' => $companyId,
            'company_name' => $company->name ?? 'Unknown',
            'report_generated_at' => now()->toIso8601String(),
            'period' => [
                'start' => $periodStart->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ],
            'policy_snapshot' => $currentPolicy ? [
                'current_version' => $currentPolicy->version,
                'effective_from' => $currentPolicy->effective_start_date?->format('Y-m-d'),
                'effective_to' => $currentPolicy->effective_end_date?->format('Y-m-d'),
                'rule_count' => count($currentPolicy->rules ?? []),
                'last_published_by' => $currentPolicy->published_by_user_id,
                'last_published_at' => $currentPolicy->published_at?->toIso8601String(),
            ] : null,
            'change_history' => $changeHistory,
            'payroll_coverage' => $payrollCoverage,
            'compliance_checklist' => $complianceChecklist,
            'anomalies_detected' => $anomalies->map(fn ($a) => [
                'anomaly_id' => $a->id,
                'type' => $a->anomaly_type,
                'severity' => $a->severity,
                'description' => $a->description,
                'detected_at' => $a->detected_at->toIso8601String(),
                'resolved' => $a->resolved_at !== null,
                'resolved_at' => $a->resolved_at?->toIso8601String(),
            ])->values()->toArray(),
        ];
    }

    /**
     * Get policy change history
     */
    private function getChangeHistory(string $companyId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $history = [];

        // Get all policy versions
        $policies = HcmTaxGovernancePolicy::where('company_id', $companyId)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($policies as $policy) {
            // Get events for this policy version
            $events = HcmTaxGovernancePolicyEvent::where('hcm_tax_governance_policy_id', $policy->id)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($events as $event) {
                $history[] = [
                    'version' => $policy->version,
                    'action' => $event->event_type,
                    'actor_user_id' => $event->actor_user_id,
                    'actor_name' => $event->actorUser?->name ?? 'Unknown',
                    'timestamp' => $event->created_at->toIso8601String(),
                    'change_summary' => (string) ($event->note ?? ''),
                ];
            }
        }

        return $history;
    }

    /**
     * Get payroll coverage statistics
     */
    private function getPayrollCoverageStats(string $companyId, Carbon $periodStart, Carbon $periodEnd): array
    {
        // TODO: Query payroll_runs table to count coverage
        // This is a placeholder that will integrate with payroll module
        return [
            'total_payroll_runs' => 45,
            'runs_under_current_policy' => 40,
            'runs_under_superseded_policy' => 5,
            'coverage_percentage' => 88.9,
        ];
    }

    /**
     * Get unresolved anomalies for company
     */
    private function getUnresolvedAnomalies(string $companyId): Collection
    {
        return HcmTaxGovernanceAnomaly::where('company_id', $companyId)
            ->whereNull('resolved_at')
            ->orderBy('detected_at', 'desc')
            ->get();
    }

    /**
     * Build compliance checklist
     */
    private function buildComplianceChecklist($currentPolicy, array $payrollCoverage, Collection $anomalies): array
    {
        $hasPublishedPolicy = $currentPolicy !== null;
        $hasRecentPublication = $currentPolicy && $currentPolicy->published_at
            && $currentPolicy->published_at->isAfter(now()->subDays(90));
        $allPayrollRunsCovered = $payrollCoverage['coverage_percentage'] >= 95;
        $noUnresolvedAnomalies = $anomalies->isEmpty();

        // Calculate readiness score (0-1)
        $score = 0;
        if ($hasPublishedPolicy) {
            $score += 0.25;
        }
        if ($hasRecentPublication) {
            $score += 0.25;
        }
        if ($allPayrollRunsCovered) {
            $score += 0.25;
        }
        if ($noUnresolvedAnomalies) {
            $score += 0.25;
        }

        return [
            'has_published_policy' => $hasPublishedPolicy,
            'has_recent_publication' => $hasRecentPublication,
            'all_payroll_runs_covered' => $allPayrollRunsCovered,
            'no_unresolved_anomalies' => $noUnresolvedAnomalies,
            'readiness_score' => round($score, 2),
        ];
    }

    /**
     * Get policy event audit trail
     */
    public function getPolicyAuditTrail(string $policyId): array
    {
        $policy = HcmTaxGovernancePolicy::find($policyId);
        if (! $policy) {
            return [];
        }

        $events = HcmTaxGovernancePolicyEvent::where('hcm_tax_governance_policy_id', $policyId)
            ->orderBy('created_at', 'asc')
            ->get();

        return $events->map(fn ($event) => [
            'event_type' => $event->event_type,
            'timestamp' => $event->created_at->toIso8601String(),
            'actor' => $event->actorUser?->name ?? 'System',
            'details' => (string) ($event->note ?? ''),
            'before_state' => $event->before_state,
            'after_state' => $event->after_state,
        ])->toArray();
    }

    /**
     * Generate anomaly snapshot
     */
    public function generateAnomalySnapshot(string $companyId): array
    {
        $anomalies = HcmTaxGovernanceAnomaly::where('company_id', $companyId)->get();

        $summary = [
            'total_anomalies' => $anomalies->count(),
            'unresolved_count' => $anomalies->whereNull('resolved_at')->count(),
            'by_severity' => [
                'info' => $anomalies->where('severity', 'info')->count(),
                'warning' => $anomalies->where('severity', 'warning')->count(),
                'critical' => $anomalies->where('severity', 'critical')->count(),
            ],
            'by_type' => $anomalies->groupBy('anomaly_type')
                ->map(fn ($group) => $group->count())
                ->toArray(),
        ];

        return $summary;
    }
}
