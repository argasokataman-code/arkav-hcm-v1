<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Payment\MockPaymentController;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MockPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.mock_payments_enabled' => true]);
    }

    public function test_create_payment_accepts_invoice_uuid_and_returns_uuid_payloads(): void
    {
        $company = $this->createIsolatedTestCompany();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => null,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 150000,
            'status' => 'draft',
            'notes' => 'Mock invoice',
        ]);

        $request = Request::create('/v1/mock/payments/create', 'POST', [
            'invoice_id' => $invoice->uuid,
            'amount' => 150000,
            'payment_method' => 'mock_card',
            'simulate_failure' => false,
        ]);
        $request->attributes->set('activeCompanyId', $company->id);

        $response = app(MockPaymentController::class)->createPayment($request);
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame($invoice->uuid, $payload['data']['invoice']['uuid']);

        $invoice->refresh();
        $payment = Payment::query()->latest('id')->first();

        $this->assertNotNull($payment);
        $this->assertSame($payment->uuid, $payload['data']['payment']['uuid']);
        $this->assertTrue($invoice->is_paid);
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($payment->verified_at);
    }

    public function test_simulate_charge_succeeded_accepts_payment_uuid_and_marks_invoice_paid(): void
    {
        $company = $this->createIsolatedTestCompany();

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => null,
            'plan_code' => 'mock-plan',
            'status' => 'pending_payment',
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'billing_cycle' => 'monthly',
            'amount' => 175000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 175000,
            'status' => 'draft',
            'is_paid' => false,
            'notes' => 'Pending webhook invoice',
        ]);

        $payment = Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'amount' => 175000,
            'currency' => 'IDR',
            'status' => 'pending',
            'payment_method' => 'credit_card',
            'gateway' => 'mock',
            'gateway_reference' => 'mock_pending_webhook',
        ]);

        $request = Request::create('/v1/mock/webhook/charge-succeeded', 'POST', [
            'payment_id' => $payment->uuid,
        ]);

        $response = app(MockPaymentController::class)->simulateChargeSucceeded($request);
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame($payment->uuid, $payload['data']['payment_uuid']);
        $this->assertSame($invoice->uuid, $payload['data']['invoice_uuid']);

        $payment->refresh();
        $invoice->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->verified_at);
        $this->assertTrue($invoice->is_paid);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_create_invoice_and_pay_supports_hosted_flow_and_requires_callback_token(): void
    {
        $company = $this->createIsolatedTestCompany();

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => null,
            'plan_code' => 'mock-hosted-plan',
            'status' => 'pending_payment',
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'billing_cycle' => 'monthly',
            'amount' => 225000,
        ]);

        $request = Request::create('/v1/mock/invoices/create-and-pay', 'POST', [
            'amount' => 225000,
            'description' => 'Hosted mock invoice',
            'currency' => 'IDR',
            'flow_mode' => 'hosted',
        ]);
        $request->attributes->set('activeCompanyId', $company->id);

        $createResponse = app(MockPaymentController::class)->createInvoiceAndPay($request);
        $createPayload = json_decode((string) $createResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $createResponse->getStatusCode());
        $this->assertTrue($createPayload['success']);
        $this->assertSame('hosted', $createPayload['data']['flow']['mode']);
        $this->assertSame('pending', $createPayload['data']['payment']['status']);
        $this->assertSame('draft', $createPayload['data']['invoice']['status']);
        $this->assertNotEmpty($createPayload['data']['flow']['hosted_checkout_url']);
        $this->assertNotEmpty($createPayload['data']['flow']['callback_token']);

        $paymentUuid = $createPayload['data']['payment']['uuid'];
        $callbackToken = $createPayload['data']['flow']['callback_token'];

        $invalidWebhookRequest = Request::create('/v1/mock/webhook/charge-succeeded', 'POST', [
            'payment_id' => $paymentUuid,
        ]);

        $invalidWebhookResponse = app(MockPaymentController::class)->simulateChargeSucceeded($invalidWebhookRequest);
        $invalidWebhookPayload = json_decode((string) $invalidWebhookResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(403, $invalidWebhookResponse->getStatusCode());
        $this->assertFalse($invalidWebhookPayload['success']);
        $this->assertSame('CALLBACK_TOKEN_INVALID', $invalidWebhookPayload['error']['code']);

        $validWebhookRequest = Request::create('/v1/mock/webhook/charge-succeeded', 'POST', [
            'payment_id' => $paymentUuid,
            'callback_token' => $callbackToken,
        ]);

        $validWebhookResponse = app(MockPaymentController::class)->simulateChargeSucceeded($validWebhookRequest);
        $validWebhookPayload = json_decode((string) $validWebhookResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $validWebhookResponse->getStatusCode());
        $this->assertTrue($validWebhookPayload['success']);
        $this->assertSame($paymentUuid, $validWebhookPayload['data']['payment_uuid']);

        $payment = Payment::query()->where('uuid', $paymentUuid)->firstOrFail();
        $invoice = Invoice::query()->where('uuid', $validWebhookPayload['data']['invoice_uuid'])->firstOrFail();

        $payment->refresh();
        $invoice->refresh();
        $subscription->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertTrue($invoice->is_paid);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('active', $subscription->status);
    }
}