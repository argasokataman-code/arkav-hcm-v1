<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingTaxCalculationService;
use App\Services\InvoiceService;
use App\Services\MidtransService;
use App\Services\MockPaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HcmCompanyInvoiceController
{
    use ChecksPermissions;
    use EnsuresHcmAdmin;

    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Tenant owner is treated as tenant-admin for their company; keep same gate as checkout.
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'is_paid' => ['nullable', 'in:0,1'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 15);

        $q = Invoice::query()
            ->with(['company:id,name,code', 'subscription.package'])
            ->where('company_id', $companyId);

        if (!empty($validated['status'])) {
            $q->where('status', $validated['status']);
        }
        if (array_key_exists('is_paid', $validated)) {
            $q->where('is_paid', (bool) ((int) $validated['is_paid']));
        }
        if (!empty($validated['search'])) {
            $term = trim((string) $validated['search']);
            $q->where(function ($inner) use ($term): void {
                $inner->where('invoice_number', 'like', '%'.$term.'%')
                    ->orWhere('notes', 'like', '%'.$term.'%');
            });
        }

        $p = $q->latest('issue_date')->paginate($perPage);

        $items = collect($p->items())
            ->map(fn (Invoice $inv) => $this->healLegacyUnpaidSubscriptionInvoiceTax($inv))
            ->map(fn (Invoice $inv) => $this->invoiceService->formatInvoice($inv))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'page' => $p->currentPage(),
                'perPage' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $invoice = Invoice::query()
            ->with(['company:id,name,code', 'subscription.package'])
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->firstOrFail();

        $invoice = $this->healLegacyUnpaidSubscriptionInvoiceTax($invoice);

        return response()->json(['success' => true, 'data' => $this->invoiceService->formatInvoice($invoice)]);
    }

    public function download(Request $request, int $id)
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $invoice = Invoice::query()
            ->with('purchaseTransaction')
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->firstOrFail();

        $invoice = $this->healLegacyUnpaidSubscriptionInvoiceTax($invoice);

        $path = $invoice->pdf_path ?: $this->invoiceService->generatePdf($invoice);
        if (! $path) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVOICE_PDF_FAILED', 'message' => 'Failed to generate invoice PDF.'],
            ], 500);
        }

        return Storage::disk('local')->download($path, basename($path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function mockPay(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $invoice = Invoice::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->firstOrFail();

        $invoice = $this->healLegacyUnpaidSubscriptionInvoiceTax($invoice);

        $validated = $request->validate([
            'paymentMethod' => ['nullable', 'string', 'in:mock_card,mock_bank,mock_ewallet'],
            'gateway' => ['nullable', 'string', 'max:50'],
        ]);

        if ($invoice->is_paid) {
            $existingPayment = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->latest('id')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $this->invoiceService->formatInvoice($invoice),
                'payment' => $existingPayment ? [
                    'id' => $existingPayment->id,
                    'gateway' => $existingPayment->gateway,
                    'gatewayReference' => $existingPayment->gateway_reference,
                    'paymentMethod' => $existingPayment->payment_method,
                    'status' => $existingPayment->status,
                    'amount' => (float) $existingPayment->amount,
                    'paidAt' => $existingPayment->paid_at?->toIso8601String(),
                ] : null,
            ]);
        }

        $mockGateway = new MockPaymentGatewayService();
        $paymentMethod = (string) ($validated['paymentMethod'] ?? 'mock_card');
        $gateway = (string) ($validated['gateway'] ?? 'mock');
        $mappedPaymentMethod = match ($paymentMethod) {
            'mock_bank' => 'bank_transfer',
            'mock_ewallet' => 'e_wallet',
            default => 'credit_card',
        };

        $payment = DB::transaction(function () use ($invoice, $companyId, $mockGateway, $paymentMethod, $mappedPaymentMethod, $gateway): Payment {
            $result = $mockGateway->createPayment([
                'invoice_id' => $invoice->id,
                'amount' => (float) $invoice->amount_due,
                'currency' => 'IDR',
                'payment_method' => $paymentMethod,
            ]);

            $payment = Payment::query()->create([
                'company_id' => $companyId,
                'subscription_id' => $invoice->subscription_id,
                'invoice_id' => $invoice->id,
                'amount' => (float) $invoice->amount_due,
                'currency' => 'IDR',
                'status' => 'completed',
                'payment_method' => $mappedPaymentMethod,
                'gateway' => $gateway,
                'gateway_reference' => (string) ($result['charge_id'] ?? ('mock_'.$invoice->id.'_'.now()->timestamp)),
                'paid_at' => now(),
                'verified_at' => now(),
            ]);

            $invoice->markAsPaid();

            return $payment;
        });

        $invoice->refresh();

            // Best-effort: notify customer with invoice email (includes PDF attachment when available).
            SendInvoiceEmailJob::dispatch($invoice->id)->afterCommit();

        return response()->json([
            'success' => true,
            'data' => $this->invoiceService->formatInvoice($invoice),
            'payment' => [
                'id' => $payment->id,
                'gateway' => $payment->gateway,
                'gatewayReference' => $payment->gateway_reference,
                'paymentMethod' => $payment->payment_method,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'paidAt' => $payment->paid_at?->toIso8601String(),
            ],
        ]);
    }

    public function mockHostedCheckout(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $invoice = Invoice::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->firstOrFail();

        $invoice = $this->healLegacyUnpaidSubscriptionInvoiceTax($invoice);

        if ($invoice->is_paid) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVOICE_ALREADY_PAID', 'message' => 'Invoice already paid.'],
            ], 422);
        }

        $validated = $request->validate([
            'paymentMethod' => ['nullable', 'string', 'in:bank_transfer,e_wallet,paylater,qr_code,card'],
            'gatewayMode' => ['nullable', 'string', 'in:auto,midtrans,mock'],
        ]);

        $gatewayMode = (string) ($validated['gatewayMode'] ?? 'auto');
        $allowMock = $this->canUseMockCheckout($request);
        $useMidtrans = $this->shouldUseMidtransCheckout($gatewayMode);
        $paymentMethod = (string) ($validated['paymentMethod'] ?? 'bank_transfer');

        if ($useMidtrans) {
            $result = $this->startMidtransHostedCheckout($invoice, $paymentMethod);
            if ($result instanceof JsonResponse) {
                return $result;
            }
        }

        if (! $allowMock) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'GATEWAY_NOT_CONFIGURED',
                    'message' => 'No payment gateway is configured and mock checkout is disabled.',
                ],
            ], 502);
        }

        return $this->startMockHostedCheckout($invoice);
    }

    private function startMockHostedCheckout(Invoice $invoice): JsonResponse
    {
        $companyId = (int) $invoice->company_id;

        $successUrl = url('/subscription').'?'.http_build_query([
            'mock_payment_status' => 'completed',
            'invoice_id' => $invoice->id,
        ]);
        $failureUrl = url('/subscription').'?'.http_build_query([
            'mock_payment_status' => 'failed',
            'invoice_id' => $invoice->id,
        ]);

        $existingPendingPayment = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existingPendingPayment && data_get($existingPendingPayment->metadata, 'hosted_checkout_url')) {
            return response()->json([
                'success' => true,
                'data' => $this->invoiceService->formatInvoice($invoice),
                'payment' => [
                    'id' => $existingPendingPayment->id,
                    'uuid' => $existingPendingPayment->uuid,
                    'gateway' => $existingPendingPayment->gateway,
                    'gatewayReference' => $existingPendingPayment->gateway_reference,
                    'paymentMethod' => $existingPendingPayment->payment_method,
                    'status' => $existingPendingPayment->status,
                    'amount' => (float) $existingPendingPayment->amount,
                ],
                'flow' => [
                    'mode' => 'hosted',
                    'hostedCheckoutUrl' => (string) data_get($existingPendingPayment->metadata, 'hosted_checkout_url', ''),
                    'callbackToken' => (string) data_get($existingPendingPayment->metadata, 'callback_token', ''),
                    'successRedirectUrl' => $successUrl,
                    'failureRedirectUrl' => $failureUrl,
                ],
            ]);
        }

        $mockGateway = new MockPaymentGatewayService();
        $callbackToken = (string) \Illuminate\Support\Str::random(40);
        $result = $mockGateway->createPayment([
            'invoice_id' => $invoice->id,
            'amount' => (float) $invoice->amount_due,
            'currency' => 'IDR',
            'payment_method' => 'mock_card',
        ]);

        $hostedCheckoutUrl = url('/mock-hosted-payment.html').'?'.http_build_query([
            'payment_uuid' => null,
            'invoice_uuid' => $invoice->uuid,
            'invoice_number' => $invoice->invoice_number,
            'amount' => (float) $invoice->amount_due,
            'callback_token' => $callbackToken,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
        ]);

        $payment = Payment::query()->create([
            'company_id' => $companyId,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id' => $invoice->id,
            'amount' => (float) $invoice->amount_due,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'credit_card',
            'gateway' => 'mock',
            'gateway_reference' => (string) ($result['charge_id'] ?? ('mock_'.$invoice->id.'_'.now()->timestamp)),
            'metadata' => [
                'mock_flow_mode' => 'hosted',
                'callback_token' => $callbackToken,
                'success_redirect_url' => $successUrl,
                'failure_redirect_url' => $failureUrl,
                'webhook_url' => url('/v1/mock/webhook/charge-succeeded'),
            ],
        ]);

        $hostedCheckoutUrl = url('/mock-hosted-payment.html').'?'.http_build_query([
            'payment_uuid' => $payment->uuid,
            'invoice_uuid' => $invoice->uuid,
            'invoice_number' => $invoice->invoice_number,
            'amount' => (float) $payment->amount,
            'callback_token' => $callbackToken,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
        ]);

        $payment->update([
            'metadata' => array_merge($payment->metadata ?? [], [
                'hosted_checkout_url' => $hostedCheckoutUrl,
            ]),
        ]);
        $payment->refresh();

        return response()->json([
            'success' => true,
            'data' => $this->invoiceService->formatInvoice($invoice),
            'payment' => [
                'id' => $payment->id,
                'uuid' => $payment->uuid,
                'gateway' => $payment->gateway,
                'gatewayReference' => $payment->gateway_reference,
                'paymentMethod' => $payment->payment_method,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
            ],
            'flow' => [
                'mode' => 'hosted',
                'hostedCheckoutUrl' => $hostedCheckoutUrl,
                'callbackToken' => $callbackToken,
                'successRedirectUrl' => $successUrl,
                'failureRedirectUrl' => $failureUrl,
            ],
        ]);
    }

    private function startMidtransHostedCheckout(Invoice $invoice, string $paymentMethod): JsonResponse|false
    {
        $companyId = (int) $invoice->company_id;
        $finishUrl = url('/subscription').'?'.http_build_query([
            'payment_status' => 'completed',
            'invoice_id' => $invoice->id,
        ]);
        $unfinishUrl = url('/subscription').'?'.http_build_query([
            'payment_status' => 'unfinished',
            'invoice_id' => $invoice->id,
        ]);
        $errorUrl = url('/subscription').'?'.http_build_query([
            'payment_status' => 'error',
            'invoice_id' => $invoice->id,
        ]);

        // Re-use existing pending Midtrans payment if redirect_url still valid
        $existingPendingPayment = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('gateway', 'midtrans')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existingPendingPayment && data_get($existingPendingPayment->metadata, 'midtrans_redirect_url')) {
            return response()->json([
                'success' => true,
                'data' => $this->invoiceService->formatInvoice($invoice),
                'payment' => [
                    'id' => $existingPendingPayment->id,
                    'uuid' => $existingPendingPayment->uuid,
                    'gateway' => $existingPendingPayment->gateway,
                    'gatewayReference' => $existingPendingPayment->gateway_reference,
                    'paymentMethod' => $existingPendingPayment->payment_method,
                    'status' => $existingPendingPayment->status,
                    'amount' => (float) $existingPendingPayment->amount,
                ],
                'flow' => [
                    'mode' => 'hosted',
                    'provider' => 'midtrans',
                    'hostedCheckoutUrl' => (string) data_get($existingPendingPayment->metadata, 'midtrans_redirect_url', ''),
                    'snapToken' => (string) data_get($existingPendingPayment->metadata, 'midtrans_snap_token', ''),
                    'finishRedirectUrl' => $finishUrl,
                    'unfinishRedirectUrl' => $unfinishUrl,
                    'errorRedirectUrl' => $errorUrl,
                ],
            ]);
        }

        try {
            /** @var MidtransService $midtransService */
            $midtransService = app(MidtransService::class);
        } catch (\Throwable $e) {
            \Log::error('Midtrans: Failed to resolve MidtransService', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MIDTRANS_INIT_FAILED', 'message' => 'Midtrans service unavailable: ' . $e->getMessage()],
            ], 502);
        }

        $company = $invoice->company;
        $companyName = (string) ($company->name ?? 'Customer');
        $companyEmail = (string) (auth()->user()?->email ?? 'noreply@arcav.com');
        $orderId = sprintf('invoice-%d-%s', $invoice->id, Str::lower(Str::random(10)));

        try {
            $result = $midtransService->createTransaction([
                'order_id' => $orderId,
                'amount' => (int) round((float) $invoice->amount_due),
                'description' => 'Invoice ' . $invoice->invoice_number,
                'customer' => [
                    'name' => $companyName,
                    'email' => $companyEmail,
                ],
                'items' => $this->buildMidtransInvoiceItems($invoice),
                'finish_url' => $finishUrl,
                'unfinish_url' => $unfinishUrl,
                'error_url' => $errorUrl,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Midtrans: createTransaction failed', [
                'invoice_id' => $invoice->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MIDTRANS_CREATE_FAILED', 'message' => 'Gagal membuat transaksi Midtrans: ' . $e->getMessage()],
            ], 502);
        }

        $payment = Payment::query()->create([
            'company_id' => $companyId,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id' => $invoice->id,
            'amount' => (float) $invoice->amount_due,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => $this->mapPaymentMethod($paymentMethod),
            'gateway' => 'midtrans',
            'gateway_reference' => $orderId,
            'metadata' => [
                'midtrans_order_id' => $orderId,
                'midtrans_snap_token' => $result['token'],
                'midtrans_redirect_url' => $result['redirect_url'],
                'checkout_mode' => 'midtrans_hosted',
                // midtrans_transaction_id filled after webhook notification
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->invoiceService->formatInvoice($invoice),
            'payment' => [
                'id' => $payment->id,
                'uuid' => $payment->uuid,
                'gateway' => $payment->gateway,
                'gatewayReference' => $payment->gateway_reference,
                'paymentMethod' => $payment->payment_method,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
            ],
            'flow' => [
                'mode' => 'hosted',
                'provider' => 'midtrans',
                'hostedCheckoutUrl' => $result['redirect_url'],
                'snapToken' => $result['token'],
                'finishRedirectUrl' => $finishUrl,
                'unfinishRedirectUrl' => $unfinishUrl,
                'errorRedirectUrl' => $errorUrl,
            ],
        ]);
    }



    private function canUseMockCheckout(Request $request): bool
    {
        return app()->isLocal() || (bool) config('app.mock_payments_enabled');
    }

    private function shouldUseMidtransCheckout(string $gatewayMode): bool
    {
        if ($gatewayMode === 'mock') {
            return false;
        }

        // In local dev, force mock gateway so webhook flows don't require a public URL
        if (app()->isLocal()) {
            return $gatewayMode === 'midtrans' && (bool) config('services.midtrans.server_key');
        }

        if ($gatewayMode === 'midtrans') {
            return (bool) config('services.midtrans.server_key');
        }

        // auto: use midtrans when configured
        return (bool) config('services.midtrans.server_key');
    }

    private function healLegacyUnpaidSubscriptionInvoiceTax(Invoice $invoice): Invoice
    {
        if ($invoice->is_paid || $invoice->subscription_id === null || $invoice->billing_tax_rate_snapshot !== null) {
            return $invoice;
        }

        $billingMonth = $invoice->issue_date?->format('Y-m')
            ?? $invoice->due_date?->format('Y-m')
            ?? now()->format('Y-m');

        $taxRateSnapshot = app(BillingTaxCalculationService::class)
            ->resolvePolicyRateSnapshot((int) $invoice->company_id, $billingMonth);

        if ($taxRateSnapshot <= 0) {
            return $invoice;
        }

        $decodedNotes = json_decode((string) ($invoice->notes ?? ''), true);
        $pricingBreakdown = is_array($decodedNotes) && is_array($decodedNotes['pricing_breakdown'] ?? null)
            ? $decodedNotes['pricing_breakdown']
            : null;

        $baseAmount = isset($pricingBreakdown['base_amount']) && is_numeric($pricingBreakdown['base_amount'])
            ? (float) $pricingBreakdown['base_amount']
            : (float) $invoice->amount_due;
        $taxAmount = isset($pricingBreakdown['subscription_tax_amount']) && is_numeric($pricingBreakdown['subscription_tax_amount'])
            ? (float) $pricingBreakdown['subscription_tax_amount']
            : round($baseAmount * ($taxRateSnapshot / 100), 2);
        $totalAmount = isset($pricingBreakdown['total_amount']) && is_numeric($pricingBreakdown['total_amount'])
            ? (float) $pricingBreakdown['total_amount']
            : round($baseAmount + $taxAmount, 2);

        $invoice->forceFill([
            'billing_tax_rate_snapshot' => $taxRateSnapshot,
            'amount_due' => $totalAmount,
        ])->save();

        return $invoice->fresh();
    }

    /**
     * Build item_details array for Midtrans from an invoice.
     */
    private function buildMidtransInvoiceItems($invoice): array
    {
        return [
            [
                'id'       => 'invoice-' . $invoice->id,
                'name'     => 'Invoice ' . $invoice->invoice_number,
                'price'    => (int) round((float) $invoice->amount_due),
                'quantity' => 1,
            ],
        ];
    }

    /**
     * Map internal payment method string to a normalised string for storage.
     */
    private function mapPaymentMethod(string $paymentMethod): string
    {
        $map = [
            'bank_transfer' => 'bank_transfer',
            'credit_card'   => 'credit_card',
            'gopay'         => 'gopay',
            'qris'          => 'qris',
        ];

        return $map[$paymentMethod] ?? 'bank_transfer';
    }
}

