<?php

namespace App\Services;

use App\Models\Payment;

class PaymentGatewayService
{
    protected string $gateway;

    public function __construct(string $gateway = 'midtrans')
    {
        $this->gateway = $gateway;
    }

    /**
     * Create a payment charge with the gateway
     */
    public function charge(array $data): array
    {
        return match ($this->gateway) {
            'midtrans' => $this->chargeWithMidtrans($data),
            default => ['success' => false, 'error' => 'Unsupported gateway'],
        };
    }

    /**
     * Verify payment with gateway
     */
    public function verify(string $reference): array
    {
        return match ($this->gateway) {
            'midtrans' => $this->verifyWithMidtrans($reference),
            default => ['success' => false, 'error' => 'Unsupported gateway'],
        };
    }

    /**
     * Handle webhook from payment gateway
     */
    public function handleWebhook(array $payload): array
    {
        return match ($this->gateway) {
            'midtrans' => $this->handleMidtransWebhook($payload),
            default => ['success' => false, 'error' => 'Unsupported gateway'],
        };
    }

    // ========== MIDTRANS ==========

    private function chargeWithMidtrans(array $data): array
    {
        try {
            $midtrans = app(MidtransService::class);
            $orderId = (string) ($data['reference_id'] ?? $data['external_id'] ?? ('payment-'.uniqid()));
            $resp = $midtrans->createTransaction([
                'order_id' => $orderId,
                'amount' => (int) round((float) ($data['amount'] ?? 0)),
                'customer' => [
                    'name' => (string) ($data['customer_name'] ?? 'Customer'),
                    'email' => (string) ($data['customer_email'] ?? ''),
                ],
                'description' => (string) ($data['description'] ?? 'Payment'),
                'items' => $data['items'] ?? [],
                'finish_url' => $data['finish_url'] ?? $data['success_url'] ?? null,
                'unfinish_url' => $data['unfinish_url'] ?? $data['failure_url'] ?? null,
                'error_url' => $data['error_url'] ?? $data['failure_url'] ?? null,
            ]);

            return [
                'success' => true,
                'gateway_reference' => $orderId,
                'order_id' => $orderId,
                'status' => 'PENDING',
                'redirect_url' => $resp['redirect_url'],
                'snap_token' => $resp['token'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifyWithMidtrans(string $reference): array
    {
        try {
            $midtrans = app(MidtransService::class);
            $tx = $midtrans->getTransaction($reference);
            if (! $tx) {
                return ['success' => false, 'error' => 'Transaction not found'];
            }

            $txStatus = strtolower((string) ($tx['transaction_status'] ?? ''));
            $fraudStatus = strtolower((string) ($tx['fraud_status'] ?? ''));
            $state = $midtrans->resolvePaymentState($txStatus, $fraudStatus);

            return [
                'success' => true,
                'status' => $txStatus,
                'paid' => $state === 'paid',
                'state' => $state,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function handleMidtransWebhook(array $payload): array
    {
        $midtrans = app(MidtransService::class);
        $txStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));
        $orderId = (string) ($payload['order_id'] ?? '');

        $state = $midtrans->resolvePaymentState($txStatus, $fraudStatus);

        if ($state === 'paid') {
            $payment = Payment::query()
                ->where('gateway', 'midtrans')
                ->where(function ($q) use ($orderId): void {
                    $q->where('gateway_reference', $orderId)
                        ->orWhere('metadata->midtrans_order_id', $orderId);
                })
                ->latest('id')
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'verified_at' => now(),
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'midtrans_transaction_id' => (string) ($payload['transaction_id'] ?? ''),
                        'midtrans_payment_type' => (string) ($payload['payment_type'] ?? ''),
                        'midtrans_fraud_status' => $fraudStatus,
                    ]),
                ]);
            }
        }

        return ['success' => true];
    }
}
