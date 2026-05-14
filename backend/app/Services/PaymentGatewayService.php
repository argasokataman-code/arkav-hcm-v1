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
            $xenditService = app(XenditService::class);
            $externalId = (string) ($data['reference_id'] ?? $data['external_id'] ?? ('payment-'.uniqid()));
            $invoice = $xenditService->createInvoice([
                'external_id' => $externalId,
                'amount' => (int) round((float) ($data['amount'] ?? 0)),
                'currency' => strtoupper((string) ($data['currency'] ?? 'IDR')),
                'description' => (string) ($data['description'] ?? 'Payment charge'),
                'customer_name' => (string) ($data['customer_name'] ?? 'Customer'),
                'customer_email' => (string) ($data['customer_email'] ?? ''),
                'success_url' => $data['success_url'] ?? null,
                'failure_url' => $data['failure_url'] ?? null,
            ]);

            if ($invoice && ! empty($invoice['id'])) {
                return [
                    'success' => true,
                    'gateway_reference' => (string) $invoice['id'],
                    'external_id' => $externalId,
                    'status' => (string) ($invoice['status'] ?? 'PENDING'),
                ];
            }

            return ['success' => false, 'error' => 'Invoice creation failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifyWithXendit(string $reference): array
    {
        try {
            $xenditService = app(XenditService::class);
            $invoice = $xenditService->getInvoice($reference);

            if ($invoice) {
                $status = strtoupper((string) ($invoice['status'] ?? ''));
                return [
                    'success' => true,
                    'status' => $status,
                    'paid' => in_array($status, ['SETTLED', 'PAID'], true),
                ];
            }

            return ['success' => false, 'error' => 'Invoice not found'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function handleXenditWebhook(array $payload): array
    {
        $eventType = $payload['event'] ?? $payload['type'] ?? null;
        $invoiceId = $payload['id'] ?? null;
        $externalId = $payload['external_id'] ?? null;
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if (in_array($eventType, ['invoice.paid', 'payment.successful'], true) || in_array($status, ['SETTLED', 'PAID', 'SUCCEEDED'], true)) {
            $payment = Payment::query()
                ->where(function ($query) use ($invoiceId, $externalId): void {
                    if ($invoiceId) {
                        $query->where('gateway_reference', $invoiceId)
                            ->orWhere('metadata->xendit_invoice_id', $invoiceId);
                    }
                    if ($externalId) {
                        $query->orWhere('gateway_reference', $externalId)
                            ->orWhere('metadata->xendit_external_id', $externalId);
                    }
                })
                ->latest('id')
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'verified_at' => now(),
                ]);
                return ['success' => true];
            }
        }

        return ['success' => true];
    }
}
