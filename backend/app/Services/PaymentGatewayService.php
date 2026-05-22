<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class PaymentGatewayService
{
    protected string $gateway;

    public function __construct(string $gateway = 'stripe')
    {
        $this->gateway = $gateway;
    }

    /**
     * Create a payment charge with the gateway
     */
    public function charge(array $data): array
    {
        return match ($this->gateway) {
            'stripe' => $this->chargeWithStripe($data),
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
            'stripe' => $this->verifyWithStripe($reference),
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
            'stripe' => $this->handleStripeWebhook($payload),
            'midtrans' => $this->handleMidtransWebhook($payload),
            default => ['success' => false, 'error' => 'Unsupported gateway'],
        };
    }

    // ========== STRIPE ==========

    private function chargeWithStripe(array $data): array
    {
        try {
            $response = Http::withToken(config('services.stripe.secret'))
                ->post('https://api.stripe.com/v1/charges', [
                    'amount' => (int) ($data['amount'] * 100), // cents
                    'currency' => strtolower($data['currency'] ?? 'usd'),
                    'source' => $data['token'] ?? $data['source'],
                    'description' => $data['description'] ?? '',
                    'metadata' => [
                        'payment_id' => $data['payment_id'] ?? null,
                        'invoice_id' => $data['invoice_id'] ?? null,
                    ],
                ]);

            if ($response->successful()) {
                $charge = $response->json();
                return [
                    'success' => true,
                    'gateway_reference' => $charge['id'],
                    'status' => $charge['status'],
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'Charge failed',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifyWithStripe(string $reference): array
    {
        try {
            $response = Http::withToken(config('services.stripe.secret'))
                ->get("https://api.stripe.com/v1/charges/$reference");

            if ($response->successful()) {
                $charge = $response->json();
                return [
                    'success' => true,
                    'status' => $charge['status'],
                    'paid' => $charge['paid'] ?? false,
                ];
            }

            return ['success' => false, 'error' => 'Charge not found'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function handleStripeWebhook(array $payload): array
    {
        $event = $payload['type'] ?? null;

        if ($event === 'charge.succeeded') {
            $chargeId = $payload['data']['object']['id'] ?? null;

            // Find and update payment
            $payment = Payment::where('gateway_reference', $chargeId)->first();
            if ($payment) {
                $payment->update(['status' => 'completed', 'verified_at' => now()]);
                return ['success' => true];
            }
        }

        return ['success' => true]; // Always return success for webhook
    }


        // ========== MIDTRANS (skeleton) ==========

    private function chargeWithMidtrans(array $data): array
    {
        try {
            $midtrans  = app(\App\Services\MidtransService::class);
            $orderId   = (string) ($data['reference_id'] ?? $data['external_id'] ?? ('payment-' . uniqid()));
            $resp = $midtrans->createTransaction([
                'order_id'     => $orderId,
                'amount'       => (int) round((float) ($data['amount'] ?? 0)),
                'customer'     => [
                    'name'  => (string) ($data['customer_name'] ?? 'Customer'),
                    'email' => (string) ($data['customer_email'] ?? ''),
                ],
                'description'  => (string) ($data['description'] ?? 'Payment'),
                'items'        => $data['items'] ?? [],
                'finish_url'   => $data['finish_url'] ?? $data['success_url'] ?? null,
                'unfinish_url' => $data['unfinish_url'] ?? $data['failure_url'] ?? null,
                'error_url'    => $data['error_url'] ?? $data['failure_url'] ?? null,
            ]);

            return [
                'success'           => true,
                'gateway_reference' => $orderId,
                'order_id'         => $orderId,
                'status'            => 'PENDING',
                'redirect_url'      => $resp['redirect_url'],
                'snap_token'        => $resp['token'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifyWithMidtrans(string $reference): array
    {
        try {
            $midtrans = app(\App\Services\MidtransService::class);
            $tx = $midtrans->getTransaction($reference);
            if (! $tx) {
                return ['success' => false, 'error' => 'Transaction not found'];
            }

            $txStatus   = strtolower((string) ($tx['transaction_status'] ?? ''));
            $fraudStatus = strtolower((string) ($tx['fraud_status'] ?? ''));
            $state      = $midtrans->resolvePaymentState($txStatus, $fraudStatus);

            return [
                'success' => true,
                'status'  => $txStatus,
                'paid'    => $state === 'paid',
                'state'   => $state,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function handleMidtransWebhook(array $payload): array
    {
        $midtrans    = app(\App\Services\MidtransService::class);
        $txStatus    = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));
        $orderId     = (string) ($payload['order_id'] ?? '');

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
                    'status'      => 'completed',
                    'paid_at'     => now(),
                    'verified_at' => now(),
                    'metadata'    => array_merge($payment->metadata ?? [], [
                        'midtrans_transaction_id' => (string) ($payload['transaction_id'] ?? ''),
                        'midtrans_payment_type'   => (string) ($payload['payment_type'] ?? ''),
                        'midtrans_fraud_status'   => $fraudStatus,
                    ]),
                ]);
            }
        }

        return ['success' => true];
    }
}
