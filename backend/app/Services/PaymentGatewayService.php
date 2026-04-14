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
            'xendit' => $this->chargeWithXendit($data),
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
            'xendit' => $this->verifyWithXendit($reference),
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
            'xendit' => $this->handleXenditWebhook($payload),
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

    // ========== XENDIT ==========

    private function chargeWithXendit(array $data): array
    {
        try {
            $response = Http::withBasicAuth(config('services.xendit.key'), '')
                ->post('https://api.xendit.co/charges', [
                    'reference_id' => $data['reference_id'] ?? uniqid(),
                    'currency' => strtoupper($data['currency'] ?? 'IDR'),
                    'amount' => (int) $data['amount'],
                    'payment_method' => [
                        'type' => $data['payment_type'] ?? 'CARD',
                        'card' => [
                            'number' => $data['card_number'] ?? null,
                            'cvv' => $data['card_cvv'] ?? null,
                            'exp_month' => $data['card_exp_month'] ?? null,
                            'exp_year' => $data['card_exp_year'] ?? null,
                        ],
                    ],
                    'metadata' => [
                        'payment_id' => $data['payment_id'] ?? null,
                    ],
                ]);

            if ($response->successful()) {
                $charge = $response->json();
                return [
                    'success' => true,
                    'gateway_reference' => $charge['id'],
                    'status' => $charge['status'] ?? 'pending',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Charge failed',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifyWithXendit(string $reference): array
    {
        try {
            $response = Http::withBasicAuth(config('services.xendit.key'), '')
                ->get("https://api.xendit.co/charges/$reference");

            if ($response->successful()) {
                $charge = $response->json();
                return [
                    'success' => true,
                    'status' => $charge['status'],
                    'paid' => strtolower($charge['status']) === 'succeeded',
                ];
            }

            return ['success' => false, 'error' => 'Charge not found'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function handleXenditWebhook(array $payload): array
    {
        $chargeId = $payload['id'] ?? null;
        $status = $payload['status'] ?? null;

        if ($status === 'SUCCEEDED') {
            $payment = Payment::where('gateway_reference', $chargeId)->first();
            if ($payment) {
                $payment->update(['status' => 'completed', 'verified_at' => now()]);
                return ['success' => true];
            }
        }

        return ['success' => true];
    }
}
