<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PurchaseTransaction;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;
use App\Services\Reconciliation\ReconciliationGateService;
use Illuminate\Support\Facades\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * GET /v1/saas/invoices
     * List invoices with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['company', 'purchaseTransaction']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->has('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }
        if ($request->has('is_paid')) {
            $query->where('is_paid', (bool) $request->get('is_paid'));
        }
        if ($request->has('from_date')) {
            $query->whereDate('issue_date', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('issue_date', '<=', $request->get('to_date'));
        }

        $invoices = $query->latest('issue_date')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'pagination' => [
                'total' => $invoices->total(),
                'per_page' => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/invoices/{id}
     * Get invoice details
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load('company', 'purchaseTransaction', 'payments');

        return response()->json([
            'success' => true,
            'data' => $this->formatInvoice($invoice),
        ]);
    }

    /**
     * POST /v1/saas/invoices
     * Create invoice (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'purchase_transaction_id' => 'nullable|integer|exists:purchase_transactions,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after:issue_date',
            'amount_due' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $invoice = Invoice::create($validated);
        $invoice->load('company', 'purchaseTransaction');

        return response()->json([
            'success' => true,
            'data' => $this->formatInvoice($invoice),
        ], 201);
    }

    /**
     * PUT /v1/saas/invoices/{id}
     * Update invoice (admin only)
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'due_date' => 'sometimes|date|after:'.$invoice->issue_date->toDateString(),
            'amount_due' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $invoice->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatInvoice($invoice),
        ]);
    }

    /**
     * PUT /v1/saas/invoices/{id}/send
     * Mark invoice as sent
     */
    public function markAsSent(Request $request, Invoice $invoice): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $invoice->markAsSent();

        return response()->json([
            'success' => true,
            'message' => 'Invoice marked as sent',
            'data' => $this->formatInvoice($invoice),
        ]);
    }

    /**
     * PUT /v1/saas/invoices/{id}/mark-paid
     * Mark invoice as paid (admin only)
     */
    public function markAsPaid(Request $request, Invoice $invoice): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($error = $this->guardInvoiceReconciliation($request, $invoice)) {
            return $error;
        }

        $invoice->markAsPaid();

        return response()->json([
            'success' => true,
            'message' => 'Invoice marked as paid',
            'data' => $this->formatInvoice($invoice),
        ]);
    }

    /**
     * DELETE /v1/saas/invoices/{id}
     * Delete invoice (admin only)
     */
    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $invoiceNumber = $invoice->invoice_number;
        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => "Invoice {$invoiceNumber} deleted successfully",
        ]);
    }

    /**
     * GET /v1/saas/invoices/{id}/pdf
     * Download invoice PDF
     */
    public function downloadPdf(Request $request, Invoice $invoice): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $invoiceService = new InvoiceService();
        $relativePath = $invoice->pdf_path;

        if (!$relativePath || !File::exists(storage_path('app/private/'.$relativePath))) {
            $relativePath = $invoiceService->generatePdf($invoice);
        }

        if (!$relativePath) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PDF_GENERATION_FAILED', 'message' => 'Failed to generate invoice PDF.'],
            ], 422);
        }

        $fullPath = storage_path('app/private/'.$relativePath);
        if (!File::exists($fullPath)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FILE_NOT_FOUND', 'message' => 'Invoice PDF file is missing.'],
            ], 404);
        }

        return response()->download($fullPath, 'invoice-'.$invoice->invoice_number.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * POST /v1/saas/invoices/{id}/send-email
     * Send invoice via email
     */
    public function sendEmail(Request $request, Invoice $invoice): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $invoiceService = new InvoiceService();
        $notificationService = new NotificationService();

        $email = $request->get('email') ?? $invoice->company->email;

        if ($invoiceService->sendInvoice($invoice, $email)) {
            $notificationService->notifyInvoiceSent($invoice);

            return response()->json([
                'success' => true,
                'message' => "Invoice sent to {$email}",
                'data' => $this->formatInvoice($invoice),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Failed to send invoice',
        ], 422);
    }

    /**
     * Format invoice for response
     */
    private function formatInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'companyId' => $invoice->company_id,
            'companyName' => $invoice->company?->name,
            'purchaseTransactionId' => $invoice->purchase_transaction_id,
            'issueDate' => $invoice->issue_date->toDateString(),
            'dueDate' => $invoice->due_date->toDateString(),
            'amountDue' => (float) $invoice->amount_due,
            'isPaid' => $invoice->is_paid,
            'paidDate' => $invoice->paid_date?->toDateString(),
            'pdfPath' => $invoice->pdf_path,
            'status' => $invoice->status,
            'isOverdue' => $invoice->isOverdue(),
            'isDueSoon' => $invoice->isDueSoon(),
            'notes' => $invoice->notes,
            'createdAt' => $invoice->created_at->toIso8601String(),
            'updatedAt' => $invoice->updated_at->toIso8601String(),
        ];
    }

    /**
     * Check if user is HCM admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->isHcmAdmin();
    }

    private function guardInvoiceReconciliation(Request $request, Invoice $invoice): ?JsonResponse
    {
        if (! (bool) config('hcm.export_reconciliation.enabled', true)) {
            return null;
        }

        if (! (bool) config('hcm.export_reconciliation.enforce.invoice.mark_paid', false)) {
            return null;
        }

        $reconciliation = $request->input('reconciliation', []);
        $filterPayload = is_array($reconciliation['filterPayload'] ?? null) ? $reconciliation['filterPayload'] : [];
        $datasetChecksum = isset($reconciliation['datasetChecksum']) ? (string) $reconciliation['datasetChecksum'] : null;
        $strictChecksum = (bool) ($reconciliation['strictChecksum'] ?? config('hcm.export_reconciliation.strict_checksum', false));

        try {
            app(ReconciliationGateService::class)->assertCanProceed(
                $invoice->company_id,
                'invoice',
                'mark_paid',
                (string) $invoice->id,
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
}
