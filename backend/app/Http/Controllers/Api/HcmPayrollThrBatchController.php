<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\HcmThrBatch;
use App\Models\HcmThrBatchLine;
use App\Notifications\ThrBatchGeneratedNotification;
use App\Notifications\ThrBatchDisbursedNotification;
use App\Models\User;
use App\Services\Hcm\ThrBatchService;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;
use App\Services\Reconciliation\ReconciliationGateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HcmPayrollThrBatchController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly ThrBatchService $thrBatchService
    ) {}

    public function show(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'calendarYear' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int) $validated['calendarYear'];
        $companyId = $this->activeCompanyId($request);
        $draft = $this->thrBatchService->findDraftBatch($year, $companyId);
        if ($draft !== null) {
            $lines = HcmThrBatchLine::query()
                ->where('hcm_thr_batch_id', $draft->id)
                ->with(['user.employeeProfile'])
                ->orderBy('user_id')
                ->get()
                ->map(fn (HcmThrBatchLine $l) => $this->thrBatchService->serializeBatchLine($l, $year));

            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => $this->thrBatchService->serializeBatch($draft, true),
                    'lines' => $lines,
                ],
            ]);
        }

        $assigned = HcmThrBatch::query()
            ->where('calendar_year', $year)
            ->where('status', HcmThrBatch::STATUS_ASSIGNED)
            ->where(function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->orderByDesc('id')
            ->first();

        if ($assigned !== null) {
            $lines = HcmThrBatchLine::query()
                ->where('hcm_thr_batch_id', $assigned->id)
                ->with(['user.employeeProfile'])
                ->orderBy('user_id')
                ->get()
                ->map(fn (HcmThrBatchLine $l) => $this->thrBatchService->serializeBatchLine($l, $year));

            return response()->json([
                'success' => true,
                'data' => [
                    'batch' => $this->thrBatchService->serializeBatch($assigned, false),
                    'lines' => $lines,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'batch' => null,
                'lines' => [],
            ],
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'calendarYear' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            $result = $this->thrBatchService->generateList(
                (int) $validated['calendarYear'],
                $request->user()?->id,
                $this->activeCompanyId($request),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->mapBatchException($e);
        }

        // Notify company admins that THR batch has been generated
        $this->notifyCompanyAdminsThr(
            $this->activeCompanyId($request),
            new ThrBatchGeneratedNotification($result['batch']),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'batch' => $this->thrBatchService->serializeBatch($result['batch'], true),
                'lines' => $result['lines'],
            ],
        ]);
    }

    public function disburse(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'batchId' => ['required', 'integer', 'exists:hcm_thr_batches,id'],
            'userIds' => ['required', 'array', 'min:1'],
            'userIds.*' => ['integer', 'exists:users,id'],
        ]);

        if ($error = $this->guardThrBatchReconciliation($request, (int) $validated['batchId'], 'disburse')) {
            return $error;
        }

        try {
            $out = $this->thrBatchService->disburseSelectedLines(
                (int) $validated['batchId'],
                $validated['userIds'],
                (int) $request->user()->id,
                $this->activeCompanyId($request),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->mapBatchException($e);
        }

        $freshBatch = HcmThrBatch::query()
            ->whereKey((int) $validated['batchId'])
            ->where(function (Builder $query) use ($request): void {
                $companyId = $this->activeCompanyId($request);
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->firstOrFail();

        // Notify company admins that THR has been disbursed
        $this->notifyCompanyAdminsThr(
            $this->activeCompanyId($request),
            new ThrBatchDisbursedNotification($freshBatch),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'disbursementId' => $out['disbursement']?->id,
                'skippedAlreadyPaidUserIds' => $out['skippedAlreadyPaidUserIds'],
                'lines' => $out['lines'],
                'batch' => $this->thrBatchService->serializeBatch($freshBatch, true),
            ],
        ]);
    }

    public function postPayroll(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'batchId' => ['required', 'integer', 'exists:hcm_thr_batches,id'],
        ]);

        if ($error = $this->guardThrBatchReconciliation($request, (int) $validated['batchId'], 'post_payroll')) {
            return $error;
        }

        try {
            $out = $this->thrBatchService->postPaidLinesToPayroll(
                (int) $validated['batchId'],
                (int) $request->user()->id,
                $this->activeCompanyId($request),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->mapBatchException($e);
        }

        $run = $out['run'];
        $period = $out['period'];

        return response()->json([
            'success' => true,
            'data' => [
                'payrollPeriodId' => $period->id,
                'periodYear' => $period->period_year,
                'periodMonth' => $period->period_month,
                'payrollRunId' => $run->id,
            ],
        ]);
    }

    public function myThrSlip(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_REQUIRED',
                    'message' => 'Unauthorized.',
                ],
            ], 401);
        }

        $validated = $request->validate([
            'calendarYear' => ['sometimes', 'nullable', 'integer', 'min:2000', 'max:2100'],
        ]);
        $companyId = $this->activeCompanyId($request);
        $filterYear = isset($validated['calendarYear']) ? (int) $validated['calendarYear'] : null;

        $candidates = HcmThrBatchLine::query()
            ->with('batch')
            ->where('user_id', $user->id)
            ->whereNotNull('slip_storage_path')
            ->where('slip_storage_path', '!=', '')
            ->whereHas('batch', function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->get();

        $history = $candidates
            ->sortByDesc(fn (HcmThrBatchLine $l) => [$l->batch->calendar_year, $l->id])
            ->map(fn (HcmThrBatchLine $l) => [
                'lineId' => $l->id,
                'calendarYear' => (int) $l->batch->calendar_year,
            ])
            ->values()
            ->all();

        $picked = null;
        if ($filterYear !== null) {
            $picked = $candidates->first(fn (HcmThrBatchLine $l) => (int) $l->batch->calendar_year === $filterYear);
        }
        if ($picked === null) {
            $picked = $candidates->sortByDesc(fn (HcmThrBatchLine $l) => [$l->batch->calendar_year, $l->id])->first();
        }

        if ($picked === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'line' => null,
                    'batch' => null,
                    'history' => $history,
                ],
            ]);
        }

        $picked->loadMissing(['user.employeeProfile']);

        return response()->json([
            'success' => true,
            'data' => [
                'line' => $this->thrBatchService->serializeBatchLine($picked),
                'batch' => $this->thrBatchService->serializeBatch($picked->batch, false),
                'history' => $history,
            ],
        ]);
    }

    public function slip(Request $request, string $line): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_REQUIRED',
                    'message' => 'Unauthorized.',
                ],
            ], 401);
        }

        $companyId = $this->activeCompanyId($request);
        $batchLineQuery = HcmThrBatchLine::query()
            ->with('batch')
            ->whereHas('batch', function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            });
        $this->applyBatchLineIdentifierScope($batchLineQuery, $line);
        $batchLine = $batchLineQuery->first();
        if ($batchLine === null || $batchLine->slip_storage_path === null || $batchLine->slip_storage_path === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'THR_SLIP_NOT_FOUND',
                    'message' => 'Slip THR belum tersedia untuk baris ini.',
                ],
            ], 404);
        }

        $isAdmin = $user->isHcmAdmin();
        $isOwner = (int) $batchLine->user_id === (int) $user->id;
        if (! $isAdmin && ! $isOwner) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Forbidden.',
                ],
            ], 403);
        }

        $path = storage_path('app/private/'.$batchLine->slip_storage_path);
        if (! is_file($path)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'THR_SLIP_FILE_MISSING',
                    'message' => 'Berkas slip tidak ditemukan di server.',
                ],
            ], 404);
        }

        $calendarYear = $batchLine->batch !== null ? (int) $batchLine->batch->calendar_year : 0;
        $raw = is_string($batchLine->thr_slip_public_no ?? null) ? trim($batchLine->thr_slip_public_no) : '';
        if ($raw === '') {
            $raw = $calendarYear > 0 ? 'THR-'.$calendarYear.'-'.$batchLine->id : 'line-'.$batchLine->id;
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $raw);
        $slipFile = 'thr-slip-'.$safe.'.pdf';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$slipFile.'"',
        ]);
    }

    private function applyBatchLineIdentifierScope(Builder $query, string $identifier): Builder
    {
        if (Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }

    public function sendSlip(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'batchId' => ['required', 'integer', 'exists:hcm_thr_batches,id'],
            'lineIds' => ['required', 'array', 'min:1'],
            'lineIds.*' => ['integer', 'exists:hcm_thr_batch_lines,id'],
        ]);

        try {
            $updated = $this->thrBatchService->markSlipNotificationsSent(
                (int) $validated['batchId'],
                $validated['lineIds'],
                $this->activeCompanyId($request),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->mapBatchException($e);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'updatedLineIds' => $updated,
                'note' => 'Notifikasi email/WhatsApp dapat dihubungkan ke driver terpisah; status "terkirim" dicatat di sistem.',
            ],
        ]);
    }

    private function mapBatchException(\InvalidArgumentException $e): JsonResponse
    {
        $map = [
            'THR_SETUP_CUTOFF_REQUIRED' => ['THR_SETUP_CUTOFF_REQUIRED', 'Isi tanggal cut-off perhitungan di pengaturan tahun ini sebelum generate.', 422],
            'THR_YEAR_ALREADY_ASSIGNED' => ['THR_YEAR_ALREADY_ASSIGNED', 'THR untuk tahun ini sudah di-assign ke payroll. Hubungi admin jika perlu ulang dari awal.', 422],
            'THR_BATCH_NOT_DRAFT' => ['THR_BATCH_NOT_DRAFT', 'Batch THR tidak dalam status draft.', 422],
            'THR_PAYMENT_DATE_REQUIRED' => ['THR_PAYMENT_DATE_REQUIRED', 'Isi tanggal pembayaran THR di pengaturan tahun ini sebelum posting payroll.', 422],
            'THR_SALARY_COMPONENT_MISSING' => ['THR_SALARY_COMPONENT_MISSING', 'Komponen gaji dengan code thr tidak ditemukan atau nonaktif.', 422],
            'THR_ASSIGN_NO_EMPLOYEES' => ['THR_ASSIGN_NO_EMPLOYEES', 'Pilih minimal satu karyawan.', 422],
            'THR_ASSIGN_USER_NOT_IN_BATCH' => ['THR_ASSIGN_USER_NOT_IN_BATCH', 'Ada userId yang tidak termasuk batch ini.', 422],
            'THR_ASSIGN_NO_POSITIVE_LINES' => ['THR_ASSIGN_NO_POSITIVE_LINES', 'Tidak ada baris THR bernilai positif untuk diposting.', 422],
            'THR_PAYROLL_FINALIZED_EXISTS' => ['THR_PAYROLL_FINALIZED_EXISTS', 'Sudah ada run THR final untuk periode pembayaran ini.', 422],
            'THR_DISBURSE_NO_EMPLOYEES' => ['THR_DISBURSE_NO_EMPLOYEES', 'Pilih minimal satu karyawan untuk pembayaran gateway.', 422],
            'THR_DISBURSE_LINE_NOT_PAYABLE' => ['THR_DISBURSE_LINE_NOT_PAYABLE', 'Baris yang dipilih tidak eligible atau THR nihil — tidak dapat dibayar lewat gateway.', 422],
            'THR_POST_UNPAID_PAYABLE_LINES' => ['THR_POST_UNPAID_PAYABLE_LINES', 'Masih ada karyawan eligible dengan THR > 0 yang belum berstatus lunas. Selesaikan pembayaran atau perbaiki yang gagal dulu.', 422],
            'THR_SEND_SLIP_NO_LINES' => ['THR_SEND_SLIP_NO_LINES', 'Pilih minimal satu baris slip.', 422],
            'THR_SEND_SLIP_INVALID_LINES' => ['THR_SEND_SLIP_INVALID_LINES', 'Ada baris yang tidak termasuk batch ini.', 422],
            'THR_SEND_SLIP_NO_PDF' => ['THR_SEND_SLIP_NO_PDF', 'Slip PDF belum ada untuk salah satu baris yang dipilih.', 422],
        ];

        $code = $e->getMessage();
        [$errorCode, $message, $status] = $map[$code] ?? ['THR_BATCH_ERROR', $code, 422];

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $message,
            ],
        ], $status);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function guardThrBatchReconciliation(Request $request, int $batchId, string $action): ?JsonResponse
    {
        if (! (bool) config('hcm.export_reconciliation.enabled', true)) {
            return null;
        }

        if (! (bool) config(sprintf('hcm.export_reconciliation.enforce.thr_batch.%s', $action), false)) {
            return null;
        }

        $reconciliation = $request->input('reconciliation', []);
        $filterPayload = is_array($reconciliation['filterPayload'] ?? null) ? $reconciliation['filterPayload'] : [];
        $datasetChecksum = isset($reconciliation['datasetChecksum']) ? (string) $reconciliation['datasetChecksum'] : null;
        $strictChecksum = (bool) ($reconciliation['strictChecksum'] ?? config('hcm.export_reconciliation.strict_checksum', false));

        try {
            app(ReconciliationGateService::class)->assertCanProceed(
                $this->activeCompanyId($request),
                'thr_batch',
                $action,
                (string) $batchId,
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

    /**
     * Dispatch a notification to all active owner/admin users of a company.
     * Best-effort — individual delivery failures are silently swallowed.
     */
    private function notifyCompanyAdminsThr(?int $companyId, object $notification): void
    {
        if ($companyId === null || $companyId <= 0) {
            return;
        }

        $adminIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->pluck('user_id');

        if ($adminIds->isEmpty()) {
            return;
        }

        User::query()->whereIn('id', $adminIds)->each(function (User $admin) use ($notification): void {
            try {
                $admin->notify(clone $notification);
            } catch (\Throwable) {
                // best-effort
            }
        });
    }
}
