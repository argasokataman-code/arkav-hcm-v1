<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Services\InvoiceService;
use App\Services\NotificationDeliveryRecorder;
use App\Services\NotificationService;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;
use App\Services\Reconciliation\ReconciliationGateService;
use Illuminate\Support\Facades\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    /**
     * GET /v1/saas/invoices
     * List invoices with filters
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = (bool) $request->user()?->isGlobalHcmAdmin();
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);

        $query = Invoice::with(['company', 'purchaseTransaction']);

        // Security: If not admin, only show invoices for active company
        if (!$isAdmin && $activeCompanyId > 0) {
            $query->where('company_id', $activeCompanyId);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->has('company_id')) {
            // Allow filtering by company_id only if user is admin or it's their own company
            $filteredCompanyId = (int) $request->get('company_id');
            if (!$isAdmin && $filteredCompanyId !== $activeCompanyId) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot view invoices for other companies.'],
                ], 403);
            }
            $query->where('company_id', $filteredCompanyId);
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
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $isAdmin = (bool) $request->user()?->isGlobalHcmAdmin();
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);

        // Security: If not admin, ensure invoice belongs to their company
        if (!$isAdmin && $activeCompanyId > 0 && $invoice->company_id !== $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot view this invoice.'],
            ], 403);
        }

        $invoice->load([
            'company',
            'purchaseTransaction',
            'payments',
            'subscription.package',
            'emailLogs' => function ($query): void {
                $query->latest('created_at');
            },
            'latestEmailLog',
        ]);

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
            'company_id' => 'required|uuid|exists:companies,uuid',
            'purchase_transaction_id' => 'nullable|uuid|exists:purchase_transactions,uuid',
            'subscription_id' => ['nullable', 'uuid', Rule::exists('subscriptions', 'uuid')],
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after:issue_date',
            'amount_due' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (! empty($validated['subscription_id'])) {
            $subscriptionCompanyId = Subscription::query()
                ->where('uuid', $validated['subscription_id'])
                ->value('company_id');

            if ((string) $subscriptionCompanyId !== (string) $validated['company_id']) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'subscription_id must belong to the selected company_id.',
                    ],
                ], 422);
            }
        }

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
            'subscription_id' => ['nullable', 'uuid', Rule::exists('subscriptions', 'uuid')],
        ]);

        if (! empty($validated['subscription_id'])) {
            $subscriptionCompanyId = Subscription::query()
                ->where('uuid', $validated['subscription_id'])
                ->value('company_id');

            if ((string) $subscriptionCompanyId !== (string) $invoice->company_id) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'subscription_id must belong to the invoice company.',
                    ],
                ], 422);
            }
        }

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
        $isAdmin = (bool) $request->user()?->isGlobalHcmAdmin();
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);

        // Security: Allow download if admin or if invoice belongs to their company
        if (!$isAdmin && $activeCompanyId > 0 && $invoice->company_id !== $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot download this invoice.'],
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

        if ($invoice->subscription?->status === 'pending_payment') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PENDING_PAYMENT_REMINDER_ONLY',
                    'message' => 'Invoice send is blocked while tenant payment is pending. Use payment reminder flow instead.',
                ],
            ], 422);
        }

        $invoiceService = new InvoiceService();
        $notificationService = new NotificationService();

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ]);

        $email = $validated['email'] ?? null;

        $result = $invoiceService->sendInvoiceWithResult($invoice, $email);

        InvoiceEmailLog::query()->create([
            'invoice_id' => $invoice->id,
            'to_email' => (string) ($result['toEmail'] ?? ''),
            'event_key' => $result['ok'] ? 'billing.invoice.email_sent' : 'billing.invoice.email_failed',
            'status' => $result['ok'] ? 'sent' : 'failed',
            'provider_message_id' => null,
            'error_message' => $result['error'],
        ]);

        $eventKey = $result['ok'] ? 'billing.invoice.email_sent' : 'billing.invoice.email_failed';
        $deliveryRecorder = app(NotificationDeliveryRecorder::class);
        $context = [
            'recipient' => (string) ($result['toEmail'] ?? ''),
            'companyUuid' => (string) ($invoice->company?->uuid ?? ''),
            'lastError' => $result['error'] ?? null,
            'metadata' => [
                'source' => 'invoice.send-email.endpoint',
                'invoiceUuid' => (string) ($invoice->uuid ?? ''),
                'invoiceNumber' => (string) ($invoice->invoice_number ?? ''),
            ],
        ];

        if ($result['ok']) {
            $deliveryRecorder->recordSent($eventKey, 'mail', $context);
        } else {
            $deliveryRecorder->recordFailed($eventKey, 'mail', $context);
        }

        if ($result['ok']) {
            $notificationService->notifyInvoiceSent($invoice);

            return response()->json([
                'success' => true,
                'message' => "Invoice sent to {$result['toEmail']}",
                'data' => $this->formatInvoice($invoice),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $result['error'] ?: 'Failed to send invoice',
            ],
        ], 422);
    }

    /**
     * Format invoice for response
     */
    private function formatInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'uuid' => $invoice->uuid,
            'invoiceNumber' => $invoice->invoice_number,
            'companyId' => $invoice->company_id,
            'companyName' => $invoice->company?->name,
            'company' => $invoice->company ? [
                'id' => $invoice->company->id,
                'uuid' => $invoice->company->uuid,
                'code' => $invoice->company->code,
                'name' => $invoice->company->name,
            ] : null,
            'purchaseTransactionId' => $invoice->purchase_transaction_id,
            'subscriptionId' => $invoice->subscription_id,
            'subscription' => $invoice->subscription ? [
                'id' => $invoice->subscription->id,
                'uuid' => $invoice->subscription->uuid,
                'status' => $invoice->subscription->status,
                'billingCycle' => $invoice->subscription->billing_cycle,
                'startsAt' => $invoice->subscription->starts_at,
                'endsAt' => $invoice->subscription->ends_at,
                'trialEndsAt' => $invoice->subscription->trial_ends_at,
                'planCode' => $invoice->subscription->plan_code,
                'packageId' => $invoice->subscription->package?->uuid,
                'packageName' => $invoice->subscription->package?->name,
                'amount' => $invoice->subscription->amount,
            ] : null,
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
            'latestEmail' => $invoice->latestEmailLog ? [
                'id' => $invoice->latestEmailLog->id,
                'uuid' => $invoice->latestEmailLog->uuid,
                'toEmail' => $invoice->latestEmailLog->to_email,
                'status' => $invoice->latestEmailLog->status,
                'providerMessageId' => $invoice->latestEmailLog->provider_message_id,
                'errorMessage' => $invoice->latestEmailLog->error_message,
                'createdAt' => $invoice->latestEmailLog->created_at?->toIso8601String(),
            ] : null,
            'emailLogs' => $invoice->emailLogs->map(function (InvoiceEmailLog $emailLog): array {
                return [
                    'id' => $emailLog->id,
                    'uuid' => $emailLog->uuid,
                    'toEmail' => $emailLog->to_email,
                    'status' => $emailLog->status,
                    'providerMessageId' => $emailLog->provider_message_id,
                    'errorMessage' => $emailLog->error_message,
                    'createdAt' => $emailLog->created_at?->toIso8601String(),
                    'updatedAt' => $emailLog->updated_at?->toIso8601String(),
                ];
            })->values()->all(),
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
        return $user && $user->isGlobalHcmAdmin();
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
