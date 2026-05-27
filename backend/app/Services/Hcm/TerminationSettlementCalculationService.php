<?php

namespace App\Services\Hcm;

use App\DataClasses\TerminationSettlementBreakdown;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * TerminationSettlementCalculationService
 *
 * Calculates enriched termination settlement items on top of the prorata salary
 * already computed by HcmTerminationController::buildSettlementBreakdown().
 *
 * Produces:
 *  - Severance pay (Uang Pesangon / UP)  — based on policy profile + service years
 *  - Service award (UPMK)                — based on service years if profile eligible
 *  - Benefit substitution (UPH)          — 15% × (UP + UPMK) if profile eligible
 *  - Leave payout                        — remaining leave × daily rate (graceful fallback)
 *  - Evidence snapshot                   — immutable source data (Anomaly #1 mitigation)
 *
 * Anomaly mitigations:
 *  #1 — All source data (hire_date, base_salary, leave_balance) captured in evidence snapshot
 *  #4 — Leave service failure → leavePayout = NULL, leaveBalanceAvailable = false; NOT silent zero
 *  #6 — All queries scoped by companyId (multi-tenant guard)
 */
final class TerminationSettlementCalculationService
{
    /**
     * Calculate enriched settlement breakdown items.
     *
     * The caller (controller) already has prorata salary + PKWT items from
     * `buildSettlementBreakdown()`.  This service appends severance, UPMK,
     * UPH, and leave payout items and returns them together with an evidence
     * snapshot for storage.
     *
     * @param  int              $companyId       Multi-tenant company scope (Anomaly #6)
     * @param  int              $userId          Employee user_id
     * @param  string           $terminationDate ISO date (YYYY-MM-DD)
     * @param  string|null      $policyProfileKey  Resolved policy profile key
     * @return TerminationSettlementBreakdown
     */
    public function calculate(
        int     $companyId,
        int     $userId,
        string  $terminationDate,
        ?string $policyProfileKey,
    ): TerminationSettlementBreakdown {
        $profileConfig  = $this->resolveProfileConfig($policyProfileKey);
        $policyKey      = $policyProfileKey ?? 'general_other';

        // --- Employee profile (multi-tenant scoped, Anomaly #6) ---
        $profile = EmployeeProfile::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        $baseSalary     = (float) ($profile?->base_salary ?? 0);
        $fixedAllowance = (float) ($profile?->fixed_allowance ?? 0);
        $referenceWage  = $baseSalary;   // pesangon reference = base salary only

        $terminationAt  = Carbon::parse($terminationDate)->startOfDay();
        $hireDate       = $profile?->hire_date
            ? Carbon::parse($profile->hire_date)->startOfDay()
            : null;

        $serviceYears   = $hireDate ? $hireDate->diffInYears($terminationAt) : 0;
        $serviceMonths  = $hireDate ? $hireDate->diffInMonths($terminationAt) : 0;

        // --- Severance (UP) ---
        $upMonths       = $profileConfig['up_multiplier'] > 0
            ? $this->upMonthsByServiceYear($serviceYears)
            : 0;
        $upAmount       = round($upMonths * $profileConfig['up_multiplier'] * $referenceWage, 2);

        // --- Service Award (UPMK) ---
        $upmkMonths     = $profileConfig['upmk_applicable']
            ? $this->upmkMonthsByServiceYear($serviceYears)
            : 0;
        $upmkAmount     = round($upmkMonths * $referenceWage, 2);

        // --- Benefit Substitution (UPH) ---
        $uphRate        = (float) config('termination-policy-profiles.uph_rate', 0.15);
        $uphAmount      = $profileConfig['uph_applicable']
            ? round($uphRate * ($upAmount + $upmkAmount), 2)
            : 0.0;

        // --- Leave Payout (graceful fallback — Anomaly #4) ---
        [$leavePayoutResult, $leaveBalanceAvailable, $leaveRemainingDays] = $this->calculateLeavePayoutSafe(
            companyId:   $companyId,
            userId:      $userId,
            baseSalary:  $baseSalary,
        );

        // --- Build line items ---
        $items = [];

        if ($upAmount > 0) {
            $items[] = [
                'componentCode' => 'termination_up_severance',
                'componentName' => $profileConfig['up_label'],
                'kind'          => 'addition',
                'category'      => 'termination_severance',
                'amount'        => number_format($upAmount, 2, '.', ''),
                'bucket'        => 'severance',
                'source'        => 'termination_settlement_calculator',
                'meta'          => [
                    'serviceYears'    => $serviceYears,
                    'upBaseMonths'    => $upMonths,
                    'upMultiplier'    => $profileConfig['up_multiplier'],
                    'referenceWage'   => number_format($referenceWage, 2, '.', ''),
                    'regulationRef'   => $profileConfig['formula_notes'],
                ],
            ];
        }

        if ($upmkAmount > 0) {
            $items[] = [
                'componentCode' => 'termination_upmk_service_award',
                'componentName' => 'Service Award (UPMK)',
                'kind'          => 'addition',
                'category'      => 'termination_service_award',
                'amount'        => number_format($upmkAmount, 2, '.', ''),
                'bucket'        => 'severance',
                'source'        => 'termination_settlement_calculator',
                'meta'          => [
                    'serviceYears'  => $serviceYears,
                    'upmkMonths'    => $upmkMonths,
                    'referenceWage' => number_format($referenceWage, 2, '.', ''),
                    'regulationRef' => 'UU 13/2003 Pasal 156 ayat 3 — UPMK',
                ],
            ];
        }

        if ($uphAmount > 0) {
            $items[] = [
                'componentCode' => 'termination_uph_benefit_substitution',
                'componentName' => 'Benefit Substitution / Penggantian Hak (UPH)',
                'kind'          => 'addition',
                'category'      => 'termination_rights_substitution',
                'amount'        => number_format($uphAmount, 2, '.', ''),
                'bucket'        => 'severance',
                'source'        => 'termination_settlement_calculator',
                'meta'          => [
                    'uphRate'       => $uphRate,
                    'upBase'        => number_format($upAmount, 2, '.', ''),
                    'upmkBase'      => number_format($upmkAmount, 2, '.', ''),
                    'regulationRef' => 'UU 13/2003 Pasal 156 ayat 4 — UPH = 15% × (UP + UPMK)',
                ],
            ];
        }

        if ($leavePayoutResult !== null && $leavePayoutResult > 0) {
            $items[] = [
                'componentCode' => 'termination_leave_payout',
                'componentName' => 'Leave Payout (Sisa Cuti)',
                'kind'          => 'addition',
                'category'      => 'termination_leave_payout',
                'amount'        => number_format($leavePayoutResult, 2, '.', ''),
                'bucket'        => 'leave',
                'source'        => 'termination_settlement_calculator',
                'meta'          => [
                    'remainingDays' => $leaveRemainingDays,
                    'dailyRate'     => $referenceWage > 0
                        ? number_format(
                            $referenceWage / config('termination-policy-profiles.leave_payout_working_days_per_month', 25),
                            2, '.', ''
                        )
                        : '0.00',
                ],
            ];
        }

        if (! $leaveBalanceAvailable) {
            $items[] = [
                'componentCode' => 'termination_leave_payout_unavailable',
                'componentName' => 'Leave Payout — Balance Unavailable',
                'kind'          => 'addition',
                'category'      => 'termination_leave_payout',
                'amount'        => '0.00',
                'bucket'        => 'leave',
                'source'        => 'termination_settlement_calculator',
                'meta'          => [
                    'warning' => 'Leave balance could not be fetched from the leave service. Manual confirmation required before finalization.',
                ],
            ];
        }

        // --- Totals ---
        // @todo Anomaly #8: PPh21 deduction MUST be re-calculated via PayrollTaxCalculationService
        //   (the same service used by payroll runs) once that service is available.
        //   Currently totalDeduction sums only the manually-supplied finalDeductionAmount line items.
        //   Tracking: docs/features/termination/tracker.md — "Pending: PayrollTaxCalculationService integration"
        $totalGross     = 0.0;
        $totalDeduction = 0.0;
        foreach ($items as $item) {
            $amt = (float) ($item['amount'] ?? 0);
            if (($item['kind'] ?? '') === 'addition') {
                $totalGross += $amt;
            } else {
                $totalDeduction += $amt;
            }
        }
        $netPayable = $totalGross - $totalDeduction;

        // --- Evidence snapshot (Anomaly #1 — immutable source data) ---
        $evidenceSnapshot = [
            'snapshot_at'       => now()->toIso8601String(),
            'company_id'        => $companyId,
            'user_id'           => $userId,
            'hire_date'         => $hireDate?->toDateString(),
            'termination_date'  => $terminationAt->toDateString(),
            'service_years'     => $serviceYears,
            'service_months'    => $serviceMonths,
            'base_salary'       => number_format($baseSalary, 2, '.', ''),
            'fixed_allowance'   => number_format($fixedAllowance, 2, '.', ''),
            'reference_wage'    => number_format($referenceWage, 2, '.', ''),
            'policy_profile_key'  => $policyKey,
            'formula_version'   => config('termination-policy-profiles.formula_version', '2026-05'),
            'up_months'         => $upMonths,
            'up_multiplier'     => $profileConfig['up_multiplier'],
            'upmk_months'       => $upmkMonths,
            'leave_balance_days'  => $leaveRemainingDays,
            'leave_balance_available' => $leaveBalanceAvailable,
        ];

        return new TerminationSettlementBreakdown(
            lineItems:             $items,
            evidenceSnapshot:      $evidenceSnapshot,
            leaveBalanceAvailable: $leaveBalanceAvailable,
            leavePayout:           $leavePayoutResult,
            totalGross:            $totalGross,
            totalDeduction:        $totalDeduction,
            netPayable:            $netPayable,
            calculationMethod:     'policy_based',
            policyProfileKey:      $policyKey,
        );
    }

    /**
     * Check if the evidence snapshot stored at calculation time has drifted from
     * the current employee profile state. Returns null if no snapshot exists.
     *
     * Used during finalization to block approval of stale settlement data (Anomaly #1).
     *
     * @param  array<string, mixed>  $storedSnapshot  Value from settlement_evidence_snapshot column
     * @return array<string, mixed>|null  ['drifted' => bool, 'fields' => []] or null if no snapshot
     */
    public function detectDrift(array $storedSnapshot, int $companyId, int $userId): ?array
    {
        if (empty($storedSnapshot)) {
            return null;
        }

        $profile = EmployeeProfile::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if (! $profile) {
            return null;
        }

        $driftedFields = [];

        $currentHireDate = $profile->hire_date
            ? Carbon::parse($profile->hire_date)->toDateString()
            : null;
        if (($storedSnapshot['hire_date'] ?? null) !== $currentHireDate) {
            $driftedFields[] = [
                'field'    => 'hire_date',
                'snapshot' => $storedSnapshot['hire_date'] ?? null,
                'current'  => $currentHireDate,
            ];
        }

        $currentBaseSalary = number_format((float) ($profile->base_salary ?? 0), 2, '.', '');
        if (($storedSnapshot['base_salary'] ?? null) !== $currentBaseSalary) {
            $driftedFields[] = [
                'field'    => 'base_salary',
                'snapshot' => $storedSnapshot['base_salary'] ?? null,
                'current'  => $currentBaseSalary,
            ];
        }

        return [
            'drifted' => count($driftedFields) > 0,
            'fields'  => $driftedFields,
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Safe leave payout calculation — returns [amount|null, available, remainingDays].
     * If the leave service throws or returns unexpected data, returns [null, false, 0]
     * so caller can set leave_balance_available = false and block finalization
     * without manual confirmation (Anomaly #4: no silent zero fallback).
     *
     * @return array{float|null, bool, float}
     */
    private function calculateLeavePayoutSafe(int $companyId, int $userId, float $baseSalary): array
    {
        try {
            $currentYear = now()->year;

            // Resolve EmployeeProfile.id — leave balances are keyed to profile.id, not user.id
            $profileId = EmployeeProfile::query()
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->value('id');

            if (! $profileId) {
                // No profile means no leave balance — not an error
                return [0.0, true, 0.0];
            }

            // Anomaly #6: scoped by company_id
            $balance = EmployeeLeaveBalance::query()
                ->where('company_id', $companyId)
                ->where('employee_id', $profileId)
                ->where('year', $currentYear)
                ->sum(
                    \Illuminate\Support\Facades\DB::raw('GREATEST(0, balance - used)')
                );

            $remainingDays = max(0.0, (float) $balance);

            if ($baseSalary <= 0) {
                return [0.0, true, $remainingDays];
            }

            $workingDaysPerMonth = (int) config('termination-policy-profiles.leave_payout_working_days_per_month', 25);
            $dailyRate           = $baseSalary / $workingDaysPerMonth;
            $leavePayout         = round($remainingDays * $dailyRate, 2);

            return [$leavePayout, true, $remainingDays];
        } catch (\Throwable $e) {
            // Anomaly #4: do NOT fall back to silent zero — return null so finalization is blocked
            Log::warning('TerminationSettlementCalculationService: leave balance unavailable', [
                'company_id' => $companyId,
                'user_id'    => $userId,
                'error'      => $e->getMessage(),
            ]);

            return [null, false, 0.0];
        }
    }

    /**
     * Resolve profile config from termination-policy-profiles.php config.
     *
     * @return array<string, mixed>
     */
    private function resolveProfileConfig(?string $policyProfileKey): array
    {
        $key      = $policyProfileKey ?? 'general_other';
        $profiles = config('termination-policy-profiles.profiles', []);

        return $profiles[$key] ?? $profiles['general_other'] ?? [
            'up_multiplier'   => 0.0,
            'upmk_applicable' => false,
            'uph_applicable'  => false,
            'up_label'        => 'Severance Pay',
            'formula_notes'   => 'Unknown policy profile.',
        ];
    }

    /**
     * Statutory UP (pesangon) months scale — UU No.13/2003 Pasal 156 ayat 2.
     *
     * Returns the number of months of salary for the given service years.
     */
    private function upMonthsByServiceYear(int $serviceYears): int
    {
        return match (true) {
            $serviceYears < 1  => 1,   // < 1 year: 1 month
            $serviceYears === 1 => 1,
            $serviceYears === 2 => 2,
            $serviceYears === 3 => 3,
            $serviceYears === 4 => 4,
            $serviceYears === 5 => 5,
            $serviceYears === 6 => 6,
            $serviceYears === 7 => 7,
            default             => 9,  // ≥ 8 years: 9 months (capped)
        };
    }

    /**
     * Statutory UPMK (service award) months scale — UU No.13/2003 Pasal 156 ayat 3.
     */
    private function upmkMonthsByServiceYear(int $serviceYears): int
    {
        return match (true) {
            $serviceYears < 3   => 0,
            $serviceYears < 6   => 2,
            $serviceYears < 9   => 3,
            $serviceYears < 12  => 4,
            $serviceYears < 15  => 5,
            $serviceYears < 18  => 6,
            $serviceYears < 21  => 7,
            $serviceYears < 24  => 8,
            default             => 10, // ≥ 24 years: 10 months
        };
    }
}
