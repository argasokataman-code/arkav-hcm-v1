<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\HcmPayrollRun;
use App\Models\HcmThrBatchLine;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manages per-company invoice configuration stored in company_settings.
 *
 * RBAC:
 *  - Global HCM admin: full access
 *  - Tenant HCM admin (owner/admin role): read + write via settings.manage
 *  - Regular employees: 403 on both GET and PUT
 */
class HcmInvoiceSettingsController extends Controller
{
    use ChecksPermissions;

    private const DOCUMENT_STATUS_KEY = 'invoice_document_status_map';

    /** Keys stored in company_settings for invoice configuration */
    private const SETTING_KEYS = [
        'invoice_prefix',
        'invoice_due_days',
        'invoice_round_off',
        'invoice_round_off_enabled',
        'invoice_show_tax',
        'invoice_header_terms',
        'invoice_footer_terms',
    ];

    /** Defaults used when no value is stored yet */
    private const DEFAULTS = [
        'invoice_prefix' => 'INV-',
        'invoice_due_days' => '30',
        'invoice_round_off' => 'none',
        'invoice_round_off_enabled' => '0',
        'invoice_show_tax' => '1',
        'invoice_header_terms' => '',
        'invoice_footer_terms' => '',
    ];

    private const PAYROLL_PURPOSES = [
        HcmPayrollRun::PURPOSE_MONTHLY,
        HcmPayrollRun::PURPOSE_THR,
        HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
    ];

    /**
     * GET /v1/hcm/invoice-settings
     * Returns current invoice settings for the active company.
     */
    public function show(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->apiError('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $stored = CompanySetting::query()
            ->where('company_id', $companyId)
            ->whereIn('key', self::SETTING_KEYS)
            ->pluck('value', 'key');

        $data = [];
        foreach (self::SETTING_KEYS as $key) {
            $data[$key] = $stored->get($key, self::DEFAULTS[$key] ?? null);
        }

        $statusMap = $this->documentStatusMap($companyId);
        $documents = $this->invoiceDocuments($companyId, $statusMap);
        $data['invoice_document_status_map'] = $statusMap;
        $data['invoice_documents'] = $documents;

        return $this->apiSuccess($data);
    }

    /**
     * PUT /v1/hcm/invoice-settings
     * Saves invoice settings for the active company.
     */
    public function update(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'settings.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->apiError('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'invoice_prefix' => 'nullable|string|max:20',
            'invoice_due_days' => 'nullable|integer|min:1|max:365',
            'invoice_round_off' => 'nullable|string|in:none,round_up,round_down',
            'invoice_round_off_enabled' => 'nullable|boolean',
            'invoice_show_tax' => 'nullable|boolean',
            'invoice_header_terms' => 'nullable|string|max:2000',
            'invoice_footer_terms' => 'nullable|string|max:2000',
            'invoice_document_status_map' => 'nullable|array',
            'invoice_document_status_map.*' => 'boolean',
        ]);

