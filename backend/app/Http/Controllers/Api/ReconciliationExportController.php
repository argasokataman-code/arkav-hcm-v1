<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\ExportReconciliationEvidence;
use App\Models\HcmPayrollLine;
use App\Services\Reconciliation\ReconciliationExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        // Default: metadata-only CSV
        $lines = [
            'feature_key,action_key,scope_ref,dataset_checksum',
            sprintf(
                '%s,%s,%s,%s',
                $this->csvEscape($featureKey),
                $this->csvEscape($actionKey),
                $this->csvEscape($scopeRef),
                $this->csvEscape($datasetChecksum),
            ),
            '',
            'filter_payload_json',
            $this->csvEscape((string) json_encode($filterPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];

        return implode("\n", $lines)."\n";
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

        // Build CSV header
        $lines = [
            'run_id,user_id,user_name,kind,component_code,component_name,amount,affects_net_pay,dataset_checksum',
        ];

        // Add metadata row
        $lines[] = sprintf(
            '%s,%s,%s,%s,%s,%s,%s,%s,%s',
            $this->csvEscape((string) $runId),
            '',
            '',
            '',
            '',
            'METADATA',
            '',
            '',
            $this->csvEscape($datasetChecksum),
        );

        // Add blank line
        $lines[] = '';

        // Add payroll line rows
        foreach ($payrollLines as $line) {
            $lines[] = sprintf(
                '%s,%s,%s,%s,%s,%s,%s,%s,%s',
                $this->csvEscape((string) $runId),
                $this->csvEscape((string) $line->user_id),
                $this->csvEscape((string) ($line->user_name ?? '')),
                $this->csvEscape((string) ($line->kind ?? '')),
                $this->csvEscape((string) ($line->component_code ?? '')),
                $this->csvEscape((string) ($line->component_name ?? '')),
                $this->csvEscape((string) $line->amount),
                $this->csvEscape($line->affects_net_pay ? 'yes' : 'no'),
                '',
            );
        }

        return implode("\n", $lines)."\n";
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, '"') || str_contains($value, ',') || str_contains($value, "\n") || str_contains($value, "\r")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
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

    public function download(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensurePermission($request, 'payroll.view')) {
            return $response;
        }

        $evidence = ExportReconciliationEvidence::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();

        if (! $evidence) {
            return $this->errorResponse('EXPORT_RECON_NOT_FOUND', 'Export reconciliation evidence not found.', 404);
        }

        $relativePath = ltrim((string) $evidence->file_path, '/');
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
}
