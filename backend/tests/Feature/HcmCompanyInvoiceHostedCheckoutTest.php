<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\HcmCompanyInvoiceController;
use App\Http\Controllers\Api\MockPaymentController;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class HcmCompanyInvoiceHostedCheckoutTest extends TestCase
{
    use RefreshDatabase;

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

        $request = Request::create("/v1/hcm/billing/invoices/{$invoice->id}/mock-hosted-checkout", 'POST');
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

        $this->assertInstanceOf(StreamedResponse::class, $response);
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
}