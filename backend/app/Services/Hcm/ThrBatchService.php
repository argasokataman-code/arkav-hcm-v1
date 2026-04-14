<?php

namespace App\Services\Hcm;

use App\Contracts\Hcm\ThrDisbursementGatewayInterface;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmResignation;
use App\Models\HcmSalaryComponent;
use App\Models\HcmTermination;
use App\Models\HcmThrBatch;
use App\Models\HcmThrBatchLine;
use App\Models\HcmThrDisbursement;
use App\Models\HcmThrYearlySetting;
use App\Models\User;
use App\Support\WebsiteSettings;
use Carbon\Carbon;
use App\Support\Hcm\ThrSlipPublicNoAllocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ThrBatchService
{
    public function __construct(
        private readonly ThrProRataCalculator $calculator,
        private readonly ThrDisbursementGatewayInterface $disbursementGateway,
        private readonly ThrSlipPdfService $slipPdf,
    ) {}

    /**
     * @return array{batch: HcmThrBatch, lines: list<array<string, mixed>>}
     */
    public function generateList(int $calendarYear, ?int $generatedByUserId, ?int $companyId = null): array
    {
        $assignedBatchQuery = HcmThrBatch::query()
            ->where('calendar_year', $calendarYear)
            ->where('status', HcmThrBatch::STATUS_ASSIGNED);
        $this->applyTenantScope($assignedBatchQuery, $companyId);
        if ($assignedBatchQuery->exists()) {
            throw new \InvalidArgumentException('THR_YEAR_ALREADY_ASSIGNED');
        }

        $settingQuery = HcmThrYearlySetting::query()->where('calendar_year', $calendarYear);
        $this->applyTenantScope($settingQuery, $companyId);
        $setting = $settingQuery->first();
        if ($setting === null || $setting->calculation_cutoff_date === null) {
            throw new \InvalidArgumentException('THR_SETUP_CUTOFF_REQUIRED');
        }

        $cutoff = $setting->calculation_cutoff_date->format('Y-m-d');

        return DB::transaction(function () use ($calendarYear, $setting, $cutoff, $generatedByUserId, $companyId) {
            $oldDraftsQuery = HcmThrBatch::query()
                ->where('calendar_year', $calendarYear)
                ->where('status', HcmThrBatch::STATUS_DRAFT);
            $this->applyTenantScope($oldDraftsQuery, $companyId);
            $oldDraftsQuery->delete();

            $batch = HcmThrBatch::query()->create([
                'company_id' => $companyId,
                'calendar_year' => $calendarYear,
                'hcm_thr_yearly_setting_id' => $setting->id,
                'cutoff_date' => $cutoff,
                'grand_total_eligible' => 0,
                'eligible_line_count' => 0,
                'total_line_count' => 0,
                'status' => HcmThrBatch::STATUS_DRAFT,
                'generated_by_user_id' => $generatedByUserId,
            ]);

            $snapshotService = app(EmployeeSnapshotService::class);
            $asOf = Carbon::parse($cutoff)->endOfDay();

            // Block karyawan yang sudah resign/terminasi (approved) per tanggal cutoff,
            // sama seperti PayrollDraftBuilder — agar tidak eligible THR.
            $resignedUserIds = HcmResignation::query()
                ->where('status', 'approved')
                ->whereDate('resignation_date', '<=', $cutoff)
                ->when($companyId !== null, function (Builder $query) use ($companyId): void {
                    $query->whereHas('user.employeeProfile', function (Builder $profileQuery) use ($companyId): void {
                        $profileQuery->where(function (Builder $inner) use ($companyId): void {
                            $inner->where('company_id', $companyId)->orWhereNull('company_id');
                        });
                    });
                })
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $terminatedUserIds = HcmTermination::query()
                ->where('status', 'approved')
                ->whereDate('termination_date', '<=', $cutoff)
                ->when($companyId !== null, function (Builder $query) use ($companyId): void {
                    $query->whereHas('user.employeeProfile', function (Builder $profileQuery) use ($companyId): void {
                        $profileQuery->where(function (Builder $inner) use ($companyId): void {
                            $inner->where('company_id', $companyId)->orWhereNull('company_id');
                        });
                    });
                })
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $blockedUserIds = collect(array_merge($resignedUserIds, $terminatedUserIds))->unique()->values()->all();

            $users = User::query()
                ->with('employeeProfile')
                ->when($blockedUserIds !== [], fn ($q) => $q->whereNotIn('id', $blockedUserIds))
                ->when($companyId !== null, function (Builder $query) use ($companyId): void {
                    $query->whereHas('employeeProfile', function (Builder $profileQuery) use ($companyId): void {
                        $profileQuery->where(function (Builder $inner) use ($companyId): void {
                            $inner->where('company_id', $companyId)->orWhereNull('company_id');
                        });
                    });
                })
                ->orderBy('id')
                ->get()
                ->filter(function (User $user) use ($snapshotService, $asOf): bool {
                    $profile = $user->employeeProfile;
                    if ($profile === null) {
                        return false;
                    }

                    $employment = $snapshotService->latestEmployment($profile, $asOf);
                    $status = strtolower((string) ($employment?->employment_status ?? $profile->getRawOriginal('employment_status') ?? 'active'));

                    return in_array($status, ['active', 'probation'], true);
                })
                ->values();

            $grand = 0.0;
            $eligibleCount = 0;
            $serialized = [];

            foreach ($users as $user) {
                $profile = $user->employeeProfile;
                if ($profile === null) {
                    continue;
                }

                $joinYmd = $this->effectiveJoinDate($user, $profile);
                $compensation = $snapshotService->latestCompensation($profile, $asOf);
                $base = max(0.0, (float) ($compensation?->base_salary ?? $profile->getRawOriginal('base_salary') ?? 0));
                $fixed = max(0.0, (float) ($compensation?->fixed_allowance ?? $profile->getRawOriginal('fixed_allowance') ?? 0));

                $calc = $this->calculator->calculate($joinYmd, $cutoff, $base, $fixed);
                $rowStatus = $this->mapRowStatus($calc['status']);
                $eligible = $calc['eligible'] && (float) $calc['thrGross'] > 0;

                $line = HcmThrBatchLine::query()->create([
                    'thr_slip_public_no' => ThrSlipPublicNoAllocator::allocate($calendarYear),
                    'hcm_thr_batch_id' => $batch->id,
                    'user_id' => $user->id,
                    'full_name' => $user->name,
                    'employee_no' => sprintf('%s%04d', WebsiteSettings::prefixEmployee(), $user->id),
                    'join_date_used' => $joinYmd,
                    'base_salary' => round($base, 2),
                    'fixed_allowance' => round($fixed, 2),
                    'reference_wage' => (float) $calc['referenceMonthlyWage'],
                    'months_of_service' => (int) $calc['monthsOfService'],
                    'multiplier' => (float) $calc['multiplier'],
                    'thr_gross' => (float) $calc['thrGross'],
                    'row_status' => $rowStatus,
                    'eligible' => $eligible,
                ]);

                if ($eligible) {
                    $grand += (float) $calc['thrGross'];
                    $eligibleCount++;
                }

                $line->setRelation('user', $user);
                $serialized[] = $this->serializeBatchLine($line, (int) $batch->calendar_year);
            }

            $batch->update([
                'grand_total_eligible' => round($grand, 2),
                'eligible_line_count' => $eligibleCount,
                'total_line_count' => count($serialized),
            ]);

            return [
                'batch' => $batch->fresh(),
                'lines' => $serialized,
            ];
        });
    }

    /**
     * Kirim subset karyawan (tercentang) ke payment gateway; baris sudah paid dilewati.
     *
     * @param  list<int>  $userIds
     * @return array{disbursement: ?HcmThrDisbursement, lines: list<array<string, mixed>>, skippedAlreadyPaidUserIds: list<int>}
     */
    public function disburseSelectedLines(int $batchId, array $userIds, int $initiatedByUserId, ?int $companyId = null): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            throw new \InvalidArgumentException('THR_DISBURSE_NO_EMPLOYEES');
        }

        $result = DB::transaction(function () use ($batchId, $userIds, $initiatedByUserId, $companyId) {
            $batchQuery = HcmThrBatch::query()->lockForUpdate()->whereKey($batchId);
            $this->applyTenantScope($batchQuery, $companyId);
            $batch = $batchQuery->firstOrFail();
            if ($batch->status !== HcmThrBatch::STATUS_DRAFT) {
                throw new \InvalidArgumentException('THR_BATCH_NOT_DRAFT');
            }

            $lines = HcmThrBatchLine::query()
                ->where('hcm_thr_batch_id', $batch->id)
                ->whereIn('user_id', $userIds)
                ->with(['user.employeeProfile'])
                ->get()
                ->keyBy('user_id');

            foreach ($userIds as $uid) {
                if (! isset($lines[$uid])) {
                    throw new \InvalidArgumentException('THR_ASSIGN_USER_NOT_IN_BATCH');
                }
            }

            $skippedAlreadyPaid = [];
            $gatewayItems = [];

            foreach ($userIds as $uid) {
                $line = $lines[$uid];
                if ($line->payment_status === HcmThrBatchLine::PAYMENT_PAID) {
                    $skippedAlreadyPaid[] = $uid;

                    continue;
                }
                if (! $line->eligible || (float) $line->thr_gross <= 0) {
                    throw new \InvalidArgumentException('THR_DISBURSE_LINE_NOT_PAYABLE');
                }
                $profile = $line->user?->employeeProfile;
                $gatewayItems[] = [
                    'userId' => $uid,
                    'amount' => round((float) $line->thr_gross, 2),
                    'bankAccountNo' => $profile?->bank_account_no,
                    'bankName' => $profile?->bank_name,
                    'recipientName' => (string) $line->full_name,
                ];
            }

            if ($gatewayItems === []) {
                return [
                    'disbursement' => null,
                    'lines' => $this->serializeAllLines($batch->id, $companyId),
                    'skippedAlreadyPaidUserIds' => $skippedAlreadyPaid,
                ];
            }

            $disbursement = HcmThrDisbursement::query()->create([
                'hcm_thr_batch_id' => $batch->id,
                'status' => HcmThrDisbursement::STATUS_PROCESSING,
                'driver' => (string) config('hcm.thr_disbursement_driver', 'stub'),
                'meta' => ['userIds' => array_column($gatewayItems, 'userId')],
                'initiated_by_user_id' => $initiatedByUserId,
            ]);

            $results = $this->disbursementGateway->disburseBatch($gatewayItems);

            $completedAt = now();
            $meta = $disbursement->meta ?? [];
            $meta['results'] = $results;
            $disbursement->update([
                'status' => HcmThrDisbursement::STATUS_COMPLETED,
                'completed_at' => $completedAt,
                'meta' => $meta,
            ]);

            $resultByUser = [];
            foreach ($results as $r) {
                $resultByUser[(int) $r['userId']] = $r;
            }

            foreach ($gatewayItems as $item) {
                $uid = $item['userId'];
                $r = $resultByUser[$uid] ?? null;
                if ($r === null) {
                    continue;
                }
                $line = $lines[$uid];
                if ($r['status'] === 'success') {
                    $line->update([
                        'payment_status' => HcmThrBatchLine::PAYMENT_PAID,
                        'payment_failure_reason' => null,
                        'payment_gateway_ref' => $r['ref'],
                        'paid_at' => $completedAt,
                        'last_disbursement_id' => $disbursement->id,
                    ]);
                } else {
                    $line->update([
                        'payment_status' => HcmThrBatchLine::PAYMENT_FAILED,
                        'payment_failure_reason' => $r['failureReason'],
                        'payment_gateway_ref' => $r['ref'],
                        'paid_at' => null,
                        'last_disbursement_id' => $disbursement->id,
                    ]);
                }
            }

            $batchFresh = $batch->fresh();
            foreach ($gatewayItems as $item) {
                $uid = $item['userId'];
                $r = $resultByUser[$uid] ?? null;
                if ($r === null || $r['status'] !== 'success') {
                    continue;
                }
                $line = HcmThrBatchLine::query()
                    ->where('hcm_thr_batch_id', $batch->id)
                    ->where('user_id', $uid)
                    ->firstOrFail();
                $path = $this->slipPdf->generateAndStore($batchFresh, $line);
                if ($path !== null) {
                    $line->update([
                        'slip_storage_path' => $path,
                        'slip_generated_at' => now(),
                    ]);
                }
            }

            return [
                'disbursement' => $disbursement->fresh(),
                'lines' => [],
                'skippedAlreadyPaidUserIds' => $skippedAlreadyPaid,
            ];
        });

        $batchAfterQuery = HcmThrBatch::query()->whereKey($batchId);
        $this->applyTenantScope($batchAfterQuery, $companyId);
        $batchAfter = $batchAfterQuery->first();
        if ($batchAfter !== null && $this->canPostPayrollForBatch($batchAfter, $companyId)) {
            try {
                $this->postPaidLinesToPayroll($batchAfter->id, $initiatedByUserId, $companyId);
            } catch (\Exception $e) {
                // Biarkan disburse re-fetch tanpa error di sini
                // atau logging jika ada issue payroll
            }
        }

        $result['lines'] = $this->serializeAllLines($batchId, $companyId);
        return $result;
    }

    /**
     * Finalisasi run payroll THR untuk semua baris payable yang sudah paid (satu kali per batch).
     *
     * @return array{run: HcmPayrollRun, period: HcmPayrollPeriod}
     */
    public function postPaidLinesToPayroll(int $batchId, int $assignedByUserId, ?int $companyId = null): array
    {
        return DB::transaction(function () use ($batchId, $assignedByUserId, $companyId) {
            $batchQuery = HcmThrBatch::query()->lockForUpdate()->whereKey($batchId);
            $this->applyTenantScope($batchQuery, $companyId);
            $batch = $batchQuery->firstOrFail();
            if ($batch->status !== HcmThrBatch::STATUS_DRAFT) {
                throw new \InvalidArgumentException('THR_BATCH_NOT_DRAFT');
            }

            $payableUserIds = HcmThrBatchLine::query()
                ->where('hcm_thr_batch_id', $batch->id)
                ->where('eligible', true)
                ->where('thr_gross', '>', 0)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($payableUserIds === []) {
                throw new \InvalidArgumentException('THR_ASSIGN_NO_POSITIVE_LINES');
            }

            $stillUnpaid = HcmThrBatchLine::query()
                ->where('hcm_thr_batch_id', $batch->id)
                ->where('eligible', true)
                ->where('thr_gross', '>', 0)
                ->where('payment_status', '!=', HcmThrBatchLine::PAYMENT_PAID)
                ->exists();
            if ($stillUnpaid) {
                throw new \InvalidArgumentException('THR_POST_UNPAID_PAYABLE_LINES');
            }

            $settingQuery = HcmThrYearlySetting::query()->where('calendar_year', $batch->calendar_year);
            $this->applyTenantScope($settingQuery, $companyId);
            $setting = $settingQuery->first();
            if ($setting === null || $setting->payment_date === null) {
                throw new \InvalidArgumentException('THR_PAYMENT_DATE_REQUIRED');
            }

            $pay = Carbon::parse($setting->payment_date);
            $periodYear = (int) $pay->year;
            $periodMonth = (int) $pay->month;

            $period = HcmPayrollPeriod::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'period_year' => $periodYear,
                    'period_month' => $periodMonth,
                ],
                [
                    'company_id' => $companyId,
                    'status' => HcmPayrollPeriod::STATUS_OPEN,
                ],
            );

            $thrFinalizedExists = HcmPayrollRun::query()
                ->where('hcm_payroll_period_id', $period->id)
                ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                ->where('purpose', HcmPayrollRun::PURPOSE_THR)
                ->exists();
            if ($thrFinalizedExists) {
                throw new \InvalidArgumentException('THR_PAYROLL_FINALIZED_EXISTS');
            }

            $thrComponent = HcmSalaryComponent::query()
                ->where('code', 'thr')
                ->where('is_active', true)
                ->where(function (Builder $query) use ($companyId): void {
                    if ($companyId !== null) {
                        $query->where('company_id', $companyId)->orWhereNull('company_id');

                        return;
                    }

                    $query->whereNull('company_id');
                })
                ->first();
            if ($thrComponent === null) {
                throw new \InvalidArgumentException('THR_SALARY_COMPONENT_MISSING');
            }

            $lines = HcmThrBatchLine::query()
                ->where('hcm_thr_batch_id', $batch->id)
                ->whereIn('user_id', $payableUserIds)
                ->get()
                ->keyBy('user_id');

            $draftThrRuns = HcmPayrollRun::query()
                ->where('hcm_payroll_period_id', $period->id)
                ->where('purpose', HcmPayrollRun::PURPOSE_THR)
                ->where('status', HcmPayrollRun::STATUS_DRAFT)
                ->get();
            foreach ($draftThrRuns as $dr) {
                $dr->lines()->delete();
                $dr->delete();
            }

            $run = HcmPayrollRun::query()->create([
                'company_id' => $companyId,
                'hcm_payroll_period_id' => $period->id,
                'purpose' => HcmPayrollRun::PURPOSE_THR,
                'status' => HcmPayrollRun::STATUS_DRAFT,
                'calculated_at' => now(),
            ]);

            $sort = 0;
            foreach ($payableUserIds as $uid) {
                $bl = $lines[$uid] ?? null;
                if ($bl === null) {
                    continue;
                }
                $amount = round((float) $bl->thr_gross, 2);
                if ($amount <= 0) {
                    continue;
                }
                HcmPayrollLine::query()->create([
                    'company_id' => $companyId,
                    'hcm_payroll_run_id' => $run->id,
                    'user_id' => $uid,
                    'hcm_salary_component_id' => $thrComponent->id,
                    'component_code' => $thrComponent->code,
                    'component_name' => $thrComponent->name,
                    'kind' => 'addition',
                    'category' => $thrComponent->category,
                    'amount' => $amount,
                    'sort_order' => $sort++,
                    'meta' => [
                        'source' => 'thr_batch',
                        'thrBatchId' => $batch->id,
                        'calendarYear' => $batch->calendar_year,
                        'rowStatus' => $bl->row_status,
                    ],
                ]);
            }

            if ($run->lines()->count() === 0) {
                $run->delete();
                throw new \InvalidArgumentException('THR_ASSIGN_NO_POSITIVE_LINES');
            }

            $run->update([
                'status' => HcmPayrollRun::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by_user_id' => $assignedByUserId,
            ]);

            $period->update(['status' => HcmPayrollPeriod::STATUS_POSTED]);

            $batch->update([
                'status' => HcmThrBatch::STATUS_ASSIGNED,
                'assigned_at' => now(),
                'assigned_by_user_id' => $assignedByUserId,
                'hcm_payroll_period_id' => $period->id,
                'hcm_payroll_run_id' => $run->id,
            ]);

            return ['run' => $run->fresh(['period']), 'period' => $period->fresh()];
        });
    }

    /**
     * @param  list<int>  $lineIds
     * @return list<int> line ids updated
     */
    public function markSlipNotificationsSent(int $batchId, array $lineIds, ?int $companyId = null): array
    {
        $lineIds = array_values(array_unique(array_map('intval', $lineIds)));
        if ($lineIds === []) {
            throw new \InvalidArgumentException('THR_SEND_SLIP_NO_LINES');
        }

        $lines = HcmThrBatchLine::query()
            ->where('hcm_thr_batch_id', $batchId)
            ->whereIn('id', $lineIds)
            ->whereHas('batch', function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->get();

        if ($lines->count() !== count($lineIds)) {
            throw new \InvalidArgumentException('THR_SEND_SLIP_INVALID_LINES');
        }

        $updated = [];
        foreach ($lines as $line) {
            if ($line->slip_storage_path === null || $line->slip_storage_path === '') {
                throw new \InvalidArgumentException('THR_SEND_SLIP_NO_PDF');
            }
            $line->update(['slip_notify_sent_at' => now()]);
            $updated[] = $line->id;
        }

        return $updated;
    }

    public function canPostPayrollForBatch(HcmThrBatch $batch, ?int $companyId = null): bool
    {
        if ($batch->status !== HcmThrBatch::STATUS_DRAFT || $batch->hcm_payroll_run_id !== null) {
            return false;
        }

        $hasPayable = HcmThrBatchLine::query()
            ->where('hcm_thr_batch_id', $batch->id)
            ->where('eligible', true)
            ->where('thr_gross', '>', 0)
            ->whereHas('batch', function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->exists();

        if (! $hasPayable) {
            return false;
        }

        return ! HcmThrBatchLine::query()
            ->where('hcm_thr_batch_id', $batch->id)
            ->where('eligible', true)
            ->where('thr_gross', '>', 0)
            ->where('payment_status', '!=', HcmThrBatchLine::PAYMENT_PAID)
            ->whereHas('batch', function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLinesForYear(int $calendarYear, ?int $companyId = null): array
    {
        $batchQuery = HcmThrBatch::query()
            ->where('calendar_year', $calendarYear)
            ->where('status', HcmThrBatch::STATUS_DRAFT)
            ->orderByDesc('id');
        $this->applyTenantScope($batchQuery, $companyId);
        $batch = $batchQuery->first();

        if ($batch === null) {
            return [];
        }

        return $this->serializeAllLines($batch->id, $companyId);
    }

    public function findDraftBatch(int $calendarYear, ?int $companyId = null): ?HcmThrBatch
    {
        $query = HcmThrBatch::query()
            ->where('calendar_year', $calendarYear)
            ->where('status', HcmThrBatch::STATUS_DRAFT)
            ->orderByDesc('id');
        $this->applyTenantScope($query, $companyId);

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeBatch(HcmThrBatch $b, bool $includeFlags = false): array
    {
        $out = [
            'id' => $b->id,
            'calendarYear' => (int) $b->calendar_year,
            'cutoffDate' => $b->cutoff_date->toDateString(),
            'grandTotalEligible' => (float) $b->grand_total_eligible,
            'eligibleLineCount' => (int) $b->eligible_line_count,
            'totalLineCount' => (int) $b->total_line_count,
            'status' => $b->status,
            'assignedAt' => optional($b->assigned_at)->toIso8601String(),
            'payrollPeriodId' => $b->hcm_payroll_period_id,
            'payrollRunId' => $b->hcm_payroll_run_id,
        ];
        if ($includeFlags) {
            $out['canPostToPayroll'] = $this->canPostPayrollForBatch($b, (int) ($b->company_id ?? 0) ?: null);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeBatchLine(HcmThrBatchLine $l, ?int $calendarYearOverride = null): array
    {
        $hasSlip = $l->slip_storage_path !== null && $l->slip_storage_path !== '';

        $calendarYear = $calendarYearOverride;
        if ($calendarYear === null && $l->relationLoaded('batch') && $l->batch !== null) {
            $calendarYear = (int) $l->batch->calendar_year;
        }

        $publicNo = is_string($l->thr_slip_public_no ?? null) ? trim((string) $l->thr_slip_public_no) : '';
        if ($publicNo === '' && $calendarYear !== null) {
            $publicNo = sprintf('THR-%d-%d', $calendarYear, $l->id);
        }
        $slipNumber = $publicNo !== '' ? '#'.$publicNo : null;

        $profile = null;
        if ($l->relationLoaded('user') && $l->user !== null) {
            $profile = $l->user->employeeProfile;
        }
        $bankName = $profile !== null ? trim((string) ($profile->bank_name ?? '')) : '';
        $bankAccountNo = $profile !== null ? trim((string) ($profile->bank_account_no ?? '')) : '';

        return [
            'id' => $l->id,
            'thrSlipPublicNo' => $publicNo !== '' ? $publicNo : null,
            'userId' => $l->user_id,
            'fullName' => $l->full_name,
            'employeeNo' => $l->employee_no,
            'bankName' => $bankName !== '' ? $bankName : null,
            'bankAccountNo' => $bankAccountNo !== '' ? $bankAccountNo : null,
            'joinDateUsed' => $l->join_date_used->toDateString(),
            'baseSalary' => (float) $l->base_salary,
            'fixedAllowance' => (float) $l->fixed_allowance,
            'referenceWage' => (float) $l->reference_wage,
            'monthsOfService' => (int) $l->months_of_service,
            'multiplier' => (float) $l->multiplier,
            'thrGross' => (float) $l->thr_gross,
            'rowStatus' => $l->row_status,
            'eligible' => (bool) $l->eligible,
            'paymentStatus' => $l->payment_status,
            'paymentFailureReason' => $l->payment_failure_reason,
            'paymentGatewayRef' => $l->payment_gateway_ref,
            'paidAt' => optional($l->paid_at)->toIso8601String(),
            'hasSlip' => $hasSlip,
            'slipGeneratedAt' => optional($l->slip_generated_at)->toIso8601String(),
            'slipNotifySentAt' => optional($l->slip_notify_sent_at)->toIso8601String(),
            'calendarYear' => $calendarYear,
            'slipNumber' => $slipNumber,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeAllLines(int $batchId, ?int $companyId = null): array
    {
        return HcmThrBatchLine::query()
            ->where('hcm_thr_batch_id', $batchId)
            ->whereHas('batch', function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->with(['batch', 'user.employeeProfile'])
            ->orderBy('user_id')
            ->get()
            ->map(fn (HcmThrBatchLine $l) => $this->serializeBatchLine($l))
            ->all();
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        return $query->where(function (Builder $inner) use ($companyId): void {
            if ($companyId !== null) {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');

                return;
            }

            $inner->whereNull('company_id');
        });
    }

    private function effectiveJoinDate(User $user, EmployeeProfile $profile): string
    {
        if ($profile->hire_date) {
            return $profile->hire_date->format('Y-m-d');
        }

        return $user->created_at->format('Y-m-d');
    }

    private function mapRowStatus(string $calcStatus): string
    {
        return match ($calcStatus) {
            'full' => 'full',
            'pro_rata' => 'pro_rata',
            'not_eligible' => 'nihil',
            'invalid_dates' => 'invalid',
            default => 'invalid',
        };
    }
}
