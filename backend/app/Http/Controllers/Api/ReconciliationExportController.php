<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\ExportReconciliationEvidence;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
use App\Services\Reconciliation\ReconciliationExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReconciliationExportController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly ReconciliationExportService $exportService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensurePermission($request, 'payroll.view')) {
            return $response;
        }

        $this->normalizeReconciliationExportRequest($request);

        $validated = $request->validate([
            'featureKey' => ['required', 'string', Rule::in($this->allowedFeatureKeys())],
            'actionKey' => ['required', 'string', Rule::in($this->allowedActionKeys())],
            'scopeRef' => ['required', 'string', 'max:191'],
            'fileFormat' => ['required', 'string', Rule::in(['csv', 'xlsx', 'pdf'])],
            'filePath' => ['nullable', 'string', 'max:2048'],
            'rowCount' => ['nullable', 'integer', 'min:0'],
            'filterPayload' => ['nullable', 'array'],
            'datasetChecksum' => ['nullable', 'string', 'max:128'],
            'expiresInMinutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
        ]);

        $fileFormat = strtolower((string) $validated['fileFormat']);
        $filterPayload = $validated['filterPayload'] ?? [];

        $filePath = isset($validated['filePath']) ? ltrim((string) $validated['filePath'], '/') : '';
        if ($filePath === '') {
            if ($fileFormat !== 'csv') {
                return $this->errorResponse(
                    'VALIDATION_ERROR',
                    'filePath is required unless fileFormat is csv (server can auto-generate csv evidence).',
                    422,
                );
            }

            $rowCount = $this->inferRowCountFromFilterPayload($filterPayload, $validated['rowCount'] ?? null);
            $datasetChecksum = $validated['datasetChecksum'] ?? $this->exportService->checksumForPayload([
                'companyId' => $companyId,
                'featureKey' => (string) $validated['featureKey'],
                'actionKey' => (string) $validated['actionKey'],
                'scopeRef' => (string) $validated['scopeRef'],
                'filterPayload' => $filterPayload,
            ]);

            $filePath = $this->buildGeneratedReconciliationCsvPath(
                $companyId,
                (string) $validated['featureKey'],
                (string) $validated['actionKey'],
                (string) $validated['scopeRef'],
            );

            $csv = $this->buildGeneratedReconciliationCsv(
                $companyId,
                (string) $validated['featureKey'],
                (string) $validated['actionKey'],
                (string) $validated['scopeRef'],
                $filterPayload,
                $datasetChecksum,
            );

            if (! Storage::disk('local')->put($filePath, $csv)) {
                return $this->errorResponse('EXPORT_RECON_FILE_WRITE_FAILED', 'Failed to persist reconciliation export file.', 500);
            }

            $validated['filePath'] = $filePath;
            $validated['rowCount'] = $rowCount;
            $validated['datasetChecksum'] = $datasetChecksum;
        }

        $filePath = ltrim((string) $validated['filePath'], '/');
        // Safety: only allow evidence pointing to reconciliation exports area.
        if (! str_starts_with($filePath, 'reconciliation/')
            || str_contains($filePath, '..')
            || str_contains($filePath, "\0")
        ) {
            return $this->errorResponse('VALIDATION_ERROR', 'filePath must be under reconciliation/ and must not contain traversal.', 422);
        }

        $companyPathToken = '/company_'.(int) $companyId.'/';
        if (! str_contains($filePath, $companyPathToken)) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'filePath must include active company namespace (company_<id>).',
                422,
            );
        }

        $rowCount = (int) ($validated['rowCount'] ?? $this->inferRowCountFromFilterPayload($filterPayload, null));

        $ttlMinutes = (int) ($validated['expiresInMinutes'] ?? config('hcm.export_reconciliation.ttl_minutes', 30));
        $evidence = $this->exportService->createEvidence(
            $companyId,
            (string) $validated['featureKey'],
            (string) $validated['actionKey'],
            (string) $validated['scopeRef'],
            (int) $request->user()->id,
            $fileFormat,
            $filePath,
            $rowCount,
            $filterPayload,
            $validated['datasetChecksum'] ?? null,
            now()->addMinutes($ttlMinutes),
        );

        return response()->json([
            'success' => true,
            'data' => $this->serializeEvidence($evidence),
        ], 201);
    }

    /**
     * Backward-compatible aliases used by UI clients:
     * - `format` -> `fileFormat`
     * - `filters` -> `filterPayload`
     */
    private function normalizeReconciliationExportRequest(Request $request): void
    {
        $payload = $request->all();

        if (! isset($payload['fileFormat']) && isset($payload['format'])) {
            $payload['fileFormat'] = $payload['format'];
        }

        if (! isset($payload['filterPayload']) && isset($payload['filters'])) {
            $payload['filterPayload'] = $payload['filters'];
        }

        $request->replace($payload);
    }

    /**
     * @param  array<string, mixed>  $filterPayload
     */
    private function inferRowCountFromFilterPayload(array $filterPayload, mixed $explicitRowCount): int
    {
        if (is_numeric($explicitRowCount)) {
            return max(0, (int) $explicitRowCount);
        }

        foreach (['lineIds', 'periods'] as $key) {
            if (isset($filterPayload[$key]) && is_array($filterPayload[$key])) {
                return max(0, count($filterPayload[$key]));
            }
        }

        return 0;
    }

    private function buildGeneratedReconciliationCsvPath(
        int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef,
    ): string {
        $safeFeature = preg_replace('/[^a-z0-9_-]+/i', '-', $featureKey) ?: 'feature';
        $safeAction = preg_replace('/[^a-z0-9_-]+/i', '-', $actionKey) ?: 'action';
        $safeScope = preg_replace('/[^a-z0-9_-]+/i', '-', $scopeRef) ?: 'scope';

        return sprintf(
            'reconciliation/generated/company_%d/%s__%s__%s__%s.csv',
            $companyId,
            $safeFeature,
            $safeAction,
            $safeScope,
            (string) now()->format('YmdHisv'),
        );
    }

    /**
     * @param  array<string, mixed>  $filterPayload
     */
    private function buildGeneratedReconciliationCsv(
        ?int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef,
        array $filterPayload,
        string $datasetChecksum,
    ): string {
        // For payroll_run exports, include actual payroll line data
        if ($featureKey === 'payroll_run' && $actionKey === 'disburse') {
            return $this->buildPayrollRunReconciliationCsv($companyId, (int) $scopeRef, $filterPayload, $datasetChecksum);
        }

        $stream = fopen('php://temp', 'w+');
        if (! $stream) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Field', 'Value']);
        fputcsv($stream, ['feature_key', $featureKey]);
        fputcsv($stream, ['action_key', $actionKey]);
        fputcsv($stream, ['scope_ref', $scopeRef]);
        fputcsv($stream, ['dataset_checksum', $datasetChecksum]);
        fputcsv($stream, ['generated_at', now()->toIso8601String()]);

        fputcsv($stream, []);
        fputcsv($stream, ['filter_key', 'filter_value']);

        foreach ($filterPayload as $key => $value) {
            $formattedValue = is_scalar($value)
                ? (string) $value
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            fputcsv($stream, [(string) $key, $formattedValue]);
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * Build CSV with actual payroll line data for reconciliation.
     * Extracts user IDs from filterPayload and fetches corresponding payroll lines.
     *
     * @param  array<string, mixed>  $filterPayload
     */
    private function buildPayrollRunReconciliationCsv(?int $companyId, int $runId, array $filterPayload, string $datasetChecksum): string {
        // Extract user IDs from filter payload: { periods: [{userId: 1}, ...] }
        $userIds = [];
        if (is_array($filterPayload) && isset($filterPayload['periods']) && is_array($filterPayload['periods'])) {
            foreach ($filterPayload['periods'] as $period) {
                if (is_array($period) && isset($period['userId'])) {
                    $userIds[] = (int) $period['userId'];
                }
            }
        }

        // Fetch payroll lines for the run and selected users
        $query = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $runId)
            ->orderBy('user_id')
            ->orderBy('sort_order');

        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $payrollLines = $query->get();

        $componentAffectsNetPay = [];
        $componentIds = $payrollLines->pluck('hcm_salary_component_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        if ($componentIds->isNotEmpty()) {
            $componentAffectsNetPay = HcmSalaryComponent::query()
                ->whereIn('id', $componentIds->all())
                ->pluck('affects_net_pay', 'id')
                ->map(fn ($value) => (bool) $value)
                ->all();
        }
        [$serviceFeeRate, $serviceFeeBase, $serviceFeeAmount, $serviceFeeBillingMonth] = $this->resolvePayrollServiceFeeSnapshot($runId, $companyId, $payrollLines);

        // Pre-compute per-employee totals
        $employeeTotals = [];
        foreach ($payrollLines as $line) {
            $uid = (int) $line->user_id;
            if (! isset($employeeTotals[$uid])) {
                $employeeTotals[$uid] = ['name' => (string) ($line->user_name ?? ''), 'gross' => 0.0, 'deductions' => 0.0];
            }
            $componentId = $line->hcm_salary_component_id !== null ? (int) $line->hcm_salary_component_id : null;
            $affectsNetPay = $componentId !== null
                ? ($componentAffectsNetPay[$componentId] ?? true)
                : true;
            if ($affectsNetPay) {
                if ($line->kind === 'addition') {
                    $employeeTotals[$uid]['gross'] += (float) $line->amount;
                } elseif ($line->kind === 'deduction') {
                    $employeeTotals[$uid]['deductions'] += (float) $line->amount;
                }
            }
        }

        $stream = fopen('php://temp', 'w+');
        if (! $stream) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'run_id',
            'user_id',
            'user_name',
            'kind',
            'component_code',
            'component_name',
            'amount',
            'affects_net_pay',
            'gross_total',
            'deductions_total',
            'net_total',
            'service_fee_rate_percent',
            'service_fee_base_amount',
            'service_fee_amount',
            'service_fee_billing_month',
            'dataset_checksum',
        ]);

        fputcsv($stream, [
            (string) $runId,
            '',
            '',
            '',
            '',
            'METADATA',
            '',
            '',
            '',
            '',
            '',
            (string) round($serviceFeeRate, 2),
            (string) round($serviceFeeBase, 2),
            (string) round($serviceFeeAmount, 2),
            $serviceFeeBillingMonth,
            $datasetChecksum,
        ]);

        fputcsv($stream, []);

        foreach ($payrollLines as $line) {
            fputcsv($stream, [
                (string) $runId,
                (string) $line->user_id,
                (string) ($line->user_name ?? ''),
                (string) ($line->kind ?? ''),
                (string) ($line->component_code ?? ''),
                (string) ($line->component_name ?? ''),
                (string) $line->amount,
                $affectsNetPay ? 'yes' : 'no',
                '',
                '',
                '',
                (string) round($serviceFeeRate, 2),
                (string) round($serviceFeeBase, 2),
                (string) round($serviceFeeAmount, 2),
                $serviceFeeBillingMonth,
                '',
            ]);
        }

        // Per-employee subtotal rows
        fputcsv($stream, []);
        $grandGross = 0.0;
        $grandDeductions = 0.0;
        foreach ($employeeTotals as $uid => $totals) {
            $net = $totals['gross'] - $totals['deductions'];
            $grandGross += $totals['gross'];
            $grandDeductions += $totals['deductions'];
            fputcsv($stream, [
                (string) $runId,
                (string) $uid,
                $totals['name'],
                'SUBTOTAL',
                '',
                'Subtotal Karyawan',
                '',
                '',
                (string) round($totals['gross'], 2),
                (string) round($totals['deductions'], 2),
                (string) round($net, 2),
                (string) round($serviceFeeRate, 2),
                (string) round($serviceFeeBase, 2),
                (string) round($serviceFeeAmount, 2),
                $serviceFeeBillingMonth,
                '',
            ]);
        }

        // Grand total row
        fputcsv($stream, []);
        fputcsv($stream, [
            (string) $runId,
            '',
            '',
            'GRAND_TOTAL',
            '',
            'Total Semua Karyawan',
            '',
            '',
            (string) round($grandGross, 2),
            (string) round($grandDeductions, 2),
            (string) round($grandGross - $grandDeductions, 2),
            (string) round($serviceFeeRate, 2),
            (string) round($serviceFeeBase, 2),
            (string) round($serviceFeeAmount, 2),
            $serviceFeeBillingMonth,
            $datasetChecksum,
        ]);

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, HcmPayrollLine>  $payrollLines
     * @return array{0: float, 1: float, 2: float, 3: string}
     */
    private function resolvePayrollServiceFeeSnapshot(int $runId, ?int $companyId, $payrollLines): array
    {
        $runQuery = HcmPayrollRun::query()->with('period')->whereKey($runId);
        if ($companyId !== null) {
            $runQuery->where('company_id', $companyId);
        }

        $run = $runQuery->first();
        if (! $run) {
            return [0.0, 0.0, 0.0, ''];
        }

        $billingMonth = $run->period
            ? sprintf('%04d-%02d', (int) $run->period->period_year, (int) $run->period->period_month)
            : now()->format('Y-m');

        return [0.0, 0.0, 0.0, $billingMonth];
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensurePermission($request, 'payroll.view')) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'featureKey' => ['nullable', 'string', Rule::in($this->allowedFeatureKeys())],
            'actionKey' => ['nullable', 'string', Rule::in($this->allowedActionKeys())],
            'scopeRef' => ['nullable', 'string', 'max:191'],
            'includeExpired' => ['nullable', 'boolean'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);

        $query = ExportReconciliationEvidence::query()
            ->where('company_id', $companyId)
            ->with('exportedBy:id,name');

        if (! empty($validated['featureKey'])) {
            $query->where('feature_key', (string) $validated['featureKey']);
        }

        if (! empty($validated['actionKey'])) {
            $query->where('action_key', (string) $validated['actionKey']);
        }

        if (! empty($validated['scopeRef'])) {
            $query->where('scope_ref', (string) $validated['scopeRef']);
        }

        if (! (bool) ($validated['includeExpired'] ?? false)) {
            $query->where(function ($inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        }

        $paginator = $query
            ->orderByDesc('exported_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map(fn (ExportReconciliationEvidence $evidence) => $this->serializeEvidence($evidence))->values(),
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function download(Request $request, string $id): BinaryFileResponse|JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensurePermission($request, 'payroll.view')) {
            return $response;
        }

        $evidenceQuery = ExportReconciliationEvidence::query()->where('company_id', $companyId);
        $this->applyIdentifierScope($evidenceQuery, $id);
        $evidence = $evidenceQuery->first();

        if (! $evidence) {
            return $this->errorResponse('EXPORT_RECON_NOT_FOUND', 'Export reconciliation evidence not found.', 404);
        }

        $relativePath = ltrim((string) $evidence->file_path, '/');
        $companyPathToken = '/company_'.(int) $companyId.'/';
        if (! str_contains($relativePath, $companyPathToken)) {
            return $this->errorResponse('EXPORT_RECON_FORBIDDEN', 'Export file does not belong to active company namespace.', 403);
        }

        if ($relativePath === '' || ! Storage::disk('local')->exists($relativePath)) {
            return $this->errorResponse('EXPORT_RECON_FILE_NOT_FOUND', 'Reconciliation export file not found.', 404);
        }

        $fullPath = Storage::disk('local')->path($relativePath);
        $downloadName = basename($relativePath);

        return response()->download($fullPath, $downloadName);
    }

    private function activeCompanyId(Request $request): ?int
    {
        return $request->attributes->get('activeCompanyId');
    }

    /**
     * @return array<int, string>
     */
    private function allowedFeatureKeys(): array
    {
        return [
            ExportReconciliationEvidence::FEATURE_PAYROLL_RUN,
            ExportReconciliationEvidence::FEATURE_THR_BATCH,
            ExportReconciliationEvidence::FEATURE_PKWT_COMPENSATION,
            ExportReconciliationEvidence::FEATURE_INVOICE,
            ExportReconciliationEvidence::FEATURE_PAYMENT,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedActionKeys(): array
    {
        return [
            ExportReconciliationEvidence::ACTION_FINALIZE,
            ExportReconciliationEvidence::ACTION_DISBURSE,
            ExportReconciliationEvidence::ACTION_POST_PAYROLL,
            ExportReconciliationEvidence::ACTION_MARK_PAID,
            ExportReconciliationEvidence::ACTION_VERIFY,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvidence(ExportReconciliationEvidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'companyId' => $evidence->company_id,
            'featureKey' => $evidence->feature_key,
            'actionKey' => $evidence->action_key,
            'scopeRef' => $evidence->scope_ref,
            'exportedByUserId' => $evidence->exported_by_user_id,
            'exportedByUserName' => $evidence->exportedBy?->name,
            'exportedAt' => $evidence->exported_at?->toIso8601String(),
            'fileFormat' => $evidence->file_format,
            'filePath' => $evidence->file_path,
            'rowCount' => $evidence->row_count,
            'filterPayload' => $evidence->filter_payload ?? [],
            'datasetChecksum' => $evidence->dataset_checksum,
            'expiresAt' => $evidence->expires_at?->toIso8601String(),
            'isExpired' => $evidence->isExpired(),
            'createdAt' => $evidence->created_at?->toIso8601String(),
        ];
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private function applyIdentifierScope($query, string $identifier)
    {
        $hasUuidColumn = Schema::hasColumn((new ExportReconciliationEvidence)->getTable(), 'uuid');
        if ($hasUuidColumn && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }
}
