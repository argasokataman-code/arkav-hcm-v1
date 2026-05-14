<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;
use App\Services\Reconciliation\ReconciliationGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * GET /v1/saas/payments
     * List payments with filters
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = (bool) $request->user()?->isGlobalHcmAdmin();
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);

        $query = Payment::with(['company', 'purchaseTransaction', 'invoice']);

        if (! $isAdmin) {
            if ($activeCompanyId <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
                ], 422);
            }

            $query->where('company_id', $activeCompanyId);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->has('company_id')) {
            $requestedCompanyId = (int) $request->get('company_id');
            if (! $isAdmin && $requestedCompanyId !== $activeCompanyId) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot view payments for other companies.'],
                ], 403);
            }

            $query->where('company_id', $requestedCompanyId);
        }
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->get('to_date'));
        }

        $payments = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => collect($payments->items())
                ->map(fn(Payment $payment) => $this->formatPayment($payment))
                ->values(),
            'pagination' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/payments/{id}
     * Get payment details
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        $isAdmin = (bool) $request->user()?->isGlobalHcmAdmin();
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);

        if (! $isAdmin && $activeCompanyId > 0 && (int) $payment->company_id !== $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot view this payment.'],
            ], 403);
        }

        $payment->load('company', 'purchaseTransaction', 'invoice');

        return response()->json([
            'success' => true,
            'data' => $this->formatPayment($payment),
        ]);
    }

    /**
     * POST /v1/saas/payments
     * Record payment (admin only)
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
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'subscription_id' => 'nullable|integer|exists:subscriptions,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|in:IDR,USD',
            'payment_method' => 'required|in:bank_transfer,credit_card,e_wallet,cash,check',
            'gateway' => 'nullable|string|max:50',
            'gateway_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'pending';
        $payment = Payment::create($validated);
        $payment->load('company', 'purchaseTransaction', 'invoice');

        return response()->json([
            'success' => true,
            'data' => $this->formatPayment($payment),
        ], 201);
    }

    /**
     * PUT /v1/saas/payments/{id}/verify
     * Verify/complete payment (admin only)
     */
    public function verify(Request $request, Payment $payment): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($error = $this->guardPaymentReconciliation($request, $payment)) {
            return $error;
        }

        $payment->markAsVerified();
        $payment->update(['paid_at' => now()]);

        // Mark invoice as paid if all payments are completed
        if ($payment->invoice && $payment->invoice->payments()->where('status', 'completed')->count() === $payment->invoice->payments()->count()) {
            $payment->invoice->markAsPaid();
        }

        // Send notification
        $notificationService = new NotificationService();
        $notificationService->notifyPaymentReceived($payment, $payment->invoice);

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully',
            'data' => $this->formatPayment($payment),
        ]);
    }

    /**
     * DELETE /v1/saas/payments/{id}
     * Delete payment (admin only)
     */
    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $paymentId = $payment->id;
        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => "Payment {$paymentId} deleted successfully",
        ]);
    }

    /**
     * Format payment for response
     */
    private function formatPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'companyId' => $payment->company_id,
            'companyName' => $payment->company?->name,
            'purchaseTransactionId' => $payment->purchase_transaction_id,
            'invoiceId' => $payment->invoice_id,
            'invoiceNumber' => $payment->invoice?->invoice_number,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'paymentMethod' => $payment->payment_method,
            'gateway' => $payment->gateway,
            'gatewayReference' => $payment->gateway_reference,
            'paidAt' => $payment->paid_at?->toIso8601String(),
            'verifiedAt' => $payment->verified_at?->toIso8601String(),
            'isPending' => $payment->isPending(),
            'isCompleted' => $payment->isCompleted(),
            'notes' => $payment->notes,
            'createdAt' => $payment->created_at->toIso8601String(),
            'updatedAt' => $payment->updated_at->toIso8601String(),
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

    private function guardPaymentReconciliation(Request $request, Payment $payment): ?JsonResponse
    {
        if (! (bool) config('hcm.export_reconciliation.enabled', true)) {
            return null;
        }

        if (! (bool) config('hcm.export_reconciliation.enforce.payment.verify', false)) {
            return null;
        }

        $reconciliation = $request->input('reconciliation', []);
        $filterPayload = is_array($reconciliation['filterPayload'] ?? null) ? $reconciliation['filterPayload'] : [];
        $datasetChecksum = isset($reconciliation['datasetChecksum']) ? (string) $reconciliation['datasetChecksum'] : null;
        $strictChecksum = (bool) ($reconciliation['strictChecksum'] ?? config('hcm.export_reconciliation.strict_checksum', false));

        try {
            app(ReconciliationGateService::class)->assertCanProceed(
                $payment->company_id,
                'payment',
                'verify',
                (string) $payment->id,
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
