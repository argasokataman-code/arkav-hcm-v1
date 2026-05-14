<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class XenditService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.xendit.co';

    public function __construct()
    {
        $this->apiKey = config('services.xendit.api_key');
        if (!$this->apiKey) {
            throw new \RuntimeException('Xendit API key not configured (XENDIT_API_KEY)');
        }
    }

    /**
     * Create an invoice on Xendit
     * 
     * @param array $params Invoice parameters
     * @return array|null Xendit invoice data or null on error
     */
    public function createInvoice(array $params): ?array
    {
        try {
            $response = $this->post('/v2/invoices', [
                'external_id' => $params['external_id'],
                'amount' => $params['amount'],
                'description' => $params['description'] ?? 'Invoice',
                'invoice_duration' => $params['invoice_duration'] ?? 86400 * 7, // 7 days default
                'customer' => [
                    'given_names' => $params['customer_name'] ?? 'Customer',
                    'email' => $params['customer_email'] ?? '',
                ],
                'currency' => $params['currency'] ?? 'IDR',
                'items' => $params['items'] ?? [],
                'fees' => $params['fees'] ?? [],
                'success_redirect_url' => $params['success_url'] ?? null,
                'failure_redirect_url' => $params['failure_url'] ?? null,
                'reminder_time' => $params['reminder_time'] ?? 1,
                'available_payment_methods' => $params['available_payment_methods'] ?? null,
                'metadata' => $params['metadata'] ?? null,
            ]);

            Log::info('Xendit invoice created', [
                'external_id' => $params['external_id'],
                'xendit_invoice_id' => $response['id'] ?? null,
                'amount' => $params['amount'],
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Xendit invoice creation failed', [
                'external_id' => $params['external_id'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get invoice details from Xendit
     * 
     * @param string $invoiceId Xendit invoice ID
     * @return array|null Invoice data or null on error
     */
    public function getInvoice(string $invoiceId): ?array
    {
        try {
            return $this->get("/v2/invoices/$invoiceId");
        } catch (\Exception $e) {
            Log::error('Xendit invoice retrieval failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify invoice payment status
     * Useful for polling if webhook fails
     * 
     * @param string $invoiceId Xendit invoice ID
     * @return bool True if payment is completed/settled
     */
    public function verifyInvoicePayment(string $invoiceId): bool
    {
        $invoice = $this->getInvoice($invoiceId);
        if (!$invoice) {
            return false;
        }

        return in_array($invoice['status'] ?? null, ['SETTLED', 'PAID']);
    }

    /**
     * Expire an invoice
     * 
     * @param string $invoiceId Xendit invoice ID
     * @return bool Success status
     */
    public function expireInvoice(string $invoiceId): bool
    {
        try {
            $this->post("/v2/invoices/$invoiceId/expire!");
            Log::info('Xendit invoice expired', ['invoice_id' => $invoiceId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Xendit invoice expiration failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create recurring invoice (for subscriptions)
     * 
     * @param array $params Recurring invoice parameters
     * @return array|null Xendit recurring invoice data
     */
    public function createRecurringInvoice(array $params): ?array
    {
        try {
            $response = $this->post('/v2/recurring_invoices', [
                'external_id' => $params['external_id'],
                'description' => $params['description'] ?? 'Recurring Invoice',
                'amount' => $params['amount'],
                'interval' => $params['interval'] ?? 'MONTHLY', // DAILY, WEEKLY, MONTHLY
                'interval_count' => $params['interval_count'] ?? 1,
                'recurrence_count' => $params['recurrence_count'] ?? null, // null = infinite
                'customer' => [
                    'given_names' => $params['customer_name'] ?? 'Customer',
                    'email' => $params['customer_email'] ?? '',
                ],
                'currency' => $params['currency'] ?? 'IDR',
                'items' => $params['items'] ?? [],
                'success_redirect_url' => $params['success_url'] ?? null,
                'failure_redirect_url' => $params['failure_url'] ?? null,
                'reminder_time' => $params['reminder_time'] ?? 1,
                'first_invoice_date' => $params['first_invoice_date'] ?? now()->toDateString(),
            ]);

            Log::info('Xendit recurring invoice created', [
                'external_id' => $params['external_id'],
                'xendit_recurring_id' => $response['id'] ?? null,
                'amount' => $params['amount'],
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Xendit recurring invoice creation failed', [
                'external_id' => $params['external_id'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Stop recurring invoice
     * 
     * @param string $recurringId Xendit recurring invoice ID
     * @return bool Success status
     */
    public function stopRecurringInvoice(string $recurringId): bool
    {
        try {
            $this->post("/v2/recurring_invoices/$recurringId/stop");
            Log::info('Xendit recurring invoice stopped', ['recurring_id' => $recurringId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Xendit recurring invoice stop failed', [
                'recurring_id' => $recurringId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create disbursement (for payouts)
     * 
     * @param array $params Disbursement parameters
     * @return array|null Xendit disbursement data
     */
    public function createDisbursement(array $params): ?array
    {
        try {
            $response = $this->post('/v2/disbursements', [
                'external_id' => $params['external_id'],
                'bank_account_number' => $params['account_number'],
                'bank_code' => $params['bank_code'],
                'amount' => $params['amount'],
                'description' => $params['description'] ?? 'Disbursement',
                'receipt_notification_email_addresses' => $params['email'] ? [$params['email']] : [],
            ]);

            Log::info('Xendit disbursement created', [
                'external_id' => $params['external_id'],
                'xendit_disbursement_id' => $response['id'] ?? null,
                'amount' => $params['amount'],
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Xendit disbursement creation failed', [
                'external_id' => $params['external_id'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Make HTTP GET request to Xendit API
     */
    private function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    /**
     * Make HTTP POST request to Xendit API
     */
    private function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Generic HTTP request handler with error handling
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->apiKey . ':',
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Xendit API request failed: $error");
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $errorMsg = $decoded['error_code'] ?? $decoded['message'] ?? 'Unknown error';
            Log::debug('Xendit API validation failed', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'request_data' => $data,
                'response_body' => $decoded,
            ]);
            throw new \RuntimeException("Xendit API error ($httpCode): $errorMsg");
        }

        return $decoded ?? [];
    }
}
