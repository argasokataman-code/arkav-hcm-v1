<?php

namespace Tests\Feature;

use App\Mail\PaymentSuccessMailable;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoicePaidActivatesSubscriptionTest extends TestCase
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

    public function test_mark_paid_activates_pending_payment_subscription(): void
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

        $owner = User::query()->create([
            'name' => 'Payment Owner',
            'email' => 'payment.owner@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'PAY01',
            'name' => 'Pay Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => $owner->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $sub = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'pending_payment',
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(7),
            'trial_ends_at' => null,
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
            ->putJson("/v1/saas/invoices/{$invoice->uuid}/mark-paid")
            ->assertOk()
            ->assertJsonPath('success', true);

        $sub->refresh();
        $this->assertSame('active', $sub->status);
        $this->assertNull($sub->trial_ends_at);
        $this->assertNotNull($sub->starts_at);
        $this->assertNotNull($sub->ends_at);
        $this->assertTrue($sub->ends_at->isFuture());

        Mail::assertSent(PaymentSuccessMailable::class, function (PaymentSuccessMailable $mail) use ($invoice): bool {
            return (int) $mail->invoice->id === (int) $invoice->id;
        });
    }
}

