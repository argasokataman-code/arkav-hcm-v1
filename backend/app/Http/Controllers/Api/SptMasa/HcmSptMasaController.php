<?php

namespace App\Http\Controllers\Api\SptMasa;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmSptMasaHeader;
use App\Support\SptMasaExportService;
use App\Support\SptMasaGenerationService;
use App\Support\SptMasaValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HcmSptMasaController extends Controller
{
    use ChecksPermissions;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function sptPayload(HcmSptMasaHeader $header, bool $withDetails = false): array
    {
        $payload = [
            'uuid' => $header->uuid,
            'periode' => $header->periode,
            'status' => $header->status,
            'totalBruto' => (float) $header->total_bruto,
            'totalPph21' => (float) $header->total_pph21,
            'totalKaryawan' => (int) $header->total_karyawan,
            'version' => (int) $header->version,
            'generatedAt' => $header->generated_at?->toIso8601String(),
            'submittedAt' => $header->submitted_at?->toIso8601String(),
            'notes' => $header->notes,
            'createdAt' => $header->created_at?->toIso8601String(),
        ];

        if ($withDetails) {
            $payload['details'] = $header->details()->orderBy('nama')->get()->map(function ($d) {
                return [
                    'uuid' => $d->uuid,
                    'nama' => $d->nama,
                    'npwp' => $d->npwp,
                    'nik' => $d->nik,
                    'employmentType' => $d->employment_type,
                    'kategoriSpt' => $d->kategori_spt,
                    'bruto' => (float) $d->bruto,
                    'pph21' => (float) $d->pph21,
                    'buktiPotongType' => $d->bukti_potong_type,
                ];
            })->values()->toArray();
        }

        return $payload;
    }

    /** Resolve header by UUID scoped to the active company. Returns error response or null. */
    private function resolveHeader(Request $request, string $sptRef): HcmSptMasaHeader|JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 400);
        }

        $header = HcmSptMasaHeader::query()
            ->where('uuid', $sptRef)
            ->where('company_id', $companyId)
            ->first();

        if (! $header) {
            return $this->errorResponse('NOT_FOUND', 'SPT Masa header not found.', 404);
        }

        return $header;
    }

    private function errorResponse(string $code, string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // List
    // ──────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.spt.view')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'periode' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['nullable', Rule::in([
                HcmSptMasaHeader::STATUS_DRAFT,
                HcmSptMasaHeader::STATUS_READY,
                HcmSptMasaHeader::STATUS_SUBMITTED,
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmSptMasaHeader::query()
            ->where('company_id', $companyId)
            ->orderByDesc('periode');

        if (! empty($validated['periode'])) {
            $query->where('periode', $validated['periode']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $rows = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($rows->items())->map(fn ($h) => $this->sptPayload($h))->values(),
                'meta' => [
                    'page' => $rows->currentPage(),
                    'perPage' => $rows->perPage(),
                    'total' => $rows->total(),
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Generate (POST /headers)
    // ──────────────────────────────────────────────────────────────────────────

    public function generate(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.spt.manage')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'periode' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'generationKey' => ['nullable', 'string', 'max:128'],
        ]);

        $periode = $validated['periode'];
        $generationKey = $validated['generationKey'] ?? null;

        // Idempotency: if generationKey provided and matching header exists, return it.
        if ($generationKey) {
            $existing = HcmSptMasaHeader::query()
                ->where('company_id', $companyId)
                ->where('generation_key', $generationKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'data' => $this->sptPayload($existing),
                ]);
            }
        }

        // Block if an active header already exists for this period.
        $activeHeader = HcmSptMasaHeader::query()
            ->where('company_id', $companyId)
            ->where('periode', $periode)
            ->whereIn('status', HcmSptMasaHeader::ACTIVE_STATUSES)
            ->first();

        if ($activeHeader) {
            return $this->errorResponse(
                'SPT_HEADER_DUPLICATE',
                "An active SPT Masa header for periode {$periode} already exists. Use regenerate to refresh it.",
                409
            );
        }

        // Gate: must have finalized monthly run.
        if (! SptMasaGenerationService::hasFinalizedMonthlyRun($companyId, $periode)) {
            return $this->errorResponse(
                'SPT_PAYROLL_NOT_FINAL',
                "No finalized monthly payroll run found for periode {$periode}.",
                422
            );
        }

        try {
            $header = SptMasaGenerationService::generate(
                $companyId,
                $periode,
                (int) $request->user()->id,
                $generationKey
            );
        } catch (\Throwable $e) {
            Log::error('SPT Masa generation failed', ['error' => $e->getMessage(), 'company_id' => $companyId, 'periode' => $periode]);
            return $this->errorResponse('GENERATION_FAILED', 'SPT Masa generation failed. Please try again.', 500);
        }

        return response()->json([
            'success' => true,
            'data' => $this->sptPayload($header),
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Show (GET /headers/{sptRef})
    // ──────────────────────────────────────────────────────────────────────────

    public function show(Request $request, string $sptRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.spt.view')) {
            return $response;
        }

        $header = $this->resolveHeader($request, $sptRef);
        if ($header instanceof JsonResponse) {
            return $header;
        }

        return response()->json([
            'success' => true,
            'data' => $this->sptPayload($header, true),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Regenerate (POST /headers/{sptRef}/regenerate)
    // ──────────────────────────────────────────────────────────────────────────

    public function regenerate(Request $request, string $sptRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.spt.manage')) {
            return $response;
        }

        $header = $this->resolveHeader($request, $sptRef);
        if ($header instanceof JsonResponse) {
            return $header;
        }

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        if ((int) $header->version !== (int) $validated['version']) {
            return $this->errorResponse('SPT_VERSION_CONFLICT', 'Version conflict. Reload the record and retry.', 409);
        }

        if ($header->isSubmitted()) {
            return $this->errorResponse('SPT_INVALID_TRANSITION', 'Cannot regenerate a submitted SPT Masa.', 422);
        }

        if (! SptMasaGenerationService::hasFinalizedMonthlyRun((int) $header->company_id, (string) $header->periode)) {
            return $this->errorResponse('SPT_PAYROLL_NOT_FINAL', 'No finalized monthly run for this period.', 422);
        }

        try {
            $header = SptMasaGenerationService::generate(
                (int) $header->company_id,
                (string) $header->periode,
                (int) $request->user()->id,
                null,
                $header
            );
        } catch (\Throwable $e) {
            Log::error('SPT Masa regeneration failed', ['error' => $e->getMessage(), 'uuid' => $sptRef]);
            return $this->errorResponse('GENERATION_FAILED', 'Regeneration failed. Please try again.', 500);
        }

        return response()->json([
            'success' => true,
            'data' => $this->sptPayload($header),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Mark Ready (POST /headers/{sptRef}/mark-ready)
    // ──────────────────────────────────────────────────────────────────────────

    public function markReady(Request $request, string $sptRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.spt.manage')) {
            return $response;
        }

        $header = $this->resolveHeader($request, $sptRef);
        if ($header instanceof JsonResponse) {
            return $header;
        }

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
        ]);

        if ((int) $header->version !== (int) $validated['version']) {
            return $this->errorResponse('SPT_VERSION_CONFLICT', 'Version conflict.', 409);
        }

        if (! $header->isDraft()) {
            return $this->errorResponse('SPT_INVALID_TRANSITION', 'Only draft SPT Masa can be marked ready.', 422);
        }

        $detailErrors = SptMasaValidationService::validateDetails($header);
        if (! empty($detailErrors)) {
            return $this->errorResponse('SPT_DETAIL_INCOMPLETE', 'One or more detail rows have missing required fields.', 422);
        }

        DB::transaction(function () use ($header): void {
            $header->status = HcmSptMasaHeader::STATUS_READY;
            $header->version += 1;
            $header->save();
        });

        return response()->json([
            'success' => true,
            'data' => $this->sptPayload($header->fresh()),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Submit (POST /headers/{sptRef}/submit)
    // ──────────────────────────────────────────────────────────────────────────

    public function submit(Request $request, string $sptRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.spt.manage')) {
            return $response;
        }

        $header = $this->resolveHeader($request, $sptRef);
        if ($header instanceof JsonResponse) {
            return $header;
        }

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ((int) $header->version !== (int) $validated['version']) {
            return $this->errorResponse('SPT_VERSION_CONFLICT', 'Version conflict.', 409);
        }

        if (! $header->isReady()) {
            return $this->errorResponse('SPT_INVALID_TRANSITION', 'Only ready SPT Masa can be submitted.', 422);
        }

        $detailErrors = SptMasaValidationService::validateDetails($header);
        if (! empty($detailErrors)) {
            return $this->errorResponse('SPT_DETAIL_INCOMPLETE', 'Detail rows are incomplete.', 422);
        }

        $totalErrors = SptMasaValidationService::validateTotals($header);
        if (! empty($totalErrors)) {
            return $this->errorResponse('SPT_TOTAL_MISMATCH', 'Header totals do not match finalized payroll lines.', 422);
        }

        DB::transaction(function () use ($header, $request, $validated): void {
            $header->status = HcmSptMasaHeader::STATUS_SUBMITTED;
            $header->version += 1;
            $header->submitted_at = now();
            $header->submitted_by_user_id = (int) $request->user()->id;
            if (! empty($validated['notes'])) {
                $header->notes = $validated['notes'];
            }
            $header->save();
        });

        return response()->json([
            'success' => true,
            'data' => $this->sptPayload($header->fresh()),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Export CSV (GET /headers/{sptRef}/export.csv)
    // ──────────────────────────────────────────────────────────────────────────

    public function exportCsv(Request $request, string $sptRef): StreamedResponse|JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.spt.view')) {
            return $response;
        }

        $header = $this->resolveHeader($request, $sptRef);
        if ($header instanceof JsonResponse) {
            return $header;
        }

        return SptMasaExportService::streamCsv($header);
    }
}
