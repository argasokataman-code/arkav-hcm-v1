<?php

namespace App\DataClasses;

/**
 * Settlement breakdown result from TerminationSettlementCalculationService.
 *
 * Contains individual line items (severance, UPMK, UPH, leave payout, etc.)
 * plus an evidence snapshot recording which source data was used (Anomaly #1).
 *
 * leavePayout is NULL (not zero) when leave service was unavailable so
 * downstream code can distinguish "not calculated" from "zero balance"
 * (Anomaly #4 — no silent zero fallback).
 */
final class TerminationSettlementBreakdown
{
    /**
     * @param list<array<string, mixed>> $lineItems   Individual settlement line items (pesangon, UPMK, UPH, leave, etc.)
     * @param array<string, mixed>       $evidenceSnapshot  Immutable data snapshot (hire_date, base_salary, leave_balance, etc.)
     * @param bool                       $leaveBalanceAvailable  false = leave service unavailable, null-safe payout
     * @param float|null                 $leavePayout   NULL when service unavailable (Anomaly #4)
     * @param float                      $totalGross    Sum of all addition items
     * @param float                      $totalDeduction  Sum of all deduction items
     * @param float                      $netPayable    totalGross - totalDeduction
     * @param string                     $calculationMethod  'policy_based' | 'manual_override'
     * @param string                     $policyProfileKey  Resolved policy profile used
     */
    public function __construct(
        public readonly array  $lineItems,
        public readonly array  $evidenceSnapshot,
        public readonly bool   $leaveBalanceAvailable,
        public readonly ?float $leavePayout,
        public readonly float  $totalGross,
        public readonly float  $totalDeduction,
        public readonly float  $netPayable,
        public readonly string $calculationMethod,
        public readonly string $policyProfileKey,
    ) {}

    /**
     * Serialize to array for storage in settlement_breakdown JSON column.
     *
     * @return array<string, mixed>
     */
    public function toStorageArray(): array
    {
        return [
            'items'                  => $this->lineItems,
            'totalGross'             => number_format($this->totalGross, 2, '.', ''),
            'totalDeduction'         => number_format($this->totalDeduction, 2, '.', ''),
            'netPayable'             => number_format($this->netPayable, 2, '.', ''),
            'calculationMethod'      => $this->calculationMethod,
            'policyProfileKey'       => $this->policyProfileKey,
            'leaveBalanceAvailable'  => $this->leaveBalanceAvailable,
            'leavePayout'            => $this->leavePayout !== null
                ? number_format($this->leavePayout, 2, '.', '')
                : null,
        ];
    }

    /**
     * Serialize evidence snapshot for settlement_evidence_snapshot JSON column.
     *
     * @return array<string, mixed>
     */
    public function toEvidenceSnapshotArray(): array
    {
        return $this->evidenceSnapshot;
    }
}
