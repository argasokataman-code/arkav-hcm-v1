<?php

namespace App\Http\Controllers\Api\Payroll\Concerns;

use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
use Illuminate\Support\Collection;

trait BuildsPayrollRunPayloads
{
    /**
     * @return array<string, mixed>
     */
    private function paymentSummary(HcmPayrollRun $run): array
    {
        $lines = $run->relationLoaded('lines')
            ? $run->lines
            : $run->lines()->get(['user_id', 'meta']);

        $perUser = $lines->groupBy('user_id');
        $paidUserIds = [];
        $latestPaidAt = null;
        $latestGatewayReference = null;

        foreach ($perUser as $userId => $items) {
            $paidMeta = collect($items)
                ->map(fn (HcmPayrollLine $line) => is_array($line->meta) ? $line->meta : [])
                ->first(fn (array $meta) => strtolower((string) ($meta['paymentStatus'] ?? 'unpaid')) === 'paid');

            if ($paidMeta === null) {
                continue;
            }

            $paidUserIds[] = (int) $userId;
            $paidAt = isset($paidMeta['paidAt']) ? (string) $paidMeta['paidAt'] : null;
            if ($paidAt !== null && ($latestPaidAt === null || strtotime($paidAt) >= strtotime((string) $latestPaidAt))) {
                $latestPaidAt = $paidAt;
                $latestGatewayReference = isset($paidMeta['gatewayReference']) ? (string) $paidMeta['gatewayReference'] : $latestGatewayReference;
            }
        }

        $employeeCount = $perUser->count();
        $paidCount = count($paidUserIds);
        $status = $paidCount === 0
            ? 'unpaid'
            : ($paidCount < $employeeCount ? 'partial' : 'paid');

        return [
            'status' => $status,
            'employeeCount' => $employeeCount,
            'paidEmployeeCount' => $paidCount,
            'paidUserIds' => array_values(array_unique($paidUserIds)),
            'paidAt' => $latestPaidAt,
            'gatewayReference' => $latestGatewayReference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePeriodBrief(HcmPayrollPeriod $period): array
    {
        return [
            'id' => $period->id,
            'periodYear' => $period->period_year,
            'periodMonth' => $period->period_month,
            'status' => $period->status,
        ];
    }

    private function latestFinalizedRunForPurpose(int $periodId, string $purpose, ?int $companyId = null): ?HcmPayrollRun
    {
        $query = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $periodId)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', $purpose)
            ->orderByDesc('id');
        $this->applyTenantScope($query, $companyId);

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRunBrief(HcmPayrollRun $run): array
    {
        $payment = $this->paymentSummary($run);

        return [
            'id' => $run->id,
            'purpose' => $run->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => $run->status,
            'finalizedAt' => $run->finalized_at?->toIso8601String(),
            'voidedAt' => $run->voided_at?->toIso8601String(),
            'voidedByUserId' => $run->voided_by_user_id,
            'paymentStatus' => $payment['status'],
            'paidAt' => $payment['paidAt'],
            'paidEmployeeCount' => $payment['paidEmployeeCount'],
            'employeeCount' => $payment['employeeCount'],
            'gatewayReference' => $payment['gatewayReference'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRun(HcmPayrollRun $run): array
    {
        $payment = $this->paymentSummary($run);
        $totals = $this->runTotals($run);
        $meta = is_array($run->meta) ? $run->meta : [];
        $out = [
            'id' => $run->id,
            'payrollPeriodId' => $run->hcm_payroll_period_id,
            'purpose' => $run->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => $run->status,
            'calculatedAt' => $run->calculated_at?->toIso8601String(),
            'finalizedAt' => $run->finalized_at?->toIso8601String(),
            'finalizedByUserId' => $run->finalized_by_user_id,
            'finalizedByUserName' => $run->relationLoaded('finalizedBy') ? $run->finalizedBy?->name : null,
            'voidedAt' => $run->voided_at?->toIso8601String(),
            'voidedByUserId' => $run->voided_by_user_id,
            'voidedByUserName' => $run->relationLoaded('voidedBy') ? $run->voidedBy?->name : null,
            'policySnapshot' => is_array($meta['policySnapshot'] ?? null) ? $meta['policySnapshot'] : null,
            'taxGovernancePolicy' => is_array($meta['taxGovernancePolicy'] ?? null) ? $meta['taxGovernancePolicy'] : null,
            'lateArrivalBuffer' => is_array($meta['lateArrivalBuffer'] ?? null) ? $meta['lateArrivalBuffer'] : null,
            'paymentStatus' => $payment['status'],
            'paidAt' => $payment['paidAt'],
            'paidEmployeeCount' => $payment['paidEmployeeCount'],
            'employeeCount' => $payment['employeeCount'],
            'gatewayReference' => $payment['gatewayReference'],
            'totals' => $totals,
        ];
        if ($run->relationLoaded('period') && $run->period) {
            $out['period'] = $this->serializePeriodBrief($run->period);
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function auditTrailForRun(HcmPayrollRun $run): array
    {
        $trail = [];
        if ($run->calculated_at) {
            $trail[] = [
                'event' => 'calculated',
                'at' => $run->calculated_at->toIso8601String(),
                'actorUserId' => null,
                'actorName' => null,
            ];
        }
        if ($run->finalized_at) {
            $trail[] = [
                'event' => 'finalized',
                'at' => $run->finalized_at->toIso8601String(),
                'actorUserId' => $run->finalized_by_user_id,
                'actorName' => $run->relationLoaded('finalizedBy') ? $run->finalizedBy?->name : null,
            ];
        }
        if ($run->voided_at) {
            $trail[] = [
                'event' => 'voided',
                'at' => $run->voided_at->toIso8601String(),
                'actorUserId' => $run->voided_by_user_id,
                'actorName' => $run->relationLoaded('voidedBy') ? $run->voidedBy?->name : null,
            ];
        }

        $payment = $this->paymentSummary($run);
        if (! empty($payment['paidAt'])) {
            $trail[] = [
                'event' => 'disbursed',
                'at' => $payment['paidAt'],
                'actorUserId' => null,
                'actorName' => null,
                'gatewayReference' => $payment['gatewayReference'] ?? null,
            ];
        }

        return $trail;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLine(HcmPayrollLine $line): array
    {
        $meta = is_array($line->meta) ? $line->meta : [];
        $affectsNetPay = $this->lineAffectsNetPay($line);

        return [
            'id' => $line->id,
            'userId' => $line->user_id,
            'userName' => $line->relationLoaded('user') ? $line->user?->name : ($meta['userName'] ?? null),
            'salaryComponentId' => $line->hcm_salary_component_id,
            'componentCode' => $line->component_code,
            'componentName' => $line->component_name,
            'kind' => $line->kind,
            'category' => $line->category,
            'amount' => round((float) $line->amount, 2),
            'sortOrder' => $line->sort_order,
            'paymentStatus' => $meta['paymentStatus'] ?? 'unpaid',
            'paidAt' => $meta['paidAt'] ?? null,
            'gatewayReference' => $meta['gatewayReference'] ?? null,
            'affectsNetPay' => $affectsNetPay,
            'meta' => $meta,
        ];
    }

    private function lineAffectsNetPay(HcmPayrollLine $line): bool
    {
        $meta = is_array($line->meta) ? $line->meta : [];
        if (array_key_exists('affectsNetPay', $meta)) {
            return (bool) $meta['affectsNetPay'];
        }

        return (string) $line->category !== 'employer_cost_display';
    }

    /**
     * @return array{thrUserIds: list<int>, compensationUserIds: list<int>}
     */
    private function specialRecipientsForRunPeriod(HcmPayrollRun $run, ?int $companyId): array
    {
        $runsQuery = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $run->hcm_payroll_period_id)
            ->whereIn('purpose', [
                HcmPayrollRun::PURPOSE_THR,
                HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
                'pkwt_comp',
            ])
            ->whereIn('status', [
                HcmPayrollRun::STATUS_DRAFT,
                HcmPayrollRun::STATUS_FINALIZED,
            ]);
        $this->applyTenantScope($runsQuery, $companyId);

        $specialRuns = $runsQuery->get(['id', 'purpose']);
        if ($specialRuns->isEmpty()) {
            return [
                'thrUserIds' => [],
                'compensationUserIds' => [],
            ];
        }

        $runPurposeById = $specialRuns
            ->mapWithKeys(fn (HcmPayrollRun $item): array => [(int) $item->id => (string) $item->purpose]);

        $lines = HcmPayrollLine::query()
            ->whereIn('hcm_payroll_run_id', $specialRuns->pluck('id')->all())
            ->get();

        $thrUserIds = [];
        $compensationUserIds = [];

        foreach ($lines->groupBy('hcm_payroll_run_id') as $runId => $items) {
            $purpose = (string) ($runPurposeById[(int) $runId] ?? '');
            if ($purpose === '') {
                continue;
            }

            $eligibleUserIds = [];
            foreach ($items->groupBy('user_id') as $userId => $userItems) {
                $net = 0.0;
                foreach ($userItems as $line) {
                    if (! $this->lineAffectsNetPay($line)) {
                        continue;
                    }

                    $amount = (float) $line->amount;
                    if ((string) $line->kind === 'addition') {
                        $net += $amount;
                    } elseif ((string) $line->kind === 'deduction') {
                        $net -= $amount;
                    }
                }

                if ($net > 0) {
                    $eligibleUserIds[] = (int) $userId;
                }
            }

            if ($purpose === HcmPayrollRun::PURPOSE_THR) {
                $thrUserIds = array_merge($thrUserIds, $eligibleUserIds);
                continue;
            }

            if ($purpose === HcmPayrollRun::PURPOSE_PKWT_COMPENSATION || $purpose === 'pkwt_comp') {
                $compensationUserIds = array_merge($compensationUserIds, $eligibleUserIds);
            }
        }

        return [
            'thrUserIds' => array_values(array_unique($thrUserIds)),
            'compensationUserIds' => array_values(array_unique($compensationUserIds)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runTotals(HcmPayrollRun $run): array
    {
        $lines = $run->relationLoaded('lines')
            ? $run->lines
            : $run->lines()->get(['kind', 'amount', 'user_id', 'category', 'meta']);

        $earningsTotal = 0.0;
        $deductionsTotal = 0.0;
        foreach ($lines as $line) {
            if (! $this->lineAffectsNetPay($line)) {
                continue;
            }
            if ((string) $line->kind === 'addition') {
                $earningsTotal += (float) $line->amount;
            } elseif ((string) $line->kind === 'deduction') {
                $deductionsTotal += (float) $line->amount;
            }
        }

        $earningsTotal = round($earningsTotal, 2);
        $deductionsTotal = round($deductionsTotal, 2);

        return [
            'lineCount' => $lines->count(),
            'employeeCount' => $lines->pluck('user_id')->filter()->unique()->count(),
            'earningsTotal' => $earningsTotal,
            'deductionsTotal' => $deductionsTotal,
            'overtimeTotal' => $this->summarizeOvertimeFromPayrollLines($lines)['amountTotal'],
            'netPay' => round($earningsTotal - $deductionsTotal, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRunDetailSummary(HcmPayrollRun $run): array
    {
        $lines = $run->relationLoaded('lines') ? $run->lines : $run->lines()->with('user:id,name')->get();
        $totals = $this->runTotals($run);

        $employeeBreakdown = $lines
            ->groupBy('user_id')
            ->map(function ($items, $userId): array {
                $first = $items->first();
                $earningsTotal = 0.0;
                $deductionsTotal = 0.0;

                foreach ($items as $line) {
                    if (! $this->lineAffectsNetPay($line)) {
                        continue;
                    }
                    if ((string) $line->kind === 'addition') {
                        $earningsTotal += (float) $line->amount;
                    } elseif ((string) $line->kind === 'deduction') {
                        $deductionsTotal += (float) $line->amount;
                    }
                }

                $earningsTotal = round($earningsTotal, 2);
                $deductionsTotal = round($deductionsTotal, 2);

                return [
                    'userId' => (int) $userId,
                    'userName' => $first?->user?->name ?? ('User '.$userId),
                    'lineCount' => $items->count(),
                    'earningsTotal' => $earningsTotal,
                    'deductionsTotal' => $deductionsTotal,
                    'netPay' => round($earningsTotal - $deductionsTotal, 2),
                ];
            })
            ->sortByDesc('netPay')
            ->values()
            ->all();

        $componentBreakdown = $lines
            ->groupBy(fn (HcmPayrollLine $line) => ($line->component_code ?? '').'|'.($line->component_name ?? ''))
            ->map(function ($items, $key): array {
                [$code, $name] = array_pad(explode('|', (string) $key, 2), 2, '');
                $amountTotal = round((float) $items->sum('amount'), 2);
                $kind = (string) ($items->first()?->kind ?? 'addition');

                return [
                    'componentCode' => $code,
                    'componentName' => $name !== '' ? $name : ($code !== '' ? strtoupper(str_replace('_', ' ', $code)) : 'Komponen'),
                    'kind' => $kind,
                    'lineCount' => $items->count(),
                    'amountTotal' => $amountTotal,
                ];
            })
            ->sortByDesc('amountTotal')
            ->values()
            ->all();

        return [
            'totals' => $totals,
            'overtime' => $this->summarizeOvertimeFromPayrollLines($lines),
            'employeeBreakdown' => $employeeBreakdown,
            'componentBreakdown' => $componentBreakdown,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array{amountTotal: float, lineCount: int}
     */
    private function summarizeOvertimeFromSerializedLines(Collection $lines): array
    {
        $overtimeLines = $lines
            ->filter(fn (array $line): bool => $this->isOvertimeComponentCode($line['componentCode'] ?? null))
            ->values();

        return [
            'amountTotal' => round((float) $overtimeLines->sum(fn (array $line): float => (float) ($line['amount'] ?? 0)), 2),
            'lineCount' => $overtimeLines->count(),
        ];
    }

    /**
     * @param  Collection<int, HcmPayrollLine>  $lines
     * @return array{amountTotal: float, lineCount: int}
     */
    private function summarizeOvertimeFromPayrollLines(Collection $lines): array
    {
        $overtimeLines = $lines
            ->filter(fn (HcmPayrollLine $line): bool => $this->isOvertimeComponentCode($line->component_code))
            ->values();

        return [
            'amountTotal' => round((float) $overtimeLines->sum('amount'), 2),
            'lineCount' => $overtimeLines->count(),
        ];
    }

    /**
     * @return array{amountTotal: float, lineCount: int}
     */
    private function emptyOvertimeSummary(): array
    {
        return [
            'amountTotal' => 0.0,
            'lineCount' => 0,
        ];
    }

    private function isOvertimeComponentCode(mixed $componentCode): bool
    {
        return (string) $componentCode === HcmSalaryComponent::CODE_OVERTIME_PAY;
    }
}