        if (array_key_exists('invoice_document_status_map', $validated)) {
            $statusMap = is_array($validated['invoice_document_status_map'])
                ? $validated['invoice_document_status_map']
                : [];
            unset($validated['invoice_document_status_map']);

            $normalized = [];
            foreach ($statusMap as $code => $active) {
                $normalized[(string) $code] = (bool) $active;
            }

            CompanySetting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => self::DOCUMENT_STATUS_KEY],
                ['value' => json_encode($normalized, JSON_UNESCAPED_SLASHES), 'type' => 'json'],
            );
        }

        $saved = [];
        foreach ($validated as $key => $value) {
            if (! in_array($key, self::SETTING_KEYS, true)) {
                continue;
            }
            // Cast booleans to string "1"/"0" for storage
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $stringValue = $value === null ? '' : (string) $value;

            CompanySetting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => $stringValue, 'type' => 'string'],
            );

            $saved[$key] = $stringValue;
        }

        // Return the full current state (merge saved over existing defaults)
        $stored = CompanySetting::query()
            ->where('company_id', $companyId)
            ->whereIn('key', self::SETTING_KEYS)
            ->pluck('value', 'key');

        $data = [];
        foreach (self::SETTING_KEYS as $key) {
            $data[$key] = $stored->get($key, self::DEFAULTS[$key] ?? null);
        }

        $statusMap = $this->documentStatusMap($companyId);
        $documents = $this->invoiceDocuments($companyId, $statusMap);
        $data['invoice_document_status_map'] = $statusMap;
        $data['invoice_documents'] = $documents;

        return $this->apiSuccess($data, 'Invoice settings saved.');
    }

    // -------------------------------------------------------------------------

    private function apiSuccess(array $data = [], ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];
        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    private function apiError(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }

    /**
     * @return array<string, bool>
     */
    private function documentStatusMap(int $companyId): array
    {
        $row = CompanySetting::query()
            ->where('company_id', $companyId)
            ->where('key', self::DOCUMENT_STATUS_KEY)
            ->value('value');

        if (! is_string($row) || $row === '') {
            return [];
        }

        $decoded = json_decode($row, true);
        if (! is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $code => $active) {
            $map[(string) $code] = (bool) $active;
        }

        return $map;
    }

    /**
     * @param  array<string, bool>  $statusMap
     * @return array<int, array<string, mixed>>
     */
    private function invoiceDocuments(int $companyId, array $statusMap): array
    {
        $documents = [];

        $billingCount = Invoice::query()
            ->where('company_id', $companyId)
            ->count();
        $latestInvoice = Invoice::query()
            ->where('company_id', $companyId)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->first(['id', 'invoice_number', 'status', 'issue_date', 'updated_at']);

        $billingCode = 'billing_invoice';
        $documents[] = [
            'code' => $billingCode,
            'name' => 'Billing Invoice',
            'group' => 'billing',
            'template' => 'pdf.invoice',
            'active' => $statusMap[$billingCode] ?? true,
            'total_generated' => (int) $billingCount,
            'latest_generated_at' => $latestInvoice?->updated_at?->toIso8601String(),
            'latest_status' => $latestInvoice?->status,
            'preview_url' => $latestInvoice ? '/v1/saas/invoices/'.$latestInvoice->id.'/pdf' : null,
            'preview_note' => $latestInvoice
                ? 'Open latest generated billing invoice PDF.'
                : 'No billing invoice generated yet for this company.',
        ];

        $purposeRows = HcmPayrollRun::query()
            ->where('company_id', $companyId)
            ->whereIn('purpose', self::PAYROLL_PURPOSES)
            ->selectRaw('purpose, COUNT(*) as total_generated, MAX(id) as latest_run_id')
            ->groupBy('purpose')
            ->get()
            ->keyBy(fn ($row) => (string) $row->purpose);

        $latestRuns = HcmPayrollRun::query()
            ->with('period:id,period_year,period_month')
            ->whereIn('id', $purposeRows->pluck('latest_run_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $latestThrSlip = HcmThrBatchLine::query()
            ->select(['id', 'slip_generated_at', 'created_at'])
            ->whereNotNull('slip_storage_path')
            ->whereHas('batch', function ($q) use ($companyId): void {
                $q->where('company_id', $companyId);
            })
            ->orderByDesc('id')
            ->first();

        foreach (self::PAYROLL_PURPOSES as $purpose) {
            $code = 'payroll_'.$purpose;
            $row = $purposeRows->get($purpose);
            $latestRun = $row ? $latestRuns->get((int) $row->latest_run_id) : null;

            $previewUrl = null;
            $previewNote = 'No generated document yet.';
            $latestGeneratedAt = $latestRun?->finalized_at?->toIso8601String()
                ?? $latestRun?->updated_at?->toIso8601String();

            if ($purpose === HcmPayrollRun::PURPOSE_THR && $latestThrSlip !== null) {
                $previewUrl = '/v1/hcm/payroll/thr-batch/lines/'.$latestThrSlip->id.'/slip';
                $previewNote = 'Open latest generated THR slip PDF.';
                $latestGeneratedAt = $latestThrSlip->slip_generated_at?->toIso8601String()
                    ?? $latestGeneratedAt
                    ?? $latestThrSlip->created_at?->toIso8601String();
            } elseif ($latestRun?->period !== null) {
                $previewUrl = '/v1/hcm/payroll/my-slip-pdf?periodYear='.$latestRun->period->period_year
                    .'&periodMonth='.$latestRun->period->period_month;
                $previewNote = 'Open payroll slip PDF for latest period.';
            }

            $documents[] = [
                'code' => $code,
                'name' => $this->purposeLabel($purpose),
                'group' => 'payroll',
                'template' => $this->purposeTemplate($purpose),
                'purpose' => $purpose,
                'active' => $statusMap[$code] ?? true,
                'total_generated' => (int) ($row?->total_generated ?? 0),
                'latest_generated_at' => $latestGeneratedAt,
                'preview_url' => $previewUrl,
                'preview_note' => $previewNote,
            ];
        }

        return $documents;
    }

    private function purposeLabel(string $purpose): string
    {
        return match ($purpose) {
            HcmPayrollRun::PURPOSE_MONTHLY => 'Payroll Payslip (Monthly)',
            HcmPayrollRun::PURPOSE_THR => 'THR Slip',
            HcmPayrollRun::PURPOSE_PKWT_COMPENSATION => 'PKWT Compensation Slip',
            default => Str::of($purpose)->replace('_', ' ')->headline()->value(),
        };
    }

    private function purposeTemplate(string $purpose): string
    {
        return match ($purpose) {
            HcmPayrollRun::PURPOSE_THR => 'pdf.thr-slip',
            HcmPayrollRun::PURPOSE_MONTHLY,
            HcmPayrollRun::PURPOSE_PKWT_COMPENSATION => 'pdf.monthly-payslip',
            default => 'pdf.monthly-payslip',
        };
    }
}
