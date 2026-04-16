<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
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
            'package_id' => $package->id,
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
            ->postJson("/v1/saas/invoices/{$invoice->id}/send-email", [
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
}

