<?php

namespace App\Http\Controllers\Api\Reconciliation;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\ExportReconciliationEvidence;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Models\HcmSalaryComponent;
use App\Models\HcmThrBatch;
use App\Models\HcmThrBatchLine;
use App\Services\Hcm\PkwtCompensationService;
use App\Services\Reconciliation\ReconciliationExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Support\Exports\TabularExportResponse;
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
            if ($fileFormat !== 'csv' && $fileFormat !== 'xlsx') {
                return $this->errorResponse(
                    'VALIDATION_ERROR',
                    'filePath is required unless fileFormat is csv or xlsx (server can auto-generate these formats).',
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

            if ($fileFormat === 'xlsx') {
                $filePath = $this->buildGeneratedReconciliationFilePath(
                    $companyId,
                    (string) $validated['featureKey'],
                    (string) $validated['actionKey'],
                    (string) $validated['scopeRef'],
                    'xlsx',
                );

                $rows = $this->buildGeneratedReconciliationRows(
                    $companyId,
                    (string) $validated['featureKey'],
                    (string) $validated['actionKey'],
                    (string) $validated['scopeRef'],
                    $filterPayload,
                    $datasetChecksum,
                );

                $xlsxBinary = TabularExportResponse::buildXlsxBinary(
                    basename($filePath, '.xlsx'),
                    $rows['headers'],
                    $rows['data'],
                );

                if ($xlsxBinary === null || ! Storage::disk('local')->put($filePath, $xlsxBinary)) {
                    return $this->errorResponse('EXPORT_RECON_FILE_WRITE_FAILED', 'Failed to persist reconciliation xlsx file.', 500);
                }
            } else {
                $filePath = $this->buildGeneratedReconciliationFilePath(
                    $companyId,
                    (string) $validated['featureKey'],
                    (string) $validated['actionKey'],
                    (string) $validated['scopeRef'],
                    'csv',
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

    private function buildGeneratedReconciliationFilePath(
        int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef,
        string $extension = 'csv',
    ): string {
        $safeFeature = preg_replace('/[^a-z0-9_-]+/i', '-', $featureKey) ?: 'feature';
        $safeAction = preg_replace('/[^a-z0-9_-]+/i', '-', $actionKey) ?: 'action';
        $safeScope = preg_replace('/[^a-z0-9_-]+/i', '-', $scopeRef) ?: 'scope';
        $ext = in_array($extension, ['csv', 'xlsx', 'pdf'], true) ? $extension : 'csv';

        return sprintf(
            'reconciliation/generated/company_%d/%s__%s__%s__%s.%s',
            $companyId,
            $safeFeature,
            $safeAction,
            $safeScope,
            (string) now()->format('YmdHisv'),
            $ext,
        );
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
        if ($this->shouldUseStructuredPayrollExport($featureKey, $actionKey)) {
            return $this->buildStructuredPayrollExportCsv(
                $companyId,
                $featureKey,
                $actionKey,
                $scopeRef,
                $filterPayload,
                $datasetChecksum,
            );
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
     * Build rows (headers + data) for xlsx/tabular export, equivalent to buildGeneratedReconciliationCsv.
     *
     * @param  array<string, mixed>  $filterPayload
     * @return array{headers: list<string>, data: list<list<scalar|null>>}
     */
    private function buildGeneratedReconciliationRows(
        ?int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef,
        array $filterPayload,
        string $datasetChecksum,
    ): array {
        if ($this->shouldUseStructuredPayrollExport($featureKey, $actionKey)) {
            return $this->buildStructuredPayrollExportRows(
                $companyId,
                $featureKey,
                $actionKey,
                $scopeRef,
                $filterPayload,
                $datasetChecksum,
            );
        }

        $headers = ['Field', 'Value'];
        $data = [
            ['feature_key', $featureKey],
            ['action_key', $actionKey],
            ['scope_ref', $scopeRef],
            ['dataset_checksum', $datasetChecksum],
            ['generated_at', now()->toIso8601String()],
            [],
            ['filter_key', 'filter_value'],
        ];

        foreach ($filterPayload as $key => $value) {
            $formattedValue = is_scalar($value)
                ? (string) $value
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $data[] = [(string) $key, $formattedValue];
        }

        return ['headers' => $headers, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $filterPayload
     */
    private function buildStructuredPayrollExportCsv(
        ?int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef,
        array $filterPayload,
        string $datasetChecksum,
    ): string {
        $rows = $this->buildStructuredPayrollExportRows($companyId, $featureKey, $actionKey, $scopeRef, $filterPayload, $datasetChecksum);
        $stream = fopen('php://temp', 'w+');
        if (! $stream) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $rows['headers']);
        foreach ($rows['data'] as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $filterPayload
     * @return array{headers: list<string>, data: list<list<scalar|null>>}
     */
    private function buildStructuredPayrollExportRows(
        ?int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef,
        array $filterPayload,
        string $datasetChecksum,
    ): array {
        if ($featureKey === ExportReconciliationEvidence::FEATURE_PAYROLL_RUN && $actionKey === ExportReconciliationEvidence::ACTION_DISBURSE) {
            return $this->buildPayrollRunReconciliationRows($companyId, (int) $scopeRef, $filterPayload, $datasetChecksum);
        }

        if ($featureKey === ExportReconciliationEvidence::FEATURE_THR_BATCH) {
            return $this->buildThrBatchPaymentExportRows($companyId, (int) $scopeRef, $filterPayload, $datasetChecksum);
        }

        if ($featureKey === ExportReconciliationEvidence::FEATURE_PKWT_COMPENSATION && $actionKey === ExportReconciliationEvidence::ACTION_POST_PAYROLL) {
            return $this->buildPkwtCompensationPaymentExportRows($companyId, $scopeRef, $filterPayload, $datasetChecksum);
        }

        return ['headers' => $this->structuredPayrollExportHeaders(), 'data' => []];
    }

    private function shouldUseStructuredPayrollExport(string $featureKey, string $actionKey): bool
    {
        if ($featureKey === ExportReconciliationEvidence::FEATURE_PAYROLL_RUN && $actionKey === ExportReconciliationEvidence::ACTION_DISBURSE) {
            return true;
        }

        if ($featureKey === ExportReconciliationEvidence::FEATURE_THR_BATCH
            && in_array($actionKey, [ExportReconciliationEvidence::ACTION_DISBURSE, ExportReconciliationEvidence::ACTION_POST_PAYROLL], true)) {
            return true;
        }

        return $featureKey === ExportReconciliationEvidence::FEATURE_PKWT_COMPENSATION
            && $actionKey === ExportReconciliationEvidence::ACTION_POST_PAYROLL;
    }

    /**
     * @return list<string>
     */
    private function structuredPayrollExportHeaders(): array
    {
        return [
            'payroll_type',
            'reference_period',
            'reference_id',
            'employee_id',
            'employee_name',
            'bank_name',
            'account_number',
            'account_holder_name',
            'bank_branch',
            'gross_total',
            'overtime_total',
            'deductions_total',
            'transfer_amount',
            'bank_data_status',
            'dataset_checksum',
        ];
    }

    /**
     * Build rows for payroll_run reconciliation (xlsx equivalent of buildPayrollRunReconciliationCsv).
     *
     * @param  array<string, mixed>  $filterPayload
     * @return array{headers: list<string>, data: list<list<scalar|null>>}
     */
    private function buildPayrollRunReconciliationRows(?int $companyId, int $runId, array $filterPayload, string $datasetChecksum): array
    {
        return $this->buildPayrollRunPaymentExportRows($companyId, $runId, $filterPayload, $datasetChecksum);
    }

    /**
     * Build CSV with actual payroll line data for reconciliation.
     * Extracts user IDs from filterPayload and fetches corresponding payroll lines.
     *
     * @param  array<string, mixed>  $filterPayload
     */
    private function buildPayrollRunReconciliationCsv(?int $companyId, int $runId, array $filterPayload, string $datasetChecksum): string {
        $rows = $this->buildPayrollRunPaymentExportRows($companyId, $runId, $filterPayload, $datasetChecksum);
        $stream = fopen('php://temp', 'w+');
        if (! $stream) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $rows['headers']);
        foreach ($rows['data'] as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $filterPayload
     * @return array{headers: list<string>, data: list<list<scalar|null>>}
     */
    private function buildPayrollRunPaymentExportRows(?int $companyId, int $runId, array $filterPayload, string $datasetChecksum): array
    {
        $run = HcmPayrollRun::query()
            ->with(['period:id,period_year,period_month'])
            ->findOrFail($runId);

        $query = HcmPayrollLine::query()
            ->with([
                'user:id,name',
                'user.employeeProfile:id,user_id,bank_name,bank_account_no,bank_branch',
                'user.employeeProfile.bankAccounts:id,employee_id,bank_name,account_number,account_holder_name,bank_branch,is_primary',
            ])
            ->where('hcm_payroll_run_id', $runId)
            ->orderBy('user_id')
            ->orderBy('sort_order');

        $userIds = $this->extractPayrollExportUserIds($filterPayload);
        if ($userIds !== []) {
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

        $periodLabel = $run->period
            ? sprintf('%04d-%02d', (int) $run->period->period_year, (int) $run->period->period_month)
            : '';

        $headers = $this->structuredPayrollExportHeaders();

        $data = [];
        foreach ($payrollLines->groupBy('user_id') as $userId => $userLines) {
            $gross = 0.0;
            $overtime = 0.0;
            $deductions = 0.0;

            foreach ($userLines as $line) {
                $componentId = $line->hcm_salary_component_id !== null ? (int) $line->hcm_salary_component_id : null;
                $affectsNetPay = $componentId !== null
                    ? ($componentAffectsNetPay[$componentId] ?? true)
                    : true;
                if (! $affectsNetPay) {
                    continue;
                }

                if ((string) $line->kind === 'addition') {
                    $gross += (float) $line->amount;
                    if ((string) $line->component_code === HcmSalaryComponent::CODE_OVERTIME_PAY) {
                        $overtime += (float) $line->amount;
                    }
                } elseif ((string) $line->kind === 'deduction') {
                    $deductions += (float) $line->amount;
                }
            }

            $firstLine = $userLines->first();
            if ($firstLine === null) {
                continue;
            }

            $bank = $this->resolvePayrollLineBankSnapshot($firstLine);
            $net = round($gross - $deductions, 2);

            $data[] = [
                (string) ($run->purpose ?: HcmPayrollRun::PURPOSE_MONTHLY),
                $periodLabel,
                'run:'.$runId,
                (string) $userId,
                $this->resolvePayrollLineUserName($firstLine),
                $bank['bankName'],
                $bank['accountNumber'],
                $bank['accountHolderName'],
                $bank['bankBranch'],
                (string) round($gross, 2),
                (string) round($overtime, 2),
                (string) round($deductions, 2),
                (string) $net,
                $bank['status'],
                $datasetChecksum,
            ];
        }

        return ['headers' => $headers, 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $filterPayload
     * @return array{headers: list<string>, data: list<list<scalar|null>>}
     */
    private function buildThrBatchPaymentExportRows(?int $companyId, int $batchId, array $filterPayload, string $datasetChecksum): array
    {
        $batchQuery = HcmThrBatch::query()->with(['payrollPeriod:id,period_year,period_month']);
        if ($companyId !== null) {
            $batchQuery->where(function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }
        $batch = $batchQuery->findOrFail($batchId);

        $lineQuery = HcmThrBatchLine::query()
            ->with([
                'user:id,name',
                'user.employeeProfile:id,user_id,bank_name,bank_account_no,bank_branch',
                'user.employeeProfile.bankAccounts:id,employee_id,bank_name,account_number,account_holder_name,bank_branch,is_primary',
            ])
            ->where('hcm_thr_batch_id', $batchId)
            ->orderBy('user_id')
            ->orderBy('id');

        $lineIds = $this->extractIdList($filterPayload['lineIds'] ?? null);
        if ($lineIds !== []) {
            $lineQuery->whereIn('id', $lineIds);
        }

        $lines = $lineQuery->get();
        $referencePeriod = $batch->payrollPeriod
            ? sprintf('%04d-%02d', (int) $batch->payrollPeriod->period_year, (int) $batch->payrollPeriod->period_month)
            : (string) $batch->calendar_year;

        $data = [];
        foreach ($lines as $line) {
            $bank = $this->resolveUserBankSnapshot($line->user_id, $line->user?->name ?? $line->full_name, $line->user?->employeeProfile);
            $gross = round((float) $line->thr_gross, 2);

            $data[] = [
                HcmPayrollRun::PURPOSE_THR,
                $referencePeriod,
                'thr_batch:'.$batchId,
                (string) $line->user_id,
                (string) ($line->full_name ?: ($line->user?->name ?? '')),
                $bank['bankName'],
                $bank['accountNumber'],
                $bank['accountHolderName'],
                $bank['bankBranch'],
                (string) $gross,
                '0',
                '0',
                (string) $gross,
                $bank['status'],
                $datasetChecksum,
            ];
        }

        return ['headers' => $this->structuredPayrollExportHeaders(), 'data' => $data];
    }

    /**
     * @param  array<string, mixed>  $filterPayload
     * @return array{headers: list<string>, data: list<list<scalar|null>>}
     */
    private function buildPkwtCompensationPaymentExportRows(?int $companyId, string $scopeRef, array $filterPayload, string $datasetChecksum): array
    {
        [$periodYear, $periodMonth] = array_pad(array_map('intval', explode('-', $scopeRef, 3)), 2, 0);
        $preview = app(PkwtCompensationService::class)->previewForMonth($periodYear, $periodMonth, $companyId);
        $lines = collect($preview['lines'] ?? [])
            ->filter(fn (array $row): bool => (bool) ($row['eligible'] ?? false) && (float) ($row['compensationAmount'] ?? 0) > 0)
            ->values();

        $userIds = $this->extractIdList($filterPayload['lineIds'] ?? null);
        if ($userIds !== []) {
            $lines = $lines->filter(fn (array $row): bool => in_array((int) ($row['userId'] ?? 0), $userIds, true))->values();
        }

        $profiles = EmployeeProfile::query()
            ->with(['bankAccounts:id,employee_id,bank_name,account_number,account_holder_name,bank_branch,is_primary'])
            ->whereIn('user_id', $lines->pluck('userId')->filter()->all())
            ->get()
            ->keyBy('user_id');

        $referencePeriod = sprintf('%04d-%02d', $periodYear, $periodMonth);
        $data = [];
        foreach ($lines as $line) {
            $userId = (int) ($line['userId'] ?? 0);
            $profile = $profiles->get($userId);
            $bank = $this->resolveUserBankSnapshot($userId, (string) ($line['fullName'] ?? ''), $profile);
            $gross = round((float) ($line['compensationAmount'] ?? 0), 2);

            $data[] = [
                HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
                $referencePeriod,
                'period:'.$referencePeriod,
                (string) $userId,
                (string) ($line['fullName'] ?? ''),
                $bank['bankName'],
                $bank['accountNumber'],
                $bank['accountHolderName'],
                $bank['bankBranch'],
                (string) $gross,
                '0',
                '0',
                (string) $gross,
                $bank['status'],
                $datasetChecksum,
            ];
        }

        return ['headers' => $this->structuredPayrollExportHeaders(), 'data' => $data];
    }

    /**
     * @param mixed $rawIds
     * @return list<int>
     */
    private function extractIdList(mixed $rawIds): array
    {
        if (! is_array($rawIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static fn ($value): int => (int) $value, $rawIds), static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return array{bankName:string, accountNumber:string, accountHolderName:string, bankBranch:string, status:string}
     */
    private function resolveUserBankSnapshot(int $userId, ?string $fallbackName = null, ?EmployeeProfile $profile = null): array
    {
        $profile ??= EmployeeProfile::query()
            ->with(['bankAccounts:id,employee_id,bank_name,account_number,account_holder_name,bank_branch,is_primary'])
            ->where('user_id', $userId)
            ->first();

        $bankAccount = $profile?->relationLoaded('bankAccounts')
            ? $profile->bankAccounts->sortByDesc('is_primary')->sortByDesc('id')->first()
            : null;

        $bankName = trim((string) ($bankAccount?->bank_name ?? $profile?->getRawOriginal('bank_name') ?? ''));
        $accountNumber = trim((string) ($bankAccount?->account_number ?? $profile?->bank_account_no ?? ''));
        $accountHolderName = trim((string) ($bankAccount?->account_holder_name ?? $fallbackName ?? ''));
        $bankBranch = trim((string) ($bankAccount?->bank_branch ?? $profile?->bank_branch ?? ''));

        return [
            'bankName' => $bankName,
            'accountNumber' => $accountNumber,
            'accountHolderName' => $accountHolderName,
            'bankBranch' => $bankBranch,
            'status' => ($bankName !== '' && $accountNumber !== '') ? 'ready' : 'missing_bank_data',
        ];
    }

    /**
     * @param array<string, mixed> $filterPayload
     * @return list<int>
     */
    private function extractPayrollExportUserIds(array $filterPayload): array
    {
        $userIds = [];
        if (isset($filterPayload['periods']) && is_array($filterPayload['periods'])) {
            foreach ($filterPayload['periods'] as $period) {
                if (is_array($period) && isset($period['userId'])) {
                    $userIds[] = (int) $period['userId'];
                }
            }
        }

        return array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return array{bankName:string, accountNumber:string, accountHolderName:string, bankBranch:string, status:string}
     */
    private function resolvePayrollLineBankSnapshot(HcmPayrollLine $line): array
    {
        $profile = $line->user?->employeeProfile;
        $bankAccount = $profile?->relationLoaded('bankAccounts')
            ? $profile->bankAccounts->sortByDesc('is_primary')->sortByDesc('id')->first()
            : null;

        $bankName = trim((string) ($bankAccount?->bank_name ?? $profile?->getRawOriginal('bank_name') ?? ''));
        $accountNumber = trim((string) ($bankAccount?->account_number ?? $profile?->bank_account_no ?? ''));
        $accountHolderName = trim((string) ($bankAccount?->account_holder_name ?? $line->user?->name ?? ''));
        $bankBranch = trim((string) ($bankAccount?->bank_branch ?? $profile?->bank_branch ?? ''));

        return [
            'bankName' => $bankName,
            'accountNumber' => $accountNumber,
            'accountHolderName' => $accountHolderName,
            'bankBranch' => $bankBranch,
            'status' => ($bankName !== '' && $accountNumber !== '') ? 'ready' : 'missing_bank_data',
        ];
    }

    private function resolvePayrollLineUserName(HcmPayrollLine $line): string
    {
        $meta = is_array($line->meta) ? $line->meta : [];

        return (string) ($line->user?->name ?? $meta['userName'] ?? $meta['user_name'] ?? '');
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
