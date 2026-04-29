<?php

namespace App\Http\Controllers\Api;

use Closure;
use App\Events\PayrollFinalized;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Mail\MonthlyPayslipMail;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmTermination;
use App\Services\Hcm\PayrollLateArrivalMigrationService;
use App\Services\Hcm\PayrollMonthlySettingsService;
use App\Models\User;
use App\Services\Hcm\MonthlyPayslipService;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;
use App\Services\Reconciliation\ReconciliationGateService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class HcmPayrollRunController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly MonthlyPayslipService $monthlyPayslipService,
        private readonly PayrollLateArrivalMigrationService $payrollLateArrivalMigrationService,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $runQuery = HcmPayrollRun::query()->with(['period', 'finalizedBy:id,name', 'voidedBy:id,name', 'lines.user:id,name']);
        $this->applyTenantScope($runQuery, $companyId);
        $run = $runQuery->where('id', $id)->firstOrFail();
        $lines = $run->lines
            ->sortBy([['user_id', 'asc'], ['sort_order', 'asc']])
            ->values()
            ->map(fn (HcmPayrollLine $l) => $this->serializeLine($l));

        return response()->json([
            'success' => true,
            'data' => [
                'run' => $this->serializeRun($run),
                'lines' => $lines,
                'specialRecipients' => $this->specialRecipientsForRunPeriod($run, $companyId),
                'auditTrail' => $this->auditTrailForRun($run),
                'summary' => $this->buildRunDetailSummary($run),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['nullable', 'integer', 'min:1', 'max:12'],
            'status' => ['nullable', 'string', 'in:draft,finalized,void'],
            'purpose' => ['nullable', 'string', 'in:monthly,thr,pkwt_compensation'],
            'paymentStatus' => ['nullable', 'string', 'in:unpaid,partial,paid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $companyId = $this->activeCompanyId($request);
        $query = HcmPayrollRun::query()->with(['period', 'finalizedBy:id,name', 'voidedBy:id,name', 'lines']);
        $this->applyTenantScope($query, $companyId);
        if (! empty($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['purpose'] ?? null)) {
            $query->where('purpose', $validated['purpose']);
        }
        if (! empty($validated['periodYear'] ?? null)) {
            $query->whereHas('period', fn ($q) => $q->where('period_year', (int) $validated['periodYear']));
        }
        if (! empty($validated['periodMonth'] ?? null)) {
            $query->whereHas('period', fn ($q) => $q->where('period_month', (int) $validated['periodMonth']));
        }

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (HcmPayrollRun $run): array {
            $summary = $this->serializeRun($run);
            return array_merge($summary, [
                'auditTrail' => $this->auditTrailForRun($run),
            ]);
        })->values();

        if (! empty($validated['paymentStatus'] ?? null)) {
            $items = $items->filter(fn (array $row): bool => ($row['paymentStatus'] ?? 'unpaid') === $validated['paymentStatus'])->values();
        }

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function finalize(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.finalize');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $runQuery = HcmPayrollRun::query();
        $this->applyTenantScope($runQuery, $companyId);
        $run = $runQuery->where('id', $id)->firstOrFail();

        if ($error = $this->guardPayrollReconciliation($request, $run, 'finalize')) {
            return $error;
        }

        if ($run->status !== HcmPayrollRun::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_RUN_NOT_DRAFT',
                    'message' => 'Only draft runs can be finalized.',
                ],
            ], 422);
        }

        $purpose = $run->purpose ?: HcmPayrollRun::PURPOSE_MONTHLY;

        $otherFinalizedQuery = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $run->hcm_payroll_period_id)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', $purpose)
            ->where('id', '!=', $run->id);
        $this->applyTenantScope($otherFinalizedQuery, $companyId);
        $otherFinalized = $otherFinalizedQuery->exists();
        if ($otherFinalized) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_FINALIZED_EXISTS',
                    'message' => 'This period already has a finalized run.',
                ],
            ], 422);
        }

        if ($run->lines()->count() === 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_RUN_EMPTY',
                    'message' => 'Cannot finalize a payroll run with no lines; recalculate draft or fix employee data.',
                ],
            ], 422);
        }

        if ($purpose === HcmPayrollRun::PURPOSE_MONTHLY) {
            if ($blocker = $this->unsettledTerminationBlocker($run, $companyId)) {
                return $blocker;
            }
        }

        [$serviceFeeRate, $serviceFeeBase, $serviceFeeAmount, $serviceFeeBillingMonth] = $this->resolvePayrollServiceFeeCharges($run, $companyId);

        $user = $request->user();
        $meta = is_array($run->meta) ? $run->meta : [];
        $meta['platform_service_fee_rate'] = $serviceFeeRate;
        $meta['platform_service_fee_base'] = $serviceFeeBase;
        $meta['platform_service_fee_amount'] = $serviceFeeAmount;
        $meta['platform_service_fee_billing_month'] = $serviceFeeBillingMonth;

        $run->update([
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'finalized_at' => now(),
            'finalized_by_user_id' => $user?->id,
            'meta' => $meta,
        ]);
        PayrollFinalized::dispatch((int) $run->id, (int) ($user?->id ?? 0));

        $periodQuery = HcmPayrollPeriod::query()->whereKey($run->hcm_payroll_period_id);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();
        if ($period !== null) {
            $period->update(['status' => HcmPayrollPeriod::STATUS_POSTED]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeRun($run->fresh(['period'])),
        ]);
    }

    public function void(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.finalize');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $result = DB::transaction(function () use ($id, $companyId, $request): array {
            $runQuery = HcmPayrollRun::query()->whereKey($id)->lockForUpdate();
            $this->applyTenantScope($runQuery, $companyId);
            $run = $runQuery->firstOrFail();

            if ($run->status !== HcmPayrollRun::STATUS_FINALIZED) {
                return [
                    'error' => [
                        'code' => 'PAYROLL_RUN_NOT_FINALIZED',
                        'message' => 'Only finalized runs can be voided.',
                    ],
                    'status' => 422,
                ];
            }

            $lines = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $run->id)
                ->lockForUpdate()
                ->get();

            $hasPaidLines = $lines->contains(function (HcmPayrollLine $line): bool {
                return strtolower((string) (($line->meta['paymentStatus'] ?? 'unpaid'))) === 'paid';
            });

            if ($hasPaidLines) {
                return [
                    'error' => [
                        'code' => 'PAYROLL_RUN_ALREADY_PAID',
                        'message' => 'Paid payroll runs cannot be voided.',
                    ],
                    'status' => 422,
                ];
            }

            $user = $request->user();
            $run->update([
                'status' => HcmPayrollRun::STATUS_VOID,
                'voided_at' => now(),
                'voided_by_user_id' => $user?->id,
                'voided_by_user_uuid' => $user?->uuid,
            ]);

            $periodQuery = HcmPayrollPeriod::query()->whereKey($run->hcm_payroll_period_id)->lockForUpdate();
            $this->applyTenantScope($periodQuery, $companyId);
            $period = $periodQuery->first();

            if ($period !== null) {
                $remainingFinalizedQuery = HcmPayrollRun::query()
                    ->where('hcm_payroll_period_id', $run->hcm_payroll_period_id)
                    ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                    ->where('id', '!=', $run->id)
                    ->lockForUpdate();
                $this->applyTenantScope($remainingFinalizedQuery, $companyId);

                if (! $remainingFinalizedQuery->exists()) {
                    $period->update(['status' => HcmPayrollPeriod::STATUS_OPEN]);
                }
            }

            $freshRunQuery = HcmPayrollRun::query()->with(['period', 'finalizedBy:id,name', 'voidedBy:id,name'])->whereKey($run->id);
            $this->applyTenantScope($freshRunQuery, $companyId);

            return [
                'run' => $freshRunQuery->firstOrFail(),
            ];
        });

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], $result['status'] ?? 422);
        }

        /** @var HcmPayrollRun $run */
        $run = $result['run'];

        return response()->json([
            'success' => true,
            'data' => $this->serializeRun($run),
        ]);
    }

    public function disburse(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.disburse');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'userIds' => ['nullable', 'array', 'min:1'],
            'userIds.*' => [function (string $attribute, mixed $value, Closure $fail): void {
                if (! $this->userIdentifierExists($value)) {
                    $fail("The selected {$attribute} is invalid.");
                }
            }],
            'applyAll' => ['nullable', 'boolean'],
        ]);

        $selectedUserIds = $this->resolveUserIdsFromIdentifiers($validated['userIds'] ?? []);
        $applyAll = (bool) ($validated['applyAll'] ?? false);
        $companyId = $this->activeCompanyId($request);
        $result = DB::transaction(function () use ($id, $request, $selectedUserIds, $applyAll, $companyId): array {
            $runQuery = HcmPayrollRun::query()->whereKey($id)->lockForUpdate();
            $this->applyTenantScope($runQuery, $companyId);
            $run = $runQuery->firstOrFail();

            if ($error = $this->guardPayrollReconciliation($request, $run, 'disburse')) {
                return [
                    'error' => $error->getData(true)['error'] ?? [
                        'code' => 'EXPORT_RECON_REQUIRED',
                        'message' => 'Export reconciliation evidence is required before this action.',
                    ],
                    'status' => $error->getStatusCode(),
                ];
            }

            $period = HcmPayrollPeriod::query()
                ->whereKey($run->hcm_payroll_period_id)
                ->where(function (Builder $inner) use ($companyId): void {
                    if ($companyId !== null) {
                        $inner->where('company_id', $companyId)->orWhereNull('company_id');

                        return;
                    }

                    $inner->whereNull('company_id');
                })
                ->lockForUpdate()
                ->first();

            if ($paydayBlocker = $this->guardBeforePaydayDisburse($run, $period, $companyId)) {
                return [
                    'error' => $paydayBlocker,
                    'status' => 422,
                ];
            }

            $lines = HcmPayrollLine::query()
                ->with('user:id,name')
                ->where('hcm_payroll_run_id', $run->id)
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                return [
                    'error' => [
                        'code' => 'PAYROLL_RUN_EMPTY',
                        'message' => 'Tidak ada karyawan eligible di payroll run ini.',
                    ],
                    'status' => 422,
                ];
            }

            if ($run->status === HcmPayrollRun::STATUS_DRAFT) {
                $otherFinalizedQuery = HcmPayrollRun::query()
                    ->where('hcm_payroll_period_id', $run->hcm_payroll_period_id)
                    ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                    ->where('purpose', $run->purpose ?: HcmPayrollRun::PURPOSE_MONTHLY)
                    ->where('id', '!=', $run->id)
                    ->lockForUpdate();
                $this->applyTenantScope($otherFinalizedQuery, $companyId);
                $otherFinalized = $otherFinalizedQuery->exists();

                if ($otherFinalized) {
                    return [
                        'error' => [
                            'code' => 'PAYROLL_FINALIZED_EXISTS',
                            'message' => 'Periode ini sudah memiliki payroll finalized lain.',
                        ],
                        'status' => 422,
                    ];
                }

                $run->update([
                    'status' => HcmPayrollRun::STATUS_FINALIZED,
                    'finalized_at' => now(),
                    'finalized_by_user_id' => $request->user()?->id,
                ]);
                if ($period !== null) {
                    $period->update(['status' => HcmPayrollPeriod::STATUS_POSTED]);
                }
            }

            $netByUser = [];
            foreach ($lines->groupBy('user_id') as $userId => $items) {
                $net = 0.0;
                foreach ($items as $line) {
                    $meta = is_array($line->meta) ? $line->meta : [];
                    $affectsNetPay = array_key_exists('affectsNetPay', $meta)
                        ? (bool) $meta['affectsNetPay']
                        : ((string) $line->category !== 'employer_cost_display');

                    if (! $affectsNetPay) {
                        continue;
                    }

                    $amount = (float) $line->amount;
                    if ((string) $line->kind === 'addition') {
                        $net += $amount;
                    } elseif ((string) $line->kind === 'deduction') {
                        $net -= $amount;
                    }
                }

                $netByUser[(int) $userId] = round($net, 2);
            }

            $availableUserIds = collect(array_keys($netByUser))->values();
            $eligibleUserIds = collect($netByUser)
                ->filter(fn ($net) => (float) $net > 0)
                ->keys()
                ->map(fn ($userId) => (int) $userId)
                ->values();
            $ineligibleUserIds = $availableUserIds
                ->diff($eligibleUserIds)
                ->values();

            $effectiveSelectedUserIds = $selectedUserIds->isNotEmpty()
                ? $selectedUserIds->intersect($eligibleUserIds)->values()
                : ($applyAll ? $eligibleUserIds : collect());

            $allEligiblePaidAfterDisburse = $eligibleUserIds->isNotEmpty()
                && $effectiveSelectedUserIds->count() === $eligibleUserIds->count();

            if ($effectiveSelectedUserIds->isEmpty()) {
                return [
                    'error' => [
                        'code' => 'PAYROLL_DISBURSE_NO_EMPLOYEES',
                        'message' => 'Tidak ada karyawan eligible untuk dibayar. Hanya user dengan net pay positif yang bisa diproses.',
                    ],
                    'status' => 422,
                ];
            }

            $selectedSet = $effectiveSelectedUserIds->flip();
            $alreadyPaidUserIds = [];
            foreach ($lines->groupBy('user_id') as $userId => $items) {
                if (! $selectedSet->has((int) $userId)) {
                    continue;
                }
                $hasPaidLine = collect($items)->contains(function (HcmPayrollLine $line): bool {
                    return strtolower((string) (($line->meta['paymentStatus'] ?? 'unpaid'))) === 'paid';
                });
                if ($hasPaidLine) {
                    $alreadyPaidUserIds[] = (int) $userId;
                }
            }

            // RACE CONDITION PROTECTION: If any selected employee already has a paid line,
            // fail atomically to prevent partial/duplicate disbursements.
            // This check happens within the transaction with lockForUpdate() on all lines,
            // ensuring consistency even under concurrent access.
            // Already-paid users are tracked in $alreadyPaidUserIds and returned as
            // skippedAlreadyPaidUserIds for idempotency. lockForUpdate() above ensures
            // atomicity under concurrent access.
            $linesToMark = $lines->filter(function (HcmPayrollLine $line) use ($selectedSet): bool {
                return $selectedSet->has((int) $line->user_id)
                    && strtolower((string) (($line->meta['paymentStatus'] ?? 'unpaid'))) !== 'paid';
            });

            $gatewayReference = (string) $lines
                ->map(fn (HcmPayrollLine $line) => $line->meta['gatewayReference'] ?? null)
                ->filter()
                ->last();

            if ($linesToMark->isNotEmpty()) {
                $gatewayReference = 'PAY-'.now()->format('YmdHis').'-'.$run->id.'-'.strtoupper(Str::random(4));
                $paidAt = now()->toIso8601String();
                
                // Mark all lines as paid atomically within the transaction
                foreach ($linesToMark as $line) {
                    $meta = is_array($line->meta) ? $line->meta : [];
                    $meta['userName'] = $line->user?->name ?? ($meta['userName'] ?? null);
                    $meta['paymentStatus'] = 'paid';
                    $meta['paidAt'] = $paidAt;
                    $meta['gatewayReference'] = $gatewayReference;
                    $meta['paymentChannel'] = 'gateway-simulated';
                    $line->meta = $meta;
                    $line->save();
                }
                
                // Post-save consistency check: Reload lines to verify payment metadata persisted
                $verifyLines = HcmPayrollLine::query()
                    ->where('hcm_payroll_run_id', $run->id)
                    ->whereIn('id', $linesToMark->pluck('id')->all())
                    ->get();
                
                foreach ($verifyLines as $verifyLine) {
                    $verifyStatus = strtolower((string) ($verifyLine->meta['paymentStatus'] ?? 'unpaid'));
                    if ($verifyStatus !== 'paid') {
                        // If metadata didn't persist, fail the transaction
                        return [
                            'error' => [
                                'code' => 'PAYROLL_DISBURSE_PERSISTENCE_FAILED',
                                'message' => 'Payment metadata failed to persist. Transaction rolled back. Please retry.',
                            ],
                            'status' => 500,
                        ];
                    }
                }
            }

            $freshRunQuery = HcmPayrollRun::query()->with(['period', 'lines.user:id,name'])->whereKey($run->id);
            $this->applyTenantScope($freshRunQuery, $companyId);
            $freshRun = $freshRunQuery->firstOrFail();

            return [
                'run' => $freshRun,
                'selectedUserIds' => $effectiveSelectedUserIds->values()->all(),
                'ineligibleUserIds' => $ineligibleUserIds->all(),
                'skippedAlreadyPaidUserIds' => array_values(array_unique($alreadyPaidUserIds)),
                'gatewayReference' => $gatewayReference,
                'allEligiblePaidAfterDisburse' => $allEligiblePaidAfterDisburse,
            ];
        });

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], $result['status'] ?? 422);
        }

        /** @var HcmPayrollRun $run */
        $run = $result['run'];
        $paymentSummary = $this->paymentSummary($run);
        $lateArrivalMigration = null;

        if ((bool) ($result['allEligiblePaidAfterDisburse'] ?? false)) {
            $lateArrivalMigration = $this->payrollLateArrivalMigrationService
                ->migrateToNextPeriodIfEligible((int) $run->id, $companyId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'run' => $this->serializeRun($run),
                'selectedUserIds' => $result['selectedUserIds'],
                'ineligibleUserIds' => $result['ineligibleUserIds'] ?? [],
                'skippedAlreadyPaidUserIds' => $result['skippedAlreadyPaidUserIds'],
                'gatewayReference' => $result['gatewayReference'],
                'payment' => $paymentSummary,
                'lateArrivalMigration' => $lateArrivalMigration,
            ],
        ]);
    }

    public function resetPayments(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.disburse')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if ($companyId === null || $companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_RESET_ACTIVE_COMPANY_REQUIRED',
                    'message' => 'Reset pembayaran hanya diizinkan dalam konteks tenant aktif.',
                ],
            ], 403);
        }

        if (! $this->isPrimarySuperAdminCodeOne($request->user())) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_RESET_PRIMARY_SUPER_ADMIN_ONLY',
                    'message' => 'Reset pembayaran hanya untuk super admin code 1.',
                ],
            ], 403);
        }

        if (! app()->environment(['local', 'development', 'testing'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_RESET_NOT_ALLOWED',
                    'message' => 'Reset pembayaran hanya diizinkan pada environment development.',
                ],
            ], 403);
        }

        $result = DB::transaction(function () use ($id, $companyId, $request): array {
            $runQuery = HcmPayrollRun::query()->whereKey($id)->lockForUpdate();
            $this->applyTenantScope($runQuery, $companyId);
            $run = $runQuery->firstOrFail();

            $linesQuery = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $run->id)
                ->lockForUpdate();

            $lines = $linesQuery->get();

            $resetLineCount = 0;
            foreach ($lines as $line) {
                $meta = is_array($line->meta)
                    ? $line->meta
                    : (json_decode((string) ($line->meta ?? '{}'), true) ?: []);

                $hadPaymentMeta = isset($meta['paymentStatus'])
                    || isset($meta['paidAt'])
                    || isset($meta['gatewayReference'])
                    || isset($meta['paymentChannel']);

                unset($meta['paymentStatus'], $meta['paidAt'], $meta['gatewayReference'], $meta['paymentChannel']);

                if ($hadPaymentMeta) {
                    $line->meta = $meta;
                    $line->save();
                    $resetLineCount++;
                }
            }

            $freshRunQuery = HcmPayrollRun::query()->with(['period', 'lines.user:id,name'])->whereKey($run->id);
            $this->applyTenantScope($freshRunQuery, $companyId);
            $freshRun = $freshRunQuery->firstOrFail();

            return [
                'run' => $freshRun,
                'resetLineCount' => $resetLineCount,
            ];
        });

        /** @var HcmPayrollRun $run */
        $run = $result['run'];

        return response()->json([
            'success' => true,
            'data' => [
                'run' => $this->serializeRun($run),
                'resetLineCount' => (int) $result['resetLineCount'],
                'payment' => $this->paymentSummary($run),
            ],
        ]);
    }

    private function guardBeforePaydayDisburse(HcmPayrollRun $run, ?HcmPayrollPeriod $period, ?int $companyId): ?array
    {
        if (($run->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY) !== HcmPayrollRun::PURPOSE_MONTHLY || $period === null) {
            return null;
        }

        $meta = is_array($run->meta) ? $run->meta : [];
        $policySnapshot = is_array($meta['policySnapshot'] ?? null)
            ? $meta['policySnapshot']
            : app(PayrollMonthlySettingsService::class)->snapshotForPeriod(
                (int) $period->period_year,
                (int) $period->period_month,
                $companyId,
            );

        if ((bool) ($policySnapshot['disburseBeforePaydayAllowed'] ?? false)) {
            return null;
        }

        $resolvedPaydayDate = (string) ($policySnapshot['resolvedPaydayDate'] ?? '');
        if ($resolvedPaydayDate === '') {
            return null;
        }

        $payrollTimezone = (string) ($policySnapshot['payrollTimezone'] ?? config('app.timezone', 'Asia/Jakarta'));
        $localToday = Carbon::now($payrollTimezone)->toDateString();
        if ($localToday >= $resolvedPaydayDate) {
            return null;
        }

        return [
            'code' => 'PAYROLL_DISBURSE_BEFORE_PAYDAY_FORBIDDEN',
            'message' => sprintf(
                'Payroll tidak bisa dibayarkan sebelum payday %s sesuai policy tenant aktif.',
                $resolvedPaydayDate
            ),
        ];
    }

    private function isPrimarySuperAdminCodeOne(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $primaryEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        $userEmail = strtolower(trim((string) ($user->email ?? '')));

        return $userEmail !== '' && $userEmail === $primaryEmail;
    }

    public function mySlip(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Unauthorized.'],
            ], 401);
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->monthlyPayslipService->buildForUser(
                $user,
                (int) $validated['periodYear'],
                (int) $validated['periodMonth'],
                $this->activeCompanyId($request),
            ),
        ]);
    }

    public function mySlipLatestPeriod(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Unauthorized.'],
            ], 401);
        }

        $companyId = $this->activeCompanyId($request);
        $latestRunQuery = HcmPayrollRun::query()
            ->with('period:id,period_year,period_month,status')
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->whereIn('purpose', [
                HcmPayrollRun::PURPOSE_MONTHLY,
                HcmPayrollRun::PURPOSE_THR,
                HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
            ])
            ->whereHas('period')
            ->whereHas('lines', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('hcm_payroll_period_id')
            ->orderByDesc('id');
        $this->applyTenantScope($latestRunQuery, $companyId);
        $latestRun = $latestRunQuery->first();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $latestRun?->period ? $this->serializePeriodBrief($latestRun->period) : null,
                'run' => $latestRun ? $this->serializeRunBrief($latestRun) : null,
            ],
        ]);
    }

    public function mySlipPdf(Request $request)
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Unauthorized.'],
            ], 401);
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $slip = $this->monthlyPayslipService->buildForUser(
            $user,
            (int) $validated['periodYear'],
            (int) $validated['periodMonth'],
            $this->activeCompanyId($request),
        );

        if (($slip['run'] ?? null) === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_SLIP_NOT_FOUND',
                    'message' => 'No finalized payroll slip is available for the requested period.',
                ],
            ], 404);
        }

        $pdf = $this->monthlyPayslipService->renderPdf(
            $user,
            (int) $validated['periodYear'],
            (int) $validated['periodMonth'],
            $this->activeCompanyId($request),
        );
        $filename = strtolower((string) ($slip['slipNumber'] ?? 'payslip')).'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function sendMonthlySlips(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.disburse')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
            'userIds' => ['required', 'array', 'min:1'],
            'userIds.*' => [function (string $attribute, mixed $value, Closure $fail): void {
                if (! $this->userIdentifierExists($value)) {
                    $fail("The selected {$attribute} is invalid.");
                }
            }],
        ]);

        $resolvedUserIds = $this->resolveUserIdsFromIdentifiers($validated['userIds']);

        $companyId = $this->activeCompanyId($request);
        $periodQuery = HcmPayrollPeriod::query()
            ->where('period_year', $validated['periodYear'])
            ->where('period_month', $validated['periodMonth']);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();

        if ($period === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_PERIOD_NOT_FOUND',
                    'message' => 'Periode payroll tidak ditemukan.',
                ],
            ], 404);
        }

        $runQuery = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ->orderByDesc('id');
        $this->applyTenantScope($runQuery, $companyId);
        $run = $runQuery->first();

        if ($run === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_SLIP_NOT_READY',
                    'message' => 'Slip gaji hanya bisa dikirim setelah payroll bulanan periode ini finalized.',
                ],
            ], 422);
        }

        $users = User::query()
            ->with(['employeeProfile.department', 'employeeProfile.designationRef'])
            ->whereIn('id', $resolvedUserIds->all())
            ->get()
            ->keyBy('id');

        $sentUserIds = [];
        $skipped = [];

        foreach ($resolvedUserIds as $userId) {
            $user = $users->get((int) $userId);
            if (! $user) {
                $skipped[] = ['userId' => (int) $userId, 'reason' => 'USER_NOT_FOUND'];
                continue;
            }

            $email = trim((string) ($user->email ?? ''));
            if ($email === '') {
                $skipped[] = ['userId' => (int) $userId, 'reason' => 'EMAIL_EMPTY'];
                continue;
            }

            $slip = $this->monthlyPayslipService->buildForUser(
                $user,
                (int) $validated['periodYear'],
                (int) $validated['periodMonth'],
                $companyId,
            );

            if (($slip['run'] ?? null) === null) {
                $skipped[] = ['userId' => (int) $userId, 'reason' => 'SLIP_NOT_FOUND'];
                continue;
            }

            $pdf = $this->monthlyPayslipService->renderPdf(
                $user,
                (int) $validated['periodYear'],
                (int) $validated['periodMonth'],
                $companyId,
            );

            try {
                Mail::to($email)->send(new MonthlyPayslipMail($user, $slip, $pdf));
                $sentUserIds[] = (int) $userId;
            } catch (\Throwable $e) {
                $skipped[] = [
                    'userId' => (int) $userId,
                    'reason' => 'MAIL_SEND_FAILED',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sentUserIds' => $sentUserIds,
                'skipped' => $skipped,
            ],
        ]);
    }

    private function userIdentifierExists(mixed $identifier): bool
    {
        $query = User::query();

        if ($this->isNumericUserIdentifier($identifier)) {
            return $query->whereKey((int) $identifier)->exists();
        }

        if (is_string($identifier) && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier)->exists();
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $identifiers
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function resolveUserIdsFromIdentifiers(array $identifiers)
    {
        $numericIds = collect($identifiers)
            ->filter(fn (mixed $identifier): bool => $this->isNumericUserIdentifier($identifier))
            ->map(fn (mixed $identifier): int => (int) $identifier)
            ->unique()
            ->values();

        $uuids = collect($identifiers)
            ->filter(fn (mixed $identifier): bool => is_string($identifier) && Str::isUuid($identifier))
            ->map(fn (string $identifier): string => strtolower($identifier))
            ->unique()
            ->values();

        if ($numericIds->isEmpty() && $uuids->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->where(function (Builder $query) use ($numericIds, $uuids): void {
                if ($numericIds->isNotEmpty()) {
                    $query->whereIn('id', $numericIds->all());
                }

                if ($uuids->isNotEmpty()) {
                    $method = $numericIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('uuid', $uuids->all());
                }
            })
            ->get(['id', 'uuid']);

        $usersById = $users->keyBy(fn (User $user): string => (string) $user->id);
        $usersByUuid = $users->keyBy(fn (User $user): string => strtolower((string) $user->uuid));

        return collect($identifiers)
            ->map(function (mixed $identifier) use ($usersById, $usersByUuid): ?int {
                if ($this->isNumericUserIdentifier($identifier)) {
                    return $usersById->get((string) ((int) $identifier))?->id;
                }

                if (is_string($identifier) && Str::isUuid($identifier)) {
                    return $usersByUuid->get(strtolower($identifier))?->id;
                }

                return null;
            })
            ->filter(fn (?int $identifier): bool => $identifier !== null)
            ->map(fn (int $identifier): int => (int) $identifier)
            ->unique()
            ->values();
    }

    private function isNumericUserIdentifier(mixed $identifier): bool
    {
        return is_int($identifier)
            || (is_string($identifier) && $identifier !== '' && ctype_digit($identifier));
    }

    public function mySlipLines(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Unauthorized.'],
            ], 401);
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $periodQuery = HcmPayrollPeriod::query()
            ->where('period_year', $validated['periodYear'])
            ->where('period_month', $validated['periodMonth']);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();

        if ($period === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'period' => null,
                    'run' => null,
                    'lines' => [],
                ],
            ]);
        }

        $monthlyRun = $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_MONTHLY, $companyId);
        $thrRun = $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_THR, $companyId);
        $pkwtRun = $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, $companyId);

        $runs = collect([$monthlyRun, $thrRun, $pkwtRun])->filter();

        if ($runs->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $this->serializePeriodBrief($period),
                    'run' => null,
                    'runs' => [],
                    'lines' => [],
                ],
            ]);
        }

        $lines = collect();
        $runBriefs = [];
        foreach ($runs as $run) {
            $runBriefs[] = $this->serializeRunBrief($run);
            $chunk = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $run->id)
                ->where('user_id', $user->id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (HcmPayrollLine $l) => $this->serializeLine($l));
            $lines = $lines->concat($chunk);
        }

        $primaryRun = $monthlyRun ?? $thrRun ?? $pkwtRun;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $this->serializePeriodBrief($period),
                'run' => $primaryRun ? $this->serializeRunBrief($primaryRun) : null,
                'runs' => $runBriefs,
                'lines' => $lines->values()->all(),
            ],
        ]);
    }

    /**
     * Admin endpoint: list all employee payslip summaries for a period's finalized run.
     */
    public function adminRunSlips(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $periodQuery = HcmPayrollPeriod::query()
            ->where('period_year', $validated['periodYear'])
            ->where('period_month', $validated['periodMonth']);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();

        if ($period === null) {
            return response()->json([
                'success'  => true,
                'data'     => ['period' => null, 'run' => null, 'slips' => []],
            ]);
        }

        $runs = collect([
            $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_MONTHLY, $companyId),
            $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_THR, $companyId),
            $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, $companyId),
        ])->filter();

        if ($runs->isEmpty()) {
            return response()->json([
                'success' => true,
                'data'    => ['period' => $this->serializePeriodBrief($period), 'run' => null, 'slips' => []],
            ]);
        }

        $run = $runs->firstWhere('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ?? $runs->firstWhere('purpose', HcmPayrollRun::PURPOSE_THR)
            ?? $runs->firstWhere('purpose', HcmPayrollRun::PURPOSE_PKWT_COMPENSATION);

        $lines = HcmPayrollLine::query()
            ->whereIn('hcm_payroll_run_id', $runs->pluck('id')->all())
                ->with(['user:id,name,email', 'user.employeeProfile:user_id,designation,team'])
            ->orderBy('sort_order')
            ->get();

        $byUser = $lines->groupBy('user_id');

        $slips = $byUser->map(function ($userLines, $userId) use ($validated) {
            $first    = $userLines->first();
            $user     = $first->user;
            $earnings   = $userLines->where('kind', 'addition')->values();
            $deductions = $userLines->where('kind', 'deduction')->values();

            $earningsTotal   = round((float) $earnings->sum('amount'), 2);
            $deductionsTotal = round((float) $deductions->sum('amount'), 2);
            $netPay          = round($earningsTotal - $deductionsTotal, 2);

            $meta        = json_decode((string) ($first->meta ?? '{}'), true);
            $userName    = $user?->name ?? ($meta['userName'] ?? "User {$userId}");
            $email       = $user?->email ?? null;

            $profile = $user?->employeeProfile;

            return [
                'userId'         => (int) $userId,
                'employeeName'   => $userName,
                'email'          => $email,
                'designation'    => $profile?->designation ?? '—',
                'team'           => $profile?->team ?? '—',
                'slipNumber'     => 'SLP-'.$validated['periodYear'].sprintf('%02d', $validated['periodMonth']).'-'.sprintf('%04d', $userId),
                'earnings'       => $earnings->map(fn ($l) => $this->serializeLine($l))->values()->all(),
                'deductions'     => $deductions->map(fn ($l) => $this->serializeLine($l))->values()->all(),
                'totals'         => [
                    'earningsTotal'   => $earningsTotal,
                    'deductionsTotal' => $deductionsTotal,
                    'netPay'          => $netPay,
                ],
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data'    => [
                'period' => $this->serializePeriodBrief($period),
                'run'    => $this->serializeRunBrief($run),
                'runs'   => $runs->map(fn (HcmPayrollRun $item) => $this->serializeRunBrief($item))->values()->all(),
                'slips'  => $slips,
            ],
        ]);
    }

    /**
     * Admin endpoint: list employee payslips across finalized monthly runs (all periods by default).
     */
    public function adminSlips(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $runsQuery = HcmPayrollRun::query()
            ->with([
                'period',
                'lines.user:id,name,email',
                'lines.user.employeeProfile:user_id,designation,team',
            ])
            ->whereIn('purpose', [
                HcmPayrollRun::PURPOSE_MONTHLY,
                HcmPayrollRun::PURPOSE_THR,
                HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
            ])
            ->when(isset($validated['periodYear']), function ($q) use ($validated): void {
                $q->whereHas('period', fn ($pq) => $pq->where('period_year', (int) $validated['periodYear']));
            })
            ->when(isset($validated['periodMonth']), function ($q) use ($validated): void {
                $q->whereHas('period', fn ($pq) => $pq->where('period_month', (int) $validated['periodMonth']));
            })
            ->orderByDesc('hcm_payroll_period_id')
            ->orderByDesc('id');
        $this->applyTenantScope($runsQuery, $companyId);
        $runs = $runsQuery->get();

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

                $rows->push([
                    'rowKey' => $run->id.'-'.$userId,
                    'runId' => (int) $run->id,
                    'periodYear' => (int) ($run->period?->period_year ?? 0),
                    'periodMonth' => (int) ($run->period?->period_month ?? 0),
                    'runStatus' => (string) $run->status,
                    'paymentStatus' => $paymentStatus,
                    'userId' => (int) $userId,
                    'employeeName' => $employeeName,
                    'email' => $user?->email,
                    'designation' => $profile?->designation ?? '—',
                    'team' => $profile?->team ?? '—',
                    'slipNumber' => 'SLP-'.(int) ($run->period?->period_year ?? 0).sprintf('%02d', (int) ($run->period?->period_month ?? 0)).'-'.sprintf('%04d', (int) $userId),
                    'earnings' => $earnings->map(fn ($l) => $this->serializeLine($l))->values()->all(),
                    'deductions' => $deductions->map(fn ($l) => $this->serializeLine($l))->values()->all(),
                    'totals' => [
                        'earningsTotal' => $earningsTotal,
                        'deductionsTotal' => $deductionsTotal,
                        'netPay' => $netPay,
                    ],
                ]);
            }
        }

        $rows = $rows
            ->groupBy(fn (array $row) => ($row['periodYear'] ?? 0).'-'.($row['periodMonth'] ?? 0).'-'.($row['userId'] ?? 0))
            ->map(function ($items): array {
                $first = $items->first();
                $earnings = collect();
                $deductions = collect();
                $paidStates = collect();
                $runIds = [];

                foreach ($items as $item) {
                    $earnings = $earnings->concat($item['earnings'] ?? []);
                    $deductions = $deductions->concat($item['deductions'] ?? []);
                    $paidStates->push($item['paymentStatus'] ?? 'unpaid');
                    $runIds[] = (int) ($item['runId'] ?? 0);
                }

                $earningsTotal = round((float) $earnings->sum('amount'), 2);
                $deductionsTotal = round((float) $deductions->sum('amount'), 2);
                $paymentStatus = $paidStates->contains('partial')
                    ? 'partial'
                    : ($paidStates->contains('unpaid') && $paidStates->contains('paid') ? 'partial' : ($paidStates->contains('paid') ? 'paid' : 'unpaid'));

                return [
                    'rowKey' => ($first['periodYear'] ?? 0).'-'.($first['periodMonth'] ?? 0).'-'.($first['userId'] ?? 0),
                    'runId' => max($runIds),
                    'runIds' => array_values(array_unique($runIds)),
                    'periodYear' => $first['periodYear'] ?? 0,
                    'periodMonth' => $first['periodMonth'] ?? 0,
                    'runStatus' => 'finalized',
                    'paymentStatus' => $paymentStatus,
                    'userId' => $first['userId'] ?? 0,
                    'employeeName' => $first['employeeName'] ?? 'User',
                    'email' => $first['email'] ?? null,
                    'designation' => $first['designation'] ?? '—',
                    'team' => $first['team'] ?? '—',
                    'slipNumber' => $first['slipNumber'] ?? null,
                    'earnings' => $earnings->values()->all(),
                    'deductions' => $deductions->values()->all(),
                    'totals' => [
                        'earningsTotal' => $earningsTotal,
                        'deductionsTotal' => $deductionsTotal,
                        'netPay' => round($earningsTotal - $deductionsTotal, 2),
                    ],
                ];
            })
            ->sortByDesc(fn (array $row) => sprintf('%04d%02d%08d', (int) ($row['periodYear'] ?? 0), (int) ($row['periodMonth'] ?? 0), (int) ($row['userId'] ?? 0)))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'rows' => $rows->values()->all(),
                'summary' => [
                    'totalRows' => $rows->count(),
                    'totalEmployees' => $rows->pluck('userId')->unique()->count(),
                    'totalPeriods' => $rows->map(fn ($r) => ($r['periodYear'] ?? 0).'-'.($r['periodMonth'] ?? 0))->unique()->count(),
                ],
            ],
        ]);
    }

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
    private function serializePeriodBrief(HcmPayrollPeriod $p): array
    {
        return [
            'id' => $p->id,
            'periodYear' => $p->period_year,
            'periodMonth' => $p->period_month,
            'status' => $p->status,
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
    private function serializeRunBrief(HcmPayrollRun $r): array
    {
        $payment = $this->paymentSummary($r);
        $meta = is_array($r->meta) ? $r->meta : [];
        $serviceFee = $this->serviceFeeSnapshotFromMeta($meta);

        return [
            'id' => $r->id,
            'purpose' => $r->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => $r->status,
            'finalizedAt' => $r->finalized_at?->toIso8601String(),
            'voidedAt' => $r->voided_at?->toIso8601String(),
            'voidedByUserId' => $r->voided_by_user_id,
            'paymentStatus' => $payment['status'],
            'paidAt' => $payment['paidAt'],
            'paidEmployeeCount' => $payment['paidEmployeeCount'],
            'employeeCount' => $payment['employeeCount'],
            'gatewayReference' => $payment['gatewayReference'],
            'policySnapshot' => is_array($meta['policySnapshot'] ?? null) ? $meta['policySnapshot'] : null,
            'lateArrivalBuffer' => is_array($meta['lateArrivalBuffer'] ?? null) ? $meta['lateArrivalBuffer'] : null,
            'platformServiceFeeRate' => $serviceFee['rate'],
            'platformServiceFeeBase' => $serviceFee['base'],
            'platformServiceFeeAmount' => $serviceFee['amount'],
            'platformServiceFeeBillingMonth' => $serviceFee['billingMonth'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRun(HcmPayrollRun $r): array
    {
        $payment = $this->paymentSummary($r);
        $totals = $this->runTotals($r);
        $meta = is_array($r->meta) ? $r->meta : [];
        $serviceFee = $this->serviceFeeSnapshotFromMeta($meta);
        $out = [
            'id' => $r->id,
            'payrollPeriodId' => $r->hcm_payroll_period_id,
            'purpose' => $r->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => $r->status,
            'calculatedAt' => $r->calculated_at?->toIso8601String(),
            'finalizedAt' => $r->finalized_at?->toIso8601String(),
            'finalizedByUserId' => $r->finalized_by_user_id,
            'finalizedByUserName' => $r->relationLoaded('finalizedBy') ? $r->finalizedBy?->name : null,
            'voidedAt' => $r->voided_at?->toIso8601String(),
            'voidedByUserId' => $r->voided_by_user_id,
            'voidedByUserName' => $r->relationLoaded('voidedBy') ? $r->voidedBy?->name : null,
            'paymentStatus' => $payment['status'],
            'paidAt' => $payment['paidAt'],
            'paidEmployeeCount' => $payment['paidEmployeeCount'],
            'employeeCount' => $payment['employeeCount'],
            'gatewayReference' => $payment['gatewayReference'],
            'policySnapshot' => is_array($meta['policySnapshot'] ?? null) ? $meta['policySnapshot'] : null,
            'lateArrivalBuffer' => is_array($meta['lateArrivalBuffer'] ?? null) ? $meta['lateArrivalBuffer'] : null,
            'platformServiceFeeRate' => $serviceFee['rate'],
            'platformServiceFeeBase' => $serviceFee['base'],
            'platformServiceFeeAmount' => $serviceFee['amount'],
            'platformServiceFeeBillingMonth' => $serviceFee['billingMonth'],
            'totals' => $totals,
        ];
        if ($r->relationLoaded('period') && $r->period) {
            $out['period'] = $this->serializePeriodBrief($r->period);
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
    private function serializeLine(HcmPayrollLine $l): array
    {
        $meta = is_array($l->meta) ? $l->meta : [];
        $affectsNetPay = $this->lineAffectsNetPay($l);

        return [
            'id' => $l->id,
            'userId' => $l->user_id,
            'userName' => $l->relationLoaded('user') ? $l->user?->name : ($meta['userName'] ?? null),
            'salaryComponentId' => $l->hcm_salary_component_id,
            'componentCode' => $l->component_code,
            'componentName' => $l->component_name,
            'kind' => $l->kind,
            'category' => $l->category,
            'amount' => round((float) $l->amount, 2),
            'sortOrder' => $l->sort_order,
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

                if (round($net, 2) > 0) {
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
        $meta = is_array($run->meta) ? $run->meta : [];
        $serviceFee = $this->serviceFeeSnapshotFromMeta($meta);

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
            'netPay' => round($earningsTotal - $deductionsTotal, 2),
            'platformServiceFeeRate' => $serviceFee['rate'],
            'platformServiceFeeBase' => $serviceFee['base'],
            'platformServiceFeeAmount' => $serviceFee['amount'],
            'platformServiceFeeBillingMonth' => $serviceFee['billingMonth'],
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
            'serviceFee' => [
                'rate' => $totals['platformServiceFeeRate'] ?? 0,
                'base' => $totals['platformServiceFeeBase'] ?? 0,
                'amount' => $totals['platformServiceFeeAmount'] ?? 0,
                'billingMonth' => $totals['platformServiceFeeBillingMonth'] ?? null,
            ],
            'employeeBreakdown' => $employeeBreakdown,
            'componentBreakdown' => $componentBreakdown,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{rate: float, base: float, amount: float, billingMonth: ?string}
     */
    private function serviceFeeSnapshotFromMeta(array $meta): array
    {
        $billingMonth = isset($meta['platform_service_fee_billing_month'])
            ? (string) $meta['platform_service_fee_billing_month']
            : null;

        if ($billingMonth === '') {
            $billingMonth = null;
        }

        return [
            'rate' => round((float) ($meta['platform_service_fee_rate'] ?? 0), 2),
            'base' => round((float) ($meta['platform_service_fee_base'] ?? 0), 2),
            'amount' => round((float) ($meta['platform_service_fee_amount'] ?? 0), 2),
            'billingMonth' => $billingMonth,
        ];
    }

    private function activeCompanyId(Request $request): ?int
    {
        return $request->attributes->get('activeCompanyId');
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: string}
     */
    private function resolvePayrollServiceFeeCharges(HcmPayrollRun $run, ?int $companyId): array
    {
        $period = HcmPayrollPeriod::query()->find($run->hcm_payroll_period_id);
        $billingMonth = $period
            ? sprintf('%04d-%02d', (int) $period->period_year, (int) $period->period_month)
            : now()->format('Y-m');

        return [0.0, 0.0, 0.0, $billingMonth];
    }

    /**
     * M4 — Guard: block finalizing a monthly payroll run when there are approved
     * terminations whose termination_date falls on/before this period's end and
     * they still have no settlement_payroll_period_id linked.
     *
     * This forces the HR flow to link/finalize the settlement period for any
     * approved terminations before locking the monthly run for that period.
     */
    private function unsettledTerminationBlocker(HcmPayrollRun $run, ?int $companyId): ?JsonResponse
    {
        $period = HcmPayrollPeriod::query()->find($run->hcm_payroll_period_id);
        if ($period === null) {
            return null;
        }

        $year = (int) $period->period_year;
        $month = (int) $period->period_month;
        if ($year <= 0 || $month <= 0) {
            return null;
        }

        $periodEnd = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $query = HcmTermination::query()
            ->where('status', 'approved')
            ->whereNull('settlement_payroll_period_id')
            ->whereDate('termination_date', '<=', $periodEnd);

        $scopeCompanyId = $companyId ?? $run->company_id;
        if ($scopeCompanyId !== null) {
            $query->where(function ($q) use ($scopeCompanyId): void {
                $q->where('company_id', $scopeCompanyId)->orWhereNull('company_id');
            });
        }

        $pendingCount = (int) $query->count();
        if ($pendingCount === 0) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'PAYROLL_UNSETTLED_TERMINATIONS',
                'message' => 'Cannot finalize monthly payroll: there are approved terminations without a linked settlement period.',
                'meta' => [
                    'pendingCount' => $pendingCount,
                    'periodEnd' => $periodEnd,
                ],
            ],
        ], 422);
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($companyId): void {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    private function guardPayrollReconciliation(Request $request, HcmPayrollRun $run, string $action): ?JsonResponse
    {
        if (! (bool) config('hcm.export_reconciliation.enabled', true)) {
            return null;
        }

        if (! (bool) config(sprintf('hcm.export_reconciliation.enforce.payroll_run.%s', $action), false)) {
            return null;
        }

        $reconciliation = $request->input('reconciliation', []);
        $filterPayload = is_array($reconciliation['filterPayload'] ?? null) ? $reconciliation['filterPayload'] : [];
        $datasetChecksum = isset($reconciliation['datasetChecksum']) ? (string) $reconciliation['datasetChecksum'] : null;
        $strictChecksum = (bool) ($reconciliation['strictChecksum'] ?? config('hcm.export_reconciliation.strict_checksum', false));

        try {
            app(ReconciliationGateService::class)->assertCanProceed(
                $this->activeCompanyId($request),
                'payroll_run',
                $action,
                (string) $run->id,
                $filterPayload,
                $datasetChecksum,
                $strictChecksum,
            );
        } catch (ExportReconciliationException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], $exception->status());
        }

        return null;
    }
}
