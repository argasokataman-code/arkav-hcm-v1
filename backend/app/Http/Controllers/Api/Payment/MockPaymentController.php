<?php

namespace App\Http\Controllers\Api\Payment;

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
        if (! $this->isMockModeEnabled()) {
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

        $invoice = Invoice::query()
            ->where('uuid', $validated['invoice_id'])
            ->firstOrFail();

        // Check authorization
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId > 0 && $invoice->company_id !== $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Cannot pay this invoice.'],
            ], 403);
        }

        $service = new MockPaymentGatewayService;
        $paymentMethod = (string) ($validated['payment_method'] ?? 'mock_card');
        $storedPaymentMethod = match ($paymentMethod) {
            'mock_bank' => 'bank_transfer',
            'mock_ewallet' => 'e_wallet',
            default => 'credit_card',
        };

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
            'payment_method' => $paymentMethod,
            'invoice_id' => $validated['invoice_id'],
            'currency' => $invoice->company?->currency ?: 'IDR',
        ]);

        // Create payment record
        $payment = Payment::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'amount' => $validated['amount'],
            'currency' => $invoice->company?->currency ?: 'IDR',
            'payment_method' => $storedPaymentMethod,
            'gateway' => 'mock',
            'gateway_reference' => $result['charge_id'],
            'status' => 'completed',
            'paid_at' => now(),
            'verified_at' => now(),
        ]);

        // Mark invoice as paid and trigger subscription activation if eligible.
        $invoice->markAsPaid();
        $invoice->refresh();

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
                    'uuid' => $payment->uuid,
                    'gateway_reference' => $payment->gateway_reference,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ],
                'invoice' => [
                    'id' => $invoice->id,
                    'uuid' => $invoice->uuid,
                    'status' => $invoice->status,
                    'paid_at' => $invoice->paid_date?->toIso8601String(),
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
        if (! $this->isMockModeEnabled()) {
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
            'flow_mode' => 'nullable|string|in:instant,hosted',
            'success_url' => 'nullable|url|max:2048',
            'failure_url' => 'nullable|url|max:2048',
        ]);

        $service = new MockPaymentGatewayService;

        $result = $service->createInvoiceAndPay([
            'company_id' => $activeCompanyId,
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? 'Test Invoice',
            'currency' => $validated['currency'] ?? 'IDR',
            'simulate_failure' => $validated['simulate_failure'] ?? false,
            'flow_mode' => $validated['flow_mode'] ?? 'instant',
            'success_url' => $validated['success_url'] ?? null,
            'failure_url' => $validated['failure_url'] ?? null,
        ]);

        Log::info('Mock invoice and payment created', [
            'company_id' => $activeCompanyId,
            'invoice_id' => $result['invoice']['id'],
            'invoice_uuid' => $result['invoice']['uuid'] ?? null,
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
        if (! $this->isMockModeEnabled()) {
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
        if (! $this->isMockModeEnabled()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MOCK_DISABLED', 'message' => 'Mock payments are disabled.'],
            ], 403);
        }

        $validated = $request->validate([
            'payment_id' => 'required|uuid|exists:payments,uuid',
            'callback_token' => 'nullable|string|min:16',
        ]);

        $payment = Payment::query()
            ->where('uuid', $validated['payment_id'])
            ->firstOrFail();

        $callbackToken = (string) data_get($payment->metadata, 'callback_token', '');
        if ($callbackToken !== '') {
            if (($validated['callback_token'] ?? null) !== $callbackToken) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'CALLBACK_TOKEN_INVALID',
                        'message' => 'Valid callback token is required for hosted mock settlement.',
                    ],
                ], 403);
            }
        }

        // Mark as completed if pending
        if ($payment->status === 'pending') {
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'verified_at' => now(),
            ]);

            if ($payment->invoice) {
                $payment->invoice->markAsPaid();
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
                'payment_uuid' => $payment->uuid,
                'invoice_uuid' => $payment->invoice?->uuid,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'callback_token' => $callbackToken !== '' ? $callbackToken : null,
            ],
        ]);
    }

    /**
     * Check if mock payment mode is enabled
     */
    private function isMockModeEnabled(): bool
    {
        // Only allow in development or if explicitly enabled
        if (! app()->environment(['local', 'testing']) && ! config('app.mock_payments_enabled')) {
            return false;
        }

        return true;
    }
}
