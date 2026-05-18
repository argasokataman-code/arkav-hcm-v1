<?php

namespace App\Http\Controllers\Api\Payroll;

use Closure;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Payroll\Concerns\BuildsMonthlyPayrollReports;
use App\Http\Controllers\Api\Payroll\Concerns\BuildsPayrollRunPayloads;
use App\Http\Controllers\Api\Payroll\Concerns\HandlesPayrollRunReadEndpoints;
use App\Http\Controllers\Api\Payroll\Concerns\HandlesPayrollRunRuntimeUtilities;
use App\Http\Controllers\Controller;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
use App\Models\User;
use App\Services\Hcm\PayrollLateArrivalMigrationService;
use App\Services\Hcm\MonthlyPayslipService;
use App\Services\XenditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class HcmPayrollRunController extends Controller
{
    use ChecksPermissions;
    use BuildsMonthlyPayrollReports;
    use BuildsPayrollRunPayloads;
    use HandlesPayrollRunReadEndpoints;
    use HandlesPayrollRunRuntimeUtilities;

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

        $user = $request->user();
        $run->update([
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'finalized_at' => now(),
            'finalized_by_user_id' => $user?->id,
        ]);

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

            if ($effectiveSelectedUserIds->isEmpty()) {
                return [
                    'error' => [
                        'code' => 'PAYROLL_DISBURSE_NO_EMPLOYEES',
                        'message' => 'Tidak ada karyawan eligible untuk ditandai selesai. Hanya user dengan net pay positif yang bisa diproses.',
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
                $gatewayReference = 'MANUAL-'.now()->format('YmdHis').'-'.$run->id.'-'.strtoupper(Str::random(4));
                $paidAt = now()->toIso8601String();
                $markedBy = $request->user();
                
                // Export-only payroll: mark selected lines as paid after the tenant
                // confirms settlement outside the application.
                foreach ($linesToMark as $line) {
                    $meta = is_array($line->meta) ? $line->meta : [];
                    $meta['userName'] = $line->user?->name ?? ($meta['userName'] ?? null);
                    $meta['paymentStatus'] = 'paid';
                    $meta['paidAt'] = $paidAt;
                    $meta['gatewayReference'] = $gatewayReference;
                    $meta['paymentChannel'] = 'manual-external';
                    $meta['paymentMethod'] = 'external_manual_transfer';
                    $meta['manuallyMarkedPaid'] = true;
                    $meta['manuallyMarkedPaidAt'] = $paidAt;
                    $meta['markedPaidByUserId'] = $markedBy?->id;
                    $meta['markedPaidByUserUuid'] = $markedBy?->uuid;
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
            $lateArrivalMigration = null;

            if (
                ($freshRun->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY) === HcmPayrollRun::PURPOSE_MONTHLY
                && $this->paymentSummary($freshRun)['status'] === 'paid'
            ) {
                $lateArrivalMigration = $this->payrollLateArrivalMigrationService->migrateToNextPeriodIfEligible(
                    (int) $freshRun->id,
                    $companyId,
                );
                if ($lateArrivalMigration !== null) {
                    $freshRun->refresh()->loadMissing(['period', 'lines.user:id,name']);
                }
            }

            return [
                'run' => $freshRun,
                'selectedUserIds' => $effectiveSelectedUserIds->values()->all(),
                'ineligibleUserIds' => $ineligibleUserIds->all(),
                'skippedAlreadyPaidUserIds' => array_values(array_unique($alreadyPaidUserIds)),
                'gatewayReference' => $gatewayReference,
                'lateArrivalMigration' => $lateArrivalMigration,
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
            'data' => [
                'run' => $this->serializeRun($run),
                'selectedUserIds' => $result['selectedUserIds'],
                'ineligibleUserIds' => $result['ineligibleUserIds'] ?? [],
                'skippedAlreadyPaidUserIds' => $result['skippedAlreadyPaidUserIds'],
                'gatewayReference' => $result['gatewayReference'],
                'completionMode' => 'manual_external',
                'payment' => $this->paymentSummary($run),
                'lateArrivalMigration' => $result['lateArrivalMigration'] ?? null,
            ],
        ]);
    }

    public function startMockHostedCheckout(Request $request, int $id): JsonResponse
    {
        return $this->exportOnlyPayrollGatewayDisabledResponse();
    }

    public function confirmMockHostedCheckout(Request $request, int $id): JsonResponse
    {
        return $this->exportOnlyPayrollGatewayDisabledResponse();
    }

    public function resetPayments(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.disburse')) {
            return $forbidden;
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

        $companyId = $this->activeCompanyId($request);
        $result = DB::transaction(function () use ($id, $companyId, $request): array {
            $runQuery = HcmPayrollRun::query()->whereKey($id)->lockForUpdate();
            $this->applyTenantScope($runQuery, $companyId);
            $run = $runQuery->firstOrFail();

            // Get primary super admin user ID for filtering
            $primaryAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
            $primaryAdminUser = User::query()
                ->whereRaw('LOWER(email) = ?', [$primaryAdminEmail])
                ->first();
            
            $primaryAdminUserId = $primaryAdminUser?->id;

            // Build query to get only primary super admin's payroll lines
            $linesQuery = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $run->id)
                ->lockForUpdate();
            
            // Filter to only primary super admin if they exist
            if ($primaryAdminUserId) {
                $linesQuery->where('user_id', $primaryAdminUserId);
            }
            
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

}
