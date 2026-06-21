<?php

namespace App\Http\Controllers\Api\Payroll\Concerns;

use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait BuildsMonthlyPayrollReports
{
    /**
     * @param  array<string, mixed>|null  $validated
     * @return array<string, mixed>
     */
    private function buildMonthlyReportPayload(Request $request, ?array $validated = null): array
    {
        $validated ??= $this->validateMonthlyReportFilters($request);
        $companyId = $this->activeCompanyId($request);
        $query = HcmPayrollRun::query()
            ->with([
                'period',
                'lines.user:id,name,email',
                'lines.user.employeeProfile:user_id,designation,team,bank_name,bank_account_no,bank_branch',
            ])
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->whereIn('purpose', [
                HcmPayrollRun::PURPOSE_MONTHLY,
                HcmPayrollRun::PURPOSE_THR,
                HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
            ])
            ->when(isset($validated['periodYear']), function ($builder) use ($validated): void {
                $builder->whereHas('period', fn ($periodQuery) => $periodQuery->where('period_year', (int) $validated['periodYear']));
            })
            ->when(isset($validated['periodMonth']), function ($builder) use ($validated): void {
                $builder->whereHas('period', fn ($periodQuery) => $periodQuery->where('period_month', (int) $validated['periodMonth']));
            })
            ->orderByDesc('hcm_payroll_period_id')
            ->orderByDesc('id');
        $this->applyTenantScope($query, $companyId);

        $rows = $this->buildMonthlyReportRows($query->get());

        return [
            'rows' => $rows->values()->all(),
            'summary' => [
                'totalRows' => $rows->count(),
                'totalEmployees' => $rows->pluck('userId')->unique()->count(),
                'totalPeriods' => $rows->map(fn (array $row) => ($row['periodYear'] ?? 0).'-'.($row['periodMonth'] ?? 0))->unique()->count(),
                'totalNetPay' => round((float) $rows->sum(fn (array $row) => (float) ($row['totals']['netPay'] ?? 0)), 2),
                'totalOvertimePay' => round((float) $rows->sum(fn (array $row) => (float) ($row['totals']['overtimeTotal'] ?? 0)), 2),
                'totalsByPurpose' => [
                    HcmPayrollRun::PURPOSE_MONTHLY => round((float) $rows->sum(fn (array $row) => (float) (($row['breakdown'][HcmPayrollRun::PURPOSE_MONTHLY]['netPay'] ?? 0))), 2),
                    HcmPayrollRun::PURPOSE_THR => round((float) $rows->sum(fn (array $row) => (float) (($row['breakdown'][HcmPayrollRun::PURPOSE_THR]['netPay'] ?? 0))), 2),
                    HcmPayrollRun::PURPOSE_PKWT_COMPENSATION => round((float) $rows->sum(fn (array $row) => (float) (($row['breakdown'][HcmPayrollRun::PURPOSE_PKWT_COMPENSATION]['netPay'] ?? 0))), 2),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMonthlyReportFilters(Request $request): array
    {
        return $request->validate([
            'periodYear' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
    }

    /**
     * @param  Collection<int, HcmPayrollRun>  $runs
     * @return Collection<int, array<string, mixed>>
     */
    private function buildMonthlyReportRows(Collection $runs): Collection
    {
        $rows = collect();

        foreach ($runs as $run) {
            $byUser = $run->lines->groupBy('user_id');

            foreach ($byUser as $userId => $userLines) {
                $first = $userLines->first();
                $user = $first?->user;
                $profile = $user?->employeeProfile;
                $earnings = $userLines->where('kind', 'addition')->values();
                $deductions = $userLines->where('kind', 'deduction')->values();
                $earningsTotal = round((float) $earnings->sum('amount'), 2);
                $deductionsTotal = round((float) $deductions->sum('amount'), 2);
                $netPay = round($earningsTotal - $deductionsTotal, 2);
                $linePaymentStates = $userLines->map(function ($line): string {
                    $lineMeta = is_array($line->meta)
                        ? $line->meta
                        : (json_decode((string) ($line->meta ?? '{}'), true) ?: []);

                    return strtolower((string) ($lineMeta['paymentStatus'] ?? ''));
                })->filter(fn ($state) => $state !== '')->values();
                $paidCount = $linePaymentStates->filter(fn ($state) => $state === 'paid')->count();
                $paymentStatus = $run->status === HcmPayrollRun::STATUS_FINALIZED ? 'paid' : 'unpaid';
                if ($paidCount > 0 && $paidCount < $linePaymentStates->count()) {
                    $paymentStatus = 'partial';
                } elseif ($linePaymentStates->count() > 0 && $paidCount === 0) {
                    $paymentStatus = 'unpaid';
                } elseif ($linePaymentStates->count() > 0 && $paidCount === $linePaymentStates->count()) {
                    $paymentStatus = 'paid';
                }

                $metaRaw = $first?->meta;
                $meta = is_array($metaRaw)
                    ? $metaRaw
                    : (json_decode((string) ($metaRaw ?? '{}'), true) ?: []);
                $employeeName = $user?->name ?? ($meta['userName'] ?? ('User '.$userId));
                $key = ($run->period?->period_year ?? 0).'-'.($run->period?->period_month ?? 0).'-'.$userId;
                $existing = $rows->get($key, [
                    'rowKey' => $key,
                    'periodYear' => (int) ($run->period?->period_year ?? 0),
                    'periodMonth' => (int) ($run->period?->period_month ?? 0),
                    'userId' => (int) $userId,
                    'employeeName' => $employeeName,
                    'email' => $user?->email,
                    'designation' => $profile?->designation ?? '—',
                    'team' => $profile?->team ?? '—',
                    'bankName' => $profile?->bank_name,
                    'accountNumber' => $profile?->bank_account_no,
                    'bankBranch' => $profile?->bank_branch,
                    'paymentStatus' => 'unpaid',
                    'breakdown' => [],
                    'overtime' => $this->emptyOvertimeSummary(),
                    'totals' => [
                        'earningsTotal' => 0.0,
                        'deductionsTotal' => 0.0,
                        'overtimeTotal' => 0.0,
                        'netPay' => 0.0,
                    ],
                ]);

                $serializedEarnings = $earnings->map(fn (HcmPayrollLine $line) => $this->serializeLine($line))->values();
                $serializedDeductions = $deductions->map(fn (HcmPayrollLine $line) => $this->serializeLine($line))->values();
                $overtime = $this->summarizeOvertimeFromSerializedLines($serializedEarnings);

                $existing['breakdown'][$run->purpose] = [
                    'runId' => (int) $run->id,
                    'paymentStatus' => $paymentStatus,
                    'earningsTotal' => $earningsTotal,
                    'deductionsTotal' => $deductionsTotal,
                    'overtime' => $overtime,
                    'netPay' => $netPay,
                    'earnings' => $serializedEarnings->all(),
                    'deductions' => $serializedDeductions->all(),
                ];
                $existing['totals']['earningsTotal'] = round((float) $existing['totals']['earningsTotal'] + $earningsTotal, 2);
                $existing['totals']['deductionsTotal'] = round((float) $existing['totals']['deductionsTotal'] + $deductionsTotal, 2);
                $existing['totals']['overtimeTotal'] = round((float) $existing['totals']['overtimeTotal'] + $overtime['amountTotal'], 2);
                $existing['totals']['netPay'] = round((float) $existing['totals']['netPay'] + $netPay, 2);
                $existing['overtime']['amountTotal'] = round((float) $existing['overtime']['amountTotal'] + $overtime['amountTotal'], 2);
                $existing['overtime']['lineCount'] = (int) $existing['overtime']['lineCount'] + (int) $overtime['lineCount'];

                $statuses = collect($existing['breakdown'])->pluck('paymentStatus')->values();
                $existing['paymentStatus'] = $statuses->contains('partial')
                    ? 'partial'
                    : ($statuses->contains('unpaid') && $statuses->contains('paid') ? 'partial' : ($statuses->contains('paid') ? 'paid' : 'unpaid'));

                $rows->put($key, $existing);
            }
        }

        return $rows
            ->values()
            ->sortByDesc(fn (array $row) => sprintf('%04d%02d%08d', (int) ($row['periodYear'] ?? 0), (int) ($row['periodMonth'] ?? 0), (int) ($row['userId'] ?? 0)))
            ->values();
    }
}
