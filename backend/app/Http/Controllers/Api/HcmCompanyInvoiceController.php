<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\SubscriptionActivationFromInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HcmCompanyInvoiceController
{
    use ChecksPermissions;

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly SubscriptionActivationFromInvoiceService $activationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Tenant owner is treated as tenant-admin for their company; keep same gate as checkout.
        if ($forbidden = $this->ensurePermission($request, 'subscription.view')) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'is_paid' => ['nullable', 'in:0,1'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 15);

        $q = Invoice::query()
            ->where('company_id', $companyId);

        if (!empty($validated['status'])) {
            $q->where('status', $validated['status']);
        }
        if (array_key_exists('is_paid', $validated)) {
            $q->where('is_paid', (bool) ((int) $validated['is_paid']));
        }
        if (!empty($validated['search'])) {
            $term = trim((string) $validated['search']);
            $q->where(function ($inner) use ($term): void {
                $inner->where('invoice_number', 'like', '%'.$term.'%')
                    ->orWhere('notes', 'like', '%'.$term.'%');
            });
        }

        $p = $q->latest('issue_date')->paginate($perPage);

        $items = collect($p->items())->map(fn (Invoice $inv) => $this->invoiceService->formatInvoice($inv))->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'page' => $p->currentPage(),
                'perPage' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'subscription.view')) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $invoice = Invoice::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $this->invoiceService->formatInvoice($invoice)]);
    }

    public function download(Request $request, int $id)
    {
        if ($forbidden = $this->ensurePermission($request, 'subscription.view')) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $invoice = Invoice::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->firstOrFail();

        $path = $invoice->pdf_path ?: $this->invoiceService->generatePdf($invoice);
        if (! $path) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVOICE_PDF_FAILED', 'message' => 'Failed to generate invoice PDF.'],
            ], 500);
        }

        return Storage::disk('local')->download('private/'.$path, basename($path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function mockPay(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'subscription.view')) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $invoice = Invoice::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->firstOrFail();

        if ($invoice->is_paid) {
            return response()->json(['success' => true, 'data' => $this->invoiceService->formatInvoice($invoice)]);
        }

        $invoice->update([
            'is_paid' => true,
            'paid_date' => now()->toDateString(),
            'status' => 'paid',
        ]);

        // Activate subscription if this invoice belongs to a subscription.
        if ($invoice->subscription_id) {
            $this->activationService->activateFromPaidInvoice($invoice->id);
        }

        $invoice->refresh();

        return response()->json(['success' => true, 'data' => $this->invoiceService->formatInvoice($invoice)]);
    }
}

