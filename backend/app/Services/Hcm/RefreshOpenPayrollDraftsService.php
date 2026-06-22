<?php

namespace App\Services\Hcm;

use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Support\PayrollDraftBuilder;
use Carbon\CarbonImmutable;

class RefreshOpenPayrollDraftsService
{
    public function __construct(
        private readonly PayrollMonthlySettingsService $payrollMonthlySettingsService,
    ) {}

    public function refresh(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $refreshedPeriodIds = [];
        $skippedFinalizedPeriodIds = [];
        $skippedAfterCutoffPeriodIds = [];

        $periods = HcmPayrollPeriod::query()
            ->select(['id', 'company_id', 'period_year', 'period_month', 'status'])
            ->where('status', HcmPayrollPeriod::STATUS_OPEN)
            ->orderBy('company_id')
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        foreach ($periods as $period) {
            $companyId = $period->company_id ? (int) $period->company_id : null;
            $finalizedExists = HcmPayrollRun::query()
                ->where('hcm_payroll_period_id', $period->id)
                ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
                ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                ->where(function ($query) use ($companyId): void {
                    if ($companyId !== null) {
                        $query->where('company_id', $companyId)->orWhereNull('company_id');

                        return;
                    }

                    $query->whereNull('company_id');
                })
                ->exists();

            if ($finalizedExists) {
                $skippedFinalizedPeriodIds[] = (int) $period->id;

                continue;
            }

            $snapshot = $this->payrollMonthlySettingsService->snapshotForPeriod(
                (int) $period->period_year,
                (int) $period->period_month,
                $companyId,
            );

            $localToday = $now->setTimezone((string) $snapshot['payrollTimezone'])->toDateString();
            if ($localToday > (string) $snapshot['resolvedCutoffDate']) {
                $skippedAfterCutoffPeriodIds[] = (int) $period->id;

                continue;
            }

            PayrollDraftBuilder::rebuildDraftRun($period, $companyId);
            $refreshedPeriodIds[] = (int) $period->id;
        }

        return [
            'refreshedPeriodIds' => $refreshedPeriodIds,
            'skippedFinalizedPeriodIds' => $skippedFinalizedPeriodIds,
            'skippedAfterCutoffPeriodIds' => $skippedAfterCutoffPeriodIds,
        ];
    }
}
