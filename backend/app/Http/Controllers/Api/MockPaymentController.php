<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\MockPaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Mock Payment Controller
 * 
 * Development-only endpoints for testing payment flows without actual gateway integration.
 * 
 * Routes (NOTE: apiPrefix is empty, so routes are /v1/... not /api/v1/...):
 * POST   /v1/mock/payments/create          - Create mock payment
 * POST   /v1/mock/invoices/create-and-pay  - Create invoice and process mock payment
 * GET    /v1/mock/test-cards               - Get test card numbers
 * POST   /v1/mock/webhook/charge-succeeded - Simulate charge.succeeded webhook
 * 
 * SECURITY: These endpoints should ONLY be available in development (config gate)
 */
class MockPaymentController extends Controller
{
    /**
     * POST /api/mock/payments/create
     * Create a mock payment (instant success)
     */
    public function createPayment(Request $request): JsonResponse
    {
        if (!$this->isMockModeEnabled()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MOCK_DISABLED', 'message' => 'Mock payments are disabled.'],
            ], 403);
        }

        $validated = $request->validate([
            'invoice_id' => 'required|uuid|exists:invoices,uuid',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|in:mock_card,mock_bank,mock_ewallet',
            'simulate_failure' => 'nullable|boolean',
        ]);

        $invoice = Invoice::find($validated['invoice_id']);

        // Check authorization
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId > 0 && $invoice->company_id !== $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot pay this invoice.'],
            ], 403);
        }

        $service = new MockPaymentGatewayService();

        // Simulate failure if requested
        if ($validated['simulate_failure'] ?? false) {
            $result = $service->createFailedPayment([
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'] ?? 'mock_card',
                'invoice_id' => $validated['invoice_id'],
                'failure_reason' => 'Mock payment simulated failure',
            ]);

            Log::info('Mock payment creation (FAILED)', [
                'invoice_id' => $validated['invoice_id'],
                'amount' => $validated['amount'],
            ]);

            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }

        // Create successful payment
        $result = $service->createPayment([
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? 'mock_card',
            'invoice_id' => $validated['invoice_id'],
            'currency' => $invoice->currency,
        ]);

        // Create payment record
        $payment = Payment::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'amount' => $validated['amount'],
            'currency' => $invoice->currency,
            'payment_method' => $validated['payment_method'] ?? 'mock_card',
            'gateway' => 'mock',
            'gateway_reference' => $result['charge_id'],
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Mark invoice as paid
        $invoice->update([
            'status' => 'paid',
            'is_paid' => true,
            'paid_date' => now(),
        ]);

        Log::info('Mock payment created successfully', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $validated['amount'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mock payment processed successfully',
            'data' => [
                'payment' => [
                    'id' => $payment->id,
                    'gateway_reference' => $payment->gateway_reference,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'paid_date' => $payment->paid_date?->toIso8601String(),
                ],
                'invoice' => [
                    'id' => $invoice->id,
                    'status' => $invoice->status,
                    'paid_date' => $invoice->paid_date?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * POST /api/mock/invoices/create-and-pay
     * Create a mock invoice and immediately process payment
     */
    public function createInvoiceAndPay(Request $request): JsonResponse
    {
        if (!$this->isMockModeEnabled()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MOCK_DISABLED', 'message' => 'Mock payments are disabled.'],
            ], 403);
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_REQUIRED', 'message' => 'Active company context required.'],
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'currency' => 'nullable|string|in:IDR,USD',
            'simulate_failure' => 'nullable|boolean',
        ]);

        $service = new MockPaymentGatewayService();
        
        $result = $service->createInvoiceAndPay([
            'company_id' => $activeCompanyId,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? 'Test Invoice',
            'currency' => $validated['currency'] ?? 'IDR',
            'simulate_failure' => $validated['simulate_failure'] ?? false,
        ]);

        Log::info('Mock invoice and payment created', [
            'company_id' => $activeCompanyId,
            'invoice_id' => $result['invoice']['id'],
            'amount' => $validated['amount'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mock invoice and payment created successfully',
            'data' => $result,
        ], 201);
    }

    /**
     * GET /api/mock/test-cards
     * Get list of test card numbers for simulation
     */
    public function getTestCards(): JsonResponse
    {
        if (!$this->isMockModeEnabled()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MOCK_DISABLED', 'message' => 'Mock payments are disabled.'],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => MockPaymentGatewayService::getTestCards(),
            'note' => 'These are test card numbers for mock payment simulation. Use with cvv=123 and any future date.',
        ]);
    }

    /**
     * POST /api/mock/webhook/charge-succeeded
     * Simulate a successful charge webhook for testing webhook handlers
     */
    public function simulateChargeSucceeded(Request $request): JsonResponse
    {
        if (!$this->isMockModeEnabled()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MOCK_DISABLED', 'message' => 'Mock payments are disabled.'],
            ], 403);
        }

        $validated = $request->validate([
            'payment_id' => 'required|uuid|exists:payments,uuid',
        ]);

        $payment = Payment::find($validated['payment_id']);
        
        // Mark as completed if pending
        if ($payment->status === 'pending') {
            $payment->update(['status' => 'completed', 'paid_date' => now()]);
            
            if ($payment->invoice) {
                $payment->invoice->update([
                    'status' => 'paid',
                    'is_paid' => true,
                    'paid_date' => now(),
                ]);
            }
        }

        Log::info('Mock charge.succeeded webhook simulated', [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook simulated',
            'data' => [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'paid_date' => $payment->paid_date?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Check if mock payment mode is enabled
     */
    private function isMockModeEnabled(): bool
    {
        // Only allow in development or if explicitly enabled
        if (!app()->isLocal() && !config('app.mock_payments_enabled')) {
            return false;
        }
        return true;
    }
}
