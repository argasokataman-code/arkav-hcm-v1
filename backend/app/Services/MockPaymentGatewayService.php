<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Mock Payment Gateway Service
 * 
 * Simulates payment processing for development/testing WITHOUT actual gateway integration.
 * Use this when Stripe/Xendit subscriptions are not yet active.
 * 
 * This is development-only and should NOT be used in production.
 */
class MockPaymentGatewayService
{
    /**
     * Create a mock payment (instant success)
     * 
     * @param array $params Payment parameters
     * @return array Payment result with charge ID
     */
    public function createPayment(array $params): array
    {
        $chargeId = 'mock_' . uniqid();
        $amount = $params['amount'] ?? 0;
        $invoiceId = $params['invoice_id'] ?? null;

        Log::info('Mock payment created', [
            'charge_id' => $chargeId,
            'amount' => $amount,
            'invoice_id' => $invoiceId,
            'payment_method' => $params['payment_method'] ?? 'mock',
        ]);

        return [
            'success' => true,
            'charge_id' => $chargeId,
            'amount' => $amount,
            'currency' => $params['currency'] ?? 'IDR',
            'status' => 'completed',
            'timestamp' => now()->toIso8601String(),
            'message' => 'Mock payment processed successfully',
        ];
    }

    /**
     * Simulate payment failure
     * 
     * @param array $params Payment parameters
     * @return array Failed payment result
     */
    public function createFailedPayment(array $params): array
    {
        $chargeId = 'mock_failed_' . uniqid();
        $failureReason = $params['failure_reason'] ?? 'Insufficient funds';

        Log::warning('Mock payment failed', [
            'charge_id' => $chargeId,
            'failure_reason' => $failureReason,
        ]);

        return [
            'success' => false,
            'charge_id' => $chargeId,
            'status' => 'failed',
            'error' => [
                'code' => 'PAYMENT_FAILED',
                'message' => $failureReason,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Create mock invoice and process payment
     * 
     * @param array $params Invoice parameters
     * @return array Result with invoice and payment
     */
    public function createInvoiceAndPay(array $params): array
    {
        $companyId = $params['company_id'];
        $amount = $params['amount'];
        $description = $params['description'] ?? 'Test Invoice';

        // Check if company has a pending trial/payment subscription to activate
        $subscription = \App\Models\Subscription::where('company_id', $companyId)
            ->whereIn('status', ['pending_payment', 'trial'])
            ->latest('created_at')
            ->first();

        // Create invoice
        $invoice = \App\Models\Invoice::create([
            'company_id' => $companyId,
            'subscription_id' => $subscription?->id,
            'invoice_number' => 'MOCK-' . date('YmdHis'),
            'status' => 'pending',
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'amount_due' => $amount,
            'is_paid' => false,
            'notes' => $description,
        ]);

        // Create mock payment
        $payment = \App\Models\Payment::create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'currency' => $params['currency'] ?? 'IDR',
            'payment_method' => 'mock_card',
            'gateway' => 'mock',
            'gateway_reference' => 'mock_' . uniqid(),
            'status' => $params['simulate_failure'] ? 'failed' : 'completed',
        ]);

        // If payment succeeded and subscription exists, activate it
        if ($payment->status === 'completed' && $subscription) {
            $subscription->update([
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonths($subscription->billing_cycle === 'yearly' ? 12 : 1),
            ]);
        }

        // Mark invoice as paid if payment succeeded
        if ($payment->status === 'completed') {
            $invoice->update([
                'status' => 'paid',
                'is_paid' => true,
                'paid_date' => now(),
            ]);
        }

        Log::info('Mock invoice and payment created', [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'subscription_id' => $subscription?->id,
            'amount' => $amount,
        ]);

        return [
            'success' => true,
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'amount' => $invoice->amount_due,
                'status' => $invoice->status,
            ],
            'payment' => [
                'id' => $payment->id,
                'gateway_reference' => $payment->gateway_reference,
                'status' => $payment->status,
                'amount' => $payment->amount,
            ],
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'activated' => $payment->status === 'completed',
            ] : null,
        ];
    }

    /**
     * Get list of test card numbers for simulation
     */
    public static function getTestCards(): array
    {
        return [
            [
                'number' => '4242 4242 4242 4242',
                'name' => 'Visa Success',
                'result' => 'success',
                'description' => 'Payment will succeed',
            ],
            [
                'number' => '4000 0000 0000 0002',
                'name' => 'Visa Declined',
                'result' => 'fail',
                'description' => 'Payment will be declined',
            ],
            [
                'number' => '5555 5555 5555 4444',
                'name' => 'Mastercard Success',
                'result' => 'success',
                'description' => 'Payment will succeed',
            ],
            [
                'number' => '2223 0031 2200 3222',
                'name' => 'Mastercard Declined',
                'result' => 'fail',
                'description' => 'Payment will be declined',
            ],
            [
                'number' => 'mock_success_any',
                'name' => 'Any amount - Success',
                'result' => 'success',
                'description' => 'Any mock card number starting with "mock" will succeed',
            ],
        ];
    }
}
