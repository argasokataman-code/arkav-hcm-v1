<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingTaxCalculationService;
use App\Services\InvoiceService;
use App\Services\MockPaymentGatewayService;
use App\Services\XenditService;
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
        $gateway = (string) ($validated['gateway'] ?? 'xendit_mock');
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
            'gatewayMode' => ['nullable', 'string', 'in:auto,xendit,mock'],
        ]);

        $gatewayMode = (string) ($validated['gatewayMode'] ?? 'auto');
        $allowMock = $this->canUseMockCheckout($request);
        $useXendit = $this->shouldUseXenditCheckout($gatewayMode, $request);
        $paymentMethod = (string) ($validated['paymentMethod'] ?? 'bank_transfer');

        if ($useXendit) {
            $result = $this->startXenditHostedCheckout($invoice, $paymentMethod);
            if ($result instanceof JsonResponse) {
                return $result;
            }
        }

        if (! $allowMock) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'XENDIT_NOT_CONFIGURED',
                    'message' => 'Xendit checkout is not configured and mock checkout is disabled.',
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

    private function startXenditHostedCheckout(Invoice $invoice, string $paymentMethod): JsonResponse|false
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

        try {
            /** @var XenditService $xenditService */
            $xenditService = app(XenditService::class);
        } catch (\Throwable $exception) {
            return false;
        }

        $existingPendingPayment = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('gateway', 'xendit')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existingPendingPayment && data_get($existingPendingPayment->metadata, 'xendit_invoice_id')) {
            $xenditInvoiceId = (string) data_get($existingPendingPayment->metadata, 'xendit_invoice_id');
            $invoiceDetails = $xenditService->getInvoice($xenditInvoiceId);
            $status = strtoupper((string) ($invoiceDetails['status'] ?? ''));

            if (in_array($status, ['SETTLED', 'PAID'], true)) {
                $existingPendingPayment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'verified_at' => now(),
                ]);
                if (! $invoice->is_paid) {
                    $invoice->markAsPaid();
                    $invoice->refresh();
                }

                return response()->json([
                    'success' => true,
                    'data' => $this->invoiceService->formatInvoice($invoice),
                    'payment' => [
                        'id' => $existingPendingPayment->id,
                        'uuid' => $existingPendingPayment->uuid,
                        'gateway' => $existingPendingPayment->gateway,
                        'gatewayReference' => $existingPendingPayment->gateway_reference,
                        'paymentMethod' => $existingPendingPayment->payment_method,
                        'status' => 'completed',
                        'amount' => (float) $existingPendingPayment->amount,
                    ],
                    'flow' => [
                        'mode' => 'hosted',
                        'provider' => 'xendit',
                        'hostedCheckoutUrl' => (string) data_get($existingPendingPayment->metadata, 'invoice_url', ''),
                        'successRedirectUrl' => $successUrl,
                        'failureRedirectUrl' => $failureUrl,
                    ],
                ]);
            }

            if (! in_array($status, ['EXPIRED', 'FAILED'], true)) {
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
                        'provider' => 'xendit',
                        'hostedCheckoutUrl' => (string) data_get($existingPendingPayment->metadata, 'invoice_url', ''),
                        'successRedirectUrl' => $successUrl,
                        'failureRedirectUrl' => $failureUrl,
                    ],
                ]);
            }
        }

        $company = $invoice->company;
        $companyName = (string) ($company->name ?? 'Customer');
        // Use user email as fallback if company email doesn't exist (company doesn't have email column)
        $companyEmail = (string) (auth()->user()?->email ?? 'noreply@arcav.com');
        $externalId = sprintf('invoice-%d-%s', $invoice->id, Str::lower(Str::random(10)));
        
        $result = $xenditService->createInvoice([
            'external_id' => $externalId,
            'amount' => (int) round((float) $invoice->amount_due),
            'description' => 'Invoice '.$invoice->invoice_number,
            'customer_name' => $companyName,
            'customer_email' => $companyEmail,
            'currency' => 'IDR',
            'items' => $this->buildXenditInvoiceItems($invoice),
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'company_id' => $companyId,
                'source' => 'company_invoice_hosted_checkout',
            ],
        ]);

        if (! $result || empty($result['id']) || empty($result['invoice_url'])) {
            return false;
        }

        $payment = Payment::query()->create([
            'company_id' => $companyId,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id' => $invoice->id,
            'amount' => (float) $invoice->amount_due,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => $this->mapXenditChannelToPaymentMethod($paymentMethod),
            'gateway' => 'xendit',
            'gateway_reference' => (string) $result['id'],
            'metadata' => [
                'xendit_invoice_id' => (string) $result['id'],
                'xendit_external_id' => $externalId,
                'xendit_channel_hint' => $paymentMethod,
                'invoice_url' => (string) $result['invoice_url'],
                'checkout_mode' => 'xendit_hosted',
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
                'provider' => 'xendit',
                'hostedCheckoutUrl' => (string) $result['invoice_url'],
                'successRedirectUrl' => $successUrl,
                'failureRedirectUrl' => $failureUrl,
            ],
        ]);
    }

    private function shouldUseXenditCheckout(string $gatewayMode, Request $request): bool
    {
        if ($this->shouldForceLocalMockCheckout($request)) {
            return false;
        }

        if ($gatewayMode === 'mock') {
            return false;
        }

        if ($gatewayMode === 'xendit') {
            return (bool) config('services.xendit.api_key');
        }

        return (bool) config('services.xendit.api_key');
    }

    private function canUseMockCheckout(Request $request): bool
    {
        if ($this->isNgrokRuntime($request)) {
            return false;
        }

        if ($this->shouldForceLocalMockCheckout($request)) {
            return true;
        }

        return (bool) config('app.mock_payments_enabled');
    }

    private function shouldForceLocalMockCheckout(Request $request): bool
    {
        return app()->isLocal() && ! $this->isNgrokRuntime($request);
    }

    private function isNgrokRuntime(Request $request): bool
    {
        $hosts = [];

        $requestHost = strtolower((string) $request->getHost());
        if ($requestHost !== '') {
            $hosts[] = $requestHost;
        }

        $forwardedHost = strtolower(trim((string) $request->header('X-Forwarded-Host', '')));
        if ($forwardedHost !== '') {
            foreach (explode(',', $forwardedHost) as $host) {
                $host = trim($host);
                if ($host !== '') {
                    $hosts[] = $host;
                }
            }
        }

        $appUrlHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appUrlHost !== '') {
            $hosts[] = $appUrlHost;
        }

        foreach ($hosts as $host) {
            if (str_contains($host, 'ngrok')) {
                return true;
            }
        }

        return false;
    }

    private function mapXenditChannelToPaymentMethod(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'e_wallet', 'paylater', 'qr_code' => 'e_wallet',
            'card' => 'credit_card',
            default => 'bank_transfer',
        };
    }

    /**
     * Build Xendit items array from invoice with tax breakdown
     * 
     * @param Invoice $invoice
     * @return array Items array for Xendit API
     */
    private function buildXenditInvoiceItems(Invoice $invoice): array
    {
        $items = [];

        // Base subscription/invoice item
        $baseAmount = (float) $invoice->amount_due;
        $taxRate = (float) ($invoice->billing_tax_rate_snapshot ?? 0);

        if ($taxRate > 0) {
            // `amount_due` is tax-inclusive, so split the tax portion back out for gateway itemization.
            $baseWithoutTax = round($baseAmount / (1 + ($taxRate / 100)), 0);
            $taxAmount = round($baseAmount - $baseWithoutTax, 0);

            $items[] = [
                'name' => 'Subscription / Invoice',
                'quantity' => 1,
                'price' => (int) $baseWithoutTax,
            ];

            $items[] = [
                'name' => "Tax ({$taxRate}%)",
                'quantity' => 1,
                'price' => (int) $taxAmount,
            ];
        } else {
            // No tax, just one line item
            $items[] = [
                'name' => 'Subscription / Invoice',
                'quantity' => 1,
                'price' => (int) $baseAmount,
            ];
        }

        return $items;
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
}

