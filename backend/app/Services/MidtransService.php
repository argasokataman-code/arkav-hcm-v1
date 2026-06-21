<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        $this->configure();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Create a Snap hosted-payment transaction.
     *
     * @param  array{
     *   order_id: string,
     *   amount: int,
     *   customer?: array{name?: string, email?: string},
     *   description?: string,
     *   items?: list<array{id?: string, price: int, quantity: int, name: string}>,
     *   finish_url?: string,
     *   unfinish_url?: string,
     *   error_url?: string,
     * }  $params
     * @return array{token: string, redirect_url: string, order_id: string}
     *
     * @throws \RuntimeException on failure
     */
    public function createTransaction(array $params): array
    {
        $orderId = (string) $params['order_id'];
        $amount = (int) $params['amount'];
        $customer = $params['customer'] ?? [];
        $itemName = (string) ($params['description'] ?? 'Invoice payment');

        $transactionDetails = [
            'order_id' => $orderId,
            'gross_amount' => $amount,
        ];

        $customerDetails = [];
        if (! empty($customer['name'])) {
            $customerDetails['first_name'] = (string) $customer['name'];
        }
        if (! empty($customer['email'])) {
            $customerDetails['email'] = (string) $customer['email'];
        }

        $itemDetails = [];
        if (! empty($params['items']) && is_array($params['items'])) {
            foreach ($params['items'] as $item) {
                $itemDetails[] = [
                    'id' => (string) ($item['id'] ?? 'item'),
                    'price' => (int) ($item['price'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'name' => (string) ($item['name'] ?? 'Item'),
                ];
            }
        }

        if (empty($itemDetails)) {
            $itemDetails = [
                ['id' => 'payment', 'price' => $amount, 'quantity' => 1, 'name' => $itemName],
            ];
        }

        $snapParams = [
            'transaction_details' => $transactionDetails,
            'item_details' => $itemDetails,
        ];

        if (! empty($customerDetails)) {
            $snapParams['customer_details'] = $customerDetails;
        }

        $callbacks = [];
        if (! empty($params['finish_url'])) {
            $callbacks['finish'] = $params['finish_url'];
        }
        if (! empty($params['unfinish_url'])) {
            $callbacks['unfinish'] = $params['unfinish_url'];
        }
        if (! empty($params['error_url'])) {
            $callbacks['error'] = $params['error_url'];
        }
        if (! empty($callbacks)) {
            $snapParams['callbacks'] = $callbacks;
        }

        Log::info('MidtransService: creating transaction', [
            'order_id' => $orderId,
            'amount' => $amount,
        ]);

        $response = Snap::createTransaction($snapParams);

        // SDK returns a stdClass with token and redirect_url
        $token = (string) ($response->token ?? '');
        $redirectUrl = (string) ($response->redirect_url ?? '');

        if ($token === '' || $redirectUrl === '') {
            throw new \RuntimeException('Midtrans Snap::createTransaction returned empty token/redirect_url');
        }

        Log::info('MidtransService: transaction created', [
            'order_id' => $orderId,
            'redirect_url' => $redirectUrl,
        ]);

        return [
            'token' => $token,
            'redirect_url' => $redirectUrl,
            'order_id' => $orderId,
        ];
    }

    /**
     * Get transaction status from Midtrans.
     *
     * Returns the raw status object cast to array, or null on error.
     *
     * @return array|null Midtrans transaction status payload
     */
    public function getTransaction(string $orderId): ?array
    {
        try {
            $status = Transaction::status($orderId);
            if (is_object($status)) {
                return (array) $status;
            }

            return is_array($status) ? $status : null;
        } catch (\Throwable $e) {
            Log::warning('MidtransService: getTransaction failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify the signature_key in an inbound notification payload.
     *
     * Midtrans signature: SHA512(order_id + status_code + gross_amount + server_key)
     *
     * @param  array  $payload  Raw notification body (decoded JSON)
     */
    public function verifySignature(array $payload): bool
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureIn = (string) ($payload['signature_key'] ?? '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureIn === '') {
            return false;
        }

        $serverKey = (string) config('services.midtrans.server_key', '');
        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, strtolower($signatureIn));
    }

    /**
     * Normalise a Midtrans transaction_status + fraud_status pair to one of:
     * 'paid' | 'pending' | 'failed'
     */
    public function resolvePaymentState(string $transactionStatus, string $fraudStatus = ''): string
    {
        return match ($transactionStatus) {
            'settlement' => 'paid',
            'capture' => $fraudStatus === 'accept' ? 'paid' : 'pending', // 'challenge' = pending review
            'pending', 'authorize' => 'pending',
            'cancel', 'deny', 'expire' => 'failed',
            default => 'pending',
        };
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function configure(): void
    {
        MidtransConfig::$serverKey = (string) config('services.midtrans.server_key', '');
        MidtransConfig::$isProduction = (bool) config('services.midtrans.is_production', false);
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;
    }
}
