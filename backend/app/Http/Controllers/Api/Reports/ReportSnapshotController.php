<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Jobs\Reporting\GenerateReportSnapshot;
use App\Models\ReportSnapshot;
use App\Services\Reporting\ReportSnapshotService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportSnapshotController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        protected ReportSnapshotService $snapshotService,
    ) {}

    /**
     * Create and queue a new snapshot generation.
     * POST /v1/hcm/reports/snapshots
     */
    public function generate(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensurePermission($request, 'reports.view')) {
            return $response;
        }

        $validated = $request->validate([
            'reportType' => ['required', 'string', Rule::in(['attendance', 'payroll', 'employee', 'leave', 'finance'])],
            'periodStart' => ['required', 'date_format:Y-m-d'],
            'periodEnd' => ['required', 'date_format:Y-m-d', 'after_or_equal:periodStart'],
            'filters' => ['nullable', 'array'],
            'async' => ['nullable', 'boolean'],
        ]);

        $periodStart = Carbon::createFromFormat('Y-m-d', $validated['periodStart']);
        $periodEnd = Carbon::createFromFormat('Y-m-d', $validated['periodEnd']);
        $filters = $validated['filters'] ?? [];
        $async = $validated['async'] ?? false;

        $userId = $request->user()->id;

        // Queue the job or execute immediately
        if ($async) {
            GenerateReportSnapshot::dispatch(
                $validated['reportType'],
                $periodStart,
                $periodEnd,
                $filters,
                $userId,
                $companyId,
            );
        } else {
            // Execute synchronously for immediate results
            $this->snapshotService->generateSnapshot(
                $validated['reportType'],
                $periodStart,
                $periodEnd,
                $filters,
                $userId,
                $companyId,
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Snapshot generation '.($async ? 'queued' : 'completed'),
                'async' => $async,
            ],
        ], 202);
    }

    /**
     * List snapshots with filters and pagination.
     * GET /v1/hcm/reports/snapshots
     */
    public function list(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensurePermission($request, 'reports.view')) {
            return $response;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'reportType' => ['nullable', 'string', Rule::in(['attendance', 'payroll', 'employee', 'leave', 'finance'])],
            'status' => ['nullable', 'string', Rule::in(['pending', 'processing', 'completed', 'failed'])],
            'sortBy' => ['nullable', 'string', Rule::in(['generated_at', 'report_type', 'status'])],
            'sortDir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $sortBy = $validated['sortBy'] ?? 'generated_at';
        $sortDir = $validated['sortDir'] ?? 'desc';

        $query = ReportSnapshot::where('company_id', $companyId);

        if ($validated['reportType'] ?? null) {
            $query->where('report_type', $validated['reportType']);
        }

        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        }

        $paginator = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->appends($request->query());

        $snapshots = collect($paginator->items())->map(function (ReportSnapshot $snapshot) {
            return [
                'id' => $snapshot->id,
                'reportType' => $snapshot->report_type,
                'periodStart' => $snapshot->period_start?->toDateString(),
                'periodEnd' => $snapshot->period_end?->toDateString(),
                'status' => $snapshot->status,
                'generatedAt' => $snapshot->generated_at?->toIso8601String(),
                'generatedBy' => $snapshot->generatedBy?->name,
                'rowCount' => $snapshot->meta['row_count'] ?? 0,
                'meta' => $snapshot->meta,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $snapshots,
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

    /**
     * Get a single snapshot with its data blocks and filters.
     * GET /v1/hcm/reports/snapshots/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensurePermission($request, 'reports.view')) {
            return $response;
        }

        $snapshotQuery = ReportSnapshot::query()
            ->where('company_id', $companyId)
            ->with(['dataBlocks', 'filters', 'exports', 'generatedBy']);

        $this->applyIdentifierScope($snapshotQuery, $id);
        $snapshot = $snapshotQuery->first();

        if (! $snapshot) {
            return $this->errorResponse('SNAPSHOT_NOT_FOUND', 'Snapshot not found.', 404);
        }

        $dataByModule = collect($snapshot->dataBlocks)->groupBy('module')->map(function ($blocks) {
            return $blocks->map(function ($block) {
                return [
                    'key' => $block->data_key,
                    'value' => $block->data_value,
                ];
            })->keyBy('key')->map(fn ($item) => $item['value']);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $snapshot->id,
                'reportType' => $snapshot->report_type,
                'periodStart' => $snapshot->period_start?->toDateString(),
                'periodEnd' => $snapshot->period_end?->toDateString(),
                'status' => $snapshot->status,
                'generatedAt' => $snapshot->generated_at?->toIso8601String(),
                'generatedBy' => $snapshot->generatedBy?->name,
                'rowCount' => $snapshot->meta['row_count'] ?? 0,
                'meta' => $snapshot->meta,
                'dataByModule' => $dataByModule,
                'filters' => collect($snapshot->filters)->map(function ($filter) {
                    return [
                        'key' => $filter->filter_key,
                        'value' => $filter->filter_value,
                    ];
                }),
                'exports' => collect($snapshot->exports)->map(function ($export) {
                    return [
                        'id' => $export->id,
                        'fileType' => $export->file_type,
                        'fileUrl' => $export->file_url,
                        'generatedAt' => $export->generated_at?->toIso8601String(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Generate an export file from a snapshot.
     * POST /v1/hcm/reports/snapshots/{id}/export
     */
    public function export(Request $request, string $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if ($response = $this->ensurePermission($request, 'reports.view')) {
            return $response;
        }

        $validated = $request->validate([
            'fileType' => ['required', 'string', Rule::in(['csv', 'excel', 'pdf'])],
        ]);

        $snapshotQuery = ReportSnapshot::query()
            ->where('company_id', $companyId)
            ->with(['dataBlocks', 'generatedBy']);

        $this->applyIdentifierScope($snapshotQuery, $id);
        $snapshot = $snapshotQuery->first();

        if (! $snapshot) {
            return $this->errorResponse('SNAPSHOT_NOT_FOUND', 'Snapshot not found.', 404);
        }

        if ($snapshot->status !== 'completed') {
            return $this->errorResponse('SNAPSHOT_NOT_READY', 'Snapshot must be in completed status to export.', 422);
        }

        $fileType = $validated['fileType'];
        $fileName = $this->buildExportFilename($snapshot, $fileType);
        $storagePath = sprintf(
            'report-exports/company_%d/snapshot_%d/%s',
            (int) $snapshot->company_id,
            (int) $snapshot->id,
            $fileName
        );

        try {
            $rows = $this->flattenSnapshotRows($snapshot);
            $content = $this->buildExportContent($fileType, $snapshot, $rows);
            Storage::disk('public')->put($storagePath, $content);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('EXPORT_GENERATION_FAILED', 'Failed to generate export file.', 500);
        }

        $fileUrl = Storage::url($storagePath);

        $export = $snapshot->exports()->create([
            'file_type' => $fileType,
            'file_url' => $fileUrl,
            'generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $export->id,
                'fileType' => $export->file_type,
                'fileUrl' => $export->file_url,
                'fileName' => $fileName,
                'generatedAt' => $export->generated_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function buildExportFilename(ReportSnapshot $snapshot, string $fileType): string
    {
        $extension = $fileType === 'excel' ? 'xlsx' : $fileType;

        return sprintf(
            '%s_%s_to_%s_%s.%s',
            $snapshot->report_type,
            $snapshot->period_start?->format('Y-m-d') ?? 'all',
            $snapshot->period_end?->format('Y-m-d') ?? 'all',
            now()->format('Ymd_His'),
            $extension
        );
    }

    private function flattenSnapshotRows(ReportSnapshot $snapshot): array
    {
        return $snapshot->dataBlocks
            ->sortBy(['module', 'data_key'])
            ->map(function ($block) {
                $value = $block->data_value;

                return [
                    'module' => (string) $block->module,
                    'dataKey' => (string) $block->data_key,
                    'payload' => is_array($value)
                        ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string) $value,
                ];
            })
            ->values()
            ->all();
    }

    private function buildExportContent(string $fileType, ReportSnapshot $snapshot, array $rows): string
    {
        return match ($fileType) {
            'csv' => $this->buildCsvContent($rows),
            'excel' => $this->buildExcelContent($rows),
            'pdf' => $this->buildPdfContent($snapshot, $rows),
            default => throw new \InvalidArgumentException('Unsupported file type.'),
        };
    }

    private function buildCsvContent(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Module', 'Data Key', 'Payload']);

        foreach ($rows as $row) {
            fputcsv($stream, [$row['module'], $row['dataKey'], $row['payload']]);
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    private function buildExcelContent(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Snapshot');

        $sheet->fromArray(['Module', 'Data Key', 'Payload'], null, 'A1');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue('A'.$rowIndex, (string) ($row['module'] ?? ''));
            $sheet->setCellValue('B'.$rowIndex, (string) ($row['dataKey'] ?? ''));
            $sheet->setCellValue('C'.$rowIndex, (string) ($row['payload'] ?? ''));
            $rowIndex++;
        }

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(32);
        $sheet->getColumnDimension('C')->setWidth(80);

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $temp = fopen('php://temp', 'w+b');
        if (! $temp) {
            throw new \RuntimeException('Failed to allocate temp stream for excel export.');
        }

        $meta = stream_get_meta_data($temp);
        $uri = (string) ($meta['uri'] ?? '');
        if ($uri === '') {
            fclose($temp);
            throw new \RuntimeException('Temp stream URI is unavailable for excel export.');
        }

        $writer->save($uri);
        rewind($temp);
        $content = (string) stream_get_contents($temp);
        fclose($temp);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $content;
    }

    private function buildPdfContent(ReportSnapshot $snapshot, array $rows): string
    {
        $safeReportType = htmlspecialchars((string) $snapshot->report_type, ENT_QUOTES, 'UTF-8');
        $safePeriodStart = htmlspecialchars($snapshot->period_start?->toDateString() ?? '-', ENT_QUOTES, 'UTF-8');
        $safePeriodEnd = htmlspecialchars($snapshot->period_end?->toDateString() ?? '-', ENT_QUOTES, 'UTF-8');

        $bodyRows = '';
        foreach ($rows as $row) {
            $bodyRows .= '<tr>'
                .'<td>'.htmlspecialchars((string) $row['module'], ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.htmlspecialchars((string) $row['dataKey'], ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.htmlspecialchars((string) $row['payload'], ENT_QUOTES, 'UTF-8').'</td>'
                .'</tr>';
        }

        if ($bodyRows === '') {
            $bodyRows = '<tr><td colspan="3">No data blocks found.</td></tr>';
        }

        $html = '<html><head><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#111827;}'
            .'h1{font-size:16px;margin:0 0 8px;}'
            .'p{margin:0 0 12px;}'
            .'table{border-collapse:collapse;width:100%;table-layout:fixed;}'
            .'th,td{border:1px solid #d1d5db;padding:6px;vertical-align:top;word-break:break-word;}'
            .'th{background:#f3f4f6;text-align:left;}'
            .'</style></head><body>'
            .'<h1>Report Snapshot Export</h1>'
            .'<p>Type: '.$safeReportType.' | Period: '.$safePeriodStart.' to '.$safePeriodEnd.'</p>'
            .'<table><thead><tr><th style="width:20%;">Module</th><th style="width:25%;">Data Key</th><th style="width:55%;">Payload</th></tr></thead>'
            .'<tbody>'.$bodyRows.'</tbody></table>'
            .'</body></html>';

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
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
        if (Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }
}
