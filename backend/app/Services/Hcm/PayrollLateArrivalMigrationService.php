<?php

namespace App\Services\Hcm;

use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Support\PayrollDraftBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollLateArrivalMigrationService
{
    /**
     * Queue migration metadata and rebuild next period draft when source monthly run is fully paid.
     *
     * @return array<string,mixed>|null
     */
    public function migrateToNextPeriodIfEligible(int $runId, ?int $companyId = null): ?array
    {
        return DB::transaction(function () use ($runId, $companyId): ?array {
            $runQuery = HcmPayrollRun::query()->with('period')->whereKey($runId)->lockForUpdate();
            $this->applyTenantScope($runQuery, $companyId);
            /** @var HcmPayrollRun|null $run */
            $run = $runQuery->first();
            if (! $run) {
                return null;
            }

            if (($run->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY) !== HcmPayrollRun::PURPOSE_MONTHLY) {
                return null;
            }

            $meta = is_array($run->meta) ? $run->meta : [];
            $buffer = is_array($meta['lateArrivalBuffer'] ?? null) ? $meta['lateArrivalBuffer'] : [];
            $hasLateArrivals = (bool) ($buffer['hasLateArrivals'] ?? false);
            if (! $hasLateArrivals) {
                return null;
            }

            $migration = is_array($buffer['migration'] ?? null) ? $buffer['migration'] : [];
            if (($migration['status'] ?? null) === 'migrated') {
                return null;
            }

            $period = $run->period;
            if (! $period) {
                return null;
            }

            [$nextYear, $nextMonth] = $this->nextYearMonth((int) $period->period_year, (int) $period->period_month);

            $nextPeriod = HcmPayrollPeriod::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'period_year' => $nextYear,
                    'period_month' => $nextMonth,
                ],
                [
                    'status' => HcmPayrollPeriod::STATUS_OPEN,
                ],
            );

            $buffer['migration'] = [
                'status' => 'queued',
                'queuedAt' => now()->toIso8601String(),
                'targetPeriodId' => (int) $nextPeriod->id,
                'targetPeriodYear' => $nextYear,
                'targetPeriodMonth' => $nextMonth,
            ];
            $meta['lateArrivalBuffer'] = $buffer;
            $run->meta = $meta;
            $run->save();

            $rebuiltRun = PayrollDraftBuilder::rebuildDraftRun($nextPeriod, $companyId);

            $run->refresh();
            $meta = is_array($run->meta) ? $run->meta : [];
            $buffer = is_array($meta['lateArrivalBuffer'] ?? null) ? $meta['lateArrivalBuffer'] : [];
            $existingMigration = is_array($buffer['migration'] ?? null) ? $buffer['migration'] : [];
            $buffer['migration'] = array_merge($existingMigration, [
                'status' => 'migrated',
                'migratedAt' => now()->toIso8601String(),
                'targetPeriodId' => (int) $nextPeriod->id,
                'targetPeriodYear' => $nextYear,
                'targetPeriodMonth' => $nextMonth,
                'targetRunId' => (int) $rebuiltRun->id,
            ]);
            $meta['lateArrivalBuffer'] = $buffer;
            $run->meta = $meta;
            $run->save();

            return [
                'sourceRunId' => (int) $run->id,
                'targetPeriodId' => (int) $nextPeriod->id,
                'targetPeriodYear' => $nextYear,
                'targetPeriodMonth' => $nextMonth,
                'targetRunId' => (int) $rebuiltRun->id,
            ];
        });
    }

    /**
     * @return array{int,int}
     */
    private function nextYearMonth(int $year, int $month): array
    {
        $current = Carbon::create($year, $month, 1, 0, 0, 0, 'UTC')->addMonth();

        return [(int) $current->year, (int) $current->month];
    }

    private function applyTenantScope($query, ?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }

        $query->where(function ($inner) use ($companyId): void {
            $inner->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }
}
