<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingTaxCalculationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mock Payment Gateway Service
 * 
 * Simulates payment processing for development/testing WITHOUT actual gateway integration.
 * Use this when payment gateway subscriptions are not yet active.
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
        $flowMode = (string) ($params['flow_mode'] ?? 'instant');
        $successRedirectUrl = (string) ($params['success_url'] ?? url('/mock-payment-tester.html'));
        $failureRedirectUrl = (string) ($params['failure_url'] ?? url('/mock-payment-tester.html'));
        $paymentMethod = (string) ($params['payment_method'] ?? 'mock_card');
        $storedPaymentMethod = match ($paymentMethod) {
            'mock_bank' => 'bank_transfer',
            'mock_ewallet' => 'e_wallet',
            default => 'credit_card',
        };

        // Check if company has a pending trial/payment subscription to activate
        $subscription = \App\Models\Subscription::where('company_id', $companyId)
            ->whereIn('status', ['pending_payment', 'trial'])
            ->latest('created_at')
            ->first();

        // Create invoice
        $taxRateSnapshot = app(BillingTaxCalculationService::class)
            ->resolvePolicyRateSnapshot((int) $companyId, now()->format('Y-m'));

        $invoice = \App\Models\Invoice::create([
            'company_id' => $companyId,
            'subscription_id' => $subscription?->id,
            'invoice_number' => 'MOCK-' . date('YmdHis'),
            'status' => 'draft',
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'amount_due' => $amount,
            'billing_tax_rate_snapshot' => $taxRateSnapshot > 0 ? $taxRateSnapshot : null,
            'is_paid' => false,
            'notes' => $description,
        ]);

        $paymentStatus = $flowMode === 'hosted'
            ? 'pending'
            : (($params['simulate_failure'] ?? false) ? 'failed' : 'completed');

        $callbackToken = $flowMode === 'hosted'
            ? Str::random(40)
            : null;

        $paymentMetadata = [
            'mock_flow_mode' => $flowMode,
        ];

        if ($callbackToken) {
            $paymentMetadata['callback_token'] = $callbackToken;
            $paymentMetadata['success_redirect_url'] = $successRedirectUrl;
            $paymentMetadata['failure_redirect_url'] = $failureRedirectUrl;
            $paymentMetadata['webhook_url'] = url('/v1/mock/webhook/charge-succeeded');
        }

        // Create mock payment
        $payment = Payment::create([
            'company_id' => $companyId,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'currency' => $params['currency'] ?? 'IDR',
            'payment_method' => $storedPaymentMethod,
            'gateway' => 'mock',
            'gateway_reference' => 'mock_' . uniqid(),
            'status' => $paymentStatus,
            'metadata' => $paymentMetadata,
        ]);

        $hostedCheckoutUrl = null;
        if ($flowMode === 'hosted' && $callbackToken) {
            $hostedCheckoutUrl = url('/mock-hosted-payment.html').'?'.http_build_query([
                'payment_uuid' => $payment->uuid,
                'invoice_uuid' => $invoice->uuid,
                'invoice_number' => $invoice->invoice_number,
                'amount' => (float) $payment->amount,
                'callback_token' => $callbackToken,
                'success_url' => $successRedirectUrl,
                'failure_url' => $failureRedirectUrl,
            ]);

            $paymentMetadata['hosted_checkout_url'] = $hostedCheckoutUrl;
            $payment->update(['metadata' => $paymentMetadata]);
            $payment->refresh();
        }

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
            $invoice->markAsPaid();
            $invoice->refresh();
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
                'uuid' => $invoice->uuid,
                'number' => $invoice->invoice_number,
                'amount' => $invoice->amount_due,
                'status' => $invoice->status,
            ],
            'payment' => [
                'id' => $payment->id,
                'uuid' => $payment->uuid,
                'gateway_reference' => $payment->gateway_reference,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'callback_token' => $callbackToken,
                'hosted_checkout_url' => $hostedCheckoutUrl,
            ],
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'activated' => $payment->status === 'completed',
            ] : null,
            'flow' => [
                'mode' => $flowMode,
                'hosted_checkout_url' => $hostedCheckoutUrl,
                'success_redirect_url' => $successRedirectUrl,
                'failure_redirect_url' => $failureRedirectUrl,
                'callback_token' => $callbackToken,
                'webhook' => [
                    'simulate_success_url' => url('/v1/mock/webhook/charge-succeeded'),
                    'requires_callback_token' => $flowMode === 'hosted',
                ],
            ],
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
