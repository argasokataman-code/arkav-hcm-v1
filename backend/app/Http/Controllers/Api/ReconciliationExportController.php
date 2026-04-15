<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\ExportReconciliationEvidence;
use App\Services\Reconciliation\ReconciliationExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReconciliationExportController extends Controller
{
    use EnsuresHcmAdmin;

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

        if ($response = $this->ensureHcmAdminForCompany($request, $companyId)) {
            return $response;
        }

        $validated = $request->validate([
            'featureKey' => ['required', 'string', Rule::in($this->allowedFeatureKeys())],
            'actionKey' => ['required', 'string', Rule::in($this->allowedActionKeys())],
            'scopeRef' => ['required', 'string', 'max:191'],
            'fileFormat' => ['required', 'string', Rule::in(['csv', 'xlsx', 'pdf'])],
            'filePath' => ['required', 'string', 'max:2048'],
            'rowCount' => ['required', 'integer', 'min:0'],
            'filterPayload' => ['nullable', 'array'],
            'datasetChecksum' => ['nullable', 'string', 'max:128'],
            'expiresInMinutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
        ]);

        $ttlMinutes = (int) ($validated['expiresInMinutes'] ?? config('hcm.export_reconciliation.ttl_minutes', 30));
        $evidence = $this->exportService->createEvidence(
            $companyId,
            (string) $validated['featureKey'],
            (string) $validated['actionKey'],
            (string) $validated['scopeRef'],
            (int) $request->user()->id,
            (string) $validated['fileFormat'],
            (string) $validated['filePath'],
            (int) $validated['rowCount'],
            $validated['filterPayload'] ?? [],
            $validated['datasetChecksum'] ?? null,
            now()->addMinutes($ttlMinutes),
        );

        return response()->json([
            'success' => true,
            'data' => $this->serializeEvidence($evidence),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensureHcmAdminForCompany($request, $companyId)) {
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

        if ($response = $this->ensureHcmAdminForCompany($request, $companyId)) {
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
