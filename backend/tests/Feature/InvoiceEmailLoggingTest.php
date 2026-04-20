<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceEmailLoggingTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Admin User',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $adminLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);
        $this->adminToken = (string) $adminLogin->json('data.accessToken');
    }

    public function test_send_email_endpoint_logs_success(): void
    {
        Mail::fake();

        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'PAID01',
            'name' => 'Paid Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $sub = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'pending_payment',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $sub->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 100000,
            'notes' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson("/v1/saas/invoices/{$invoice->uuid}/send-email", [
                'email' => 'billing@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('invoice_email_logs', [
            'invoice_id' => $invoice->id,
            'to_email' => 'billing@example.com',
            'status' => 'sent',
        ]);
    }

    public function test_show_invoice_returns_full_email_history_for_admin_detail_page(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'DETAIL01',
            'name' => 'Detail Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => 1,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'pending_payment',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 100000,
            'notes' => 'detail test',
        ]);

        $firstLog = InvoiceEmailLog::query()->create([
            'invoice_id' => $invoice->id,
            'to_email' => 'billing@example.com',
            'status' => 'failed',
            'provider_message_id' => null,
            'error_message' => 'SMTP timeout',
        ]);

        $latestLog = InvoiceEmailLog::query()->create([
            'invoice_id' => $invoice->id,
            'to_email' => 'billing@example.com',
            'status' => 'sent',
            'provider_message_id' => 'msg-123',
            'error_message' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/v1/saas/invoices/'.$invoice->uuid)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $invoice->uuid)
            ->assertJsonPath('data.subscription.uuid', $subscription->uuid)
            ->assertJsonPath('data.latestEmail.uuid', $latestLog->uuid);

        $this->assertCount(2, $response->json('data.emailLogs'));
        $this->assertSame($latestLog->uuid, $response->json('data.emailLogs.0.uuid'));
        $this->assertSame($firstLog->uuid, $response->json('data.emailLogs.1.uuid'));
        $this->assertSame('SMTP timeout', $response->json('data.emailLogs.1.errorMessage'));
    }
}

