<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Billing\HcmCompanyInvoiceController;
use App\Http\Controllers\Api\Payment\MockPaymentController;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class HcmCompanyInvoiceHostedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.mock_payments_enabled' => true]);
    }

    public function test_hosted_checkout_for_existing_invoice_returns_gateway_url_and_can_be_settled(): void
    {
        $company = $this->createIsolatedTestCompany();
        $this->createHcmAdminWithCompany([
            'email' => 'invoice-hosted-owner@example.com',
        ], $company);
        $user = User::query()->where('email', 'invoice-hosted-owner@example.com')->firstOrFail();

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => null,
            'plan_code' => 'starter',
            'status' => 'pending_payment',
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'billing_cycle' => 'yearly',
            'amount' => 1200000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 1200000,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => 'Hosted checkout invoice',
        ]);

        $request = Request::create("/v1/hcm/billing/invoices/{$invoice->id}/mock-hosted-checkout", 'POST', [
            'gatewayMode' => 'mock',
        ]);
        $request->attributes->set('activeCompanyId', $company->id);
        $request->attributes->set('activeCompany', $company);
        $request->attributes->set('activeCompanyCode', $company->code);
        $request->setUserResolver(fn () => $user);

        $response = app(HcmCompanyInvoiceController::class)->mockHostedCheckout($request, $invoice->id);
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());

        $this->assertTrue($payload['success']);
        $this->assertSame('hosted', $payload['flow']['mode']);
        $this->assertStringContainsString('mock-hosted-payment.html', $payload['flow']['hostedCheckoutUrl']);
        $this->assertNotEmpty($payload['flow']['callbackToken']);

        $payment = Payment::query()->where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame($payment->uuid, $payload['payment']['uuid']);

        $webhookRequest = Request::create('/v1/mock/webhook/charge-succeeded', 'POST', [
            'payment_id' => $payment->uuid,
            'callback_token' => $payload['flow']['callbackToken'],
        ]);

        $webhookResponse = app(MockPaymentController::class)->simulateChargeSucceeded($webhookRequest);
        $webhookPayload = json_decode((string) $webhookResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $webhookResponse->getStatusCode());
        $this->assertTrue($webhookPayload['success']);

        $payment->refresh();
        $invoice->refresh();
        $subscription->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertTrue($invoice->is_paid);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('active', $subscription->status);
    }

    public function test_legacy_unpaid_subscription_invoice_is_healed_with_tax_before_display_and_payment(): void
    {
        $company = $this->createIsolatedTestCompany();
        $this->createHcmAdminWithCompany([
            'email' => 'invoice-legacy-tax-owner@example.com',
        ], $company);
        $user = User::query()->where('email', 'invoice-legacy-tax-owner@example.com')->firstOrFail();

        HcmBillingTaxPolicy::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'billing_month' => now()->format('Y-m'),
            'billing_cycle_type' => 'monthly',
            'tax_rate_percentage' => 11,
            'base_calculation_method' => 'invoice_amount_due',
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => now()->endOfMonth()->toDateString(),
            'status' => 'active',
            'notes' => json_encode([
                'global_rates' => [
                    'subscription_tax_rate' => 11,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => null,
            'plan_code' => 'legacy-renewal',
            'status' => 'inactive',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDays(7),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount_due' => 100000,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => 'Legacy renewal invoice without tax snapshot',
        ]);

        $controller = app(HcmCompanyInvoiceController::class);

        $showRequest = Request::create('/v1/hcm/billing/invoices/'.$invoice->id, 'GET');
        $showRequest->attributes->set('activeCompanyId', $company->id);
        $showRequest->attributes->set('activeCompany', $company);
        $showRequest->attributes->set('activeCompanyCode', $company->code);
        $showRequest->setUserResolver(fn () => $user);

        $showResponse = $controller->show($showRequest, $invoice->id);
        $showPayload = json_decode((string) $showResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $showResponse->getStatusCode());
        $this->assertTrue($showPayload['success']);
        $this->assertEqualsWithDelta(111000, (float) ($showPayload['data']['amountDue'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(11, (float) ($showPayload['data']['billingTaxRateSnapshot'] ?? 0), 0.01);

        $checkoutRequest = Request::create('/v1/hcm/billing/invoices/'.$invoice->id.'/mock-hosted-checkout', 'POST');
        $checkoutRequest->attributes->set('activeCompanyId', $company->id);
        $checkoutRequest->attributes->set('activeCompany', $company);
        $checkoutRequest->attributes->set('activeCompanyCode', $company->code);
        $checkoutRequest->setUserResolver(fn () => $user);

        $checkoutResponse = $controller->mockHostedCheckout($checkoutRequest, $invoice->id);
        $checkoutPayload = json_decode((string) $checkoutResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $checkoutResponse->getStatusCode());
        $this->assertTrue($checkoutPayload['success']);
        $this->assertEqualsWithDelta(111000, (float) ($checkoutPayload['data']['amountDue'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(111000, (float) ($checkoutPayload['payment']['amount'] ?? 0), 0.01);

        $invoice->refresh();
        $this->assertEqualsWithDelta(111000, (float) $invoice->amount_due, 0.01);
        $this->assertEqualsWithDelta(11, (float) ($invoice->billing_tax_rate_snapshot ?? 0), 0.01);
    }

    public function test_company_invoice_download_returns_pdf_binary_for_existing_generated_pdf(): void
    {
        $company = $this->createIsolatedTestCompany();
        $this->createHcmAdminWithCompany([
            'email' => 'invoice-download-owner@example.com',
        ], $company);
        $user = User::query()->where('email', 'invoice-download-owner@example.com')->firstOrFail();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 199000,
            'status' => 'paid',
            'is_paid' => true,
            'paid_date' => now(),
            'notes' => 'PDF download regression test',
        ]);

        $pdfPath = app(InvoiceService::class)->generatePdf($invoice);
        $this->assertNotNull($pdfPath);
        $invoice->refresh();
        $this->assertNotEmpty($invoice->pdf_path);

        $request = Request::create('/v1/hcm/billing/invoices/'.$invoice->id.'/download', 'GET');
        $request->attributes->set('activeCompanyId', $company->id);
        $request->attributes->set('activeCompany', $company);
        $request->attributes->set('activeCompanyCode', $company->code);
        $request->setUserResolver(fn () => $user);

        $response = app(HcmCompanyInvoiceController::class)->download($request, $invoice->id);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString(basename($invoice->pdf_path), (string) $response->headers->get('content-disposition'));
    }

    public function test_company_invoice_detail_exposes_package_cycle_and_next_billing_metadata(): void
    {
        $company = $this->createIsolatedTestCompany();
        $this->createHcmAdminWithCompany([
            'email' => 'invoice-detail-owner@example.com',
        ], $company);
        $user = User::query()->where('email', 'invoice-detail-owner@example.com')->firstOrFail();

        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Starter package',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => 'starter',
            'status' => 'active',
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->addMonth()->startOfDay(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 199000,
            'status' => 'paid',
            'is_paid' => true,
            'paid_date' => now(),
            'notes' => 'Monthly starter invoice',
        ]);

        $request = Request::create('/v1/hcm/billing/invoices/'.$invoice->id, 'GET');
        $request->attributes->set('activeCompanyId', $company->id);
        $request->attributes->set('activeCompany', $company);
        $request->attributes->set('activeCompanyCode', $company->code);
        $request->setUserResolver(fn () => $user);

        $response = app(HcmCompanyInvoiceController::class)->show($request, $invoice->id);
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame($subscription->id, $payload['data']['subscriptionId']);
        $this->assertSame('starter', $payload['data']['packageCode']);
        $this->assertSame('Starter', $payload['data']['packageName']);
        $this->assertSame('monthly', $payload['data']['billingCycle']);
        $this->assertSame('Bulanan', $payload['data']['billingCycleLabel']);
        $this->assertSame($subscription->ends_at?->toDateString(), $payload['data']['nextBillingDate']);
    }

    public function test_non_admin_user_cannot_access_company_invoice_endpoints(): void
    {
        $company = $this->createIsolatedTestCompany();

        $employee = User::factory()->create([
            'email' => 'invoice-non-admin@example.com',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 150000,
            'status' => 'draft',
            'is_paid' => false,
        ]);

        $controller = app(HcmCompanyInvoiceController::class);

        $makeRequest = function (string $uri, string $method) use ($company, $employee): Request {
            $request = Request::create($uri, $method);
            $request->attributes->set('activeCompanyId', $company->id);
            $request->attributes->set('activeCompany', $company);
            $request->attributes->set('activeCompanyCode', $company->code);
            $request->setUserResolver(fn () => $employee);

            return $request;
        };

        $indexResponse = $controller->index($makeRequest('/v1/hcm/billing/invoices', 'GET'));
        $this->assertSame(403, $indexResponse->getStatusCode());
        $this->assertSame('AUTH_FORBIDDEN', data_get(json_decode((string) $indexResponse->getContent(), true), 'error.code'));

        $showResponse = $controller->show($makeRequest('/v1/hcm/billing/invoices/'.$invoice->id, 'GET'), $invoice->id);
        $this->assertSame(403, $showResponse->getStatusCode());
        $this->assertSame('AUTH_FORBIDDEN', data_get(json_decode((string) $showResponse->getContent(), true), 'error.code'));

        $downloadResponse = $controller->download($makeRequest('/v1/hcm/billing/invoices/'.$invoice->id.'/download', 'GET'), $invoice->id);
        $this->assertSame(403, $downloadResponse->getStatusCode());
        $this->assertSame('AUTH_FORBIDDEN', data_get(json_decode((string) $downloadResponse->getContent(), true), 'error.code'));

        $checkoutResponse = $controller->mockHostedCheckout($makeRequest('/v1/hcm/billing/invoices/'.$invoice->id.'/mock-hosted-checkout', 'POST'), $invoice->id);
        $this->assertSame(403, $checkoutResponse->getStatusCode());
        $this->assertSame('AUTH_FORBIDDEN', data_get(json_decode((string) $checkoutResponse->getContent(), true), 'error.code'));

        $mockPayResponse = $controller->mockPay($makeRequest('/v1/hcm/billing/invoices/'.$invoice->id.'/mock-pay', 'POST'), $invoice->id);
        $this->assertSame(403, $mockPayResponse->getStatusCode());
        $this->assertSame('AUTH_FORBIDDEN', data_get(json_decode((string) $mockPayResponse->getContent(), true), 'error.code'));
    }
}